01.Contexte & Objectifs
1.1 Contexte du projet
Dans le cadre du Projet Framework Web, les étudiants doivent concevoir et développer une application web complète basée sur le framework Laravel. Ce projet vise à consolider les compétences acquises tout au long de la formation en les mettant en œuvre dans un contexte applicatif réel et professionnel.

Le projet intègre obligatoirement :

Un backend et frontend complets développés avec Laravel

Le respect des bonnes pratiques de développement logiciel

Une démarche de qualité logicielle (tests, revue de code)

L'intégration d'un Chatbot IA intelligent connecté aux données métier

1.2 Objectifs pédagogiques
À l'issue du projet, l'étudiant sera capable de :

Développer une application web complète avec Laravel (MVC, Eloquent, Blade)

Concevoir une architecture propre et maintenable

Gérer une base de données relationnelle (MySQL/PostgreSQL)

Développer une interface utilisateur dynamique et responsive

Consommer une API externe (IA) et construire des prompts intelligents

Implémenter un chatbot fonctionnel répondant à des questions métier

Rédiger des tests unitaires et fonctionnels avec PHPUnit(Optionnel)

Analyser la qualité du code via SonarCloud(Optionnel)

Structurer et livrer un projet professionnel documenté

2.Sujet du Projet
2.1 Liberté du sujet
Le sujet est libre Critère principal : le projet doit contenir une logique métier réelle avec des données exploitables par le chatbot.

2.2 Exemples de sujets
Plateforme e-commerce (produits, commandes, clients)

Système de gestion académique (étudiants, cours, notes)

Application de réservation (hôtels, vols, événements)

Gestion d'entreprise (RH, projets, facturation)

Système hospitalier (patients, rendez-vous, médecins)

Bibliothèque numérique / médiathèque

3.Architecture & Technologies
3.1 Architecture imposée
Critère	Barème
Couche	Technologie
Backend	PHP 8.x + Laravel 10/11
Frontend	Blade + Bootstrap OU Vue.js OU React
Base de données	MySQL (recommandé) / PostgreSQL
ORM	Eloquent (intégré Laravel)
Auth	Laravel Breeze / Sanctum
TOTAL	Stack Web Complet
3.2 Pattern architectural
Architecture MVC strictement respectée

Séparation des responsabilités : Controllers, Models, Views, Services

Utilisation des Routes, Middleware, Policies Laravel

Eloquent pour toutes les interactions avec la base de données

4.Partie Chatbot IA — OBLIGATOIRE
4.1 Objectif du chatbot
Le chatbot doit être un assistant intelligent, ancré dans les données métier de l'application. Il ne s'agit pas d'un chatbot générique, mais d'un agent capable de comprendre le contexte applicatif et d'y répondre de manière pertinente.

4.2 APIs IA autorisées
Choisir une API parmi :

Google Gemini API (recommandé — quota gratuit généreux)

OpenAI API (GPT-4o)

Ollama (déploiement local)

Claude API (Anthropic)

4.3 Flux de fonctionnement imposé
Étape	Description
Étape 1	L'utilisateur pose une question en langage naturel
Étape 2	Laravel reçoit et traite la requête (Controller)
Étape 3	Récupération des données pertinentes depuis la base (Eloquent)
Étape 4	Construction d'un prompt intelligent (contexte + données + question)
Étape 5	Appel à l'API IA choisie via HTTP (Guzzle / fetch)
Étape 6	Retour de la réponse générée à l'interface utilisateur
4.4 Exemples de questions attendues
Recherche & Consultation
"Afficher mes commandes du mois de janvier"

"Existe-t-il un produit nommé X dans le catalogue ?"

"Combien d'étudiants sont inscrits en section A ?"

Disponibilité & Statut
"Ce produit est-il encore disponible en stock ?"

"Quels rendez-vous sont prévus pour demain ?"

Recommandation
"Quel produit me conseilles-tu selon mon historique ?"

"Quels cours sont disponibles pour mon niveau ?"

Analyse & Statistiques
"Quel est le produit le plus vendu ce mois-ci ?"

"Quel est le taux de satisfaction moyen des clients ?"

4.5 Contraintes techniques du chatbot
Les réponses doivent être dynamiques — aucune réponse codée en dur (hardcoded)

Le prompt doit obligatoirement contenir : le contexte métier, les données récupérées et la question utilisateur

La gestion des erreurs est obligatoire (API indisponible, timeout, quota dépassé)

L'historique de la conversation doit être transmis à l'API pour maintenir le contexte

4.6 Interface chatbot
Interface web intégrée : fenêtre de chat embarquée dans l'application (recommandé)
OU API REST testable : endpoint /api/chatbot accessible via Postman
Historique des conversations sauvegardé en base de données

4.7 Barème Chatbot
Critère	Barème
Intégration correcte de l'API IA	/5 pts
Pertinence et cohérence des réponses générées	/5 pts
Exploitation réelle des données de l'application	/5 pts
Qualité de l'interface / UX du chat	/5 pts
TOTAL	/20 pts
5.Tests Unitaires & Fonctionnels — OBLIGATOIRE
La partie tests est obligatoire et fait partie intégrante de l'évaluation. Un projet sans tests ne peut pas obtenir la note maximale.

5.1 Objectif des tests
Les tests garantissent la fiabilité, la maintenabilité et la qualité du code produit. Ils doivent être écrits de manière progressive et couvrir les fonctionnalités critiques de l'application.

5.2 Framework de test
PHPUnit (intégré à Laravel — aucune installation supplémentaire requise)

Commande d'exécution : php artisan test

Ou directement : ./vendor/bin/phpunit

Laravel HTTP Test Helpers (pour les tests d'intégration et API)

Mockery / Faker (pour les mocks et la génération de données de test)

5.3 Types de tests exigés
A. Tests Unitaires (Unit Tests)
Localisation : tests/Unit/

Tester les méthodes des Models (calculs, scopes Eloquent, accessors/mutators)

Tester les classes Service (logique métier isolée)

Tester la construction du prompt IA (validation du format et du contenu)

Tester les helpers et utilitaires custom

PHP
// Exemple : Test de la méthode getInStockProducts() d'un ProductService
public function test_returns_only_in_stock_products(): void {
    Product::factory()->count(3)->create(['stock' => 0]);
    Product::factory()->count(2)->create(['stock' => 10]);
    $result = $this->productService->getInStockProducts();
    $this->assertCount(2, $result);
}
B. Tests de Fonctionnalités (Feature Tests)
Localisation : tests/Feature/

Tester les routes et réponses HTTP (statuts 200, 401, 403, 404, 422)

Tester l'authentification et l'autorisation (routes protégées)

Tester les opérations CRUD via les formulaires et APIs

Tester les validations Laravel (Form Requests)

Tester l'endpoint du chatbot avec des questions simulées

PHP
// Exemple : Test de l'endpoint POST /api/chatbot
public function test_chatbot_returns_valid_response(): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/chatbot', [
        'message' => 'Quels produits sont disponibles ?'
    ]);
    $response->assertStatus(200)->assertJsonStructure(['reply']);
}
C. Tests API (si API REST Laravel exposée)
Tester tous les endpoints REST (GET, POST, PUT, DELETE)

Vérifier les structures JSON retournées (assertJsonStructure)

Tester les cas d'erreur (données manquantes, ressource introuvable)

Tester l'authentification via token (Sanctum / Bearer token)

5.4 Couverture de code cible
Critère	Barème
Couverture minimale attendue	>= 40%
Couverture recommandée	>= 60%
Couverture excellente	>= 80%
TOTAL	PHPUnit Coverage
Bash
# Générer le rapport de couverture :
php artisan test --coverage
# Rapport HTML complet :
XDEBUG_MODE=coverage php artisan test --coverage --coverage-html=reports/coverage
5.5 Bonnes pratiques de test
Un test = un comportement vérifié (ne pas tester plusieurs choses à la fois)

Utiliser les Factories Laravel pour générer des données de test cohérentes

Utiliser RefreshDatabase pour réinitialiser la base entre les tests

Mocker les appels à l'API IA (ne pas effectuer d'appels réels pendant les tests)

Nommer les tests de façon explicite : test_user_can_view_product_list()

5.6 Barème Tests
Critère	Barème
Tests unitaires écrits et fonctionnels	/5 pts
Tests de fonctionnalités (Feature tests)	/5 pts
Taux de couverture de code atteint	/5 pts
Qualité et lisibilité des tests	/5 pts
TOTAL	/20 pts
6.Qualité du Code & Sécurité
6.1 Qualité du code
Architecture MVC : Controllers minces, logique dans les Services/Models

Validation : Utilisation des Form Requests Laravel pour toute validation

Gestion des erreurs : Try/catch, pages d'erreur personnalisées (403, 404, 500)

Code lisible : PSR-12, nommage explicite, commentaires pertinents

Git : Commits atomiques et messages clairs (feat:, fix:, test:, docs:)

6.2 Sécurité
Authentification : Laravel Breeze ou Sanctum (obligatoire)

CSRF : Protection CSRF active sur tous les formulaires

Injection SQL : Utilisation exclusive d'Eloquent / Query Builder paramétré

Clés API : Stockées dans .env — jamais commitées dans Git

Gestion des rôles : Roles/Permissions recommandé (Spatie Laravel Permission)

7.SonarCloud — Analyse Qualité (Facultatif)
Cette section est facultative mais valorisée. Sa réalisation complète peut donner jusqu'à 10 points bonus sur la note finale.

7.1 Qu'est-ce que SonarCloud ?
SonarCloud est une plateforme cloud d'analyse statique de code qui permet de détecter automatiquement les bugs potentiels, les vulnérabilités de sécurité, les code smells et de mesurer la dette technique d'un projet. Il s'intègre directement avec GitHub.

7.2 Mise en place (étapes)
Étape	Description
Étape 1	Créer un compte SonarCloud sur sonarcloud.io (gratuit pour projets publics)
Étape 2	Lier votre repository GitHub à SonarCloud (OAuth)
Étape 3	Créer un projet SonarCloud et récupérer le SONAR_TOKEN
Étape 4	Ajouter le fichier sonar-project.properties à la racine du projet
Étape 5	Configurer le workflow GitHub Actions (.github/workflows/sonar.yml)
Étape 6	Vérifier les résultats sur le dashboard SonarCloud après chaque push