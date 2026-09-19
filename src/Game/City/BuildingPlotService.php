<?php
declare(strict_types=1);
namespace Conquer\Game\City;
use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;

/** Retired building-plot feature: settle and credit legacy rows without exposing new actions. */
final class BuildingPlotService
{
 public static function rows(int $city):array {return Connection::getInstance()->query('SELECT * FROM city_building_plots WHERE city_id=? ORDER BY plot_id',[$city])->fetchAll();}
 /** Legacy work is deliberately hidden from the public building queues. */
 public static function queue(int $city):array {return [];}
 /** Legacy work must never reserve a normal building slot. */
 public static function busy(int $city):int {return 0;}
 public static function trainingBonus(int $city):float {return .05*(int)Connection::getInstance()->query("SELECT COALESCE(SUM(level),0) FROM city_building_plots WHERE city_id=? AND building_code='barrack'",[$city])->fetchColumn();}
 /** Settle the old and new production intervals before advancing a completed plot. */
 public static function complete(int $city,array $buildings):void {
  $db=Connection::getInstance();
  if(!$db->query('SELECT 1 FROM city_building_plots WHERE city_id=? AND finishes_at<=UTC_TIMESTAMP() LIMIT 1',[$city])->fetchColumn())return;
  $run=static function()use($db,$city,$buildings){
   $row=$db->query('SELECT * FROM cities WHERE id=? FOR UPDATE',[$city])->fetch();
   ResourceTick::persist($row,$buildings);
   $db->execute('UPDATE city_building_plots SET level=level_to,level_to=NULL,started_at=NULL,finishes_at=NULL WHERE city_id=? AND finishes_at<=UTC_TIMESTAMP()',[$city]);
  };
  if($db->getPdo()->inTransaction())$run();else $db->transaction($run);
 }
 public static function production(int $city,int $from,int $to,array $bonuses,float $speed):array {
  $gains=['food'=>0.0,'lumber'=>0.0,'stone'=>0.0,'gold'=>0.0];
  foreach(self::rows($city) as $row){
   $code=$row['building_code'];$res=BuildingData::getProducedResource($code);if(!$res)continue;
   $finish=$row['finishes_at']?strtotime($row['finishes_at'].' UTC'):$to;
   $old=max(0,min($to,$finish)-$from);$new=$row['finishes_at']?max(0,$to-max($from,$finish)):0;
   $gains[$res]+=($old*BuildingData::getHourlyRate($code,(int)$row['level'],$bonuses)+$new*BuildingData::getHourlyRate($code,(int)$row['level_to'],$bonuses))*$speed/3600;
  }
  return $gains;
 }
 /** No building-plot data is exposed while this retired feature is disabled. */
 public static function snapshot(array $state):array {return [];}
 public static function start(int $player,int $id,string $code,int $expected):array {
  WorldContext::assertActionAvailable();
  throw new \DomainException('Zusätzliche Bauplätze sind deaktiviert.');
 }
}
