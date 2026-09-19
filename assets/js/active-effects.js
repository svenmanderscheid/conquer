(() => {
    'use strict';
    window.ConquerActiveEffects = ({base, esc, date, now, getState}) => {
        const labels = {
            construction:'Baugeschwindigkeit', construction_speed:'Baugeschwindigkeit',
            research:'Forschungsgeschwindigkeit', research_speed:'Forschungsgeschwindigkeit',
            training_speed:'Ausbildungsgeschwindigkeit', troop_training:'Ausbildungsgeschwindigkeit',
            resource_production:'Rohstoffproduktion', food_production:'Nahrungsproduktion',
            lumber_production:'Holzproduktion', wood_production:'Holzproduktion', stone_production:'Steinproduktion', gold_production:'Goldproduktion',
            gathering:'Sammelgeschwindigkeit', gathering_speed:'Sammelgeschwindigkeit', march_speed:'Marschgeschwindigkeit',
            troops_attack:'Truppenangriff', troops_atk:'Truppenangriff', attack_bonus:'Truppenangriff',
            troops_defense:'Truppenverteidigung', troops_def:'Truppenverteidigung', defense_bonus:'Truppenverteidigung',
            troops_hp:'Truppen-Lebenspunkte', carry:'Traglast', troops_storage:'Traglast',
            march_capacity:'Marschkapazität', march_size:'Marschkapazität', monster_attack:'Angriff gegen Monster', vs_monster_attack:'Angriff gegen Monster',
        };
        const grades = {normal:'Normal', common:'Gewöhnlich', magic:'Magisch', rare:'Selten', epic:'Episch', legendary:'Legendär'};
        const buttons = {bonus:document.getElementById('hud-bonuses'), debuff:document.getElementById('hud-debuffs')};
        const drawer = document.getElementById('active-effects-drawer');
        const heading = drawer.querySelector('h2');
        const list = drawer.querySelector('.active-effects-list');
        const clock = milliseconds => {
            const total = Math.max(0, Math.ceil(milliseconds / 1000));
            return [Math.floor(total / 3600), Math.floor(total % 3600 / 60), total % 60].map(n => String(n).padStart(2, '0')).join(':');
        };
        const live = () => (getState()?.active_effects || []).filter(effect => date(effect.expires_at) > now());
        const title = kind => kind === 'debuff' ? 'Aktive Debuffs' : 'Aktive Boni';
        function update() {
            const effects = live();
            for (const [kind, button] of Object.entries(buttons)) {
                const count = effects.filter(effect => effect.kind === kind).length;
                button.hidden = count === 0;
                button.setAttribute('aria-label', `${title(kind)} anzeigen (${count})`);
                button.title = `${title(kind)} (${count})`;
            }
            if (drawer.hidden) return;
            const kind = drawer.dataset.kind;
            const rows = effects.filter(effect => effect.kind === kind);
            if (!rows.length) { close(); return; }
            const signature = JSON.stringify(rows);
            if (list.dataset.signature !== signature) {
                list.dataset.signature = signature;
                const scroll = list.scrollTop;
                list.innerHTML = rows.map(effect => {
                    const grade = Object.hasOwn(grades, effect.grade) ? effect.grade : 'normal';
                    const source = effect.source === 'charm' ? `Charm · ${grades[grade]}` : effect.kind === 'debuff' ? 'Debuff' : 'Verstärkung';
                    const value = Number(effect.bonus_pct);
                    return `<li class="active-effect is-${esc(effect.kind)}" data-effect-id="${esc(effect.id)}">
                        <span class="active-effect-art grade-${grade}" aria-hidden="true"><img src="${base}/assets/art/${effect.source === 'charm' ? 'map/crystal' : 'items/speedup'}.svg" alt=""></span>
                        <div class="active-effect-copy"><small>${esc(source)}</small><strong>${esc(labels[effect.stat_category] || 'Aktiver Effekt')} <b>${value > 0 ? '+' : ''}${esc(value.toLocaleString('de-DE', {maximumFractionDigits:2}))} %</b></strong>
                        <div class="active-effect-duration"><span class="active-effect-track" aria-hidden="true"><span></span></span><time aria-label="Verbleibende Dauer"></time></div></div>
                    </li>`;
                }).join('');
                list.scrollTop = scroll;
            }
            [...list.querySelectorAll('.active-effect')].forEach((row, index) => {
                const effect = rows[index], remaining = date(effect.expires_at) - now();
                const total = effect.activated_at ? date(effect.expires_at) - date(effect.activated_at) : NaN;
                row.querySelector('time').textContent = clock(remaining);
                row.querySelector('time').setAttribute('aria-label', `Verbleibende Dauer: ${clock(remaining)}`);
                row.querySelector('.active-effect-track').hidden = !Number.isFinite(total) || total <= 0;
                row.querySelector('.active-effect-track > span').style.width = `${Math.max(0, Math.min(100, remaining / total * 100))}%`;
            });
        }
        function close({restoreFocus = false} = {}) {
            if (drawer.hidden) return;
            const trigger = buttons[drawer.dataset.kind];
            drawer.hidden = true;
            delete drawer.dataset.kind;
            delete list.dataset.signature;
            Object.values(buttons).forEach(button => button.setAttribute('aria-expanded', 'false'));
            if (restoreFocus && trigger && !trigger.hidden) trigger.focus();
        }
        function open(kind) {
            if (!drawer.hidden && drawer.dataset.kind === kind) { close({restoreFocus:true}); return; }
            drawer.dataset.kind = kind;
            heading.textContent = title(kind);
            list.setAttribute('aria-label', title(kind));
            delete list.dataset.signature;
            drawer.hidden = false;
            Object.entries(buttons).forEach(([buttonKind, button]) => button.setAttribute('aria-expanded', String(buttonKind === kind)));
            update();
        }
        Object.entries(buttons).forEach(([kind, button]) => button.addEventListener('click', () => open(kind)));
        document.addEventListener('pointerdown', event => {
            if (!drawer.hidden && !event.target.closest('.hud-effects')) close();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !drawer.hidden) { event.preventDefault(); close({restoreFocus:true}); }
        });
        return {update};
    };
})();
