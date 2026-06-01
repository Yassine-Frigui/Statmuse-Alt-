# Business Logic — NBA Query Engine

## 1. Vue d'ensemble

Le NBA Query Engine est un système RAG-like spécialisé : les questions utilisateur sont transformées en requêtes structurées via LLM, exécutées sur la base de données NBA/ABA, puis les résultats sont formatés pour l'affichage.

```
Question NL → [LLM] → Structured Query → [SQL/Eloquent] → Data → [LLM] → Réponse
```

## 2. Schéma de la Base de Données

### 2.1 Modèle Conceptuel de Données (MCD)

```
┌─────────────┐       ┌──────────────────┐       ┌──────────────┐
│   Player    │       │ PlayerSeasonStat │       │    Team      │
├─────────────┤       ├──────────────────┤       ├──────────────┤
│ id          │──1:N──│ player_id        │       │ id           │
│ first_name  │       │ team_id          │──N:1──│ name         │
│ last_name   │       │ season_id        │       │ city         │
│ position    │       │ season_id        │       │ abbreviation │
│ height      │       │ games_played     │       │ conference   │
│ weight      │       │ points           │       │ division     │
│ birth_date  │       │ rebounds         │       │ founded_year │
│ college     │       │ assists          │       │ arena        │
│ drafted_year│       │ steals           │       └──────────────┘
│ bio         │       │ blocks                  │
└──────┬──────┘       │ minutes                 │
       │              │ fg_pct                  │
       │              │ three_pct               │
       │              │ ft_pct                  │
       │              └──────────────────────────┘
       │                                         
       │  ┌──────────────────┐       ┌──────────────┐
       │  │ Championship     │       │   Season     │
       │  ├──────────────────┤       ├──────────────┤
       │  │ id               │       │ id           │
       │  │ season_id        │──N:1──│ year         │
       │  │ champion_team_id │       │ start_date   │
       │  │ runner_up_team_id│       │ end_date     │
       │  │ mvp_id           │──N:1──│ label        │
       │  │ result_label     │       └──────────────┘
       │  └──────────────────┘
       │
       │  ┌──────────────┐       ┌──────────────────┐
       │  │    Award     │       │  PlayerAward     │
       │  ├──────────────┤       ├──────────────────┤
       │  │ id           │       │ id               │
       │  │ name         │──1:N──│ player_id        │
       │  │ description  │       │ award_id         │
       │  └──────────────┘       │ season_id        │
       │                         └──────────────────┘
       │
       │  ┌──────────────┐       ┌──────────────────┐
       │  │    Coach     │       │  TeamSeasonCoach  │
       │  ├──────────────┤       ├──────────────────┤
       │  │ id           │       │ id               │
       │  │ first_name   │──1:N──│ coach_id         │
       │  │ last_name    │       │ team_id          │
       │  └──────────────┘       │ season_id        │
       │                         │ games            │
       │                         │ wins             │
       │                         │ losses           │
       │                         └──────────────────┘
       │
       │  ┌──────────────┐       ┌──────────────────┐
       │  │    Game      │       │   CorpusEntry    │
       │  ├──────────────┤       ├──────────────────┤
       │  │ id           │       │ id               │
       │  │ date         │       │ title            │
       │  │ home_team_id │       │ content (text)   │
       │  │ away_team_id │       │ category         │
       │  │ home_score   │       │ tags (JSON)      │
       │  │ away_score   │       │ source           │
       │  │ season_id    │       └──────────────────┘
       │  │ stage        │
       │  └──────────────┘
       │
       │  ┌──────────────────┐
       │  │ Conversation      │
       │  ├──────────────────┤
       │  │ id               │
       │  │ user_id          │
       │  │ messages (JSON)  │
       │  │ created_at       │
       │  └──────────────────┘
```

### 2.2 Détail des tables

**players** — Informations biographiques et carrière des joueurs NBA/ABA.
- Index : `(last_name, first_name)`, `drafted_year`

**teams** — Franchises NBA et ABA, actives ou historiques.
- Index : `abbreviation`, `conference`

**seasons** — Saisons NBA (ex: 1997-98).
- Index : `year`

**player_season_stats** — Statistiques cumulées d'un joueur pour une saison.
- Index composite : `(player_id, season_id)`, `(team_id, season_id)`, `points`, `rebounds`, `assists`

**championships** — Résultats des finales NBA/ABA.
- Index : `season_id`, `champion_team_id`

**awards** — Définition des récompenses (MVP, ROY, DPOY, 6MOY, etc.).

**player_awards** — Attribution des récompenses.
- Index : `(player_id, award_id, season_id)`

**coaches** — Informations sur les coaches.

**team_season_coach** — Association coach/équipe/saison avec bilan.

**games** — Matchs individuels.
- Index : `date`, `(home_team_id, away_team_id)`, `season_id`

**corpus_entries** — Corpus textuel pour les questions historiques/règles.
- Index fulltext : `content`, `category`

**conversations** — Historique des sessions utilisateur.
- `messages` : JSON array de `{role, content, structured_query, retrieved_data}`

## 3. Pipeline de traitement

### 3.1 Query Understanding (`QueryUnderstandingService`)

```
Entrée : String NL
Sortie : { intent: IntentType, entities: array, constraints: array }
Processus :
  1. Envoyer la question à Gemini avec un prompt de classification
  2. Parser la réponse JSON
  3. Valider l'intent (doit être dans IntentType enum)
  4. Retourner le résultat structuré
```

### 3.2 Query Transformation (`QueryTransformationService`)

```
Entrée : { intent, entities, constraints }
Sortie : StructuredQuery (objet/array)
Processus :
  1. Construire un prompt Gemini avec le contexte NBA
  2. Demander la conversion en structured query
  3. Valider la structure (metric, filters, limit, sort)
  4. Retourner la requête structurée
```

### 3.3 Corpus Retrieval (`CorpusRetrievalService`)

```
Entrée : StructuredQuery
Sortie : Collection Eloquent
Processus :
  1. Analyser l'intent → déterminer la table principale
  2. Appliquer les filtres (WHERE)
  3. Appliquer le tri (ORDER BY)
  4. Appliquer la limite (LIMIT)
  5. Exécuter la requête Eloquent
  6. Retourner les résultats
```

### 3.4 Response Formatting (`ResponseFormatterService`)

```
Entrée : { structured_query, retrieved_data, original_question }
Sortie : String (réponse formatée)
Processus :
  1. Envoyer les données brutes à Gemini
  2. Prompt : "Formate ces données NBA en réponse naturelle"
  3. Retourner la réponse formatée
  4. Sauvegarder dans conversation.messages
```

## 4. Implémentation Laravel

### 4.1 Contrôleur

```php
class ChatbotController extends Controller
{
    public function __construct(
        private QueryUnderstandingService $understandingService,
        private QueryTransformationService $transformationService,
        private CorpusRetrievalService $retrievalService,
        private ResponseFormatterService $formatterService
    ) {}

    public function ask(ChatbotRequest $request)
    {
        $question = $request->input('message');

        $analysis = $this->understandingService->analyze($question);
        $structuredQuery = $this->transformationService->transform($analysis);
        $data = $this->retrievalService->retrieve($structuredQuery);
        $reply = $this->formatterService->format($structuredQuery, $data, $question);

        // Sauvegarder la conversation
        // Retourner la réponse

        return response()->json([
            'reply' => $reply,
            'data' => $data,
            'conversation_id' => $conversation->id,
        ]);
    }
}
```

### 4.2 Enum IntentType

```php
enum IntentType: string
{
    case RankingQuery = 'ranking_query';
    case PlayerInfo = 'player_info';
    case TeamInfo = 'team_info';
    case ChampionshipQuery = 'championship_query';
    case HistoricalEvent = 'historical_event';
    case ComparisonQuery = 'comparison_query';
    case SeasonStats = 'season_stats';
    case HeadToHead = 'head_to_head';
    case AwardQuery = 'award_query';
    case RuleExplanation = 'rule_explanation';
}
```

### 4.3 GeminiService

Service générique pour les appels à l'API Gemini.

```php
class GeminiService
{
    public function analyze(string $prompt): array;
    public function transform(array $context): array;
    public function format(array $data, string $question): string;
}
```

Chaque méthode utilise un prompt système différent selon l'étape du pipeline.

## 5. Data Ingestion — Alimentation du Corpus

### 5.1 Problématique

Le moteur de requêtes repose sur un corpus NBA/ABA en base de données. Les données doivent être ingérées de manière fiable et traçable. Trois sources sont couvertes.

### 5.2 Sources de données

| Source | Type | Contenu | Méthode |
|--------|------|---------|---------|
| **data.nba.com (NBA CDN)** | CDN gratuit (0 req/min limit) | Saisons 2015-2024 : ~1400 matchs/saison, scores, scoring leaders | `NbaApiService` via Guzzle |
| **CSV/JSON statiques** | Fichiers locaux | Données historiques (légendes, ABA, championnats anciens) | `php artisan db:seed --class=NbaCorpusSeeder` |
| **Corpus textuel** | Fichiers JSON | Règles, événements historiques, biographies | Import via commande artisan |

### 5.3 Architecture d'ingestion

```
┌──────────────┐     ┌───────────────────┐     ┌──────────────┐
│ balldontlie  │────▶│ DataIngestion     │────▶│  MySQL DB    │
│ API          │     │ Service           │     │  (Corpus)    │
├──────────────┤     │                   │     └──────────────┘
│ CSV/JSON     │────▶│ - fetchApiData()  │
│ (repo/local) │     │ - importCsv()     │
├──────────────┤     │ - validate()      │
│ Corpus TXT   │────▶│ - deduplicate()   │
└──────────────┘     │ - logIngestion()  │
                     └───────────────────┘
```

### 5.4 API data.nba.com (NBA CDN — 10 saisons)

**Endpoint** : `https://data.nba.com/data/10s/v2015/json/mobile_teams/nba/{season}/league/00_full_schedule.json`

Le CDN officiel de la NBA couvre les saisons 2015-2024. Données disponibles par match :

| Champ | Description |
|-------|-------------|
| `gid` | ID unique du match |
| `gdte` | Date du match |
| `h.tid` / `v.tid` | IDs API des équipes (ex: 1610612739) |
| `h.s` / `v.s` | Scores finaux |
| `ptsls.pl` | Meilleurs scoreurs du match (nom, équipe, points) |
| `an` | Aréna |
| `st` / `stt` | Statut (3 = Final) |

**Données non disponibles** via ce CDN :
- Boxscores complets (rebonds, passes, % tirs) — endpoints 403
- Stats joueurs avant 2015

**Commandes artisan :**
```bash
php artisan nba:ingest --source=api --season=2024    # 1 saison
php artisan nba:ingest --source=api                   # 2021-2024 par défaut
php artisan nba:ingest --source=api --seasons=2015,2016,2017,2018,2019,2020,2021,2022,2023,2024
php artisan nba:ingest --sync-teams                   # Mapper IDs API → DB
```

### 5.5 Données historiques (CSV/JSON)

Fichiers dans `database/data/` :

```
database/data/
├── players_historical.csv    (Wilt, Russell, Magic, Bird, Jordan…)
├── teams_aba.csv             (Spirits of St. Louis, Kentucky Colonels…)
├── championships.csv         (Tous les champions NBA/ABA)
├── awards.csv                (MVP, ROY, DPOY par saison)
├── coaches_historical.csv    (Red Auerbach, Phil Jackson, Pop…)
└── corpus_entries.json       (Texte structuré : règles, merger ABA-NBA…)
```

**Commande artisan :**
```bash
php artisan nba:ingest --source=csv --file=database/data/championships.csv
```

### 5.6 Service d'ingestion

```php
class DataIngestionService
{
    public function fromApi(string $type, ?int $season = null): int;
    public function fromCsv(string $filePath): int;
    public function fromJson(string $filePath): int;
    public function validate(array $record): bool;
    public function deduplicate(string $model, array $criteria): bool;
}
```

### 5.7 Pipeline de validation

Chaque enregistrement importé passe par :

1. **Validation** — Vérification des champs obligatoires, types, contraintes
2. **Déduplication** — Éviter les doublons (ex: même joueur via API vs CSV)
3. **Normalisation** — Standardiser les noms, dates, unités
4. **Logging** — Journaliser chaque import (table `ingestion_logs`)

### 5.8 Table ingestion_logs

```
ingestion_logs
├── id
├── source (string: "balldontlie"|"csv"|"json")
├── type (string: "players"|"teams"|"stats"|...)
├── records_processed (int)
├── records_inserted (int)
├── records_skipped (int)
├── errors (JSON — enregistrements en échec)
├── duration_ms (int)
├── created_at
└── updated_at
```

### 5.9 Volume de données actuel (après fetch)

| Entité | Quantité | Source |
|--------|----------|--------|
| Teams | 34 (30 NBA + 4 ABA historiques) | CSV |
| Seasons | 35 (1990-91 à 2024-25) | CSV + API |
| Games | **13,605** | API (2015-2024 : ~1,360 matchs/saison) |
| Players | **708** | API (scoring leaders) + CSV (légendes) |
| Game Player Stats | **14,708** | API (performances individuelles/match) |
| Season Player Stats | **2,123** | API (accumulé par saison/joueur) |
| Championships | 31 | CSV |
| Corpus Entries | 8 | CSV |

### 5.10 Limites et extensions possibles

- **data.nba.com** ne fournit que les scores et meilleurs scoreurs (pas de boxscores complets)
- Coverage : 2015-2024 (10 saisons). Avant 2015 : données historiques uniquement
- **Extension possible** : balldontlie API (payant, $9.99/mois) pour boxscores complets + stats avancées
- **Extension possible** : scraper basketball-reference.com pour données pré-2015

## 6. Gestion des erreurs

| Situation | Comportement |
|-----------|-------------|
| API Gemini indisponible | Retourner message d'erreur utilisateur + log |
| Timeout Gemini | Retenter 1x, puis retourner erreur |
| Quota dépassé | Retourner message explicite |
| Aucune donnée trouvée | "No data found for your query" |
| Question hors domaine | "Please ask a basketball-related question" |
| Structured query invalide | Logger + retourner erreur générique |

## 7. Seeders

Le seeder `NbaCorpusSeeder` doit fournir un jeu de données réaliste :

- Au moins 50 joueurs (légendes NBA/ABA)
- Au moins 10 équipes historiques
- 10+ saisons de stats
- 5+ championnats avec détails
- 3+ awards (MVP, ROY, DPOY)
- 5+ corpus_entries (histoire NBA, règles, événements)
- 10+ matchs simulés
