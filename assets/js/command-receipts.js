/* A single unresolved army command per account/world survives reloads. */
window.ConquerCommandReceipts=({scope,storage})=>{
    storage=storage||{getItem:k=>window.sessionStorage.getItem(k),setItem:(k,v)=>window.sessionStorage.setItem(k,v),removeItem:k=>window.sessionStorage.removeItem(k)};
    const memory=new Map();
    const protectedPath=(path,body)=>['march/dispatch','march/dispatch-charm','march/dispatch-player','march/dispatch-scout','march/dispatch-gather','march/dispatch-field-attack','march/reinforce','rally/start','rally/start-monster','rally/join'].includes(path)||(path==='defense/action'&&['scout','reinforce','promotion.start','wall.repair'].includes(body?.action));
    const key=()=>`conquer:command:${scope()}`;
    function pending(){const k=key();if(memory.has(k))return memory.get(k);try{const saved=JSON.parse(storage.getItem(k)||'null');if(saved&&protectedPath(saved.path,saved.body)&&typeof saved.body.operation_key==='string'){memory.set(k,saved);return saved;}}catch{}return null;}
    function prepare(path,body){
        if(!protectedPath(path,body))return null;
        const old=pending();
        if(old){
            const clean=value=>{const copy={...value};delete copy.operation_key;return JSON.stringify(copy);};
            if(old.path!==path||clean(old.body)!==clean(body))throw new Error('Bitte prüfe zuerst deinen unbestätigten Auftrag.');
            return old;
        }
        const next={path,body:{...body,operation_key:crypto.randomUUID()}};
        // Refuse a new command when it cannot be preserved through a reload.
        try{storage.setItem(key(),JSON.stringify(next));}catch{throw new Error('Der Browser kann den Auftrag nicht sichern. Bitte erlaube Sitzungsspeicher und versuche es erneut.');}
        memory.set(key(),next);return next;
    }
    function complete(receipt){if(pending()?.body.operation_key!==receipt?.body.operation_key)return;memory.set(key(),null);try{storage.removeItem(key());}catch{}}
    return {prepare,pending,complete,protectedPath};
};
