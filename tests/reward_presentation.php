<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\Rewards\RewardPresentation;
use Conquer\Game\Treasure\TreasureData;

function presentationCheck(bool $valid, string $label): void {
    if (!$valid) throw new RuntimeException($label);
    echo "PASS $label\n";
}

$code = 60100001;
$fragment = RewardPresentation::fragment($code);
$relic = RewardPresentation::relic($code);
presentationCheck($relic['type'] === 'relic' && $fragment['type'] === 'fragment', 'Whole relic and fragment metadata have distinct types');
presentationCheck($relic['treasure_code'] === $code && $relic['icon'] === $fragment['icon'] && $relic['name'] === $fragment['name'], 'Both reward types retain the selected relic identity');
$details = ['relics'=>[$code=>2,60100002=>0,99999999=>8], 'fragments'=>[$code=>7], 'attacker_damage'=>731];
$report = RewardPresentation::report($details);
presentationCheck(count($report['relic_rewards']) === 1 && $report['relic_rewards'][0]['count'] === 2 && $report['relic_rewards'][0]['type'] === 'relic', 'Reports show only confirmed positive whole-relic rewards');
presentationCheck($report['fragment_rewards'][0]['count'] === 7 && $report['fragment_rewards'][0]['type'] === 'fragment', 'Fragments remain separate when the same relic also drops whole');
presentationCheck($report['relics'] === $details['relics'] && $report['fragments'] === $details['fragments'] && $report['attacker_damage'] === 731, 'Display metadata does not change payout or combat values');
presentationCheck(RewardPresentation::report($report) === $report, 'Refreshing a report does not duplicate relic rewards');
presentationCheck(RewardPresentation::relic(99999999) === [] && RewardPresentation::relic(TreasureData::RETIRED_CODES[0]) === [], 'Unknown and retired relics have no reward presentation');
