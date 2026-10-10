(() => {
    'use strict';
    // Fixed-size diagnostics only. No bodies, messages, form values, tokens, or storage inventory.
    const config = window.CONQUER_TELEMETRY;
    if (!config || !config.csrf || typeof window.fetch !== 'function' || window.ConquerTelemetry) return;
    const originalFetch = window.fetch.bind(window);
    const base = String(window.CONQUER_BASE || '');
    const endpoint = base + '/api/telemetry';
    const storageKey = 'uok-diagnostics:' + String(config.player || 0);
    const maxQueue = 50, maxAge = 86400000;
    let queue = [], sending = false, nextFlush = 0, dropped = 0, failedSince = 0, previousCode = '';
    const token = (value, length = 64) => String(value ?? '').replace(/[^a-zA-Z0-9_.:/-]/g, '').slice(0, length);
    const id = () => window.crypto?.randomUUID ? window.crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    const pathOnly = value => {
        try {
            const url = new URL(String(value), location.href);
            if (url.origin !== location.origin) return '';
            const path = url.pathname.startsWith(base + '/') ? url.pathname.slice(base.length) : url.pathname;
            return /^\/(api|assets)\//.test(path) ? path.replace(/[a-zA-Z0-9_-]{48,}/g, '[redacted]').slice(0,160) : '';
        } catch { return ''; }
    };
    const filePath = value => {
        const path = pathOnly(value);
        return path.startsWith('/assets/') ? path : '';
    };
    const environment = () => ({
        screen: token(location.hash.slice(1).split(/[?&/]/)[0],32),
        browser: /Firefox\//.test(navigator.userAgent) ? 'Firefox' : /Edg\//.test(navigator.userAgent) ? 'Edge' : /Chrome\//.test(navigator.userAgent) ? 'Chromium' : /Safari\//.test(navigator.userAgent) ? 'Safari' : 'Other',
        platform: /Android/.test(navigator.userAgent) ? 'Android' : /iPhone|iPad/.test(navigator.userAgent) ? 'iOS' : /Windows/.test(navigator.userAgent) ? 'Windows' : /Mac/.test(navigator.userAgent) ? 'macOS' : 'Other',
        online: navigator.onLine !== false,
        visibility: document.visibilityState === 'hidden' ? 'hidden' : 'visible',
        client_release: token(config.release),
    });
    const persist = () => {
        queue = queue.filter(event => event && Number(event.at) >= Date.now() - maxAge).slice(-maxQueue);
        try { sessionStorage.setItem(storageKey, JSON.stringify(queue)); } catch { /* memory queue remains bounded */ }
    };
    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
        if (Array.isArray(saved)) queue = saved.filter(event => event && typeof event.id === 'string' && Number(event.at) >= Date.now()-maxAge).slice(-maxQueue);
    } catch { /* unavailable or malformed storage is nonfatal */ }
    const emit = (code, details = {}) => {
        const now = Date.now(), route = pathOnly(details.route || ''), context = environment();
        // Only these fixed metadata fields can be supplied by callers.
        for (const key of ['client_request_id','exception_class','previous_code']) if (details[key]) context[key]=token(details[key]);
        if (details.file) context.file=filePath(details.file);
        for (const key of ['line','column','queue_dropped']) if (Number.isFinite(Number(details[key]))) context[key]=Math.max(0,Math.min(1000000,Math.floor(Number(details[key]))));
        // Repeated polling failures cannot evict every other diagnostic from the offline queue.
        const recent = queue[queue.length-1];
        if (recent && recent.code===code && recent.route===route && now-recent.at<10000
            && (recent.context.file||'')===(context.file||'') && (recent.context.line||0)===(context.line||0)
            && (recent.context.column||0)===(context.column||0) && (recent.context.exception_class||'')===(context.exception_class||'')) {
            recent.context.repeat_count=Math.min(1000000,(recent.context.repeat_count||1)+1);persist();return;
        }
        if (queue.length>=maxQueue) { queue.shift(); dropped++; }
        queue.push({id:id(),code:token(code),at:now,world_id:Number(config.world)||0,route,duration_ms:Math.max(0,Math.min(86400000,Number(details.duration_ms)||0)),context});
        persist();
    };
    async function flush() {
        if (sending || !queue.length || Date.now()<nextFlush || navigator.onLine===false) return;
        sending=true;
        const batch=queue.slice(0,10);
        nextFlush=Date.now()+12000;
        try {
            const response=await originalFetch(endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',
                headers:{'Content-Type':'application/json','X-CSRF-Token':config.csrf},
                body:JSON.stringify({events:batch}),signal:AbortSignal.timeout(8000)});
            if (response.ok) {
                const sent=new Set(batch.map(event=>event.id)); queue=queue.filter(event=>!sent.has(event.id));
                if(dropped){const count=dropped;dropped=0;emit('CLIENT_QUEUE_DROPPED',{queue_dropped:count});}
                persist();
            } else if(response.status===401 || response.status===403) {
                queue=[];persist();nextFlush=Infinity;
            } else nextFlush=Date.now()+Math.max(30000,Math.min(300000,(Number(response.headers.get('Retry-After'))||30)*1000));
        } catch { nextFlush=Date.now()+30000; }
        finally { sending=false; }
    }
    window.fetch = async function(input, init) {
        const url = typeof input==='string' || input instanceof URL ? String(input) : input?.url;
        const route=pathOnly(url);
        if (!route.startsWith('/api/') || route==='/api/telemetry') return originalFetch(input,init);
        const start=Date.now(), clientId=id();
        // Preserve Request options/body and caller-provided headers; observe only our own API.
        let options=init;
        try {
            const headers=new Headers(init?.headers || (typeof Request!=='undefined' && input instanceof Request ? input.headers : undefined));
            headers.set('X-Client-Request-ID',clientId);
            options={...init,headers};
        } catch { /* Never fail gameplay merely because metadata cannot be attached. */ }
        try {
            const response=await originalFetch(input,options);
            if(failedSince){emit('CONNECTION_RESTORED',{route,duration_ms:Date.now()-failedSince,previous_code:previousCode,client_request_id:clientId});failedSince=0;previousCode='';}
            return response;
        } catch(error) {
            // Explicit caller aborts include navigation changes, not necessarily connection loss.
            if(error?.name!=='AbortError'){
                const code=error?.name==='TimeoutError'?'NETWORK_TIMEOUT':'NETWORK_FAILURE';
                failedSince ||= start;previousCode=code;
                emit(code,{route,duration_ms:Date.now()-start,client_request_id:clientId});
            }
            throw error;
        }
    };
    window.addEventListener('error', event => {
        if(event.target && event.target!==window){const file=filePath(event.target.src||event.target.href||'');if(file)emit('RESOURCE_ERROR',{file});return;}
        emit('JAVASCRIPT_ERROR',{file:event.filename,line:event.lineno,column:event.colno,exception_class:event.error?.name || 'Error'});
    },true);
    window.addEventListener('unhandledrejection',event=>emit('UNHANDLED_REJECTION',{exception_class:event.reason?.name || 'Error'}));
    window.addEventListener('offline',()=>{failedSince ||= Date.now();previousCode='BROWSER_OFFLINE';emit('BROWSER_OFFLINE');});
    window.addEventListener('online',()=>{emit('BROWSER_ONLINE');nextFlush=0;void flush();});
    document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')void flush();});
    window.addEventListener('pagehide',persist);
    window.ConquerTelemetry=Object.freeze({report:emit,flush});
    setInterval(()=>void flush(),12000);
    if(queue.length)void flush();
})();
