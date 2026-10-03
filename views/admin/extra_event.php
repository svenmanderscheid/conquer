<?php
declare(strict_types=1);
use Conquer\Game\Locale;
use Conquer\Game\Ui\ExtraEventButton;
$events=ExtraEventButton::all($selectedWorld);
$eventId=filter_var($_GET['event_id']??($events[0]['id']??0),FILTER_VALIDATE_INT,['options'=>['min_range'=>0]])?:0;
$extra=[];foreach($events as $event)if($event['id']===$eventId)$extra=$event;
if(!$extra)$eventId=0;
$extra+=['enabled'=>false,'name_en'=>'','name_de'=>'','name_fr'=>'','description_en'=>'','description_de'=>'','description_fr'=>'','starts_at'=>gmdate('Y-m-d H:i:s'),'ends_at'=>gmdate('Y-m-d H:i:s',time()+604800),'icon'=>'events','target'=>'events'];
?>
<section class="card" id="extra-event-settings">
  <h2><?= Locale::html('extra_event.admin_title') ?></h2>
  <p><?= Locale::html('extra_event.hint') ?></p>
  <nav class="extra-event-admin-tabs" aria-label="<?= htmlspecialchars(Locale::t('extra_event.manage'),ENT_QUOTES) ?>">
    <?php foreach($events as $event): ?><a href="<?= htmlspecialchars(APP_BASE,ENT_QUOTES) ?>/admin/world?world_id=<?= $selectedWorld ?>&amp;event_id=<?= (int)$event['id'] ?>#extra-event-settings" <?= $eventId===$event['id']?'aria-current="page"':'' ?>><?= ah($event['name_'.Locale::current()]?:$event['name_en']) ?><?= !$event['enabled']?' · '.Locale::html('extra_event.disabled'):'' ?></a><?php endforeach ?>
    <a data-event-add href="<?= htmlspecialchars(APP_BASE,ENT_QUOTES) ?>/admin/world?world_id=<?= $selectedWorld ?>&amp;event_id=0#extra-event-settings"><?= Locale::html('extra_event.add') ?></a>
  </nav>
  <?php adminForm('extra-event-save',$selectedWorld); ?>
  <input type="hidden" name="event_id" value="<?= $eventId ?>">
  <label class="check"><input type="checkbox" name="enabled" value="1" <?= $extra['enabled']?'checked':'' ?>> <?= Locale::html('extra_event.enabled') ?></label>
  <div class="fields">
    <?php foreach(['en','de','fr'] as $lang): ?>
    <label><?= Locale::html('extra_event.name_'.$lang) ?><input name="name_<?= $lang ?>" value="<?= ah($extra['name_'.$lang]) ?>" maxlength="40" <?= $lang==='en'?'required':'' ?>></label>
    <?php endforeach ?>
    <?php foreach(['en','de','fr'] as $lang): ?><label><?= Locale::html('extra_event.description_'.$lang) ?><textarea name="description_<?= $lang ?>" maxlength="2000" rows="4"><?= ah($extra['description_'.$lang]) ?></textarea></label><?php endforeach ?>
    <label><?= Locale::html('extra_event.starts') ?><input type="datetime-local" name="starts_at" value="<?= ah(str_replace(' ','T',substr($extra['starts_at'],0,16))) ?>" required></label>
    <label><?= Locale::html('extra_event.ends') ?><input type="datetime-local" name="ends_at" value="<?= ah(str_replace(' ','T',substr($extra['ends_at'],0,16))) ?>" required></label>
    <label><?= Locale::html('extra_event.icon') ?><select name="icon"><?php foreach(ExtraEventButton::ICONS as $icon): ?><option value="<?= ah($icon) ?>" <?= $extra['icon']===$icon?'selected':'' ?>><?= Locale::html('nav.'.($icon==='world-map'?'world':($icon==='quests'?'quests':$icon))) ?></option><?php endforeach ?></select></label>
    <label><?= Locale::html('extra_event.target') ?><select name="target"><?php foreach(ExtraEventButton::TARGETS as $target): ?><option value="<?= ah($target) ?>" <?= $extra['target']===$target?'selected':'' ?>><?= Locale::html('nav.'.$target) ?></option><?php endforeach ?></select></label>
  </div>
  <div class="extra-event-preview"><img src="<?= htmlspecialchars(APP_BASE,ENT_QUOTES) ?>/assets/art/menu-icons/<?= ah($extra['icon']) ?>.png" width="44" height="44" alt=""><strong><?= ah($extra['name_en']) ?></strong></div>
  <?php adminSubmit(Locale::t('extra_event.save')); ?>
</section>
