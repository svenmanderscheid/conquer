// Deterministic slow-canvas checks for the production sprite warmup scheduler.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../assets/js/world-sprite-motion.js'),'utf8').replace(/^export /gm,'');
const turn=()=>new Promise(resolve=>setImmediate(resolve));
function harness({coarseClock=false}={}){
 let clock=0,draws=0,sliceDraws=0,sliceStart=0,failNext=false;
 const timers=[],slices=[],canvases=[];
 const context={
  performance:{now:()=>coarseClock?0:clock},
  setTimeout(callback,delay){slices.push({draws:sliceDraws,ms:clock-sliceStart,delay});sliceDraws=0;sliceStart=clock;timers.push(callback);},
  document:{createElement(tag){
   assert.equal(tag,'canvas');if(failNext){failNext=false;throw new Error('canvas unavailable');}
   const canvas={draws:0,image:null};canvases.push(canvas);
   const g={save(){},restore(){},beginPath(){},moveTo(){},lineTo(){},closePath(){},clip(){},transform(){},rect(){},clearRect(){},
    drawImage(image){canvas.image=image;canvas.draws++;draws++;sliceDraws++;clock+=.05;}};
   canvas.getContext=()=>g;return canvas;
  }}
 };
 vm.runInNewContext(source+'\nthis.api={buildMotionFrames,motionFrame,posePoint};',context);
 async function tick(){await turn();const callback=timers.shift();if(callback)callback();await turn();}
 async function finish(promise){
  let done=false,value,error;promise.then(v=>{done=true;value=v;},e=>{done=true;error=e;});
  for(let n=0;!done&&n<10000;n++)await tick();
  assert(done,'sprite work completes after its pause is released');if(error)throw error;return value;
 }
 return {...context.api,tick,finish,timers,slices,canvases,get draws(){return draws;},failNext(){failNext=true;}};
}
(async()=>{
 const work=harness(),ids=['green-dragon','red-dragon','gold-dragon','magdar','castle'];
 const pending=ids.map(id=>work.buildMotionFrames({id},id));
 assert.equal(work.draws,0,'first-visible sprites do not render synchronously in the animation callback');
 const frames=await work.finish(Promise.all(pending));
 for(let i=0;i<ids.length;i++){
  assert.equal(frames[i].length,24,ids[i]+' retains every pose');
  for(const frame of frames[i]){
   assert.equal(frame.width,256);assert.equal(frame.height,256);
   assert.equal(frame.draws,ids[i]==='castle'?801:800,'complete mesh, including the castle base');
  }
  assert.deepEqual(work.canvases.slice(i*24,(i+1)*24).map(c=>c.image.id),Array(24).fill(ids[i]),'sprite builds share one queue');
  assert.equal(work.motionFrame({id:ids[i],image:'still',motionFrames:frames[i]},1,true),'still');
 }
 assert(work.slices.every(s=>s.ms<4.2),'slow drawing yields within one mesh cell of the 4ms budget');

 const paused=harness();let pause=true;
 const waiting=paused.buildMotionFrames({id:'castle'},'castle',{shouldPause:()=>pause});
 await paused.tick();await paused.tick();
 assert.equal(paused.draws,0,'an active gesture pauses warmup before it starts');
 assert(paused.slices.every(s=>s.delay===50),'paused work uses a bounded retry timer');
 pause=false;await paused.tick();assert(paused.draws>0);
 pause=true;const beforePause=paused.draws;await paused.tick();await paused.tick();
 assert.equal(paused.draws,beforePause,'a gesture pauses already-running warmup at the next slice');
 pause=false;assert.equal((await paused.finish(waiting)).length,24,'all frames survive pause and resume');

 const coarse=harness({coarseClock:true});await coarse.finish(coarse.buildMotionFrames({},'castle'));
 assert(coarse.slices.every(s=>s.draws<=513),'coarse browser clocks still bound each chunk');
 const failures=harness();failures.failNext();
 const failed=failures.buildMotionFrames({},'magdar');failed.catch(()=>{});
 const recovery=failures.buildMotionFrames({},'green-dragon');
 await assert.rejects(failures.finish(failed),/canvas unavailable/);
 assert.equal((await failures.finish(recovery)).length,24,'a failed sprite does not block later sprites');
 const unsupported=harness();assert.equal(await unsupported.buildMotionFrames({},'orc'),null);assert.equal(unsupported.canvases.length,0);
 console.log('PASS sprite warmup: time budget, shared queue, full meshes, pause/resume, coarse clocks, error recovery');
})().catch(error=>{console.error(error);process.exitCode=1;});
