<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\March\BattleReportService;

final class BattleHandler
{
    private static function session(): array
    {
        $session = Session::current();
        if (!$session) Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        header('Cache-Control: no-store');
        return $session;
    }

    public static function reports(array $params): void
    {
        $session = self::session();
        $page = max(1,(int)($_GET['page'] ?? 1));
        Response::ok(['reports'=>BattleReportService::list((int)$session['player_id'],$page),'page'=>$page]);
    }

    public static function report(array $params): void
    {
        $session = self::session();
        $row = BattleReportService::get((int)$session['player_id'],(int)($params['id'] ?? 0));
        if (!$row) Response::error(404,'NOT_FOUND','Kampfbericht nicht gefunden.');
        $row['data'] = $row['details'];
        Response::ok(['report'=>$row]);
    }

    public static function delete(array $params): void
    {
        $session = self::session();
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals((string)$session['csrf_token'],$csrf)) Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        if (!BattleReportService::delete((int)$session['player_id'],(int)($params['id'] ?? 0))) Response::error(404,'NOT_FOUND','Kampfbericht nicht gefunden.');
        Response::ok(['deleted'=>true]);
    }
}
