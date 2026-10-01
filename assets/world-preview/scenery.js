// Decorative trees have no gameplay collision. Recompute visibility from occupancy.
export function hash(x,y){let h=Math.imul(x,374761393)^Math.imul(y,668265263)^1979;h=Math.imul(h^(h>>>13),1274126177);return(h^(h>>>16))>>>0;}
export function overlaps(a,b){return a.x-a.w/2<b.x+b.w/2+18&&a.x+a.w/2>b.x-b.w/2-18&&a.y-a.h<b.y+18&&a.y>b.y-b.h-18;}
export function treesIn(bounds,objects){const trees=[],step=220;
 for(let gy=Math.floor(bounds.top/step)-1;gy<=Math.ceil(bounds.bottom/step)+1;gy++)for(let gx=Math.floor(bounds.left/step)-1;gx<=Math.ceil(bounds.right/step)+1;gx++){
  const seed=hash(gx,gy);if(seed%100>48)continue;
  const count=seed%7===0?3:1;
  for(let n=0;n<count;n++){const size=100+(seed>>>10)%36,tree={x:gx*step+35+(seed>>>8)%125+n*44,y:gy*step+40+(seed>>>17)%110+n%2*28,w:size,h:size,kind:(seed+n)%2,tree:true};
   if(!objects.some(obj=>overlaps(tree,obj)))trees.push(tree);
  }
 }
 return trees;
}
