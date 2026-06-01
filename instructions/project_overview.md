# Project Overview — NBA Query Engine

## 1. Contexte

Projet Framework Web — Application Laravel : moteur de requêtes NBA/ABA avec interface de chat IA. L'utilisateur pose des questions en langage naturel sur le basketball et reçoit des réponses structurées issues d'un corpus dédié.

## 2. Objectifs pédagogiques

- Développer une application Laravel complète (MVC, Eloquent, Blade)
- Concevoir une architecture propre et maintenable
- Gérer une base de données relationnelle (MySQL) avec schéma NBA/ABA
- Développer une UI dynamique et responsive (chat + visualisations)
- Consommer l'API Gemini pour l'analyse et la transformation de requêtes
- Implémenter un pipeline NL → structured query → corpus retrieval → réponse
- Rédiger des tests unitaires et fonctionnels avec PHPUnit
- Analyser la qualité du code via SonarCloud (optionnel)

## 3. Livrables attendus

| Livrable | Description |
|----------|-------------|
| Application Laravel | Backend + Frontend complets (NBA Query Engine) |
| Pipeline IA | NL → structured query → corpus retrieval → réponse formatée |
| Tests | Tests unitaires (Services, Models) et fonctionnels (endpoints) |
| Documentation | Structurée et livrée professionnellement |
| Qualité | Analyse SonarCloud (optionnel — bonus) |

## 4. Contraintes techniques

- PHP 8.x + Laravel 10/11
- Blade + Bootstrap
- MySQL
- ORM Eloquent pour toutes les interactions BD
- Auth via Laravel Breeze / Sanctum
- Architecture MVC strict
- API Google Gemini pour le NLU et le formatting

## 5. Domaine métier

NBA Query Engine — système de questions-réponses sur le basketball (NBA & ABA) :

- Joueurs, équipes, saisons, statistiques
- Championnats, awards, coaches
- Histoire NBA/ABA, règles, événements
- Style StatMuse : questions en NL → réponses structurées
