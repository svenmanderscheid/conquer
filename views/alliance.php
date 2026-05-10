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
            background: #080c18;
            color: #e2e8f0;
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
            background: var(--c-panel, #0f1729);
            border: 1px solid var(--c-border, rgba(184,134,11,0.3));
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.03);
        }

        .card-header {
            background: linear-gradient(180deg, #1a2744 0%, #0f1729 100%);
            border-bottom: 1px solid rgba(184,134,11,0.25);
            padding: 10px 16px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--c-gold, #d4a017);
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
            border-bottom: 1px solid rgba(184,134,11,0.25);
            background: #080c18;
        }

        .tab-btn {
            padding: 10px 20px;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #64748b;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: color 0.15s, border-color 0.15s;
            margin-bottom: -1px;
            font-family: inherit;
        }

        .tab-btn:hover          { color: #e2e8f0; }
        .tab-btn.tab-active     { color: #f0d080; border-bottom-color: #d4a017; }

        /* ── Alliance info panel ── */
        .alliance-banner {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 16px;
            padding: 16px;
            background: linear-gradient(135deg, rgba(26,39,68,0.6) 0%, rgba(8,12,24,0.6) 100%);
            border-radius: 10px;
            border: 1px solid rgba(184,134,11,0.2);
        }

        .alliance-tag-badge {
            flex-shrink: 0;
            width: 68px;
            height: 68px;
            border-radius: 10px;
            background: linear-gradient(180deg, #1a1200 0%, #0d0900 100%);
            border: 2px solid rgba(212,160,23,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 900;
            color: #f0d080;
            letter-spacing: 0.04em;
            box-shadow: 0 0 16px rgba(212,160,23,0.15);
        }

        .alliance-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: #f0d080;
            text-shadow: 0 0 20px rgba(212,160,23,0.3);
        }

        .alliance-meta {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 4px;
            line-height: 1.5;
        }

        .alliance-meta strong { color: #d4a017; }

        .alliance-desc {
            font-size: 0.82rem;
            color: #94a3b8;
            line-height: 1.6;
            margin-top: 12px;
            padding: 10px 14px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            border: 1px solid rgba(184,134,11,0.15);
            border-left: 3px solid rgba(212,160,23,0.4);
        }

        /* ── Member list ── */
        .member-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 7px;
            border: 1px solid rgba(45,64,96,0.6);
            background: rgba(0,0,0,0.25);
            margin-bottom: 6px;
            font-size: 0.82rem;
            transition: border-color 0.15s, background 0.15s;
        }

        .member-row:hover {
            border-color: rgba(184,134,11,0.25);
            background: rgba(255,255,255,0.02);
        }

        .member-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7c1e0e, #4a0d05);
            border: 1px solid rgba(212,160,23,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .member-username {
            flex: 1;
            font-weight: 700;
            color: #e2e8f0;
        }

        .role-badge {
            font-size: 0.62rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .role-leader       { background: #d97706; color: #fff; }
        .role-vice_leader  { background: #7c3aed; color: #fff; }
        .role-officer      { background: #0369a1; color: #fff; }
        .role-veteran      { background: #166534; color: #fff; }
        .role-member       { background: rgba(51,65,85,0.6); color: #94a3b8; border: 1px solid #334155; }

        .member-joined {
            font-size: 0.68rem;
            color: #475569;
            font-family: monospace;
        }

        /* ── Chat panel ── */
        .chat-messages {
            height: 340px;
            overflow-y: auto;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(45,64,96,0.6);
            border-radius: 8px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 10px;
            scrollbar-width: thin;
            scrollbar-color: rgba(184,134,11,0.2) transparent;
        }

        .chat-messages::-webkit-scrollbar { width: 5px; }
        .chat-messages::-webkit-scrollbar-thumb { background: rgba(184,134,11,0.2); border-radius: 3px; }

        .chat-msg {
            font-size: 0.8rem;
            line-height: 1.4;
            padding: 3px 0;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }

        .chat-msg:last-child { border-bottom: none; }

        .chat-msg-name {
            font-weight: 800;
            color: #f0d080;
            margin-right: 6px;
        }

        .chat-msg-text {
            color: #cbd5e1;
            word-break: break-word;
        }

        .chat-msg-time {
            font-size: 0.62rem;
            color: #475569;
            margin-left: 6px;
            font-family: monospace;
        }

        .chat-input-row {
            display: flex;
            gap: 8px;
        }

        .chat-input {
            flex: 1;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(184,134,11,0.25);
            border-radius: 7px;
            color: #e2e8f0;
            font-size: 0.85rem;
            padding: 9px 12px;
            outline: none;
            transition: border-color 0.15s;
            font-family: inherit;
        }

        .chat-input:focus { border-color: rgba(212,160,23,0.6); }
        .chat-input::placeholder { color: #475569; }

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
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            border-bottom: 2px solid #1e3a8a;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        .btn-success {
            background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            border-bottom: 2px solid #166534;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        .btn-danger {
            background: linear-gradient(180deg, #dc2626 0%, #b91c1c 100%);
            color: #fff;
            border-bottom: 2px solid #991b1b;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
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
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(184,134,11,0.25);
            border-radius: 7px;
            color: #e2e8f0;
            font-size: 0.85rem;
            padding: 8px 12px;
            outline: none;
            transition: border-color 0.15s;
            font-family: inherit;
        }

        .search-input:focus { border-color: rgba(212,160,23,0.6); }
        .search-input::placeholder { color: #475569; }

        .alliance-list-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid rgba(45,64,96,0.5);
            background: rgba(0,0,0,0.25);
            margin-bottom: 6px;
            font-size: 0.82rem;
            transition: border-color 0.15s, background 0.15s;
        }

        .alliance-list-row:hover {
            border-color: rgba(184,134,11,0.3);
            background: rgba(255,255,255,0.02);
        }

        .alliance-list-tag {
            flex-shrink: 0;
            padding: 3px 9px;
            border-radius: 5px;
            background: rgba(212,160,23,0.1);
            border: 1px solid rgba(212,160,23,0.5);
            color: #f0d080;
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 0.06em;
        }

        .alliance-list-name {
            flex: 1;
            font-weight: 700;
            color: #e2e8f0;
        }

        .alliance-list-count {
            font-size: 0.74rem;
            color: #64748b;
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
            color: #64748b;
            margin-bottom: 6px;
        }

        .form-input, .form-textarea {
            width: 100%;
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(184,134,11,0.25);
            border-radius: 7px;
            color: #e2e8f0;
            font-size: 0.85rem;
            padding: 9px 12px;
            outline: none;
            transition: border-color 0.15s;
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus { border-color: rgba(212,160,23,0.6); }
        .form-input::placeholder, .form-textarea::placeholder { color: #475569; }

        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .hint {
            font-size: 0.68rem;
            color: #475569;
            margin-top: 4px;
        }

        /* ── Toast ── */
        #al-toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: #0f1729;
            border: 1px solid rgba(184,134,11,0.3);
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 700;
            display: none;
            z-index: 9000;
            white-space: nowrap;
            backdrop-filter: blur(4px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.6);
        }

        #al-toast.ok  { border-color: rgba(34,197,94,0.5); color: #22c55e; }
        #al-toast.err { border-color: rgba(239,68,68,0.5); color: #ef4444; }

        /* ── Leave modal confirmation ── */
        .confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.78);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 8000;
            backdrop-filter: blur(3px);
        }

        .confirm-box {
            background: #0f1729;
            border: 1px solid rgba(239,68,68,0.4);
            border-radius: 12px;
            padding: 28px;
            max-width: 360px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.85);
        }

        .confirm-box h3 {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: #f0d080;
        }

        .confirm-box p {
            font-size: 0.82rem;
            color: #94a3b8;
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .confirm-box strong { color: #e2e8f0; }

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
                <div style="color:#64748b;font-size:.82rem;text-align:center;padding:16px 0">Suche...</div>
            </template>

            <template x-if="!loading && alliances.length === 0">
                <div style="color:#475569;font-size:.82rem;text-align:center;padding:16px 0">Keine Allianzen gefunden.</div>
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
        </div>

        <!-- ── Info tab ── -->
        <div class="card-body" x-show="tab === 'info'">
            <p style="font-size:.85rem;color:#94a3b8;line-height:1.6">
                Willkommen in der Allianz
                <strong style="color:#e2e8f0"><?= htmlspecialchars((string)$myAlliance['name']) ?></strong>.
                Nutze den Chat-Tab um mit deinen Mitstreitern zu kommunizieren, und den Mitglieder-Tab
                um die Zusammensetzung deiner Allianz zu sehen.
            </p>
            <p style="font-size:.75rem;color:#475569;margin-top:10px">
                Weitere Allianz-Features (Territorien, Alliance-Buffs, War-Declare) folgen in späteren Sprints.
            </p>
        </div>

        <!-- ── Members tab ── -->
        <div class="card-body" x-show="tab === 'members'">

            <template x-if="membersLoading">
                <div style="color:#64748b;font-size:.82rem;text-align:center;padding:16px 0">Lade Mitglieder...</div>
            </template>

            <template x-for="m in memberList" :key="m.player_id">
                <div class="member-row">
                    <span class="member-username" x-text="m.username"></span>
                    <span class="role-badge" :class="'role-'+m.role" x-text="roleLabel(m.role)"></span>
                    <span class="member-joined" x-text="'Beigetreten: ' + m.joined_at.substring(0,10)"></span>
                </div>
            </template>

            <template x-if="!membersLoading && memberList.length === 0">
                <div style="color:#475569;font-size:.82rem;text-align:center;padding:16px 0">Keine Mitglieder gefunden.</div>
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
                    <div style="color:#475569;font-size:.8rem;text-align:center;padding:20px 0">
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
            <div style="font-size:.7rem;color:#475569;margin-top:5px">
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
        tab:          'info',
        memberList:   [],
        membersLoading: false,
        messages:     [],
        lastMsgId:    0,
        chatInput:    '',
        sending:      false,
        pollTimer:    null,
        confirmLeave: false,
        leaving:      false,

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
