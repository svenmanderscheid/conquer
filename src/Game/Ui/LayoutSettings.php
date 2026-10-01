<?php
declare(strict_types=1);
namespace Conquer\Game\Ui;

use Conquer\Db\Connection;

final class LayoutSettings
{
    public const PROFILES = ['portrait','landscape','desktop'];
    public const ELEMENTS = ['resources','navigation','chat'];
    public const LIMITS = ['x'=>[-4096,4096], 'y'=>[-4096,4096], 'width'=>[25,300], 'height'=>[25,300]];
    public const ANCHORS = ['auto','top-left','top','top-right','left','center','right','bottom-left','bottom','bottom-right'];

    /** One allowlist for storage, preview and game. Never accept user-supplied selectors. */
    public static function catalog(): array
    {
        $items=[];
        $add=static function(string $id,string $label,string $selector,string $group='HUD',string $mode='scale',int $minWidth=44,int $minHeight=44,?string $screen=null)use(&$items):void {
            $items[$id]=compact('label','selector','group','mode','minWidth','minHeight','screen');
        };
        $add('resources','Ressourcenleiste','#resources','Gruppen','box',220,32);
        $add('navigation','Untere Navigation','#navigation','Gruppen','box',314,44);
        $add('chat','Chat-Vorschau','.world-chat:not(.is-open)','Gruppen','box',220,44);
        $add('profile','Profilgruppe','.hud-profile','Gruppen');
        $add('left_tools','Aufträge links','#hud-left-tools','Gruppen');
        $add('right_tools','Menü und Ereignisse','.hud-right-tools','Gruppen');
        foreach(['account'=>['Spielerprofil','#account-button'],'power'=>['Macht','.hud-power'],'vip'=>['VIP','#hud-vip-button'],'hunter'=>['Hunter-Stufe','#lord-talent-button'],'energy'=>['Aktionspunkte','#hud-energy'],'gems'=>['Kristalle','#hud-gems'],'bonuses'=>['Boni','#hud-bonuses'],'debuffs'=>['Debuffs','#hud-debuffs'],'build'=>['Bauen I','#hud-build'],'build_second'=>['Bauen II','#hud-build-second'],'research_job'=>['Forschungsauftrag','#hud-research'],'training'=>['Ausbildung','#hud-training'],'healing'=>['Heilung','#hud-healing'],'marches'=>['Truppenmärsche','#hud-marches'],'report'=>['Melden','#hud-report'],'menu'=>['Spielmenü','#hud-menu'],'rallies'=>['Allianz-Rallys','#hud-alliance-rallies'],'events'=>['Ereignisse','.hud-right-tools [data-id="events"]'],'objective'=>['Aufgabenhinweis','#hud-objective']] as $id=>[$label,$selector])$add($id,$label,$selector);
        foreach(['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'] as $id=>$label)$add('resource_'.$id,$label,'#resources [data-id="'.$id.'"]','Einzelne Rohstoffe');
        foreach(['quests'=>'Aufgaben','inventory'=>'Inventar','reports'=>'Post','chat'=>'Chat','shop'=>'Shop','alliance'=>'Allianz'] as $id=>$label)$add('nav_'.$id,$label,'#navigation [data-id="'.$id.'"]','Einzelne Navigation');
        $add('nav_scene','Dorf / Welt','#navigation .hud-scene-switch','Einzelne Navigation');
        foreach(['map_search'=>['Kartensuche','.map-overlay-search-toggle'],'map_coordinates'=>['Koordinaten','.map-overlay-coordinate-toggle'],'march_list'=>['Marschliste','.world-march-hud'],'march_detail'=>['Marschdetails','.world-march-card']] as $id=>[$label,$selector])$add($id,$label,$selector,'Weltkarte');
        foreach(['search_panel'=>['Suchfenster','#atlas-search-panel'],'coordinates_panel'=>['Koordinatenfenster','#atlas-navigation-panel']] as $id=>[$label,$selector])$add($id,$label,$selector,'Weltkarte','box',260,160);
        $panels=['profile'=>'Profil','quests'=>'Aufgaben','army'=>'Ausbildung / Hospital','research'=>'Forschung','inventory'=>'Inventar','treasures'=>'Schatzkammer','mastery'=>'Talente','market'=>'Shop','defense'=>'Verteidigung','land'=>'Länder','dungeons'=>'Dungeons','expeditions'=>'Feldzüge','community'=>'Gemeinschaft','events'=>'Ereignisse','rankings'=>'Rangliste','arena'=>'Arena','settings'=>'Einstellungen','worlds'=>'Welten','account'=>'Konto','help'=>'Hilfe','alliance'=>'Allianz','reports'=>'Post'];
        foreach($panels as $id=>$label)$add('panel_'.$id,$label,'#panel-dialog[open]','Spielfenster','box',280,220,$id);
        $add('dialog','Zusätzliche Dialoge','#game-dialog[open]','Spielfenster','box',280,180);
        $add('chat_window','Geöffneter Chat','.world-chat-window:not([hidden])','Spielfenster','box',280,220);
        $add('effects_window','Aktive Effekte','#active-effects-drawer:not([hidden])','Spielfenster','box',240,160);
        return $items;
    }

    public static function defaults(): array
    {
        return array_fill_keys(self::PROFILES, array_fill_keys(array_keys(self::catalog()), null));
    }

    public static function validate(mixed $value): array
    {
        if (!is_array($value) || array_diff(array_keys($value),self::PROFILES)) throw new \InvalidArgumentException('Ungültige Bildschirmformate.');
        $result=self::defaults();
        foreach ($value as $profile=>$elements) {
            if (!is_array($elements) || array_diff(array_keys($elements),array_keys(self::catalog()))) throw new \InvalidArgumentException('Ungültige Layoutbereiche.');
            foreach ($elements as $element=>$settings) {
                if ($settings===null) continue;
                if (!is_array($settings) || array_diff(array_keys($settings),[...array_keys(self::LIMITS),'anchor'])) throw new \InvalidArgumentException('Ungültige Layouteinstellungen.');
                if(array_key_exists('anchor',$settings)&&!in_array($settings['anchor'],self::ANCHORS,true))throw new \InvalidArgumentException('Ungültige Verankerung.');
                foreach (self::LIMITS as $field=>[$min,$max]) {
                    $number=$settings[$field]??null;
                    if (!is_int($number) || $number<$min || $number>$max) throw new \InvalidArgumentException('Layoutwert außerhalb des erlaubten Bereichs: '.$field);
                }
                $result[$profile][$element]=$settings;
            }
        }
        return $result;
    }

    public static function read(bool $lock=false): array
    {
        try {
            $row=Connection::getInstance()->query('SELECT revision,settings_json FROM ui_layout_settings WHERE id=1'.($lock?' FOR UPDATE':''))->fetch();
        } catch (\PDOException $e) {
            // Older deployments keep their existing HUD until the additive migration runs.
            if (($e->errorInfo[1]??null)!==1146 || $lock) throw $e;
            return ['revision'=>0,'profiles'=>self::defaults(),'available'=>false];
        }
        if (!$row) throw new \RuntimeException('Layout-Speicher ist nicht initialisiert.');
        return ['revision'=>(int)$row['revision'],'profiles'=>self::validate(json_decode($row['settings_json'],true,32,JSON_THROW_ON_ERROR)),'available'=>true];
    }

    /** Inside the shared admin transaction: role, CSRF, receipt and audit are handled there. */
    public static function save(Connection $db,array $input): array
    {
        $raw=$input['layout']??null;
        if (!is_string($raw) || strlen($raw)>50000) throw new \InvalidArgumentException('Ungültiges Layout.');
        try {$profiles=self::validate(json_decode($raw,true,32,JSON_THROW_ON_ERROR));}
        catch (\JsonException $e) {throw new \InvalidArgumentException('Das Layout konnte nicht gelesen werden.');}
        $before=self::read(true);
        if (!isset($input['revision']) || (string)$input['revision']!==(string)$before['revision']) throw new \DomainException('Das Layout wurde inzwischen geändert. Bitte neu laden und deine Änderungen erneut prüfen.');
        $revision=$before['revision']+1;
        $db->execute('UPDATE ui_layout_settings SET settings_json=?,revision=? WHERE id=1',[json_encode($profiles,JSON_THROW_ON_ERROR),$revision]);
        return ['target_type'=>'ui_layout','target_id'=>1,'before'=>$before,'after'=>['revision'=>$revision,'profiles'=>$profiles], 'message'=>'Layout für alle Spieler gespeichert. Es wird beim nächsten Laden des Spiels angewendet.'];
    }
}
