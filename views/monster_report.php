<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Conquer · Monster-Kampfbericht</title>
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/combat-report.css?v=<?= filemtime(ROOT_DIR.'/assets/css/combat-report.css') ?>">
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR.'/assets/css/village-theme.css') ?>">
</head>
<body class="mobile-game mr-direct">
<main><a class="button" href="<?= APP_BASE ?>/city#reports">‹ Zur Post</a><div id="monster-report-root"></div><p id="report-error" role="alert"></p></main>
<script src="<?= APP_BASE ?>/assets/js/monster-report.js?v=<?= filemtime(ROOT_DIR.'/assets/js/monster-report.js') ?>"></script>
<script>
(() => {
    const report=<?= json_encode($monsterReport,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const base=<?= json_encode(APP_BASE,JSON_HEX_TAG|JSON_HEX_AMP) ?>;
    const root=document.querySelector('#monster-report-root');
    root.innerHTML=ConquerMonsterReport.render(report,{base});
    ConquerMonsterReport.bind({root,getReport:()=>report,base,toast:message=>document.querySelector('#report-error').textContent=message,locateReport:item=>{
        const charm=item.outcome==='attacker_wins'?item.details?.charm:null,url=new URL(base+'/city',location.origin);
        url.searchParams.set('map_x',String(charm?.x??item.target_x));url.searchParams.set('map_y',String(charm?.y??item.target_y));url.searchParams.set('map_kind',charm?'charms':'monsters');
        if(charm?.id??item.target_id)url.searchParams.set('map_id',String(charm?.id??item.target_id));url.hash='world';location.href=url;
    }});
    document.addEventListener('click',async event=>{
        const button=event.target.closest('[data-action]');if(!button)return;
        if(button.dataset.action==='monster-report-back'){location.href=base+'/city#reports';return;}
        if(button.dataset.action!=='monster-report-delete')return;
        button.disabled=true;
        try {
            const response=await fetch(base+'/api/battle/report/'+report.id+'/delete',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':<?= json_encode($session['csrf_token']) ?>,'X-World-ID':String(report.world_id)},body:JSON.stringify({expected_world_id:Number(report.world_id)})});
            if(!response.ok)throw new Error('Der Bericht konnte nicht gelöscht werden. Bitte erneut versuchen.');
            location.href=base+'/city#reports';
        } catch(error){document.querySelector('#report-error').textContent=error.message;button.disabled=false;}
    });
})();
</script>
</body>
</html>
