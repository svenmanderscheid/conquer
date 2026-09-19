// Local-only, short-lived receiver for the review page's three rendered PNGs.
const http=require('http'),fs=require('fs'),path=require('path');
const allowed=new Set(['ui','report','thumb']);
const received=new Set();
const server=http.createServer((req,res)=>{
 res.setHeader('Access-Control-Allow-Origin','http://localhost');
 res.setHeader('Access-Control-Allow-Methods','POST, OPTIONS');
 res.setHeader('Access-Control-Allow-Headers','Content-Type');
 if(req.headers.origin!=='http://localhost'){res.writeHead(403).end();return;}
 if(req.method==='OPTIONS'){res.writeHead(204).end();return;}
 const kind=req.url.slice(1);
 if(req.method!=='POST'||!allowed.has(kind)){res.writeHead(404).end();return;}
 let chunks=[],length=0;
 req.on('data',chunk=>{length+=chunk.length;if(length>4000000)req.destroy();else chunks.push(chunk);});
 req.on('end',()=>{
  const bytes=Buffer.concat(chunks);
  if(bytes.subarray(0,8).toString('hex')!=='89504e470d0a1a0a'){res.writeHead(400).end();return;}
  const dest=path.resolve('assets/art/characters',`infantry-t10-${kind}-v2.png`);
  fs.writeFileSync(dest,bytes);console.log(dest,bytes.length);received.add(kind);res.end('saved');
  if(received.size===3)server.close();
 });
});
server.listen(19387,'127.0.0.1',()=>console.log('Render receiver ready'));
setTimeout(()=>server.close(),300000).unref();
