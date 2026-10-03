"""Repeatable alpha checks. Explicit reviewed suites; databases are disposable.
Requires local PHP/MySQL plus Node, Playwright and Chromium for browser groups.
Run only one copy: game advisory locks are shared across test databases.
"""
import argparse, json, os, re, shutil, socket, subprocess, sys, time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BACKEND = ['admin_cli', 'active_effects', 'admin_password_change', 'alpha_access', 'alpha_keys_admin', 'alpha_registration', 'alpha_waitlist', 'apache_request_limits', 'army_receipts', 'backoffice_integration', 'balance_import', 'balance_lifecycle', 'battle_luck', 'battle_preview', 'bug_reports', 'building_durations', 'building_plots', 'charm_lifecycle', 'city_combat', 'city_placement', 'city_placement_paths', 'combat_reports', 'community_systems', 'congress_lifecycle', 'crystal_economy', 'daily_chests', 'defense_lifecycle', 'dungeon_lifecycle', 'dungeon_rules', 'expedition_lifecycle', 'full_progression', 'gathering_lifecycle', 'guide_progression', 'hospital_healing', 'inventory_bulk', 'inventory_rewards', 'item_catalog', 'kingdom_regressions', 'land_progression', 'local_free_skins', 'localization', 'lord_talents', 'mailbox', 'map_land_access', 'map_search', 'march_composition', 'march_skins', 'migration_lifecycle', 'monster_balance', 'monster_rallies', 'monster_reports', 'monster_reward_balance', 'multiworld_integration', 'mvp_rules', 'oauth_identity', 'queue_speedups', 'regional_bosses', 'regional_rewards', 'research_combat', 'research_effects', 'research_live_services', 'research_snapshot', 'return_summary', 'reward_admin', 'reward_preview', 'security_accounts', 'security_api_matrix', 'security_guard', 'shrine_event', 'theme_bundles', 'trading_shop', 'training_buildings', 'training_costs', 'training_durations', 'training_unlocks', 'treasure_catalog', 'treasure_loadouts', 'treasure_presets', 'troop_march_speeds', 'ui_layout', 'vip_system', 'world_chat', 'world_placement']
APP = ['active_effects_app', 'alpha_final_app', 'army_receipts_app', 'beginner_guide_app', 'building_contrast_app', 'charm_runes_app', 'combat_report_app', 'dungeon_app', 'fantasy_theme_app', 'game_audio_app', 'game_comfort_app', 'gathering_app', 'hospital_app', 'inventory_app', 'inventory_overview_app', 'localization_app', 'mailbox_app', 'march_compact_app', 'march_skin_app', 'march_skin_world_app', 'mobile_pages_app', 'monster_health_app', 'quests_app', 'queue_speedups_app', 'queue_speedups_inventory_app', 'regional_bosses_app', 'research_app', 'scout_report_app', 'training_app', 'training_http_app', 'training_layout', 'training_mobile_layout', 'ui_layout_app']
STATIC = ['alliance_overview_app', 'alliance_territory_app', 'app_polling', 'building_recommendation', 'community_panel', 'congress_panel', 'defense_panel', 'dungeon_panel', 'graphics_quality', 'inventory_overview', 'land_panel', 'layered_village', 'localization_landing', 'localization_pwa', 'march_motion_contract', 'march_skin_collection', 'march_windows', 'name_frames', 'painted_city', 'premium_march_assets', 'profile_layout', 'progression_panel', 'queue_speedup_plan', 'rally_hud', 'reward_catalog', 'skin_picker', 'theme_bundle_shop', 'toast_layout', 'trading_panel', 'training_hud', 'treasure_panel', 'window_panels', 'world_painted', 'world_terrain', 'world_chat_app', 'world_skin_effects', 'world_march_skins']

# Entry routing and the responsive game login are part of the release gate.
BACKEND.append('play_entry')
STATIC.append('play_login_layout')
APP += ['beginner_journey_app','item_sources_app','improvement_app','world_target_navigation_app','map_search_app','world_overview_app','map_alliance_labels_app','territory_main_app','territory_main_report','monster_report_app','bug_reports_app','reward_dialog_app','scene_transition_app','hospital_quick_heal_app','gathering_occupation_app','community_upgrade_app']
STATIC += ['community_chat_app','social_hub_app','hud_activity','locale_delivery','localization_semantics']
STATIC += ['world_sprite_hit_bounds','map_action_history']
BACKEND += ['boss_mechanics','grumwald_rally','rally_boss_skills']
BACKEND.append('alliance_ranks')
BACKEND += ['resource_production', 'starter_quest_rewards', 'economy_http']
APP.append('alliance_ranks_app')
STATIC.append('alliance_ranks_ui')
APP.append('rally_boss_skills_app')
STATIC.append('boss_mechanic_ui')
FIXTURE_FLAGS={'territory_main_report':['--territory'],'monster_report_app':['--monster-reports'],'hospital_quick_heal_app':['--hospital'],'map_search_app':['--regional-bosses','--chat','--map-search'],'research_app':['--speed-bonuses']}

def main():
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    parser = argparse.ArgumentParser()
    parser.add_argument('--group', choices=['backend','app','static','all'], default='all')
    parser.add_argument('--only', nargs='*', help='Restrict to named reviewed suites')
    parser.add_argument('--output',type=Path,help='Write a separate verification run without replacing older audit evidence')
    args = parser.parse_args()
    php = os.environ.get('PHP_BINARY') or shutil.which('php')
    node = shutil.which('node')
    out = args.output.resolve() if args.output else ROOT / 'artifacts/alpha-audit-2026-09-26/final'
    out.mkdir(parents=True, exist_ok=True)
    groups = {'backend':(BACKEND,php,'.php'),'app':(APP,node,'.cjs'),'static':(STATIC,node,'.cjs')}
    results = []
    for group,(names,runtime,extension) in groups.items():
        if args.group not in (group,'all'): continue
        if not runtime: raise SystemExit('Missing runtime for '+group)
        for name in names:
            if args.only and name not in args.only: continue
            started=time.monotonic()
            command=[runtime,str(ROOT/'tests'/(name+extension))]
            # These suites own and dispose their browser HTTP fixtures.
            if name in ('reward_admin','alpha_keys_admin'): command.append('--browser')
            environment=os.environ.copy()
            fixture=None
            fixture_log=None
            try:
                source=(ROOT/'tests'/(name+extension)).read_text(encoding='utf-8-sig')
                if group=='app' and 'spawn(' not in source:
                    variable=re.search(r'const base\s*=\s*process.env.([A-Z_]+)',source)
                    if not variable: raise RuntimeError('Missing reviewed fixture URL for '+name)
                    with socket.socket() as port_socket:
                        port_socket.bind(('127.0.0.1',0)); port=port_socket.getsockname()[1]
                    flags=sorted(set(re.findall(r'--[a-z]+(?:-[a-z]+)*', '\n'.join(line for line in source.splitlines()[:8] if line.startswith('//'))))-{'--port'})
                    flags=list(dict.fromkeys(flags+FIXTURE_FLAGS.get(name,[])))
                    fixture_log=open(out/(name+'-fixture.log'),'w+',encoding='utf-8')
                    fixture=subprocess.Popen([php,str(ROOT/'tools/preview-feature-fixture.php'),'--port='+str(port),'--appearance',*flags],cwd=ROOT,stdin=subprocess.PIPE,stdout=fixture_log,stderr=fixture_log,text=True)
                    deadline=time.monotonic()+60
                    while True:
                        fixture_log.seek(0)
                        if 'Synthetic preview ready' in fixture_log.read(): break
                        if fixture.poll() is not None or time.monotonic()>deadline: raise RuntimeError('Preview did not start: '+name)
                        time.sleep(.1)
                    environment[variable[1]]='http://127.0.0.1:'+str(port)
                result=subprocess.run(command,cwd=ROOT,env=environment,capture_output=True,text=True,encoding='utf-8',errors='replace')
            finally:
                if fixture is not None:
                    fixture.communicate('\n')
                if fixture_log is not None: fixture_log.close()
            log=result.stdout+result.stderr
            (out/(name+'.log')).write_text(log,encoding='utf-8')
            ok=result.returncode==0 and not any(marker in log for marker in ('PHP Fatal error:','PHP Warning:','Fatal error:','FAIL '))
            results.append({'suite':name,'group':group,'passed':ok,'exit':result.returncode,'seconds':round(time.monotonic()-started,2),'completed_at':datetime.now(timezone.utc).isoformat()})
            (out/(name+'.result.json')).write_text(json.dumps(results[-1],indent=2),encoding='utf-8')
            print(('PASS' if ok else 'FAIL')+' '+name,flush=True)
            if not ok: print('\n'.join(log.splitlines()[-8:]),flush=True)
            (out/(args.group+'-results.json')).write_text(json.dumps(results,indent=2),encoding='utf-8')
    if not results: raise SystemExit('No reviewed suites selected')
    print(str(sum(r['passed'] for r in results))+'/'+str(len(results))+' passed',flush=True)
    return 0 if all(r['passed'] for r in results) else 1

if __name__ == '__main__':
    raise SystemExit(main())
