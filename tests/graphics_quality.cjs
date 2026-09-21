'use strict';
const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert');
const root=path.resolve(__dirname,'..'),saved=new Map(),events=[];
const body={dataset:{},setAttribute(name,value){if(name==='data-graphics-quality')this.dataset.graphicsQuality=value;}};
const context={
  window:{dispatchEvent:event=>events.push(event)},document:{readyState:'complete',body},
  navigator:{deviceMemory:2,hardwareConcurrency:4},innerWidth:1280,innerHeight:720,
  localStorage:{getItem:key=>saved.get(key)??null,setItem:(key,value)=>saved.set(key,value)},
  matchMedia:()=>({matches:false}),CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail;}},
};
context.window.window=context.window;context.window.document=context.document;context.window.navigator=context.navigator;
vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/graphics-quality.js'),'utf8'),context);
const quality=context.window.ConquerGraphicsQuality;
assert.deepEqual({...quality.state()},{requested:'auto',effective:'light'});
assert.equal(body.dataset.graphicsQuality,'light');
quality.set('high');assert.deepEqual({...quality.state()},{requested:'high',effective:'high'});assert.equal(saved.get('conquer:graphics-quality:v1'),'high');
assert.equal(quality.degrade(),false,'manual quality is never overridden');
quality.set('auto');context.navigator.deviceMemory=8;context.navigator.hardwareConcurrency=8;quality.apply();
assert.deepEqual({...quality.state()},{requested:'auto',effective:'normal'});
assert.equal(quality.degrade(),true);assert.equal(quality.state().effective,'light');
assert(events.some(event=>event.type==='conquer-graphics-quality'&&event.detail.effective==='high'));
console.log('PASS graphics quality: conservative auto, persistence, manual override and automatic fallback');
