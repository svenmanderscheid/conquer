<?php
declare(strict_types=1);
$base = htmlspecialchars(APP_BASE, ENT_QUOTES);
$openAlpha = \Conquer\Auth\AlphaAccess::isOpen();
$mode = (($_POST['mode'] ?? $_GET['mode'] ?? 'login') === 'register') ? 'register' : 'login';
$loginError = $loginError ?? '';
$username = htmlspecialchars(is_string($_POST['username'] ?? null) ? $_POST['username'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$email = htmlspecialchars(is_string($_POST['email'] ?? null) ? $_POST['email'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$identifier = htmlspecialchars(is_string($_POST['identifier'] ?? null) ? $_POST['identifier'] : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$registrationWorlds=[];$registrationWorld=0;
if($mode==='register'){
    $registrationWorlds=\Conquer\Db\Connection::getInstance()->query("SELECT id,name FROM worlds WHERE status IN ('open','running') ORDER BY id")->fetchAll();
    try{$registrationWorld=\Conquer\Game\World\WorldEntry::defaultWorld();}catch(\DomainException){}
    $requestedWorld=$_POST['world_id']??$_GET['world_id']??null;
    if(is_string($requestedWorld)&&ctype_digit($requestedWorld))$registrationWorld=(int)$requestedWorld;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Conquer\Game\Locale::current(), ENT_QUOTES) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#fff7e7">
<title>Union of Kingdoms – Sign in to play</title><?php require ROOT_DIR.'/views/partials/brand-head.php'; ?>

<link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/localization.css?v=<?= filemtime(ROOT_DIR.'/assets/css/localization.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/play-login.css?v=<?= filemtime(ROOT_DIR.'/assets/css/play-login.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/kingdom-entry.css?v=<?= filemtime(ROOT_DIR.'/assets/css/kingdom-entry.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR.'/assets/css/village-theme.css') ?>">
<?= \Conquer\Game\Locale::bootstrapScripts($landingCspNonce) ?>
<script src="<?= $base ?>/assets/js/localization.js?v=<?= filemtime(ROOT_DIR.'/assets/js/localization.js') ?>" defer></script>
</head>
<body class="play-login-page" data-i18n-scope>
<?php require ROOT_DIR.'/views/partials/app-start.php'; ?>
<main class="play-shell">
  <section class="play-panel" aria-labelledby="play-login-title">
    <div class="play-card">
      <header class="play-card-head">
        <img class="play-logo" src="<?= $base ?>/assets/art/logo-union-of-kingdoms-en-v3.webp" width="190" height="127" alt="Union of Kingdoms">
        <p data-i18n="<?= $openAlpha ? 'landing.open_alpha' : 'login.alpha' ?>"><?= $openAlpha ? 'Open Alpha' : 'Playable alpha' ?></p>
        <h1 id="play-login-title" data-i18n="<?= $mode === 'register' ? 'login.register' : 'login.login' ?>"><?= $mode === 'register' ? 'New kingdom' : 'Sign in' ?></h1>
      </header>
      <div class="play-card-body">
        <nav class="play-tabs" aria-label="Choose sign-in method">
          <a href="<?= $base ?>/"<?= $mode === 'login' ? ' aria-current="page"' : '' ?> data-i18n="login.login">Sign in</a>
          <a href="<?= $base ?>/?mode=register"<?= $mode === 'register' ? ' aria-current="page"' : '' ?> data-i18n="login.register">New kingdom</a>
        </nav>
        <?php if ($loginError): ?><p class="play-error" role="alert" tabindex="-1"><?= htmlspecialchars($loginError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif ?>
        <form method="post" action="<?= $base ?>/auth/local">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
          <input type="hidden" name="mode" value="<?= $mode ?>">
          <?php if ($mode === 'register' && !$openAlpha): ?>
          <label><span data-i18n="login.alpha_key">Alpha key</span>
            <input name="alpha_key" required inputmode="text" autocomplete="one-time-code" maxlength="35" spellcheck="false" value="<?= htmlspecialchars(is_string($_POST['alpha_key'] ?? null) ? $_POST['alpha_key'] : '', ENT_QUOTES) ?>">
          </label>
          <?php endif ?>
          <?php if ($mode === 'register'): ?>
          <?php if ($openAlpha): ?><p class="play-world-rule" data-i18n="landing.open_access">No key needed. Create your account and start playing in your browser.</p><?php endif ?>
          <label><span data-i18n="waitlist.email">Email address</span><input type="email" name="email" required maxlength="254" autocomplete="email" autocapitalize="none" spellcheck="false" value="<?= $email ?>"></label>
          <label><span data-i18n="login.username_short">Player name</span><input name="username" required minlength="3" maxlength="25" pattern="[A-Za-z0-9_]+" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= $username ?>"></label>
          <label><span data-i18n="registration.world">World</span><select name="world_id" required>
            <option value="" disabled<?= !in_array($registrationWorld,array_map('intval',array_column($registrationWorlds,'id')),true)?' selected':'' ?> data-i18n="registration.choose_world">Choose a world</option>
            <?php foreach($registrationWorlds as $registrationChoice): ?><option value="<?= (int)$registrationChoice['id'] ?>"<?= (int)$registrationChoice['id']===$registrationWorld?' selected':'' ?> data-user-content><?= htmlspecialchars($registrationChoice['name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></option><?php endforeach ?>
          </select></label>
          <p class="play-world-rule" data-i18n="registration.village_rule">This account will have one village in this world. Create another account for a second village.</p>
          <?php else: ?>
          <label><span>Email or player name</span><input name="identifier" required maxlength="254" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= $identifier ?>"></label>
          <?php endif ?>
          <label><span data-i18n="login.password_short">Password</span>
            <input type="password" name="password" required minlength="<?= $mode === 'register' ? '10' : '1' ?>" maxlength="200" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>">
          </label>
          <button class="play-submit" type="submit" data-i18n="<?= $mode === 'register' ? 'login.create' : 'login.continue' ?>"><?= $mode === 'register' ? 'Create kingdom →' : 'Continue playing →' ?></button>
        </form>
        <?php if ($openAlpha): ?><p class="play-world-rule" data-i18n="landing.open_notice">The game is in active development. Features and balance may change, and alpha progress may be reset.</p><?php endif ?>
        <div class="play-links"><a href="<?= $base ?>/auth/recover" data-i18n="login.forgot">Forgot your password?</a><a href="<?= $base ?>/privacy"><?= \Conquer\Game\Locale::html('legal.privacy.title') ?></a><a href="<?= $base ?>/account-deletion"><?= \Conquer\Game\Locale::html('legal.deletion.title') ?></a></div>
      </div>
    </div>
    <div class="play-panel-footer">
      <?php if($discordInvite=\Conquer\Game\Community\CommunityNewsService::discordInvite()): ?><a class="play-home" href="<?= htmlspecialchars($discordInvite,ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= \Conquer\Game\Locale::html('social.discord_join') ?></a><?php endif ?>
      <a class="play-home" href="https://unionofkingdoms.com/"><span data-i18n="login.back_website">Back to website</span></a>
      <div class="play-language" data-locale-controls data-locale-compact="true" data-locale-install="false"></div>
    </div>
  </section>
</main>
</body>
</html>
