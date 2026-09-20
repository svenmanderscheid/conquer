<?php
declare(strict_types=1);
$localizationBase=htmlspecialchars(APP_BASE,ENT_QUOTES);
?>
<link rel="stylesheet" href="<?= $localizationBase ?>/assets/css/localization.css?v=<?= filemtime(ROOT_DIR.'/assets/css/localization.css') ?>">
<script><?= \Conquer\Game\Locale::bootstrap() ?></script>
<script src="<?= $localizationBase ?>/assets/js/localization.js?v=<?= filemtime(ROOT_DIR.'/assets/js/localization.js') ?>" defer></script>
