<?php
declare(strict_types=1);
$list = \Conquer\Admin\AlphaKeyAdmin::listing($db, $_GET);
$labels = ['active'=>'Verfügbar', 'used'=>'Aufgebraucht', 'expired'=>'Abgelaufen', 'revoked'=>'Gesperrt'];
$draft = $_SESSION['admin_alpha_draft'] ?? [];
$issued = $_SESSION['admin_alpha_issued'] ?? null;
unset($_SESSION['admin_alpha_issued']);
$showIssued = $canEdit && is_array($issued) && ($issued['admin_id'] ?? 0) === (int)$adminSession['id'] && ($issued['expires'] ?? 0) >= time();
$pageUrl = static fn(int $page): string => APP_BASE.'/admin/alpha-keys?'.http_build_query(['q'=>$list['search'],'status'=>$list['status'],'page'=>$page]);
?>
<script src="<?= APP_BASE ?>/assets/js/admin-alpha-keys.js?v=<?= filemtime(ROOT_DIR.'/assets/js/admin-alpha-keys.js') ?>" defer></script>
<?php if($showIssued): ?>
<section class="card alpha-issued" aria-labelledby="alpha-issued-title">
    <h2 id="alpha-issued-title">Deine neuen Alpha-Keys</h2>
    <p>Kopiere die Keys jetzt. Nach dem Verlassen oder Neuladen dieser Seite können sie nicht erneut angezeigt werden.</p>
    <label for="alpha-issued-keys">Keys zum Weitergeben · ein Key je Zeile</label>
    <textarea id="alpha-issued-keys" rows="<?= min(8,max(2,count($issued['keys']))) ?>" readonly spellcheck="false" autocomplete="off" translate="no"><?= ah(implode("\n",array_column($issued['keys'],'key'))) ?></textarea>
    <div class="alpha-copy-actions"><button type="button" data-copy-alpha>Keys kopieren</button><span data-alpha-copy-status role="status" aria-live="polite"></span></div>
    <p class="subtle">In der Übersicht findest du sie unter <?= ah(implode(', ',array_map(static fn(array $key): string => '#'.$key['id'],$issued['keys']))) ?>.</p>
</section>
<?php endif ?>
<section class="card alpha-create" aria-labelledby="alpha-create-title">
    <h2 id="alpha-create-title">Alpha-Keys erstellen</h2>
    <p>Ein Key erlaubt neue Registrierungen. Für persönliche Einladungen wähle eine Registrierung je Key; für eine Gruppe kannst du mehr erlauben.</p>
    <?php adminForm('alpha-key-create',0); ?>
    <div class="fields">
        <label class="span-two">Bezeichnung<input name="label" value="<?= ah($draft['label']??'') ?>" required maxlength="120" placeholder="z. B. Erste Testgruppe"></label>
        <?php adminNumber('Anzahl Keys','quantity',$draft['quantity']??1,1,50); adminNumber('Registrierungen je Key','max_uses',$draft['max_uses']??1,1,65535); ?>
        <label class="span-two">Ablaufdatum (UTC, optional)<input type="datetime-local" name="expires_at" value="<?= ah($draft['expires_at']??'') ?>" aria-describedby="alpha-expiry-hint"></label>
    </div>
    <p class="subtle" id="alpha-expiry-hint">Leer lassen für unbegrenzte Gültigkeit. Du kannst bis zu 50 Keys auf einmal erstellen und später einzeln sperren.</p>
    <label>Begründung für das Änderungsprotokoll<input name="reason" required minlength="3" maxlength="500" value="<?= ah($draft['reason']??'Einladungen zur geschlossenen Alpha') ?>"></label>
    <button type="submit">Alpha-Keys erstellen</button></fieldset></form>
    <div class="hint">Alpha-Keys gelten für die Registrierung, unabhängig von der Welt. Bestehende Konten benötigen beim Anmelden keinen Key.</div>
</section>
<section class="card alpha-overview" aria-labelledby="alpha-overview-title">
    <h2 id="alpha-overview-title">Einladungen im Überblick</h2>
    <form method="get" action="<?= APP_BASE ?>/admin/alpha-keys" class="toolbar">
        <label>Bezeichnung oder Key-ID<input type="search" name="q" maxlength="120" value="<?= ah($list['search']) ?>" placeholder="Einladungen suchen"></label>
        <label>Status<select name="status"><option value="">Alle</option><?php foreach($labels as $value=>$label): ?><option value="<?= $value ?>" <?= $list['status']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
        <button type="submit" class="secondary">Filtern</button>
    </form>
    <p class="subtle"><?= an($list['total']) ?> <?= $list['total']===1?'Key':'Keys' ?> gefunden · Die vollständigen Keys werden nur direkt nach dem Erstellen angezeigt.</p>
    <div class="alpha-key-list">
    <?php foreach($list['rows'] as $key): ?>
        <article class="alpha-key-row" data-alpha-key-id="<?= (int)$key['id'] ?>">
            <header><h3><span class="subtle">#<?= (int)$key['id'] ?></span> <span translate="no"><?= ah($key['label']) ?></span></h3><span class="alpha-key-status is-<?= ah($key['status']) ?>"><?= $labels[$key['status']] ?></span></header>
            <dl class="alpha-key-facts">
                <div><dt>Registrierungen</dt><dd><?= an($key['uses_count']) ?> / <?= an($key['max_uses']) ?></dd></div>
                <div><dt>Erstellt (UTC)</dt><dd><?= ah($key['created_at']) ?></dd></div>
                <div><dt>Gültig bis (UTC)</dt><dd><?= ah($key['expires_at']??'Unbegrenzt') ?></dd></div>
                <div><dt>Zuletzt genutzt (UTC)</dt><dd><?= ah($key['last_used_at']??'Noch nicht genutzt') ?></dd></div>
            </dl>
            <?php if($key['revoked_at']!==null): ?>
                <p class="subtle">Gesperrt am <?= ah($key['revoked_at']) ?> UTC. Bestehende Konten bleiben erhalten.</p>
            <?php elseif($canEdit && $key['status']==='active'): ?>
                <details class="alpha-key-revoke"><summary>Key sperren</summary>
                    <p>„<?= ah($key['label']) ?>“ (#<?= (int)$key['id'] ?>) für weitere Registrierungen sperren. Bereits registrierte Konten bleiben bestehen.</p>
                    <?php adminForm('alpha-key-revoke',0); ?>
                    <input type="hidden" name="key_id" value="<?= (int)$key['id'] ?>">
                    <label>Begründung<input name="reason" required minlength="3" maxlength="500" placeholder="z. B. Einladung zurückgezogen"></label>
                    <button type="submit" class="danger">Key jetzt sperren</button></fieldset></form>
                </details>
            <?php endif ?>
        </article>
    <?php endforeach ?>
    </div>
    <?php if(!$list['rows']): ?><p class="empty">Keine Alpha-Keys gefunden.</p><?php endif ?>
    <?php if($list['pages']>1): ?><nav class="pagination" aria-label="Seiten der Alpha-Keys">
        <?php if($list['page']>1): ?><a href="<?= ah($pageUrl($list['page']-1)) ?>">← Zurück</a><?php endif ?>
        <span>Seite <?= $list['page'] ?> / <?= $list['pages'] ?></span>
        <?php if($list['page']<$list['pages']): ?><a href="<?= ah($pageUrl($list['page']+1)) ?>">Weiter →</a><?php endif ?>
    </nav><?php endif ?>
</section>
