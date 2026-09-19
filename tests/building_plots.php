<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;use Conquer\Game\City\{BuildingPlotService as Plots,BuildingUpgrader,CityState};use Conquer\Game\World\WorldContext;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$n=0;
function checkp(bool $ok,string $label):void{global$n;if(!$ok)throw new RuntimeException($label);$n++;}
function rejectp(callable $f,string $label):void{try{$f();}catch(RuntimeException|DomainException){checkp(true,$label);return;}throw new RuntimeException($label);}
try{
 \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0081_building_plots.sql'));
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'Plots','plots@test.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,last_resource_update)VALUES(1,1,1,'Plots',30,40,100000,100000,100000,60000,UTC_TIMESTAMP())");
 foreach(CityState::BUILDING_CODES as$c)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,3)',[$c]);
 $db->execute("INSERT INTO city_building_plots(city_id,plot_id,building_code,level,level_to,started_at,finishes_at)VALUES(1,1,'lumber_camp',1,2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)),(1,2,'barrack',2,NULL,NULL,NULL)");
 WorldContext::bind(1);$state=CityState::loadForPlayer(1);$city=(int)$state['city']['id'];
 checkp(Plots::snapshot($state)===[]&&Plots::queue($city)===[]&&Plots::busy($city)===0,'retired plots are absent from public snapshot and builder queue');
 checkp(Plots::production($city,time()-3600,time(),[],1)['lumber']>0&&abs(Plots::trainingBonus($city)-.1)<.0001,'legacy plots retain production and barrack effects');
 $resources=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();$rows=$db->query('SELECT * FROM city_building_plots WHERE city_id=1 ORDER BY plot_id')->fetchAll();
 rejectp(fn()=>Plots::start(1,1,'lumber_camp',1),'legacy plot upgrade post is rejected');rejectp(fn()=>Plots::start(1,3,'farm',0),'new plot post is rejected');
 checkp($db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch()===$resources&&$db->query('SELECT * FROM city_building_plots WHERE city_id=1 ORDER BY plot_id')->fetchAll()===$rows,'rejected plot posts change neither resources nor stored plots');
 $db->execute("UPDATE city_buildings SET level=2 WHERE city_id=1 AND building_code='storage'");$state=CityState::loadForPlayer(1);BuildingUpgrader::start(1,'storage',$state['city'],$state['buildings']);
 checkp((int)$db->query('SELECT COUNT(*) FROM building_queue WHERE city_id=1 AND is_processed=0')->fetchColumn()===1,'legacy plot work does not occupy a normal builder');
 $db->execute("UPDATE city_building_plots SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=1 AND plot_id=1");$state=CityState::loadForPlayer(1);$legacy=$db->query('SELECT level,level_to,finishes_at FROM city_building_plots WHERE city_id=1 AND plot_id=1')->fetch();
 checkp((int)$legacy['level']===2&&$legacy['level_to']===null&&$legacy['finishes_at']===null,'legacy construction completes without being exposed');
 checkp(Plots::snapshot($state)===[]&&Plots::queue($city)===[]&&count(Plots::rows($city))===2,'settled legacy data remains stored but hidden');
 echo "$n building plot checks passed\n";
}catch(Throwable$e){fwrite(STDERR,$e."\n");exit(1);}finally{$fixture->close();}
