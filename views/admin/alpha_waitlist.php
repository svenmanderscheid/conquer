<?php
declare(strict_types=1);
$list = \Conquer\Admin\AlphaWaitlistAdmin::listing($db, $_GET);
$pageUrl = static fn(int $page): string => APP_BASE.'/admin/alpha-waitlist?'.http_build_query(['q'=>$list['search'],'status'=>$list['status'],'page'=>$page]);
?>
<section class="card" aria-labelledby="waitlist-title">
    <div class="split"><div><h2 id="waitlist-title">Interessenten für die Alpha</h2><p>Kontaktdaten aus dem Anmeldeformular der öffentlichen Startseite.</p></div><a class="button secondary" href="<?= APP_BASE ?>/admin/alpha-waitlist?export=csv">CSV exportieren</a></div>
    <form method="get" action="<?= APP_BASE ?>/admin/alpha-waitlist" class="toolbar">
        <label>Name oder E-Mail<input type="search" name="q" maxlength="120" value="<?= ah($list['search']) ?>" placeholder="Alpha-Anmeldungen suchen"></label>
        <label>Status<select name="status"><option value="">Alle</option><option value="waiting" <?= $list['status']==='waiting'?'selected':'' ?>>Wartet</option><option value="invited" <?= $list['status']==='invited'?'selected':'' ?>>Eingeladen</option></select></label>
        <button type="submit" class="secondary">Filtern</button>
    </form>
    <p class="subtle"><?= an($list['total']) ?> <?= $list['total']===1?'Anmeldung':'Anmeldungen' ?> gefunden.</p>
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>E-Mail</th><th>Sprache</th><th>Eingetragen (UTC)</th><th>Status</th><th>Aktion</th></tr></thead><tbody>
    <?php foreach($list['rows'] as $entry): ?><tr>
        <td><?= ah($entry['first_name'].' '.$entry['last_name']) ?></td>
        <td><a href="mailto:<?= ah($entry['email']) ?>"><?= ah($entry['email']) ?></a></td>
        <td><?= ah(strtoupper($entry['locale'])) ?></td><td><?= ah($entry['created_at']) ?></td>
        <td><span class="pill"><?= $entry['invited_at']===null?'Wartet':'Eingeladen' ?></span></td>
        <td><?php adminForm('alpha-waitlist-update',0); ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><input type="hidden" name="reason" value="Alpha-Warteliste verwaltet"><select name="status" aria-label="Status für <?= ah($entry['email']) ?>"><option value="waiting" <?= $entry['invited_at']===null?'selected':'' ?>>Wartet</option><option value="invited" <?= $entry['invited_at']!==null?'selected':'' ?>>Eingeladen</option><option value="delete">Löschen</option></select><button type="submit" class="secondary">Speichern</button></fieldset></form></td>
    </tr><?php endforeach ?>
    </tbody></table></div>
    <?php if(!$list['rows']): ?><p class="empty">Noch keine Alpha-Anmeldungen vorhanden.</p><?php endif ?>
    <?php if($list['pages']>1): ?><nav class="pagination" aria-label="Seiten der Alpha-Warteliste"><?php if($list['page']>1): ?><a href="<?= ah($pageUrl($list['page']-1)) ?>">← Zurück</a><?php endif ?><span>Seite <?= $list['page'] ?> / <?= $list['pages'] ?></span><?php if($list['page']<$list['pages']): ?><a href="<?= ah($pageUrl($list['page']+1)) ?>">Weiter →</a><?php endif ?></nav><?php endif ?>
</section>
