<?php
declare(strict_types=1);
$base = htmlspecialchars(APP_BASE, ENT_QUOTES);
$config = \Conquer\Bootstrap::getConfig();
$configuredRoot = rtrim((string) ($config['base_url'] ?? ''), '/');
$requestHost = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
if (in_array($requestHost, ['unionofkingdoms.com', 'www.unionofkingdoms.com', 'play.unionofkingdoms.com'], true)) {
    $configuredRoot = 'https://' . $requestHost;
}
if (!filter_var($configuredRoot, FILTER_VALIDATE_URL)) {
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $configuredRoot = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$publicRoot = $configuredRoot;
$canonical = $publicRoot . '/';
$socialImage = $publicRoot . '/assets/marketing/conquer-social.jpg';
$postedMode = ($_POST['mode'] ?? 'register') === 'login' ? 'login' : 'register';
$accessMode = $accessMode ?? (!empty($loginError) ? $postedMode : ($_GET['zugang'] ?? 'waitlist'));
if (!is_string($accessMode) || !in_array($accessMode, ['waitlist', 'register', 'login'], true)) $accessMode = 'waitlist';
if ($accessMode !== 'waitlist') $postedMode = $accessMode;
$waitlistError = $waitlistError ?? '';
$waitlistSuccess = $waitlistSuccess ?? false;
$waitlistValue = static fn(string $name): string => htmlspecialchars(is_string($_POST[$name] ?? null) ? mb_substr($_POST[$name], 0, 254) : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$wt = static fn(string $key): string => \Conquer\Game\Locale::html('waitlist.' . $key);
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
<link rel="preload" as="image" href="<?= $base ?>/assets/art/loading/branded/royal-sunrise-logo-v1.webp" type="image/webp" fetchpriority="high">
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
    <img src="<?= $base ?>/assets/art/logo-union-of-kingdoms.png" width="190" height="127" alt="Union of Kingdoms – A New Era Begins">
  </a>
  <div class="lp-header-actions">
    <div class="lp-language lp-language-header" data-locale-controls data-locale-compact="true" data-locale-install="false"></div>
    <a class="lp-sign-in" href="<?= $base ?>/?zugang=login#zugang" data-auth-target="login"><span data-i18n="login.login">Anmelden</span> <span aria-hidden="true">→</span></a>
  </div>
</header>

<main id="main-content" class="lp-main">
  <section class="lp-hero" aria-labelledby="hero-title">
    <div class="lp-hero-picture" data-hero-gallery aria-hidden="true">
      <img class="lp-hero-slide is-active" src="<?= $base ?>/assets/art/loading/branded/royal-sunrise-logo-v1.webp" width="1672" height="936" alt="" fetchpriority="high">
      <img class="lp-hero-slide" src="<?= $base ?>/assets/art/loading/branded/heroes-monsters-logo-v1.webp" width="1672" height="936" alt="" loading="lazy">
      <img class="lp-hero-slide" src="<?= $base ?>/assets/art/loading/branded/moonlit-kingdom-logo-v1.webp" width="1672" height="936" alt="" loading="lazy">
    </div>
    <div class="lp-hero-content">
      <p class="lp-kicker"><span data-i18n="landing.browser">Im Browser</span><i aria-hidden="true">·</i><span data-i18n="landing.no_download">ohne Download</span></p>
      <h1 id="hero-title"><span data-i18n="landing.hero_kingdom">Dein Königreich.</span><br><em data-i18n="landing.hero_legend">Eure Legende.</em></h1>
      <p class="lp-lead" data-i18n="landing.short_intro">Baue deine Stadt. Finde Verbündete. Erobere die Welt.</p>
      <a class="lp-button lp-mobile-start" href="<?= $base ?>/?zugang=waitlist#zugang" data-auth-target="waitlist"><span data-i18n="waitlist.cta"><?= $wt('cta') ?></span> <span aria-hidden="true">→</span></a>
    </div>
    <div class="lp-hero-controls" role="group" aria-label="Titelmotiv auswählen">
      <button class="is-active" type="button" data-hero-slide="0" aria-label="Königreich bei Sonnenaufgang" aria-pressed="true"></button>
      <button type="button" data-hero-slide="1" aria-label="Helden und Monster" aria-pressed="false"></button>
      <button type="button" data-hero-slide="2" aria-label="Königreich bei Nacht" aria-pressed="false"></button>
    </div>
  </section>

  <section class="lp-gameplay" aria-labelledby="gameplay-title">
    <div class="lp-gameplay-heading">
      <p class="lp-section-kicker" data-i18n="landing.gameplay">Zum Spielprinzip</p>
      <h2 id="gameplay-title" data-i18n="landing.decisions">Eine Welt voller Entscheidungen</h2>
      <p><strong data-i18n="landing.plan">Plane weitsichtig. Handle gemeinsam.</strong> <span data-i18n="landing.plan_text">Deine Burgstufe allein entscheidet keinen Krieg. Marschzeiten, Vorräte, Spezialisierungen, Verteidigung und der richtige Moment sind ebenso wichtig.</span></p>
    </div>
    <div class="lp-gameplay-grid">
      <article class="lp-gameplay-card">
        <img src="<?= $base ?>/assets/art/landing/gameplay-build-v1.webp" width="1536" height="1024" loading="lazy" alt="Eine neue Turm wird im Königreich gebaut" data-i18n-attrs="alt:landing.explainer_build_image">
        <div><span aria-hidden="true">Ⅰ</span><h3 data-i18n="landing.city_economy">Stadt &amp; Wirtschaft</h3><p data-i18n="landing.city_economy_text">Halte Produktion, Lager und Ausbau im Gleichgewicht.</p></div>
      </article>
      <article class="lp-gameplay-card">
        <img src="<?= $base ?>/assets/art/landing/gameplay-train-v1.webp" width="1536" height="1024" loading="lazy" alt="Ritter, Bogenschützin und Reiter trainieren gemeinsam" data-i18n-attrs="alt:landing.explainer_train_image">
        <div><span aria-hidden="true">Ⅱ</span><h3 data-i18n="landing.research_army">Forschung &amp; Armee</h3><p data-i18n="landing.research_army_text">Entwickle klare Stärken statt alles gleichzeitig zu beginnen.</p></div>
      </article>
      <article class="lp-gameplay-card lp-gameplay-card-battle">
        <img src="<?= $base ?>/assets/art/landing/gameplay-battle-v1.webp" width="1536" height="1024" loading="lazy" alt="Helden kämpfen gemeinsam gegen Orc, Skelett und Golem" data-i18n-attrs="alt:landing.explainer_battle_image">
        <div><span aria-hidden="true">Ⅲ</span><h3 data-i18n="landing.world_alliances">Welt &amp; Allianzen</h3><p data-i18n="landing.world_alliances_text">Teile Informationen, unterstütze Verbündete und wähle deine Kämpfe.</p></div>
      </article>
    </div>
  </section>

  <section id="zugang" class="lp-access" aria-labelledby="access-title" tabindex="-1">
    <div class="lp-access-character lp-access-character-left" aria-hidden="true"><img src="<?= $base ?>/assets/art/knight.png" alt=""></div>
    <div class="lp-auth-card" data-auth-card data-access-mode="<?= $accessMode ?>">
      <div class="lp-auth-heading">
        <p class="lp-auth-badge">Geschlossene Alpha</p>
        <h2 id="access-title">Dein Platz im Königreich</h2>
      </div>
      <div class="lp-auth-body">
        <div class="auth-switch" role="group" aria-label="Anmeldung auswählen">
          <a href="<?= $base ?>/?zugang=waitlist#zugang" class="<?= $accessMode === 'waitlist' ? 'active' : '' ?>" data-auth-target="waitlist"<?= $accessMode === 'waitlist' ? ' aria-current="true"' : '' ?> data-i18n="waitlist.tab"><?= $wt('tab') ?></a>
          <a href="<?= $base ?>/?zugang=login#zugang" class="<?= $accessMode === 'login' ? 'active' : '' ?>" data-auth-target="login"<?= $accessMode === 'login' ? ' aria-current="true"' : '' ?>>Anmelden</a>
        </div>
        <div data-access-panel="waitlist"<?= $accessMode !== 'waitlist' ? ' hidden' : '' ?>>
          <?php if ($waitlistSuccess): ?>
          <p class="lp-waitlist-success" role="status" tabindex="-1" data-i18n="waitlist.success"><?= $wt('success') ?></p>
          <?php else: ?>
          <p class="lp-waitlist-intro" data-i18n="waitlist.intro"><?= $wt('intro') ?></p>
          <?php if ($waitlistError): ?><p class="form-error" role="alert" tabindex="-1" data-i18n="<?= htmlspecialchars($waitlistError, ENT_QUOTES) ?>"><?= \Conquer\Game\Locale::html($waitlistError) ?></p><?php endif ?>
          <form method="post" action="<?= $base ?>/alpha/waitlist" id="waitlist-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
            <div class="lp-name-fields">
              <label><span data-i18n="waitlist.first_name"><?= $wt('first_name') ?></span><input name="first_name" required maxlength="80" autocomplete="given-name" value="<?= $waitlistValue('first_name') ?>"></label>
              <label><span data-i18n="waitlist.last_name"><?= $wt('last_name') ?></span><input name="last_name" required maxlength="80" autocomplete="family-name" value="<?= $waitlistValue('last_name') ?>"></label>
            </div>
            <label><span data-i18n="waitlist.email"><?= $wt('email') ?></span><input type="email" name="email" required maxlength="254" autocomplete="email" autocapitalize="none" spellcheck="false" value="<?= $waitlistValue('email') ?>"></label>
            <label class="lp-consent"><input type="checkbox" name="consent" value="1" required<?= ($_POST['consent'] ?? null) === '1' ? ' checked' : '' ?> aria-describedby="waitlist-privacy"><span data-i18n="waitlist.consent"><?= $wt('consent') ?></span></label>
            <button type="submit" class="lp-button lp-submit" data-i18n="waitlist.submit"><?= $wt('submit') ?></button>
          </form>
          <?php endif ?>
          <details class="lp-alpha-note" id="waitlist-privacy">
            <summary data-i18n="waitlist.privacy_title"><?= $wt('privacy_title') ?></summary>
            <p data-i18n="waitlist.privacy"><?= $wt('privacy') ?></p>
            <p><span data-i18n="waitlist.privacy_contact"><?= $wt('privacy_contact') ?></span> <a href="mailto:hello@unionofkingdoms.com">hello@unionofkingdoms.com</a></p>
          </details>
        </div>
        <div data-access-panel="auth"<?= $accessMode === 'waitlist' ? ' hidden' : '' ?>>
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
        <div class="lp-auth-footer lp-key-link"><a href="<?= $base ?>/?zugang=register#zugang" data-auth-target="register"<?= $accessMode === 'register' ? ' aria-current="true"' : '' ?> data-i18n="waitlist.redeem"><?= $wt('redeem') ?></a></div>
      </div>
    </div>
    <div class="lp-access-character lp-access-character-right" aria-hidden="true"><img src="<?= $base ?>/assets/art/archer.png" alt=""></div>
  </section>
</main>

<footer class="lp-footer">
  <small>© <?= date('Y') ?> Sven Manderscheid</small>
  <a href="mailto:hello@unionofkingdoms.com" data-i18n="landing.contact">Kontakt</a>
</footer>
</body>
</html>
