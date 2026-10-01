"""UI/UX audit: run existing real-app suites sequentially on disposable fixtures.
No product changes. Existing assertion failures remain failures for review.
"""
import argparse, hashlib, importlib.util, json, os, re, shutil, socket, subprocess, sys, time
from datetime import datetime, timezone
from pathlib import Path

ROOT=Path(__file__).resolve().parent.parent
OUT=ROOT/'artifacts/ui-ux-audit-2026-09-30/functional'
EXTRA=['beginner_journey_app','item_sources_app','improvement_app','world_target_navigation_app','map_search_app','world_overview_app','map_alliance_labels_app','territory_main_app','territory_main_report','monster_report_app','bug_reports_app','reward_dialog_app','scene_transition_app','hospital_quick_heal_app','gathering_occupation_app']
FLAGS={'territory_main_report':['--territory'],'monster_report_app':['--monster-reports'],'hospital_quick_heal_app':['--hospital'],'map_search_app':['--regional-bosses','--chat','--map-search'],'research_app':['--speed-bonuses']}
def now():return datetime.now(timezone.utc).isoformat()
def manifest():
    files=[p for area in ['assets/js','assets/css','views','src','data/i18n'] for p in (ROOT/area).rglob('*') if p.is_file() and p.suffix in ['.js','.css','.php','.json']]
    return {str(p.relative_to(ROOT)).replace('\\','/'):hashlib.sha256(p.read_bytes()).hexdigest() for p in files}
def main():
    global OUT
    sys.stdout.reconfigure(encoding='utf-8',errors='replace')
    parser=argparse.ArgumentParser();parser.add_argument('--only',nargs='*');parser.add_argument('--extra-only',action='store_true');parser.add_argument('--recheck',action='store_true');parser.add_argument('--final-recheck',action='store_true');parser.add_argument('--resume',action='store_true');parser.add_argument('--de',nargs='*',default=[]);parser.add_argument('--current-rules',nargs='*',default=[]);parser.add_argument('--navigation-timeout',type=int);parser.add_argument('--output',type=Path,help='Separate evidence directory; preserves the original audit');args=parser.parse_args()
    if args.recheck:OUT=ROOT/'artifacts/ui-ux-audit-2026-09-30/functional-rechecks'
    if args.final_recheck:
        if not args.recheck:parser.error('--final-recheck requires --recheck')
        OUT=ROOT/'artifacts/ui-ux-audit-2026-09-30/functional-final-rechecks'
    if args.output:OUT=args.output.resolve()
    OUT.mkdir(parents=True,exist_ok=True)
    spec=importlib.util.spec_from_file_location('reviewed',ROOT/'tests/run_alpha.py');reviewed=importlib.util.module_from_spec(spec);spec.loader.exec_module(reviewed)
    names=EXTRA if args.extra_only else list(dict.fromkeys(reviewed.APP+EXTRA))
    if args.only:names=[n for n in names if n in args.only]
    php=os.environ.get('PHP_BINARY','C:/xampp/php/php.exe');node=shutil.which('node');env=os.environ.copy()
    env['PLAYWRIGHT_MODULE']='C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'
    env['NODE_PATH']=str(Path(env['PLAYWRIGHT_MODULE']).parent);env['PLAYWRIGHT_CHANNEL']='chrome';env['PHP_BINARY']=php
    for executable in [Path('C:/Program Files/Google/Chrome/Application/chrome.exe'),Path('C:/Program Files (x86)/Google/Chrome/Application/chrome.exe')]:
        if executable.exists():env['BROWSER_EXECUTABLE_PATH']=str(executable);break
    if args.resume and (OUT/'manifest-start.json').exists():baseline=json.loads((OUT/'manifest-start.json').read_text(encoding='utf-8'))['files']
    else:
        baseline=manifest();(OUT/'manifest-start.json').write_text(json.dumps({'at':now(),'files':baseline},indent=2),encoding='utf-8')
    results=json.loads((OUT/'results.json').read_text(encoding='utf-8')) if args.resume and (OUT/'results.json').exists() else []
    if args.resume:names=[n for n in names if n not in {r['suite'] for r in results}]
    for name in names:
        print('RUN '+name,flush=True);start=time.monotonic();fixture=None;fixture_log=None;code=-1;error=None
        source=(ROOT/'tests'/f'{name}.cjs').read_text(encoding='utf-8-sig');current=env.copy()
        if args.output:current['UI_UX_OUTPUT_ROOT']=str(OUT/'screenshots')
        try:
            if 'spawn(' not in source:
                variable=re.search(r'const base\s*=\s*process.env\.([A-Z_]+)',source)
                if not variable:raise RuntimeError('Unreviewed fixture URL dependency')
                with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
                comments='\n'.join(line for line in source.splitlines()[:8] if line.startswith('//'))
                flags=sorted(set(re.findall(r'--[a-z]+(?:-[a-z]+)*',comments))-{'--port'})
                flags=list(dict.fromkeys(flags+FLAGS.get(name,[])+['--appearance']))
                fixture_log=open(OUT/f'{name}-fixture.log','w+',encoding='utf-8')
                fixture=subprocess.Popen([php,str(ROOT/'tools/preview-feature-fixture.php'),f'--port={port}',*flags],cwd=ROOT,stdin=subprocess.PIPE,stdout=fixture_log,stderr=fixture_log,text=True)
                deadline=time.monotonic()+90
                while True:
                    fixture_log.seek(0)
                    if 'Synthetic preview ready' in fixture_log.read():break
                    if fixture.poll() is not None or time.monotonic()>deadline:raise RuntimeError('Fixture did not become ready')
                    time.sleep(.15)
                current[variable[1]]=f'http://127.0.0.1:{port}'
            with open(OUT/f'{name}.log','w',encoding='utf-8') as log:
                if args.final_recheck:current['UI_UX_RECHECK_STAGE']='final'
                command=[node,str(ROOT/'tests/ui_ux_legacy_launcher.cjs'),name]+(['--locale=de'] if name in args.de else []) if args.recheck else [node,str(ROOT/'tests'/f'{name}.cjs')]
                if args.recheck and name in args.current_rules:command.append('--current-rules')
                if args.recheck and args.navigation_timeout:command.append('--navigation-timeout='+str(args.navigation_timeout))
                result=subprocess.run(command,cwd=ROOT,env=current,stdout=log,stderr=log,text=True)
                code=result.returncode
        except Exception as exc:error=str(exc)
        finally:
            if fixture is not None and fixture.poll() is None:fixture.communicate('\n')
            if fixture_log is not None:fixture_log.close()
        logfile=OUT/f'{name}.log';log=logfile.read_text(encoding='utf-8',errors='replace') if logfile.exists() else ''
        passed=code==0 and error is None and not any(x in log for x in ['PHP Fatal error:','FAIL '])
        record={'suite':name,'passed':passed,'exit':code,'error':error,'seconds':round(time.monotonic()-start,2),'completed_at':now(),'log':str(logfile.relative_to(ROOT)),'audit_launcher':args.recheck,'explicit_locale':'de' if name in args.de else None}
        record['current_rules']=name in args.current_rules;record['navigation_timeout_ms']=args.navigation_timeout if args.recheck else None
        results.append(record);(OUT/f'{name}.result.json').write_text(json.dumps(record,indent=2),encoding='utf-8');(OUT/'results.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
        print(('PASS ' if passed else 'FAIL ')+name+f' ({record["seconds"]}s)',flush=True)
        if not passed:print(error or '\n'.join(log.splitlines()[-8:]),flush=True)
    after=manifest();(OUT/'manifest-end.json').write_text(json.dumps({'at':now(),'files':after,'changed_during_run':[k for k in baseline if baseline[k]!=after.get(k)]},indent=2),encoding='utf-8')
    print(f'{sum(x["passed"] for x in results)}/{len(results)} selected real-app suites passed',flush=True)
    return 0 if all(r['passed'] for r in results) else 1
if __name__=='__main__':raise SystemExit(main())
