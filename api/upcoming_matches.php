<?php
require_once "_db.php";

/*
  Endpoint: /api/upcoming-matches.php
  Returns: [
    {
      match_id, season_label, matchday, kickoff_at, venue,
      home_team: { id, name }, away_team: { id, name },
      odds: { home_odds, draw_odds, away_odds }
    }, ...
  ]
*/

$sql = "
  SELECT
    m.match_id,
    m.season_label,
    m.matchday,
    m.kickoff_at,
    m.venue,
    m.home_odds, m.draw_odds, m.away_odds,
    th.team_id  AS home_id, th.name AS home_name,
    ta.team_id  AS away_id, ta.name AS away_name
  FROM match_game AS m
  JOIN upcoming_match AS u   ON u.match_id = m.match_id
  JOIN team          AS th  ON th.team_id = m.home_team_id
  JOIN team          AS ta  ON ta.team_id = m.away_team_id
  WHERE m.match_type = 'upcoming'
  ORDER BY m.kickoff_at ASC
  LIMIT 10
";

$res = $mysqli->query($sql);
if (!$res) {
  send_json(["error" => "Query failed", "details" => $mysqli->error], 500);
}

$out = [];
while ($r = $res->fetch_assoc()) {
  $out[] = [
    "match_id"      => (int)$r["match_id"],
    "season_label"  => $r["season_label"],
    "matchday"      => (int)$r["matchday"],
    "kickoff_at"    => $r["kickoff_at"],
    "venue"         => $r["venue"],
    "home_team"     => ["id" => (int)$r["home_id"], "name" => $r["home_name"]],
    "away_team"     => ["id" => (int)$r["away_id"], "name" => $r["away_name"]],
    "odds"          => [
      "home_odds" => $r["home_odds"] !== null ? (float)$r["home_odds"] : null,
      "draw_odds" => $r["draw_odds"] !== null ? (float)$r["draw_odds"] : null,
      "away_odds" => $r["away_odds"] !== null ? (float)$r["away_odds"] : null
    ]
  ];
}

send_json($out);
