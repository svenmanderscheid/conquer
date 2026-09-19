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
<html lang="de" class="no-js">
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
<link rel="preload" as="image" href="<?= $base ?>/assets/marketing/village-1280.webp" type="image/webp" fetchpriority="high">
<link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR . '/assets/css/fantasy-fonts.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/landing.css?v=<?= filemtime(ROOT_DIR . '/assets/css/landing.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR . '/assets/css/village-theme.css') ?>">
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>">document.documentElement.classList.replace('no-js','js')</script>
<script nonce="<?= htmlspecialchars($landingCspNonce, ENT_QUOTES) ?>" type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= $base ?>/assets/js/landing.js?v=<?= filemtime(ROOT_DIR . '/assets/js/landing.js') ?>" defer></script>
</head>
<body class="welcome landing-page">
<a class="skip-link" href="#main-content">Zum Inhalt springen</a>
<header class="lp-header" data-site-header>
  <a class="lp-brand" href="<?= $base ?>/" aria-label="Conquer Startseite"><img src="<?= $base ?>/assets/icons/conquer.svg" width="44" height="44" alt=""><span>CONQUER<small>Chroniken eines Königreichs</small></span></a>
  <button class="lp-menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation"><span></span><span></span><span></span><span class="sr-only">Navigation öffnen</span></button>
  <nav id="site-navigation" class="lp-nav" aria-label="Hauptnavigation">
    <a href="#spiel">Das Spiel</a><a href="#welt">Die Welt</a><a href="#alpha">Alpha</a><a href="#fragen">Fragen</a>
    <a class="lp-nav-cta" href="#zugang">Alpha-Zugang</a>
  </nav>
</header>

<main id="main-content">
  <section class="lp-hero" aria-labelledby="hero-title">
    <picture class="lp-hero-picture">
      <source type="image/webp" media="(max-width:700px)" srcset="<?= $base ?>/assets/marketing/village-760.webp">
      <source type="image/webp" srcset="<?= $base ?>/assets/marketing/village-1280.webp 1280w, <?= $base ?>/assets/marketing/village-1920.webp 1920w" sizes="100vw">
      <img src="<?= $base ?>/assets/art/village2.png" width="1536" height="1024" alt="Ein gezeichnetes Fantasy-Königreich mit Burg, Höfen und Werkstätten" fetchpriority="high">
    </picture>
    <div class="lp-hero-shade"></div><div class="lp-stars" aria-hidden="true"></div>
    <div class="lp-hero-content">
      <p class="lp-kicker"><span></span> Geschlossene Alpha</p>
      <h1 id="hero-title">Dein Königreich.<br><em>Eure Legende.</em></h1>
      <p class="lp-lead">Errichte eine lebendige Stadt, schmiede mächtige Armeen und verbünde dich mit anderen Herrschern. Jede Entscheidung formt eure gemeinsame Welt.</p>
      <div class="lp-actions"><a class="lp-button lp-button-primary" href="#zugang">Alpha-Key einlösen <span aria-hidden="true">→</span></a><a class="lp-button lp-button-ghost" href="#spiel">Spiel entdecken</a></div>
      <ul class="lp-hero-facts" aria-label="Spielmerkmale"><li><strong>Im Browser</strong><span>ohne Download</span></li><li><strong>Gemeinsame Welt</strong><span>mit echten Allianzen</span></li><li><strong>Strategie</strong><span>statt Autopilot</span></li></ul>
    </div>
    <a class="lp-scroll-cue" href="#spiel" aria-label="Zum Spielprinzip"><span></span></a>
  </section>

  <section id="spiel" class="lp-section lp-proof">
    <div class="lp-proof-heading reveal"><div><p class="lp-kicker">Kein Render. Das ist die Alpha.</p><h2>Eine Minute im Königreich.</h2></div><p>Du springst nicht durch austauschbare Menüs. Stadt, Welt und Kampf gehören zu einem einzigen Rhythmus: vorbereiten, entscheiden, gemeinsam handeln.</p></div>
    <div class="lp-proof-layout">
      <div class="lp-proof-copy reveal">
        <span class="lp-margin-note">Aus dem Spieltagebuch</span>
        <ol class="lp-decisions">
          <li><span>07:12</span><div><strong>Die Stadt erwacht</strong><p>Erträge einsammeln, Bauplätze prüfen, Forschung und Ausbildung aufeinander abstimmen.</p></div></li>
          <li><span>07:15</span><div><strong>Die Welt ruft</strong><p>Rohstoffe sichern, freie Wege lesen und entdecken, wo deine Allianz dich braucht.</p></div></li>
          <li><span>07:18</span><div><strong>Der Marsch beginnt</strong><p>Truppen wählen, Laufzeit abwägen und nur angreifen, wenn der Einsatz stimmt.</p></div></li>
        </ol>
        <p class="lp-proof-note">Alle Aufnahmen stammen aus der laufenden Spielversion – mit echtem HUD, echten Gebäuden und der aktuellen Weltkarte.</p>
      </div>
      <div class="lp-game-reel reveal" data-game-reel aria-label="Bewegter Einblick in die Alpha">
        <div class="lp-reel-top"><span><i></i> Live aus der Alpha</span><span data-reel-counter>01 / 03</span></div>
        <div class="lp-reel-screen">
          <figure class="is-active" data-reel-scene="0"><img src="<?= $base ?>/assets/marketing/game-city.webp" width="1280" height="800" loading="lazy" alt="Die spielbare Stadtansicht von Conquer"><figcaption><small>Stadt</small><strong>Baue eine Stadt, die funktioniert.</strong></figcaption></figure>
          <figure data-reel-scene="1" aria-hidden="true"><img src="<?= $base ?>/assets/marketing/game-world.webp" width="1280" height="800" loading="lazy" alt="Die Weltkarte von Conquer mit Rohstoffziel"><figcaption><small>Welt</small><strong>Lies die Karte. Wähle deinen Weg.</strong></figcaption></figure>
          <figure data-reel-scene="2" aria-hidden="true"><img src="<?= $base ?>/assets/marketing/game-battle.webp" width="1280" height="800" loading="lazy" alt="Die Truppenauswahl vor einem Monsterangriff"><figcaption><small>Kampf</small><strong>Jeder Marsch ist eine Entscheidung.</strong></figcaption></figure>
          <div class="lp-reel-scan" aria-hidden="true"></div>
        </div>
        <div class="lp-reel-controls">
          <div role="group" aria-label="Spielansicht wählen"><button class="is-active" type="button" data-reel-to="0" aria-pressed="true">Stadt</button><button type="button" data-reel-to="1" aria-pressed="false">Welt</button><button type="button" data-reel-to="2" aria-pressed="false">Kampf</button></div>
          <button class="lp-reel-toggle" type="button" data-reel-toggle aria-pressed="false"><span aria-hidden="true">Ⅱ</span><span data-reel-toggle-label>Animation pausieren</span></button>
        </div>
        <div class="lp-reel-progress" aria-hidden="true"><span data-reel-progress></span></div>
      </div>
    </div>
  </section>

  <section id="welt" class="lp-section lp-world">
    <div class="lp-world-copy reveal"><p class="lp-kicker">Eine Welt voller Entscheidungen</p><h2>Plane weitsichtig. Handle gemeinsam.</h2><p>Deine Burgstufe allein entscheidet keinen Krieg. Marschzeiten, Vorräte, Spezialisierungen, Verteidigung und der richtige Moment sind ebenso wichtig.</p><ul><li><strong>Stadt & Wirtschaft</strong> – halte Produktion, Lager und Ausbau im Gleichgewicht.</li><li><strong>Forschung & Armee</strong> – entwickle klare Stärken statt alles gleichzeitig zu beginnen.</li><li><strong>Welt & Allianzen</strong> – teile Informationen, unterstütze Verbündete und wähle deine Kämpfe.</li></ul></div>
    <div class="lp-world-art reveal"><img src="<?= $base ?>/assets/marketing/world-960.webp" width="960" height="640" loading="lazy" alt="Die gezeichnete Weltkarte von Conquer"><span class="lp-map-marker one" aria-hidden="true"></span><span class="lp-map-marker two" aria-hidden="true"></span><span class="lp-map-marker three" aria-hidden="true"></span><span class="lp-march-path" aria-hidden="true"><i></i></span><div class="lp-map-card"><small>Gemeinsames Ziel</small><strong>Regionaler Boss</strong><span>Allianz sammeln · Marsch planen</span></div></div>
  </section>

  <section class="lp-oath" aria-labelledby="oath-title">
    <img class="lp-oath-knight reveal" src="<?= $base ?>/assets/marketing/character-infantry.webp" width="978" height="1133" loading="lazy" alt="Schwertkämpfer aus Conquer">
    <div class="lp-oath-copy reveal"><p class="lp-kicker">Deine Chronik</p><h2 id="oath-title"><span>Baue</span>, was Bestand hat.<br><span>Führe</span>, was dir vertraut.<br><span>Teile</span>, was euch stärker macht.</h2><p>Conquer belohnt keine einsame Checkliste. Dein Reich bekommt eine Handschrift – durch Prioritäten, Verbündete und die Kämpfe, die du bewusst führst oder vermeidest.</p></div>
    <img class="lp-oath-orc reveal" src="<?= $base ?>/assets/marketing/character-archer.webp" width="523" height="727" loading="lazy" alt="Bogenschütze aus Conquer">
  </section>

  <section id="alpha" class="lp-section lp-alpha-info"><div class="lp-alpha-seal reveal" aria-hidden="true"><span>α</span><strong>GESCHLOSSENE<br>ALPHA</strong></div><div class="reveal"><p class="lp-kicker">Du spielst früh</p><h2>Hilf uns, das Königreich zu formen.</h2><p>Die Alpha ist ein echter Spieltest: Systeme werden erweitert, Werte angepasst und Fehler beseitigt. Dein Feedback beeinflusst, was Conquer wird.</p><div class="lp-alpha-notes"><div><strong>Was du mitbringst</strong><span>Einen gültigen Alpha-Key, einen modernen Browser und Freude an ehrlichem Feedback.</span></div><div><strong>Womit du rechnest</strong><span>Unfertige Inhalte, Balanceänderungen, mögliche Unterbrechungen und – falls technisch nötig – Spielstandsresets.</span></div><div><strong>Was wir versprechen</strong><span>Kein Pay-to-win-Test, transparente Änderungen und ein Spiel, das gemeinsam mit seinen Testern wächst.</span></div></div></div></section>

  <section id="zugang" class="lp-section lp-access" aria-labelledby="access-title">
    <div class="lp-access-copy reveal"><p class="lp-kicker">Dein Platz im Königreich</p><h2 id="access-title">Alpha-Key bereit?</h2><p>Löse deinen persönlichen Zugang einmal beim Erstellen des Kontos ein. Danach genügt dein Spielername und Passwort, um weiterzuspielen.</p><ol><li><span>1</span><div><strong>Key einlösen</strong><small>Dein Key wird sicher geprüft und nicht im Klartext gespeichert.</small></div></li><li><span>2</span><div><strong>Königreich gründen</strong><small>Wähle Namen und Passwort für dein Spielkonto.</small></div></li><li><span>3</span><div><strong>Losspielen</strong><small>Dein Fortschritt wird automatisch serverseitig gespeichert.</small></div></li></ol></div>
    <div class="lp-auth-card reveal" data-auth-card>
      <span class="lp-auth-badge">Nur mit Alpha-Key</span><h3>Tor zum Königreich</h3><p class="lp-auth-intro">Neu hier oder schon Teil der Alpha?</p>
      <div class="auth-switch" role="group" aria-label="Anmeldung auswählen"><button type="button" class="<?= $postedMode === 'register' ? 'active' : '' ?>" data-mode="register" aria-pressed="<?= $postedMode === 'register' ? 'true' : 'false' ?>">Neues Königreich</button><button type="button" class="<?= $postedMode === 'login' ? 'active' : '' ?>" data-mode="login" aria-pressed="<?= $postedMode === 'login' ? 'true' : 'false' ?>">Anmelden</button></div>
      <?php if ($loginError): ?><p class="form-error" role="alert" tabindex="-1"><?= htmlspecialchars($loginError) ?></p><?php endif ?>
      <form method="post" action="<?= $base ?>/auth/local">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>"><input type="hidden" name="mode" id="auth-mode" value="<?= $postedMode ?>">
        <label class="alpha-key-field">Alpha-Key<span class="lp-field-help">Einmalig für neue Konten</span><input name="alpha_key" inputmode="text" autocomplete="one-time-code" maxlength="35" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX" value="<?= htmlspecialchars((string) ($_POST['alpha_key'] ?? ''), ENT_QUOTES) ?>"></label>
        <label>Spielername<input name="username" required minlength="3" maxlength="25" pattern="[A-Za-z0-9_]+" autocomplete="username" placeholder="Wie sollen wir dich nennen?" value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES) ?>"></label>
        <label>Passwort<input type="password" name="password" required minlength="<?= $postedMode === 'register' ? '10' : '1' ?>" maxlength="200" autocomplete="<?= $postedMode === 'register' ? 'new-password' : 'current-password' ?>" placeholder="<?= $postedMode === 'register' ? 'Mindestens 10 Zeichen' : 'Dein Passwort' ?>"></label>
        <button class="lp-button lp-button-primary lp-submit" id="auth-submit"> <?= $postedMode === 'register' ? 'Königreich gründen' : 'Weiterspielen' ?> <span aria-hidden="true">→</span></button>
      </form>
      <div class="lp-auth-footer"><a href="<?= $base ?>/auth/recover">Passwort vergessen?</a><span>Kein Download · Keine Blockchain</span></div>
    </div>
  </section>

  <section id="fragen" class="lp-section lp-faq"><div class="lp-section-heading reveal"><p class="lp-kicker">Häufige Fragen</p><h2>Bevor du aufbrichst.</h2></div><div class="lp-faq-list">
    <details class="reveal"><summary>Was bedeutet „geschlossene Alpha“?</summary><p>Der Zugang ist auf eingeladene Tester begrenzt. Das Spiel ist bereits spielbar, wird aber aktiv erweitert und ausbalanciert.</p></details>
    <details class="reveal"><summary>Brauche ich den Alpha-Key bei jeder Anmeldung?</summary><p>Nein. Du löst ihn einmal bei der Kontoerstellung ein. Danach meldest du dich mit Spielername und Passwort an.</p></details>
    <details class="reveal"><summary>Muss ich etwas herunterladen?</summary><p>Nein. Conquer läuft direkt in einem modernen Browser und ist für Touch, Hoch- und Querformat ausgelegt.</p></details>
    <details class="reveal"><summary>Läuft die Welt weiter, wenn ich offline bin?</summary><p>Ja. Bauaufträge, Forschung und die gemeinsame Welt folgen serverseitigen Zeiten. Du musst nicht dauerhaft online bleiben.</p></details>
    <details class="reveal"><summary>Kann mein Fortschritt zurückgesetzt werden?</summary><p>In einer Alpha kann ein Reset nötig werden, wenn grundlegende Systeme oder Datenstrukturen geändert werden. Solche Schritte werden transparent angekündigt.</p></details>
  </div></section>
</main>

<footer class="lp-footer"><div><a class="lp-brand" href="#main-content"><img src="<?= $base ?>/assets/icons/conquer.svg" width="38" height="38" alt=""><span>CONQUER<small>Chroniken eines Königreichs</small></span></a><p>Ein handgezeichnetes Fantasy-Strategiespiel in aktiver Entwicklung.</p></div><nav aria-label="Fußnavigation"><a href="#spiel">Das Spiel</a><a href="#alpha">Alpha</a><a href="#fragen">FAQ</a><a href="mailto:sven@svenmanderscheid.lu">Kontakt</a></nav><small>© <?= date('Y') ?> Sven Manderscheid · Conquer ist ein Arbeitstitel.</small></footer>
</body></html>
