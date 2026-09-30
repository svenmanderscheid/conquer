<?php
declare(strict_types=1);
use Conquer\Game\Locale;
use Conquer\Admin\ItemPresentation;
$tableItems=array_column(ItemPresentation::catalog(),null,'code');
$frequencies=[];$matrixRows=[];
foreach($sources as $entry){
    $config=$sourceOverview[$entry['key']]['config'];$drops=[];
    foreach($config['drops'] as $drop){
        $code=(string)$drop['item_code'];
        $drops[$code]=['quantity'=>$drop['count'],'chance'=>round($drop['probability']*100,4)];
        $frequencies[$code]=($frequencies[$code]??0)+($entry['active']&&$drop['probability']>0?1:0);
    }
    $matrixRows[$entry['key']]=$drops;
}
arsort($frequencies);
$defaultColumns=array_slice(array_keys($frequencies),0,4);
$choices=[];
foreach(array_keys($frequencies) as $code)if(isset($tableItems[$code]))$choices[$code]=$tableItems[$code];
uasort($choices,static fn($a,$b)=>strnatcasecmp(Locale::text($a['name']),Locale::text($b['name'])));
$choiceGroups=[];
foreach($choices as $code=>$item)$choiceGroups[Locale::text($item['category_name'])][$code]=$item;
uksort($choiceGroups,'strnatcasecmp');
$canEdit=($_SESSION['admin']['role']??'')==='superadmin';
$tableNumber=static function(int|float $n):string {
    $decimal=Locale::current()==='en'?'.':',';$thousands=$decimal==='.'?',':'.';
    return rtrim(rtrim(number_format($n,4,$decimal,$thousands),'0'),$decimal);
};
?>
<div class="monster-matrix-toolbar">
<div class="monster-matrix-views" role="group" aria-label="<?= Locale::html('admin.drops.matrix_view') ?>">
<button type="button" class="secondary" data-matrix-switch="items" aria-pressed="true"><?= Locale::html('admin.drops.matrix_items') ?></button>
<button type="button" class="secondary" data-matrix-switch="resources" aria-pressed="false"><?= Locale::html('admin.drops.table_resources') ?></button>
</div>
<p class="monster-table-scope"><?= Locale::html('admin.drops.table_scope') ?> <strong data-user-content><?= ah($scopeWorld?$scopeLabel:Locale::text('Alle Welten')) ?></strong></p>
</div>
<p class="matrix-legend" data-matrix-legend><?= Locale::html('admin.drops.matrix_hint') ?></p>
<div class="monster-table-scroll" tabindex="0" role="region" aria-label="<?= Locale::html('admin.drops.table_title') ?>">
<table class="monster-reward-table reward-matrix" data-matrix-view="items" data-can-edit="<?= $canEdit?'1':'0' ?>">
<thead><tr>
<th class="matrix-monster-col" scope="col"><?= Locale::html('admin.drops.table_monster') ?></th>
<?php for($column=0;$column<4;$column++): $defaultCode=$defaultColumns[$column]??'';$selectedItem=$choices[$defaultCode]??null; ?>
<th scope="col" data-matrix-column="<?= $column ?>"><div class="matrix-item-head">
<img class="matrix-item-icon" data-compare-icon="<?= $column ?>" src="<?= ah($selectedItem['image']??ItemPresentation::image('items/pouch.svg')) ?>" alt="">
<label><span class="matrix-column-label" data-compare-name="<?= $column ?>" aria-hidden="true"><?= ah(Locale::text($selectedItem['name']??Locale::t('admin.drops.table_empty'))) ?></span>
<select data-compare-item="<?= $column ?>" aria-label="<?= Locale::html('admin.drops.matrix_item',['number'=>$column+1]) ?>">
<?php foreach($choiceGroups as $category=>$items): ?><optgroup label="<?= ah($category) ?>"><?php foreach($items as $code=>$item): ?><option value="<?= ah($code) ?>" data-image="<?= ah($item['image']) ?>" <?= (string)$defaultCode===(string)$code?'selected':'' ?>><?= ah(Locale::text($item['name'])) ?></option><?php endforeach ?></optgroup><?php endforeach ?>
<?php if(!$choices): ?><option value=""><?= Locale::html('admin.drops.table_empty') ?></option><?php endif ?>
</select></label></div></th>
<?php endfor ?>
<?php foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold','gems'=>'Kristalle'] as $resource=>$label): ?>
<th scope="col" data-matrix-resource><?= adminIcon($resource==='gems'?'items/gems.svg':'ui-resources/'.$resource.'.png') ?><span><?= ah(Locale::text($label)) ?></span></th>
<?php endforeach ?>
<th class="matrix-action-col" scope="col"><?= Locale::html('admin.drops.table_action') ?></th>
</tr></thead><tbody>
<?php foreach($sources as $entry):
    $overview=$sourceOverview[$entry['key']];$config=$overview['config'];$active=$requested!==''&&(string)$entry['key']===$key;
    $search=Locale::text($entry['name']).' '.Locale::text($entry['subtitle']).' '.$entry['key'];
    foreach($config['drops'] as $drop)$search.=' '.Locale::text($tableItems[$drop['item_code']]['name']??'').' '.$drop['item_code'];
    $entryEditUrl=APP_BASE.'/admin/rewards?type=monster&source='.rawurlencode((string)$entry['key']).$scopeQuery;
    $entryLabel=Locale::text($entry['name']).' · '.Locale::t('admin.drops.level',['level'=>$overview['level']]);
?>
<tr class="reward-source-row <?= $active?'is-selected':'' ?>" data-source-key="<?= ah($entry['key']) ?>" data-source-label="<?= ah($entryLabel) ?>" data-editor-url="<?= ah($entryEditUrl) ?>" data-source-level-value="<?= $overview['level'] ?>" data-source-custom="<?= $overview['custom']?'1':'0' ?>" data-source-items="<?= $overview['enabled'] ?>" data-source-name="<?= ah(mb_strtolower($search)) ?>" data-matrix-drops="<?= ah(json_encode($matrixRows[$entry['key']],JSON_THROW_ON_ERROR)) ?>">
<th class="matrix-monster-col" scope="row"><div class="monster-table-name"><?= adminIcon($entry['image']) ?><span><strong><?= ah(Locale::text($entry['name'])) ?></strong><small class="matrix-source-meta"><?= Locale::html('admin.drops.level',['level'=>$overview['level']]) ?> · <?= Locale::html($entry['definition']['type']==='rally'?'admin.drops.matrix_rally':'admin.drops.matrix_solo') ?></small>
<?php if($overview['custom']): ?><small class="matrix-source-state"><?= Locale::html('admin.drops.custom') ?></small><?php endif ?>
<?php if(!$entry['active']): ?><small class="matrix-source-state"><?= Locale::html('admin.drops.table_inactive') ?></small><?php endif ?>
</span></div></th>
<?php for($column=0;$column<4;$column++): $code=$defaultColumns[$column]??'';$drop=$matrixRows[$entry['key']][$code]??null;$linked=$code!==''&&($drop||$canEdit); ?>
<td data-matrix-column="<?= $column ?>" data-matrix-cell="<?= $column ?>" class="<?= !$drop?'is-missing':($drop['chance']===100.0||$drop['chance']===100?'is-guaranteed':($drop['chance']<=0?'is-off':'')) ?>">
<a class="matrix-drop-link" <?= $linked?'href="'.ah($entryEditUrl.'#drop-item-'.$code).'"':'' ?> aria-label="<?= Locale::html($drop?'admin.drops.edit_drop':'admin.drops.add_drop_for',['item'=>Locale::text($choices[$code]['name']??''),'source'=>$entryLabel]) ?>">
<strong class="matrix-quantity"><?= $drop?ah($tableNumber($drop['quantity'])).'×':'—' ?></strong><small class="matrix-chance"><?= $drop?ah($tableNumber($drop['chance'])).'%':Locale::html($linked?'admin.drops.add_drop':'admin.drops.matrix_no_drop') ?></small></a>
</td>
<?php endfor ?>
<?php foreach(['food','lumber','stone','gold'] as $resource): ?><td data-matrix-resource><strong class="matrix-quantity"><?= ah($tableNumber($config['resource_reward'][$resource]??0)) ?></strong></td><?php endforeach ?>
<td data-matrix-resource class="<?= $config['gems_drop']['chance']<=0?'is-off':'' ?>"><strong class="matrix-quantity"><?= ah($tableNumber($config['gems_drop']['amount'])) ?>×</strong><small class="matrix-chance"><?= ah($tableNumber($config['gems_drop']['chance']*100)) ?>%</small></td>
<td class="matrix-action-col"><a class="button secondary" href="<?= APP_BASE ?>/admin/rewards?type=monster&amp;source=<?= ah($entry['key'].$scopeQuery) ?>#reward-editor" aria-label="<?= Locale::html('admin.drops.table_edit_source',['name'=>Locale::text($entry['name']),'level'=>$overview['level']]) ?>"><span class="matrix-edit-label"><?= Locale::html('admin.drops.table_edit') ?></span><span class="matrix-edit-icon" aria-hidden="true">✎</span></a></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
