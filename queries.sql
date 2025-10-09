
-- queries.sql
-- 9 SELECT queries with natural-language phrasing as comments (3 per teammate).
-- Each query is written to be executable directly on MySQL 8.

/* Q1 (Alice-1): Next matches for a viewer's favourite teams with odds.
   Show the viewer's favourites and any upcoming fixtures for those teams. */
SELECT v.user_id AS viewer_id, ft.team_id, t.name AS team_name,
       m.match_id, m.kickoff_at,
       th.name AS home_team, ta.name AS away_team,
       m.home_odds, m.draw_odds, m.away_odds
FROM viewer v
JOIN favourite_team ft ON ft.viewer_user_id = v.user_id
JOIN team t ON t.team_id = ft.team_id
JOIN match_game m ON m.match_type='upcoming' 
                  AND (m.home_team_id = ft.team_id OR m.away_team_id = ft.team_id)
JOIN team th ON th.team_id=m.home_team_id
JOIN team ta ON ta.team_id=m.away_team_id
WHERE v.user_id = (SELECT user_id FROM user_account WHERE username='alice')
ORDER BY m.kickoff_at;

/* Q2 (Alice-2): League table from completed matches (points, GF, GA, GD).
   Aggregate per team based on completed results. */
SELECT
  t.team_id, t.name,
  SUM(CASE 
        WHEN c.result='home' AND m.home_team_id=t.team_id THEN 3
        WHEN c.result='away' AND m.away_team_id=t.team_id THEN 3
        WHEN c.result='draw' THEN 1 ELSE 0
      END) AS points,
  SUM(CASE WHEN m.home_team_id=t.team_id THEN c.home_goals ELSE c.away_goals END) AS goals_for,
  SUM(CASE WHEN m.home_team_id=t.team_id THEN c.away_goals ELSE c.home_goals END) AS goals_against,
  SUM(CASE WHEN m.home_team_id=t.team_id THEN c.home_goals - c.away_goals 
           ELSE c.away_goals - c.home_goals END) AS goal_diff
FROM team t
JOIN match_game m ON t.team_id IN (m.home_team_id, m.away_team_id)
JOIN completed_match c ON c.match_id=m.match_id
WHERE m.season_label='2025/26'
GROUP BY t.team_id, t.name
HAVING COUNT(*) > 0
ORDER BY points DESC, goal_diff DESC, goals_for DESC;

/* Q3 (Alice-3): Head-to-head summary between two teams.
   Parameterize with team names; returns W/D/L for the first team. */
SELECT th.name AS team_A, ta.name AS team_B,
       SUM(CASE WHEN c.result='home' THEN 1 ELSE 0 END) AS A_home_wins,
       SUM(CASE WHEN c.result='away' THEN 1 ELSE 0 END) AS B_away_wins,
       SUM(CASE WHEN c.result='draw' THEN 1 ELSE 0 END) AS draws,
       SUM(CASE 
             WHEN (c.result='home' AND m.home_team_id=th.team_id) OR
                  (c.result='away' AND m.away_team_id=th.team_id) THEN 1
             ELSE 0 END) AS A_total_wins
FROM match_game m
JOIN completed_match c ON c.match_id=m.match_id
JOIN team th ON th.team_id=m.home_team_id
JOIN team ta ON ta.team_id=m.away_team_id
WHERE (th.name='Bayern München' AND ta.name='Borussia Dortmund')
   OR (th.name='Borussia Dortmund' AND ta.name='Bayern München')
GROUP BY th.name, ta.name;

/* Q4 (Bob-1): Bettor leaderboard – profit, stake, hit rate.
   Compute totals and win rate per bettor across all time. */
SELECT ua.username,
       COUNT(*) AS total_bets,
       SUM(stake) AS total_stake,
       SUM(CASE WHEN outcome='won' THEN (stake*(odds-1)) 
                WHEN outcome='lost' THEN -stake 
                ELSE 0 END) AS profit,
       AVG(outcome='won')*100 AS win_rate_pct
FROM bet b
JOIN bettor bt ON bt.user_id=b.bettor_user_id
JOIN user_account ua ON ua.user_id=bt.user_id
GROUP BY ua.username
ORDER BY profit DESC;

/* Q5 (Bob-2): Season-wise settlement rate (share of bets already decided).
   For each season, % of bets not pending. */
SELECT m.season_label,
       COUNT(*) AS bets_total,
       SUM(outcome<>'pending') AS decided_count,
       ROUND(SUM(outcome<>'pending')/COUNT(*)*100,2) AS decided_pct
FROM bet b
JOIN match_game m ON m.match_id=b.match_id
GROUP BY m.season_label;

/* Q6 (Bob-3): Upcoming matches with large betting interest (sum stake >= 50).
   Helps trading/risk team to monitor exposure before kickoff. */
SELECT m.match_id, th.name AS home, ta.name AS away, m.kickoff_at,
       SUM(b.stake) AS total_staked
FROM match_game m
JOIN upcoming_match u ON u.match_id=m.match_id
LEFT JOIN bet b ON b.match_id=m.match_id
JOIN team th ON th.team_id=m.home_team_id
JOIN team ta ON ta.team_id=m.away_team_id
GROUP BY m.match_id, home, away, m.kickoff_at
HAVING COALESCE(SUM(b.stake),0) >= 50
ORDER BY total_staked DESC;

/* Q7 (Carol-1): Per-match liability by outcome (home/draw/away).
   Sums stakes for each selection on a given match. */
SELECT m.match_id, th.name AS home, ta.name AS away,
       SUM(CASE WHEN b.selection='home' THEN b.stake ELSE 0 END) AS stake_home,
       SUM(CASE WHEN b.selection='draw' THEN b.stake ELSE 0 END) AS stake_draw,
       SUM(CASE WHEN b.selection='away' THEN b.stake ELSE 0 END) AS stake_away
FROM match_game m
LEFT JOIN bet b ON b.match_id=m.match_id
JOIN team th ON th.team_id=m.home_team_id
JOIN team ta ON ta.team_id=m.away_team_id
GROUP BY m.match_id, home, away
ORDER BY m.match_id;

/* Q8 (Carol-2): Squad composition – count players by position for a team.
   Uses person/person_team/player joins. */
SELECT t.name AS team,
       p2.position,
       COUNT(*) AS cnt
FROM team t
JOIN person_team pt ON pt.team_id=t.team_id AND pt.role='player' AND pt.end_date IS NULL
JOIN person p ON p.person_id=pt.person_id AND p.person_type='player'
JOIN player p2 ON p2.person_id=p.person_id
WHERE t.name='Bayern München'
GROUP BY t.name, p2.position;

/* Q9 (Carol-3): Bettor activity over last 14 days – rolling aggregates.
   Show each bettor's #bets and total stake in the last 14 days. */
SELECT ua.username,
       COUNT(*) AS bets_14d,
       SUM(stake) AS stake_14d
FROM bet b
JOIN bettor bt ON bt.user_id=b.bettor_user_id
JOIN user_account ua ON ua.user_id=bt.user_id
WHERE b.placed_at >= (CURRENT_DATE - INTERVAL 14 DAY)
GROUP BY ua.username
ORDER BY stake_14d DESC, bets_14d DESC;
