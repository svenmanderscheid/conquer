'use strict';
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..');
const creatures={
 ironkeep:['Eisenkoloss','Ein gepanzerter Festungsgolem stampft für die Eisenwacht über die Weltkarte.'],
 rosehall:['Rosenhirsch','Ein elfenbeinfarbener Hirsch trägt ein lebendes Geweih aus Rosenblüten.'],
 sandspire:['Sonnenskarabäus','Ein goldener Riesenskarabäus zieht mit Türkissteinen und Sonnenpanzer durch die Dünen.'],
 tidewatch:['Leuchtrücken','Eine große Meeresschildkröte trägt einen kleinen Leuchtturm auf ihrem Panzer.'],
 winterhold:['Frostmammut','Ein zottiges Mammut mit Eisstoßzähnen bahnt der Winterfeste den Weg.'],
 jadecourt:['Jadeglockenlöwe','Ein jadegrüner Tempellöwe trägt Pagodendetails und eine klingende Hofglocke.'],
 emberforge:['Glutsalamander','Ein geschmiedeter Salamander glüht zwischen Kupferplatten und dunklem Eisen.'],
 ravenloft:['Rabenfürst','Ein riesiger gepanzerter Rabe gleitet mit violetten Bändern aus dem Rabenhorst.'],
 clockwork:['Uhrwerkhase','Ein kupferner Maschinenhase sprintet mit Zahnrädern und kleinen Dampfstößen.'],
 sapphire:['Saphirpfau','Ein königlicher Pfau entfaltet einen Fächer aus großen geschliffenen Saphiren.'],
 astral:['Sternenwal','Ein schwebender Himmelswal zieht einen Schweif aus Sternenlicht hinter sich her.'],
 leviathan:['Korallenleviathan','Ein türkisfarbener Meeresleviathan windet sich zwischen lebenden Korallen.'],
 yggdrasil:['Wurzelkoloss','Ein uralter Baumkoloss schreitet auf mächtigen Wurzeln durch die Welt.'],
 tempest:['Sturmqualle','Eine schwebende Gewitterqualle lädt ihre langen Tentakel mit Blitzen auf.'],
 eclipse:['Finstersonnenwagen','Ein reiterloser Obsidianwagen trägt eine schwarze Sonne mit violetter Korona.']
};
const write=(file,value)=>fs.writeFileSync(file,JSON.stringify(value,null,2)+'\n','utf8');
const catalogFile=path.join(root,'data','march_skins.json'),catalog=JSON.parse(fs.readFileSync(catalogFile,'utf8'));
for(const entry of catalog.entries)if(creatures[entry.id])[entry.name,entry.description]=creatures[entry.id];
write(catalogFile,catalog);
const bundlesFile=path.join(root,'data','theme_bundles.json'),bundles=JSON.parse(fs.readFileSync(bundlesFile,'utf8'));
for(const theme of bundles.themes)if(creatures[theme.id])theme.march_name=creatures[theme.id][0];
write(bundlesFile,bundles);
console.log(`Synced ${Object.keys(creatures).length} creature names and descriptions.`);
