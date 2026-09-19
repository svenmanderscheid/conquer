// Presentation adapter for the server-owned, sequential cosmetic bundle catalog.
// Prices, contents, ownership and checkout availability are never invented here.
window.ConquerThemeBundles = (()=>{
    const text=(value,fallback='')=>typeof value==='string'&&value.trim()?value.trim():fallback;
    const integer=value=>Number.isFinite(Number(value))?Math.max(0,Math.floor(Number(value))):null;
    const normalizeResources=value=>{
        const source=value&&typeof value==='object'?value:{};
        return Object.freeze(['food','lumber','stone','gold'].reduce((result,key)=>{
            const amount=integer(source[key]);if(amount!==null)result[key]=amount;return result;
        },{}));
    };
    const normalizeEntry=(row,index)=>{
        if(!row||typeof row!=='object')return null;
        const id=text(row.id||row.bundle_id||row.code),themeId=text(row.theme_id||row.theme||row.skin_id);
        const step=integer(row.step??row.tier??row.order),priceCents=integer(row.price_cents??row.price?.amount);
        if(!id||!themeId||!step||priceCents===null)return null;
        const rawContents=row.contents||row.rewards||{};
        const cosmeticSource=rawContents.cosmetic||row.cosmetic||{};
        const cosmeticType=text(cosmeticSource.type||row.cosmetic_type);
        const cosmeticId=text(cosmeticSource.id||row.cosmetic_id||themeId);
        const cosmeticName=text(cosmeticSource.name||row.cosmetic_name);
        const owned=Boolean(row.owned||row.purchased||row.status==='owned'||row.status==='purchased');
        const unlocked=Boolean(row.unlocked||row.available||row.can_purchase||row.status==='available');
        return Object.freeze({
            id,theme_id:themeId,theme_name:text(row.theme_title||row.theme_name||row.series_name,themeId),step,
            title:text(row.title||row.name,`Paket ${step}`),price_cents:priceCents,
            currency:text(row.currency).toUpperCase(),owned,unlocked,next:Boolean(row.next||row.is_next),
            cosmetic_owned:Boolean(row.cosmetic_owned??cosmeticSource.owned??owned),
            status:text(row.status,owned?'owned':unlocked?'available':'locked'),
            lock_reason:text(row.lock_reason||row.requirement),
            contents:Object.freeze({
                resources:normalizeResources(rawContents.resources||row.resources),
                gems:integer(rawContents.gems??row.gems),
                cosmetic:Object.freeze({type:cosmeticType,id:cosmeticId,name:cosmeticName})
            }),
            source_index:index
        });
    };
    const normalize=raw=>{
        const source=raw?.theme_bundles&&typeof raw.theme_bundles==='object'?raw.theme_bundles:raw;
        const entries=(Array.isArray(source?.entries)?source.entries:[]).map(normalizeEntry).filter(Boolean)
            .sort((a,b)=>a.theme_name.localeCompare(b.theme_name,'de')||a.step-b.step||a.source_index-b.source_index);
        const paymentConfigured=Boolean(source?.payment_configured??source?.checkout_available??source?.provider_configured);
        const currency=text(source?.currency,'EUR').toUpperCase();
        return Object.freeze({
            entries:Object.freeze(entries),payment_configured:paymentConfigured,currency,
            provider_message:text(source?.provider_message||source?.checkout_message),
            next_bundle_id:text(source?.next_bundle_id)
        });
    };
    const money=(cents,currency='EUR')=>new Intl.NumberFormat('de-DE',{style:'currency',currency:currency||'EUR'}).format((integer(cents)||0)/100);
    return Object.freeze({normalize,money});
})();
