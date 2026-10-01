'use strict';
// Isolated HTML fixtures load the same translator and catalogues as the app.
// No server, session, database, or language request is needed.
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'../..');
module.exports=function isolatedLocale(locale='de'){
 if(!['en','de','fr'].includes(locale))throw new Error('Unsupported fixture locale');
 const catalogs=Object.fromEntries(['en','de','fr'].map(code=>[code,JSON.parse(fs.readFileSync(path.join(root,'data/i18n',code+'.json'),'utf8'))]));
 return 'window.CONQUER_I18N='+JSON.stringify({locale,catalogs}).replaceAll('<','\\u003c')+';try{localStorage.setItem("conquer.locale",'+JSON.stringify(locale)+');}catch{};\n'+fs.readFileSync(path.join(root,'assets/js/localization.js'),'utf8');
};
