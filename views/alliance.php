<?php
declare(strict_types=1);

/**
 * Alliance view — /alliance
 *
 * Variables provided by index.php:
 *   $session  array  — current session row
 */

use Conquer\Db\Connection;

$db       = Connection::getInstance();
$playerId = (int) $session['player_id'];

// Load membership
$myMember = $db->query(
    'SELECT am.alliance_id, am.role
     FROM   alliance_members am
     WHERE  am.player_id = ?',
    [$playerId],
)->fetch() ?: null;

$myAlliance = null;
if ($myMember !== null) {
    $myAlliance = $db->query(
        'SELECT id, name, tag, description, leader_id, member_count, max_members, created_at
         FROM   alliances WHERE id = ?',
        [(int) $myMember['alliance_id']],
    )->fetch() ?: null;
}

$csrf = $session['csrf_token'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Allianz</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100vh;
            background: var(--c-bg, #f0e8d0);
            color: var(--c-text, #4a3520);
            font-family: system-ui, -apple-system, sans-serif;
        }

        .page-wrap {
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            margin-top: 70px;
            margin-bottom: 90px;
            padding: 0 12px;
        }

        /* ── Section card — extends game-panel from main.css ── */
        .card {
            background: var(--c-panel, #f4e4c1);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px var(--c-shadow, rgba(139,90,43,0.18));
        }

        .card-header {
            background: var(--c-panel3, #e8d8b0);
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
            padding: 10px 16px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--c-wood-dark, #8b5a2b);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-body {
            padding: 16px;
        }

        /* ── Tabs ── */
        .tab-bar {
            display: flex;
            border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
            background: var(--c-panel3, #e8d8b0);
        }

        .tab-btn {
            padding: 10px 20px;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--c-muted, #8b6f47);
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: color 0.15s, border-color 0.15s;
            margin-bottom: -1px;
            font-family: inherit;
        }

        .tab-btn:hover          { color: var(--c-text, #4a3520); }
        .tab-btn.tab-active     { color: var(--c-wood-dark, #8b5a2b); border-bottom-color: var(--c-gold, #c08858); }

        /* ── Alliance info panel ── */
        .alliance-banner {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 16px;
            padding: 16px;
            background: var(--c-panel2, #ede0c4);
            border-radius: 10px;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
        }

        .alliance-tag-badge {
            flex-shrink: 0;
            width: 68px;
            height: 68px;
            border-radius: 10px;
            background: var(--c-panel3, #e8d8b0);
            border: 2px solid var(--c-gold, #c08858);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 900;
            color: var(--c-wood-dark, #8b5a2b);
            letter-spacing: 0.04em;
            box-shadow: 0 0 16px rgba(192,136,88,0.15);
        }

        .alliance-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--c-wood-dark, #8b5a2b);
        }

        .alliance-meta {
            font-size: 0.78rem;
            color: var(--c-muted, #8b6f47);
            margin-top: 4px;
            line-height: 1.5;
        }

        .alliance-meta strong { color: var(--c-wood-dark, #8b5a2b); }

        .alliance-desc {
            font-size: 0.82rem;
            color: var(--c-text, #4a3520);
            line-height: 1.6;
            margin-top: 12px;
            padding: 10px 14px;
            background: var(--c-panel3, #e8d8b0);
            border-radius: 8px;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-left: 3px solid var(--c-gold, #c08858);
        }

        /* ── Member list ── */
        .member-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 7px;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-bottom: 1px solid rgba(139,90,43,0.12);
            background: transparent;
            margin-bottom: 6px;
            font-size: 0.82rem;
            transition: border-color 0.15s, background 0.15s;
        }

        .member-row:hover {
            background: rgba(192,136,88,0.08);
        }

        .member-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--c-panel3, #e8d8b0);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .member-username {
            flex: 1;
            font-weight: 700;
            color: var(--c-text, #4a3520);
        }

        .role-badge {
            font-size: 0.62rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .role-leader       { background: #c08858; color: #fff; }
        .role-vice_leader  { background: #9070c8; color: #fff; }
        .role-officer      { background: var(--c-info, #5f9ea0); color: #fff; }
        .role-veteran      { background: var(--c-success, #7fb069); color: #fff; }
        .role-member       { background: var(--c-panel3, #e8d8b0); color: var(--c-muted, #8b6f47); border: 1px solid var(--c-border, rgba(139,90,43,0.35)); }

        .member-joined {
            font-size: 0.68rem;
            color: var(--c-muted, #8b6f47);
            font-family: monospace;
        }

        /* ── Chat panel ── */
        .chat-messages {
            height: 340px;
            overflow-y: auto;
            background: var(--c-panel2, #ede0c4);
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 8px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 10px;
            scrollbar-width: thin;
            scrollbar-color: var(--c-border, rgba(139,90,43,0.35)) transparent;
        }

        .chat-messages::-webkit-scrollbar { width: 5px; }
        .chat-messages::-webkit-scrollbar-thumb { background: var(--c-border, rgba(139,90,43,0.35)); border-radius: 3px; }

        .chat-msg {
            font-size: 0.8rem;
            line-height: 1.4;
            padding: 3px 0;
            border-bottom: 1px solid rgba(139,90,43,0.08);
        }

        .chat-msg:last-child { border-bottom: none; }

        .chat-msg-name {
            font-weight: 800;
            color: var(--c-wood-dark, #8b5a2b);
            margin-right: 6px;
        }

        .chat-msg-text {
            color: var(--c-text, #4a3520);
            word-break: break-word;
        }

        .chat-msg-time {
            font-size: 0.62rem;
            color: var(--c-muted, #8b6f47);
            margin-left: 6px;
            font-family: monospace;
        }

        .chat-input-row {
            display: flex;
            gap: 8px;
        }

        .chat-input {
            flex: 1;
            background: #fdf6e8;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 7px;
            color: var(--c-text, #4a3520);
            font-size: 0.85rem;
            padding: 9px 12px;
            outline: none;
            transition: border-color 0.15s;
            font-family: inherit;
        }

        .chat-input:focus { border-color: var(--c-gold, #c08858); }
        .chat-input::placeholder { color: var(--c-muted, #8b6f47); }

        /* ── Buttons — use btn-game from main.css; local aliases for compat ── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 800;
            cursor: pointer;
            border: none;
            transition: filter 0.15s, transform 0.1s;
            font-family: inherit;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .btn:disabled { filter: grayscale(0.5) opacity(0.5); cursor: not-allowed; }
        .btn:hover:not(:disabled) { filter: brightness(1.12); }
        .btn:active:not(:disabled) { transform: translateY(1px); }

        .btn-primary {
            background: linear-gradient(180deg, #c9925a 0%, #9a6535 100%);
            color: #fff8ec;
            border-bottom: 2px solid #6b4120;
            box-shadow: 0 2px 8px var(--c-shadow, rgba(139,90,43,0.18));
        }
        .btn-success {
            background: linear-gradient(180deg, #8fc076 0%, #6a9454 100%);
            color: #fff;
            border-bottom: 2px solid #4a6e38;
            box-shadow: 0 2px 8px var(--c-shadow, rgba(139,90,43,0.18));
        }
        .btn-danger {
            background: linear-gradient(180deg, #d4705a 0%, #b0503e 100%);
            color: #fff;
            border-bottom: 2px solid #8a3028;
            box-shadow: 0 2px 8px var(--c-shadow, rgba(139,90,43,0.18));
        }
        .btn-sm { padding: 5px 12px; font-size: 0.72rem; }

        /* ── Search / join panel ── */
        .search-row {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .search-input {
            flex: 1;
            background: #fdf6e8;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 7px;
            color: var(--c-text, #4a3520);
            font-size: 0.85rem;
            padding: 8px 12px;
            outline: none;
            transition: border-color 0.15s;
            font-family: inherit;
        }

        .search-input:focus { border-color: var(--c-gold, #c08858); }
        .search-input::placeholder { color: var(--c-muted, #8b6f47); }

        .alliance-list-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            background: var(--c-panel2, #ede0c4);
            margin-bottom: 6px;
            font-size: 0.82rem;
            transition: border-color 0.15s, background 0.15s;
        }

        .alliance-list-row:hover {
            border-color: var(--c-gold, #c08858);
            background: rgba(192,136,88,0.08);
        }

        .alliance-list-tag {
            flex-shrink: 0;
            padding: 3px 9px;
            border-radius: 5px;
            background: rgba(192,136,88,0.12);
            border: 1px solid var(--c-gold, #c08858);
            color: var(--c-wood-dark, #8b5a2b);
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 0.06em;
        }

        .alliance-list-name {
            flex: 1;
            font-weight: 700;
            color: var(--c-text, #4a3520);
        }

        .alliance-list-count {
            font-size: 0.74rem;
            color: var(--c-muted, #8b6f47);
        }

        /* ── Create form ── */
        .form-row {
            margin-bottom: 14px;
        }

        .form-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--c-muted, #8b6f47);
            margin-bottom: 6px;
        }

        .form-input, .form-textarea {
            width: 100%;
            background: #fdf6e8;
            border: 1px solid var(--c-border, rgba(139,90,43,0.35));
            border-radius: 7px;
            color: var(--c-text, #4a3520);
            font-size: 0.85rem;
            padding: 9px 12px;
            outline: none;
            transition: border-color 0.15s;
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus { border-color: var(--c-gold, #c08858); }
        .form-input::placeholder, .form-textarea::placeholder { color: var(--c-muted, #8b6f47); }

        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .hint {
            font-size: 0.68rem;
            color: var(--c-muted, #8b6f47);
            margin-top: 4px;
        }

        /* ── Toast ── */
        #al-toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: var(--c-panel, #f4e4c1);
            border-left: 4px solid var(--c-gold, #c08858);
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 700;
            display: none;
            z-index: 9000;
            white-space: nowrap;
            backdrop-filter: blur(4px);
            color: var(--c-text, #4a3520);
            box-shadow: 0 4px 20px var(--c-shadow, rgba(139,90,43,0.18));
        }

        #al-toast.ok  { border-left-color: var(--c-success, #7fb069); color: var(--c-success, #7fb069); }
        #al-toast.err { border-left-color: var(--c-danger, #c0604d);  color: var(--c-danger, #c0604d); }

        /* ── Leave modal confirmation ── */
        .confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(74,53,32,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 8000;
            backdrop-filter: blur(3px);
        }

        .confirm-box {
            background: var(--c-panel, #f4e4c1);
            border: 1px solid rgba(192,96,77,0.5);
            border-radius: 12px;
            padding: 28px;
            max-width: 360px;
            width: 100%;
            text-align: center;
            color: var(--c-text, #4a3520);
            box-shadow: 0 20px 60px var(--c-shadow, rgba(139,90,43,0.18));
        }

        .confirm-box h3 {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--c-wood-dark, #8b5a2b);
        }

        .confirm-box p {
            font-size: 0.82rem;
            color: var(--c-muted, #8b6f47);
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .confirm-box strong { color: var(--c-text, #4a3520); }

        .confirm-btns {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        @media (max-width: 640px) {
            .alliance-banner { flex-direction: column; text-align: center; }
            .tab-btn { padding: 8px 12px; font-size: 0.7rem; }
        }
    </style>
</head>
<body>
<?php $hudCurrentView = 'alliance'; require __DIR__ . '/partials/hud.php'; ?>

<div class="page-wrap">

<?php if ($myAlliance === null): ?>
<!-- ═══════════════════════════════════════════════════════════════
     NOT A MEMBER — show search + create
═══════════════════════════════════════════════════════════════ -->
<div x-data="allianceBrowse()" x-init="init()">

    <!-- Search card -->
    <div class="card">
        <div class="card-header">⚔ Allianz suchen &amp; beitreten</div>
        <div class="card-body">
            <div class="search-row">
                <input
                    type="text"
                    class="search-input"
                    placeholder="Name oder Tag suchen..."
                    x-model="query"
                    @input.debounce.400ms="search()"
                >
            </div>

            <template x-if="loading">
                <div style="color:var(--c-muted,#8b6f47);font-size:.82rem;text-align:center;padding:16px 0">Suche...</div>
            </template>

            <template x-if="!loading && alliances.length === 0">
                <div style="color:var(--c-muted,#8b6f47);font-size:.82rem;text-align:center;padding:16px 0">Keine Allianzen gefunden.</div>
            </template>

            <template x-for="a in alliances" :key="a.id">
                <div class="alliance-list-row">
                    <span class="alliance-list-tag" x-text="'['+a.tag+']'"></span>
                    <span class="alliance-list-name" x-text="a.name"></span>
                    <span class="alliance-list-count" x-text="a.member_count+'/'+a.max_members+' Mitglieder'"></span>
                    <button
                        class="btn-game btn-game-green btn-game-sm"
                        :disabled="a.member_count >= a.max_members || joining"
                        @click="join(a.id, a.name)"
                        x-text="a.member_count >= a.max_members ? 'Voll' : 'Beitreten'"
                    ></button>
                </div>
            </template>
        </div>
    </div>

    <!-- Create card -->
    <div class="card">
        <div class="card-header">⊕ Neue Allianz gründen</div>
        <div class="card-body">
            <div class="form-row">
                <label class="form-label">Allianz-Name</label>
                <input type="text" class="form-input" x-model="createName" placeholder="z.B. Iron Legion" maxlength="50">
                <div class="hint">3–50 Zeichen</div>
            </div>
            <div class="form-row">
                <label class="form-label">Tag (Kürzel)</label>
                <input type="text" class="form-input" x-model="createTag"
                       placeholder="z.B. IRL" maxlength="6" style="text-transform:uppercase"
                       @input="createTag = createTag.toUpperCase()">
                <div class="hint">2–6 Großbuchstaben, z.B. [IRL]</div>
            </div>
            <div class="form-row">
                <label class="form-label">Beschreibung (optional)</label>
                <textarea class="form-textarea" x-model="createDesc" placeholder="Kurze Beschreibung..." maxlength="500"></textarea>
            </div>
            <button class="btn-game btn-game-blue" :disabled="creating" @click="create()">
                <span x-text="creating ? 'Wird erstellt...' : 'Allianz gründen'"></span>
            </button>
        </div>
    </div>

</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════
     IS A MEMBER — show alliance info + tabs
═══════════════════════════════════════════════════════════════ -->
<div x-data="allianceMember()" x-init="init()">

    <!-- Banner -->
    <div class="card">
        <div class="card-body">
            <div class="alliance-banner">
                <div class="alliance-tag-badge">[<?= htmlspecialchars((string)$myAlliance['tag']) ?>]</div>
                <div>
                    <div class="alliance-title"><?= htmlspecialchars((string)$myAlliance['name']) ?></div>
                    <div class="alliance-meta">
                        <?= (int)$myAlliance['member_count'] ?> / <?= (int)$myAlliance['max_members'] ?> Mitglieder
                        &bull; Gegründet <?= date('d.m.Y', strtotime((string)$myAlliance['created_at'])) ?>
                        &bull; Meine Rolle: <strong><?= htmlspecialchars((string)$myMember['role']) ?></strong>
                    </div>
                </div>
            </div>
            <?php if (!empty($myAlliance['description'])): ?>
            <div class="alliance-desc"><?= nl2br(htmlspecialchars((string)$myAlliance['description'])) ?></div>
            <?php endif ?>

            <!-- Leave button -->
            <div style="margin-top:14px;text-align:right">
                <button class="btn-game btn-game-red btn-game-sm" @click="confirmLeave = true">
                    Allianz verlassen
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="card">
        <div class="tab-bar">
            <button class="tab-btn" :class="tab==='info' && 'tab-active'" @click="tab='info'">Info</button>
            <button class="tab-btn" :class="tab==='members' && 'tab-active'" @click="tab='members'; loadMembers()">Mitglieder</button>
            <button class="tab-btn" :class="tab==='chat' && 'tab-active'" @click="tab='chat'; startChat()">Chat</button>
            <button class="tab-btn" :class="tab==='research' && 'tab-active'" @click="tab='research'; loadResearch()">&#x1F52C; Forschung</button>
            <button class="tab-btn" :class="tab==='rallies' && 'tab-active'" @click="tab='rallies'; loadRallies()">&#x2694; Rallies</button>
        </div>

        <!-- ── Research tab ── -->
        <div class="card-body" x-show="tab === 'research'" style="padding-bottom:8px">

            <!-- Active queue banner -->
            <template x-if="researchActiveCode">
                <div style="background:var(--c-panel3,#e8d8b0);border:1px solid var(--c-gold,#c08858);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:.8rem;display:flex;align-items:center;gap:10px">
                    <span style="font-size:1.1rem">&#x231B;</span>
                    <div>
                        <strong style="color:var(--c-wood-dark,#8b5a2b)" x-text="researchNodeName(researchActiveCode)"></strong>
                        wird erforscht
                        <span style="color:var(--c-muted,#8b6f47)">— fertig: <span x-text="fmtDate(researchFinishesAt)"></span></span>
                    </div>
                </div>
            </template>

            <!-- Node cards per tree -->
            <template x-for="tree in ['battle','production','special']" :key="tree">
                <div style="margin-bottom:16px">
                    <div style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--c-wood-dark,#8b5a2b);margin-bottom:8px" x-text="treeLabel(tree)"></div>
                    <template x-for="node in researchNodesForTree(tree)" :key="node.code">
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;border:1px solid var(--c-border,rgba(139,90,43,.35));background:var(--c-panel2,#ede0c4);margin-bottom:6px">
                            <!-- Level pips -->
                            <div style="display:flex;flex-direction:column;align-items:center;gap:2px;flex-shrink:0;min-width:36px">
                                <span style="font-size:0.65rem;font-weight:800;color:var(--c-muted,#8b6f47);text-transform:uppercase">Level</span>
                                <span style="font-size:1rem;font-weight:900;color:var(--c-wood-dark,#8b5a2b)" x-text="node.level + '/10'"></span>
                            </div>

                            <!-- Info -->
                            <div style="flex:1;min-width:0">
                                <div style="font-size:.82rem;font-weight:800;color:var(--c-text,#4a3520)" x-text="node.name"></div>
                                <div style="font-size:.7rem;color:var(--c-muted,#8b6f47)" x-text="bonusText(node)"></div>
                                <template x-if="node.cost_next">
                                    <div style="font-size:.68rem;color:var(--c-muted,#8b6f47);margin-top:2px">
                                        Kosten: &#x1FAB5; <span x-text="node.cost_next.lumber"></span>
                                        / &#x1FAA8; <span x-text="node.cost_next.stone"></span>
                                        / &#x1FA99; <span x-text="node.cost_next.gold"></span>
                                    </div>
                                </template>
                            </div>

                            <!-- Action -->
                            <div style="flex-shrink:0">
                                <template x-if="node.level >= 10">
                                    <span style="font-size:.7rem;font-weight:700;color:var(--c-success,#7fb069)">&#x2714; Max</span>
                                </template>
                                <template x-if="node.in_queue">
                                    <span style="font-size:.7rem;color:var(--c-muted,#8b6f47)">&#x231B; Aktiv</span>
                                </template>
                                <template x-if="node.level < 10 && !node.in_queue && canStartResearch">
                                    <button
                                        class="btn-game btn-game-blue btn-game-sm"
                                        :disabled="researchStarting || researchActiveCode !== null"
                                        @click="startResearch(node.code)"
                                        x-text="researchStarting ? 'Starte...' : ('→ L' + (node.level + 1))"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="researchNodes.length === 0">
                <div style="color:var(--c-muted,#8b6f47);font-size:.82rem;text-align:center;padding:20px 0">Lade Forschungs-Daten...</div>
            </template>
        </div>

        <!-- ── Rallies tab ── -->
        <div class="card-body" x-show="tab === 'rallies'">

            <template x-if="ralliesLoading">
                <div style="color:var(--c-muted,#8b6f47);font-size:.82rem;text-align:center;padding:16px 0">Lade Rallies...</div>
            </template>

            <template x-if="!ralliesLoading && ralliesList.length === 0">
                <div style="color:var(--c-muted,#8b6f47);font-size:.82rem;text-align:center;padding:20px 0">Keine aktiven Rallies.</div>
            </template>

            <template x-for="r in ralliesList" :key="r.id">
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;border:1px solid var(--c-border,rgba(139,90,43,.35));background:var(--c-panel2,#ede0c4);margin-bottom:8px;font-size:.82rem">
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:800;color:var(--c-wood-dark,#8b5a2b)">&#x2694; Rally</div>
                        <div style="font-size:.74rem;color:var(--c-muted,#8b6f47)">
                            Starter: <span x-text="r.starter_name ?? 'Unbekannt'"></span>
                            &bull; Ziel: (<span x-text="r.target_x"></span>, <span x-text="r.target_y"></span>)
                            &bull; <span x-text="(r.participant_count ?? 0)"></span> Teilnehmer
                        </div>
                        <div style="font-size:.68rem;color:var(--c-muted,#8b6f47);margin-top:2px">
                            Start: <span x-text="fmtRallyDate(r.launches_at)"></span>
                        </div>
                    </div>
                    <a :href="'/map?rally=' + r.id" class="btn-game btn-game-blue btn-game-sm">
                        Karte
                    </a>
                </div>
            </template>
        </div>

        <!-- ── Info tab ── -->
        <div class="card-body" x-show="tab === 'info'">
            <p style="font-size:.85rem;color:var(--c-text,#4a3520);line-height:1.6">
                Willkommen in der Allianz
                <strong style="color:var(--c-wood-dark,#8b5a2b)"><?= htmlspecialchars((string)$myAlliance['name']) ?></strong>.
                Nutze den Chat-Tab um mit deinen Mitstreitern zu kommunizieren, den Mitglieder-Tab
                um die Zusammensetzung deiner Allianz zu sehen, und den Forschungs-Tab um Allianz-Buffs freizuschalten.
            </p>
            <p style="font-size:.75rem;color:var(--c-muted,#8b6f47);margin-top:10px">
                Weitere Allianz-Features (Territorien, War-Declare) folgen in späteren Sprints.
            </p>
        </div>

        <!-- ── Members tab ── -->
        <div class="card-body" x-show="tab === 'members'">

            <template x-if="membersLoading">
                <div style="color:var(--c-muted,#8b6f47);font-size:.82rem;text-align:center;padding:16px 0">Lade Mitglieder...</div>
            </template>

            <template x-for="m in memberList" :key="m.player_id">
                <div class="member-row">
                    <span class="member-username" x-text="m.username"></span>
                    <span class="role-badge" :class="'role-'+m.role" x-text="roleLabel(m.role)"></span>
                    <span class="member-joined" x-text="'Beigetreten: ' + m.joined_at.substring(0,10)"></span>
                </div>
            </template>

            <template x-if="!membersLoading && memberList.length === 0">
                <div style="color:var(--c-muted,#8b6f47);font-size:.82rem;text-align:center;padding:16px 0">Keine Mitglieder gefunden.</div>
            </template>
        </div>

        <!-- ── Chat tab ── -->
        <div class="card-body" x-show="tab === 'chat'">
            <div class="chat-messages" id="chat-scroll">
                <template x-for="msg in messages" :key="msg.id">
                    <div class="chat-msg">
                        <span class="chat-msg-name" x-text="msg.username"></span>
                        <span class="chat-msg-text" x-text="msg.message"></span>
                        <span class="chat-msg-time" x-text="msg.sent_at.substring(11,16)+' UTC'"></span>
                    </div>
                </template>
                <template x-if="messages.length === 0">
                    <div style="color:var(--c-muted,#8b6f47);font-size:.8rem;text-align:center;padding:20px 0">
                        Noch keine Nachrichten. Schreib die erste!
                    </div>
                </template>
            </div>
            <div class="chat-input-row">
                <input
                    type="text"
                    class="chat-input"
                    placeholder="Nachricht eingeben..."
                    x-model="chatInput"
                    maxlength="200"
                    @keydown.enter.prevent="sendMsg()"
                >
                <button class="btn-game btn-game-blue btn-game-sm" :disabled="sending || chatInput.trim() === ''" @click="sendMsg()">
                    Senden
                </button>
            </div>
            <div style="font-size:.7rem;color:var(--c-muted,#8b6f47);margin-top:5px">
                Max. 200 Zeichen &bull; 1 Nachricht alle 3 Sekunden
            </div>
        </div>
    </div>

    <!-- Leave confirmation overlay -->
    <div class="confirm-overlay" x-show="confirmLeave" x-cloak>
        <div class="confirm-box">
            <h3>Allianz verlassen?</h3>
            <p>
                Bist du sicher, dass du
                <strong><?= htmlspecialchars((string)$myAlliance['name']) ?></strong>
                verlassen möchtest?
                <?php if ($myMember['role'] === 'leader' && (int)$myAlliance['member_count'] > 1): ?>
                Als Leader musst du zuerst ein anderes Mitglied zum Leader ernennen.
                <?php endif ?>
            </p>
            <div class="confirm-btns">
                <button class="btn-game btn-game-red" :disabled="leaving" @click="leave()">
                    <span x-text="leaving ? 'Verlasse...' : 'Ja, verlassen'"></span>
                </button>
                <button class="btn-game btn-game-blue" @click="confirmLeave = false">Abbrechen</button>
            </div>
        </div>
    </div>

</div>

<?php endif ?>

</div><!-- /page-wrap -->

<div id="al-toast"></div>

<script>
(function () {
'use strict';

const CSRF = <?= json_encode($csrf) ?>;

// ---------------------------------------------------------------------------
// Toast helper (global)
// ---------------------------------------------------------------------------
window.alToast = function (msg, type) {
    const t = document.getElementById('al-toast');
    t.textContent  = msg;
    t.className    = type;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
};

// ---------------------------------------------------------------------------
// Browse / create component (not a member)
// ---------------------------------------------------------------------------
function allianceBrowse() {
    return {
        query:      '',
        alliances:  [],
        loading:    false,
        joining:    false,
        creating:   false,
        createName: '',
        createTag:  '',
        createDesc: '',

        async init() {
            await this.search();
        },

        async search() {
            this.loading = true;
            try {
                const url = '/api/alliance/search' + (this.query.trim() !== '' ? '?q=' + encodeURIComponent(this.query) : '');
                const res  = await fetch(url);
                const json = await res.json();
                if (json.ok) this.alliances = json.data.alliances;
            } catch (e) {
                alToast('Netzwerkfehler beim Suchen', 'err');
            } finally {
                this.loading = false;
            }
        },

        async join(id, name) {
            if (!confirm('Der Allianz "' + name + '" beitreten?')) return;
            this.joining = true;
            try {
                const res  = await fetch('/api/alliance/join', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ alliance_id: id }),
                });
                const json = await res.json();
                if (json.ok) {
                    alToast('Allianz beigetreten!', 'ok');
                    setTimeout(() => location.reload(), 900);
                } else {
                    alToast(json.error?.message ?? json.error?.code ?? 'Fehler', 'err');
                }
            } catch (e) {
                alToast('Netzwerkfehler', 'err');
            } finally {
                this.joining = false;
            }
        },

        async create() {
            const name = this.createName.trim();
            const tag  = this.createTag.trim();
            const desc = this.createDesc.trim();

            if (name.length < 3) { alToast('Name muss mindestens 3 Zeichen haben', 'err'); return; }
            if (!/^[A-Z]{2,6}$/.test(tag)) { alToast('Tag: 2–6 Großbuchstaben', 'err'); return; }

            this.creating = true;
            try {
                const res  = await fetch('/api/alliance/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ name, tag, description: desc }),
                });
                const json = await res.json();
                if (json.ok) {
                    alToast('Allianz gegründet!', 'ok');
                    setTimeout(() => location.reload(), 900);
                } else {
                    alToast(json.error?.message ?? json.error?.code ?? 'Fehler', 'err');
                }
            } catch (e) {
                alToast('Netzwerkfehler', 'err');
            } finally {
                this.creating = false;
            }
        },
    };
}

// ---------------------------------------------------------------------------
// Member component (is a member)
// ---------------------------------------------------------------------------
function allianceMember() {
    return {
        tab:                'info',
        memberList:         [],
        membersLoading:     false,
        messages:           [],
        lastMsgId:          0,
        chatInput:          '',
        sending:            false,
        pollTimer:          null,
        confirmLeave:       false,
        leaving:            false,
        researchNodes:      [],
        researchActiveCode: null,
        researchFinishesAt: null,
        researchStarting:   false,
        canStartResearch:   <?= json_encode(in_array($myMember['role'], ['leader', 'vice_leader', 'officer'])) ?>,
        ralliesList:        [],
        ralliesLoading:     false,

        async init() {
            // nothing on load — tabs trigger their own loads
        },

        async loadMembers() {
            if (this.memberList.length > 0) return; // already loaded
            this.membersLoading = true;
            try {
                const res  = await fetch('/api/alliance/members');
                const json = await res.json();
                if (json.ok) this.memberList = json.data.members;
            } catch (e) {
                alToast('Netzwerkfehler beim Laden der Mitglieder', 'err');
            } finally {
                this.membersLoading = false;
            }
        },

        roleLabel(role) {
            const labels = {
                leader: 'Leader',
                vice_leader: 'Vize',
                officer: 'Offizier',
                veteran: 'Veteran',
                member: 'Mitglied',
            };
            return labels[role] ?? role;
        },

        async startChat() {
            if (this.messages.length === 0) await this.loadChat(false);
            if (this.pollTimer) clearInterval(this.pollTimer);
            this.pollTimer = setInterval(() => this.loadChat(true), 5000);
        },

        async loadChat(poll) {
            try {
                const url = '/api/alliance/chat' + (poll && this.lastMsgId > 0 ? '?since_id=' + this.lastMsgId : '');
                const res  = await fetch(url);
                const json = await res.json();
                if (!json.ok) return;

                const msgs = json.data.messages;
                if (msgs.length === 0) return;

                if (poll) {
                    this.messages.push(...msgs);
                } else {
                    this.messages = msgs;
                }

                this.lastMsgId = msgs[msgs.length - 1].id;
                this.$nextTick(() => this.scrollChat());
            } catch (e) {
                // silent — polling
            }
        },

        scrollChat() {
            const el = document.getElementById('chat-scroll');
            if (el) el.scrollTop = el.scrollHeight;
        },

        async sendMsg() {
            const msg = this.chatInput.trim();
            if (!msg || this.sending) return;

            this.sending = true;
            try {
                const res  = await fetch('/api/alliance/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ message: msg }),
                });
                const json = await res.json();
                if (json.ok) {
                    this.chatInput = '';
                    await this.loadChat(true);
                } else {
                    alToast(json.error?.message ?? json.error?.code ?? 'Fehler', 'err');
                }
            } catch (e) {
                alToast('Netzwerkfehler', 'err');
            } finally {
                this.sending = false;
            }
        },

        async loadRallies() {
            this.ralliesLoading = true;
            try {
                const res  = await fetch('/api/rally/list');
                const json = await res.json();
                if (json.ok) this.ralliesList = json.data.rallies ?? [];
            } catch (e) {
                alToast('Rallies konnten nicht geladen werden.', 'err');
            } finally {
                this.ralliesLoading = false;
            }
        },

        fmtRallyDate(dt) {
            if (!dt) return '—';
            try {
                return new Date(dt.replace(' ', 'T') + 'Z').toLocaleString('de-DE', {
                    hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit',
                });
            } catch { return dt; }
        },

        async loadResearch() {
            if (this.researchNodes.length > 0) return;
            try {
                const res  = await fetch('/api/alliance/research/state');
                const json = await res.json();
                if (!json.ok) return;
                const nodes = Object.values(json.data.nodes);
                this.researchNodes      = nodes;
                this.researchActiveCode = json.data.active_code  ?? null;
                this.researchFinishesAt = json.data.finishes_at  ?? null;
            } catch (e) {
                alToast('Forschungs-Daten konnten nicht geladen werden.', 'err');
            }
        },

        researchNodesForTree(tree) {
            return this.researchNodes.filter(n => n.tree === tree);
        },

        researchNodeName(code) {
            const node = this.researchNodes.find(n => n.code === code);
            return node ? node.name : code;
        },

        treeLabel(tree) {
            const labels = { battle: '⚔ Kampf', production: '🌾 Produktion', special: '✨ Spezial' };
            return labels[tree] ?? tree;
        },

        bonusText(node) {
            const total = node.level * (node.bonus_per_level ?? 0);
            let label = (node.bonus_label ?? '').replace('{n}', total.toFixed(total % 1 === 0 ? 0 : 1));
            if (node.level > 0) return label + ' (Stufe ' + node.level + ')';
            return node.description ?? '';
        },

        fmtDate(dt) {
            if (!dt) return '—';
            return new Date(dt.replace(' ', 'T') + 'Z').toLocaleString('de-DE', {
                hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit',
            });
        },

        async startResearch(code) {
            if (this.researchStarting || this.researchActiveCode !== null) return;
            this.researchStarting = true;
            try {
                const res  = await fetch('/api/alliance/research/start', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ research_code: code }),
                });
                const json = await res.json();
                if (json.ok) {
                    alToast('Forschung gestartet!', 'ok');
                    this.researchNodes = []; // force reload
                    await this.loadResearch();
                } else {
                    alToast(json.error?.message ?? json.error?.code ?? 'Fehler', 'err');
                }
            } catch (e) {
                alToast('Netzwerkfehler', 'err');
            } finally {
                this.researchStarting = false;
            }
        },

        async leave() {
            this.leaving = true;
            try {
                const res  = await fetch('/api/alliance/leave', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF },
                });
                const json = await res.json();
                if (json.ok) {
                    alToast('Du hast die Allianz verlassen.', 'ok');
                    setTimeout(() => location.reload(), 900);
                } else {
                    alToast(json.error?.message ?? json.error?.code ?? 'Fehler', 'err');
                    this.confirmLeave = false;
                }
            } catch (e) {
                alToast('Netzwerkfehler', 'err');
            } finally {
                this.leaving = false;
            }
        },
    };
}

// Register Alpine components before Alpine boots
document.addEventListener('alpine:init', () => {
    Alpine.data('allianceBrowse', allianceBrowse);
    Alpine.data('allianceMember', allianceMember);
});

})();
</script>

</body>
</html>
