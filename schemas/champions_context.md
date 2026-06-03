# Data Boundaries

Our dataset covers:
- **Seasons**: 2018-2019 to 2025-2026 (season IDs 2018 to 2025)
- **Matches**: Completed match results for all stages (Qualifying through Final)
- **Total matches**: ~1,250 match records

## What we have
- Team names and match scores
- Full-time scores (home vs away goals)
- Match stages (Qualifying, Group Stage, Round of 16, Quarter-finals, Semi-finals, Final)
- Season information (name, start/end dates)

## What we DON'T have
- No player data or individual statistics
- No half-time scores
- No meaningful match dates (dates are placeholder values; use `season_id` instead)
- No team countries or metadata beyond team name
- No data before season 2018

## What CAN be derived via SQL
Standings, group tables, tournament winners, and advancement can all be computed from match results. See RULES for query patterns.

If the user asks about something outside our data boundaries, respond saying the data isn't available.

# Examples

## E1: Team Match Results in a Season
- **User:** Show me all of Liverpool's match results in 2023
- **SQL:**
  ```sql
  SELECT m.season_id, m.stage,
         home.name AS home_team, m.home_score,
         away.name AS away_team, m.away_score,
         m.status
  FROM cl_matches m
  JOIN cl_teams home ON m.home_team_id = home.id
  JOIN cl_teams away ON m.away_team_id = away.id
  WHERE m.season_id = 2023
    AND (LOWER(home.name) LIKE '%liverpool%' OR LOWER(away.name) LIKE '%liverpool%')
    AND m.status IN ('FINISHED', 'AET', 'PEN')
  ORDER BY m.matchday;
  ```

## E2: Top Scoring Teams in a Season
- **User:** Which team scored the most goals in 2024?
- **SQL:**
  ```sql
  SELECT t.name,
         SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE 0 END +
             CASE WHEN m.away_team_id = t.id THEN m.away_score ELSE 0 END) AS total_goals,
         COUNT(*) AS matches_played
  FROM cl_teams t
  JOIN cl_matches m ON t.id IN (m.home_team_id, m.away_team_id)
  WHERE m.season_id = 2024
    AND m.status IN ('FINISHED', 'AET', 'PEN')
  GROUP BY t.id, t.name
  ORDER BY total_goals DESC
  LIMIT 10;
  ```

## E3: Head-to-Head Record
- **User:** What is the head-to-head record between Barcelona and Real Madrid in the Champions League?
- **SQL:**
  ```sql
  SELECT m.season_id, m.stage,
         home.name AS home_team, m.home_score,
         away.name AS away_team, m.away_score,
         CASE WHEN m.home_score > m.away_score THEN home.name
              WHEN m.away_score > m.home_score THEN away.name
              ELSE 'Draw' END AS winner
  FROM cl_matches m
  JOIN cl_teams home ON m.home_team_id = home.id
  JOIN cl_teams away ON m.away_team_id = away.id
  WHERE (LOWER(home.name) LIKE '%barcelona%' AND LOWER(away.name) LIKE '%real madrid%')
     OR (LOWER(home.name) LIKE '%real madrid%' AND LOWER(away.name) LIKE '%barcelona%')
    AND m.status IN ('FINISHED', 'AET', 'PEN')
  ORDER BY m.season_id;
  ```

## E4: Group Standings (Derived)
- **User:** Who finished top of Group A in the 2022 Champions League?
- **SQL:**
  ```sql
  SELECT t.name,
         SUM(CASE WHEN m.home_team_id = t.id AND m.home_score > m.away_score THEN 3
                  WHEN m.away_team_id = t.id AND m.away_score > m.home_score THEN 3
                  WHEN m.home_team_id = t.id AND m.home_score = m.away_score THEN 1
                  WHEN m.away_team_id = t.id AND m.away_score = m.home_score THEN 1
                  ELSE 0 END) AS points,
         SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score
                  WHEN m.away_team_id = t.id THEN m.away_score ELSE 0 END) AS goals_for,
         SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score
                  WHEN m.away_team_id = t.id THEN m.home_score ELSE 0 END) AS goals_against,
         SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score
                  WHEN m.away_team_id = t.id THEN m.away_score ELSE 0 END) -
         SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score
                  WHEN m.away_team_id = t.id THEN m.home_score ELSE 0 END) AS goal_diff
  FROM cl_teams t
  JOIN cl_matches m ON t.id IN (m.home_team_id, m.away_team_id)
  WHERE m.season_id = 2022 AND m.stage = 'Group Stage' AND m.group_name = 'A'
    AND m.status IN ('FINISHED', 'AET', 'PEN')
  GROUP BY t.id, t.name
  ORDER BY points DESC, goal_diff DESC, goals_for DESC;
  ```

## E5: Tournament Winner (Derived)
- **User:** Who won the Champions League in 2023?
- **SQL:**
  ```sql
  SELECT s.name AS season,
         home.name AS home_team, m.home_score,
         away.name AS away_team, m.away_score,
         m.status,
         CASE WHEN m.home_score > m.away_score THEN home.name
              WHEN m.away_score > m.home_score THEN away.name
              ELSE 'Draw (winner decided by penalties)' END AS champion
  FROM cl_matches m
  JOIN cl_teams home ON m.home_team_id = home.id
  JOIN cl_teams away ON m.away_team_id = away.id
  JOIN cl_seasons s ON m.season_id = s.id
  WHERE m.season_id = 2023 AND m.stage = 'Final'
    AND m.status IN ('FINISHED', 'AET', 'PEN');
  ```
