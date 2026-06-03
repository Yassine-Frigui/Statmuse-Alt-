---
tables:
  - cl_teams
  - cl_seasons
  - cl_matches

columns:
  cl_teams:
    - id (integer PK)
    - name (string)
    - crest_url (string nullable)
    - venue (string nullable)
    - founded (integer nullable)

  cl_seasons:
    - id (integer PK)
    - name (string)
    - start_date (date)
    - end_date (date)
    - current_matchday (integer nullable)

  cl_matches:
    - id (integer PK)
    - season_id (integer FK -> cl_seasons.id)
    - status (string: SCHEDULED, FINISHED, AET, PEN)
    - matchday (integer nullable)
    - stage (string: Qualifying, Group Stage, Round of 16, Quarter-finals, Semi-finals, Final)
    - group_name (string nullable)
    - home_team_id (integer FK -> cl_teams.id)
    - away_team_id (integer FK -> cl_teams.id)
    - home_score (integer, full-time home goals)
    - away_score (integer, full-time away goals)
    - duration (string: always REGULAR)

relationships:
  cl_teams:
    - HasMany cl_matches as home team via cl_matches.home_team_id
    - HasMany cl_matches as away team via cl_matches.away_team_id

  cl_seasons:
    - HasMany cl_matches via cl_matches.season_id

  cl_matches:
    - BelongsTo cl_seasons via cl_matches.season_id
    - BelongsTo cl_teams as home team via cl_matches.home_team_id
    - BelongsTo cl_teams as away team via cl_matches.away_team_id

data_range:
  seasons: { min: 2018, max: 2025 }
  matches: { first: "2018-09-18", last: "2025-06-01" }
---
