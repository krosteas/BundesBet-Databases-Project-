<?php
require_once "_db.php";

/*
  Endpoint: /api/search.php?q=...
  Returns: {
    query: "...",
    teams:   [{ team_id, name, city }],
    players: [{ person_id, full_name, position, team_id, team_name }]
  }
*/

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
if ($q === "") {
  send_json(["error" => "Missing search query"], 400);
}

/* ---------- Teams ---------- */
$teamsStmt = $mysqli->prepare("
  SELECT t.team_id, t.name, t.city
  FROM team AS t
  WHERE t.name LIKE CONCAT('%', ?, '%')
     OR t.city LIKE CONCAT('%', ?, '%')
  ORDER BY t.name
  LIMIT 10
");
if (!$teamsStmt) {
  send_json(["error" => "Prepare failed (teams)", "details" => $mysqli->error], 500);
}
$teamsStmt->bind_param("ss", $q, $q);
$teamsStmt->execute();
$teamsRes = $teamsStmt->get_result();

$teams = [];
while ($t = $teamsRes->fetch_assoc()) {
  $teams[] = [
    "team_id" => (int)$t["team_id"],
    "name"    => $t["name"],
    "city"    => $t["city"]
  ];
}

/* ---------- Players (with current team if available) ---------- */
$playersStmt = $mysqli->prepare("
  SELECT 
    p.person_id,
    p.full_name,
    pl.position,
    pt.team_id,
    t.name AS team_name
  FROM person AS p
  JOIN player AS pl ON pl.person_id = p.person_id
  LEFT JOIN person_team AS pt 
    ON pt.person_id = p.person_id 
   AND pt.role = 'player' 
   AND (pt.end_date IS NULL OR pt.end_date > CURDATE())
  LEFT JOIN team AS t ON t.team_id = pt.team_id
  WHERE p.full_name LIKE CONCAT('%', ?, '%')
     OR pl.position LIKE CONCAT('%', ?, '%')
  ORDER BY p.full_name
  LIMIT 10
");
if (!$playersStmt) {
  send_json(["error" => "Prepare failed (players)", "details" => $mysqli->error], 500);
}
$playersStmt->bind_param("ss", $q, $q);
$playersStmt->execute();
$playersRes = $playersStmt->get_result();

$players = [];
while ($p = $playersRes->fetch_assoc()) {
  $players[] = [
    "person_id" => (int)$p["person_id"],
    "full_name" => $p["full_name"],
    "position"  => $p["position"],
    "team_id"   => $p["team_id"] !== null ? (int)$p["team_id"] : null,
    "team_name" => $p["team_name"]
  ];
}

send_json([
  "query"   => $q,
  "teams"   => $teams,
  "players" => $players
]);
