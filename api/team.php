<?php
require_once "_db.php";

/*
  GET /api/team.php?id=... [&season=2025/26]

  {
    team_id, name, city, stadium, founded_year, website, crest_url, colors,
    stats: { matches_played, wins, draws, losses, goals_for, goals_against, points },
    squad: [
      { person_id, full_name, position, preferred_foot, squad_number,
        career_appearances, career_goals, career_assists }
    ],
    recent_matches: [
      { match_id, date, opponent_id, opponent_name, result, home_goals, away_goals }
    ]
  }
*/

$team_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
$season  = isset($_GET["season"]) ? trim($_GET["season"]) : null;

if ($team_id <= 0) {
  send_json(["error" => "Missing or invalid team id"], 400);
}

/* ---------- Basic team info ---------- */
$teamStmt = $mysqli->prepare("
  SELECT team_id, name, city, founded_year, stadium, stadium_capacity,
         website, crest_url, colors
  FROM team WHERE team_id = ? LIMIT 1
");
$teamStmt->bind_param("i", $team_id);
$teamStmt->execute();
$team = $teamStmt->get_result()->fetch_assoc();
if (!$team) send_json(["error" => "Team not found"], 404);

/* ---------- Stats from completed matches ---------- */
$whereSeason = "";
$params = [$team_id, $team_id, $team_id, $team_id, $team_id, $team_id];
$types  = "iiiiii";

if ($season !== null && $season !== "") {
  $whereSeason = " AND m.season_label = ? ";
  $params[] = $season;
  $types .= "s";
}

$sqlStats = "
  SELECT
    COUNT(*) AS matches_played,
    SUM(CASE
          WHEN (m.home_team_id = ? AND c.result = 'home') OR
               (m.away_team_id = ? AND c.result = 'away') THEN 1 ELSE 0
        END) AS wins,
    SUM(CASE WHEN c.result = 'draw' THEN 1 ELSE 0 END) AS draws,
    SUM(CASE
          WHEN (m.home_team_id = ? AND c.result = 'away') OR
               (m.away_team_id = ? AND c.result = 'home') THEN 1 ELSE 0
        END) AS losses,
    SUM(CASE WHEN m.home_team_id = ? THEN c.home_goals ELSE c.away_goals END) AS goals_for,
    SUM(CASE WHEN m.home_team_id = ? THEN c.away_goals ELSE c.home_goals END) AS goals_against
  FROM match_game m
  JOIN completed_match c ON c.match_id = m.match_id
  WHERE (m.home_team_id = ? OR m.away_team_id = ?)
  $whereSeason
";
$params[] = $team_id; $params[] = $team_id; $types .= "ii";

$statsStmt = $mysqli->prepare($sqlStats);
$statsStmt->bind_param($types, ...$params);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc() ?: [];

$wins  = (int)($stats["wins"]  ?? 0);
$draws = (int)($stats["draws"] ?? 0);
$stats["points"] = $wins * 3 + $draws;

/* ---------- Current squad (players) ---------- */
$squadStmt = $mysqli->prepare("
  SELECT
    p.person_id, p.full_name, pl.position, pl.preferred_foot, pl.squad_number,
    pl.career_appearances, pl.career_goals, pl.career_assists
  FROM person_team pt
  JOIN person p  ON p.person_id = pt.person_id AND pt.role='player'
  JOIN player pl ON pl.person_id = p.person_id
  WHERE pt.team_id = ?
    AND (pt.end_date IS NULL OR pt.end_date > CURDATE())
  ORDER BY
    FIELD(pl.position,'GK','DF','MF','FW'), p.full_name
");
$squadStmt->bind_param("i", $team_id);
$squadStmt->execute();
$squadRes = $squadStmt->get_result();
$squad = [];
while ($row = $squadRes->fetch_assoc()) {
  $squad[] = [
    "person_id"          => (int)$row["person_id"],
    "full_name"          => $row["full_name"],
    "position"           => $row["position"],
    "preferred_foot"     => $row["preferred_foot"],
    "squad_number"       => $row["squad_number"] !== null ? (int)$row["squad_number"] : null,
    "career_appearances" => (int)$row["career_appearances"],
    "career_goals"       => (int)$row["career_goals"],
    "career_assists"     => (int)$row["career_assists"]
  ];
}

/* ---------- Recent matches (last 5 completed) ---------- */
$whereSeason2 = ($season) ? " AND m.season_label = ? " : "";
$sqlRecent = "
  SELECT
    m.match_id,
    m.kickoff_at AS date,
    CASE WHEN m.home_team_id = ? THEN m.away_team_id ELSE m.home_team_id END AS opponent_id,
    CASE WHEN m.home_team_id = ? THEN ta.name ELSE th.name END AS opponent_name,
    c.home_goals, c.away_goals,
    c.result
  FROM match_game m
  JOIN completed_match c ON c.match_id = m.match_id
  JOIN team th ON th.team_id = m.home_team_id
  JOIN team ta ON ta.team_id = m.away_team_id
  WHERE (m.home_team_id = ? OR m.away_team_id = ?)
  $whereSeason2
  ORDER BY m.kickoff_at DESC
  LIMIT 5
";
$recentStmt = $mysqli->prepare($sqlRecent);
if ($season) {
  // 4 ints + 1 string (season)
  $recentStmt->bind_param("iiiis", $team_id, $team_id, $team_id, $team_id, $season);
} else {
  // 4 ints
  $recentStmt->bind_param("iiii", $team_id, $team_id, $team_id, $team_id);
}
$recentStmt->execute();
$rres = $recentStmt->get_result();
$recent = [];
while ($m = $rres->fetch_assoc()) {
  // Normalize to W/D/L from this team's perspective
  $result = 'D';
  if ($m["result"] === 'home') {
    $result = ($m["opponent_id"] == $team_id) ? 'L' : 'W';
  } elseif ($m["result"] === 'away') {
    $result = ($m["opponent_id"] == $team_id) ? 'W' : 'L';
  }
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

/* ---------- Output ---------- */
send_json([
  "team_id"          => (int)$team["team_id"],
  "name"             => $team["name"],
  "city"             => $team["city"],
  "stadium"          => $team["stadium"],
  "founded_year"     => $team["founded_year"] !== null ? (int)$team["founded_year"] : null,
  "stadium_capacity" => $team["stadium_capacity"] !== null ? (int)$team["stadium_capacity"] : null,
  "website"          => $team["website"],
  "crest_url"        => $team["crest_url"],
  "colors"           => $team["colors"],
  "stats"            => [
    "matches_played" => (int)($stats["matches_played"] ?? 0),
    "wins"           => $wins,
    "draws"          => $draws,
    "losses"         => (int)($stats["losses"] ?? 0),
    "goals_for"      => (int)($stats["goals_for"] ?? 0),
    "goals_against"  => (int)($stats["goals_against"] ?? 0),
    "points"         => (int)$stats["points"]
  ],
  "squad"            => $squad,
  "recent_matches"   => $recent
]);
