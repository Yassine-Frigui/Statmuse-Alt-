---
tables:
  - nba_players
  - nba_teams
  - nba_seasons
  - nba_games
  - nba_game_player_stats
  - nba_player_season_stats
  - nba_championships
  - nba_awards
  - nba_player_awards
  - nba_corpus_entries

columns:
  nba_players:
    - id (integer PK)
    - first_name (string)
    - last_name (string)
    - position (string nullable)
    - height (string nullable)
    - weight (string nullable)
    - college (string nullable)
    - drafted_year (integer nullable)

  nba_teams:
    - id (integer PK)
    - name (string)
    - city (string)
    - abbreviation (string)
    - conference (string nullable)
    - division (string nullable)
    - arena (string nullable)
    - founded_year (integer nullable)

  nba_seasons:
    - id (integer PK)
    - year (integer)
    - label (string)
    - start_date (date)
    - end_date (date)

  nba_games:
    - id (integer PK)
    - date (date)
    - home_team_id (integer FK -> nba_teams.id)
    - away_team_id (integer FK -> nba_teams.id)
    - home_score (integer)
    - away_score (integer)
    - season_id (integer FK -> nba_seasons.id)
    - stage (string)

  nba_game_player_stats:
    - id (integer PK)
    - game_id (integer FK -> nba_games.id)
    - player_id (integer FK -> nba_players.id)
    - team_id (integer FK -> nba_teams.id)
    - points (integer)
    - rebounds (integer)
    - assists (integer)
    - steals (integer)
    - blocks (integer)
    - fg_pct (float)
    - three_pct (float)
    - ft_pct (float)
    - minutes (string)
    - turnovers (integer)
    - personal_fouls (integer)

  nba_player_season_stats:
    - id (integer PK)
    - player_id (integer FK -> nba_players.id)
    - team_id (integer FK -> nba_teams.id)
    - season_id (integer FK -> nba_seasons.id)
    - games_played (integer)
    - points (integer)
    - rebounds (integer)
    - assists (integer)
    - steals (integer)
    - blocks (integer)
    - fg_pct (float)
    - three_pct (float)
    - ft_pct (float)

  nba_championships:
    - id (integer PK)
    - season_id (integer FK -> nba_seasons.id)
    - champion_team_id (integer FK -> nba_teams.id)
    - runner_up_team_id (integer FK -> nba_teams.id)
    - mvp_player_id (integer FK -> nba_players.id, nullable)
    - result_label (string)

  nba_awards:
    - id (integer PK)
    - name (string)
    - description (text)

  nba_player_awards:
    - id (integer PK)
    - player_id (integer FK -> nba_players.id)
    - award_id (integer FK -> nba_awards.id)
    - season_id (integer FK -> nba_seasons.id)

  nba_corpus_entries:
    - id (integer PK)
    - title (string)
    - content (text)
    - category (string)
    - tags (json)

relationships:
  nba_players:
    - HasMany nba_game_player_stats via nba_game_player_stats.player_id
    - HasMany nba_player_season_stats via nba_player_season_stats.player_id
    - HasMany nba_player_awards via nba_player_awards.player_id

  nba_teams:
    - HasMany nba_games as home team via nba_games.home_team_id
    - HasMany nba_games as away team via nba_games.away_team_id
    - HasMany nba_championships as champion via nba_championships.champion_team_id
    - HasMany nba_championships as runner-up via nba_championships.runner_up_team_id

  nba_seasons:
    - HasMany nba_games via nba_games.season_id
    - HasMany nba_player_season_stats via nba_player_season_stats.season_id
    - HasMany nba_championships via nba_championships.season_id
    - HasMany nba_player_awards via nba_player_awards.season_id

  nba_games:
    - BelongsTo nba_seasons via nba_games.season_id
    - BelongsTo nba_teams as home team via nba_games.home_team_id
    - BelongsTo nba_teams as away team via nba_games.away_team_id
    - HasMany nba_game_player_stats via nba_game_player_stats.game_id

  nba_game_player_stats:
    - BelongsTo nba_games via nba_game_player_stats.game_id
    - BelongsTo nba_players via nba_game_player_stats.player_id
    - BelongsTo nba_teams via nba_game_player_stats.team_id

  nba_player_season_stats:
    - BelongsTo nba_players via nba_player_season_stats.player_id
    - BelongsTo nba_teams via nba_player_season_stats.team_id
    - BelongsTo nba_seasons via nba_player_season_stats.season_id

  nba_championships:
    - BelongsTo nba_seasons via nba_championships.season_id
    - BelongsTo nba_teams as champion via nba_championships.champion_team_id
    - BelongsTo nba_teams as runner-up via nba_championships.runner_up_team_id
    - BelongsTo nba_players as mvp via nba_championships.mvp_player_id

  nba_awards:
    - HasMany nba_player_awards via nba_player_awards.award_id

  nba_player_awards:
    - BelongsTo nba_players via nba_player_awards.player_id
    - BelongsTo nba_awards via nba_player_awards.award_id
    - BelongsTo nba_seasons via nba_player_awards.season_id

data_range:
  seasons: { min: 2015, max: 2024 }
  games: { first: "2015-10-01", last: "2025-06-22" }
  championships: { min: 2015, max: 2022 }
---

## Notes

- `nba_player_season_stats` stores **season totals** (cumulative points, rebounds, etc. across all games played that season). Not per-game averages.
- `nba_game_player_stats` stores a single player's stats for **one game**.
- To get per-game averages (e.g. PPG, RPG, APG), divide the total by `games_played`.
- `fg_pct` is stored as a decimal (0.0–1.0). Multiply by 100 to show as percentage.
- Player names are split across `first_name` and `last_name` columns. Use `CONCAT(first_name, ' ', last_name)` for full names.
- `nba_corpus_entries` contains unstructured reference text (historical facts, rule explanations). Use `LIKE` search on `title` or `content` for trivia questions.
