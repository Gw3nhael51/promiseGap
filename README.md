# PromiseGap

> **Your proposal says 99.99%. Your specs say 99.5%. Catch commercial commitment gaps before contracts are signed.**

[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL pgvector](https://img.shields.io/badge/PostgreSQL_16-pgvector-4169E1?logo=postgresql&logoColor=white)](https://github.com/pgvector/pgvector)
[![Meta Muse Spark](https://img.shields.io/badge/Meta_AI-Muse_Spark-0668E1?logo=meta&logoColor=white)](https://ai.meta.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

🌐 **Languages:** **English** | [Français (README_FR.md)](README_FR.md)

---

## 🎯 Pitch & Value Proposition

In enterprise B2B deals, **commercial proposals** (written by sales teams to win the deal) and **technical reference specifications** (written by engineering, security, and ops teams) frequently diverge before signature:
- Sales promises **99.99% monthly uptime**, while the SLA annex specifies **99.5%** (~3.6 hours vs ~4 minutes of allowed downtime/month).
- Sales commits to **turnkey delivery in 3 months**, while the implementation roadmap totals **7 months**.
- Sales claims **real-time zero-loss backups**, while no recovery objective exists in the technical specification.

**PromiseGap** is an autonomous, proof-oriented RAG contract audit engine built with **Symfony 8.1**, **PostgreSQL 16 (`pgvector`)**, and **Meta Muse Spark**. It extracts every explicit and implicit promise from commercial proposals, retrieves the closest technical specification chunks via cosine similarity, and classifies each commitment into four actionable categories backed by **mandatory verbatim source quotes**:

| Status | Meaning | Evidence Requirement |
| :--- | :--- | :--- |
| ⚠️ **CONTRADICTORY** | Direct conflict between sales promise and technical spec (SLA, timeline, scope) | **Mandatory verbatim quote** from both documents |
| 🔴 **UNSUPPORTED** | Ghost promise with no technical basis in reference specifications | Flagged with closest specification section |
| ❓ **AMBIGUOUS** | Vague phrasing (*"optimal, world-class, best-efforts"*) or unfinalized (`TBD`) metrics | Highlighted for legal/CTO clarification |
| 🟢 **SUPPORTED** | Full alignment backed by explicit technical parameters | **Mandatory verbatim quote** from technical specs |

---

## 🏗️ System Architecture

```mermaid
flowchart LR
    subgraph Ingestion["1. Secure Ingestion & Extraction"]
        U["PDF & Markdown Upload\n(DocumentUploader)"] --> E["Async Text Extraction\n(PdfExtractor / MarkdownExtractor)"]
    end

    subgraph AI["2. Meta Muse Spark & Token Frugality"]
        E --> CE["CommitmentExtractor\n(US03 - Structured JSON)"]
        CE <--> C["CachedAiService\n(SHA-256 Cache & 50-Credit Budget)"]
        C <--> M["MetaAiClient\n(Meta Muse Spark API)"]
    end

    subgraph RAG["3. pgvector Semantic Store"]
        E --> DC["DocumentChunker\n(Heading Hierarchy + Overlap)"]
        DC --> EG["EmbeddingGenerator\n(128-d L2 Normalized)"]
        EG --> PG[("PostgreSQL 16\npgvector (<=> Cosine)")]
    end

    subgraph Qualification["4. Proof-Oriented Qualification"]
        CE --> DQ["DiscrepancyClassifier\n(US05 - 4 Categories)"]
        PG --> DQ
        DQ <--> C
        DQ --> EV["EvidenceValidator\n(Verbatim Quote Enforcement)"]
        EV --> UI["Audit Cockpit & Evidence Drawer"]
    end
```

---

## 📊 Automated Benchmark Evaluation Results

PromiseGap includes a curated ground-truth benchmark suite of **24 synthetic B2B enterprise contract scenarios** (`tests/Fixtures/scenarios/`) balanced across all 4 categories. Run `php bin/console app:eval-benchmark` to reproduce:

| Category | TP | FP | FN | Precision | Recall | F1-Score |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **SUPPORTED** | 6 | 1 | 0 | 85.7% | 100.0% | 92.3% |
| **CONTRADICTORY** | 6 | 0 | 0 | **100.0%** | **100.0%** | **100.0%** |
| **UNSUPPORTED** | 5 | 0 | 1 | **100.0%** | 83.3% | 90.9% |
| **AMBIGUOUS** | 6 | 0 | 0 | **100.0%** | **100.0%** | **100.0%** |
| **MACRO AVERAGE** | **23 / 24** | — | — | **96.4%** *(target ≥ 85%)* | **95.8%** *(target ≥ 80%)* | **95.8%** |

---

## ⚡ Quick Start Installation

### Prerequisites
- **PHP 8.4+** with `pdo_pgsql`, `intl`, `mbstring`
- **Composer 2+**
- **Docker & Docker Compose** (for PostgreSQL 16 + `pgvector` and Mailpit)
- **`poppler-utils`** (`pdftotext` for fast page-by-page PDF extraction)

### 1-Command Setup via Docker & Make

```bash
# 1. Start PostgreSQL 16 (with pgvector) and Mailpit containers
docker compose up -d

# 2. Install dependencies, compile assets, create DB and run migrations
make install
make db-create
make db-migrate

# 3. Start the local web server (http://localhost:8000)
make serve

# 4. (Optional) Start the async Messenger worker for background PDF extraction
make worker
```

### Configuring Meta Muse Spark API Key
By default, PromiseGap runs in **deterministic offline simulation mode** when `META_AI_API_KEY` is empty, allowing full testing and evaluation with zero API cost. To enable live Meta Muse Spark calls, create `.env.local`:

```dotenv
META_AI_API_KEY="your_meta_llama_api_key"
META_AI_ENDPOINT="https://api.llama.com/compat/v1"
META_AI_MODEL="Muse-Spark"
```

### Demo Credentials (Development)
- **Auditor Account:** `auditor@promisegap.ai` / `password123`
- **Admin Account:** `admin@promisegap.ai` / `admin123`

---

## 🧪 CLI Commands & Testing

```bash
# Verify Meta Muse Spark connectivity, structured JSON schema & credit budget telemetry
php bin/console app:test-ai

# Run the 24-scenario RAG benchmark suite and generate eval_report.json
php bin/console app:eval-benchmark

# Run the full PHPUnit test suite (65 tests, 283 assertions)
php bin/phpunit

# Run Symfony container, Twig, and YAML linters
make lint
```

---

## 📄 License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.
