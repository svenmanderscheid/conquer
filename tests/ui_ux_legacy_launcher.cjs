'use strict';
// Audit-only launcher. Keeps original assertions and original __filename/__dirname.
// Run only in the root-coordinated DB slot. --inspect performs no browser/fixture run.
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),Module=require('node:module'),crypto=require('node:crypto'),vm=require('node:vm');
const suite=process.argv[2],args=process.argv.slice(3);
if(!/^[a-z_]+$/.test(suite||'')||args.some(a=>!['--locale=de','--inspect','--current-rules','--navigation-timeout=45000'].includes(a)))throw Error('Usage: node tests/ui_ux_legacy_launcher.cjs <suite> [--locale=de] [--current-rules] [--navigation-timeout=45000] [--inspect]');
const currentRules=args.includes('--current-rules'),navigationTimeout=args.includes('--navigation-timeout=45000')?45000:null;
const finalStage=process.env.UI_UX_RECHECK_STAGE==='final';
const root=path.resolve(__dirname,'..'),filename=path.join(__dirname,suite+'.cjs'),output=path.join(process.env.UI_UX_OUTPUT_ROOT||path.join(root,'artifacts/ui-ux-audit-2026-09-30',process.env.UI_UX_RECHECK_STAGE==='final'?'rechecks-final':'rechecks'),suite,...(currentRules?['current-rules']:[])),locale=args.includes('--locale=de')?'de':null;
if(filename===__filename)throw Error('Cannot launch itself');
const original=fs.readFileSync(filename,'utf8');let source=original;
fs.mkdirSync(output,{recursive:true});
const replacements=[];
const rules=currentRules?require('./ui_ux_current_rule_overrides.cjs')(source,suite,root):null;
if(rules)source=rules.source;
// A small balanced scanner changes only directory initializers, including nested mkdtempSync.
function expressionEnd(start){let depth=0,quote=null,escape=false;
 for(let i=start;i<source.length;i++){const c=source[i];if(quote){if(escape){escape=false;continue;}if(c==='\\'){escape=true;continue;}if(c===quote)quote=null;continue;}if("'\"`".includes(c)){quote=c;continue;}if(c==='(')depth++;if(c===')'&&--depth===0)return i+1;}
 throw Error('Unbalanced output initializer');
}
const matches=[...source.matchAll(/\b(out|output|shotDir)\s*=\s*((?:path\.(?:join|resolve)|fs\.mkdtempSync)\()/g)].reverse();
for(const match of matches){const start=match.index+match[0].length-match[2].length,end=expressionEnd(start),before=source.slice(start,end),after=JSON.stringify(output);replacements.push({variable:match[1],before,after,offset:start});source=source.slice(0,start)+after+source.slice(end);}
const hash=text=>crypto.createHash('sha256').update(text).digest('hex');
new vm.Script(source,{filename});
const manifest={suite,original_file:filename,original_sha256:hash(original),compiled_sha256:hash(source),locale_preference:locale,navigation_timeout_ms:navigationTimeout,assertions_changed:rules?.assertionsChanged??false,assertions_removed:false,expectation_mode:rules?.expectationMode||'original',output,replacements:[...replacements.reverse(),...(rules?.replacements||[])],rule_evidence:rules?.evidence||[],runtime_adaptations:['os.tmpdir scoped to suite output','Playwright screenshots redirected to suite output'],started_at:new Date().toISOString(),inspect_only:args.includes('--inspect')};
if(locale)manifest.runtime_adaptations.push('Context init adds explicit DE localStorage and locale cookie only when no stored preference exists');
if(navigationTimeout)manifest.runtime_adaptations.push('Page default navigation timeout is at least 45000ms; later setDefaultNavigationTimeout calls preserve this floor. Explicit per-call timeouts and general assertion timeouts remain unchanged.');
if(finalStage)manifest.runtime_adaptations.push('Diagnostic-only listener records API responses with status>=400 and page errors; no response, request, timing limit or assertion is changed.');
fs.writeFileSync(path.join(output,'launcher-manifest.json'),JSON.stringify(manifest,null,2));console.log(JSON.stringify(manifest));
if(args.includes('--inspect'))process.exit(0);
os.tmpdir=()=>output;
const playwright=require(process.env.PLAYWRIGHT_MODULE||'playwright'),launch=playwright.chromium.launch.bind(playwright.chromium);
const seenContexts=new WeakSet(),seenPages=new WeakSet(),shots=[];
const diagnostics={api_errors:[],page_errors:[]};
function saveDiagnostics(){fs.writeFileSync(path.join(output,'diagnostics.json'),JSON.stringify(diagnostics,null,2));}
async function prepareContext(context){if(seenContexts.has(context))return;seenContexts.add(context);
 if(locale)await context.addInitScript(value=>{if(!['http:','https:'].includes(location.protocol))return;try{if(!localStorage.getItem('conquer.locale')){localStorage.setItem('conquer.locale',value);document.cookie='conquer_locale='+value+'; Path=/; SameSite=Lax';}}catch{}},locale);
 const createPage=context.newPage.bind(context);context.newPage=async(...a)=>preparePage(await createPage(...a));
}
function preparePage(page){if(seenPages.has(page))return page;seenPages.add(page);const screenshot=page.screenshot.bind(page);
 if(finalStage){
  saveDiagnostics();
  page.on('pageerror',error=>{diagnostics.page_errors.push({at:new Date().toISOString(),url:page.url(),message:error.message,stack:error.stack});saveDiagnostics();});
  page.on('response',response=>{
   if(response.status()<400||!new URL(response.url()).pathname.startsWith('/api/'))return;
   const entry={at:new Date().toISOString(),url:response.url(),method:response.request().method(),status:response.status(),body_pending:true};diagnostics.api_errors.push(entry);saveDiagnostics();
   response.text().then(body=>{entry.body=body.slice(0,8000);entry.body_truncated=body.length>8000;}).catch(error=>{entry.body_read_error=error.message;}).finally(()=>{entry.body_pending=false;saveDiagnostics();});
  });
 }
 if(navigationTimeout){const setNavigationTimeout=page.setDefaultNavigationTimeout.bind(page);setNavigationTimeout(navigationTimeout);page.setDefaultNavigationTimeout=value=>setNavigationTimeout(Math.max(navigationTimeout,Number(value)||0));}
 page.screenshot=async options=>{if(!options?.path)return screenshot(options);const target=path.join(output,path.basename(options.path));shots.push({original:options.path,redirected:target});fs.writeFileSync(path.join(output,'screenshot-paths.json'),JSON.stringify(shots,null,2));return screenshot({...options,path:target});};return page;
}
playwright.chromium.launch=async options=>{const browser=await launch(options),createContext=browser.newContext.bind(browser),createPage=browser.newPage.bind(browser);
 browser.newContext=async(...a)=>{const context=await createContext(...a);await prepareContext(context);return context;};
 browser.newPage=async(...a)=>{const page=await createPage(...a);await prepareContext(page.context());return preparePage(page);};return browser;
};
process.argv=[process.argv[0],filename];
const child=new Module(filename,module);child.filename=filename;child.paths=Module._nodeModulePaths(path.dirname(filename));child._compile(source,filename);
