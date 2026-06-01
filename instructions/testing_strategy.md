# Testing Strategy — NBA Query Engine

## 1. Framework

PHPUnit (intégré à Laravel)

- Commande : `php artisan test`
- Laravel HTTP Test Helpers
- Mockery / Faker

## 2. Types de tests

### A. Tests Unitaires (`tests/Unit/`)

| Cible | Description |
|-------|-------------|
| Models | Scopes Eloquent (ex: `topScorers()`, `championsByYear()`), accessors/mutators |
| Services | `QueryUnderstandingService`, `QueryTransformationService`, `CorpusRetrievalService`, `ResponseFormatterService` |
| IntentClassifier | Mapping NL → intent enum |
| GeminiService | Construction du prompt, parsing de la réponse |

**Exemple :**
```php
public function test_ranking_query_returns_top_scorers(): void
{
    Player::factory()->count(5)->hasStats(1, ['points' => 100])->create();
    Player::factory()->count(3)->hasStats(1, ['points' => 200])->create();

    $result = $this->corpusRetrievalService->getRanking('points', null, 5);

    $this->assertCount(5, $result);
    $this->assertEquals(200, $result->first()->points);
}
```

**Exemple — Transformation Service :**
```php
public function test_query_transformation_returns_structured_query(): void
{
    $result = $this->transformationService->transform(
        "Top 5 scorers in the 1990s"
    );

    $this->assertEquals('ranking_query', $result['intent']);
    $this->assertEquals('points', $result['metric']);
    $this->assertEquals(5, $result['limit']);
}
```

### B. Tests de Fonctionnalités (`tests/Feature/`)

| Cible | Description |
|-------|-------------|
| Routes | Statuts 200, 401, 403, 404, 422 |
| Auth | Routes protégées |
| Chatbot | Endpoint `/api/chatbot` avec questions simulées |
| Validation | `ChatbotRequest` — message requis, limite de taille |

**Exemple :**
```php
public function test_chatbot_returns_valid_response(): void
{
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/chatbot', [
        'message' => 'Who won the 1998 NBA Championship?'
    ]);
    $response->assertStatus(200)
             ->assertJsonStructure(['reply', 'data', 'conversation_id']);
}
```

**Exemple — test d'erreur :**
```php
public function test_chatbot_rejects_empty_message(): void
{
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/chatbot', [
        'message' => ''
    ]);
    $response->assertStatus(422);
}
```

### C. Tests API

- Tous les endpoints REST
- Structures JSON (`assertJsonStructure`)
- Cas d'erreur (données manquantes, 404)
- Authentification via Sanctum

## 3. Couverture de code

| Niveau | Couverture |
|--------|------------|
| Minimum | >= 40% |
| Recommandé | >= 60% |
| Excellent | >= 80% |

```bash
php artisan test --coverage
XDEBUG_MODE=coverage php artisan test --coverage --coverage-html=reports/coverage
```

## 4. Bonnes pratiques

- Un test = un comportement vérifié
- Factories Laravel pour les données de test NBA (PlayerFactory, TeamFactory, etc.)
- `RefreshDatabase` pour réinitialiser la BD entre tests
- Mocker les appels API Gemini (pas d'appels réels en test)
- Noms explicites : `test_ranking_query_returns_top_scorers()`
- Tester le pipeline complet : NL → structured query → retrieval → response
