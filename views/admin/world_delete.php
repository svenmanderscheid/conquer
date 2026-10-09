<?php declare(strict_types=1);
use Conquer\Game\Locale;
$deleteBlocked=count($worlds)<=1?'last':($world['status']!=='closed'?'close_first':null);
$worldPlayers=(int)$db->query('SELECT COUNT(*) FROM cities WHERE world_id=?',[$selectedWorld])->fetchColumn();
$worldAlliances=(int)$db->query('SELECT COUNT(*) FROM alliances WHERE world_id=?',[$selectedWorld])->fetchColumn();
?>
<section class="card world-delete-card" id="world-delete" aria-labelledby="world-delete-title">
    <h2 id="world-delete-title" data-i18n="admin.world_delete.title"><?= Locale::html('admin.world_delete.title') ?></h2>
    <p data-i18n="admin.world_delete.summary" data-i18n-params="<?= ah(json_encode(['name'=>$world['name'],'players'=>$worldPlayers,'alliances'=>$worldAlliances],JSON_THROW_ON_ERROR)) ?>"><?= Locale::html('admin.world_delete.summary',['name'=>$world['name'],'players'=>$worldPlayers,'alliances'=>$worldAlliances]) ?></p>
    <p class="hint world-delete-warning"><?= Locale::html('admin.world_delete.warning') ?></p>
    <p class="subtle"><?= Locale::html('admin.world_delete.accounts_preserved') ?></p>
    <?php if($deleteBlocked): ?><p class="hint"><?= Locale::html('admin.world_delete.'.$deleteBlocked) ?></p><?php endif ?>
    <?php if($canEdit): ?>
    <?php adminForm('world-delete',$selectedWorld); ?>
    <label><?= Locale::html('admin.world_delete.name_label') ?><input name="confirm_name" required maxlength="50" autocomplete="off" spellcheck="false" aria-describedby="world-delete-name-help"></label>
    <p class="subtle" id="world-delete-name-help" data-i18n="admin.world_delete.name_help" data-i18n-params="<?= ah(json_encode(['name'=>$world['name']],JSON_THROW_ON_ERROR)) ?>"><?= Locale::html('admin.world_delete.name_help',['name'=>$world['name']]) ?></p>
    <label class="check"><input type="checkbox" name="confirm_delete" value="1" required> <?= Locale::html('admin.world_delete.checkbox') ?></label>
    <label><?= Locale::html('admin.world_delete.reason_label') ?><input name="reason" required minlength="3" maxlength="500"></label>
    <button type="submit" class="danger" <?= $deleteBlocked?'disabled':'' ?>><?= Locale::html('admin.world_delete.button') ?></button>
    </fieldset></form>
    <?php endif ?>
</section>
