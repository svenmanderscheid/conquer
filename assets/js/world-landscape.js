// Fixed world-space scenery: moving the camera or refreshing never relocates it.
window.ConquerLandscape = (() => {
    const data=window.ConquerTerrainData;
    const lakes=data.lakes, rivers=data.rivers;
    const riverX=(y,side)=>side+Math.sin(y/rivers.period+side)*rivers.amplitude+Math.sin(y/rivers.detailPeriod)*rivers.detailAmplitude;
    const streams=data.streams.map(s=>({...s,y:x=>s.base+Math.sin(x/s.period)*s.amplitude+Math.sin(x/s.detailPeriod)*s.detailAmplitude}));
    const waterAt=(x,y)=>rivers.sides.some(side=>Math.abs(riverX(y,side)-x)<rivers.bankWidth)||streams.some(s=>{const sx=Math.max(s.from,Math.min(s.to,x));return Math.hypot(x-sx,y-s.y(sx))<data.streamBankWidth;})||lakes.some(l=>{const dx=(x-l.x)/l.rx,dy=(y-l.y)/l.ry,a=Math.atan2(dy,dx);return Math.hypot(dx,dy)<(1+Math.sin(a*3+.8)*.065+Math.cos(a*5)*.035)*data.lakeBankScale;});
    const names=['forest','ice','sand','lava'],labels=['Smaragdwald','Frostlande','Sonnendünen','Aschenlande'];
    const palettes={
        // One continuous enchanted green world. Regional gameplay remains intact,
        // while restrained cool, gold and ember accents preserve orientation.
        forest:{ground:'#b9c985',shore:'#edd09a',bank:'#94b784',water:'#57b6d7',waterDeep:'#3793bd',waterLight:'#c5eff0',tree:'#498047',treeLight:'#74a452',treeDark:'#315f4c',rock:'#9d9d8b',rockLight:'#d0cfb5',accent:'#edc24b',road:'#edd09a',roadEdge:'#c4a76d'},
        ice:{ground:'#b5ca91',shore:'#ead8ab',bank:'#91b68a',water:'#6fc2d7',waterDeep:'#459dbe',waterLight:'#ddf5ec',tree:'#477c58',treeLight:'#77a96d',treeDark:'#315f4c',rock:'#9eaaa0',rockLight:'#d8dfce',accent:'#8fc9d4',road:'#e8d4a5',roadEdge:'#b5a873'},
        sand:{ground:'#c2ca82',shore:'#efd59c',bank:'#a4b778',water:'#59b7d2',waterDeep:'#3793b8',waterLight:'#ccefe8',tree:'#568347',treeLight:'#87aa56',treeDark:'#396345',rock:'#aa9473',rockLight:'#d7c696',accent:'#edc24b',road:'#ecd09a',roadEdge:'#b8a268'},
        lava:{ground:'#aebb7b',shore:'#dfc68f',bank:'#8fa16d',water:'#62b2c4',waterDeep:'#468ca9',waterLight:'#c7e8d8',tree:'#456c43',treeLight:'#718e50',treeDark:'#344d3c',rock:'#817d70',rockLight:'#b9b49b',accent:'#ed8a31',road:'#d9bd8b',roadEdge:'#9f8f65'}
    };
    const rgb=hex=>hex.slice(1).match(/../g).map(n=>parseInt(n,16));
    const alpha=(hex,value)=>hex+Math.round(clamp(value)*255).toString(16).padStart(2,'0');
    const colors=names.map(name=>Object.fromEntries(Object.entries(palettes[name]).map(([key,value])=>[key,rgb(value)])));
    const clamp=(n,min=0,max=1)=>Math.max(min,Math.min(max,n));
    const smooth=n=>{n=clamp(n);return n*n*(3-2*n);};
    const hash=(x,y,seed=0)=>{let n=Math.imul(x+317,374761393)^Math.imul(y+619,668265263)^Math.imul(seed+17,1274126177);n=Math.imul(n^(n>>>13),1274126177);return ((n^(n>>>16))>>>0)/4294967295;};
    function noise(x,y){const ix=Math.floor(x),iy=Math.floor(y),tx=smooth(x-ix),ty=smooth(y-iy),a=hash(ix,iy),b=hash(ix+1,iy),c=hash(ix,iy+1),d=hash(ix+1,iy+1);return (a+(b-a)*tx)*(1-ty)+(c+(d-c)*tx)*ty;}
    function lavaFissure(gx,gy){
        const x=gx*13+3+hash(gx,gy,81)*7,y=gy*13+3+hash(gx,gy,82)*7,amount=weightsAt(x,y)[3];
        if(amount<.52||hash(gx,gy,85)>.19)return null;
        return {x,y,amount,bend:hash(gx,gy,86)>.5?1:-1,length:1.45+hash(gx,gy,87)*1.45};
    }
    function weightsAt(x,y){
        const cx=data.biomes?.centerX??128,cy=data.biomes?.centerY??128,width=data.biomes?.transitionWidth??52;
        // Meandering climate boundaries form broad transition belts instead of straight quadrants.
        const east=smooth(.5+(x-cx-13*Math.sin((y-cy)/34)-5*Math.sin((y-cy)/13))/width);
        const south=smooth(.5+(y-cy+15*Math.sin((x-cx)/39)+4*Math.sin((x-cx)/15))/width);
        return [(1-east)*(1-south),east*(1-south),(1-east)*south,east*south];
    }
    function mix(weights,key){const value=[0,0,0];for(let i=0;i<4;i++)for(let j=0;j<3;j++)value[j]+=colors[i][key][j]*weights[i];return '#'+value.map(n=>Math.round(n).toString(16).padStart(2,'0')).join('');}
    function biomeAt(x,y){
        const weights=weightsAt(x,y),index=weights.indexOf(Math.max(...weights)),result={id:names[index],name:labels[index],weights:Object.fromEntries(names.map((name,i)=>[name,weights[i]]))};
        for(const key of Object.keys(palettes.forest))result[key]=mix(weights,key);
        return result;
    }
    let groundTexture=null;
    const spriteCache=new Map();
    function canvas(width,height){const node=typeof OffscreenCanvas==='function'?new OffscreenCanvas(width,height):document.createElement('canvas');node.width=width;node.height=height;return node;}
    function texture(){
        if(groundTexture)return groundTexture;
        // Four pixels per world tile: generated once, then cropped and resampled while panning.
        // The variation is deliberately broad; the map should read as painted land, never as noise.
        const size=1024,texture=canvas(size,size),c=texture.getContext('2d'),pixels=c.createImageData(size,size);
        for(let py=0;py<size;py++)for(let px=0;px<size;px++){
            const x=px/4-.5,y=py/4-.5,w=weightsAt(x,y),broad=noise(x*.034,y*.034)-.5,soft=noise(x*.092+14,y*.092-7)-.5;
            const dune=Math.sin(x*.32+y*.19+Math.sin(y*.055)*2.4+Math.sin(x*.04)*1.3);
            const iceVein=Math.max(0,1-Math.abs(Math.sin(x*.11+y*.055+Math.sin(y*.12)*.7))/.052);
            const fissure=Math.max(0,1-Math.abs(Math.sin(x*.22+Math.sin(y*.12)*1.4)*Math.sin(y*.2+Math.sin(x*.16)*1.2))/.052);
            const ember=fissure*fissure*(.28+.72*noise(x*.115+19,y*.115+7));
            // Keep the land as a quiet painted stage. Broad colour movement is
            // still visible, but never competes with cities, encounters or paths.
            const variation=broad*7+soft*2,duneShade=dune*1.25,offset=(py*size+px)*4;
            for(let channel=0;channel<3;channel++){
                let value=0;for(let i=0;i<4;i++)value+=colors[i].ground[channel]*w[i];
                value+=variation+w[2]*duneShade+w[1]*iceVein*[-17,-8,-1][channel]+w[3]*ember*[24,9,-1][channel];
                pixels.data[offset+channel]=clamp(Math.round(value),0,255);
            }
            pixels.data[offset+3]=255;
        }
        c.putImageData(pixels,0,0);groundTexture=texture;return texture;
    }
    function paintedGroundDetails(c,project,s,bounds){
        const detailSpan=22,left=Math.floor(bounds.left/detailSpan)-1,top=Math.floor(bounds.top/detailSpan)-1,right=Math.ceil(bounds.right/detailSpan)+1,bottom=Math.ceil(bounds.bottom/detailSpan)+1;
        // Feathered, uneven washes make broad terrain variation without readable geometric ovals.
        const wash=(x,y,rx,ry,colour,opacity,turn=0,seed=0)=>{const [px,py]=project(x,y),sx=rx*s,sy=ry*s,span=Math.max(sx,sy)*1.15;c.save();c.translate(px,py);c.rotate(turn);const g=c.createRadialGradient(-sx*.22,-sy*.14,Math.min(sx,sy)*.05,0,0,span);g.addColorStop(0,alpha(colour,opacity));g.addColorStop(.52,alpha(colour,opacity*.46));g.addColorStop(1,alpha(colour,0));c.fillStyle=g;c.beginPath();for(let i=0;i<=11;i++){const a=i/11*Math.PI*2,w=.74+hash(Math.floor(x*3)+i,Math.floor(y*3)+seed,217)*.34,pxx=Math.cos(a)*sx*w,pyy=Math.sin(a)*sy*w;i?c.lineTo(pxx,pyy):c.moveTo(pxx,pyy);}c.closePath();c.fill();c.restore();};
        const island=(x,y,r,colour,opacity,turn=0,seed=0)=>{const [px,py]=project(x,y);c.save();c.translate(px,py);c.rotate(turn);c.fillStyle=alpha(colour,opacity);c.beginPath();for(let i=0;i<=6;i++){const a=i/6*Math.PI*2,w=.68+hash(Math.floor(x*11)+i,Math.floor(y*11)+seed,223)*.38,pxx=Math.cos(a)*r*s*w,pyy=Math.sin(a)*r*s*.62*w;i?c.lineTo(pxx,pyy):c.moveTo(pxx,pyy);}c.closePath();c.fill();c.restore();};
        const stroke=(points,colour,width,alpha)=>{c.save();c.globalAlpha=alpha;c.strokeStyle=colour;c.lineWidth=Math.max(.45,width*s);c.lineCap='round';c.lineJoin='round';c.beginPath();points.forEach(([x,y],i)=>{const [px,py]=project(x,y);i?c.lineTo(px,py):c.moveTo(px,py);});c.stroke();c.restore();};
        for(let gy=top;gy<=bottom;gy++)for(let gx=left;gx<=right;gx++){
            const h=hash(gx,gy,211),x=gx*detailSpan+4+hash(gx,gy,212)*(detailSpan-8),y=gy*detailSpan+4+hash(gx,gy,213)*(detailSpan-8),biome=biomeAt(x,y),turn=(hash(gx,gy,214)-.5)*1.15;
            if(biome.id==='forest'){
                wash(x,y,5.8+hash(gx,gy,215)*2.8,2.1+hash(gx,gy,216)*1.25,biome.treeLight,.085,turn,gy);
                if(h>.7)wash(x+2,y-1,2.2,.82,biome.accent,.075,turn-.2,gx);
                if(h>.9)for(let i=0;i<3;i++){const a=i*Math.PI*2/3+.2;wash(x+Math.cos(a)*.58,y+Math.sin(a)*.46,.13,.1,i%2?'#efb0c7':'#f5e6a5',.62,a,i);}
            }else if(biome.id==='ice'){
                wash(x,y,6.1+hash(gx,gy,215)*3.1,1.25+hash(gx,gy,216)*.9,'#dcebd2',.12,turn,gx);
                if(h>.68)stroke([[x-5,y+1],[x-1,y-1],[x+4,y-1.6],[x+7,y-3]],biome.waterLight,.075,.2);
            }else if(biome.id==='sand'){
                wash(x,y,6.4+hash(gx,gy,215)*3.2,1.3+hash(gx,gy,216)*.95,'#d9db91',.11,turn,gx);
                if(h>.62)stroke([[x-6,y+2],[x-1,y-1],[x+5,y-1.4]],biome.roadEdge,.065,.13);
            }else{
                wash(x,y,5.2+hash(gx,gy,215)*2.4,1.7+hash(gx,gy,216)*1.05,'#879b66',.1,turn,gx);
                wash(x-2,y+1.1,2.1+hash(gx,gy,218)*1.1,.85+hash(gx,gy,219)*.45,'#9cab72',.08,turn+.25,gy);
                if(h>.58)wash(x+2.2,y-1.2,1.4,.54,'#667953',.1,turn-.35,gx+gy);
                if(h>.76)wash(x+1.6,y-.7,.82,.34,'#c9824d',.09,turn,gx-gy);
                if(h>.86){island(x-1.25,y+.52,.55+hash(gx,gy,224)*.28,'#687257',.18,turn,gx);island(x-.35,y+.02,.32,'#a38d62',.14,turn-.18,gy);}
            }
        }
        // Tiny flower drifts and leafy undergrowth fill the large quiet areas
        // without becoming a repeated texture. They fade out when zoomed away.
        if(s>2.15)for(let gy=Math.floor(bounds.top/8)-1;gy<=Math.ceil(bounds.bottom/8)+1;gy++)for(let gx=Math.floor(bounds.left/8)-1;gx<=Math.ceil(bounds.right/8)+1;gx++){
            const x=gx*8+1+hash(gx,gy,401)*6,y=gy*8+1+hash(gx,gy,402)*6,biome=biomeAt(x,y);
            if(waterAt(x,y)||biome.weights.forest<.16||hash(gx,gy,403)>.58)continue;
            const count=3+Math.floor(hash(gx,gy,404)*5),colours=['#f2d56f','#e9a8bd','#f5eee0','#91bddd'];
            c.save();c.lineCap='round';
            for(let i=0;i<count;i++){
                const px=x+(hash(gx+i,gy,405)-.5)*1.65,py=y+(hash(gx,gy+i,406)-.5)*.78;
                if(waterAt(px,py))continue;
                const [sx,sy]=project(px,py),sway=(hash(gx+i,gy-i,407)-.5)*s*.08;
                c.globalAlpha=.35+biome.weights.forest*.4;c.strokeStyle=biome.treeDark;c.lineWidth=Math.max(.45,s*.025);c.beginPath();c.moveTo(sx,sy);c.quadraticCurveTo(sx+sway,sy-s*.13,sx+sway*.6,sy-s*.23);c.stroke();
                c.fillStyle=colours[Math.floor(hash(gx-i,gy+i,408)*colours.length)];
                for(let petal=0;petal<4;petal++){const a=petal*Math.PI/2;c.beginPath();c.ellipse(sx+sway*.6+Math.cos(a)*s*.035,sy-s*.23+Math.sin(a)*s*.025,Math.max(.45,s*.026),Math.max(.35,s*.018),a,0,Math.PI*2);c.fill();}
            }
            c.restore();
        }
    }
    function ground(c,project,s,bounds){
        const left=Math.max(-.5,bounds.left),top=Math.max(-.5,bounds.top),right=Math.min(255.5,bounds.right),bottom=Math.min(255.5,bounds.bottom);
        if(left>=right||top>=bottom)return;
        const [x,y]=project(left,top);c.save();c.imageSmoothingEnabled=true;c.imageSmoothingQuality='high';
        // A complete opaque base makes repeated draws byte-stable, including canvas edges.
        c.globalCompositeOperation='copy';c.drawImage(texture(),(left+.5)*4,(top+.5)*4,(right-left)*4,(bottom-top)*4,x,y,(right-left)*s,(bottom-top)*s);c.globalCompositeOperation='source-over';
        paintedGroundDetails(c,project,s,{left,top,right,bottom});
        // Rare hand-drawn fissures: short organic seams, never a repeated lightning grid.
        if(s>3){c.lineJoin='round';c.lineCap='round';for(let gy=Math.floor(top/13)-1;gy<=Math.ceil(bottom/13);gy++)for(let gx=Math.floor(left/13)-1;gx<=Math.ceil(right/13);gx++){
            const fissure=lavaFissure(gx,gy);if(!fissure)continue;const {x,y,amount,bend,length}=fissure,points=[[0,0],[bend*.22,length*.24],[-bend*.08,length*.48],[bend*.28,length*.75],[-bend*.04,length]].map(([a,b],i)=>project(x+a+(hash(gx+i,gy,88)-.5)*.16,y+b));
            c.globalAlpha=smooth((amount-.52)/.48)*.86;c.beginPath();points.forEach(([a,b],i)=>i?c.lineTo(a,b):c.moveTo(a,b));
            for(const [width,color]of [[.115,'#574947'],[.052,'#c86542'],[.016,'#ffd178']]){c.lineWidth=Math.max(.55,s*width);c.strokeStyle=color;c.stroke();}
        }}c.restore();
    }
    function water(c,project,s,bounds) {
        c.save();
        c.lineCap='round';c.lineJoin='round';
        function strokePath(points,width){
            const parts=[];for(let i=0;i<points.length-1;i+=12){const part=points.slice(i,i+13),mid=part[Math.floor(part.length/2)];parts.push({points:part,biome:biomeAt(...mid)});}
            // Draw all banks before the inner water, so colour segments never leave end-cap seams.
            for(const [factor,key] of [[1.78,'roadEdge'],[1.6,'shore'],[1.22,'bank'],[1,'waterDeep'],[.68,'water'],[.11,'waterLight']])for(const part of parts){
                c.beginPath();part.points.forEach(([x,y],i)=>{const [px,py]=project(x,y);i?c.lineTo(px,py):c.moveTo(px,py);});
                c.strokeStyle=part.biome[key];c.lineWidth=s*width*factor;c.stroke();
            }
        }
        for(const side of rivers.sides){const points=[];for(let y=Math.max(0,bounds.top-3);y<=Math.min(256,bounds.bottom+3);y+=.4)points.push([riverX(y,side),y]);strokePath(points,.94);}
        for(const stream of streams){const points=[];for(let x=Math.max(stream.from,bounds.left-3);x<=Math.min(stream.to,bounds.right+3);x+=.35)points.push([x,stream.y(x)]);if(points.length>1)strokePath(points,.48);}
        for(const lake of lakes){
            if(lake.x+lake.rx*1.25<bounds.left||lake.x-lake.rx*1.25>bounds.right||lake.y+lake.ry*1.25<bounds.top||lake.y-lake.ry*1.25>bounds.bottom)continue;
            const [cx,cy]=project(lake.x,lake.y);
            const biome=biomeAt(lake.x,lake.y);
            const shape=factor=>{c.beginPath();for(let i=0;i<=64;i++){const a=i/64*Math.PI*2,r=1+Math.sin(a*3+.8)*.065+Math.cos(a*5)*.035;const x=cx+Math.cos(a)*lake.rx*s*r*factor,y=cy+Math.sin(a)*lake.ry*s*r*factor;i?c.lineTo(x,y):c.moveTo(x,y);}c.closePath();};
            for(const [factor,key]of [[1.13,'shore'],[1.06,'bank'],[1,'water']]){
                const shore=c.createLinearGradient(cx-lake.rx*s,cy-lake.ry*s,cx+lake.rx*s,cy+lake.ry*s);
                for(let i=0;i<=4;i++)shore.addColorStop(i/4,biomeAt(lake.x+(i/2-1)*lake.rx,lake.y+(i/2-1)*lake.ry)[key]);
                shape(factor);c.fillStyle=shore;c.fill();
            }
            const gradient=c.createRadialGradient(cx-s,cy-s,.1,cx,cy,s*lake.rx);gradient.addColorStop(0,biome.waterDeep);gradient.addColorStop(.72,biome.water);gradient.addColorStop(1,biome.waterLight);shape(.96);c.fillStyle=gradient;c.fill();
            c.lineWidth=Math.max(.6,s*.025);c.strokeStyle=biome.waterLight+'99';
            for(let i=0;i<7;i++){const x=cx+Math.sin(i*2.4)*lake.rx*s*.62,y=cy+Math.cos(i*1.7)*lake.ry*s*.58;c.beginPath();c.moveTo(x-s*.16,y);c.quadraticCurveTo(x,y+s*.045,x+s*.16,y);c.stroke();}
        }
        // Sparse, broad highlight strokes make water feel painted and calm rather than patterned.
        c.globalAlpha=.46;c.strokeStyle='#e8f7eb';c.lineCap='round';c.lineWidth=Math.max(.55,s*.045);
        for(const side of rivers.sides)for(let y=Math.ceil((bounds.top-2)/7)*7+2;y<bounds.bottom+2;y+=7){const x=riverX(y,side),[a,b]=project(x-.23,y),[d,e]=project(x+.25,y+.34);c.beginPath();c.moveTo(a,b);c.quadraticCurveTo((a+d)/2,(b+e)/2+s*.08,d,e);c.stroke();}
        for(const stream of streams)for(let x=Math.ceil((Math.max(stream.from,bounds.left)-1)/8)*8+3;x<Math.min(stream.to,bounds.right)+1;x+=8){const y=stream.y(x),[a,b]=project(x-.18,y-.06),[d,e]=project(x+.2,y+.06);c.beginPath();c.moveTo(a,b);c.quadraticCurveTo((a+d)/2,(b+e)/2-s*.06,d,e);c.stroke();}
        c.restore();
    }
    const bridges=[
        {kind:'river',side:64,y:46},{kind:'river',side:64,y:116},{kind:'river',side:64,y:205},
        {kind:'river',side:192,y:76},{kind:'river',side:192,y:177},{kind:'stream',stream:0,x:82},
        {kind:'stream',stream:1,x:164},{kind:'stream',stream:2,x:205}
    ].map((bridge,index)=>bridge.kind==='river'?{...bridge,index,x:riverX(bridge.y,bridge.side),angle:0}:{...bridge,index,y:streams[bridge.stream].y(bridge.x),angle:Math.PI/2});
    function bridgesLayer(c,project,s,bounds){
        c.save();c.lineCap='round';c.lineJoin='round';
        for(const bridge of bridges){
            if(bridge.x<bounds.left-4||bridge.x>bounds.right+4||bridge.y<bounds.top-4||bridge.y>bounds.bottom+4)continue;
            const [x,y]=project(bridge.x,bridge.y),length=s*(bridge.kind==='river'?2.55:1.75),width=s*.7;
            c.save();c.translate(x,y);c.rotate(bridge.angle);
            c.fillStyle='#493b333b';c.beginPath();c.ellipse(s*.08,s*.17,length*.56,width*.62,0,0,Math.PI*2);c.fill();
            c.strokeStyle='#5d4634';c.lineWidth=Math.max(1,s*.13);c.beginPath();c.moveTo(-length*.53,-width*.47);c.lineTo(length*.53,-width*.47);c.moveTo(-length*.53,width*.47);c.lineTo(length*.53,width*.47);c.stroke();
            const planks=7;for(let i=0;i<planks;i++){
                const px=-length*.48+i*length*(.96/(planks-1)),jitter=(hash(bridge.index,i,511)-.5)*s*.06;
                c.strokeStyle=i%2?'#b98755':'#c99b64';c.lineWidth=Math.max(2,s*.18);c.beginPath();c.moveTo(px+jitter,-width*.39);c.lineTo(px-jitter,width*.39);c.stroke();
                c.strokeStyle='#73513a';c.lineWidth=Math.max(.5,s*.025);c.stroke();
            }
            c.strokeStyle='#e2bd7a99';c.lineWidth=Math.max(.6,s*.03);c.beginPath();c.moveTo(-length*.48,-width*.3);c.lineTo(length*.48,-width*.3);c.stroke();
            c.restore();
        }
        c.restore();
    }
    // Optional overlay pass. The map can call this on a throttled animation layer;
    // it never redraws the cached ground or alters waterAt/terrain geometry.
    function ambience(c,project,s,bounds,time=0){
        const t=Number(time||0);c.save();c.lineCap='round';c.lineJoin='round';
        c.strokeStyle='#f2fff3';c.lineWidth=Math.max(.45,s*.032);c.globalAlpha=.18;
        for(const side of rivers.sides)for(let y=Math.ceil(bounds.top/21)*21+4;y<bounds.bottom;y+=21){const wave=Math.sin(t*1.3+y*.43)*.34,x=riverX(y,side)+wave;if(!waterAt(x,y))continue;const [ax,ay]=project(x-.18,y),[bx,by]=project(x+.2,y+.28);c.beginPath();c.moveTo(ax,ay);c.quadraticCurveTo((ax+bx)/2,(ay+by)/2+s*.07,bx,by);c.stroke();}
        for(const lake of lakes){if(lake.x+lake.rx<bounds.left||lake.x-lake.rx>bounds.right||lake.y+lake.ry<bounds.top||lake.y-lake.ry>bounds.bottom)continue;const a=t*.35+hash(lake.x,lake.y,235)*6.28,cx=lake.x+Math.cos(a)*lake.rx*.38,cy=lake.y+Math.sin(a*1.7)*lake.ry*.34;if(!waterAt(cx,cy))continue;const [x,y]=project(cx,cy);c.beginPath();c.ellipse(x,y,s*.15,s*.024,0,0,Math.PI*2);c.stroke();}
        for(let gy=Math.floor(bounds.top/13);gy<=Math.ceil(bounds.bottom/13);gy++)for(let gx=Math.floor(bounds.left/13);gx<=Math.ceil(bounds.right/13);gx++){const fissure=lavaFissure(gx,gy);if(!fissure)continue;const [px,py]=project(fissure.x+fissure.bend*.05,fissure.y+fissure.length*.52),pulse=.3+.7*(.5+.5*Math.sin(t*2.2+hash(gx,gy,234)*6.28));c.globalAlpha=.12+pulse*.22;c.fillStyle='#ffd27d';c.beginPath();c.ellipse(px,py,Math.max(.55,s*.07*pulse),Math.max(.55,s*.045*pulse),0,0,Math.PI*2);c.fill();}
        c.restore();
    }
    function scenerySprite(id,kind,variant){
        const key=id+':'+kind+':'+variant;if(spriteCache.has(key))return spriteCache.get(key);
        const image=canvas(160,192),c=image.getContext('2d'),p=palettes[id];c.translate(80,164);
        const poly=(points,fill,stroke)=>{c.beginPath();points.forEach(([x,y],i)=>i?c.lineTo(x,y):c.moveTo(x,y));c.closePath();c.fillStyle=fill;c.fill();if(stroke){c.strokeStyle='#493b33';c.lineWidth=2.6;c.lineJoin='round';c.stroke();}};
        const ellipse=(x,y,rx,ry,color,rotation=0)=>{c.fillStyle=color;c.beginPath();c.ellipse(x,y,rx,ry,rotation,0,Math.PI*2);c.fill();};
        const line=(points,color,width)=>{c.beginPath();points.forEach(([x,y],i)=>i?c.lineTo(x,y):c.moveTo(x,y));c.lineCap='round';c.lineJoin='round';c.strokeStyle=color;c.lineWidth=width;c.stroke();};
        const shadow=kind==='tree'?[29,10]:kind==='hill'?[52,15]:kind==='relic'?[31,9]:[23,6];ellipse(4,1,shadow[0],shadow[1],p.treeDark+'35',-.12);
        if(kind==='detail'){
            if(id==='forest'){
                if(variant===0){
                    for(const [x,y,h,color]of [[-22,1,27,'#d9785f'],[1,4,20,'#efd06c'],[21,2,31,'#b76b91']]){line([[x,y],[x+2,y-h]],'#efe0bd',4);ellipse(x+2,y-h,10,5,color,-.15);ellipse(x-1,y-h-2,5,2,'#fff0b5',-.15);}
                    for(const x of [-32,-12,12,33])line([[x,3],[x-3,-11]],p.tree,3);
                }else if(variant===1){
                    for(const [x,y,r]of [[-27,-2,7],[-9,1,5],[14,0,8],[31,2,5]]){ellipse(x,y,r,r*.45,p.rock);ellipse(x-2,y-2,r*.7,r*.28,p.rockLight);}
                    for(const [x,y,color]of [[-19,-9,'#e9b7cf'],[4,-11,'#f0d36f'],[24,-8,'#9ec9df']]){line([[x,1],[x,y]],p.tree,2.5);for(let a=0;a<5;a++)ellipse(x+Math.cos(a*1.257)*4,y+Math.sin(a*1.257)*3,2.8,1.8,color,a);}
                }else{
                    poly([[-19,2],[-17,-27],[-7,-40],[10,-35],[19,-18],[16,3]],p.rock,true);poly([[-17,-27],[-7,-40],[-5,-5],[-19,2]],p.rockLight);
                    line([[-6,-25],[5,-29],[1,-19],[8,-12],[-4,-8]],'#79c6b0',3.3);for(const x of [-26,24])line([[x,3],[x+(x<0?-4:4),-13]],p.tree,3);
                }
            }else if(id==='ice'){
                const shards=variant===0?[[-24,0,-16,-37,-5,-3],[-8,1,3,-58,13,-2],[9,0,24,-42,31,2]]:variant===1?[[-31,1,-21,-26,-10,1],[-13,2,-2,-46,8,1],[7,1,19,-31,28,2],[24,3,33,-17,38,3]]:[[-24,2,-8,-45,2,2],[-2,1,11,-60,20,2],[17,2,29,-35,37,3]];
                for(const [ax,ay,bx,by,cx,cy]of shards){poly([[ax,ay],[bx,by],[cx,cy]],'#9fd9e1',true);poly([[bx,by],[cx,cy],[bx+2,cy-5]],'#e9fbf5');line([[bx,by],[bx+2,cy-5]],'#ffffffaa',1.6);}
                ellipse(3,3,38,7,'#c8e4df55');
            }else if(id==='sand'){
                if(variant===0){
                    poly([[-28,2],[-25,-37],[-14,-45],[-4,-35],[-8,2]],p.rock,true);poly([[4,2],[8,-25],[21,-31],[28,-20],[25,2]],p.rockLight,true);line([[-29,-9],[-7,-13]],'#f0c77f',3);ellipse(10,-30,12,4,p.rock);
                }else if(variant===1){
                    ellipse(-12,-2,18,8,'#9a6247');poly([[-27,-3],[-22,-30],[-9,-39],[4,-28],[7,-3]],'#c9855d',true);ellipse(-9,-31,12,5,'#e5b07b');line([[-19,-22],[-3,-14],[-18,-8]],'#f0c77f',3);poly([[12,2],[19,-15],[31,-11],[34,2]],p.rock,true);
                }else{
                    poly([[-25,3],[-20,-34],[-6,-49],[11,-42],[24,-16],[20,3]],p.rock,true);poly([[-20,-34],[-6,-49],[-3,-7],[-25,3]],p.rockLight);line([[-8,-32],[7,-36],[2,-24],[11,-16],[-3,-12]],'#d99a45',3.2);
                }
            }else{
                for(const [x,h,w]of [[-25,31,13],[-5,49,17],[17,37,15],[31,22,10]]){poly([[x-w/2,2],[x-3,-h],[x+4,-h-8],[x+w/2,2]],'#4c4245',true);poly([[x-3,-h],[x+4,-h-8],[x+2,-6]],'#756166');line([[x,-h*.78],[x+2,-8]],'#e8713d',2.5);}
                for(const [x,y,r]of [[-31,3,3],[-13,1,2],[8,3,3],[28,1,2]]){ellipse(x,y,r*2,r,'#e86f3e88');ellipse(x,y-1,r,r*.5,'#ffd176');}
            }
        }else if(kind==='relic'){
            if(id==='forest'){
                poly([[-25,3],[-20,-72],[-8,-91],[13,-84],[26,-58],[21,3]],p.rock,true);poly([[-20,-72],[-8,-91],[-3,-9],[-25,3]],p.rockLight);
                line([[-7,-65],[9,-69],[3,-52],[13,-41],[-5,-31],[7,-18]],'#78d0ae',5);line([[-26,-4],[-35,-20],[-39,-36]],p.tree,5);ellipse(-39,-39,8,5,p.treeLight,.4);
            }else if(id==='ice'){
                poly([[-25,3],[-13,-73],[2,-108],[18,-68],[28,3]],'#8fd0dc',true);poly([[-13,-73],[2,-108],[3,-8],[-25,3]],'#dff7f2');poly([[2,-108],[18,-68],[28,3],[4,-8]],'#69aec6');line([[2,-90],[4,-24]],'#ffffffbb',3);
            }else if(id==='sand'){
                poly([[-35,3],[-31,-62],[-16,-69],[-10,3]],p.rockLight,true);poly([[12,3],[17,-62],[31,-67],[36,3]],p.rock,true);line([[-27,-57],[-14,-78],[2,-84],[19,-62]],p.rock,13);line([[-25,-56],[-13,-71],[2,-77],[18,-59]],p.rockLight,6);line([[-4,-63],[7,-67]],'#d99a45',3);
            }else{
                poly([[-35,3],[-24,-50],[-9,-80],[8,-91],[24,-56],[36,3]],'#443b40',true);poly([[-24,-50],[-9,-80],[-2,-11],[-35,3]],'#6f5c60');line([[3,-73],[-6,-55],[8,-39],[-4,-22],[7,-8]],'#ff7a3d',6);line([[3,-73],[-6,-55],[8,-39],[-4,-22],[7,-8]],'#ffd06b',2.2);ellipse(5,-82,9,4,'#ff8f4d88');
            }
        }else if(kind==='hill'){
            if(id==='forest'){
                // Broad connected outcrops replace the old isolated white
                // pyramid and match the soft rock language of the village.
                poly([[-65,3],[-51,-27],[-31,-43],[-12,-82],[8,-65],[24,-88],[43,-49],[63,-2],[18,15],[-28,13]],p.rock,true);
                poly([[-65,3],[-51,-27],[-31,-43],[-12,-82],[-8,-24],[-28,13]],p.rockLight);
                poly([[8,-65],[24,-88],[43,-49],[52,-20],[20,-31]],'#b8b7a0');
                line([[-42,-18],[-20,-28],[-7,-54]],'#e0ddc4',2.2);
                line([[15,-40],[24,-60],[38,-37]],'#7f806f',2);
            }else if(id==='sand'){
                poly([[-59,0],[-41,-35],[-29,-68],[16,-72],[39,-46],[60,-3],[13,15]],p.rock,p.rockLight);
                poly([[-41,-35],[-29,-68],[-1,-73],[-13,-4],[-59,0]],p.rockLight);
                poly([[-29,-68],[16,-72],[26,-61],[-25,-56]],'#efc990');
                for(let i=0;i<4;i++)line([[-39-i*4,-42+i*12],[-8,-41+i*12],[36+i*5,-48+i*12]],i%2?p.rockLight+'88':'#7b523f88',2);
            }else{
                const peak=id==='lava'?-106:-121;
                poly([[-63,2],[-23,-77],[2,peak],[34,-57],[61,1],[10,15]],p.rock,p.rockLight+'aa');
                poly([[-63,2],[-23,-77],[2,peak],[-3,-30],[10,15]],p.rockLight);
                poly([[2,peak],[34,-57],[61,1],[17,-23]],p.rock);
                poly([[-22,-74],[2,peak],[21,-76],[9,-69],[2,-79],[-7,-66]],id==='ice'?'#f3fbf6':id==='lava'?'#382f37':'#c5c7ae');
                if(id==='ice'){poly([[-63,2],[-42,-20],[-12,-15],[10,15]],'#cce5e5');line([[16,-40],[30,-26],[41,-4]],'#cce2e9',2);}
                if(id==='lava'){
                    line([[0,-96],[-7,-77],[4,-61],[-4,-40],[7,-23],[4,4]],'#d45a35',7);
                    line([[0,-94],[-7,-77],[4,-61],[-4,-40],[7,-23],[4,4]],'#ffc16a',2.5);
                    ellipse(2,-99,11,4,'#f4964e');ellipse(2,-100,7,2,'#ffe0a0');
                    for(let i=0;i<3;i++)ellipse(5-i*7,-120-i*15,7+i*4,6+i*3,'#7e727359');
                }
            }
            for(let i=0;i<3;i++){const x=-51+i*44,y=8+(i%2)*3;poly([[x-9,y],[x-7,y-10],[x+3,y-14],[x+12,y-4],[x+5,y+5]],i%2?p.rockLight:p.rock);}
        }else if(id==='sand'&&variant!==1){
            line([[-2,0],[-5,-34],[2,-72],[8,-108]],'#997449',12);line([[-5,-2],[-8,-35],[-1,-75],[6,-108]],'#d3ac6e',4);
            for(let i=0;i<5;i++)line([[-8,-15-i*17],[1,-12-i*17]],'#775d43',1.8);
            for(const [dx,dy]of [[-49,4],[-44,-29],[-17,-43],[22,-43],[49,-25],[48,10]]){
                c.beginPath();c.moveTo(7,-108);c.quadraticCurveTo(7+dx*.6,-120+dy*.3,7+dx,-106+dy);c.quadraticCurveTo(7+dx*.7,-100+dy*.3,7,-105);c.fillStyle=dx<0?p.treeLight:p.tree;c.fill();
                line([[7,-108],[7+dx*.48,-116+dy*.43],[7+dx,-106+dy]],p.treeDark+'99',1.3);
            }
            ellipse(3,-101,5,6,'#8d6649');ellipse(13,-101,4,5,'#bc8b55');
        }else if(id==='sand'){
            line([[0,-2],[0,-99]],p.treeDark,19);line([[-1,-2],[-1,-100]],p.tree,13);line([[-6,-4],[-6,-99]],p.treeLight,3);
            line([[-3,-40],[-25,-45],[-28,-69]],p.treeDark,13);line([[-3,-42],[-25,-47],[-28,-69]],p.tree,8);
            line([[4,-60],[23,-65],[25,-85]],p.treeDark,12);line([[4,-62],[23,-67],[25,-85]],p.treeLight,6);
            for(let i=0;i<6;i++)line([[4,-14-i*14],[8,-16-i*14]],'#e5ce9677',1);
            for(let i=0;i<5;i++)ellipse(-2+Math.cos(i*1.25)*5,-105+Math.sin(i*1.25)*4,3.5,3,'#e7a47d');
        }else if(id==='lava'){
            // Broad twisted charcoal trunks give the ash biome a strong readable silhouette.
            line([[0,3],[-6,-31],[5,-73],[-4,-126]],'#453b39',21);line([[-1,2],[-4,-32],[8,-73],[-1,-126]],p.treeDark,11);
            for(const [side,height]of [[-1,30],[1,53],[-1,79],[1,103]]){line([[0,-height],[side*24,-height-13],[side*34,-height-34]],'#453b39',10);line([[side*22,-height-12],[side*40,-height-17]],p.treeDark,6);}
            for(const side of [-1,1])line([[0,1],[side*18,-4],[side*25,3]],'#59483f',6);
            line([[0,-11],[-2,-35],[3,-45]],'#d97948',3);ellipse(10,4,7,2.8,'#b9513d');ellipse(-14,1,4.5,2.6,'#ed9856');ellipse(1,-4,3,1.2,'#ffd178');
        }else if(id==='ice'||variant!==1){
            line([[-10,3],[-2,-8],[-5,-30]],'#493b33',13);line([[-10,3],[-2,-8],[-5,-30]],'#aa743f',8);
            for(let i=0;i<4;i++){
                const width=46-i*8,top=-63-i*24,bottom=-17-i*25,lean=(variant===2?-1:1)*(i*3-3);
                c.beginPath();c.moveTo(lean,top);c.quadraticCurveTo(-width*.3+lean,top+25,-width,bottom);c.quadraticCurveTo(-width*.3,bottom+16,width,bottom+2);c.quadraticCurveTo(width*.35+lean,top+30,lean,top);c.closePath();c.fillStyle=i%2?p.tree:p.treeDark;c.fill();c.strokeStyle='#493b33';c.lineWidth=2.6;c.stroke();
                poly([[lean,top+3],[-width*.88,bottom],[-width*.5,bottom+3],[lean-4,bottom-3]],p.treeLight);
                if(id==='ice')poly([[0,top],[-width*.9,bottom-4],[-width*.38,bottom-2],[-width*.17,bottom-10],[width*.14,bottom-4],[width*.8,bottom-6]],'#e9f5ef');
            }
        }else{
            // Crooked trunk, root flare and offset foliage make every broadleaf tree read as alive.
            const lean=variant===2?-1:1;line([[0,2],[-5*lean,-34],[4*lean,-68],[-2*lean,-94]],'#493b33',15);line([[-1,2],[-3*lean,-34],[6*lean,-68],[0,-94]],'#8b6945',9);
            for(const side of [-1,1])line([[0,-3],[side*19,-10],[side*25,-4]],'#6d553b',4);
            const crowns=variant===2?[[-25,-62,25],[17,-61,29],[-4,-101,31],[-30,-91,24],[24,-99,28],[2,-76,33]]:[[-23,-58,25],[23,-65,28],[0,-101,29],[-22,-91,27],[16,-111,24],[0,-74,31]];
            for(const [x,y,r]of crowns){ellipse(x,y,r,r*.88,p.treeDark);ellipse(x-2,y-3,r-3,r*.88-3,p.tree);ellipse(x-r*.27,y-r*.25,r*.57,r*.42,p.treeLight);}
            for(let i=0;i<7;i++)ellipse(Math.sin(i*2.7)*27,-76+Math.cos(i*1.8)*28,3,2,i%2?p.treeLight+'aa':'#e8ca70aa',.5);
        }
        spriteCache.set(key,image);return image;
    }
    function scenery(c,project,s,decorations){
        for(const d of [...decorations].sort((a,b)=>a.y-b.y)){
            const weights=weightsAt(d.x,d.y);let choice=hash(Math.floor(d.x*7),Math.floor(d.y*7),71),index=0;
            while(index<3&&choice>weights[index]){choice-=weights[index];index++;}
            const id=names[index],variant=Math.floor((Number(d.variant)||0)*3)%3;
            // Trees now share the lush forest language across every region;
            // hills, relics and small details still carry local storytelling.
            const spriteId=d.kind==='tree'?'forest':id;
            const image=scenerySprite(spriteId,d.kind,variant),[x,y]=project(d.x,d.y),height=d.size*(d.kind==='tree'?1.95:d.kind==='hill'?1.7:d.kind==='relic'?1.9:2.7),width=height*160/192;
            c.drawImage(image,x-width/2,y-height*164/192,width,height);
        }
    }
    // Smooth, uneven outlines avoid both tile-shaped pads and perfect ellipses.
    function groundShape(c,x,y,rx,ry,phase){
        const points=[];
        for(let i=0;i<24;i++){const a=i/24*Math.PI*2,r=1+.075*Math.sin(a*3+phase)+.045*Math.cos(a*5-phase);points.push([x+Math.cos(a)*rx*r,y+Math.sin(a)*ry*r]);}
        c.beginPath();c.moveTo((points[23][0]+points[0][0])/2,(points[23][1]+points[0][1])/2);
        points.forEach((p,i)=>{const next=points[(i+1)%24];c.quadraticCurveTo(...p,(p[0]+next[0])/2,(p[1]+next[1])/2);});c.closePath();
    }
    function objectGround(c,x,y,s,{kind,point,biome=biomeAt(point.x,point.y),large=false,contactY}){
        if(waterAt(point.x,point.y))return;
        const village=kind==='village',node=kind==='nodes',shrine=kind==='shrine';
        const rx=village?1.62:shrine?2.35:node?.55:large?1.22:.48;
        const ry=village?.64:shrine?.85:node?.25:large?.43:.2;
        const baseY=contactY??(village?.91:shrine?1.7:node?.11:large?.49:.2);
        const phase=hash(Math.round(point.x*2),Math.round(point.y*2),301)*Math.PI*2;
        const weights=biome.weights,soil=mix([weights.forest,weights.ice,weights.sand,weights.lava],'roadEdge');
        c.save();c.translate(x,y);c.scale(s,s);c.lineCap='round';c.lineJoin='round';
        // Near shores, clip just this small apron to the authoritative land mask.
        // Interior objects need no mask; no extra canvas or texture per target.
        const left=-rx*1.55,right=rx*1.55,top=baseY-ry*1.6,bottom=baseY+ry*1.6+(village?.75:0);
        let coast=false;
        for(let dy=top;dy<=bottom&&!coast;dy+=.5)for(let dx=left;dx<=right;dx+=.5)if(waterAt(point.x+dx,point.y+dy)){coast=true;break;}
        if(coast){c.beginPath();for(let dy=top;dy<bottom;dy+=.2)for(let dx=left;dx<right;dx+=.2)if([[0,0],[.2,0],[0,.2],[.2,.2]].every(([a,b])=>!waterAt(point.x+dx+a,point.y+dy+b)))c.rect(dx,dy,.2,.2);c.clip();}
        // Broad translucent washes let the existing terrain show through the rim.
        c.save();c.translate(0,baseY);c.scale(rx*1.45,ry*1.45);
        groundShape(c,0,0,1,1,phase);c.clip();
        const wash=c.createRadialGradient(-.1,-.08,.12,0,0,1);wash.addColorStop(0,alpha(soil,.44));wash.addColorStop(.5,alpha(soil,.23));wash.addColorStop(1,alpha(soil,0));c.fillStyle=wash;c.fillRect(-1.2,-1.2,2.4,2.4);c.restore();
        // A tight contact shadow stays under the feet; it never outlines roofs.
        if(!node){c.save();c.translate(rx*.05,baseY-.035);c.scale(rx,ry*.66);
        const shadow=c.createRadialGradient(0,0,.08,0,0,1);shadow.addColorStop(0,'#493b3345');shadow.addColorStop(.5,'#493b3328');shadow.addColorStop(1,'#493b3300');c.fillStyle=shadow;c.fillRect(-1,-1,2,2);c.restore();}
        if(village){
            // Worn curved entrance, fading back into the local soil.
            for(let i=0;i<9;i++){const t=i/9,next=(i+1)/9;c.beginPath();c.moveTo(.18+Math.sin(t*1.4)*.42,baseY+.26+t*1.03);c.lineTo(.18+Math.sin(next*1.4)*.42,baseY+.26+next*1.03);c.strokeStyle=alpha(biome.road,(1-t)*.5);c.lineWidth=.38-t*.13;c.stroke();}
        }
        // Irregular groups of low details bind the silhouette to this climate.
        // Blended weights make the apron change gently through transition belts.
        for(let i=0;i<(village||shrine?7:node?5:3);i++){
            const seed=hash(Math.round(point.x*2)+i,Math.round(point.y*2),302),a=hash(Math.round(point.y*2)+i,Math.round(point.x*2),303)*Math.PI*2;
            const px=Math.cos(a)*rx*(.68+seed*.56),py=baseY+Math.sin(a)*ry*(.55+seed*.55),r=.045+seed*.09;
            if(village&&px>0&&px<.75&&py>baseY+.2)continue;
            // Flattened, half-buried stones have a narrow lower edge, not a plinth.
            if(i%3===0){groundShape(c,px,py,r*1.15,r*.46,phase+i);c.fillStyle=alpha(biome.rock,.58);c.fill();groundShape(c,px-r*.15,py-r*.14,r*.85,r*.3,phase+i);c.fillStyle=alpha(biome.rockLight,.72);c.fill();}
            if(weights.forest>.03){
                c.globalAlpha=weights.forest*.72;c.strokeStyle=biome.tree;c.lineWidth=.026;
                for(let blade=-1;blade<=1;blade++){c.beginPath();c.moveTo(px,py);c.quadraticCurveTo(px+blade*r*.65,py-r*.6,px+blade*r,py-r*(1.1-Math.abs(blade)*.3));c.stroke();}
                if(i%4===1){c.fillStyle=biome.accent;c.beginPath();c.ellipse(px+r,py,r*.5,r*.22,-.5,0,Math.PI*2);c.fill();}
            }
            if(weights.ice>.03){
                c.globalAlpha=weights.ice*.85;groundShape(c,px,py,r*2.1,r*.7,phase+i);c.fillStyle=biome.bank;c.fill();groundShape(c,px-r*.2,py-r*.18,r*2.1,r*.58,phase+i);c.fillStyle=biome.rockLight;c.fill();
            }
            if(weights.sand>.03){
                c.globalAlpha=weights.sand*.58;c.beginPath();c.moveTo(px-r*2,py+r*.2);c.quadraticCurveTo(px,py-r*.65,px+r*2.8,py-r*.1);c.strokeStyle=biome.shore;c.lineWidth=r*.46;c.stroke();
            }
            if(weights.lava>.03){
                c.globalAlpha=weights.lava*.65;groundShape(c,px,py,r*1.4,r*.6,phase+i);c.fillStyle=biome.rock;c.fill();
                if(i%4===0){c.beginPath();c.moveTo(px-r,py);c.lineTo(px,py-r*.15);c.lineTo(px+r*.7,py+r*.14);c.strokeStyle=biome.accent;c.lineWidth=.025;c.stroke();}
            }
            c.globalAlpha=1;
        }
        c.restore();
    }
    function settlement(c,x,y,s,skin,worldPoint={x:50,y:50}){
        objectGround(c,x,y,s,{kind:'village',point:worldPoint});
    }
    return {ground,biomeAt,scenery,water,waterAt,bridges:bridgesLayer,bridgePoints:bridges,ambience,settlement,objectGround,lakes,riverX};
})();
