<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$db=\Conquer\Db\Connection::init(ROOT_DIR);$pdo=$db->getPdo();
$pdo->exec('START TRANSACTION READ ONLY');
try {
    $hasProof=(bool)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='oauth_accounts' AND COLUMN_NAME='identity_verified_at'")->fetchColumn();
    $proofCount=$hasProof?'SUM(o.identity_verified_at IS NULL)':'COUNT(*)';
    $rows=$db->query('SELECT o.provider,COUNT(*) AS links,SUM(p.email_verified_at IS NULL) AS local_email_unverified,SUM(p.is_banned=1) AS banned_players,'.$proofCount.' AS links_requiring_revalidation FROM oauth_accounts o JOIN players p ON p.id=o.player_id GROUP BY o.provider')->fetchAll();
    echo json_encode(['read_only'=>true,'identity_migration_applied'=>$hasProof,'providers'=>$rows,'note'=>'Historical provider verification claims were not stored. Counts are triage indicators, not proof that any link is malicious. No accounts, links, or sessions were changed.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}finally{$pdo->rollBack();}
