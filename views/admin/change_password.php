<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Passwort ändern · Conquer Verwaltung</title>
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/admin-backoffice.css?v=<?= filemtime(ROOT_DIR.'/assets/css/admin-backoffice.css') ?>">
  <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR.'/assets/css/village-theme.css') ?>">
</head>
<body class="admin-village admin-auth">
<main class="admin-auth-card" aria-labelledby="password-title">
  <header class="admin-auth-header">
    <span aria-hidden="true">⚔</span>
    <div><p>Conquer Verwaltung</p><h1 id="password-title">Neues Passwort festlegen</h1></div>
  </header>
  <div class="admin-auth-content">
    <p class="admin-auth-intro">Willkommen, <strong><?= htmlspecialchars((string)$admin['username']) ?></strong>. Vor dem ersten Zugriff musst du das temporäre Passwort ersetzen.</p>
    <?php if($error!==''): ?><div class="notice error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif ?>
    <form method="post" action="<?= APP_BASE ?>/admin/change-password">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <label for="current-password">Temporäres Passwort<input id="current-password" name="current_password" type="password" autocomplete="current-password" required autofocus></label>
      <label for="new-password">Neues Passwort<input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="14" required aria-describedby="password-help"></label>
      <p id="password-help" class="hint">Mindestens 14 Zeichen. Verwende ein eigenes, nur hier eingesetztes Passwort.</p>
      <label for="confirm-password">Neues Passwort wiederholen<input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="14" required></label>
      <button type="submit">Passwort speichern und fortfahren</button>
    </form>
    <a class="admin-auth-logout" href="<?= APP_BASE ?>/admin/logout">Abmelden</a>
  </div>
</main>
</body>
</html>
