<?php declare(strict_types=1);
$descriptions=[
    'layout_editor'=>'Chat, Navigation und Ressourcen für Handy und Desktop anpassen.',
    'dashboard'=>'Die wichtigsten Bereiche deines Königreichs, an einem Ort.',
    'analytics'=>'Aktivität, Wirtschaft und Kämpfe mit belastbaren Zeiträumen auswerten.',
    'rewards'=>'Lege fest, welche Belohnungen deine Spieler erhalten.',
    'items'=>'Alle Gegenstände mit Bild, Seltenheit und Beschreibung.',
    'players'=>'Spieler finden, Fortschritt verwalten und Geschenke zustellen.',
    'alpha_keys'=>'Einladungen erstellen und den Zugang zur geschlossenen Alpha verwalten.',
    'alpha_waitlist'=>'E-Mail-Adressen der Alpha-Interessenten prüfen und Einladungen verwalten.',
    'world'=>'Population, Spieltempo und Ereignisse deiner Welt steuern.',
    'world_create'=>'Eine neue Welt mit allen Regeln in einem Schritt vorbereiten.',
    'lands'=>'Landstufen, Beiträge und die Freigabe der drei Kartenbereiche einstellen.',
    'alliances'=>'Allianzen und ihre Mitglieder im Blick behalten.',
    'chat'=>'Unterhaltungen in deiner Welt nachvollziehen.',
    'bug_reports'=>'Meldungen deiner Spieler prüfen, priorisieren und abschließen.',
    'audit'=>'Nachsehen, wer welche Einstellung geändert hat.'
];
$globalPage=in_array($activePage,['layout_editor','items','alpha_keys','alpha_waitlist','world_create'],true)||($activePage==='rewards'&&($_GET['scope']??'global')!=='world');
$areas=[
 'overview'=>['dashboard','analytics'], 'players'=>['players','alliances'],
 'worlds'=>['world','world_create','lands'], 'drops'=>['rewards'], 'catalog'=>['items'],
 'access'=>array_merge(['alpha_keys'], $canEdit?['alpha_waitlist']:[], ['bug_reports','chat']),
 'system'=>['audit','layout_editor']
];
$pages=['dashboard'=>['','Übersicht'],'analytics'=>['/analytics','Statistiken'],'players'=>['/players','Spieler & Geschenke'],'alliances'=>['/alliances','Allianzen'],'world'=>['/world','Welten & Spawns'],'world_create'=>['/world-create','Welt erstellen'],'lands'=>['/lands','Länder & Entwicklung'],'rewards'=>['/rewards?type=farm','Beute & Drops'],'items'=>['/items','Gegenstände'],'alpha_keys'=>['/alpha-keys','Alpha-Keys'],'alpha_waitlist'=>['/alpha-waitlist','Alpha-E-Mails'],'bug_reports'=>['/bug-reports','Bugmeldungen'],'chat'=>['/chat','Chatprotokoll'],'audit'=>['/audit','Änderungsprotokoll'],'layout_editor'=>['/layout','Layout-Editor']];
$currentArea='overview';foreach($areas as $area=>$members)if(in_array($activePage,$members,true))$currentArea=$area;
$adminUrl=static fn(string $path):string=>APP_BASE.'/admin'.$path.(str_contains($path,'?')?'&':'?').'world_id='.$selectedWorld;
if($activePage==='dashboard'){$pageTitle=\Conquer\Game\Locale::t('admin.modern.nav_overview');$descriptions['dashboard']=\Conquer\Game\Locale::t('admin.modern.overview_description');}
if($activePage==='rewards'){$pageTitle=\Conquer\Game\Locale::t('admin.modern.nav_drops');$descriptions['rewards']=\Conquer\Game\Locale::t('admin.modern.drops_description');}
$newBugCount=(int)$db->query("SELECT COUNT(*) FROM bug_reports WHERE world_id=? AND status='new'",[$selectedWorld])->fetchColumn();
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Conquer\Game\Locale::current(),ENT_QUOTES) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= ah($pageTitle) ?> · Union of Kingdoms Verwaltung</title>
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/admin-backoffice.css?v=<?= filemtime(ROOT_DIR.'/assets/css/admin-backoffice.css') ?>">
<?php if($activePage==='layout_editor'): ?><link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/layout-editor.css?v=<?= filemtime(ROOT_DIR.'/assets/css/layout-editor.css') ?>"><?php endif ?>
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR.'/assets/css/village-theme.css') ?>">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/admin-modern.css?v=<?= filemtime(ROOT_DIR.'/assets/css/admin-modern.css') ?>">
<?php require ROOT_DIR.'/views/partials/localization-head.php'; ?>
<?php if($activePage==='rewards'): ?><script src="<?= APP_BASE ?>/assets/js/relic-presentation.js?v=<?= filemtime(ROOT_DIR.'/assets/js/relic-presentation.js') ?>" defer></script><?php endif ?>
<script src="<?= APP_BASE ?>/assets/js/admin-modern.js?v=<?= filemtime(ROOT_DIR.'/assets/js/admin-modern.js') ?>" defer></script>
<script src="<?= APP_BASE ?>/assets/js/admin-backoffice.js?v=<?= filemtime(ROOT_DIR.'/assets/js/admin-backoffice.js') ?>" defer></script>
</head><body class="admin-village admin-modern" data-i18n-scope>
<a class="skip-link" href="#main">Zum Inhalt</a>
<aside class="sidebar">
    <a class="brand" href="<?= ah($adminUrl('')) ?>"><span class="admin-brand-mark" aria-hidden="true">U</span><span>Union of Kingdoms<small><?= \Conquer\Game\Locale::html('admin.modern.administration') ?></small></span></a>
    <button type="button" class="secondary mobile-menu" aria-expanded="false" aria-controls="admin-nav">☰ <?= \Conquer\Game\Locale::html('admin.modern.workspaces') ?></button>
    <div class="admin-locale" data-locale-controls data-locale-compact="true" data-locale-install="false"></div>
    <nav id="admin-nav" aria-label="<?= \Conquer\Game\Locale::html('admin.modern.administration') ?>">
    <div class="nav-caption"><?= \Conquer\Game\Locale::html('admin.modern.workspaces') ?></div>
    <?php foreach($areas as $area=>$members): $first=$members[0]; ?><a <?= $currentArea===$area?'class="active" aria-current="page"':'' ?> href="<?= ah($adminUrl($pages[$first][0])) ?>" data-admin-area="<?= $area ?>"><?= adminUiIcon($area) ?><span><?= \Conquer\Game\Locale::html('admin.modern.nav_'.$area) ?></span><?php if($area==='access'&&$newBugCount): ?><small class="admin-nav-count"><?= $newBugCount ?></small><?php endif ?></a><?php endforeach ?>
    </nav>
    <div class="sidebar-bottom"><strong data-user-content><?= ah($adminSession['username']) ?></strong><small><?= ah(\Conquer\Game\Locale::text($canEdit?'Administrator · voller Zugriff':'Moderator · Lesezugriff')) ?></small><form method="post" action="<?= APP_BASE ?>/admin/logout"><input type="hidden" name="csrf_token" value="<?= ah($csrf) ?>"><button type="submit" class="secondary">Abmelden →</button></form></div>
</aside>
<div class="shell">
<header class="topbar"><span class="admin-breadcrumb"><?= \Conquer\Game\Locale::html('admin.modern.administration') ?> / <?= \Conquer\Game\Locale::html('admin.modern.nav_'.$currentArea) ?></span>
<?php if($activePage==='rewards'): ?><form method="get" class="world-picker admin-context-picker" data-modern-context><input type="hidden" name="type" value="<?= ah($type) ?>"><?php if($requested!==''): ?><input type="hidden" name="source" value="<?= ah($key) ?>"><?php endif ?><label><?= \Conquer\Game\Locale::html('admin.modern.context') ?><select name="scope"><option value="global" <?= $rewardScope==='global'?'selected':'' ?>><?= \Conquer\Game\Locale::html('admin.modern.all_worlds') ?></option><option value="world" <?= $rewardScope==='world'?'selected':'' ?>><?= \Conquer\Game\Locale::html('admin.modern.world_rule') ?></option></select></label><label data-modern-world><?= \Conquer\Game\Locale::html('admin.modern.nav_worlds') ?><select name="world_id"><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$w['id']===$selectedWorld?'selected':'' ?> data-user-content><?= ah($w['name']) ?></option><?php endforeach ?></select></label><button class="secondary" type="submit" aria-label="<?= \Conquer\Game\Locale::html('admin.switch') ?>">↗</button></form>
<?php elseif(!$globalPage): ?><form method="get" class="world-picker"><label for="world-picker"><?= \Conquer\Game\Locale::html('admin.modern.nav_worlds') ?></label><select id="world-picker" name="world_id" aria-label="<?= \Conquer\Game\Locale::html('admin.modern.nav_worlds') ?>"><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$w['id']===$selectedWorld?'selected':'' ?>><?= ah($w['name']) ?></option><?php endforeach ?></select><button class="secondary" type="submit">Wechseln</button></form><?php else: ?><span class="pill"><?= \Conquer\Game\Locale::html('admin.modern.all_worlds') ?></span><?php endif ?>
</header>
<main id="main">
<div class="page-heading"><div><h1><?= ah($pageTitle) ?></h1><p><?= ah($descriptions[$activePage]??'Dein Spielerprofil und die zugehörige Stadt verwalten.') ?></p></div><a class="button secondary" href="<?= APP_BASE ?>/city#city"><?= \Conquer\Game\Locale::html('admin.modern.open_game') ?> ↗</a></div>
<?php if(count($areas[$currentArea])>1): ?><nav class="admin-section-nav" aria-label="<?= \Conquer\Game\Locale::html('admin.modern.nav_'.$currentArea) ?>"><?php foreach($areas[$currentArea] as $page): ?><a href="<?= ah($adminUrl($pages[$page][0])) ?>" <?= $page===$activePage?'aria-current="page"':'' ?>><?= ah(\Conquer\Game\Locale::text($pages[$page][1])) ?></a><?php endforeach ?></nav><?php endif ?>
<?php if(isset($_SESSION['admin_flash'])): ?><div class="notice <?= ah($_SESSION['admin_flash_kind']??'success') ?>" role="<?= ($_SESSION['admin_flash_kind']??'')==='error'?'alert':'status' ?>"><?= ah($_SESSION['admin_flash']) ?></div><?php unset($_SESSION['admin_flash'],$_SESSION['admin_flash_kind']);endif ?>
<?php if(!$canEdit): ?><div class="notice">Du kannst alle Einstellungen ansehen. Zum Speichern ist ein Administratorkonto erforderlich.</div><?php endif ?>
<noscript><div class="notice">Bitte aktiviere JavaScript für die Bildauswahl und das Hinzufügen von Beuteeinträgen.</div></noscript>
<?= $content ?>
</main>
<footer><span>Union of Kingdoms · Verwaltung</span><span><?= \Conquer\Game\Locale::html('admin.modern.footer') ?></span></footer>
</div>
<?php if($usesItemPicker): ?><dialog id="item-picker-dialog" aria-labelledby="item-picker-title">
    <div class="picker-header"><div><h2 id="item-picker-title">Gegenstand auswählen</h2><p>Suche nach Name oder Gegenstandsnummer.</p></div><button type="button" class="secondary" data-picker-close aria-label="Auswahl schließen">✕</button></div>
    <div class="picker-filters"><label>Suche<input type="search" id="item-picker-search" placeholder="z. B. Nahrung, Beschleuniger …"></label><label>Kategorie<select id="item-picker-category"><option value="">Alle Kategorien</option><?php foreach(\Conquer\Admin\ItemPresentation::CATEGORIES+['relics'=>\Conquer\Game\Locale::t('admin.drops.relic_title'),'specific_fragments'=>\Conquer\Game\Locale::t('admin.drops.fragment_specific_title'),'fragments'=>'Zufällige Reliktfragmente'] as $key=>$label): ?><option value="<?= ah($key) ?>"><?= ah($label) ?></option><?php endforeach ?></select></label></div>
    <p class="picker-count" aria-live="polite"></p><div class="picker-results"></div>
</dialog>
<script type="application/json" id="admin-item-catalog"><?= json_encode(\Conquer\Admin\ItemPresentation::catalog(true),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php endif ?>
</body></html>
