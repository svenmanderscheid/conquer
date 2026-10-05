'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/action-audio');
(async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try{
        const page=await browser.newPage();
        await page.route('http://audio.test/',route=>route.fulfill({contentType:'text/html',body:'<!doctype html><html><body></body></html>'}));
        await page.goto('http://audio.test/');
        // Render the production sound graph with the browser's native offline engine.
        await page.evaluate(()=>{
            localStorage.setItem('conquer:audio:v1:',JSON.stringify({music:false,effectsVolume:100}));
            window.AudioContext=class{
                constructor(){this.engine=new OfflineAudioContext(1,26460,22050);window.renderEngine=this.engine;}
                get state(){return 'running';}get currentTime(){return this.engine.currentTime;}get sampleRate(){return this.engine.sampleRate;}get destination(){return this.engine.destination;}
                createGain(){return this.engine.createGain();}createOscillator(){return this.engine.createOscillator();}
                createBuffer(...args){return this.engine.createBuffer(...args);}createBufferSource(){return this.engine.createBufferSource();}createBiquadFilter(){return this.engine.createBiquadFilter();}
                addEventListener(){}removeEventListener(){}resume(){return Promise.resolve();}suspend(){return Promise.resolve();}close(){return Promise.resolve();}
            };
        });
        await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js/game-audio.js'),'utf8')});
        const kinds=await page.evaluate(()=>{const audio=ConquerAudio.create({base:''});const root=document.createElement('div');root.innerHTML=audio.controls();const kinds=[...root.querySelectorAll('[data-audio-demo]')].map(el=>el.dataset.audioDemo);audio.destroy();return kinds;});
        const chunks=[],checks=[],hashes=new Set();
        for(const kind of kinds){
            const rendered=await page.evaluate(async kind=>{
                const audio=ConquerAudio.create({base:''});
                // An explicit control change starts the context, as in the real options.
                const input=document.createElement('input');input.type='checkbox';input.dataset.audioSetting='music';input.checked=false;document.body.append(input);input.dispatchEvent(new Event('change',{bubbles:true}));input.remove();
                if(!audio.play(kind))throw Error('Sound not scheduled: '+kind);
                const buffer=await renderEngine.startRendering(),samples=buffer.getChannelData(0);
                let peak=0,energy=0;const pcm=new Uint8Array(samples.length*2),view=new DataView(pcm.buffer);
                for(let i=0;i<samples.length;i++){peak=Math.max(peak,Math.abs(samples[i]));energy+=samples[i]**2;view.setInt16(i*2,Math.round(Math.max(-1,Math.min(1,samples[i]))*32767),true);}
                const voices=audio.status().voices;audio.destroy();
                let bytes='';for(let i=0;i<pcm.length;i+=4096)bytes+=String.fromCharCode(...pcm.subarray(i,i+4096));
                return {peak,rms:Math.sqrt(energy/samples.length),voices,pcm:btoa(bytes)};
            },kind);
            assert(rendered.peak>0.01&&rendered.peak<0.8,kind+': peak '+rendered.peak);assert(rendered.rms>0.001,kind+': audible waveform');assert.equal(rendered.voices,0,kind+': source cleanup');
            const chunk=Buffer.from(rendered.pcm,'base64'),hash=crypto.createHash('sha256').update(chunk).digest('hex');assert(!hashes.has(hash),kind+': distinct waveform');hashes.add(hash);chunks.push(chunk);checks.push({kind,peak:rendered.peak,rms:rendered.rms});
        }
        fs.mkdirSync(out,{recursive:true});
        const pcm=Buffer.concat(chunks),header=Buffer.alloc(44);header.write('RIFF');header.writeUInt32LE(36+pcm.length,4);header.write('WAVEfmt ',8);header.writeUInt32LE(16,16);header.writeUInt16LE(1,20);header.writeUInt16LE(1,22);header.writeUInt32LE(22050,24);header.writeUInt32LE(44100,28);header.writeUInt16LE(2,32);header.writeUInt16LE(16,34);header.write('data',36);header.writeUInt32LE(pcm.length,40);
        const preview=Buffer.concat([header,pcm]);
        fs.writeFileSync(path.join(out,'sound-preview.wav'),preview);
        fs.writeFileSync(path.join(out,'sound-preview-epic-storybook-v2.wav'),preview);
        fs.writeFileSync(path.join(out,'render-report.json'),JSON.stringify({style:'epic-storybook-v2',sampleRate:22050,secondsPerSound:1.2,checks},null,2));
        console.log(`PASS native audio rendering: ${checks.length} distinct sounds, no clipping at full effect volume, all sources cleaned up. Preview: artifacts/action-audio/sound-preview-epic-storybook-v2.wav`);
    }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
