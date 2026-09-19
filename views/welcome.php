<?php
declare(strict_types=1);
$base = htmlspecialchars(APP_BASE, ENT_QUOTES);
$config = \Conquer\Bootstrap::getConfig();
$configuredRoot = rtrim((string) ($config['base_url'] ?? ''), '/');
if (!filter_var($configuredRoot, FILTER_VALIDATE_URL)) {
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $configuredRoot = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$publicRoot = $configuredRoot;
$canonical = $publicRoot . '/';
$socialImage = $publicRoot . '/assets/marketing/conquer-social.jpg';
$postedMode = ($_POST['mode'] ?? 'register') === 'login' ? 'login' : 'register';
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [[
        '@type' => 'WebSite', '@id' => $canonical . '#website', 'url' => $canonical,
        'name' => 'Conquer · Chroniken eines Königreichs', 'inLanguage' => 'de',
        'description' => 'Baue ein lebendiges Fantasy-Königreich, führe deine Truppen und kämpfe mit deiner Allianz um eine gemeinsame Welt.',
    ], [
        '@type' => ['VideoGame', 'WebApplication'], '@id' => $canonical . '#game',
        'name' => 'Conquer · Chroniken eines Königreichs', 'url' => $canonical,
        'description' => 'Ein gemeinsames Browser-Strategiespiel über Aufbau, Forschung, Armeen, Allianzen und eine lebendige Fantasywelt.',
        'image' => $socialImage, 'inLanguage' => 'de', 'genre' => ['Strategiespiel', 'Aufbauspiel', 'Fantasy'],
        'gamePlatform' => 'Webbrowser', 'applicationCategory' => 'GameApplication',
        'operatingSystem' => 'Web Browser', 'isAccessibleForFree' => true,
        'author' => ['@type' => 'Person', 'name' => 'Sven Manderscheid'],
    ]],
];
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Conquer\Game\Locale::current(), ENT_QUOTES) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Conquer – Fantasy-Königreich aufbauen &amp; gemeinsam erobern</title>
<meta name="description" content="Errichte dein Königreich, erforsche neue Technologien, bilde Armeen aus und kämpfe mit deiner Allianz. Conquer befindet sich in einer geschlossenen Alpha.">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
<meta name="theme-color" content="#5c4270">
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>">
<link rel="icon" href="<?= $base ?>/assets/icons/conquer.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= $base ?>/assets/icons/conquer-192.png">
<link rel="manifest" href="<?= $base ?>/manifest.php">
<meta property="og:type" content="website"><meta property="og:locale" content="de_DE">
<meta property="og:site_name" content="Conquer"><meta property="og:title" content="Conquer – Dein Königreich. Eure Legende.">
<meta property="og:description" content="Baue, forsche und kämpfe mit deiner Allianz in einer gemeinsamen Fantasywelt. Geschlossene Alpha.">
<meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES) ?>"><meta property="og:image" content="<?= htmlspecialchars($socialImage, ENT_QUOTES) ?>">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta property="og:image:alt" content="Das gezeichnete Fantasy-Königreich von Conquer">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="Conquer – Dein Königreich. Eure Legende."><meta name="twitter:description" content="Baue, forsche und kämpfe in der geschlossenen Alpha."><meta name="twitter:image" content="<?= htmlspecialchars($socialImage, ENT_QUOTES) ?>">
<link rel="preload" as="image" href="<?= $base ?>/assets/marketing/village-1280.webp" imagesrcset="<?= $base ?>/assets/marketing/village-760.webp 760w, <?= $base ?>/assets/marketing/village-1280.webp 1280w, <?= $base ?>/assets/marketing/village-1920.webp 1920w" imagesizes="(max-width: 800px) 100vw, 65vw" type="image/webp" fetchpriority="high">
<link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR . '/assets/css/fantasy-fonts.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/landing.css?v=<?= filemtime(ROOT_DIR . '/assets/css/landing.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/localization.css?v=<?= filemtime(ROOT_DIR . '/assets/css/localization.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR . '/assets/css/village-theme.css') ?>">
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>">document.documentElement.classList.replace('no-js','js')</script>
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>"><?= \Conquer\Game\Locale::bootstrap() ?></script>
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>" type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= $base ?>/assets/js/localization.js?v=<?= filemtime(ROOT_DIR . '/assets/js/localization.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/landing.js?v=<?= filemtime(ROOT_DIR . '/assets/js/landing.js') ?>" defer></script>
</head>
<body class="welcome landing-page">
<a class="skip-link" href="#main-content">Zum Inhalt springen</a>
<header class="lp-header">
  <a class="lp-brand" href="<?= $base ?>/" aria-label="Conquer Startseite">
    <img src="<?= $base ?>/assets/icons/conquer.svg" width="44" height="44" alt="">
    <span>CONQUER<small>Chroniken eines Königreichs</small></span>
  </a>
  <a class="lp-sign-in" href="#zugang" data-auth-target="login">Anmelden <span aria-hidden="true">→</span></a>
</header>

<main id="main-content" class="lp-main">
  <section class="lp-hero" aria-labelledby="hero-title">
    <picture class="lp-hero-picture">
      <source type="image/webp" srcset="<?= $base ?>/assets/marketing/village-760.webp 760w, <?= $base ?>/assets/marketing/village-1280.webp 1280w, <?= $base ?>/assets/marketing/village-1920.webp 1920w" sizes="(max-width: 800px) 100vw, 65vw">
      <img src="<?= $base ?>/assets/art/village2.png" width="1536" height="1024" alt="Ein gezeichnetes Fantasy-Königreich mit Burg, Höfen und Werkstätten" fetchpriority="high">
    </picture>
    <div class="lp-hero-content">
      <p class="lp-kicker"><span>Im Browser</span><i aria-hidden="true">·</i><span>ohne Download</span></p>
      <h1 id="hero-title">Dein Königreich.<br><em>Eure Legende.</em></h1>
      <p class="lp-lead" data-i18n="landing.short_intro">Baue deine Stadt. Finde Verbündete. Erobere die Welt.</p>
      <a class="lp-button lp-mobile-start" href="#zugang" data-auth-target="register">Losspielen <span aria-hidden="true">→</span></a>
    </div>
  </section>

  <section id="zugang" class="lp-access" aria-labelledby="access-title" tabindex="-1">
    <div class="lp-auth-card" data-auth-card>
      <div class="lp-auth-heading">
        <p class="lp-auth-badge">Geschlossene Alpha</p>
        <h2 id="access-title">Dein Platz im Königreich</h2>
      </div>
      <div class="lp-auth-body">
        <div class="auth-switch" role="group" aria-label="Anmeldung auswählen">
          <button type="button" class="<?= $postedMode === 'register' ? 'active' : '' ?>" data-mode="register" aria-pressed="<?= $postedMode === 'register' ? 'true' : 'false' ?>">Neues Königreich</button>
          <button type="button" class="<?= $postedMode === 'login' ? 'active' : '' ?>" data-mode="login" aria-pressed="<?= $postedMode === 'login' ? 'true' : 'false' ?>">Anmelden</button>
        </div>
        <?php if ($loginError): ?><p class="form-error" role="alert" tabindex="-1"><?= htmlspecialchars($loginError) ?></p><?php endif ?>
        <form method="post" action="<?= $base ?>/auth/local">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
          <input type="hidden" name="mode" id="auth-mode" value="<?= $postedMode ?>">
          <label class="alpha-key-field"<?= $postedMode === 'login' ? ' hidden' : '' ?>>Alpha-Key
            <input name="alpha_key" inputmode="text" autocomplete="one-time-code" maxlength="35" spellcheck="false"<?= $postedMode === 'register' ? ' required' : ' disabled' ?> value="<?= htmlspecialchars((string) ($_POST['alpha_key'] ?? ''), ENT_QUOTES) ?>">
          </label>
          <label>Spielername
            <input name="username" required minlength="3" maxlength="25" pattern="[A-Za-z0-9_]+" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES) ?>">
          </label>
          <label>Passwort
            <input type="password" name="password" required minlength="<?= $postedMode === 'register' ? '10' : '1' ?>" maxlength="200" autocomplete="<?= $postedMode === 'register' ? 'new-password' : 'current-password' ?>" placeholder="<?= $postedMode === 'register' ? 'Mindestens 10 Zeichen' : 'Dein Passwort' ?>">
          </label>
          <button type="submit" class="lp-button lp-submit" id="auth-submit"> <?= $postedMode === 'register' ? 'Königreich gründen' : 'Weiterspielen' ?> <span aria-hidden="true">→</span></button>
        </form>
        <div class="lp-auth-footer"><a href="<?= $base ?>/auth/recover">Passwort vergessen?</a></div>
        <details class="lp-alpha-note">
          <summary data-i18n="landing.alpha_details">Hinweis zur Alpha</summary>
          <p data-i18n="landing.alpha_notice">Neue Konten benötigen einen Alpha-Key. Das Spiel wird weiterentwickelt; Änderungen und Spielstandsresets sind möglich.</p>
        </details>
      </div>
    </div>
    <div class="lp-language" data-locale-controls data-locale-compact="true" data-locale-install="false"></div>
  </section>
</main>

<footer class="lp-footer">
  <small>© <?= date('Y') ?> Sven Manderscheid</small>
  <a href="mailto:sven@svenmanderscheid.lu">Kontakt</a>
</footer>
</body>
</html>
