<?php
declare(strict_types=1);
namespace Conquer\Auth;
// Only an isolated test router loads this namespace replacement. Production never does.
function mail(string $to,string $subject,string $body,string $headers): bool
{
    if (!defined('CONQUER_TEST_MAIL_FILE') || !in_array(PHP_SAPI,['cli','cli-server'],true)) throw new \LogicException('Test mail sink is unavailable.');
    $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] = ($GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] ?? 0) + 1;
    if (($GLOBALS['CONQUER_TEST_MAIL_FAILURE'] ?? null) === 'throw') throw new \RuntimeException('Simulated account mail transport failure.');
    if (($GLOBALS['CONQUER_TEST_MAIL_FAILURE'] ?? null) === 'return_false') return false;
    return file_put_contents(CONQUER_TEST_MAIL_FILE,json_encode(compact('to','subject','body','headers'),JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX)!==false;
}
