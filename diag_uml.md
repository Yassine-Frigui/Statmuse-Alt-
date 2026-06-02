# Diagrammes UML — NBA Query Engine

Ce document contient les descriptions textuelles des diagrammes UML pour le projet NBA Query Engine. Ces descriptions peuvent être utilisées pour générer les diagrammes correspondants avec un outil comme PlantUML, Mermaid, ou Lucidchart.

---

## 1. Diagramme de Cas d'Utilisation (Use Case)

### Acteurs

| Acteur | Description |
|--------|-------------|
| **Utilisateur non authentifié** | Peut effectuer des requêtes et consulter les résultats |
| **Utilisateur authentifié** | Peut en plus sauvegarder l'historique des conversations et créer des scénarios what-if |
| **Système Gemini (IA)** | Acteur secondaire — traite les requêtes NL et génère des requêtes SQL |

### Cas d'Utilisation

```
+-------------------------------------------+
|  NBA Query Engine                          |
|                                            |
|  +----------------------------+            |
|  | Effectuer une requête      |<----+     |
|  | en langage naturel         |     |     |
|  +----------------------------+     |     |
|  | - Poser une question       |     |     |
|  | - Obtenir une réponse      |     |     |
|  +----------------------------+     |     |
|                                      |     |
|  +----------------------------+     |     |
|  | Consulter l'historique     |<----+     |
|  | des conversations          |  (Auth)   |
|  +----------------------------+          |
|                                            |
|  +----------------------------+            |
|  | Comparer deux joueurs      |<----+     |
|  +----------------------------+     |     |
|                                      |     |
|  +----------------------------+     |     |
|  | Créer un scénario          |<----+     |
|  | what-if                    |  (Auth)   |
|  +----------------------------+          |
|                                            |
|  +----------------------------+            |
|  | Consulter les classements  |<----+     |
|  +----------------------------+     |     |
|                                      |     |
|  +----------------------------+     |     |
|  | Rechercher un joueur       |<----+     |
|  +----------------------------+          |
+-------------------------------------------+
                   |
          +--------+--------+
          | Système Gemini   |
          | (IA)             |
          +-----------------+
```

### Relations

| ID | Nom | Acteur principal | Acteur secondaire | Description |
|----|-----|------------------|-------------------|-------------|
| UC1 | Effectuer une requête | Utilisateur | Système Gemini | L'utilisateur pose une question ; le système utilise Gemini ou le pipeline local pour répondre |
| UC2 | Consulter l'historique | Utilisateur (Auth) | — | L'utilisateur authentifié consulte ses conversations passées |
| UC3 | Comparer deux joueurs | Utilisateur | — | L'utilisateur sélectionne deux joueurs pour une comparaison détaillée |
| UC4 | Créer un scénario what-if | Utilisateur (Auth) | — | L'utilisateur modifie des paramètres et visualise les impacts |
| UC5 | Consulter les classements | Utilisateur | — | L'utilisateur consulte les leaders statistiques |
| UC6 | Rechercher un joueur | Utilisateur | — | L'utilisateur recherche un joueur par nom |

---

## 2. Diagramme de Classes (Class Diagram)

### Paquetage : Modèles (app/Models)

```
+-------------------+       +-------------------+
|      Player       |       |     Team          |
+-------------------+       +-------------------+
| - id: int         |       | - id: int         |
| - first_name: str |       | - name: string    |
| - last_name: str  |       | - city: string    |
| - position: str   |       | - abbreviation: str|
| - height: str     |       | - conference: str |
| - weight: int     |       | - arena: string   |
| - college: str    |       | - founded_year: int|
| - drafted_year:int|       +-------------------+
+-------------------+       | + games()         |
| + seasonStats()   |       | + championships() |
| + awards()        |       +-------------------+
| + championships() |                |
+-------------------+                |
        |                            |
        | 1                        1 |
        |                           |
+-------|---------------------------|----+
|       |                           |    |
| +-----+------+       +-----------+--+ |
| |PlayerSeason |       |   Game       | |
| |Stat        |       +--------------+ |
| +------------+       | - date: date  | |
| - games_played|     | - home_score  | |
| - points     |       | - away_score  | |
| - rebounds   |       | - stage: str  | |
| - assists    |       +--------------+ |
| - steals     |       | + homeTeam()  | |
| - blocks     |       | + awayTeam()  | |
| - fg_pct     |       +--------------+ |
| - three_pct  |                |       |
| - ft_pct     |                |       |
+------------+                 |       |
| + player()  |                |       |
| + team()    |                |       |
| + season()  |                |       |
+------------+                 |       |
        |                      |       |
        | 1                    |       |
        |                      |       |
+-------|----------------------|---+   |
| Season |                      |   |   |
+--------+                      |   |   |
| - year: int                   |   |   |
| - label: string               |   |   |
+--------+                      |   |   |
| + games()                     |   |   |
| + stats()                     |   |   |
+--------+                      |   |   |
                                |   |   |
+-------------------+           |   |   |
|  Championship     |           |   |   |
+-------------------+           |   |   |
| - result_label    |           |   |   |
+-------------------+           |   |   |
| + season()        |           |   |   |
| + champion()      |-----------+   |   |
| + runnerUp()      |---------------+   |
| + mvp()           |-------------------+
+-------------------+

+-------------------+
|     Award         |       +-------------------+
+-------------------+       |  PlayerAward      |
| - name: string    |------>| - player_id: FK   |
| - description: text|      | - award_id: FK    |
+-------------------+       | - season_id: FK   |
                            +-------------------+

+-------------------+
|  Conversation     |
+-------------------+
| - user_id: FK     |
| - messages: json  |
+-------------------+

+-------------------+
| GamePlayerStat    |
+-------------------+
| - game_id: FK     |
| - player_id: FK   |
| - team_id: FK     |
| - points: int     |
| - rebounds: int   |
| - assists: int    |
| - minutes: string |
| - fg_pct: float   |
+-------------------+
```

### Paquetage : Services (app/Services)

```
+---------------------------+
|     NLQueryEngine          |
+---------------------------+
| - schema: array            |
| - allowedTables: array     |
| - trace: array             |
+---------------------------+
| + ask(question): array     |
| + getTrace(): array        |
| - tryGemini(): ?array      |
| - askLocally(): array      |
| - resolveEntities()        |
| - detectIntent()           |
| - buildQuery()             |
| - buildComparisonQuery()   |
| - buildLeadersQuery()      |
| - executeQuery()           |
| - buildLocalReply()        |
+---------------------------+

+---------------------------+
|     GeminiService          |
+---------------------------+
| - apiKey: string           |
| - endpoint: string         |
+---------------------------+
| + analyze(system, query)   |
| + transform(system, ctx)   |
| + format(system, data, q)  |
| + chat(system, messages)   |
| + generateContent(prompt)  |
| + generateInsight(prompt)  |
| - call(payload): array     |
+---------------------------+

+---------------------------+
|  PlayerProfileService      |
+---------------------------+
| - archetypeDefinitions     |
+---------------------------+
| + generateProfile(id)      |
| - calculateCareerStats()   |
| - determineArchetype()     |
| - identifyStrengths()      |
| - identifyWeaknesses()     |
| - generateScoutingReport() |
| - findPeakSeason()         |
| - calculateAdvancedMetrics |
+---------------------------+

+---------------------------+
| QueryUnderstandingService  |
+---------------------------+
| + understand(question)     |
| - classifyIntent()         |
| - extractEntities()        |
+---------------------------+

+---------------------------+
|  ResponseFormatterService  |
+---------------------------+
| + format(data, intent)     |
| + formatComparison(data)   |
+---------------------------+
```

### Paquetage : Contrôleurs (app/Http/Controllers)

```
+---------------------------+
|    ChatbotController       |
+---------------------------+
| - engine: NLQueryEngine    |
| - gemini: GeminiService    |
+---------------------------+
| + index()                  |
| + ask(ChatbotRequest)      |
| + insight(ChatbotRequest)  |
| + history(Conversation)    |
+---------------------------+

+---------------------------+
|    CompareController       |
+---------------------------+
| - profileService           |
+---------------------------+
| + index()                  |
| + compare(Request)         |
| - buildComparison()        |
+---------------------------+

+---------------------------+
|    PlayerController        |
+---------------------------+
| + index(Request)           |
| + search(Request)          |
| + show(Player)             |
+---------------------------+
```

---

## 3. Diagramme de Séquence — Requête Utilisateur

### Scénario : Utilisateur pose une question "Compare Michael Jordan and LeBron James"

```
Utilisateur        Frontend          ChatbotController    NLQueryEngine      GeminiService     Base de données
    |                  |                    |                    |                 |                 |
    |-- Question NL -->|                    |                    |                 |                 |
    |                  |-- POST /chatbot -->|                    |                 |                 |
    |                  |                    |-- ask(question) -->|                 |                 |
    |                  |                    |                    |                 |                 |
    |                  |                    |                    |-- tryGemini() ->|                 |
    |                  |                    |                    |                 |-- generateContent() |
    |                  |                    |                    |                 |<--- JSON réponse -|
    |                  |                    |                    |                 |                 |
    |                  |                    |                    |---- (si échec ou invalide)         |
    |                  |                    |                    |                   |               |
    |                  |                    |                    |-- askLocally()   |               |
    |                  |                    |                    |-- resolveEntities()              |
    |                  |                    |                    |----------------->|-- SELECT ------|
    |                  |                    |                    |<-- players ------|               |
    |                  |                    |                    |-- detectIntent() |               |
    |                  |                    |                    |-- buildComparisonQuery()          |
    |                  |                    |                    |-- executeQuery()                  |
    |                  |                    |                    |----------------->|-- SELECT ------|
    |                  |                    |                    |<-- data ---------|               |
    |                  |                    |                    |-- buildLocalReply()               |
    |                  |                    |                    |                   |               |
    |                  |                    |<--- reply ---------|                   |               |
    |                  |<--- JSON ----------|                    |                   |               |
    |<-- Résultat -----|                    |                    |                   |               |
```

### Scénario : Utilisateur compare deux joueurs (page /compare)

```
Utilisateur         Frontend (Blade)       CompareController    PlayerProfileService    MySQL
    |                     |                       |                     |                |
    |-- Sélectionne ----->|                       |                     |                |
    |   Joueur A et B    |                       |                     |                |
    |                     |-- POST /api/compare ->|                     |                |
    |                     |   {player_a_id,       |                     |                |
    |                     |    player_b_id}       |                     |                |
    |                     |                       |                     |                |
    |                     |                       |-- generateProfile(a)-->|             |
    |                     |                       |                     |-- SELECT ----->|
    |                     |                       |                     |<-- data -------|
    |                     |                       |                     |-- Analyser     |
    |                     |                       |<-- Profile A ------|                |
    |                     |                       |                     |                |
    |                     |                       |-- generateProfile(b)-->|             |
    |                     |                       |                     |-- SELECT ----->|
    |                     |                       |                     |<-- data -------|
    |                     |                       |                     |-- Analyser     |
    |                     |                       |<-- Profile B ------|                |
    |                     |                       |                     |                |
    |                     |                       |-- buildComparison() |                |
    |                     |                       |                     |                |
    |                     |<-- JSON {player_a, ---|                     |                |
    |                     |    player_b, compare} |                     |                |
    |                     |                       |                     |                |
    |-- Affiche ---------|                       |                     |                |
    |   profils riches   |                       |                     |                |
```

---

## 4. Diagramme d'Activités — Pipeline de Requête

```
+------------------+
|  Recevoir        |
|  question NL     |
+--------+---------+
         |
         v
+------------------+
|  Valider entrée  |
|  (max 500 car.)  |
+--------+---------+
         |
         v
+------------------+     OUI
|  API Key        |-------->+------------------+
|  disponible ?   |         |  Pipeline Gemini  |
+------------------+         |  - buildPrompt() |
         | NON               |  - generateContent|
         |                   |  - parseResponse()|
         v                   |  - validateQuery()|
+------------------+         +--------+---------+
|  Pipeline Local  |                  |
|  - extractYear() |                  | Échec ?
|  - resolveEnts() |                  v
|  - detectIntent()+<--------+------------------+
|  - buildQuery()  |         |  Pipeline Local  |
|  - executeQuery()|         |  (fallback)      |
|  - buildReply()  |         +------------------+
+--------+---------+                  |
         |                            |
         +----------+----------------+
                    |
                    v
          +------------------+
          |  Formater réponse |
          |  (Markdown/JSON)  |
          +--------+---------+
                   |
                   v
          +------------------+
          |  Retourner JSON  |
          |  {reply, data,   |
          |   intent, debug} |
          +------------------+
```

---

## 5. Diagramme de Déploiement (Deployment Diagram)

```
+----------------------------------------------------------+
|  Client Web                                                |
|  +---------------------+  +----------------------+        |
|  | Navigateur (Blade)  |  | Navigateur (React)   |        |
|  | Alpine.js + Tailwind|  | TanStack + Recharts  |        |
|  +----------+----------+  +----------+-----------+        |
+----------------------------------------------------------+
             |                           |
             | HTTP                      | HTTP
             v                           v
+----------------------------------------------------------+
|  Serveur Web (Apache / Nginx)                             |
|  +-----------------------------------------------------+ |
|  | Laravel Application (PHP 8.2)                        | |
|  | +-----------+ +-----------+ +--------------------+   | |
|  | | Routes    | | Middleware| | Controllers        |   | |
|  | | web/api   | | auth,sess | | (12 controlleurs)  |   | |
|  | +-----------+ +-----------+ +--------------------+   | |
|  |                        |                             | |
|  | +------------------------------------------------+  | |
|  | | Services Layer (10 services)                    |  | |
|  | | NLQueryEngine, GeminiService, PlayerProfile... |  | |
|  | +------------------------------------------------+  | |
|  |                        |                             | |
|  | +------------------------------------------------+  | |
|  | | Eloquent ORM (15 modèles)                      |  | |
|  | | Player, Team, Season, Game, Stats, Awards...   |  | |
|  | +------------------------------------------------+  | |
|  +-----------------------------------------------------+ |
+----------------------------------------------------------+
             |                              |
             | PDO                         | HTTPS
             v                              v
+---------------------+          +---------------------+
| MySQL / MariaDB     |          | Google Gemini API   |
| Base stat_muse      |          | gemini-2.5-flash    |
| 22 tables           |          | generateContent     |
| 1 718 joueurs       |          +---------------------+
| 13 605 matchs       |
+---------------------+
```

---

## 6. Diagramme de Packages

```
+-------------------------------------------+
|  nba-query-engine (Racine du projet)       |
|                                            |
|  +------------------+  +----------------+  |
|  | app/             |  | routes/        |  |
|  |  + Services/     |  |  web.php       |  |
|  |  + Http/         |  |  api.php       |  |
|  |    + Controllers/|  |  auth.php      |  |
|  |    + Requests/   |  |  console.php   |  |
|  |  + Models/       |  +----------------+  |
|  |  + Enums/        |                      |
|  |  + Providers/    |  +----------------+  |
|  |  + Console/      |  | database/      |  |
|  +------------------+  |  migrations/   |  |
|                         |  seeders/      |  |
|  +------------------+  +----------------+  |
|  | resources/       |                      |
|  |  + views/        |  +----------------+  |
|  |    + compare/    |  | basket-insights|  |
|  |    + chatbot/    |  | (React SPA)    |  |
|  |    + layouts/    |  |  src/          |  |
|  +------------------+  |  routes/       |  |
|                         |  components/   |  |
|  +------------------+  +----------------+  |
|  | config/          |                      |
|  | tests/           |  +----------------+  |
|  | vendor/          |  | scripts/       |  |
|  +------------------+  +----------------+  |
+-------------------------------------------+
```

---

## Notes pour la génération des diagrammes

Les descriptions ci-dessus peuvent être converties en diagrammes visuels avec les outils suivants :

- **PlantUML** : Copier les descriptions textuelles dans un fichier `.puml` et utiliser l'extension VS Code ou le serveur PlantUML.
- **Mermaid** : Utiliser la syntaxe Mermaid dans un fichier Markdown avec un renderer compatible (GitHub, GitLab, ou extension VS Code).
- **Lucidchart / Draw.io** : Utiliser les descriptions comme guide pour créer manuellement les diagrammes.

### Convention de couleurs suggérée

| Élément | Couleur |
|---------|---------|
| Entités/Modèles | Bleu clair (#E3F2FD) |
| Services | Vert clair (#E8F5E9) |
| Contrôleurs | Orange clair (#FFF3E0) |
| Frontend | Violet clair (#F3E5F5) |
| Base de données | Rouge clair (#FFEBEE) |
| Acteurs (UML) | Jaune clair (#FFFDE7) |
