<?php
declare(strict_types=1);
$base = htmlspecialchars(APP_BASE, ENT_QUOTES);
$openAlpha = \Conquer\Auth\AlphaAccess::isOpen();
$openText = static fn(string $key): string => \Conquer\Game\Locale::html('landing.open_' . $key);
$registerUrl = 'https://play.unionofkingdoms.com/?mode=register';
$config = \Conquer\Bootstrap::getConfig();
$configuredRoot = rtrim((string) ($config['base_url'] ?? ''), '/');
$requestHost = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
if (in_array($requestHost, ['unionofkingdoms.com', 'www.unionofkingdoms.com', 'play.unionofkingdoms.com'], true)) {
    $configuredRoot = 'https://' . ($requestHost === 'www.unionofkingdoms.com' ? 'unionofkingdoms.com' : $requestHost);
}
if (!filter_var($configuredRoot, FILTER_VALIDATE_URL)) {
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $configuredRoot = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$publicRoot = $configuredRoot;
$canonical = $publicRoot . '/';
$socialImage = $publicRoot . '/assets/marketing/' . ($openAlpha ? 'kingdom-social-en-open-alpha-v1.webp' : 'kingdom-social-en-v5.webp');
$seoTitle = \Conquer\Game\Locale::t('landing.seo.title', [], 'en');
$seoDescription = \Conquer\Game\Locale::t($openAlpha ? 'landing.open_description' : 'landing.seo.description', [], 'en');
$waitlistError = $waitlistError ?? '';
$waitlistSuccess = $waitlistSuccess ?? false;
$waitlistValue = static fn(string $name): string => htmlspecialchars(is_string($_POST[$name] ?? null) ? mb_substr($_POST[$name], 0, 254) : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$wt = static fn(string $key): string => \Conquer\Game\Locale::html('waitlist.' . $key);
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [[
        '@type' => 'WebSite', '@id' => $canonical . '#website', 'url' => $canonical,
        'name' => 'Union of Kingdoms', 'inLanguage' => 'en',
        'description' => 'Build a lasting fantasy kingdom, develop your army and face rivals and powerful monsters with your alliance.',
    ], [
        '@type' => ['VideoGame', 'WebApplication'], '@id' => $canonical . '#game',
        'name' => 'Union of Kingdoms', 'url' => $canonical,
        'description' => 'A browser strategy game about city development, research, armies, PvP and cooperative alliance PvE. Currently in ' . ($openAlpha ? 'Open Alpha.' : 'closed alpha.'),
        'image' => $socialImage, 'inLanguage' => 'en', 'genre' => ['Strategy', 'City building', 'Fantasy'],
        'gamePlatform' => 'Webbrowser', 'applicationCategory' => 'GameApplication',
        'operatingSystem' => 'Web Browser', 'isAccessibleForFree' => true,
        'author' => ['@type' => 'Person', 'name' => 'Sven Manderscheid'],
    ]],
];
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= htmlspecialchars($seoTitle, ENT_QUOTES) ?></title><?php require ROOT_DIR.'/views/partials/brand-head.php'; ?>
<meta name="description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES) ?>">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
<meta name="theme-color" content="#5c4270">
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>">



<meta property="og:type" content="website"><meta property="og:locale" content="en_US">
<meta property="og:site_name" content="Union of Kingdoms"><meta property="og:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES) ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>"><meta property="og:image" content="<?= htmlspecialchars($socialImage, ENT_QUOTES) ?>">
<meta property="og:image:width" content="1122"><meta property="og:image:height" content="1402"><meta property="og:image:alt" content="The illustrated fantasy world of Union of Kingdoms">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES) ?>"><meta name="twitter:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES) ?>"><meta name="twitter:image" content="<?= htmlspecialchars($socialImage, ENT_QUOTES) ?>">
<link rel="preload" as="image" href="<?= $base ?>/assets/art/loading/branded/royal-sunrise-logo-v2.webp" type="image/webp" fetchpriority="high">
<link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR . '/assets/css/fantasy-fonts.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/landing.css?v=<?= filemtime(ROOT_DIR . '/assets/css/landing.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/localization.css?v=<?= filemtime(ROOT_DIR . '/assets/css/localization.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR . '/assets/css/village-theme.css') ?>">
<?= \Conquer\Game\Locale::bootstrapScripts($landingCspNonce) ?>
<script src="<?= $base ?>/assets/js/localization.js?v=<?= filemtime(ROOT_DIR . '/assets/js/localization.js') ?>" defer></script>
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>">document.documentElement.classList.replace('no-js','js')</script>
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>" type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= $base ?>/assets/js/landing.js?v=<?= filemtime(ROOT_DIR . '/assets/js/landing.js') ?>" defer></script>
</head>
<body class="welcome landing-page" data-i18n-scope>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="lp-header">
  <a class="lp-brand" href="<?= $base ?>/" aria-label="Union of Kingdoms home page">
    <img src="<?= $base ?>/assets/art/logo-union-of-kingdoms-en-v3.webp" width="190" height="127" alt="Union of Kingdoms – A new Era begins">
  </a>
  <div class="lp-header-actions">
    <div data-locale-controls data-locale-compact="true" data-locale-install="false"></div>
    <a class="lp-sign-in" href="https://play.unionofkingdoms.com/"><span>Log in</span> <span aria-hidden="true">→</span></a>
  </div>
</header>

<main id="main-content" class="lp-main">
  <section class="lp-hero" aria-labelledby="hero-title">
    <div class="lp-hero-picture" data-hero-gallery aria-hidden="true">
      <img class="lp-hero-slide is-active" src="<?= $base ?>/assets/art/loading/branded/royal-sunrise-logo-v2.webp" width="1672" height="936" alt="" fetchpriority="high">
      <img class="lp-hero-slide" src="<?= $base ?>/assets/art/loading/branded/heroes-monsters-logo-v2.webp" width="1672" height="936" alt="" loading="lazy">
      <img class="lp-hero-slide" src="<?= $base ?>/assets/art/loading/branded/moonlit-kingdom-logo-v2.webp" width="1672" height="936" alt="" loading="lazy">
    </div>
    <div class="lp-hero-content">
      <p class="lp-kicker"><span>In your browser</span><i aria-hidden="true">·</i><span>no download</span></p>
      <h1 id="hero-title"><span>Your kingdom.</span><br><em>Our next adventure.</em></h1>
      <p class="lp-lead"><?= \Conquer\Game\Locale::html('landing.seo.intro', [], 'en') ?></p>
      <?php if ($openAlpha): ?>
      <a class="lp-button lp-hero-start" href="<?= $registerUrl ?>"><span><?= $openText('cta') ?></span> <span aria-hidden="true">→</span></a>
      <?php else: ?>
      <a class="lp-button lp-hero-start" href="<?= $base ?>/?zugang=waitlist#zugang" data-auth-target="waitlist"><span>Register for closed alpha</span> <span aria-hidden="true">→</span></a>
      <?php endif ?>
    </div>
    <div class="lp-hero-controls" role="group" aria-label="Choose featured artwork">
      <button class="is-active" type="button" data-hero-slide="0" aria-label="Kingdom at sunrise" aria-pressed="true"></button>
      <button type="button" data-hero-slide="1" aria-label="Troops and monsters" aria-pressed="false"></button>
      <button type="button" data-hero-slide="2" aria-label="Kingdom at night" aria-pressed="false"></button>
    </div>
  </section>

  <?php require ROOT_DIR . '/views/partials/landing-game-guide.php'; ?>

  <section id="zugang" class="lp-access" aria-labelledby="access-title" tabindex="-1">
    <div class="lp-access-character lp-access-character-left" aria-hidden="true"><img src="<?= $base ?>/assets/art/characters/fantasy-troops-v3/guardian-ui.webp" alt=""></div>
    <?php if ($openAlpha): ?>
    <div class="lp-auth-card">
      <div class="lp-auth-heading">
        <p class="lp-auth-badge"><?= \Conquer\Game\Locale::html('landing.open_alpha') ?></p>
        <h2 id="access-title"><?= $openText('title') ?></h2>
      </div>
      <div class="lp-auth-body">
        <p class="lp-waitlist-intro"><?= $openText('access') ?></p>
        <a class="lp-button lp-submit" href="<?= $registerUrl ?>"><?= $openText('cta') ?> <span aria-hidden="true">→</span></a>
        <p class="lp-alpha-note"><?= $openText('notice') ?></p>
        <div class="lp-auth-footer"><a href="https://play.unionofkingdoms.com/" data-i18n="login.login"><?= \Conquer\Game\Locale::html('login.login') ?></a></div>
      </div>
    </div>
    <?php else: ?>
    <div class="lp-auth-card" data-auth-card data-access-mode="waitlist">
      <div class="lp-auth-heading">
        <p class="lp-auth-badge">Closed alpha</p>
        <h2 id="access-title">Your place in the kingdom</h2>
      </div>
      <div class="lp-auth-body">
        <div class="auth-switch" role="group" aria-label="Choose access option">
          <a href="<?= $base ?>/?zugang=waitlist#zugang" class="active" data-auth-target="waitlist" aria-current="true"><?= $wt('tab') ?></a>
          <a href="https://play.unionofkingdoms.com/">Log in</a>
        </div>
        <div data-access-panel="waitlist">
          <?php if ($waitlistSuccess): ?>
          <p class="lp-waitlist-success" role="status" tabindex="-1"><?= $wt('success') ?></p>
          <?php else: ?>
          <p class="lp-waitlist-intro"><?= $wt('intro') ?></p>
          <?php if ($waitlistError): ?><p class="form-error" role="alert" tabindex="-1"><?= \Conquer\Game\Locale::html($waitlistError) ?></p><?php endif ?>
          <form method="post" action="<?= $base ?>/alpha/waitlist" id="waitlist-form">
            <input type="hidden" name="locale" value="<?= \Conquer\Game\Locale::current() ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
            <div class="lp-name-fields">
              <label><span><?= $wt('first_name') ?></span><input name="first_name" required maxlength="80" autocomplete="given-name" value="<?= $waitlistValue('first_name') ?>"></label>
              <label><span><?= $wt('last_name') ?></span><input name="last_name" required maxlength="80" autocomplete="family-name" value="<?= $waitlistValue('last_name') ?>"></label>
            </div>
            <label><span><?= $wt('email') ?></span><input type="email" name="email" required maxlength="254" autocomplete="email" autocapitalize="none" spellcheck="false" value="<?= $waitlistValue('email') ?>"></label>
            <label class="lp-consent"><input type="checkbox" name="consent" value="1" required<?= ($_POST['consent'] ?? null) === '1' ? ' checked' : '' ?> aria-describedby="waitlist-privacy"><span><?= $wt('consent') ?></span></label>
            <button type="submit" class="lp-button lp-submit">Register for closed alpha</button>
          </form>
          <?php endif ?>
          <details class="lp-alpha-note" id="waitlist-privacy">
            <summary><?= $wt('privacy_title') ?></summary>
            <p><?= $wt('privacy') ?></p>
            <p><span><?= $wt('privacy_contact') ?></span> <a href="mailto:hello@unionofkingdoms.com">hello@unionofkingdoms.com</a></p>
          </details>
        </div>
        <div class="lp-auth-footer lp-key-link"><a href="https://play.unionofkingdoms.com/?mode=register"><?= $wt('redeem') ?></a></div>
      </div>
    </div>
    <?php endif ?>
    <div class="lp-access-character lp-access-character-right" aria-hidden="true"><img src="<?= $base ?>/assets/art/characters/fantasy-troops-v3/fire-archer-ui.webp" alt=""></div>
  </section>
</main>

<footer class="lp-footer">
  <?php if($discordInvite=\Conquer\Game\Community\CommunityNewsService::discordInvite()): ?><a href="<?= htmlspecialchars($discordInvite,ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer"><?= \Conquer\Game\Locale::html('social.discord_join') ?></a><?php endif ?>
  <small>© <?= date('Y') ?> Sven Manderscheid</small>
  <a href="mailto:hello@unionofkingdoms.com">Contact</a>
</footer>
</body>
</html>
