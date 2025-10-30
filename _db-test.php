<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
try {
  $pdo = new PDO('mysql:host=localhost;dbname=db_eyavuz;charset=utf8mb4','eyavuz','H60WgRrp+ZMy+xiq', [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
  $row = $pdo->query("SELECT COUNT(*) AS teams FROM team")->fetch();
  echo "DB OK. Teams: " . $row['teams'];
} catch (Throwable $e) { echo "DB FAIL: ".$e->getMessage(); }

