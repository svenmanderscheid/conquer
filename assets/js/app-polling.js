(() => {
    'use strict';
    // A single request loop, stopped while hidden/offline and resumed immediately.
    window.ConquerPolling = function({refresh,delay,onError=()=>{},onAvailabilityChange=()=>{}}) {
        let timer=0,pending=false,stopped=false,resumePending=false,nativeActive=true,nativeListener=null,lastAvailability='';
        const active=()=>!stopped&&!document.hidden&&nativeActive;
        const available=()=>active()&&navigator.onLine!==false;
        function announce() {
            const status={active:active(),online:navigator.onLine!==false},signature=JSON.stringify(status);
            if(signature!==lastAvailability){lastAvailability=signature;onAvailabilityChange(status);}
        }
        function schedule(ms=delay()) {
            clearTimeout(timer);timer=0;
            if(available())timer=setTimeout(run,ms);
        }
        async function run() {
            clearTimeout(timer);timer=0;
            if(!available())return;
            if(pending){resumePending=true;return;}
            pending=true;
            try {await refresh();}catch(error){onError(error);}
            finally {pending=false;const immediate=resumePending;resumePending=false;schedule(immediate?0:delay());}
        }
        function resume(){if(stopped)return;clearTimeout(timer);timer=0;announce();if(available())run();}
        document.addEventListener('visibilitychange',resume);
        window.addEventListener('online',resume);
        window.addEventListener('offline',resume);
        window.addEventListener('pageshow',resume);
        // The native WebView may stay visible while its Activity is in the background.
        // Subscribe only to app state; leave Capacitor's Back handler untouched.
        const app=window.Capacitor?.isNativePlatform?.()?window.Capacitor.Plugins?.App:null;
        if(app?.addListener){
            try {
                nativeListener=Promise.resolve(app.addListener('appStateChange',({isActive})=>{
                    if(stopped||typeof isActive!=='boolean'||nativeActive===isActive)return;
                    nativeActive=isActive;resume();
                })).catch(()=>null);
            }catch{/* Browser visibility remains available if the bridge fails. */}
        }
        announce();schedule();
        return {resume,isActive:active,stop(){stopped=true;clearTimeout(timer);document.removeEventListener('visibilitychange',resume);for(const type of ['online','offline','pageshow'])window.removeEventListener(type,resume);nativeListener?.then(handle=>handle?.remove()).catch(()=>{});}};
    };
})();
