-- Run this in your DB (mysql client)
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS bet;
DROP TABLE IF EXISTS completed_match;
DROP TABLE IF EXISTS upcoming_match;
DROP TABLE IF EXISTS match_game;
SET FOREIGN_KEY_CHECKS=1;

-- Recreate match_game (no CHECK)
CREATE TABLE match_game (
  match_id      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  season_label  VARCHAR(9) NOT NULL,
  matchday      INT NOT NULL,
  kickoff_at    DATETIME NULL,
  venue         VARCHAR(160) NULL,
  referee       VARCHAR(120) NULL,
  home_team_id  INT UNSIGNED NOT NULL,
  away_team_id  INT UNSIGNED NOT NULL,
  match_type    ENUM('upcoming','completed') NOT NULL,
  home_odds     DECIMAL(6,3) NULL,
  draw_odds     DECIMAL(6,3) NULL,
  away_odds     DECIMAL(6,3) NULL,
  CONSTRAINT fk_match_season FOREIGN KEY (season_label) REFERENCES season(season_label)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_match_home FOREIGN KEY (home_team_id) REFERENCES team(team_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_match_away FOREIGN KEY (away_team_id) REFERENCES team(team_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Enforce home<>away via triggers (INSERT and UPDATE)
DELIMITER //
CREATE TRIGGER bi_match_game_home_away
BEFORE INSERT ON match_game
FOR EACH ROW
BEGIN
  IF NEW.home_team_id = NEW.away_team_id THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'home_team_id and away_team_id must differ';
  END IF;
END//
CREATE TRIGGER bu_match_game_home_away
BEFORE UPDATE ON match_game
FOR EACH ROW
BEGIN
  IF NEW.home_team_id = NEW.away_team_id THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'home_team_id and away_team_id must differ';
  END IF;
END//
DELIMITER ;

-- Now the child tables will succeed
CREATE TABLE upcoming_match (
  match_id          BIGINT UNSIGNED PRIMARY KEY,
  tickets_available BOOLEAN NOT NULL DEFAULT FALSE,
  broadcast_info    VARCHAR(255) NULL,
  CONSTRAINT fk_up_match FOREIGN KEY (match_id) REFERENCES match_game(match_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE completed_match (
  match_id            BIGINT UNSIGNED PRIMARY KEY,
  home_goals          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  away_goals          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  result              ENUM('home','draw','away') NOT NULL,
  extra_time_minutes  TINYINT UNSIGNED NULL,
  penalties_home      TINYINT UNSIGNED NULL,
  penalties_away      TINYINT UNSIGNED NULL,
  CONSTRAINT fk_cm_match FOREIGN KEY (match_id) REFERENCES match_game(match_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bet (
  bet_id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bettor_user_id BIGINT UNSIGNED NOT NULL,
  match_id       BIGINT UNSIGNED NOT NULL,
  selection      ENUM('home','draw','away') NOT NULL,
  odds           DECIMAL(6,3) NOT NULL,
  stake          DECIMAL(12,2) NOT NULL,
  outcome        ENUM('pending','won','lost','voided') NOT NULL DEFAULT 'pending',
  placed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bet_bettor FOREIGN KEY (bettor_user_id) REFERENCES bettor(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_bet_match  FOREIGN KEY (match_id)      REFERENCES match_game(match_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_bet_bettor (bettor_user_id),
  INDEX idx_bet_match (match_id),
  INDEX idx_bet_outcome (outcome)
) ENGINE=InnoDB;
