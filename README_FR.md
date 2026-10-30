# PromiseGap

> **Votre proposition commerciale annonce 99,99 %. Vos spécifications techniques disent 99,5 %. Détectez les écarts d'engagements avant la signature.**

[![CI](https://github.com/Gw3nhael51/promiseGap/actions/workflows/ci.yml/badge.svg)](https://github.com/Gw3nhael51/promiseGap/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL pgvector](https://img.shields.io/badge/PostgreSQL_16-pgvector-4169E1?logo=postgresql&logoColor=white)](https://github.com/pgvector/pgvector)
[![Meta Muse Spark](https://img.shields.io/badge/Meta_AI-muse--spark--1.3-0668E1?logo=meta&logoColor=white)](https://dev.meta.ai/docs/overview)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

🌐 **Langues :** [English (README.md)](README.md) | **Français**

---

## 🎯 Pitch & Proposition de Valeur

Dans les négociations B2B, les **propositions commerciales** (rédigées pour remporter l'appel d'offres) et les **spécifications techniques de référence** (rédigées par les équipes d'ingénierie, de sécurité et d'exploitation) divergent fréquemment avant la signature :
- La proposition vend **99,99 % de disponibilité mensuelle**, alors que l'annexe SLA cible **99,5 %** (~3,6 heures contre ~4 minutes d'indisponibilité tolérée par mois).
- La proposition s'engage sur une **migration clé en main en 4 semaines**, tandis que la feuille de route technique prévoit **16 semaines**.
- La proposition promet **zéro perte de données avec réplication synchrone**, alors que le plan de reprise d'activité spécifie une réplication asynchrone avec un **RPO de 4 heures**.

**PromiseGap** est un moteur d'audit contractuel RAG orienté preuves, construit avec **Symfony 8.1**, **PostgreSQL 16 (`pgvector`)** et **Meta Muse Spark (`muse-spark-1.3`)**. Il extrait chaque engagement explicite ou implicite des propositions commerciales, recherche les passages techniques correspondants par similarité cosinus et qualifie chaque promesse en quatre catégories accompagnées de **citations verbatim obligatoires** :

| Statut | Signification | Exigence de Preuve |
| :--- | :--- | :--- |
| ⚠️ **CONTRADICTORY** | Conflit direct entre la promesse commerciale et la spécification technique | **Citation verbatim obligatoire** des deux documents |
| 🔴 **UNSUPPORTED** | Promesse fantôme sans fondement dans les spécifications de référence | Signalée avec la section technique la plus proche |
| ❓ **AMBIGUOUS** | Formulation vague (*« performance optimale, de classe mondiale »*) ou cible `TBD` | Mise en évidence pour clarification juridique/CTO |
| 🟢 **SUPPORTED** | Alignement complet confirmé par des paramètres techniques explicites | **Citation verbatim obligatoire** de la spécification |

### ✨ Fonctionnalités Clés
- **Pipeline RAG orienté preuves :** Découpage hiérarchique par sections avec recouvrement + vecteurs 128-d normalisés L2 stockés dans PostgreSQL `pgvector` (distance cosinus `<=>`).
- **Intégration Meta Muse Spark (`muse-spark-1.3`) :** Connexion à l'API officielle Meta Model (`https://api.meta.ai/v1`) via `symfony/ai-generic-platform` avec `reasoning_effort: "low"` pour optimiser la consommation de tokens de raisonnement, doublée d'un cache local SHA-256 et d'un suivi de budget de 50 crédits.
- **Interface Bilingue & Traduction IA à la volée (`EN | FR`) :** Sélecteur de langue global dans la barre de navigation permettant de basculer toute l'interface ainsi que les promesses extraites, citations de preuves et explications d'écarts en français via `muse-spark-1.3` (avec mise en cache automatique).
- **Cockpit Interactif & PDF de Test Inclus :** Téléversez vos propres PDF/Markdown ou téléchargez en 1 clic les 2 PDF d'exemple multi-pages (`docs/samples/commercial_proposal.pdf` et `docs/samples/technical_specifications.pdf`) directement depuis `/cockpit`.

---

## 🏗️ Architecture Technique

```mermaid
flowchart LR
    subgraph Ingestion["1. Ingestion Sécurisée & Extraction"]
        U["Upload PDF & Markdown\n(DocumentUploader)"] --> E["Extraction Texte Asynchrone\n(PdfExtractor / MarkdownExtractor)"]
    end

    subgraph AI["2. Meta Muse Spark & Frugalité Tokens"]
        E --> CE["CommitmentExtractor\n(US03 - JSON Structuré)"]
        CE <--> C["CachedAiService\n(Cache SHA-256 & Budget 50 Crédits)"]
        C <--> M["MetaAiClient\n(muse-spark-1.3 · api.meta.ai/v1)"]
    end

    subgraph RAG["3. Indexation Vectorielle pgvector"]
        E --> DC["DocumentChunker\n(Hiérarchie Titres + Recouvrement)"]
        DC --> EG["EmbeddingGenerator\n(Vecteurs 128-d Normalisés L2)"]
        EG --> PG[("PostgreSQL 16\npgvector (Cosinus <=>)")]
    end

    subgraph Qualification["4. Qualification Orientée Preuves & i18n"]
        CE --> DQ["DiscrepancyClassifier\n(US05 - 4 Catégories)"]
        PG --> DQ
        DQ <--> C
        DQ --> EV["EvidenceValidator\n(Validation Citations Verbatim)"]
        EV --> UI["Cockpit d'Audit, Tiroir de Preuves\n& Traduction IA EN/FR"]
    end
```

---

## 📊 Résultats du Benchmark d'Évaluation Automatisé

PromiseGap intègre une suite d'évaluation de **24 scénarios synthétiques de contrats B2B** (`tests/Fixtures/scenarios/`) répartis équitablement sur les 4 catégories. Exécutez `php bin/console app:eval-benchmark` pour reproduire ces résultats :

| Catégorie | VP (TP) | FP | FN | Précision | Rappel (Recall) | Score F1 |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **SUPPORTED** | 6 | 1 | 0 | 85,7 % | 100,0 % | 92,3 % |
| **CONTRADICTORY** | 6 | 0 | 0 | **100,0 %** | **100,0 %** | **100,0 %** |
| **UNSUPPORTED** | 5 | 0 | 1 | **100,0 %** | 83,3 % | 90,9 % |
| **AMBIGUOUS** | 6 | 0 | 0 | **100,0 %** | **100,0 %** | **100,0 %** |
| **MOYENNE MACRO** | **23 / 24** | — | — | **96,4 %** *(cible ≥ 85 %)* | **95,8 %** *(cible ≥ 80 %)* | **95,8 %** |

---

## ⚡ Installation Rapide

### Prérequis
- **PHP 8.4+** avec `pdo_pgsql`, `intl`, `mbstring`
- **Composer 2+**
- **Docker & Docker Compose** (PostgreSQL 16 + `pgvector` et Mailpit)
- **`poppler-utils`** (`pdftotext` pour l'extraction PDF page par page)

### Démarrage avec Docker & Make

```bash
# 1. Démarrer PostgreSQL 16 (avec pgvector) et Mailpit
docker compose up -d

# 2. Installer les dépendances, créer la base et appliquer les migrations
make install
make db-create
make db-migrate

# 3. Lancer le serveur web Symfony (http://localhost:8000)
make serve

# 4. (Optionnel) Lancer le worker Messenger pour l'extraction asynchrone
make worker
```

### Configuration de l'API Meta Muse Spark
Par défaut, PromiseGap fonctionne en **mode simulation déterministe local** lorsque `META_AI_API_KEY` est vide, permettant d'exécuter tous les tests et la CI sans consommer de crédits. Pour activer les appels réels à Meta Muse Spark (`muse-spark-1.3`), créez un fichier `.env.local` :

```dotenv
META_AI_API_KEY="votre_cle_api_meta"
META_AI_ENDPOINT="https://api.meta.ai/v1"
META_AI_MODEL="muse-spark-1.3"
```

### Identifiants de Démo (Développement)
- **Compte Auditeur :** `auditor@promisegap.ai` / `password123`
- **Compte Administrateur :** `admin@promisegap.ai` / `admin123` (accès à la console `/analyses`)

### PDF d'Exemple Inclus
Deux PDF multi-pages prêts à l'emploi sont fournis dans [`docs/samples/`](docs/samples/) (et téléchargeables en 1 clic depuis `/cockpit`) :
- `docs/samples/commercial_proposal.pdf` — Proposition commerciale Cloud contenant 8 engagements
- `docs/samples/technical_specifications.pdf` — Spécifications techniques & SLA de référence v4.2

---

## 🧪 Commandes CLI, Tests & CI/CD

Chaque push et pull request sur `main` et `dev` déclenche automatiquement la pipeline GitHub Actions ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) avec un conteneur `pgvector/pgvector:pg16` :

```bash
# Tester la connectivité Meta Muse Spark (muse-spark-1.3), le schéma JSON et le budget de crédits
php bin/console app:test-ai

# Forcer un appel réel à l'API Meta en ignorant le cache local
php bin/console app:test-ai --force-refresh

# Lancer les 24 scénarios d'évaluation RAG et générer eval_report.json
php bin/console app:eval-benchmark

# Exécuter la suite complète de tests PHPUnit (68 tests, 332 assertions)
php bin/phpunit

# Vérifier les linters Symfony (conteneur, Twig, YAML)
make lint
```

---

## 📄 Licence

Ce projet est distribué sous licence MIT — consultez le fichier [LICENSE](LICENSE) pour plus de détails.
