
-- seed.sql
-- Minimal-but-robust sample data for testing queries against the Bundesliga Betting schema.
-- Assumes tables from the earlier DDL exist in the current database.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Seasons
INSERT IGNORE INTO season VALUES ('2024/25'), ('2025/26');

-- Teams
INSERT INTO team(name, city, founded_year, stadium, stadium_capacity, website)
VALUES 
 ('Bayern München','München',1900,'Allianz Arena',75000,'https://fcbayern.com'),
 ('Borussia Dortmund','Dortmund',1909,'Signal Iduna Park',81365,'https://bvb.de'),
 ('RB Leipzig','Leipzig',2009,'Red Bull Arena',47069,'https://rbleipzig.com'),
 ('VfB Stuttgart','Stuttgart',1893,'MHPArena',60449,'https://vfb.de');

-- Users
INSERT INTO user_account(username,email,password_hash,user_type,birth_date)
VALUES 
 ('alice','alice@example.com','hash','viewer','2002-04-10'),
 ('bob','bob@example.com','hash','bettor','2000-09-02'),
 ('carol','carol@example.com','hash','bettor','1999-12-12');

-- Viewer/Bettor children
INSERT INTO viewer(user_id, notifications_on)
SELECT user_id, TRUE FROM user_account WHERE username='alice';

INSERT INTO bettor(user_id, wallet_balance, total_stake, total_profit)
SELECT user_id, 500.00, 0.00, 0.00 FROM user_account WHERE username IN ('bob','carol');

-- Favourites
INSERT INTO favourite_team(viewer_user_id, team_id, created_at)
SELECT (SELECT user_id FROM user_account WHERE username='alice'), team_id, NOW()
FROM team WHERE name IN ('Bayern München','Borussia Dortmund');

-- People: players & coaches (minimal)
INSERT INTO person(full_name, birth_date, nationality, person_type) VALUES
 ('Manuel Neuer','1986-03-27','Germany','player'),
 ('Harry Kane','1993-07-28','England','player'),
 ('Thomas Tuchel','1973-08-29','Germany','coach'),
 ('Marco Reus','1989-05-31','Germany','player'),
 ('Edin Terzić','1982-10-30','Germany','coach');

-- Player details
INSERT INTO player(person_id, position, preferred_foot, squad_number, career_goals, career_assists)
SELECT p.person_id,'GK','right',1,0,0 FROM person p WHERE p.full_name='Manuel Neuer';
INSERT INTO player(person_id, position, preferred_foot, squad_number, career_goals, career_assists)
SELECT p.person_id,'FW','right',9,250,120 FROM person p WHERE p.full_name='Harry Kane';
INSERT INTO player(person_id, position, preferred_foot, squad_number, career_goals, career_assists)
SELECT p.person_id,'MF','right',11,160,140 FROM person p WHERE p.full_name='Marco Reus';

-- Coach details
INSERT INTO coach(person_id, role, trophies_count)
SELECT p.person_id,'Head Coach',12 FROM person p WHERE p.full_name='Thomas Tuchel';
INSERT INTO coach(person_id, role, trophies_count)
SELECT p.person_id,'Head Coach',2 FROM person p WHERE p.full_name='Edin Terzić';

-- Current memberships
-- Bayern: Neuer, Kane, Tuchel
INSERT INTO person_team(person_id, team_id, role, start_date)
SELECT (SELECT person_id FROM person WHERE full_name='Manuel Neuer'),
       (SELECT team_id FROM team WHERE name='Bayern München'), 'player','2011-07-01';
INSERT INTO person_team(person_id, team_id, role, start_date)
SELECT (SELECT person_id FROM person WHERE full_name='Harry Kane'),
       (SELECT team_id FROM team WHERE name='Bayern München'), 'player','2023-08-12';
INSERT INTO person_team(person_id, team_id, role, start_date)
SELECT (SELECT person_id FROM person WHERE full_name='Thomas Tuchel'),
       (SELECT team_id FROM team WHERE name='Bayern München'), 'coach','2023-03-24';

-- Dortmund: Reus, Terzić
INSERT INTO person_team(person_id, team_id, role, start_date)
SELECT (SELECT person_id FROM person WHERE full_name='Marco Reus'),
       (SELECT team_id FROM team WHERE name='Borussia Dortmund'), 'player','2012-07-01';
INSERT INTO person_team(person_id, team_id, role, start_date)
SELECT (SELECT person_id FROM person WHERE full_name='Edin Terzić'),
       (SELECT team_id FROM team WHERE name='Borussia Dortmund'), 'coach','2022-05-23';

-- Matches: 4 total (2 completed, 2 upcoming)
INSERT INTO match_game(season_label, matchday, kickoff_at, venue, home_team_id, away_team_id, match_type, home_odds, draw_odds, away_odds)
VALUES 
 ('2025/26',1,'2025-08-15 18:30:00','Allianz Arena',
   (SELECT team_id FROM team WHERE name='Bayern München'),
   (SELECT team_id FROM team WHERE name='Borussia Dortmund'),
   'completed', 1.85, 3.80, 4.10),
 ('2025/26',2,'2025-08-22 18:30:00','Signal Iduna Park',
   (SELECT team_id FROM team WHERE name='Borussia Dortmund'),
   (SELECT team_id FROM team WHERE name='RB Leipzig'),
   'completed', 2.20, 3.50, 3.10),
 ('2025/26',3,'2025-08-29 18:30:00','Red Bull Arena',
   (SELECT team_id FROM team WHERE name='RB Leipzig'),
   (SELECT team_id FROM team WHERE name='VfB Stuttgart'),
   'upcoming', 1.95, 3.60, 3.90),
 ('2025/26',4,'2025-09-05 18:30:00','MHPArena',
   (SELECT team_id FROM team WHERE name='VfB Stuttgart'),
   (SELECT team_id FROM team WHERE name='Bayern München'),
   'upcoming', 4.50, 3.90, 1.75);

-- Completed child rows
INSERT INTO completed_match(match_id, home_goals, away_goals, result, extra_time_minutes)
SELECT match_id, 2, 1, 'home', NULL FROM match_game WHERE season_label='2025/26' AND matchday=1;
INSERT INTO completed_match(match_id, home_goals, away_goals, result, extra_time_minutes)
SELECT match_id, 1, 1, 'draw', NULL FROM match_game WHERE season_label='2025/26' AND matchday=2;

-- Upcoming child rows
INSERT INTO upcoming_match(match_id, tickets_available, broadcast_info)
SELECT match_id, TRUE, 'DAZN' FROM match_game WHERE season_label='2025/26' AND matchday IN (3,4);

-- Bets: multiple on both completed and upcoming matches
-- Bob
INSERT INTO bet(bettor_user_id, match_id, selection, odds, stake, outcome, placed_at)
SELECT (SELECT user_id FROM user_account WHERE username='bob'),
       (SELECT match_id FROM match_game WHERE season_label='2025/26' AND matchday=1),
       'home', 1.85, 50.00, 'won', '2025-08-10 12:00:00';
INSERT INTO bet(bettor_user_id, match_id, selection, odds, stake, outcome, placed_at)
SELECT (SELECT user_id FROM user_account WHERE username='bob'),
       (SELECT match_id FROM match_game WHERE season_label='2025/26' AND matchday=2),
       'away', 3.10, 20.00, 'lost', '2025-08-18 09:00:00';
INSERT INTO bet(bettor_user_id, match_id, selection, odds, stake, outcome, placed_at)
SELECT (SELECT user_id FROM user_account WHERE username='bob'),
       (SELECT match_id FROM match_game WHERE season_label='2025/26' AND matchday=3),
       'home', 1.95, 35.00, 'pending', '2025-08-25 18:00:00';

-- Carol
INSERT INTO bet(bettor_user_id, match_id, selection, odds, stake, outcome, placed_at)
SELECT (SELECT user_id FROM user_account WHERE username='carol'),
       (SELECT match_id FROM match_game WHERE season_label='2025/26' AND matchday=1),
       'draw', 3.80, 15.00, 'lost', '2025-08-14 11:00:00';
INSERT INTO bet(bettor_user_id, match_id, selection, odds, stake, outcome, placed_at)
SELECT (SELECT user_id FROM user_account WHERE username='carol'),
       (SELECT match_id FROM match_game WHERE season_label='2025/26' AND matchday=4),
       'away', 1.75, 40.00, 'pending', '2025-09-01 12:00:00';
