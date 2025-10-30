<?php
// api/_db.php — shared database connection

// ---------------------------------------------------------------------
// DATABASE CONFIG 
// ---------------------------------------------------------------------
$DB_HOST = "localhost";
$DB_USER = "eyavuz";     
$DB_PASS = "H60WgRrp+ZMy+xiq";     
$DB_NAME = "db_eyavuz";  

// ---------------------------------------------------------------------
// 🔌 Connect (using mysqli, utf8mb4 charset)
// ---------------------------------------------------------------------
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  header("Content-Type: application/json");
  echo json_encode(["error" => "Database connection failed", "details" => $mysqli->connect_error]);
  exit;
}
$mysqli->set_charset("utf8mb4");

// ---------------------------------------------------------------------
// 🔒 Helper for safe JSON output
// ---------------------------------------------------------------------
function send_json($data, int $code = 200) {
  http_response_code($code);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  exit;
}
?>
