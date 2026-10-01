# Rally boss skills

Union of Kingdoms gives each of its five active rally boss families one versioned, server-resolved skill. The ten levels of a family share one understandable rule and counter. These rules extend the single-pass battle model; they do not introduce combat rounds, timers or additional actions.

| Boss | Skill | Effect without the counter | Counter based on troop catalogue power |
| --- | --- | --- | --- |
| Grumwald | Living roots | After surviving, restores 12% of the damage just taken, rounded down. A killing blow remains fatal. | At least 50% ranged power |
| Frostgrimm | Ice armor | Raises the current victory threshold by 12%, rounded up to whole power. | At least 50% infantry power |
| Sandmaul | Sandstorm | Reduces effective attacking power by 10% before battle luck is applied. | At least 50% cavalry power |
| Glutramm | Ember backlash | If the boss survives, increases the calculated injury ratio by 20% relative to its original value, capped at 35% total. It does not change victory or monster HP. | At least 50% ranged power |
| Dawnhorn (Dämmerhorn) | Runic barrier | Raises the current victory threshold by 15%, rounded up, while its HP before battle is strictly above 50% of the saved definition's maximum HP. | At least 30% infantry, 30% ranged and 30% cavalry power |

For example, an injury ratio of 10% becomes 12% under Ember backlash, not 30%. The barrier is inactive at exactly half health. Base-power percentages are evaluated without display rounding and without research, equipment or talent bonuses. Higher-tier troops therefore contribute according to their actual base power, not merely their headcount.

At settlement, all armies that reached the rally in time supply the shared counter. The existing battle preview includes only the selected player's contribution. Other participants, actual server luck and the target's condition on arrival can change the eventual result. The march summary uses the server's adjusted power and threshold once the current preview is available.

Balance update, 1 October 2026: single-type counters increased from 30% to 50%, and the balanced counter from 20% to 30% per troop type, to encourage alliance coordination. Effects remain unchanged. Newly created rallies save these thresholds; existing rallies and historical reports retain their saved values. The version-one schema remains compatible because thresholds are stored parameters.

## Versioned rules and saved results

`MonsterData::definition()` attaches rules only to the current catalogue: Dämmerhorn 20200501–20200510, Frostgrimm 20202101–20202110, Sandmaul 20202201–20202210, Glutramm 20202301–20202310 and Grumwald 20202401–20202410. No dormant family is activated and no spawn, reward or AP cost changes.

The existing rally creation path saves the complete definition. Settlement evaluates that saved rule rather than upgrading old orders. Definitions without a rule keep their original battle behaviour; unsupported versions, species-mismatched IDs and invalid parameters are ignored. Grumwald's version-one definition and historical report fields remain compatible.

New resolved skill payloads retain the rule parameters and add `countered`, `counter_power_share_percent`, `type_power_share_percent` and `active`. For a balanced formation, the scalar counter share is the smallest of the three type shares. Depending on the skill they also store:

- `required_power_before` and `required_power_after` for the two threshold skills;
- `army_power_before` and `army_power_after` for Sandstorm, before luck;
- `injury_ratio_before` and `injury_ratio_after` for Ember backlash, expressed as fractions;
- `phase_active` for Runic barrier.

The standard report fields `required_power`, `army_power` and `army_power_before_luck` contain the values actually used. New skill reports also retain their unmodified values under `required_power_before_mechanic` and `army_power_before_mechanic`. The outcome, persisted monster HP and per-owner casualties come from that same calculation. Viewing an archived report displays its saved result and does not recalculate combat.

A discovered rounding edge case is corrected in the common monster resolver: damage below the victory threshold cannot round a surviving monster down to zero HP. Such a hit leaves at least one HP. A hit meeting the threshold still kills normally. Other damage rounding stays unchanged, and Ember backlash can therefore only apply to a boss that actually survives.

## Interface and verification

`boss-mechanic.js` shares the explanation between march preparation, calculator and historical report. Rally details and the joining player's troop selection also show the rule saved for that rally. EN is the default; DE and FR provide complete equivalents. The existing beige skill card and shared fonts remain in place. Direct report pages load the same renderer as the main app.

`tests/boss_mechanics.php` covers exact thresholds, phase boundaries, modifier effects, combined armies, buff independence, saved rules, malformed or foreign rules and unchanged Grumwald behaviour. `tests/rally_boss_skills.php` verifies the four additions through real disposable-database rallies, authenticated preview logic, authoritative map data, snapshots, participant reports, HP persistence, repeated ticks and historical orders. The existing Grumwald and general monster-rally suites remain part of regression coverage.

`tests/boss_mechanic_ui.cjs` checks the renderer with real shared catalogues. `tests/rally_boss_skills_app.cjs` uses the isolated `--boss-skills` preview for actual march dialogs, calculator responses and historical reports, including narrow portrait, short landscape and longer translated text. The new suites are registered in `tests/run_alpha.py`. Local run results and screenshots are recorded under `artifacts/rally-boss-skills-2026-09-30/`.

These percentages are the current balance values. Automated checks establish their calculation and integration; longer-term difficulty and player preference still require playtesting.

## Counter-threshold verification, 1 October 2026

The 50% / 30% update passes 315 mechanic checks, including every boss level, exact thresholds, formations just below them, and saved 30% / 20% rules. Real disposable-database rallies pass all 13 Grumwald and 56 other-boss checks, including combined allied counters. The renderer passes 72 EN/DE/FR states with current and historical thresholds. The real app passes 12 EN cases at desktop, narrow portrait and landscape sizes plus 11 saved report views, with no browser/API errors or gameplay-writing requests. Grumwald's 50% and Dawnhorn's 30% descriptions were also visually checked at 320×568. Evidence is under `artifacts/rally-counter-thresholds-2026-10-01/`.

## Local verification, 30 September 2026

The ten selected backend suites pass: `boss_mechanics` (295 checks), `grumwald_rally` (13), `rally_boss_skills` (56), `monster_rallies` (75), `battle_preview` (84), `regional_bosses` (198), `monster_balance`, `battle_luck`, `monster_reports` and `localization`. Database scenarios use disposable fixtures. The final catalogue check includes 9,287 matching EN/DE/FR keys.

Two old test assumptions were corrected after reproducing their failures with the original battle engine: `battle_luck` assumed an outdated fixed T1 power, and `monster_reports` expected a victory from an army below the current victory threshold. The tests now derive these values from the actual troop catalogue and buffs, retain their assertions, and pass completely in `backend-recheck/`. The original failed logs and baseline proof remain available.

Renderer checks pass for 60 localized states, including saved values, unsupported versions and historical Grumwald results. The rally HUD check verifies that a saved 17% / 31% Frostgrimm rule remains unchanged in details and join options, and that old or city rallies receive no new rule. Localization semantics also pass.

The first full app run exposed a French number-formatting defect: generic text translation replaced narrow nonbreaking spaces in the already translated forecast. Explicit translation metadata now preserves the server-backed forecast and is cleared for warning messages. The same review removed two untranslated battle-luck labels from the English preflight. A separate code review found the missing explanation in the monster-rally join composer; that composer now renders the saved rule directly without enabling an unsupported calculator.

Final browser evidence combines the 13 successful EN/DE cases and 13 report views from `app-recheck/` with the successful FR rerun in `app-fr-final/`: **14 app cases and 16 historical report views**. All five bosses are covered at 320×568 and 568×320, with additional 390×844 and 1280×800 views. Formation changes, real preview responses, calculator parity, translated effects, Back, fixed touch actions and unchanged troops/monster HP pass, with no API errors, browser errors or gameplay-writing requests. The EN/DE run stopped at a Playwright scroll action when polling replaced a card; the test now scrolls the current element atomically, retaining all content, bounds and action assertions. The focused FR rerun records its selected locale explicitly.

The final `march_windows` run passes **866 checks**, including 33 new checks of the actual join composer: preserved 17% / 31% snapshot, no invented resolved result, scrollable skill text, reachable 44px confirmation, and unchanged legacy/city joins across three viewports. Screenshots are in `static-final/march_windows/`. Syntax checks and focused diff checks also pass. `final-source-hashes.json` records the final implementation and test sources. Mobile checks use browser viewports; no physical iOS or Android device was used for this change.
