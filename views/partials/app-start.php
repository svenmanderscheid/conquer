<section id="app-start" class="app-start" aria-busy="true" aria-label="<?= \Conquer\Game\Locale::html('startup.loading') ?>" data-game="<?= isset($session['username']) ? 'true' : 'false' ?>">
  <picture class="app-start-art"><source media="(max-width: 760px)" srcset="<?= $base ?>/assets/marketing/village-760.webp"><img src="<?= $base ?>/assets/marketing/village-1920.webp" alt="" fetchpriority="high"></picture>
  <div class="app-start-brand"><img src="<?= $base ?>/assets/art/logo-union-of-kingdoms-en-v3.webp" alt="Union of Kingdoms" width="360" height="240"><p><?= \Conquer\Game\Locale::html('startup.tagline') ?></p></div>
  <div class="app-start-card">
    <p id="app-start-status" role="status"><?= \Conquer\Game\Locale::html('startup.loading') ?></p>
    <div id="app-start-progress" class="app-start-track" role="progressbar" aria-label="<?= \Conquer\Game\Locale::html('startup.loading') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i></div>
    <strong id="app-start-percent">0%</strong>
    <p class="app-start-tip"><?= \Conquer\Game\Locale::html('startup.tip') ?></p>
    <button id="app-start-retry" type="button" hidden><?= \Conquer\Game\Locale::html('startup.retry') ?></button>
  </div>
</section>
<script src="<?= $base ?>/assets/js/app-start.js?v=<?= filemtime(ROOT_DIR.'/assets/js/app-start.js') ?>"></script>
<noscript><style>.app-start{display:none}</style></noscript>
