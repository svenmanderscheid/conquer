<?php
declare(strict_types=1);
$alphaText=static fn(string $key,array $parameters=[]):string=>\Conquer\Game\Locale::html('alpha.metrics.'.$key,$parameters);
?>
<section class="card alpha-playtest" data-i18n-scope>
    <h2><?= $alphaText('title') ?></h2>
    <p><?= $alphaText('intro') ?></p>
    <h3><?= $alphaText('return_title') ?></h3>
    <p class="subtle"><?= $alphaText('return_scope') ?></p>
    <?php if(!$alpha['activity_available']): ?><p><?= $alphaText('unavailable') ?></p><?php else: ?>
    <div class="stats">
    <?php foreach($alpha['retention'] as $day=>$row): ?>
        <div class="stat"><small><?= $alphaText('day',['day'=>$day]) ?></small><strong><?= $row['percent']===null?'—':an($row['percent']).' %' ?></strong><span><?= $row['eligible']?$alphaText('returned',['count'=>$row['returned'],'total'=>$row['eligible']]):$alphaText('waiting') ?></span></div>
    <?php endforeach ?>
    </div><?php endif ?>
    <h3><?= $alphaText('progress') ?></h3>
    <p class="subtle"><?= $alphaText('progress_scope',['total'=>$alpha['cities']]) ?></p>
    <div class="stats"><?php foreach($alpha['milestones'] as $key=>$count): ?><div class="stat"><small><?= $alphaText($key) ?></small><strong><?= an($count) ?></strong></div><?php endforeach ?></div>
    <h3><?= $alphaText('balance') ?></h3>
    <div class="stats">
        <div class="stat"><small><?= $alphaText('supporters') ?></small><strong><?= an($alpha['territory_supporters']) ?></strong></div>
        <div class="stat"><small><?= $alphaText('conquests') ?></small><strong><?= an($alpha['conquests']) ?></strong></div>
        <div class="stat"><small><?= $alphaText('wounded') ?></small><strong><?= an($alpha['wounded']) ?></strong></div>
    </div>
    <p><?= $alphaText('resources') ?>: <?php $resourceLabels=['food'=>'food','lumber'=>'wood','stone'=>'stone','gold'=>'gold'];foreach($alpha['average_resources'] as $resource=>$amount): ?><span><?= $alphaText($resourceLabels[$resource]) ?> <?= an((int)round($amount)) ?> · </span><?php endforeach ?></p>
    <h3><?= $alphaText('control') ?></h3>
    <?php if(!$alpha['territory_control']): ?><p><?= $alphaText('no_control') ?></p><?php endif ?>
    <?php foreach($alpha['territory_control'] as $owner): ?><div class="split"><span data-user-content><?= ah($owner['name']) ?></span><strong><?= $alphaText('communes',['count'=>$owner['communes']]) ?></strong></div><?php endforeach ?>
    <p class="subtle"><?= $alphaText('limits') ?></p>
</section>
