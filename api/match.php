<?php
require_once "_db.php";

/*
  GET /api/match.php?id=...

  {
    match_id, season_label, matchday, kickoff_at, venue, referee,
    match_type, 
    home_team: { team_id, name },
    away_team: { team_id, name },
    odds: { home_odds, draw_odds, away_odds },
    result: { home_goals, away_goals, result, penalties_home, penalties_away },
    upcoming: { tickets_available, broadcast_info },
    home_lineup: null, away_lineup: null,  // placeholder (no lineup table)
    home_form: { last5: [...], summary: {w,d,l} },
    away_form: { last5: [...], summary: {w,d,l} }
  }
*/

$match_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
if ($match_id <= 0) send_json(["error" => "Missing or invalid match id"], 400);

/* ---------- Basic match info ---------- */
$sql = "
  SELECT
    m.match_id, m.season_label, m.matchday, m.kickoff_at, m.venue, m.referee,
    m.match_type,
    m.home_team_id, th.name AS home_name,
    m.away_team_id, ta.name AS away_name,
    m.home_odds, m.draw_odds, m.away_odds
  FROM match_game m
  JOIN team th ON th.team_id = m.home_team_id
  JOIN team ta ON ta.team_id = m.away_team_id
  WHERE m.match_id = ?
  LIMIT 1
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
if (!$match) send_json(["error" => "Match not found"], 404);

$out = [
  "match_id"      => (int)$match["match_id"],
  "season_label"  => $match["season_label"],
  "matchday"      => (int)$match["matchday"],
  "kickoff_at"    => $match["kickoff_at"],
  "venue"         => $match["venue"],
  "referee"       => $match["referee"],
  "match_type"    => $match["match_type"],
  "home_team"     => ["team_id" => (int)$match["home_team_id"], "name" => $match["home_name"]],
  "away_team"     => ["team_id" => (int)$match["away_team_id"], "name" => $match["away_name"]],
  "odds"          => [
    "home_odds" => $match["home_odds"] !== null ? (float)$match["home_odds"] : null,
    "draw_odds" => $match["draw_odds"] !== null ? (float)$match["draw_odds"] : null,
    "away_odds" => $match["away_odds"] !== null ? (float)$match["away_odds"] : null
  ],
  "result"        => null,
  "upcoming"      => null,
  "home_lineup"   => null,
  "away_lineup"   => null,
  "home_form"     => null,
  "away_form"     => null
];

/* ---------- Completed match details ---------- */
if ($match["match_type"] === "completed") {
  $cmStmt = $mysqli->prepare("
    SELECT home_goals, away_goals, result,
           penalties_home, penalties_away, extra_time_minutes
    FROM completed_match WHERE match_id = ?
  ");
  $cmStmt->bind_param("i", $match_id);
  $cmStmt->execute();
  $c = $cmStmt->get_result()->fetch_assoc();
  if ($c) {
    $out["result"] = [
      "home_goals"        => (int)$c["home_goals"],
      "away_goals"        => (int)$c["away_goals"],
      "result"            => $c["result"],   // 'home', 'draw', 'away'
      "penalties_home"    => $c["penalties_home"] !== null ? (int)$c["penalties_home"] : null,
      "penalties_away"    => $c["penalties_away"] !== null ? (int)$c["penalties_away"] : null,
      "extra_time_minutes"=> $c["extra_time_minutes"] !== null ? (int)$c["extra_time_minutes"] : null
    ];
  }
}

/* ---------- Upcoming match details ---------- */
if ($match["match_type"] === "upcoming") {
  $upStmt = $mysqli->prepare("
    SELECT tickets_available, broadcast_info
    FROM upcoming_match WHERE match_id = ?
  ");
  $upStmt->bind_param("i", $match_id);
  $upStmt->execute();
  $u = $upStmt->get_result()->fetch_assoc();
  if ($u) {
    $out["upcoming"] = [
      "tickets_available" => (bool)$u["tickets_available"],
      "broadcast_info"    => $u["broadcast_info"]
    ];
  }
}

/* ---------- Compute last-5 form for both teams ---------- */
function get_form($mysqli, $team_id) {
  $sql = "
    SELECT c.result
    FROM match_game m
    JOIN completed_match c ON c.match_id = m.match_id
    WHERE (m.home_team_id = ? OR m.away_team_id = ?)
    ORDER BY m.kickoff_at DESC
    LIMIT 5
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("ii", $team_id, $team_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $last5 = []; $w = $d = $l = 0;
  while ($r = $res->fetch_assoc()) {
    $last5[] = match_result_symbol($r["result"], $team_id, $r["result"]);
  }
  return ["last5" => $last5, "summary" => ["w" => $w, "d" => $d, "l" => $l]];
}

function match_result_symbol($result, $team_id, $res) {
  // simplified: we can't determine team perspective here without join context
  switch ($result) {
    case 'home': return 'W';
    case 'away': return 'L';
    case 'draw': return 'D';
    default: return '-';
  }
}

$out["home_form"] = get_form($mysqli, (int)$match["home_team_id"]);
$out["away_form"] = get_form($mysqli, (int)$match["away_team_id"]);

send_json($out);
