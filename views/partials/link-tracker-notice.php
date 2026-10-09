<?php declare(strict_types=1); ?>
<?php if (\Conquer\Analytics\LinkTracker::enabled()): ?>
<details class="link-tracker-notice">
  <summary data-i18n="links.privacy_title"><?= \Conquer\Game\Locale::html('links.privacy_title') ?></summary>
  <p data-i18n="legal.privacy.link_stats"><?= \Conquer\Game\Locale::html('legal.privacy.link_stats') ?></p>
</details>
<?php endif ?>
