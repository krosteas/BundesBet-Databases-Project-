<?php
require_once __DIR__ . '/_auth.php';
require_admin(); // gate the page
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>BundesBet — Admin Dashboard</title>
  <link rel="stylesheet" href="/app.css"/>
  <style>
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
    .tile{padding:1rem;border:1px solid #eee;border-radius:12px}
    .muted{opacity:.8}
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container nav">
      <a class="brand" href="/index.html"><span class="brand-logo"></span><span class="brand-name">BundesBet</span></a>
      <nav class="nav-links">
        <a href="/index.html">Home</a>
        <a class="active" href="/admin/dashboard.php">Admin</a>
        <a href="/admin/logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container">
    <section class="card">
      <h1>Maintenance</h1>
      <p class="muted">Welcome, <strong><?=htmlspecialchars($_SESSION['admin_username']??'admin',ENT_QUOTES)?></strong>. Choose an action below.</p>
      <div class="grid" style="margin-top:1rem">
        <div class="tile">
          <h3>Teams</h3>
          <p class="muted">Add/update team properties (city, stadium, crest…)</p>
          <p><em>Coming soon</em></p>
        </div>
        <div class="tile">
          <h3>Players</h3>
          <p class="muted">Add players and link to teams</p>
          <p><em>Coming soon</em></p>
        </div>
        <div class="tile">
          <h3>Matches</h3>
          <p class="muted">Create upcoming matches, finalize results</p>
          <p><em>Coming soon</em></p>
        </div>
      </div>
    </section>
  </main>

  <footer class="container site-footer">
    <div class="muted">© 2025 BundesBet · <a href="/imprint.html">Imprint</a></div>
  </footer>
</body>
</html>
