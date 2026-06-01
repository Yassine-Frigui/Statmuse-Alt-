# NBA Query Engine - Project Concept

## Overview

The NBA Query Engine is a specialized information retrieval system designed to answer basketball-related questions using a curated NBA and ABA knowledge corpus.

The system aims to provide an experience similar to StatMuse by allowing users to ask questions in natural language while receiving accurate, structured, and corpus-grounded answers.

Unlike a general-purpose chatbot, the system is not intended to engage in open-ended conversations or generate knowledge from its own training data. Its primary objective is to understand user queries, translate them into structured information requests, retrieve relevant information from the corpus, and present the results in an understandable format.

---

# Problem Statement

Basketball information is distributed across numerous sources:

* Historical NBA records
* ABA historical archives
* Player biographies
* Team histories
* Season summaries
* Rulebooks
* Statistical databases
* News articles

Accessing specific information often requires users to manually search through multiple sources and databases.

The objective of this project is to create a unified system capable of understanding basketball-related questions and locating relevant information within a dedicated basketball corpus.

---

# Core Concept

The system acts as a bridge between:

1. Human language
2. Structured basketball knowledge

A user asks a question using natural language.

Example:

> Who scored the most points during the 1997 NBA Finals?

The system interprets the request, identifies the relevant entities and constraints, searches the corpus, and returns the appropriate answer.

---

# Functional Workflow

## Step 1: User Query

The user submits a basketball-related question.

Examples:

* Who won the 1986 NBA Championship?
* Which player averaged the most rebounds in 1995?
* Explain the ABA-NBA merger.
* Which teams have never won a championship?

---

## Step 2: Query Understanding

The system analyzes the query and identifies:

### Intent

The type of information being requested.

Examples:

* Statistical query
* Historical query
* Player information query
* Team information query
* Rule explanation query

### Entities

Important basketball concepts appearing in the query.

Examples:

* Players
* Teams
* Seasons
* Competitions
* Statistics
* Historical events

### Constraints

Conditions that limit the search.

Examples:

* Specific year
* Time period
* Minimum statistics
* Team affiliation
* Position

---

## Step 3: Query Transformation

The natural language request is transformed into a structured representation.

Example:

User Query:

> Top 5 scorers in the 1990s

Structured Representation:

```json
{
  "intent": "ranking_query",
  "metric": "points",
  "period": "1990s",
  "limit": 5,
  "sort": "descending"
}
```

This intermediate representation enables the system to process queries consistently.

---

## Step 4: Corpus Retrieval

The system searches the basketball corpus for relevant information.

The corpus may contain:

* Historical documents
* Statistical records
* Team information
* Player information
* League history
* Basketball terminology

Only information contained within the corpus should be considered valid for answering the query.

---

## Step 5: Result Generation

The retrieved information is organized into a user-friendly response.

Examples:

### Statistical Response

| Rank | Player         | Points |
| ---- | -------------- | ------ |
| 1    | Michael Jordan | 24997  |
| 2    | Karl Malone    | 23145  |

### Historical Response

A concise explanation generated from the relevant historical documents contained within the corpus.

---

# System Scope

The system focuses exclusively on basketball knowledge.

Supported topics include:

* NBA history
* ABA history
* Players
* Teams
* Coaches
* Championships
* Awards
* Statistics
* Rules
* Historical events
* Basketball terminology

The system does not aim to answer unrelated questions outside the basketball domain.

---

# Role of the Language Model

The language model is not the primary source of knowledge.

Its responsibility is to:

* Understand user language
* Extract entities
* Identify constraints
* Classify query intent
* Generate structured query representations
* Format final responses

The actual information source remains the basketball corpus.

This approach minimizes hallucinations and ensures that answers remain grounded in verified corpus data.

---

# Difference from a Traditional Chatbot

Traditional Chatbot:

* Generates answers from internal model knowledge
* Can discuss virtually any topic
* May hallucinate information

NBA Query Engine:

* Restricted to basketball-related content
* Uses a dedicated corpus as its knowledge source
* Retrieves information before answering
* Produces more deterministic and verifiable outputs

---

# Expected Benefits

* Natural language access to basketball knowledge
* Faster information retrieval
* Unified access to NBA and ABA history
* Reduced dependence on manual searching
* Structured and verifiable answers
* Extensible architecture for future datasets

---

# Summary

The NBA Query Engine is a domain-specific information retrieval system that combines natural language understanding with corpus-based retrieval.

Rather than functioning as a general conversational AI, the system acts as a basketball knowledge interface capable of translating user questions into structured searches and returning answers derived directly from a curated NBA and ABA corpus.
w