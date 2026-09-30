<?php
declare(strict_types=1);
use Conquer\Game\Locale;
use Conquer\Admin\ItemPresentation;
$tableItems=array_column(ItemPresentation::catalog(),null,'code');
$tableNumber=static fn(int|float $n):string=>Locale::current()==='en'
    ?rtrim(rtrim(number_format($n,2,'.',','),'0'),'.')
    :rtrim(rtrim(number_format($n,2,',','.'),'0'),',');
$renderTableDrop=static function(array $drop)use($tableItems,$tableNumber):void {
    $item=$tableItems[$drop['item_code']]??null;
    ?>
    <li class="monster-table-drop <?= $drop['probability']<=0?'is-disabled':'' ?>">
        <span><?= ah(Locale::text($item['name']??(string)$drop['item_code'])) ?></span>
        <strong><?= ah($tableNumber($drop['count'])) ?>×</strong>
        <span class="monster-drop-chance"><?= ah($tableNumber($drop['probability']*100)) ?>%</span>
    </li>
    <?php
};
?>
<p class="monster-table-scope"><?= Locale::html('admin.drops.table_scope') ?> <strong data-user-content><?= ah(Locale::text($scopeWorld?'Welt':'Alle Welten')) ?><?= $scopeWorld?' · '.ah($scopeLabel):'' ?></strong></p>
<p class="monster-table-scroll-hint"><?= Locale::html('admin.drops.table_scroll') ?></p>
<div class="monster-table-scroll" tabindex="0" role="region" aria-label="<?= Locale::html('admin.drops.table_title') ?>">
<table class="monster-reward-table">
<caption><?= Locale::html('admin.drops.table_caption') ?></caption>
<thead><tr>
<th scope="col"><?= Locale::html('admin.drops.table_monster') ?></th>
<th scope="col"><?= Locale::html('admin.drops.level_filter') ?></th>
<th scope="col"><?= Locale::html('admin.drops.table_items') ?></th>
<th scope="col"><?= Locale::html('admin.drops.table_resources') ?></th>
<th scope="col"><?= Locale::html('admin.drops.table_crystals') ?></th>
<th scope="col"><?= Locale::html('admin.drops.rule_filter') ?></th>
<th scope="col"><?= Locale::html('admin.drops.table_action') ?></th>
</tr></thead>
<tbody>
<?php foreach($sources as $entry):
    $overview=$sourceOverview[$entry['key']];$config=$overview['config'];$active=(string)$entry['key']===$key;
    $drops=$config['drops'];
    $search=Locale::text($entry['name']).' '.Locale::text($entry['subtitle']).' '.$entry['key'];
    foreach($drops as $drop)$search.=' '.Locale::text($tableItems[$drop['item_code']]['name']??'').' '.$drop['item_code'];
?>
<tr class="reward-source-row <?= $active?'is-selected':'' ?>" data-source-level-value="<?= $overview['level'] ?>" data-source-custom="<?= $overview['custom']?'1':'0' ?>" data-source-items="<?= $overview['enabled'] ?>" data-source-name="<?= ah(mb_strtolower($search)) ?>">
<th scope="row"><div class="monster-table-name"><?= adminIcon($entry['image']) ?><span><strong><?= ah(Locale::text($entry['name'])) ?></strong><small><?= Locale::html($entry['definition']['type']==='rally'?'admin.drops.table_rally':'admin.drops.table_solo') ?></small><?php if(!$entry['active']): ?><small class="monster-table-inactive"><?= Locale::html('admin.drops.table_inactive') ?></small><?php endif ?></span></div></th>
<td class="monster-table-level"><?= $overview['level'] ?></td>
<td><?php if($drops): ?><ul class="monster-table-drops"><?php foreach(array_slice($drops,0,3) as $drop)$renderTableDrop($drop); ?></ul>
<?php if(count($drops)>3): ?><details class="monster-table-more"><summary><?= Locale::html('admin.drops.table_more',['count'=>count($drops)-3]) ?></summary><ul class="monster-table-drops"><?php foreach(array_slice($drops,3) as $drop)$renderTableDrop($drop); ?></ul></details><?php endif ?>
<?php else: ?><span class="subtle"><?= Locale::html('admin.drops.table_empty') ?></span><?php endif ?></td>
<td><div class="monster-table-resources"><?php foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'] as $resource=>$label): ?><span><?= adminIcon('ui-resources/'.$resource.'.png') ?><span><small><?= ah(Locale::text($label)) ?></small><strong><?= ah($tableNumber($config['resource_reward'][$resource]??0)) ?></strong></span></span><?php endforeach ?></div></td>
<td class="monster-table-gems"><strong><?= ah($tableNumber($config['gems_drop']['amount'])) ?></strong><small><?= ah($tableNumber($config['gems_drop']['chance']*100)) ?>%</small></td>
<td><span class="monster-rule <?= $overview['custom']?'is-custom':'' ?>"><?= Locale::html($overview['custom']?'admin.drops.custom':'admin.drops.inherited') ?></span></td>
<td><a class="button secondary" href="<?= APP_BASE ?>/admin/rewards?type=monster&amp;source=<?= ah($entry['key'].$scopeQuery) ?>#reward-editor" aria-label="<?= Locale::html('admin.drops.table_edit_source',['name'=>Locale::text($entry['name']),'level'=>$overview['level']]) ?>"><?= Locale::html('admin.drops.table_edit') ?></a></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
