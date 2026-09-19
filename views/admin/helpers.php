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
    echo '<div class="item-select" data-fragments="'.($fragments?'1':'0').'" data-optional="'.($optional?'1':'0').'"><input type="hidden" name="'.ah($name).'" value="'.ah($code).'"><button class="item-choice secondary" type="button" data-item-picker aria-label="'.ah($item?'Gegenstand ändern: '.$item['name']:'Gegenstand auswählen').'"><img src="'.ah($item['image']??\Conquer\Admin\ItemPresentation::image('items/pouch.svg')).'" alt=""><span><strong>'.ah($item['name']??($optional?'Kein Gegenstand':'Gegenstand auswählen')).'</strong><small>'.ah($item['category_name']??'Katalog öffnen').' · Auswählen</small></span><span aria-hidden="true">⌄</span></button></div>';
}
function adminDropRow(string $type,int|string $index,array $row): void {
    $prefix='config[rows]['.$index.']';$weighted=in_array($type,['chest','dungeon'],true);
    echo '<div class="drop-row"><div><span class="field-label">Gegenstand</span>';
    adminItemPicker($prefix.'[target]',$row['target']??'', $type==='chest');echo '</div>';
    if($type!=='dungeon')adminNumber('Anzahl',$prefix.'[quantity]',$row['quantity']??1,1,100000);
    adminNumber($weighted?'Gewichtung':'Chance (%)',$prefix.($weighted?'[weight]':'[chance]'),$row[$weighted?'weight':'chance']??($weighted?1:100),0,$weighted?1000000:100,$weighted?'1':'.01');
    echo '<div class="drop-row-end"><span class="drop-probability" aria-live="polite"></span><button type="button" class="secondary remove-drop" aria-label="Beuteeintrag entfernen">✕</button></div></div>';
}
