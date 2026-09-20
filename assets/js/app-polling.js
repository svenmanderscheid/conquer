(() => {
    'use strict';
    // A single request loop, stopped while hidden/offline and resumed immediately.
    window.ConquerPolling = function({refresh,delay,onError=()=>{}}) {
        let timer=0,pending=false,stopped=false,resumePending=false;
        const available=()=>!stopped&&!document.hidden&&navigator.onLine!==false;
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
        function resume(){clearTimeout(timer);timer=0;if(available())run();}
        document.addEventListener('visibilitychange',resume);
        window.addEventListener('online',resume);
        window.addEventListener('offline',resume);
        window.addEventListener('pageshow',resume);
        schedule();
        return {resume,stop(){stopped=true;clearTimeout(timer);document.removeEventListener('visibilitychange',resume);for(const type of ['online','offline','pageshow'])window.removeEventListener(type,resume);}};
    };
})();
