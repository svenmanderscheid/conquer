<?php
declare(strict_types=1);
$base = htmlspecialchars(APP_BASE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$lt = static fn(string $key): string => \Conquer\Game\Locale::html('legal.' . $key, [], 'en');
$isDeletion = ($legalPage ?? 'privacy') === 'deletion';
$pageKey = $isDeletion ? 'deletion' : 'privacy';
$sections = $isDeletion ? range(0, 4) : range(0, 11);
$deletionEmail = 'mailto:hello@unionofkingdoms.com?subject=' . rawurlencode('Union of Kingdoms account deletion request');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#fff7e7">
  <title>Union of Kingdoms — <?= $lt($pageKey . '.title') ?></title>
  <?php require ROOT_DIR . '/views/partials/brand-head.php'; ?>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR . '/assets/css/fantasy-fonts.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/legal.css?v=<?= filemtime(ROOT_DIR . '/assets/css/legal.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR . '/assets/css/village-theme.css') ?>">
</head>
<body class="legal-page">
  <main class="legal-document">
    <header>
      <img src="<?= $base ?>/assets/icons/conquer-192.png" width="76" height="76" alt="Union of Kingdoms">
      <p class="legal-brand">Union of Kingdoms</p>
      <h1><?= $lt($pageKey . '.title') ?></h1>
      <p class="legal-date"><?= $lt('updated') ?></p>
      <?php if (!$isDeletion): ?><p><?= $lt('privacy.intro') ?></p><?php endif ?>
    </header>
    <?php if ($isDeletion): ?>
      <div class="legal-request">
        <a class="button" href="<?= htmlspecialchars($deletionEmail, ENT_QUOTES) ?>"><?= $lt('deletion.action') ?></a>
        <p><?= $lt('deletion.email_hint') ?> <a href="mailto:hello@unionofkingdoms.com">hello@unionofkingdoms.com</a></p>
      </div>
    <?php else: ?>
      <nav class="legal-links" aria-label="<?= $lt('navigation') ?>">
        <a href="<?= $base ?>/account-deletion"><?= $lt('deletion.title') ?></a>
        <a href="#section-9"><?= $lt('privacy.9.title') ?></a>
        <a href="mailto:hello@unionofkingdoms.com"><?= $lt('contact') ?></a>
      </nav>
    <?php endif ?>
    <?php foreach ($sections as $section): ?>
      <section id="section-<?= $section ?>">
        <h2><?= $lt($pageKey . '.' . $section . '.title') ?></h2>
        <p><?= $lt($pageKey . '.' . $section . '.body') ?></p>
        <?php if (!$isDeletion && $section === 4): ?><p><?= $lt('privacy.link_stats') ?></p><?php endif ?>
        <?php if (!$isDeletion && $section === 7): ?>
          <p class="legal-links"><a href="https://www.hostinger.com/legal/privacy-policy" rel="noopener noreferrer">Hostinger</a><a href="https://policies.google.com/privacy" rel="noopener noreferrer">Google</a><a href="https://discord.com/privacy" rel="noopener noreferrer">Discord</a></p>
        <?php elseif (!$isDeletion && $section === 9): ?>
          <p><a href="<?= $base ?>/account-deletion"><?= $lt('deletion.title') ?></a> · <a href="https://cnpd.public.lu/en.html" rel="noopener noreferrer"><?= $lt('authority') ?></a></p>
        <?php endif ?>
      </section>
    <?php endforeach ?>
    <footer class="legal-links">
      <a href="<?= htmlspecialchars($legalGameUrl ?? APP_BASE . '/city', ENT_QUOTES) ?>"><?= $lt('back') ?></a>
      <a href="<?= $base ?>/<?= $isDeletion ? 'privacy' : 'account-deletion' ?>"><?= $lt(($isDeletion ? 'privacy' : 'deletion') . '.title') ?></a>
      <a href="mailto:hello@unionofkingdoms.com">hello@unionofkingdoms.com</a>
    </footer>
  </main>
</body>
</html>
