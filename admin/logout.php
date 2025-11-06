<?php
require_once __DIR__ . '/_auth.php';
admin_logout();
header('Location: /admin/login.php');
exit;
