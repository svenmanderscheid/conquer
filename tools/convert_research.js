/** Compatibility entry point for the repeatable source importer. */
'use strict';
const {spawnSync}=require('node:child_process');
const path=require('node:path');
const result=spawnSync(process.env.PYTHON_BINARY||'python',[path.join(__dirname,'import_balance.py'),'--research-only',...process.argv.slice(2)],{stdio:'inherit'});
if(result.error)throw result.error;
process.exitCode=result.status??1;
