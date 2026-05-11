<?php
/**
 * Admin layout — wraps all admin pages.
 *
 * Expected variables (set before require):
 *   $adminSession  — from AdminAuth::requireAuth()
 *   $pageTitle     — string, page heading
 *   $activePage    — string, sidebar active state key
 *   $content       — string, ob_get_clean() from the inner view
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> &mdash; Conquer</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, sans-serif;
      background: #0f172a;
      color: #e2e8f0;
      display: flex;
      min-height: 100vh;
    }

    /* ---- Sidebar ---- */
    #admin-sidebar {
      width: 220px;
      flex-shrink: 0;
      background: #1e293b;
      border-right: 1px solid #334155;
      display: flex;
      flex-direction: column;
    }
    .admin-logo {
      padding: 20px 16px;
      border-bottom: 1px solid #334155;
      font-weight: 800;
      font-size: 1rem;
      color: #f1f5f9;
      letter-spacing: 0.02em;
    }
    .admin-logo span { color: #f59e0b; }
    .admin-nav { flex: 1; padding: 12px 0; }
    .admin-nav a {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 9px 16px;
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 500;
      transition: all 0.15s;
      border-left: 2px solid transparent;
    }
    .admin-nav a:hover,
    .admin-nav a.active {
      color: #f1f5f9;
      background: rgba(59, 130, 246, 0.1);
      border-left-color: #3b82f6;
      padding-left: 14px;
    }
    .admin-nav-section {
      padding: 8px 16px 4px;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #475569;
    }
    .admin-sidebar-footer {
      padding: 12px 16px;
      border-top: 1px solid #334155;
      font-size: 0.75rem;
      color: #64748b;
    }

    /* ---- Main ---- */
    #admin-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0;
    }
    #admin-topbar {
      height: 52px;
      flex-shrink: 0;
      background: #1e293b;
      border-bottom: 1px solid #334155;
      display: flex;
      align-items: center;
      padding: 0 20px;
      gap: 12px;
    }
    .admin-topbar-title {
      flex: 1;
      font-size: 0.9rem;
      font-weight: 600;
      color: #f1f5f9;
    }
    .admin-badge {
      font-size: 0.7rem;
      padding: 2px 8px;
      border-radius: 999px;
      font-weight: 700;
    }
    .admin-badge-super {
      background: rgba(245, 158, 11, 0.2);
      color: #f59e0b;
      border: 1px solid rgba(245, 158, 11, 0.3);
    }
    .admin-badge-mod {
      background: rgba(59, 130, 246, 0.2);
      color: #60a5fa;
      border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .admin-logout {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      border-radius: 5px;
      color: #f87171;
      font-size: 0.75rem;
      padding: 4px 10px;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.15s;
    }
    .admin-logout:hover {
      background: rgba(239, 68, 68, 0.2);
    }
    #admin-content {
      flex: 1;
      overflow-y: auto;
      padding: 24px;
    }

    /* ---- Flash message ---- */
    .admin-flash {
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.3);
      border-radius: 8px;
      color: #86efac;
      padding: 12px 16px;
      font-size: 0.82rem;
      margin-bottom: 20px;
    }

    /* ---- Cards ---- */
    .admin-card {
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 10px;
      margin-bottom: 20px;
      overflow: hidden;
    }
    .admin-card-header {
      padding: 14px 18px;
      border-bottom: 1px solid #334155;
      font-size: 0.82rem;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .admin-card-body { padding: 18px; }

    /* ---- Stats grid ---- */
    .admin-stats {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .admin-stat {
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 10px;
      padding: 18px;
    }
    .admin-stat-label {
      font-size: 0.72rem;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 6px;
    }
    .admin-stat-value {
      font-size: 1.6rem;
      font-weight: 800;
      color: #f1f5f9;
    }
    .admin-stat-sub {
      font-size: 0.7rem;
      color: #475569;
      margin-top: 4px;
    }

    /* ---- Table ---- */
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
    }
    .admin-table th {
      text-align: left;
      padding: 10px 12px;
      background: #0f172a;
      color: #64748b;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 1px solid #334155;
    }
    .admin-table td {
      padding: 10px 12px;
      border-bottom: 1px solid #1a2744;
      color: #cbd5e1;
    }
    .admin-table tr:last-child td { border-bottom: none; }
    .admin-table tr:hover td { background: rgba(255, 255, 255, 0.02); }

    /* ---- Buttons ---- */
    .admin-btn {
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: opacity 0.15s;
    }
    .admin-btn:hover { opacity: 0.85; }
    .admin-btn-primary { background: #3b82f6; color: white; }
    .admin-btn-danger {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .admin-btn-warning {
      background: rgba(245, 158, 11, 0.15);
      color: #fcd34d;
      border: 1px solid rgba(245, 158, 11, 0.3);
    }
    .admin-btn-sm { padding: 3px 8px; font-size: 0.72rem; }

    /* ---- Tabs inside card ---- */
    .admin-tab-btn {
      flex: 1;
      padding: 10px 12px;
      background: transparent;
      border: none;
      border-right: 1px solid #1e293b;
      color: #64748b;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      cursor: pointer;
      transition: background .15s, color .15s;
    }
    .admin-tab-btn:last-child { border-right: none; }
    .admin-tab-btn:hover { background: rgba(255,255,255,.04); color: #94a3b8; }
    .admin-tab-active { background: rgba(59,130,246,.12) !important; color: #93c5fd !important; border-bottom: 2px solid #3b82f6; }

    /* ---- Input ---- */
    .admin-input {
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 6px;
      color: #e2e8f0;
      padding: 6px 8px;
      font-size: 0.82rem;
      width: 100%;
      font-family: inherit;
    }
    .admin-input:focus { outline: none; border-color: #3b82f6; }

    /* ---- Form ---- */
    .admin-form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 16px;
    }
    .admin-form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 16px;
    }
    .admin-form-group label {
      font-size: 0.75rem;
      color: #94a3b8;
      font-weight: 600;
    }
    .admin-form-group input,
    .admin-form-group select,
    .admin-form-group textarea {
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 6px;
      color: #e2e8f0;
      padding: 8px 10px;
      font-size: 0.82rem;
      outline: none;
      transition: border-color 0.15s;
    }
    .admin-form-group input:focus,
    .admin-form-group select:focus,
    .admin-form-group textarea:focus {
      border-color: #3b82f6;
    }

    /* ---- Pagination ---- */
    .admin-pagination {
      display: flex;
      gap: 6px;
      align-items: center;
      margin-top: 16px;
    }
    .admin-pagination a,
    .admin-pagination span {
      padding: 5px 10px;
      border-radius: 5px;
      font-size: 0.78rem;
      text-decoration: none;
    }
    .admin-pagination a {
      background: #1e293b;
      border: 1px solid #334155;
      color: #94a3b8;
    }
    .admin-pagination a:hover { border-color: #3b82f6; color: #f1f5f9; }
    .admin-pagination span {
      background: #3b82f6;
      color: white;
      font-weight: 700;
    }

    /* ---- Tag / pill ---- */
    .admin-tag {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 999px;
      font-size: 0.68rem;
      font-weight: 700;
    }
    .admin-tag-green { background: rgba(34,197,94,0.15); color: #86efac; border: 1px solid rgba(34,197,94,0.3); }
    .admin-tag-red   { background: rgba(239,68,68,0.15);  color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
    .admin-tag-blue  { background: rgba(59,130,246,0.15); color: #93c5fd; border: 1px solid rgba(59,130,246,0.3); }
    .admin-tag-amber { background: rgba(245,158,11,0.15); color: #fcd34d; border: 1px solid rgba(245,158,11,0.3); }

    /* ---- Empty state ---- */
    .admin-empty {
      text-align: center;
      padding: 48px 24px;
      color: #475569;
      font-size: 0.85rem;
    }
  </style>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>

<div id="admin-sidebar">
  <div class="admin-logo">&#9876; <span>Conquer</span> Admin</div>
  <nav class="admin-nav">
    <div class="admin-nav-section">Uebersicht</div>
    <a href="<?= APP_BASE ?>/admin" <?= ($activePage ?? '') === 'dashboard' ? 'class="active"' : '' ?>>&#128202; Dashboard</a>

    <div class="admin-nav-section">Spieler</div>
    <a href="<?= APP_BASE ?>/admin/players"   <?= ($activePage ?? '') === 'players'   ? 'class="active"' : '' ?>>&#128100; Spieler</a>
    <a href="<?= APP_BASE ?>/admin/alliances" <?= ($activePage ?? '') === 'alliances' ? 'class="active"' : '' ?>>&#9876; Allianzen</a>

    <div class="admin-nav-section">Welt</div>
    <a href="<?= APP_BASE ?>/admin/world" <?= ($activePage ?? '') === 'world' ? 'class="active"' : '' ?>>&#128506; Weltverwaltung</a>
    <a href="<?= APP_BASE ?>/admin/chat"  <?= ($activePage ?? '') === 'chat'  ? 'class="active"' : '' ?>>&#128172; Chat Moderation</a>

    <div class="admin-nav-section">System</div>
    <a href="<?= APP_BASE ?>/admin/audit" <?= ($activePage ?? '') === 'audit' ? 'class="active"' : '' ?>>&#128203; Audit Log</a>
  </nav>
  <div class="admin-sidebar-footer">
    <?= htmlspecialchars($adminSession['username'] ?? '') ?> &bull;
    <?= ($adminSession['role'] ?? '') === 'superadmin' ? 'Superadmin' : 'Moderator' ?>
  </div>
</div>

<div id="admin-main">
  <div id="admin-topbar">
    <div class="admin-topbar-title"><?= htmlspecialchars($pageTitle ?? 'Admin') ?></div>
    <span class="admin-badge <?= ($adminSession['role'] ?? '') === 'superadmin' ? 'admin-badge-super' : 'admin-badge-mod' ?>">
      <?= ($adminSession['role'] ?? '') === 'superadmin' ? '&#9733; Superadmin' : 'Moderator' ?>
    </span>
    <a href="<?= APP_BASE ?>/admin/logout" class="admin-logout">Abmelden</a>
  </div>
  <div id="admin-content">
    <?php if (!empty($_SESSION['admin_flash'])): ?>
      <div class="admin-flash"><?= htmlspecialchars($_SESSION['admin_flash']) ?></div>
      <?php unset($_SESSION['admin_flash']); ?>
    <?php endif ?>
    <?= $content ?? '' ?>
  </div>
</div>

</body>
</html>
