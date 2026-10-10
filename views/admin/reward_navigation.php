<?php declare(strict_types=1);
$rewardNavigationWorld=is_scalar($_GET['world_id']??null)?max(0,(int)$_GET['world_id']):$selectedWorld;
?>
<nav class="reward-ledger-tabs" aria-label="<?= \Conquer\Game\Locale::html('admin.reward_ledger.navigation') ?>">
<?php foreach(['rules','actual','invalid'] as $rewardPage): ?>
<a href="<?= ah(APP_BASE.'/admin/rewards?'.http_build_query(['tab'=>$rewardPage,'world_id'=>$rewardNavigationWorld,'days'=>is_scalar($_GET['days']??null)?(int)$_GET['days']:7])) ?>" class="<?= $rewardPage===$rewardTab?'active':'' ?>" <?= $rewardPage===$rewardTab?'aria-current="page"':'' ?>><?= \Conquer\Game\Locale::html('admin.reward_ledger.tab_'.$rewardPage) ?></a>
<?php endforeach ?>
</nav>
