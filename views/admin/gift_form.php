<?php declare(strict_types=1); adminForm('gift',$selectedWorld,$giftPlayerId); ?>
<?php if(!$giftPlayerId): ?><input type="hidden" name="recipient_count" value="<?= $recipientCount ?>"><?php endif ?>
<label>Titel des Geschenks<input name="title" required minlength="3" maxlength="100" placeholder="Ein Dankeschön an dein Königreich"></label>
<label>Nachricht an den Spieler<textarea name="message" rows="2" maxlength="1000" placeholder="Deine Nachricht"></textarea></label>
<div class="resource-fields"><?php foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold','gems'=>'Edelsteine'] as $key=>$name): ?><div><?= adminIcon($key==='gems'?'items/gems.svg':'ui-resources/'.$key.'.png') ?><?php adminNumber($name,$key,0,0,$key==='gems'?1000000:1000000000); ?></div><?php endforeach ?></div>
<div class="fields"><div><span class="field-label">Gegenstand (optional)</span><?php adminItemPicker('item_code',0,false,true); ?></div><?php adminNumber('Anzahl Gegenstände','quantity',0,0,100000); ?></div>
<div class="hint">Rohstoffe gehen an die ausgewählte Stadt. Edelsteine und Gegenstände sind in allen Welten nutzbar. Das Geschenk wird sofort zugestellt.</div>
<?php adminSubmit($giftPlayerId?'Geschenk zustellen':'An '.an($recipientCount).' Spieler zustellen'); ?>
