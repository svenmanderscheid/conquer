<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin &mdash; Conquer</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      background: #0f172a;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      font-family: system-ui, -apple-system, sans-serif;
    }
    .login-box {
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 12px;
      padding: 40px;
      width: 360px;
    }
    h1 {
      color: #f1f5f9;
      font-size: 1.25rem;
      margin: 0 0 24px;
      font-weight: 700;
    }
    label {
      display: block;
      font-size: 0.8rem;
      color: #94a3b8;
      margin-bottom: 6px;
      font-weight: 600;
    }
    input[type="text"],
    input[type="password"] {
      width: 100%;
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 6px;
      color: #f1f5f9;
      padding: 10px 12px;
      font-size: 0.9rem;
      margin-bottom: 16px;
      outline: none;
      transition: border-color 0.15s;
    }
    input[type="text"]:focus,
    input[type="password"]:focus {
      border-color: #3b82f6;
    }
    button[type="submit"] {
      width: 100%;
      background: #3b82f6;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 11px;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
    }
    button[type="submit"]:hover { background: #2563eb; }
    .error {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      border-radius: 6px;
      color: #f87171;
      padding: 10px 12px;
      font-size: 0.82rem;
      margin-bottom: 16px;
    }
    .flash {
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.3);
      border-radius: 6px;
      color: #86efac;
      padding: 10px 12px;
      font-size: 0.82rem;
      margin-bottom: 16px;
    }
    .admin-hint {
      margin-top: 20px;
      font-size: 0.72rem;
      color: #334155;
      text-align: center;
    }
  </style>
</head>
<body>
<div class="login-box">
  <h1>&#9876; Conquer Admin</h1>

  <?php if (!empty($_SESSION['admin_flash'])): ?>
    <div class="flash"><?= htmlspecialchars($_SESSION['admin_flash']) ?></div>
    <?php unset($_SESSION['admin_flash']); ?>
  <?php endif ?>

  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif ?>

  <form method="post" action="<?= APP_BASE ?>/admin/login">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

    <label for="admin-username">Benutzername</label>
    <input
      type="text"
      id="admin-username"
      name="username"
      autocomplete="username"
      required
      autofocus
    >

    <label for="admin-password">Passwort</label>
    <input
      type="password"
      id="admin-password"
      name="password"
      autocomplete="current-password"
      required
    >

    <button type="submit">Anmelden</button>
  </form>

  <div class="admin-hint">Conquer Admin Panel &mdash; Nur autorisierte Zugiffe</div>
</div>
</body>
</html>
