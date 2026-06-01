# Technical Architecture — NBA Query Engine

## 1. Stack Technologique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.x + Laravel 10/11 |
| Frontend | Blade + Bootstrap |
| Base de données | MySQL |
| ORM | Eloquent |
| Auth | Laravel Breeze / Sanctum |
| IA | Google Gemini API (NLU + formatting) |
| Tests | PHPUnit |

## 2. Architecture Pipeline

```
User (NL question)
    │
    ▼
┌──────────────────────────────────┐
│  Step 1: Chat Interface (Blade)  │
└──────────┬───────────────────────┘
           │ POST /api/chatbot
           ▼
┌──────────────────────────────────┐
│  Step 2: QueryUnderstanding      │
│  Service                         │
│  ─ Appel Gemini → extrait:       │
│    • Intent (ranking, history…)   │
│    • Entities (player, team…)    │
│    • Constraints (year, period…) │
└──────────┬───────────────────────┘
           ▼
┌──────────────────────────────────┐
│  Step 3: QueryTransformation     │
│  Service                         │
│  ─ NL → Structured Query (JSON)  │
│  ex: {"metric":"points",         │
│       "period":"1990s",          │
│       "limit":5,                 │
│       "sort":"desc"}             │
└──────────┬───────────────────────┘
           ▼
┌──────────────────────────────────┐
│  Step 4: CorpusRetrieval         │
│  Service                         │
│  ─ Eloquent queries basées       │
│    sur la structured query       │
│  ─ Tables : players, teams,      │
│    seasons, stats, championships │
└──────────┬───────────────────────┘
           ▼
┌──────────────────────────────────┐
│  Step 5: ResponseFormatter       │
│  Service                         │
│  ─ Appel Gemini → NL formaté     │
│  ─ Retour à l'interface chat     │
└──────────┬───────────────────────┘
           ▼
    User sees formatted answer
```

## 3. Structure des dossiers

```
app/
├── Http/
│   ├── Controllers/
│   │   └── ChatbotController.php
│   ├── Requests/
│   │   └── ChatbotRequest.php
│   └── Middleware/
├── Models/
│   ├── Player.php
│   ├── Team.php
│   ├── Season.php
│   ├── PlayerSeasonStat.php
│   ├── Championship.php
│   ├── Award.php
│   ├── Coach.php
│   ├── Game.php
│   └── Conversation.php
├── Services/
│   ├── QueryUnderstandingService.php
│   ├── QueryTransformationService.php
│   ├── CorpusRetrievalService.php
│   ├── ResponseFormatterService.php
│   ├── GeminiService.php
│   └── IntentClassifier.php
├── Enums/
│   └── IntentType.php
└── Providers/
resources/
├── views/
│   ├── layouts/
│   ├── auth/
│   ├── dashboard/
│   └── chatbot/
│       ├── index.blade.php
│       └── _message.blade.php
routes/
├── web.php
├── api.php
└── chatbot.php
database/
├── migrations/
├── factories/
└── seeders/
    └── NbaCorpusSeeder.php
tests/
├── Unit/
│   ├── Services/
│   └── Models/
└── Feature/
    ├── ChatbotTest.php
    └── ApiTest.php
```

## 4. Rôle spécifique du LLM (Gemini)

| Étape | Rôle du LLM |
|-------|-------------|
| Query Understanding | Extraire intent, entités, contraintes de la NL |
| Query Transformation | Convertir NL en structured query JSON |
| Response Formatting | Formater les données brutes en NL lisible |
| Jamais | Le LLM ne génère PAS de connaissances — les réponses viennent du corpus |

## 5. Base de Données

Voir `business_logic.md` pour le schéma complet.

### Migrations

- Toutes les migrations via `php artisan make:migration`
- `foreignId()` et `constrained()` pour les clés étrangères
- Indexation sur les colonnes de recherche (player name, team name, season year)

## 6. Authentification & Autorisation

- Laravel Breeze
- Middleware `auth` sur les routes protégées
- Policies Laravel pour les autorisations
- Rôles/permissions via Spatie Laravel Permission (recommandé)
