/* Shared presentation of server-provided upgrade prerequisites. */
(() => {
    'use strict';
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const text = (key, params = {}) => window.ConquerLocale.t('requirements.' + key, params);
    const fmt = value => window.ConquerLocale.formatNumber(value);
    const numbers = (current, required) => ({current:Math.max(0, Number(current) || 0), required:Math.max(0, Number(required) || 0)});
    const missingFirst = entries => entries.slice().sort((a, b) => Number(a.met) - Number(b.met));
    function meter(name, current, required) {
        const value = Math.min(current, required), percent = required > 0 ? value / required * 100 : 100;
        return `<span class="requirement-meter" role="progressbar" aria-label="${esc(name)}" aria-valuemin="0" aria-valuemax="${required || 1}" aria-valuenow="${required ? value : 1}" aria-valuetext="${esc(text('progress', {current:fmt(current), required:fmt(required)}))}"><span style="width:${percent}%"></span></span>`;
    }
    function levelCard({name, current, required, action, code, art, className = ''}) {
        ({current, required} = numbers(current, required));
        const met = current >= required, remaining = Math.max(0, required - current);
        const status = text(met ? 'met' : 'missing');
        const hint = text(met ? 'met' : remaining === 1 ? 'level_missing' : 'levels_missing', {count:fmt(remaining)});
        const destination = text(action === 'building' ? 'open_building' : 'open_research');
        return `<button type="button" class="research-requirement requirement-card ${met ? 'requirement-met' : 'requirement-missing'} ${esc(className)}" data-action="${esc(action)}" data-id="${esc(code)}" aria-label="${esc(text('level_label', {name, current:fmt(current), required:fmt(required), status, action:destination}))}">${art}<span class="research-requirement-copy"><span class="requirement-card-heading"><strong>${esc(name)}</strong><span class="requirement-status"><i aria-hidden="true">${met ? '✓' : '!'}</i>${esc(status)}</span></span><span class="requirement-levels"><span><small>${esc(text('current'))}</small><b>${esc(text('level', {level:fmt(current)}))}</b></span><i aria-hidden="true">→</i><span><small>${esc(text('required'))}</small><b>${esc(text('level', {level:fmt(required)}))}</b></span></span>${meter(name, current, required)}<span class="requirement-card-footer"><strong>${esc(hint)}</strong><span>${esc(destination)} <i aria-hidden="true">›</i></span></span></span></button>`;
    }
    function quantityFeedback(name, current, required) {
        ({current, required} = numbers(current, required));
        const met = current >= required;
        return `<span class="requirement-quantity-feedback">${meter(name, current, required)}<strong>${esc(text(met ? 'available' : 'quantity_missing', {count:fmt(Math.ceil(Math.max(0, required - current)))}))}</strong></span>`;
    }
    function overview(missing) {
        return `<div class="requirements-overview ${missing ? 'is-missing' : 'is-ready'}"><span aria-hidden="true">${missing ? '!' : '✓'}</span><strong>${esc(text(missing ? missing === 1 ? 'one_missing' : 'many_missing' : 'ready', {count:fmt(missing)}))}</strong></div>`;
    }
    window.ConquerRequirements = {levelCard, quantityFeedback, overview, missingFirst};
})();
