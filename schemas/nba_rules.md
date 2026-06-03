# Business Rules

## Player & Team Lookup
- For player lookup, search by `first_name`, `last_name`, or full name via `CONCAT(first_name, ' ', last_name)`.
- For team lookup, search by `name`, `city`, or `abbreviation`.

## Season Stats vs Game Stats
- Use `nba_player_season_stats` for career, season-level, or multi-season queries (totals over full seasons).
- Use `nba_game_player_stats` for single-game, date-range, or per-game queries.
- `nba_player_season_stats` stores **cumulative totals** (points, rebounds, assists) for the entire season, not averages. Divide by `games_played` for per-game averages.

## Stat Definitions
- "Efficiency" means field goal percentage (`fg_pct`). Sort by FG% descending. Always require PPG >= 10 minimum to ensure meaningful volume. Show PPG alongside FG% for context.
- "Volume scorer" means high PPG (points per game). Sort by PPG descending. Show FG% for context. Require PPG >= 10.
- "PPG" = points divided by games played.
- "RPG" = rebounds divided by games played.
- "APG" = assists divided by games played.

## Minimum Data Thresholds
- For any leaderboard (scoring, efficiency, FG%, rebounds, assists, etc.), always filter `nba_player_season_stats.games_played >= 20` to exclude players with too few games.
- For efficiency/FG% queries, also require PPG >= 10 (having clause on `points / games_played`).

## Head-to-Head / Team Comparison
- Use `nba_games` table joined to `nba_teams` (as `home` and `away` aliases) for head-to-head matchups.

## Championships
- Use `nba_championships` for "who won the title" style queries. Join `nba_teams` (as `champion` and `runner_up` aliases) and `nba_seasons`.

## Awards
- Use `nba_player_awards` joined to `nba_awards`, `nba_players`, and `nba_seasons` for award queries.
- Award names are stored in `nba_awards.name` (e.g. "Most Valuable Player", "Rookie of the Year").

## General
- All WHERE values must be literal strings or numbers. Use `LIKE` for partial name matches.
- Use `table.column` notation in all expressions (not bare column names).
- `fg_pct` is stored as decimal 0.0–1.0. Multiply by 100 to show as percentage.
- Limit results to a reasonable number (default 10 unless the question specifies otherwise).
