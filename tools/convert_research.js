/**
 * convert_research.js
 *
 * Converts LoK-format research JSON files into Conquer's internal format.
 * Run from the project root: node tools/convert_research.js
 *
 * Input:  C:/Users/svenm/Downloads/LOK INFO/leagueofkingdoms-wiki-main/research/
 * Output: data/research/battle.json, production.json, advanced.json
 */

'use strict';

const fs   = require('fs');
const path = require('path');

const INPUT_DIR  = 'C:/Users/svenm/Downloads/LOK INFO/leagueofkingdoms-wiki-main/research';
const OUTPUT_DIR = path.join(__dirname, '..', 'data', 'research');

// ── Row assignments ──────────────────────────────────────────────────────────

const BATTLE_ROWS = {
    infantry: ['infantry_hp','infantry_def','infantry_atk','infantry_spd','warrior',
               'infantry_training_amount','infantry_training_speed','infantry_training_cost',
               'knight','guardian','advanced_infantry_hp','advanced_infantry_def',
               'advanced_infantry_atk','advanced_infantry_spd','crusader'],
    ranged:   ['ranged_hp','ranged_def','ranged_atk','ranged_spd','longbow_man',
               'ranged_training_amount','ranged_training_speed','ranged_training_cost',
               'ranger','crossbow_man','advanced_ranged_hp','advanced_ranged_def',
               'advanced_ranged_atk','advanced_ranged_spd','sniper'],
    cavalry:  ['cavalry_hp','cavalry_def','cavalry_atk','cavalry_spd','horseman',
               'cavalry_training_amount','cavalry_training_speed','cavalry_training_cost',
               'heavy_cavalry','iron_cavalry','advanced_cavalry_hp','advanced_cavalry_def',
               'advanced_cavalry_atk','advanced_cavalry_spd','dragoon'],
    general:  ['troops_storage','march_size','march_limit','troops_hp','troops_atk',
               'troops_def','troops_spd','hospital_capacity','healing_time_reduced','rally_attack_amount'],
};

const PRODUCTION_ROWS = {
    food:    ['food_production','food_capacity','food_gathering_speed',
              'advanced_food_production','advanced_food_capacity','advanced_food_gathering_speed'],
    wood:    ['wood_production','wood_capacity','wood_gathering_speed',
              'advanced_wood_production','advanced_wood_capacity','advanced_wood_gathering_speed'],
    stone:   ['stone_production','stone_capacity','stone_gathering_speed',
              'advanced_stone_production','advanced_stone_capacity','advanced_stone_gathering_speed'],
    general: ['gold_production','gold_capacity','gold_gathering_speed','crystal_gathering_speed',
              'infantry_storage','ranged_storage','cavalry_storage','resource_protect',
              'research_speed','construction_speed',
              'advanced_gold_production','advanced_gold_capacity','advanced_gold_gathering_speed',
              'advanced_crystal_gathering_speed','advanced_research_speed','advanced_construction_speed'],
};

const ADVANCED_ROWS = {
    counter:     ['infantry_hp_against_archer','infantry_def_against_archer','infantry_atk_against_archer',
                  'archer_hp_against_cavalry','archer_def_against_cavalry','archer_atk_against_cavalry',
                  'cavalry_hp_against_infantry','cavalry_def_against_infantry','cavalry_atk_against_infantry'],
    castle_def:  ['castle_defending_infantrys_hp','castle_defending_infantrys_def','castle_defending_infantrys_atk',
                  'castle_defending_archers_hp','castle_defending_archers_def','castle_defending_archers_atk',
                  'castle_defending_cavalrys_hp','castle_defending_cavalrys_def','castle_defending_cavalrys_atk'],
    composed:    ['infantrys_hp_when_composed_of_infantry_only','infantrys_def_when_composed_of_infantry_only',
                  'infantrys_atk_when_composed_of_infantry_only',
                  'archers_hp_when_composed_of_archer_only','archers_def_when_composed_of_archer_only',
                  'archers_atk_when_composed_of_archer_only',
                  'cavalrys_hp_when_composed_of_cavalry_only','cavalrys_def_when_composed_of_cavalry_only',
                  'cavalrys_atk_when_composed_of_cavalry_only'],
    rally:       ['resource_production','resource_capacity','resource_protect',
                  'troop_speed_when_participating_a_rally',
                  'infantrys_hp_when_participating_a_rally','infantrys_def_when_participating_a_rally',
                  'infantrys_atk_when_participating_a_rally',
                  'archers_hp_when_participating_a_rally','archers_def_when_participating_a_rally',
                  'archers_atk_when_participating_a_rally',
                  'cavalrys_hp_when_participating_a_rally','cavalrys_def_when_participating_a_rally',
                  'cavalrys_atk_when_participating_a_rally'],
};

// Build reverse maps: code => row
function buildReverseMap(rowDefs) {
    const map = {};
    for (const [row, codes] of Object.entries(rowDefs)) {
        for (const code of codes) {
            map[code] = row;
        }
    }
    return map;
}

const BATTLE_ROW_MAP     = buildReverseMap(BATTLE_ROWS);
const PRODUCTION_ROW_MAP = buildReverseMap(PRODUCTION_ROWS);
const ADVANCED_ROW_MAP   = buildReverseMap(ADVANCED_ROWS);

// ── Unlock names ─────────────────────────────────────────────────────────────

const UNLOCK_NAMES = {
    warrior:      'Warrior',
    longbow_man:  'Longbow Man',
    horseman:     'Horseman',
    knight:       'Knight',
    ranger:       'Ranger',
    heavy_cavalry:'Heavy Cavalry',
    guardian:     'Guardian',
    crossbow_man: 'Crossbowman',
    iron_cavalry: 'Iron Cavalry',
    crusader:     'Crusader',
    sniper:       'Sniper',
    dragoon:      'Dragoon',
    march_limit:  'March Slots',
};

// ── Meta inference ───────────────────────────────────────────────────────────

function inferMeta(code, stats) {
    const isUnlock = stats.ability_relations === 'Unlock';

    const unlockCodes = ['warrior','longbow_man','horseman','knight','ranger','heavy_cavalry',
                         'guardian','crossbow_man','iron_cavalry','crusader','sniper','dragoon','march_limit'];
    if (isUnlock || unlockCodes.includes(code)) {
        return { category: 'unlock', stat: '', type: 'unlock' };
    }

    // Advanced unit types
    if (code.startsWith('advanced_infantry_')) return { category: 'infantry', stat: code.replace('advanced_infantry_',''), type: 'buff' };
    if (code.startsWith('advanced_ranged_'))   return { category: 'ranged',   stat: code.replace('advanced_ranged_',''),   type: 'buff' };
    if (code.startsWith('advanced_cavalry_'))  return { category: 'cavalry',  stat: code.replace('advanced_cavalry_',''),  type: 'buff' };

    // Training codes
    if (code.startsWith('infantry_training_')) return { category: 'training', stat: code, type: 'buff' };
    if (code.startsWith('ranged_training_'))   return { category: 'training', stat: code, type: 'buff' };
    if (code.startsWith('cavalry_training_'))  return { category: 'training', stat: code, type: 'buff' };

    // Unit specific stats
    if (code.startsWith('infantry_')) return { category: 'infantry', stat: code.replace('infantry_',''), type: 'buff' };
    if (code.startsWith('ranged_'))   return { category: 'ranged',   stat: code.replace('ranged_',''),   type: 'buff' };
    if (code.startsWith('cavalry_'))  return { category: 'cavalry',  stat: code.replace('cavalry_',''),  type: 'buff' };

    // Troops general
    if (code.startsWith('troops_'))   return { category: 'general', stat: code, type: 'buff' };

    // Production
    if (code.endsWith('_production') || code.endsWith('_capacity') || code.endsWith('_gathering_speed') ||
        code.endsWith('_protect') || code === 'resource_production' || code === 'resource_capacity' || code === 'resource_protect') {
        return { category: 'production', stat: code, type: 'buff' };
    }
    if (code.endsWith('_storage')) return { category: 'general', stat: code, type: 'buff' };

    // Speed / size / hospital
    if (code === 'march_size')                  return { category: 'general', stat: 'march_size', type: 'buff' };
    if (code === 'research_speed' || code === 'advanced_research_speed')         return { category: 'general', stat: code, type: 'buff' };
    if (code === 'construction_speed' || code === 'advanced_construction_speed') return { category: 'general', stat: code, type: 'buff' };
    if (code === 'hospital_capacity')   return { category: 'general', stat: 'hospital_capacity', type: 'buff' };
    if (code === 'healing_time_reduced')return { category: 'general', stat: 'healing_time_reduced', type: 'buff' };
    if (code === 'rally_attack_amount') return { category: 'rally',   stat: 'rally_attack_amount', type: 'buff' };

    // Advanced counter/castle/composed/rally
    if (code.includes('_against_'))          return { category: 'counter',        stat: code, type: 'buff' };
    if (code.startsWith('castle_defending')) return { category: 'castle_defense', stat: code, type: 'buff' };
    if (code.includes('when_composed_of'))   return { category: 'composed',       stat: code, type: 'buff' };
    if (code.includes('when_participating')) return { category: 'rally',          stat: code, type: 'buff' };
    if (code.includes('_speed_when_'))       return { category: 'rally',          stat: code, type: 'buff' };

    return { category: 'general', stat: code, type: 'buff' };
}

// ── Resource conversion ───────────────────────────────────────────────────────

function convertResources(arr) {
    const r = { food: 0, lumber: 0, stone: 0, gold: 0 };
    if (!Array.isArray(arr)) return r;
    for (const item of arr) {
        const key = item.type === 'wood' ? 'lumber' : item.type;
        r[key] = Number(item.value) || 0;
    }
    return r;
}

// ── Requirement conversion ────────────────────────────────────────────────────

function convertRequirements(arr) {
    if (!Array.isArray(arr)) return [];
    return arr.map(req => {
        const out = { type: String(req.type), level: Number(req.level) };
        // If the requirement type is not 'academy', treat it as a research prerequisite
        if (out.type !== 'academy') {
            out.code = out.type;
            out.type = 'research';
        }
        return out;
    });
}

// ── Name resolution ───────────────────────────────────────────────────────────

function resolveName(code, stats, meta) {
    if (meta.type === 'unlock' && UNLOCK_NAMES[code]) {
        return UNLOCK_NAMES[code];
    }
    const abilityName = String(stats.ability || code);
    // Prefix advanced nodes
    if (code.startsWith('advanced_infantry_') || code.startsWith('advanced_ranged_') || code.startsWith('advanced_cavalry_')) {
        return 'Adv. ' + abilityName;
    }
    // For advanced_ production nodes too
    if (code.startsWith('advanced_')) {
        return 'Adv. ' + abilityName;
    }
    return abilityName;
}

// ── Core converter ────────────────────────────────────────────────────────────

function convertTree(inputData, rowMap, treeName) {
    const nodes = [];

    for (const [code, levels] of Object.entries(inputData)) {
        if (!Array.isArray(levels) || levels.length === 0) continue;

        const firstLevel = levels[0];
        const stats = firstLevel.stats || {};
        const meta  = inferMeta(code, stats);
        const name  = resolveName(code, stats, meta);
        const row   = rowMap[code] || 'general';

        // Determine category for row label on production tree (food/wood/stone map to their resource)
        const category = meta.category;

        const convertedLevels = levels.map(entry => ({
            level:         Number(entry.level),
            ability_value: Number(entry.stats?.ability_value ?? 0),
            time:          Number(entry.time) || 0,
            power:         Number(entry.power) || 0,
            resources:     convertResources(entry.resources),
            requirements:  convertRequirements(entry.requirements),
        }));

        nodes.push({
            code,
            name,
            row,
            category,
            stat:      meta.stat,
            type:      meta.type,
            max_level: convertedLevels.length,
            levels:    convertedLevels,
        });
    }

    return { nodes };
}

// ── Ordered output ────────────────────────────────────────────────────────────
// Preserve the intended row order so the view can render them in sequence.

function orderNodes(data, rowDefs) {
    const nodeByCode = {};
    for (const node of data.nodes) {
        nodeByCode[node.code] = node;
    }

    const ordered = [];
    for (const codes of Object.values(rowDefs)) {
        for (const code of codes) {
            if (nodeByCode[code]) {
                ordered.push(nodeByCode[code]);
            }
        }
    }

    // Append any nodes not in the row definitions (shouldn't happen, but safety net)
    for (const node of data.nodes) {
        if (!ordered.find(n => n.code === node.code)) {
            ordered.push(node);
        }
    }

    return { nodes: ordered };
}

// ── Main ──────────────────────────────────────────────────────────────────────

function main() {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });

    const tasks = [
        { name: 'battle',     rowMap: BATTLE_ROW_MAP,     rowDefs: BATTLE_ROWS },
        { name: 'production', rowMap: PRODUCTION_ROW_MAP, rowDefs: PRODUCTION_ROWS },
        { name: 'advanced',   rowMap: ADVANCED_ROW_MAP,   rowDefs: ADVANCED_ROWS },
    ];

    for (const { name, rowMap, rowDefs } of tasks) {
        const inputPath  = path.join(INPUT_DIR, `${name}.json`);
        const outputPath = path.join(OUTPUT_DIR, `${name}.json`);

        console.log(`Converting ${name}...`);

        const raw  = fs.readFileSync(inputPath, 'utf8');
        const data = JSON.parse(raw);

        const converted = convertTree(data, rowMap, name);
        const ordered   = orderNodes(converted, rowDefs);

        fs.writeFileSync(outputPath, JSON.stringify(ordered, null, 2), 'utf8');

        console.log(`  -> ${ordered.nodes.length} nodes written to ${outputPath}`);
    }

    console.log('\nDone.');
}

main();
