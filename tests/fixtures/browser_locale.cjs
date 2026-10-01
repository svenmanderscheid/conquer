'use strict';
// Language-dependent UI assertions must declare their preference explicitly.
// Default-language and language-switching suites deliberately do not use this.
module.exports=function explicitBrowserLocale(locale){
 if(!['en','de','fr'].includes(locale))throw Error('Unsupported test locale');
 const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
 const launch=chromium.launch.bind(chromium);
 chromium.launch=async options=>{
  const browser=await launch(options),createContext=browser.newContext.bind(browser),createPage=browser.newPage.bind(browser),prepared=new WeakSet();
  async function prepare(context){
   if(prepared.has(context))return;prepared.add(context);
   await context.addInitScript(value=>{
    if(!['http:','https:'].includes(location.protocol))return;
    try{if(!localStorage.getItem('conquer.locale')){localStorage.setItem('conquer.locale',value);document.cookie='conquer_locale='+value+'; Path=/; SameSite=Lax';}}catch{}
   },locale);
  }
  browser.newContext=async(...args)=>{const context=await createContext(...args);await prepare(context);return context;};
  browser.newPage=async(...args)=>{const page=await createPage(...args);await prepare(page.context());return page;};
  return browser;
 };
};
