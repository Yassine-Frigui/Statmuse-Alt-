# NBA Query Engine — Architecture

## Overview

Natural language → structured query → database → formatted response.

The user types a basketball question in plain English. The system uses Google Gemini to understand the question, transforms it into a structured query, retrieves data via Eloquent, and formats the result.

---

## Pipeline

```
User Question
    │
    ▼
┌─────────────────────────────┐
│ 1. QueryUnderstandingService │  Gemini extracts intent, entities, constraints
│    POST /api/chatbot         │  Output: { intent, entities, constraints }
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 2. QueryTransformationService│  Gemini converts analysis into structured query
│                              │  Output: { intent_type, primary_table, filters, limit, order_by }
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 3. CorpusRetrievalService    │  Translates structured query to Eloquent
│                              │  Runs against MySQL database
└──────────┬──────────────────┘
           ▼
┌─────────────────────────────┐
│ 4. ResponseFormatterService  │  Gemini formats raw data into natural language
│                              │  Output: human-readable reply
└──────────┬──────────────────┘
           ▼
     JSON Response
     { reply, data, conversation_id }
```

---

## Key Components

### Services (app/Services/)

| Service | Responsibility |
|---------|---------------|
| `GeminiService` | HTTP wrapper for Google Gemini API. Methods: `analyze()`, `transform()`, `format()`, `chat()` |
| `QueryUnderstandingService` | Sends question to Gemini with system prompt to classify intent and extract entities |
| `QueryTransformationService` | Converts intent analysis into structured query JSON |
| `CorpusRetrievalService` | Maps structured query to Eloquent queries. Also provides `getRanking()`, `getPlayerInfo()`, `getTeamInfo()`, `getChampionship()`, `searchCorpus()` |
| `ResponseFormatterService` | Formats retrieved data into natural language via Gemini. Falls back to simple table formatting |
| `NbaApiService` | Fetches season schedules from data.nba.com CDN |
| `DataIngestionService` | Ingests data from CSV, JSON, or API into the database |
| `BoxscoreReconstructionService` | Reconstructs player boxscores from NBA play-by-play data |

### Models (app/Models/)

| Model | Table | Key Fields |
|-------|-------|------------|
| `Player` | players | first_name, last_name, position, height, weight, drafted_year |
| `Team` | teams | name, city, abbreviation, conference, division, nba_api_id |
| `Season` | seasons | year, label, start_date, end_date |
| `Game` | games | date, home_team_id, away_team_id, home_score, away_score, stage |
| `PlayerSeasonStat` | player_season_stats | player_id, season_id, points, rebounds, assists, games_played |
| `GamePlayerStat` | game_player_stats | game_id, player_id, points, rebounds, assists, steals, blocks |
| `Championship` | championships | season_id, champion_team_id, runner_up_team_id, mvp_player_id |
| `Award` | awards | name, description |
| `PlayerAward` | player_awards | player_id, award_id, season_id |
| `Coach` | coaches | first_name, last_name |
| `CorpusEntry` | corpus_entries | title, content, category, tags |
| `Conversation` | conversations | user_id, messages (JSON) |
| `WhatIfScenario` | what_if_scenarios | user_id, name, base_query, modifications, result_data, is_public |
| `IngestionLog` | ingestion_logs | source, type, records_processed, records_inserted, records_skipped |

### Controllers

| Controller | Endpoint | Action |
|-----------|----------|--------|
| `ChatbotController@ask` | `POST /api/chatbot` | Runs the full pipeline |
| `ChatbotController@index` | `/chatbot` | Returns the search page |
| `ChatbotController@history` | `GET /api/chatbot/history/{id}` | Returns conversation JSON |
| `DataController@index` | `/` and `/data` | Data browsing interface |

### Artisan Commands

| Command | Purpose |
|---------|---------|
| `php artisan nba:ingest` | Import data from API, CSV, or JSON |
| `php artisan nba:boxscore` | Reconstruct boxscores from PBP data |
| `php artisan nba:aggregate-stats` | Rebuild season stats from game stats |

---

## Supported Query Intents

| Intent | Example |
|--------|---------|
| `ranking_query` | "Top 10 scorers all-time" |
| `player_info` | "Tell me about Michael Jordan" |
| `team_info` | "History of the Lakers" |
| `championship_query` | "Who won in 1998?" |
| `historical_event` | "Explain the ABA-NBA merger" |
| `comparison_query` | "Who has more rings: Jordan or LeBron?" |
| `season_stats` | "LeBron's stats in 2012" |
| `head_to_head` | "Lakers vs Celtics head to head 2023" |
| `award_query` | "List of MVP winners" |
| `rule_explanation` | "How does the salary cap work?" |

---

## Data Sources

| Source | Data | Volume |
|--------|------|--------|
| data.nba.com (CDN) | Games, scores, scoring leaders (2015-2024) | 13,605 games |
| CSV files (database/data/) | Historical players, teams, championships, awards | 1,718 players, 34 teams |
| Corpus JSON | Rules, historical events, biographies | 24 entries |

---

## API

### POST /api/chatbot

Request:
```json
{ "message": "Who scored the most points in the 1997 NBA Finals?" }
```

Response:
```json
{
  "reply": "Michael Jordan scored 163 points in the 1997 NBA Finals...",
  "data": [{ "player": "Michael Jordan", "points": 163, "games": 6 }],
  "conversation_id": 42
}
```

Validation: `message` required, max 500 characters. Returns 422 on validation failure.

---

## Database

MySQL (configurable via .env). Migrations in `database/migrations/`. Seed data in `database/data/`.

Key indexing:
- `players`: (last_name, first_name), drafted_year
- `player_season_stats`: (player_id, season_id), (team_id, season_id), points, rebounds, assists
- `games`: date, (home_team_id, away_team_id), season_id

---

## Tests

```
php artisan test
```

| Suite | Tests | Coverage |
|-------|-------|----------|
| Unit/Models | Model scopes, accessors | 9 tests |
| Unit/Services | Retrieval service, formatting, intent enum | 12 tests |
| Feature/Chatbot | API validation, pipeline, auth | 5 tests |
| Feature/Auth | Login, register, password reset | 20 tests |

Gemini calls are mocked in tests — no real API calls during test execution.
