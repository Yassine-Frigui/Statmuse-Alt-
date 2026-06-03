# Business Rules

## Team Lookup
- Search teams by `name` using `LIKE` for partial matches (e.g. `WHERE LOWER(t.name) LIKE '%liverpool%'`).

## Season Format
- Season `id` is a single year (e.g. 2018 for 2018-2019 season).
- Season `name` is in "YYYY-YYYY" format (e.g. "2018-2019").
- Use `cl_seasons.name` or `cl_seasons.id` interchangeably. Both identify the season uniquely.

## Match Results
- `home_score` and `away_score` are **full-time** scores (including extra time goals, excluding penalties).
- For penalty shootout results, check `status = 'PEN'`. The score in `home_score`-`away_score` is the **extra-time** score (or full-time if level), before penalties.
- `status = 'AET'` means the match went to extra time. `status = 'PEN'` means it went to penalties.
- `status = 'SCHEDULED'` matches have no scores (both NULL). Always filter `WHERE status IN ('FINISHED', 'AET', 'PEN')` for completed matches.

## Goal Statistics
- To compute "goals per game" or "average goals", use `AVG(home_score + away_score)`.
- To compute goals scored by a specific team (home or away), sum `home_score` when that team is the home team, and `away_score` when that team is the away team.
- For a team's total goals in a season, combine home and away matches:
  ```sql
  SUM(CASE WHEN m.home_team_id = <team_id> THEN m.home_score ELSE 0 END) +
  SUM(CASE WHEN m.away_team_id = <team_id> THEN m.away_score ELSE 0 END)
  ```

## Match Stages
- Stages in order: Qualifying → Group Stage → Round of 16 → Quarter-finals → Semi-finals → Final
- Not all seasons have all stages. Earlier seasons (2018–2021) may lack some knockout rounds.
- To filter by competition phase: `WHERE stage = 'Group Stage'` for group phase, `WHERE stage IN ('Round of 16', 'Quarter-finals', 'Semi-finals', 'Final')` for knockout phase.

## Head-to-Head
- For head-to-head queries between two teams, join `cl_matches` twice to `cl_teams` (as home and away aliases):
  ```sql
  SELECT * FROM cl_matches m
  JOIN cl_teams home ON m.home_team_id = home.id
  JOIN cl_teams away ON m.away_team_id = away.id
  WHERE (LOWER(home.name) LIKE '%team_a%' AND LOWER(away.name) LIKE '%team_b%')
     OR (LOWER(home.name) LIKE '%team_b%' AND LOWER(away.name) LIKE '%team_a%')
  ```

## Derived Data (from raw matches)

Standings, group tables, tournament winners, and advancement can all be **derived via SQL** from match results. There are no pre-computed tables for these.

### Match Result Determination
```sql
CASE
  WHEN m.home_score > m.away_score THEN 'home_win'
  WHEN m.away_score > m.home_score THEN 'away_win'
  ELSE 'draw'
END
```

### Group Stage Standings (pre-2024)
Before 2024, the UCL had 8 groups of 4 teams. Each team played 6 group matches (home and away against each other team). Points: 3 for win, 1 for draw, 0 for loss. Top 2 in each group advanced to Round of 16.

Compute standings per group:
```sql
SELECT t.name, m.group_name,
  SUM(CASE WHEN m.home_team_id = t.id AND m.home_score > m.away_score THEN 3
           WHEN m.away_team_id = t.id AND m.away_score > m.home_score THEN 3
           WHEN m.home_team_id = t.id AND m.home_score = m.away_score THEN 1
           WHEN m.away_team_id = t.id AND m.away_score = m.home_score THEN 1
           ELSE 0 END) AS points,
  SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score
           WHEN m.away_team_id = t.id THEN m.away_score ELSE 0 END) AS goals_for,
  SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score
           WHEN m.away_team_id = t.id THEN m.home_score ELSE 0 END) AS goals_against
FROM cl_teams t
JOIN cl_matches m ON t.id IN (m.home_team_id, m.away_team_id)
WHERE m.season_id = <year> AND m.stage = 'Group Stage' AND m.group_name IS NOT NULL
GROUP BY t.id, t.name, m.group_name
ORDER BY m.group_name, points DESC, (goals_for - goals_against) DESC;
```

### Group Phase / League Phase (2024+)
From 2024 onwards, the UCL uses a 36-team single-league format. Each team plays 8 matches (not all teams play each other). Teams are ranked 1-36 in a single table. Top 8 advance directly to Round of 16. Teams ranked 9-24 enter a knockout playoff.

Derive the league table with the same points logic, but GROUP BY team only (no group_name), filtering `stage = 'Group Stage'`.

### Tournament Winner (Champion)
Find the Final match for the season. The winner is the team that scored more goals. If the Final went to penalties (status = 'PEN'), the team listed under a different system — but the scoreline still reflects the score before/during extra time.

Query pattern:
```sql
SELECT home.name AS home_team, m.home_score,
       away.name AS away_team, m.away_score,
       CASE WHEN m.home_score > m.away_score THEN home.name
            WHEN m.away_score > m.home_score THEN away.name END AS champion
FROM cl_matches m
JOIN cl_teams home ON m.home_team_id = home.id
JOIN cl_teams away ON m.away_team_id = away.id
WHERE m.season_id = <year> AND m.stage = 'Final'
  AND m.status IN ('FINISHED', 'AET', 'PEN');
```

### Knockout Advancement (Round of 16, Quarter-finals, Semi-finals)
These are two-legged ties played home-and-away. The team with the higher **aggregate score** across both legs advances. If aggregate is level after extra time, the match goes to penalties.

To determine who advanced from each knockout round, group matches by the two participating teams, compute aggregate, and compare.

Simple approximation: For each pair of teams in a knockout round, the team that scores more total goals across both legs wins. If tied, use the team that won on the night (the one with status = 'PEN' win or away goals). For most queries that just need "who reached the semi-finals?" or "who won the tie?", use aggregate scoring:

```sql
SELECT season_id, stage,
  LEAST(home_team_id, away_team_id) AS team_a,
  GREATEST(home_team_id, away_team_id) AS team_b,
  SUM(CASE WHEN home_team_id = LEAST(home_team_id, away_team_id) THEN home_score ELSE 0 END +
      CASE WHEN away_team_id = LEAST(home_team_id, away_team_id) THEN away_score ELSE 0 END) AS team_a_goals,
  SUM(CASE WHEN home_team_id = GREATEST(home_team_id, away_team_id) THEN home_score ELSE 0 END +
      CASE WHEN away_team_id = GREATEST(home_team_id, away_team_id) THEN away_score ELSE 0 END) AS team_b_goals
FROM cl_matches
WHERE season_id = <year> AND stage = 'Round of 16'
GROUP BY season_id, stage, team_a, team_b;
```

For easier head-to-head comparisons, you can also simply identify the winner of each individual knockout match and check the status for penalty shootout winners.

## Limitations
- No player-level or individual statistics are available. Only team-level match scores.
- Half-time scores are not available.
- Match dates are not meaningful (all placeholder values).
- `cl_standings` table exists but is empty — do not use it.
