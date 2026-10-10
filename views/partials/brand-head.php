<?php
declare(strict_types=1);
// Public, session-independent branding shared by website, game and account pages.
$brandBase = htmlspecialchars(APP_BASE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$brandVersion = '20261007-kingdom-a';
$brandManifestVersion = filemtime(ROOT_DIR . '/manifest.php') . '-' . $brandVersion;
?>
<meta name="application-name" content="Union of Kingdoms">
<meta name="apple-mobile-web-app-title" content="Union of Kingdoms">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="icon" href="<?= $brandBase ?>/favicon.ico?v=<?= $brandVersion ?>" sizes="16x16 32x32 48x48" type="image/x-icon">
<link rel="icon" href="<?= $brandBase ?>/assets/icons/conquer-32.png?v=<?= $brandVersion ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?= $brandBase ?>/apple-touch-icon.png?v=<?= $brandVersion ?>" sizes="180x180">
<link rel="manifest" href="<?= $brandBase ?>/manifest.php?v=<?= $brandManifestVersion ?>">
<script src="<?= $brandBase ?>/assets/js/ui-press.js?v=<?= filemtime(ROOT_DIR.'/assets/js/ui-press.js') ?>" defer></script>
