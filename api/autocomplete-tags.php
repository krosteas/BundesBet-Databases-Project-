<?php
require_once "_db.php";

/*
  GET /api/autocomplete-tags.php

  Returns a flat array for jQuery UI:
  [
    { label, value, type, id },
    ...
  ]

  type = "team" | "player"
*/

$tags = [];

/* ---- Teams ---- */
$sqlTeams = "
  SELECT team_id, name
  FROM team
  ORDER BY name
";

if ($res = $mysqli->query($sqlTeams)) {
  while ($row = $res->fetch_assoc()) {
    $tags[] = [
      "label" => $row["name"] . " (Team)",
      "value" => $row["name"],
      "type"  => "team",
      "id"    => (int)$row["team_id"]
    ];
  }
} else {
  send_json(["error" => "Team query failed", "details" => $mysqli->error], 500);
}

/* ---- Players ---- */
$sqlPlayers = "
  SELECT p.person_id, p.full_name
  FROM person AS p
  JOIN player AS pl ON pl.person_id = p.person_id
  ORDER BY p.full_name
";

if ($res = $mysqli->query($sqlPlayers)) {
  while ($row = $res->fetch_assoc()) {
    $tags[] = [
      "label" => $row["full_name"] . " (Player)",
      "value" => $row["full_name"],
      "type"  => "player",
      "id"    => (int)$row["person_id"]
    ];
  }
} else {
  send_json(["error" => "Player query failed", "details" => $mysqli->error], 500);
}

send_json($tags);
