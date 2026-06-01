# Query Engine / Chatbot — Spécifications

## 1. Objectif

Interface de chat pour le NBA Query Engine. Contrairement à un chatbot classique, le LLM ne génère pas les réponses de sa propre connaissance — il transforme la question en requête structurée, puis formate les données récupérées du corpus NBA/ABA.

## 2. API IA : Google Gemini

| Critère | Valeur |
|---------|--------|
| API | Gemini API |
| SDK | `google/generative-ai` via Composer ou HTTP (Guzzle) |
| Alternative | OpenAI / Ollama / Claude |
| Usage | NLU (intent + entities) + transformation + formatting |

## 3. Flux de fonctionnement

| Étape | Description |
|-------|-------------|
| 1 | Utilisateur pose une question NBA (ex: "Who scored the most points in the 1997 Finals?") |
| 2 | `QueryUnderstandingService` → Gemini extrait intent, entités, contraintes |
| 3 | `QueryTransformationService` → Gemini convertit en structured query JSON |
| 4 | `CorpusRetrievalService` → Eloquent interroge la base NBA |
| 5 | `ResponseFormatterService` → Gemini formate les données en réponse lisible |
| 6 | Réponse renvoyée à l'interface chat |

## 4. Structure du Prompt — Query Understanding

```json
{
  "system": "You are an NBA query analyzer. Extract intent, entities, and constraints.",
  "user_query": "Who scored the most points in the 1997 NBA Finals?",
  "expected_output": {
    "intent": "top_scorer_in_series",
    "entities": {
      "competition": "NBA Finals",
      "year": 1997
    },
    "constraints": {
      "metric": "points",
      "rank": 1
    }
  }
}
```

## 5. Structured Query (sortie de Query Transformation)

```
User: "Top 5 scorers in the 1990s"

→ Structured Query:
{
  "intent": "ranking_query",
  "metric": "points",
  "period": {
    "type": "decade",
    "start_year": 1990,
    "end_year": 1999
  },
  "limit": 5,
  "sort": "descending",
  "group_by": "player"
}
```

## 6. Intents supportés

| Intent | Description | Exemple |
|--------|-------------|---------|
| `ranking_query` | Classement par métrique | "Top 10 scorers all-time" |
| `player_info` | Info sur un joueur | "Tell me about Michael Jordan" |
| `team_info` | Info sur une équipe | "History of the Lakers" |
| `championship_query` | Info championnat | "Who won in 1998?" |
| `historical_event` | Événement historique | "Explain the ABA-NBA merger" |
| `comparison_query` | Comparaison | "Who has more rings: Jordan or LeBron?" |
| `season_stats` | Stats d'une saison | "LeBron's stats in 2012" |
| `head_to_head` | Face à face | "Lakers vs Celtics head to head 2023" |
| `award_query` | Info sur les awards | "List of MVP winners" |

## 7. Contraintes

- Réponses dynamiques — aucune réponse hardcodée
- Gestion des erreurs obligatoire (API indisponible, timeout, quota dépassé)
- Historique de la conversation transmis pour maintenir le contexte
- Interface web intégrée : fenêtre de chat embarquée
- Le LLM ne doit JAMAIS répondre sans que les données du corpus soient récupérées

## 8. Endpoints API

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | `/api/chatbot` | Envoyer une question NBA |
| GET | `/chatbot` | Interface web du query engine |

### 8.1 Requête POST /api/chatbot

```json
{
  "message": "Who scored the most points in the 1997 NBA Finals?"
}
```

### 8.2 Réponse

```json
{
  "reply": "Michael Jordan scored the most points in the 1997 NBA Finals with 163 points over 6 games (27.2 PPG).",
  "data": {
    "player": "Michael Jordan",
    "team": "Chicago Bulls",
    "points": 163,
    "games": 6,
    "avg": 27.2,
    "year": 1997
  },
  "conversation_id": 42
}
```

## 9. Base de données — Conversations

```
conversations
├── id
├── user_id (FK)
├── messages (JSON — tableau de {role, content, structured_query, retrieved_data})
├── created_at
└── updated_at
```
