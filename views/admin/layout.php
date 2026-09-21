<?php declare(strict_types=1);
$descriptions=[
    'dashboard'=>'Die wichtigsten Bereiche deines Königreichs, an einem Ort.',
    'analytics'=>'Aktivität, Wirtschaft und Kämpfe mit belastbaren Zeiträumen auswerten.',
    'rewards'=>'Lege fest, welche Belohnungen deine Spieler erhalten.',
    'items'=>'Alle Gegenstände mit Bild, Seltenheit und Beschreibung.',
    'players'=>'Spieler finden, Fortschritt verwalten und Geschenke zustellen.',
    'alpha_keys'=>'Einladungen erstellen und den Zugang zur geschlossenen Alpha verwalten.',
    'world'=>'Population, Spieltempo und Ereignisse deiner Welt steuern.',
    'world_create'=>'Eine neue Welt mit allen Regeln in einem Schritt vorbereiten.',
    'lands'=>'Landstufen, Beiträge und die Freigabe der drei Kartenbereiche einstellen.',
    'alliances'=>'Allianzen und ihre Mitglieder im Blick behalten.',
    'chat'=>'Unterhaltungen in deiner Welt nachvollziehen.',
    'bug_reports'=>'Meldungen deiner Spieler prüfen, priorisieren und abschließen.',
    'audit'=>'Nachsehen, wer welche Einstellung geändert hat.'
];
$globalPage=in_array($activePage,['items','alpha_keys','world_create'],true)||($activePage==='rewards'&&($_GET['scope']??'global')!=='world');
$newBugCount=(int)$db->query("SELECT COUNT(*) FROM bug_reports WHERE world_id=? AND status='new'",[$selectedWorld])->fetchColumn();
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Conquer\Game\Locale::current(),ENT_QUOTES) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= ah($pageTitle) ?> · Conquer Verwaltung</title>
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/admin-backoffice.css?v=<?= filemtime(ROOT_DIR.'/assets/css/admin-backoffice.css') ?>">
<link rel="icon" href="<?= APP_BASE ?>/assets/icons/conquer.svg">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR.'/assets/css/village-theme.css') ?>">
<?php require ROOT_DIR.'/views/partials/localization-head.php'; ?>
<script src="<?= APP_BASE ?>/assets/js/admin-backoffice.js?v=<?= filemtime(ROOT_DIR.'/assets/js/admin-backoffice.js') ?>" defer></script>
</head><body class="admin-village" data-i18n-scope>
<a class="skip-link" href="#main">Zum Inhalt</a>
<aside class="sidebar">
    <a class="brand" href="<?= APP_BASE ?>/admin"><?= adminIcon('hud/city.svg','brand-mark') ?><span>CONQUER<small>Deine Verwaltung</small></span></a>
    <div data-locale-controls data-locale-compact="true" data-locale-install="false"></div>
    <button type="button" class="secondary mobile-menu" aria-expanded="false" aria-controls="admin-nav">☰ Menü</button>
    <nav id="admin-nav" aria-label="Verwaltung">
    <?php foreach([
        'Start'=>['dashboard'=>['','Übersicht','hud/city.svg'],'analytics'=>['/analytics','Statistiken','hud/reports.svg']],
        'Spielinhalte'=>['rewards'=>['/rewards','Beute & Drops','items/chest-gold.svg'],'items'=>['/items','Gegenstände','hud/inventory.svg'],'world_create'=>['/world-create','Welt erstellen','hud/city.svg'],'world'=>['/world','Welten & Spawns','hud/world.svg'],'lands'=>['/lands','Länder & Entwicklung','hud/world.svg']],
        'Gemeinschaft'=>['alpha_keys'=>['/alpha-keys','Alpha-Keys','items/scroll.svg'],'players'=>['/players','Spieler & Geschenke','knight.png'],'alliances'=>['/alliances','Allianzen','hud/alliance.svg'],'chat'=>['/chat','Chatprotokoll','hud/reports.svg'],'bug_reports'=>['/bug-reports','Bugmeldungen'.($newBugCount?' · '.$newBugCount:''),'hud/quest.svg']],
        'Verlauf'=>['audit'=>['/audit','Änderungsprotokoll','hud/quest.svg']]
    ] as $group=>$links): ?><div class="nav-caption"><?= ah($group) ?></div>
        <?php foreach($links as $key=>[$path,$label,$icon]): ?><a <?= $activePage===$key?'class="active" aria-current="page"':'' ?> href="<?= APP_BASE ?>/admin<?= $path ?>?world_id=<?= $selectedWorld ?>"><?= adminIcon($icon) ?><span><?= ah($label) ?></span></a><?php endforeach ?>
    <?php endforeach ?>
    </nav>
    <div class="sidebar-bottom"><strong><?= ah($adminSession['username']) ?></strong><small><?= $canEdit?'Administrator · voller Zugriff':'Moderator · Lesezugriff' ?></small><a href="<?= APP_BASE ?>/admin/logout">Abmelden →</a></div>
</aside>
<div class="shell">
<header class="topbar"><span><?= $globalPage?'🌐 Spielinhalte · alle Welten':'Verwaltung deiner Welt' ?></span>
<?php if($activePage==='rewards'): ?><span class="pill"><?= $globalPage?'Grundbeute · alle Welten':'Weltregel · '.ah($world['name']??'Welt '.$selectedWorld) ?></span>
<?php elseif(!$globalPage): ?><form method="get" class="world-picker"><label for="world-picker">Aktive Welt</label><select id="world-picker" name="world_id" aria-label="Aktive Verwaltungswelt"><?php foreach($worlds as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$w['id']===$selectedWorld?'selected':'' ?>><?= ah($w['name']) ?></option><?php endforeach ?></select><button class="secondary" type="submit">Wechseln</button></form><?php else: ?><span class="pill">Für alle Welten</span><?php endif ?>
</header>
<main id="main">
<div class="page-heading"><div><h1><?= ah($pageTitle) ?></h1><p><?= ah($descriptions[$activePage]??'Dein Spielerprofil und die zugehörige Stadt verwalten.') ?></p></div><a class="button secondary" href="<?= APP_BASE ?>/city#city">Spiel öffnen ↗</a></div>
<?php if(isset($_SESSION['admin_flash'])): ?><div class="notice <?= ah($_SESSION['admin_flash_kind']??'success') ?>" role="<?= ($_SESSION['admin_flash_kind']??'')==='error'?'alert':'status' ?>"><?= ah($_SESSION['admin_flash']) ?></div><?php unset($_SESSION['admin_flash'],$_SESSION['admin_flash_kind']);endif ?>
<?php if(!$canEdit): ?><div class="notice">Du kannst alle Einstellungen ansehen. Zum Speichern ist ein Administratorkonto erforderlich.</div><?php endif ?>
<noscript><div class="notice">Bitte aktiviere JavaScript für die Bildauswahl und das Hinzufügen von Beuteeinträgen.</div></noscript>
<?= $content ?>
</main>
<footer><span>Conquer · Verwaltung</span><span>Änderungen sind im Verlauf nachvollziehbar. Zeitangaben in UTC.</span></footer>
</div>
<?php if($usesItemPicker): ?><dialog id="item-picker-dialog" aria-labelledby="item-picker-title">
    <div class="picker-header"><div><h2 id="item-picker-title">Gegenstand auswählen</h2><p>Suche nach Name oder Gegenstandsnummer.</p></div><button type="button" class="secondary" data-picker-close aria-label="Auswahl schließen">✕</button></div>
    <div class="picker-filters"><label>Suche<input type="search" id="item-picker-search" placeholder="z. B. Nahrung, Beschleuniger …"></label><label>Kategorie<select id="item-picker-category"><option value="">Alle Kategorien</option><?php foreach(\Conquer\Admin\ItemPresentation::CATEGORIES+['fragments'=>'Zufällige Reliktfragmente'] as $key=>$label): ?><option value="<?= ah($key) ?>"><?= ah($label) ?></option><?php endforeach ?></select></label></div>
    <p class="picker-count" aria-live="polite"></p><div class="picker-results"></div>
</dialog>
<script type="application/json" id="admin-item-catalog"><?= json_encode(\Conquer\Admin\ItemPresentation::catalog(true),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php endif ?>
</body></html>
