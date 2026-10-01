/* Presentation only. The server owns membership and rank permissions. */
(() => {
    'use strict';
    const levels = Object.freeze({member:1, veteran:2, officer:3, vice_leader:4, leader:5});
    const english = Object.freeze({member:'Member', veteran:'Veteran', officer:'Officer', vice_leader:'Deputy leader', leader:'Alliance leader'});
    const escape = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const level = role => typeof role === 'string' && Object.hasOwn(levels, role) ? levels[role] : 0;
    const name = role => {
        if (!level(role)) return '';
        const key = 'alliance_rank.role.' + role, value = window.ConquerLocale?.t?.(key);
        return value && value !== key ? value : english[role];
    };
    const label = role => {
        if (!level(role)) return '';
        const key = 'alliance_rank.label.' + role, value = window.ConquerLocale?.t?.(key);
        return value && value !== key ? value : `R${level(role)} · ${name(role)}`;
    };
    const badge = role => level(role) ? `<span class="alliance-rank-badge" data-alliance-rank="${level(role)}" data-i18n-attrs="title:alliance_rank.label.${role};aria-label:alliance_rank.label.${role}" title="${escape(label(role))}" aria-label="${escape(label(role))}">R${level(role)}</span>` : '';
    window.ConquerAllianceRanks = Object.freeze({level, name, label, badge});
})();
