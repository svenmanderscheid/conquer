// Build helper only: official GML EPSG:3035 axis order is northing, easting.
const fs=require('node:fs'),proj4=require(process.argv[2]);
const transform=proj4('+proj=laea +lat_0=52 +lon_0=10 +x_0=4321000 +y_0=3210000 +ellps=GRS80 +units=m +no_defs','EPSG:4326');
const lists=JSON.parse(fs.readFileSync(0,'utf8'));
process.stdout.write(JSON.stringify(lists.map(text=>{
 const values=text.trim().split(/\s+/).map(Number),points=[];
 for(let i=0;i<values.length;i+=2)points.push(transform.forward([values[i+1],values[i]]));
 return points;
})));
