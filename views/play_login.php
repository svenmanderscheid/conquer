<?php
declare(strict_types=1);
$base = htmlspecialchars(APP_BASE, ENT_QUOTES);
$mode = (($_POST['mode'] ?? $_GET['mode'] ?? 'login') === 'register') ? 'register' : 'login';
$loginError = $loginError ?? '';
$username = htmlspecialchars(is_string($_POST['username'] ?? null) ? $_POST['username'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$email = htmlspecialchars(is_string($_POST['email'] ?? null) ? $_POST['email'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$identifier = htmlspecialchars(is_string($_POST['identifier'] ?? null) ? $_POST['identifier'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Conquer\Game\Locale::current(), ENT_QUOTES) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#443052">
<title>Union of Kingdoms – Spiel anmelden</title>
<link rel="icon" href="<?= $base ?>/assets/icons/conquer.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/localization.css?v=<?= filemtime(ROOT_DIR.'/assets/css/localization.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/play-login.css?v=<?= filemtime(ROOT_DIR.'/assets/css/play-login.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR.'/assets/css/village-theme.css') ?>">
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>"><?= \Conquer\Game\Locale::bootstrap() ?></script>
<script src="<?= $base ?>/assets/js/localization.js?v=<?= filemtime(ROOT_DIR.'/assets/js/localization.js') ?>" defer></script>
</head>
<body class="play-login-page">
<main class="play-shell">
  <section class="play-art" aria-labelledby="play-story-title">
    <div class="play-story">
      <p data-i18n="login.alpha">Spielbare Alpha</p>
      <h1 id="play-story-title"><span data-i18n="landing.hero_kingdom">Dein Königreich.</span><br><em data-i18n="landing.hero_legend">Eure Legende.</em></h1>
    </div>
  </section>
  <section class="play-panel" aria-labelledby="play-login-title">
    <div class="play-panel-top">
      <a class="play-home" href="https://unionofkingdoms.com/">← <span data-i18n="login.back_website">Zur Website</span></a>
      <div class="play-language" data-locale-controls data-locale-compact="true" data-locale-install="false"></div>
    </div>
    <div class="play-card">
      <header class="play-card-head">
        <img class="play-logo" src="<?= $base ?>/assets/art/logo-union-of-kingdoms.png" width="190" height="127" alt="Union of Kingdoms">
        <p data-i18n="login.alpha">Spielbare Alpha</p>
        <h2 id="play-login-title" data-i18n="<?= $mode === 'register' ? 'login.register' : 'login.login' ?>"><?= $mode === 'register' ? 'Neues Königreich' : 'Anmelden' ?></h2>
      </header>
      <div class="play-card-body">
        <nav class="play-tabs" aria-label="Zugang wählen">
          <a href="<?= $base ?>/"<?= $mode === 'login' ? ' aria-current="page"' : '' ?> data-i18n="login.login">Anmelden</a>
          <a href="<?= $base ?>/?mode=register"<?= $mode === 'register' ? ' aria-current="page"' : '' ?> data-i18n="login.register">Neues Königreich</a>
        </nav>
        <?php if ($loginError): ?><p class="play-error" role="alert" tabindex="-1"><?= htmlspecialchars($loginError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif ?>
        <form method="post" action="<?= $base ?>/auth/local">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
          <input type="hidden" name="mode" value="<?= $mode ?>">
          <?php if ($mode === 'register'): ?>
          <label><span data-i18n="login.alpha_key">Alpha-Key</span>
            <input name="alpha_key" required inputmode="text" autocomplete="one-time-code" maxlength="35" spellcheck="false" value="<?= htmlspecialchars((string)($_POST['alpha_key'] ?? ''), ENT_QUOTES) ?>">
          </label>
          <?php endif ?>
          <?php if ($mode === 'register'): ?>
          <label><span>E-Mail-Adresse</span><input type="email" name="email" required maxlength="254" autocomplete="email" autocapitalize="none" spellcheck="false" value="<?= $email ?>"></label>
          <label><span data-i18n="login.username_short">Spielername</span><input name="username" required minlength="3" maxlength="25" pattern="[A-Za-z0-9_]+" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= $username ?>"></label>
          <?php else: ?>
          <label><span>E-Mail oder Spielername</span><input name="identifier" required maxlength="254" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= $identifier ?>"></label>
          <?php endif ?>
          <label><span data-i18n="login.password_short">Passwort</span>
            <input type="password" name="password" required minlength="<?= $mode === 'register' ? '10' : '1' ?>" maxlength="200" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>">
          </label>
          <button class="play-submit" type="submit" data-i18n="<?= $mode === 'register' ? 'login.create' : 'login.continue' ?>"><?= $mode === 'register' ? 'Königreich gründen →' : 'Weiterspielen →' ?></button>
        </form>
        <div class="play-links"><a href="<?= $base ?>/auth/recover" data-i18n="login.forgot">Passwort vergessen?</a></div>
        <p class="play-note" data-i18n="login.note_short">Kein Download · Keine Blockchain</p>
      </div>
    </div>
  </section>
</main>
</body>
</html>
