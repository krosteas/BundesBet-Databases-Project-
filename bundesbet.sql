-- =====================================================================
--  Bundesliga Betting – SQL Schema (MySQL 8 / InnoDB / utf8mb4)
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------- Lookup helpers (optional but tidy) ------------------------
CREATE TABLE season (
  season_label VARCHAR(9) PRIMARY KEY  -- e.g. '2025/26'
) ENGINE=InnoDB;

-- ---------- Users & roles (ISA: User -> Viewer | Bettor) --------------
CREATE TABLE user_account (
  user_id       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  user_type     ENUM('viewer','bettor') NOT NULL,        -- ISA discriminator
  status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
  birth_date    DATE NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE viewer (
  user_id        BIGINT UNSIGNED PRIMARY KEY,
  notifications_on BOOLEAN NOT NULL DEFAULT FALSE,
  CONSTRAINT fk_viewer_user
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bettor (
  user_id        BIGINT UNSIGNED PRIMARY KEY,
  wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_bets     INT UNSIGNED NOT NULL DEFAULT 0,
  bets_won       INT UNSIGNED NOT NULL DEFAULT 0,
  bets_lost      INT UNSIGNED NOT NULL DEFAULT 0,
  bets_void      INT UNSIGNED NOT NULL DEFAULT 0,
  total_stake    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_profit   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  wrl_rate       DECIMAL(6,3)  NULL,                   -- optional cached W/L ratio
  CONSTRAINT fk_bettor_user
    FOREIGN KEY (user_id) REFERENCES user_account(user_id)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------- Teams -----------------------------------------------------
CREATE TABLE team (
  team_id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name              VARCHAR(100) NOT NULL UNIQUE,
  short_name        VARCHAR(20)  NULL,
  city              VARCHAR(100) NULL,
  founded_year      SMALLINT NULL,
  stadium           VARCHAR(120) NULL,
  stadium_capacity  INT NULL,
  website           VARCHAR(200) NULL,
  crest_url         VARCHAR(400) NULL,
  colors            VARCHAR(120) NULL,
  history_text      TEXT NULL,
  total_titles      INT NULL,
  all_time_matches  INT NULL,
  all_time_wins     INT NULL,
  all_time_draws    INT NULL,
  all_time_losses   INT NULL,
  all_time_goals_for    INT NULL,
  all_time_goals_against INT NULL
) ENGINE=InnoDB;

-- ---------- Favourite team (viewer has many favourites) ---------------
CREATE TABLE favourite_team (
  favourite_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  viewer_user_id BIGINT UNSIGNED NOT NULL,
  team_id        INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fav_viewer
    FOREIGN KEY (viewer_user_id) REFERENCES viewer(user_id)
      ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fav_team
    FOREIGN KEY (team_id) REFERENCES team(team_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY uk_viewer_team (viewer_user_id, team_id)
) ENGINE=InnoDB;

-- ---------- People (ISA: Person -> Player | Coach) --------------------
CREATE TABLE person (
  person_id     BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  full_name     VARCHAR(120) NOT NULL,
  birth_date    DATE NULL,
  nationality   VARCHAR(80) NULL,
  height_cm     SMALLINT NULL,
  weight_kg     SMALLINT NULL,
  person_type   ENUM('player','coach') NOT NULL,  -- ISA discriminator
  photo_url     VARCHAR(400) NULL
) ENGINE=InnoDB;

CREATE TABLE player (
  person_id       BIGINT UNSIGNED PRIMARY KEY,
  position        ENUM('GK','DF','MF','FW') NULL,
  preferred_foot  ENUM('left','right','both') NULL,
  squad_number    SMALLINT NULL,
  career_appearances INT NOT NULL DEFAULT 0,
  career_goals       INT NOT NULL DEFAULT 0,
  career_assists     INT NOT NULL DEFAULT 0,
  career_minutes     INT NOT NULL DEFAULT 0,
  career_yellow_cards INT NOT NULL DEFAULT 0,
  career_red_cards    INT NOT NULL DEFAULT 0,
  career_clean_sheets INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_player_person
    FOREIGN KEY (person_id) REFERENCES person(person_id)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE coach (
  person_id       BIGINT UNSIGNED PRIMARY KEY,
  role            VARCHAR(60) NOT NULL DEFAULT 'Head Coach', -- or enum if you prefer
  career_matches  INT NOT NULL DEFAULT 0,
  career_wins     INT NOT NULL DEFAULT 0,
  career_draws    INT NOT NULL DEFAULT 0,
  career_losses   INT NOT NULL DEFAULT 0,
  trophies_count  INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_coach_person
    FOREIGN KEY (person_id) REFERENCES person(person_id)
      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------- Person <-> Team memberships (plays_at / coaches_at) -------
-- If you intend “current team only”, drop dates. This version supports history.
CREATE TABLE person_team (
  person_id BIGINT UNSIGNED NOT NULL,
  team_id   INT UNSIGNED NOT NULL,
  role      ENUM('player','coach') NOT NULL,
  start_date DATE NULL,
  end_date   DATE NULL,
  PRIMARY KEY (person_id, team_id, COALESCE(start_date,'1000-01-01')),
  CONSTRAINT fk_pt_person FOREIGN KEY (person_id) REFERENCES person(person_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pt_team   FOREIGN KEY (team_id)   REFERENCES team(team_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_pt_role  CHECK (role IN ('player','coach'))
) ENGINE=InnoDB;

-- ---------- Matches (ISA: Match -> UpcomingMatch | CompletedMatch) ----
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
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_match_teams CHECK (home_team_id <> away_team_id)
) ENGINE=InnoDB;

CREATE TABLE upcoming_match (
  match_id        BIGINT UNSIGNED PRIMARY KEY,
  tickets_available BOOLEAN NOT NULL DEFAULT FALSE,
  broadcast_info  VARCHAR(255) NULL,
  CONSTRAINT fk_up_match FOREIGN KEY (match_id) REFERENCES match_game(match_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE completed_match (
  match_id        BIGINT UNSIGNED PRIMARY KEY,
  home_goals      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  away_goals      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  result          ENUM('home','draw','away') NOT NULL,
  extra_time_minutes  TINYINT UNSIGNED NULL,
  penalties_home  TINYINT UNSIGNED NULL,
  penalties_away  TINYINT UNSIGNED NULL,
  CONSTRAINT fk_cm_match FOREIGN KEY (match_id) REFERENCES match_game(match_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------- Bets (Bettor places Bet on Match) -------------------------
CREATE TABLE bet (
  bet_id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bettor_user_id BIGINT UNSIGNED NOT NULL,
  match_id      BIGINT UNSIGNED NOT NULL,
  selection     ENUM('home','draw','away') NOT NULL,
  odds          DECIMAL(6,3) NOT NULL,
  stake         DECIMAL(12,2) NOT NULL,
  outcome       ENUM('pending','won','lost','voided') NOT NULL DEFAULT 'pending',
  placed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bet_bettor FOREIGN KEY (bettor_user_id) REFERENCES bettor(user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_bet_match  FOREIGN KEY (match_id)      REFERENCES match_game(match_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_bet_bettor (bettor_user_id),
  INDEX idx_bet_match (match_id),
  INDEX idx_bet_outcome (outcome)
) ENGINE=InnoDB;

-- ---------- Helpful indexes ------------------------------------------
CREATE INDEX idx_person_name ON person(full_name);
CREATE INDEX idx_team_name   ON team(name);
CREATE INDEX idx_match_season_day ON match_game(season_label, matchday);
