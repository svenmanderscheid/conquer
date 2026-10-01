<?php declare(strict_types=1); ?>
<nav class="lp-guide-nav" aria-label="Explore the game">
  <a href="#gallery">Discover the world</a><a href="#game-goal">The goal</a><a href="#troops">Troops</a><a href="#items">Items &amp; relics</a><a href="#progression">Your journey</a><a href="#development">Development</a>
</nav>
<section id="gallery" class="lp-guide-section lp-guide-alt" aria-labelledby="gallery-title">
  <div class="lp-guide-heading"><p class="lp-section-kicker lp-brand-slogan">A new Era begins</p><h2 id="gallery-title">Discover your next kingdom.</h2><p>A growing city, an army of your own and monsters to face together. Explore the latest artwork from Union of Kingdoms.</p></div>
  <div class="lp-campaign-grid">
    <?php foreach ([
      '01-build' => ['A kingdom worth building', 'A sunlit fantasy city with blue-roofed towers', '#game-goal', 'Explore the kingdom'],
      '02-army' => ['Three troop types. Your strategy.', 'Guardian, fire archer and shadow rider together', '#troops', 'Meet the troops'],
      '03-infantry' => ['Stand strong.', 'A blue-shielded infantry guardian defending the kingdom', '#troops', 'Discover infantry'],
      '04-archers' => ['Make every shot count.', 'A red-hooded archer with a flaming arrow', '#troops', 'Discover archers'],
      '05-cavalry' => ['Ride with purpose.', 'A shadow rider on a mount with violet antlers', '#troops', 'Discover cavalry'],
      '06-relics' => ['Equip your ambition.', 'A harvest horn, woodland axe and army banner in a treasury', '#items', 'Explore items and relics'],
      '07-alliance' => ['Stronger together.', 'Three allies planning their next adventure over a map', '#game-goal', 'Find your shared goal'],
      '08-monsters' => ['Face greater threats together', 'Grumwald in his current storybook design, with a guardian and fire archer', '#bosses', 'Meet the rally monsters'],
      '09-conquest' => ['Build strength. Challenge rivals.', 'Rival kingdoms and armies facing each other across a valley', '#progression', 'Explore your journey'],
      '10-alpha' => ['Be part of the beginning.', 'A guardian and archer welcoming players through the city gates', '#zugang', 'Register for closed alpha'],
    ] as $art => [$title, $description, $target, $link]): ?>
    <figure class="lp-campaign-card">
      <?php $artVersion = 'v4'; ?>
      <a href="<?= $target ?>"><img src="<?= $base ?>/assets/marketing/<?= $art === '01-build' ? 'kingdom-social-en-v5' : 'campaign-' . $art . '-' . $artVersion ?>.webp" width="1122" height="1402" loading="lazy" decoding="async" alt="<?= $description ?>"></a>
      <figcaption><h3><?= $title ?></h3><a href="<?= $target ?>"><?= $link ?> <span aria-hidden="true">→</span></a></figcaption>
    </figure>
    <?php endforeach ?>
  </div>
  <p class="lp-guide-note">Promotional illustrations · Register for closed alpha. These images are artwork, not gameplay screenshots.</p>
</section>
<section id="game-goal" class="lp-guide-section" aria-labelledby="goal-title">
  <div class="lp-guide-heading"><p class="lp-section-kicker lp-brand-slogan">A new Era begins</p><h2 id="goal-title">A kingdom built for the long run.</h2>
  <p>Union of Kingdoms is a fantasy strategy game about growing your city, building an army and finding your place in a shared world. The long-term vision brings together lasting progression, player-versus-player conquest and cooperative monster battles.</p></div>
  <div class="lp-guide-grid">
    <article class="lp-guide-card"><h3>Build lasting strength</h3><p>Develop production, storage, buildings and research. Train stronger troops and shape your equipment around your next objective. Each improvement supports the next stage of your kingdom.</p></article>
    <article class="lp-guide-card"><h3>Compete for the kingdom</h3><p>Scout rivals, prepare your army and coordinate with allies. Player-versus-player combat is part of the alpha; fighting for control of the kingdom is the long-term conquest goal. The final conquest rules are still being developed.</p></article>
    <article class="lp-guide-card"><h3>Take on greater threats</h3><p>Join alliance rallies against powerful monsters. The direction is an expanding series of tougher enemies and exciting rewards, giving your alliance new reasons to prepare, improve and fight together.</p></article>
  </div>
  <p class="lp-guide-note">The intended progression is ongoing, rather than a repeating seasonal restart. During development, alpha resets and balance changes are still possible.</p>
</section>
<section id="troops" class="lp-guide-section lp-guide-alt" aria-labelledby="troops-title">
  <div class="lp-guide-heading"><p class="lp-section-kicker">The current army</p><h2 id="troops-title">Three troop types. Five tiers each.</h2><p>Infantry, archers and cavalry each progress through five tiers. Every type trains in its own building. Upgrade your academy and complete troop research to unlock T2–T5.</p></div>
  <div class="lp-tier-picker" role="group" aria-label="Preview troop tier" hidden>
    <?php for ($tier = 1; $tier <= 5; $tier++): ?>
    <button type="button" data-preview-tier="<?= $tier ?>" aria-pressed="<?= $tier === 5 ? 'true' : 'false' ?>">T<?= $tier ?></button>
    <?php endfor ?>
  </div>
  <p class="lp-tier-status" data-tier-status role="status">Troop artwork · Tier 5 of 5</p>
  <div class="lp-guide-grid lp-troop-grid">
    <article class="lp-guide-card"><img src="<?= $base ?>/assets/art/characters/fantasy-troops-v2/guardian-t5-ui.webp" width="512" height="512" loading="lazy" alt="The current infantry artwork: a guardian with a blue shield and hammer"><h3>Infantry</h3><p class="lp-guide-label">Barracks · T1–T5</p><p>Build the core of your army. Compare attack, defence, health and carrying capacity before choosing the troops for a march. The new guardian artwork gives infantry its shield-bearing identity.</p></article>
    <article class="lp-guide-card"><img src="<?= $base ?>/assets/art/characters/fantasy-troops-v2/fire-archer-t5-ui.webp" width="512" height="512" loading="lazy" alt="The current archer artwork: a white-haired archer with a red hood and flaming arrow"><h3>Archers</h3><p class="lp-guide-label">Archery range · T1–T5</p><p>Develop your ranged troops alongside the rest of the army. The new fire-archer artwork brings a red hood, a crescent bow and a blazing arrow to the battlefield.</p></article>
    <article class="lp-guide-card"><img src="<?= $base ?>/assets/art/characters/fantasy-troops-v2/shadow-rider-t5-ui.webp" width="512" height="512" loading="lazy" alt="The current cavalry artwork: a violet shadow rider on an antlered mount"><h3>Cavalry</h3><p class="lp-guide-label">Stable · T1–T5</p><p>Bring mounted troops into your formations. Consider march speed, carrying capacity and the strength needed at the destination. The shadow rider is the new cavalry appearance.</p></article>
  </div>
  <p class="lp-guide-note">Train, unlock higher tiers and promote eligible troops. Treat wounded units in the hospital and use suitable speedups on active orders.</p>
</section>
<section id="items" class="lp-guide-section" aria-labelledby="items-title">
  <div class="lp-guide-heading"><p class="lp-section-kicker">Prepare for what comes next</p><h2 id="items-title">Make your inventory part of the strategy.</h2><p>Items support the choices you make: building, researching, training, healing, gathering and preparing for battle. Check each item's effect and requirements before using it.</p></div>
  <div class="lp-guide-grid">
    <article class="lp-guide-card"><h3>Resources &amp; speedups</h3><p>Resource packs provide supplies when used. General and specialised speedups shorten eligible active orders. Keep your next construction, research or army goal in mind when deciding what to spend.</p></article>
    <article class="lp-guide-card"><h3>Boosts &amp; useful items</h3><p>Temporary boosts support specific activities. Open chests, collect fragments and use teleporters when their requirements are met. Check the in-game details for each item’s effect and availability.</p></article>
    <article class="lp-guide-card"><h3>Relics &amp; loadouts</h3><p>Collect fragments, exchange matching-rarity universal fragments and develop the relic effects that fit your objective. The treasury offers up to six equipment slots, unlocked as it grows, and five saved loadouts. Equipped relics determine your active bonuses.</p></article>
  </div>
  <div class="lp-relic-strip" aria-label="Examples of the updated relic artwork">
    <figure><img src="<?= $base ?>/assets/marketing/current-rel-001-kornhorn-der-ernte.webp" width="1024" height="1024" loading="lazy" alt="Harvest horn filled with grain and fruit"><figcaption>Harvest horn</figcaption></figure>
    <figure><img src="<?= $base ?>/assets/marketing/current-rel-006-axt-des-gruenhains.webp" width="1024" height="1024" loading="lazy" alt="A woodland axe relic"><figcaption>Woodland axe</figcaption></figure>
    <figure><img src="<?= $base ?>/assets/marketing/current-rel-020-banner-der-drei-heere.webp" width="1024" height="1024" loading="lazy" alt="Banner representing three armies"><figcaption>Three-army banner</figcaption></figure>
  </div>
  <p class="lp-guide-note">Discover the updated storybook relic artwork. The in-game catalogue shows each relic’s current effects, requirements and values.</p>
</section>
<section id="progression" class="lp-guide-section lp-guide-alt" aria-labelledby="progression-title">
  <div class="lp-guide-heading"><p class="lp-section-kicker">Your journey</p><h2 id="progression-title">From a small city to a powerful alliance.</h2><p>The central loop is simple: prepare, act, earn rewards and reinvest. The decisions become more demanding as your kingdom grows.</p></div>
  <ol class="lp-progression-list">
    <li><h3>Establish your economy</h3><p>Produce food, wood, stone and gold. Expand your storage and meet building prerequisites. A strong economy keeps construction, research and training moving.</p></li>
    <li><h3>Develop your city and army</h3><p>Upgrade the city centre and training buildings to unlock higher troop tiers. Research useful bonuses. Balance new recruits, promotions and hospital needs against your available resources.</p></li>
    <li><h3>Explore and learn the world</h3><p>Gather resources and choose suitable monster targets. Compare the army you can send with the risks, travel time and possible rewards. Read reports and use the results to improve the next march.</p></li>
    <li><h3>Find your allies</h3><p>Join an alliance, share information and help coordinate objectives. Your own progress gives you more ways to contribute to the group, while cooperation makes larger challenges possible.</p></li>
    <li><h3>Prepare for PvP and alliance PvE</h3><p>For rival players, scouting and timing matter. For rally monsters, assemble with your alliance and commit suitable forces. These are different risks and objectives, supported by the same economy and army.</p></li>
    <li><h3>Turn rewards into the next step</h3><p>Use earned supplies, useful items and relic progress to strengthen your kingdom. The long-term aim is to compete for kingdom control and keep facing more powerful monsters together as the game expands.</p></li>
  </ol>
</section>
<section id="bosses" class="lp-guide-section" aria-labelledby="bosses-title">
  <div class="lp-guide-heading"><p class="lp-section-kicker">Alliance PvE</p><h2 id="bosses-title">Meet the regional rally monsters.</h2><p>Grumwald, Frostgrimm, Sandmaul and Glutramm are integrated rally enemies, with ten levels per regional monster. Assemble your alliance and prepare together. Battle rewards depend on the target and the current reward tables.</p></div>
  <div class="lp-boss-grid">
    <?php foreach (['grumwald' => ['Grumwald', 'Emerald Forest'], 'frostgrimm' => ['Frostgrimm', 'Frostlands'], 'sandmaul' => ['Sandmaul', 'Sun Dunes'], 'glutramm' => ['Glutramm', 'Ashlands']] as $boss => [$name, $region]): ?>
    <article class="lp-guide-card"><img src="<?= $base ?>/assets/marketing/current-<?= $boss ?>.webp" width="512" height="512" loading="lazy" alt="<?= $name ?>, a regional rally monster"><h3><?= $name ?></h3><p><?= $region ?></p></article>
    <?php endforeach ?>
  </div>
</section>
<section id="development" class="lp-guide-section lp-guide-alt" aria-labelledby="development-title">
  <div class="lp-guide-heading"><p class="lp-section-kicker">The road ahead · September 2026</p><h2 id="development-title">The road to the full game.</h2><p>The alpha is a working foundation, not the finished destination. Features, visuals, balance and progression are still evolving.</p></div>
  <div class="lp-guide-grid">
    <article class="lp-guide-card"><p class="lp-guide-label">Current alpha foundation</p><h3>Build, train and play together</h3><p>City development, research, three troop types through T5, inventory, relic loadouts, gathering, player combat and alliance rallies are implemented in the current project. Availability follows world settings and alpha access.</p></article>
    <article class="lp-guide-card"><p class="lp-guide-label">Development direction</p><h3>Deeper progression and conquest</h3><p>Continue refining the everyday game loop, combat balance, rewards and alliance coordination. Develop kingdom conquest and expand the ladder of cooperative monster challenges. Exact conquest rules and future reward tiers are not final.</p></article>
    <article class="lp-guide-card"><p class="lp-guide-label">Later platform step</p><h3>One game across devices</h3><p>The browser is the current entry point. iOS and Android apps are planned after gameplay, navigation and server interfaces are stable. No App Store, Google Play or full-release date is announced.</p></article>
  </div>
  <details class="lp-guide-faq"><summary>Is this a seasonal game with regular restarts?</summary><p>The intended model is lasting kingdom development, PvP competition and continuing alliance PvE. Routine seasonal restarts are not the design goal. Alpha testing may still require resets.</p></details>
  <details class="lp-guide-faq"><summary>Can I play now?</summary><p>Access is currently limited to the closed alpha. Join the waiting list below to register your interest. Signing up does not guarantee immediate access; new game accounts require an alpha key.</p></details>
  <details class="lp-guide-faq"><summary>Is the Star Crown story the endgame?</summary><p>The Star Crown is an earlier story concept, not a confirmed playable campaign or a fixed seasonal ending. The confirmed direction is ongoing development, kingdom conquest and stronger cooperative monster challenges.</p></details>
  <a class="lp-button lp-guide-cta" href="#zugang">Register for closed alpha <span aria-hidden="true">→</span></a>
</section>
