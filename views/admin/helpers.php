<?php
declare(strict_types=1);
function ah(mixed $value): string {return htmlspecialchars(is_scalar($value)?(string)$value:'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function an(mixed $value): string {return number_format((float)$value,0,',','.');}
function adminForm(string $action,int $worldId,int $playerId=0): void {
    echo '<form method="post" action="'.ah(APP_BASE.'/admin/action/'.$action).'" class="admin-form"><input type="hidden" name="csrf_token" value="'.ah($_SESSION['admin_csrf']).'"><input type="hidden" name="operation_id" value="'.bin2hex(random_bytes(16)).'"><input type="hidden" name="world_id" value="'.$worldId.'"><input type="hidden" name="player_id" value="'.$playerId.'"><fieldset'.(($_SESSION['admin']['role']??'')==='superadmin'?'':' disabled').'>';
}
function adminSubmit(string $label='Änderung speichern'): void {
    echo '<label>Begründung für das Änderungsprotokoll<input name="reason" required minlength="3" maxlength="500" placeholder="z. B. Supportkorrektur oder Eventbelohnung"></label><button type="submit">'.ah($label).'</button></fieldset></form>';
}
function adminNumber(string $label,string $name,mixed $value,int|float $min=0,int|float $max=1000000000000,string $step='1'): void {
    echo '<label>'.ah($label).'<input type="number" name="'.ah($name).'" value="'.ah($value).'" min="'.$min.'" max="'.$max.'" step="'.ah($step).'" required></label>';
}
function adminIcon(string $path,string $class='admin-icon'): string {
    return '<img class="'.ah($class).'" src="'.ah(\Conquer\Admin\ItemPresentation::image($path)).'" alt="" loading="lazy">';
}
function adminItemPicker(string $name,mixed $code,bool $fragments=false,bool $optional=false): void {
    if(!is_scalar($code))$code='';
    static $catalog=null;$catalog??=array_column(\Conquer\Admin\ItemPresentation::catalog(true),null,'code');
    $item=$catalog[(string)$code]??null;
    echo '<div class="item-select" data-fragments="'.($fragments?'1':'0').'" data-optional="'.($optional?'1':'0').'"><input type="hidden" name="'.ah($name).'" value="'.ah($code).'"><button class="item-choice secondary" type="button" data-item-picker aria-label="'.ah($item?'Gegenstand ändern: '.$item['name']:'Gegenstand auswählen').'"><img src="'.ah($item['image']??\Conquer\Admin\ItemPresentation::image('items/pouch.svg')).'" alt=""><span><strong>'.ah($item['name']??($optional?'Kein Gegenstand':'Gegenstand auswählen')).'</strong><small>'.ah(\Conquer\Game\Locale::text($item['category_name']??'Katalog öffnen')).' · '.ah(\Conquer\Game\Locale::text('Auswählen')).'</small></span><span aria-hidden="true">⌄</span></button></div>';
}
function adminDropRow(string $type,int|string $index,array $row): void {
    $prefix='config[rows]['.$index.']';$weighted=in_array($type,['chest','dungeon'],true);
    echo '<div class="drop-row"><div><span class="field-label">'.($type==='chest'?\Conquer\Game\Locale::html('admin.drops.chest_reward'):'Gegenstand').'</span>';
    adminItemPicker($prefix.'[target]',$row['target']??'', $type==='chest');echo '</div>';
    if($type!=='dungeon')adminNumber('Anzahl',$prefix.'[quantity]',$row['quantity']??1,1,100000);
    adminNumber($weighted?'Gewichtung':'Chance (%)',$prefix.($weighted?'[weight]':'[chance]'),$row[$weighted?'weight':'chance']??($weighted?1:100),0,$weighted?1000000:100,$weighted?'1':'.01');
    echo '<div class="drop-row-end"><span class="drop-probability" aria-live="polite"></span><button type="button" class="secondary remove-drop" aria-label="Beuteeintrag entfernen">✕</button></div></div>';
}
function adminUiIcon(string $name):string {
    $paths=['overview'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'players'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5"/>',
        'worlds'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18M12 3c-5 5-5 13 0 18"/>',
        'drops'=>'<path d="m3 7 9-4 9 4-9 4-9-4ZM3 7v10l9 4 9-4V7M12 11v10M7 5l10 4"/>',
        'catalog'=>'<rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="18" rx="1"/><path d="M6 7h1M17 7h1"/>',
        'access'=>'<circle cx="8" cy="8" r="5"/><path d="m12 12 9 9M17 17l3-3M14 14l3-3"/>',
        'system'=>'<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="10" cy="18" r="2"/>'];
    return '<svg class="admin-ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['overview']).'</svg>';
}

function adminFragmentRow(int|string $index,array $row): void {
    $prefix='config[fragment_rows]['.$index.']';
    $target=is_string($row['target']??null)?$row['target']:'';
    echo '<div class="fragment-row"><label>'.\Conquer\Game\Locale::html('admin.drops.fragment_relic').'<select name="'.ah($prefix.'[target]').'" required><option value="">'.\Conquer\Game\Locale::html('admin.drops.fragment_select').'</option>';
    echo '<optgroup label="'.\Conquer\Game\Locale::html('admin.drops.fragment_random_group').'">';
    foreach(['normal','rare','epic','legendary'] as $grade){
        $value='fragment:'.$grade;
        echo '<option value="'.$value.'"'.($target===$value?' selected':'').'>'.\Conquer\Game\Locale::html('admin.drops.fragment_random_'.$grade).'</option>';
    }
    echo '</optgroup><optgroup label="'.\Conquer\Game\Locale::html('admin.drops.fragment_specific_group').'">';
    foreach(\Conquer\Game\Treasure\TreasureData::all() as $code=>$def){
        if(!empty($def['legacy_only']))continue;
        $value='treasure:'.$code;
        echo '<option value="'.$value.'"'.($target===$value?' selected':'').'>'.ah(\Conquer\Game\Locale::text($def['name_de']??$def['name'])).'</option>';
    }
    echo '</optgroup></select></label>';
    adminNumber(\Conquer\Game\Locale::t('admin.drops.fragment_quantity'),$prefix.'[quantity]',$row['quantity']??1,1,100000);
    adminNumber(\Conquer\Game\Locale::t('admin.drops.fragment_chance'),$prefix.'[chance]',$row['chance']??0,0,100,'.0001');
    echo '<div class="drop-row-end"><button type="button" class="secondary" data-remove-fragment aria-label="'.\Conquer\Game\Locale::html('admin.drops.fragment_remove').'">✕</button></div><small class="fragment-outcome" aria-live="polite"></small></div>';
}

function adminRelicRow(int|string $index,array $row): void {
    $prefix='config[relic_rows]['.$index.']';
    $target=is_string($row['target']??null)?$row['target']:'';
    echo '<div class="relic-row"><label>'.\Conquer\Game\Locale::html('admin.drops.fragment_relic').'<select name="'.ah($prefix.'[target]').'" required><option value="">'.\Conquer\Game\Locale::html('admin.drops.relic_select').'</option>';
    foreach(\Conquer\Game\Treasure\TreasureData::all() as $code=>$def){
        if(!empty($def['legacy_only']))continue;
        $value='relic:'.$code;
        echo '<option value="'.$value.'"'.($target===$value?' selected':'').'>'.ah(\Conquer\Game\Locale::text($def['name_de']??$def['name'])).'</option>';
    }
    echo '</select></label>';
    adminNumber(\Conquer\Game\Locale::t('admin.drops.relic_quantity'),$prefix.'[quantity]',$row['quantity']??1,1,100000);
    adminNumber(\Conquer\Game\Locale::t('admin.drops.relic_chance'),$prefix.'[chance]',$row['chance']??0,0,100,'.0001');
    echo '<div class="drop-row-end"><button type="button" class="secondary" data-remove-relic aria-label="'.\Conquer\Game\Locale::html('admin.drops.relic_remove').'">✕</button></div><small class="relic-outcome" aria-live="polite"></small></div>';
}
