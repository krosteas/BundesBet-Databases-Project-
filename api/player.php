<?php
require_once "_db.php";

/*
  GET /api/player.php?id=...

  Response:
  {
    person_id, full_name, birth_date, nationality, height_cm, weight_kg,
    position, preferred_foot, squad_number,
    team: { team_id, name } | null,
    career: { appearances, goals, assists, minutes, yellow_cards, red_cards, clean_sheets },
    recent_matches: [
      { match_id, date, opponent_id, opponent_name, result, home_goals, away_goals }
    ]
  }
*/

$person_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
if ($person_id <= 0) send_json(["error" => "Missing or invalid player id"], 400);

/* ---------- Player core ---------- */
$ppStmt = $mysqli->prepare("
  SELECT
    pe.person_id, pe.full_name, pe.birth_date, pe.nationality, pe.height_cm, pe.weight_kg, pe.person_type,
    pl.position, pl.preferred_foot, pl.squad_number,
    pl.career_appearances, pl.career_goals, pl.career_assists,
    pl.career_minutes, pl.career_yellow_cards, pl.career_red_cards, pl.career_clean_sheets
  FROM person pe
  JOIN player pl ON pl.person_id = pe.person_id
  WHERE pe.person_id = ? AND pe.person_type = 'player'
  LIMIT 1
");
if (!$ppStmt) send_json(["error"=>"Prepare failed (player)","details"=>$mysqli->error],500);
$ppStmt->bind_param("i", $person_id);
$ppStmt->execute();
$player = $ppStmt->get_result()->fetch_assoc();
if (!$player) send_json(["error" => "Player not found"], 404);

/* ---------- Current team ---------- */
$teamStmt = $mysqli->prepare("
  SELECT t.team_id, t.name
  FROM person_team pt
  JOIN team t ON t.team_id = pt.team_id
  WHERE pt.person_id = ? AND pt.role='player'
    AND (pt.end_date IS NULL OR pt.end_date > CURDATE())
  ORDER BY COALESCE(pt.end_date, '9999-12-31') DESC
  LIMIT 1
");
if (!$teamStmt) send_json(["error"=>"Prepare failed (team)","details"=>$mysqli->error],500);
$teamStmt->bind_param("i", $person_id);
$teamStmt->execute();
$team = $teamStmt->get_result()->fetch_assoc();
$team_id = $team ? (int)$team["team_id"] : null;

/* ---------- Recent matches (team context) ---------- */
$recent = [];
if ($team_id) {
  // Add an is_home flag so we can compute W/L correctly from this team’s perspective
  $recentStmt = $mysqli->prepare("
    SELECT
      m.match_id,
      m.kickoff_at AS date,
      CASE WHEN m.home_team_id = ? THEN m.away_team_id ELSE m.home_team_id END AS opponent_id,
      CASE WHEN m.home_team_id = ? THEN ta.name ELSE th.name END AS opponent_name,
      c.home_goals, c.away_goals, c.result,
      (m.home_team_id = ?) AS is_home
    FROM match_game m
    JOIN completed_match c ON c.match_id = m.match_id
    JOIN team th ON th.team_id = m.home_team_id
    JOIN team ta ON ta.team_id = m.away_team_id
    WHERE (m.home_team_id = ? OR m.away_team_id = ?)
    ORDER BY m.kickoff_at DESC
    LIMIT 5
  ");
  if (!$recentStmt) send_json(["error"=>"Prepare failed (recent)","details"=>$mysqli->error],500);
  // 5 ints: team_id used 5x
  $recentStmt->bind_param("iiiii", $team_id, $team_id, $team_id, $team_id, $team_id);
  $recentStmt->execute();
  $r = $recentStmt->get_result();
  while ($m = $r->fetch_assoc()) {
    $result = 'D';
    if ($m["result"] === 'home')  $result = ($m["is_home"] ? 'W' : 'L');
    if ($m["result"] === 'away')  $result = ($m["is_home"] ? 'L' : 'W');

    $recent[] = [
      "match_id"      => (int)$m["match_id"],
      "date"          => $m["date"],
      "opponent_id"   => (int)$m["opponent_id"],
      "opponent_name" => $m["opponent_name"],
      "home_goals"    => (int)$m["home_goals"],
      "away_goals"    => (int)$m["away_goals"],
      "result"        => $result
    ];
  }
}

/* ---------- Output ---------- */
send_json([
  "person_id"      => (int)$player["person_id"],
  "full_name"      => $player["full_name"],
  "birth_date"     => $player["birth_date"],
  "nationality"    => $player["nationality"],
  "height_cm"      => $player["height_cm"] !== null ? (int)$player["height_cm"] : null,
  "weight_kg"      => $player["weight_kg"] !== null ? (int)$player["weight_kg"] : null,
  "position"       => $player["position"],
  "preferred_foot" => $player["preferred_foot"],
  "squad_number"   => $player["squad_number"] !== null ? (int)$player["squad_number"] : null,
  "team"           => $team ? ["team_id" => $team_id, "name" => $team["name"]] : null,
  "career"         => [
    "appearances"   => (int)$player["career_appearances"],
    "goals"         => (int)$player["career_goals"],
    "assists"       => (int)$player["career_assists"],
    "minutes"       => (int)$player["career_minutes"],
    "yellow_cards"  => (int)$player["career_yellow_cards"],
    "red_cards"     => (int)$player["career_red_cards"],
    "clean_sheets"  => (int)$player["career_clean_sheets"]
  ],
  "recent_matches" => $recent
]);
