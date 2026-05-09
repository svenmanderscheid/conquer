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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100vh;
            background: #0f172a;
            color: #e2e8f0;
            font-family: system-ui, -apple-system, sans-serif;
        }

        .page-wrap {
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            margin-top: 90px;
            margin-bottom: 32px;
            padding: 0 12px;
        }

        /* ── Section card ── */
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .card-header {
            background: #0f172a;
            border-bottom: 1px solid #334155;
            padding: 10px 16px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #64748b;
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
            border-bottom: 1px solid #334155;
            background: #0f172a;
        }

        .tab-btn {
            padding: 10px 20px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #64748b;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: color .15s, border-color .15s;
        }

        .tab-btn:hover          { color: #94a3b8; }
        .tab-btn.tab-active     { color: #fbbf24; border-bottom-color: #fbbf24; }

        /* ── Alliance info panel ── */
        .alliance-banner {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .alliance-tag-badge {
            flex-shrink: 0;
            width: 64px;
            height: 64px;
            border-radius: 8px;
            background: #0f172a;
            border: 2px solid #fbbf24;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 900;
            color: #fbbf24;
            letter-spacing: .05em;
        }

        .alliance-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: #e2e8f0;
        }

        .alliance-meta {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 2px;
        }

        .alliance-desc {
            font-size: 0.82rem;
            color: #94a3b8;
            line-height: 1.5;
            margin-top: 10px;
            padding: 10px;
            background: #0f172a;
            border-radius: 6px;
            border: 1px solid #334155;
        }

        /* ── Member list ── */
        .member-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #0f172a;
            margin-bottom: 6px;
            font-size: 0.82rem;
        }

        .member-username {
            flex: 1;
            font-weight: 600;
            color: #e2e8f0;
        }

        .role-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .role-leader       { background: #d97706; color: #fff; }
        .role-vice_leader  { background: #7c3aed; color: #fff; }
        .role-officer      { background: #0369a1; color: #fff; }
        .role-veteran      { background: #166534; color: #fff; }
        .role-member       { background: #334155; color: #94a3b8; }

        .member-joined {
            font-size: 0.7rem;
            color: #475569;
        }

        /* ── Chat panel ── */
        .chat-messages {
            height: 340px;
            overflow-y: auto;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 10px;
        }

        .chat-msg {
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .chat-msg-name {
            font-weight: 700;
            color: #fbbf24;
            margin-right: 5px;
        }

        .chat-msg-text {
            color: #cbd5e1;
            word-break: break-word;
        }

        .chat-msg-time {
            font-size: 0.65rem;
            color: #475569;
            margin-left: 5px;
        }

        .chat-input-row {
            display: flex;
            gap: 8px;
        }

        .chat-input {
            flex: 1;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: 0.85rem;
            padding: 8px 12px;
            outline: none;
            transition: border-color .15s;
        }

        .chat-input:focus { border-color: #475569; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: opacity .15s, filter .15s;
        }

        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn:hover:not(:disabled) { filter: brightness(1.1); }

        .btn-primary { background: linear-gradient(180deg, #2563eb, #1e3a8a); color: #fff; }
        .btn-success { background: linear-gradient(180deg, #16a34a, #14532d); color: #fff; }
        .btn-danger  { background: linear-gradient(180deg, #dc2626, #7f1d1d); color: #fff; }
        .btn-sm      { padding: 5px 12px; font-size: 0.76rem; }

        /* ── Search / join panel ── */
        .search-row {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .search-input {
            flex: 1;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: 0.85rem;
            padding: 8px 12px;
            outline: none;
            transition: border-color .15s;
        }

        .search-input:focus { border-color: #475569; }

        .alliance-list-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #0f172a;
            margin-bottom: 6px;
            font-size: 0.82rem;
        }

        .alliance-list-tag {
            flex-shrink: 0;
            padding: 2px 8px;
            border-radius: 4px;
            background: #1e293b;
            border: 1px solid #fbbf24;
            color: #fbbf24;
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: .05em;
        }

        .alliance-list-name {
            flex: 1;
            font-weight: 600;
            color: #e2e8f0;
        }

        .alliance-list-count {
            font-size: 0.75rem;
            color: #64748b;
        }

        /* ── Create form ── */
        .form-row {
            margin-bottom: 12px;
        }

        .form-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #64748b;
            margin-bottom: 5px;
        }

        .form-input, .form-textarea {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: 0.85rem;
            padding: 8px 12px;
            outline: none;
            transition: border-color .15s;
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus { border-color: #475569; }

        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .hint {
            font-size: 0.7rem;
            color: #475569;
            margin-top: 3px;
        }

        /* ── Toast ── */
        #al-toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 0.85rem;
            display: none;
            z-index: 9000;
            white-space: nowrap;
        }

        #al-toast.ok  { border-color: #22c55e; color: #22c55e; }
        #al-toast.err { border-color: #ef4444; color: #ef4444; }

        /* ── Leave modal confirmation ── */
        .confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 8000;
        }

        .confirm-box {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 24px;
            max-width: 340px;
            width: 100%;
            text-align: center;
        }

        .confirm-box h3 {
            font-size: 1rem;
            margin-bottom: 10px;
            color: #e2e8f0;
        }

        .confirm-box p {
            font-size: 0.82rem;
            color: #94a3b8;
            margin-bottom: 20px;
            line-height: 1.5;
        }

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
<?php require __DIR__ . '/partials/nav.php'; ?>

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
                        class="btn btn-success btn-sm"
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
            <button class="btn btn-primary" :disabled="creating" @click="create()">
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
                <button class="btn btn-danger btn-sm" @click="confirmLeave = true">
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
                <button class="btn btn-primary btn-sm" :disabled="sending || chatInput.trim() === ''" @click="sendMsg()">
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
                <button class="btn btn-danger" :disabled="leaving" @click="leave()">
                    <span x-text="leaving ? 'Verlasse...' : 'Ja, verlassen'"></span>
                </button>
                <button class="btn btn-primary" @click="confirmLeave = false">Abbrechen</button>
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
