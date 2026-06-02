# Champions League Expansion Plan

## Scope
- UEFA Champions League only (no domestic leagues, no Europa, no international)
- Editions from 1992/93 (rebranding) to present
- ~30 teams/season, ~125 matches/season, ~400 players/season

## Phase 1 — Data Model

### New tables

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `cl_seasons` | UCL campaign per year | `id`, `label` (e.g. "2023/24"), `start_year`, `end_year` |
| `cl_clubs` | Participating clubs | `id`, `name`, `country`, `crest_url` |
| `cl_players` | Players in UCL matches | `id`, `name`, `position`, `nationality`, `dob` |
| `cl_matches` | Individual games | `id`, `season_id`, `home_club_id`, `away_club_id`, `stage` (group/r16/qf/sf/final), `group_name`, `home_goals`, `away_goals`, `date`, `venue` |
| `cl_match_stats` | Player per-match stats | `id`, `match_id`, `player_id`, `club_id`, `goals`, `assists`, `minutes_played`, `yellow_cards`, `red_cards`, `shots`, `shots_on_target`, `passes`, `tackles`, `saves` (GK) |
| `cl_standings` | Group table snapshots | `id`, `season_id`, `group_name`, `club_id`, `played`, `won`, `drawn`, `lost`, `gf`, `ga`, `pts` |
| `cl_awards` | Seasonal honors | `id`, `season_id`, `award` (top_scorer/best_player/best_goalkeeper), `player_id`, `value` (goals scored etc.) |

### Schema constraints
- Matches: `stage` enum (group/r16/qf/sf/final)
- Use `club_id` not `team_id` to disambiguate from NBA teams
- All tables prefixed `cl_` to coexist with NBA schema

## Phase 2 — Data Ingestion

### Recommended source: [football-data.org](https://www.football-data.org/)
- Free tier: 10 req/min, covers UCL from 2003/04
- Returns JSON with matches, teams, standings, scorers
- No player-by-match stats on free tier (need Sportmonks or Opta for that)

### Fallback: Wikipedia scraping
- UCL pages have structured tables for results, top scorers, group standings
- Slower, less reliable, but free and comprehensive back to 1992

### Seeder order
1. `cl_seasons` — one row per edition
2. `cl_clubs` — deduplicated by name
3. `cl_players` — merged from match lineups and top scorers
4. `cl_standings` — group stage final tables
5. `cl_matches` — all results from group to final
6. `cl_match_stats` — only if per-player data available (goals/assists per match)
7. `cl_awards` — top scorer, player of tournament

## Phase 3 — NL Query Integration

### Question routing
- Detect soccer keywords: `champions league`, `ucl`, `real madrid`, `messi`, etc.
- Either extend `NLQueryEngine::detectIntent()` with a `sport` field
- Or add a `SportRouter` that delegates to NBA engine vs CL engine

### Supported query types (stretch goal: Gemini-generated queries)
| Intent | Example | Tables |
|--------|---------|--------|
| `cl_match_result` | "Who won the 2022 UCL final?" | cl_matches, cl_clubs |
| `cl_standings` | "Show me Group F standings 2023/24" | cl_standings, cl_clubs |
| `cl_top_scorer` | "Top 10 UCL scorers all-time" | cl_match_stats, cl_players |
| `cl_head_to_head` | "Real Madrid vs Bayern H2H in UCL" | cl_matches, cl_clubs |
| `cl_season_stats` | "How many goals did Haaland score in 2023/24 UCL?" | cl_match_stats, cl_players |
| `cl_awards` | "Who won UCL golden boot 2021?" | cl_awards |

### Reuse existing architecture
- `NLQueryEngine` can be parameterized by sport
- Same `executeQuery()` method works with new tables
- Frontend: new `/champions-league` page or a sport switcher in the nav
- Gemini prompt gets the CL schema appended when CL context is detected

## Non-goals (Phase 1)
- No domestic leagues (Premier League, La Liga, etc.)
- No Europa/Conference League
- No player transfers or market values
- No real-time / live match data
- No video or highlight integration
- No headshots or rich media

## Estimated effort
| Phase | Days | Dependencies |
|-------|------|-------------|
| Data model migration | 0.5 | None |
| API integration (football-data.org) | 1 | API key |
| Seeders | 1 | API integration complete |
| Match + standings queries | 1 | Seeders complete |
| Player stat queries | 1 | Seeders complete |
| Frontend (nav, pages) | 1 | Queries complete |
| Gemini prompt + routing | 1 | Frontend complete |
| **Total** | **~6.5 days** | |
