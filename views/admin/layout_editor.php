<?php
$layout=\Conquer\Game\Ui\LayoutSettings::read();
$catalog=\Conquer\Game\Ui\LayoutSettings::catalog();
$layout['catalog']=$catalog;$layout['limits']=\Conquer\Game\Ui\LayoutSettings::LIMITS;$layout['anchors']=\Conquer\Game\Ui\LayoutSettings::ANCHORS;
?>
<section id="layout-editor" data-endpoint="<?= ah(APP_BASE) ?>/admin/layout-data" data-preview="<?= ah(APP_BASE) ?>/city?layout_preview=1#city" data-editable="<?= $canEdit&&$layout['available']?'1':'0' ?>">
    <p>Bearbeite die echte Spieloberfläche. Zum Anzeigen ist zusätzlich ein angemeldetes Spielkonto nötig. Gespeicherte Änderungen gelten für <strong>alle Spieler und Welten</strong> beim nächsten Laden des Spiels.</p>
    <?php if(!$layout['available']): ?><p class="notice error">Der Layout-Speicher ist noch nicht eingerichtet. Die Servermigration für den Layout-Editor fehlt.</p><?php endif ?>
    <div class="layout-toolbar">
        <label>Bildschirmformat<select id="layout-profile"><option value="portrait">Handy · Hochformat</option><option value="landscape">Handy · Querformat</option><option value="desktop">Desktop</option></select></label>
        <label>Vorschaugröße<select id="layout-size"></select></label>
        <label>Breite<input id="layout-screen-width" type="number" min="320" max="3840" value="390"></label>
        <label>Höhe<input id="layout-screen-height" type="number" min="320" max="2160" value="844"></label>
        <button type="button" class="secondary" id="layout-custom-size">Größe anwenden</button>
        <label>Ansicht<select id="layout-screen"><option value="city">Dorf</option><option value="world">Weltkarte</option><?php foreach($catalog as $def):if(!$def['screen'])continue;?><option value="<?= ah($def['screen']) ?>"><?= ah($def['label']) ?></option><?php endforeach ?></select></label>
        <button type="button" class="secondary" id="layout-interact" aria-pressed="false">Spiel bedienen / anmelden</button>
        <button type="button" class="secondary" id="layout-reload">Vorschau neu laden</button>
    </div>
    <div class="layout-toolbar"><button type="button" class="secondary" id="layout-undo" disabled>↶ Rückgängig</button><button type="button" class="secondary" id="layout-redo" disabled>↷ Wiederholen</button><label>Raster<select id="layout-grid"><option value="1">Frei</option><option value="4">4 px</option><option value="8">8 px</option><option value="16">16 px</option></select></label><label>Format kopieren nach<select id="layout-copy-target"><option value="portrait">Hochformat</option><option value="landscape">Querformat</option><option value="desktop">Desktop</option></select></label><button type="button" class="secondary" id="layout-copy">Kopieren</button><button type="button" class="secondary" id="layout-export">Layout exportieren</button><button type="button" class="secondary" id="layout-import">Layout importieren</button><input id="layout-import-file" type="file" accept="application/json,.json" hidden></div>
    <div class="layout-toolbar"><button type="button" class="secondary" id="layout-multi" aria-pressed="false">Mehrfachauswahl</button><button type="button" class="secondary" id="layout-deselect">Auswahl aufheben (Esc)</button></div>
    <div class="layout-workspace">
        <div class="layout-viewport" id="layout-viewport"><div id="layout-frame-space"><iframe id="layout-frame" title="Union of Kingdoms – Layoutvorschau" src="<?= ah(APP_BASE) ?>/city?layout_preview=1#city"></iframe></div></div>
        <div class="layout-controls">
            <label>Bereich suchen<input id="layout-search" type="search" placeholder="z. B. Profil, Forschung …"></label>
            <label>Bereich<select id="layout-element"><option value="">Keine Auswahl</option><?php $groups=[];foreach($catalog as $key=>$def)$groups[$def['group']][$key]=$def;foreach($groups as $group=>$entries): ?><optgroup label="<?= ah($group) ?>"><?php foreach($entries as $key=>$def): ?><option value="<?= ah($key) ?>" <?= $key==='navigation'?'selected':'' ?>><?= ah($def['label']) ?></option><?php endforeach ?></optgroup><?php endforeach ?></select></label>
            <p id="layout-selection-status" role="status"></p>
            <p id="layout-default-note">Standardlayout aktiv. Eine Änderung erzeugt eine eigene Einstellung für dieses Format.</p>
            <fieldset id="layout-fields" <?= !$canEdit||!$layout['available']?'disabled':'' ?>>
                <label>Verankerung<select id="layout-anchor"><?php foreach(['auto'=>'Bestehende Position','top-left'=>'Oben links','top'=>'Oben mittig','top-right'=>'Oben rechts','left'=>'Links mittig','center'=>'Bildschirmmitte','right'=>'Rechts mittig','bottom-left'=>'Unten links','bottom'=>'Unten mittig','bottom-right'=>'Unten rechts'] as $key=>$label): ?><option value="<?= $key ?>"><?= $label ?></option><?php endforeach ?></select></label>
                <?php foreach(['x'=>['Links / rechts (px)',-4096,4096,0],'y'=>['Oben / unten (px)',-4096,4096,0],'width'=>['Breite (%)',25,300,100],'height'=>['Höhe (%)',25,300,100]] as $key=>[$label,$min,$max,$value]): ?>
                <label for="layout-<?= $key ?>"><?= $label ?></label><div class="layout-input-pair"><input id="layout-<?= $key ?>" data-layout-field="<?= $key ?>" type="range" min="<?= $min ?>" max="<?= $max ?>" value="<?= $value ?>"><input aria-label="<?= $label ?> als Zahl" data-layout-number="<?= $key ?>" type="number" min="<?= $min ?>" max="<?= $max ?>" step="1" value="<?= $value ?>"></div>
                <?php endforeach ?>
                <button type="button" class="secondary" id="layout-reset-element">Bereich zurücksetzen</button>
                <button type="button" class="secondary" id="layout-reset-profile">Dieses Format zurücksetzen</button>
            </fieldset>
            <p>Strg-/Cmd- oder Umschalt-Klick wählt mehrere Bereiche aus. Auf dem Handy „Mehrfachauswahl“ einschalten, Bereiche antippen und zum gemeinsamen Ziehen wieder ausschalten. Esc hebt die Auswahl auf. Strg/Cmd+Z macht rückgängig, Strg/Cmd+Umschalt+Z oder Strg+Y wiederholt. Bereich auswählen und ziehen. Die acht Anfasser ändern die Größe. Pfeiltasten verschieben um 1 px, mit Umschalt um 10 px. Gruppen bewegen ihre Unterelemente mit. Für einzelne Knöpfe den passenden Eintrag auswählen. Fenster lassen sich über „Ansicht“ öffnen.</p>
            <p id="layout-warnings" role="status"></p>
        </div>
    </div>
    <form id="layout-save-form">
        <input type="hidden" name="csrf_token" value="<?= ah($csrf) ?>">
        <label>Notiz zur Änderung<input name="reason" required minlength="3" maxlength="500" placeholder="z. B. mehr Platz für den Chat auf Handys" <?= !$canEdit?'disabled':'' ?>></label>
        <div class="layout-toolbar"><button type="submit" id="layout-save" disabled>Für alle Spieler speichern</button><button type="button" class="secondary" id="layout-discard" disabled>Änderungen verwerfen</button></div>
    </form>
    <p id="layout-status" role="status">Vorschau lädt. Falls du dich dort anmeldest, anschließend „Vorschau neu laden“ wählen.</p>
</section>
<script type="application/json" id="layout-editor-data"><?= json_encode($layout,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="<?= ah(APP_BASE) ?>/assets/js/layout-editor.js?v=<?= filemtime(ROOT_DIR.'/assets/js/layout-editor.js') ?>" defer></script>
