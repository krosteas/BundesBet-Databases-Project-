<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_csrf.php';

$err = '';
$next = isset($_GET['next']) ? $_GET['next'] : 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check()) {
    $err = 'Invalid form token. Please try again.';
  } else {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (!$u || !$p) {
      $err = 'Please enter username and password.';
    } else if (!admin_login($u, $p)) {
      $err = 'Invalid credentials.';
    } else {
      header('Location: ' . $next);
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>BundesBet — Admin Login</title>
  <link rel="stylesheet" href="/app.css"/>
  <style>
    .login-card{max-width:420px;margin:4rem auto;padding:1.5rem}
    .field{margin:0.6rem 0}
    .field label{display:block;margin-bottom:0.25rem}
    .field input{width:100%}
    .error{color:#b00020;margin-top:0.5rem}
  </style>
</head>
<body>
  <main class="container">
    <section class="card login-card">
      <h1>Admin Login</h1>
      <?php if ($err): ?><div class="error"><?=htmlspecialchars($err,ENT_QUOTES)?></div><?php endif; ?>
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="next" value="<?=htmlspecialchars($next,ENT_QUOTES)?>"/>
        <div class="field">
          <label>Username</label>
          <input name="username" type="text" maxlength="60" required autofocus/>
        </div>
        <div class="field">
          <label>Password</label>
          <input name="password" type="password" required/>
        </div>
        <div class="field">
          <button class="btn" type="submit">Sign in</button>
          <a class="btn btn-secondary" href="/">Cancel</a>
        </div>
      </form>
      <p class="muted" style="margin-top:0.75rem">Authorized personnel only.</p>
    </section>
  </main>
</body>
</html>
