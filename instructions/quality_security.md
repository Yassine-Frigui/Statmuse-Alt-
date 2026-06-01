# Qualité du Code & Sécurité — NBA Query Engine

## 1. Qualité du code

| Critère | Pratique |
|---------|----------|
| Architecture | MVC — Controllers minces, logique dans Services |
| Validation | Form Requests Laravel (`ChatbotRequest`) |
| Gestion d'erreurs | Try/catch sur appels Gemini, pages 403/404/500 personnalisées |
| Style | PSR-12, nommage explicite en anglais |
| Git | Commits atomiques (feat:, fix:, test:, docs:) |

## 2. Sécurité

| Mesure | Implémentation |
|--------|---------------|
| Authentification | Laravel Breeze ou Sanctum |
| CSRF | Protection active sur tous les formulaires |
| Injection SQL | Eloquent / Query Builder paramétré exclusivement |
| Clés API Gemini | Stockées dans `.env` — jamais commitées |
| Rôles | Spatie Laravel Permission (recommandé) |
| Rate limiting | Sur endpoint `/api/chatbot` pour éviter abus |

## 3. SonarCloud (Optionnel — Bonus)

### Étapes

1. Créer un compte SonarCloud (sonarcloud.io)
2. Lier le repository GitHub (OAuth)
3. Créer un projet et récupérer le SONAR_TOKEN
4. Ajouter `sonar-project.properties` à la racine
5. Configurer GitHub Actions (`.github/workflows/sonar.yml`)
6. Vérifier le dashboard après chaque push

### Fichier sonar-project.properties

```properties
sonar.projectKey=nba-query-engine
sonar.organization=mon-organisation
sonar.host.url=https://sonarcloud.io
sonar.php.coverage.reportPaths=coverage.xml
```

### Barème attendu

| Critère | Barème |
|---------|--------|
| Bugs | 0 |
| Vulnérabilités | 0 |
| Code Smells | Minimisés |
| Couverture | >= 40% |
| Duplication | < 5% |
