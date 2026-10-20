# ReviewLens

Review intelligence for shoppers: import product reviews from CSV, analyse sentiment and themes, and click through to the original reviews behind every conclusion.

Laravel 13 · Inertia + Vue 3 + TypeScript · Tailwind 4 · SQLite/MySQL · Pest.

## Features
Products, CSV import with preview/mapping/duplicate detection, a local NLP pipeline, evidence-linked findings, theme analysis, product comparison, review explorer, saved analyses (queued, re-runnable), CSV/HTML exports, synthetic demo data. Works with no API key.

## Requirements (tested versions)
PHP 8.3 (with `pdo_sqlite` or `pdo_mysql`, `mbstring`, `intl` optional), Composer 2, Node 22 / npm 10. Python is **not** required. Database: SQLite (default, used by tests) or MySQL 8.

## Quick start
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # demo user: demo@reviewlens.test / password (synthetic data)
composer dev                      # server + queue worker + vite  (or run each separately)
```
Without `composer dev`: `php artisan serve`, `php artisan queue:work`, `npm run dev`. Analyses are queued (`QUEUE_CONNECTION=database`); **a worker must be running** or they stay "Queued". Set `QUEUE_CONNECTION=sync` to run inline.

MySQL: set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, then migrate. (MySQL was not available in the build environment; migrations use only portable schema features and were verified on SQLite.)

## Testing and checks
```bash
vendor/bin/pest          # backend (in-memory SQLite, never touches your DB)
npm test                 # vitest
npm run lint && npm run typecheck && npm run build
```

## CSV format
Header row required. Delimiters `, ; tab |`, UTF-8 (Windows-1252 fallback). Only review text is required (≥10 chars).

| Field | Accepted headers |
|---|---|
| review text* | review, review_text, review_body, text, body, content, comment(s) |
| title | title, review_title, headline, summary, subject |
| rating | rating, stars, star_rating, score (1–5, `4/5`, `8/10`) |
| date | date, review_date, reviewed_at, created_at, timestamp |
| verified | verified, verified_purchase, is_verified (yes/no/true/false/1/0) |
| source | source, retailer, site, platform |
| id | id, review_id, external_id, source_id |

Columns can be re-mapped on the preview screen. Limits: 5 MB, 5,000 rows (`REVIEWLENS_MAX_*`). Duplicates are skipped by source id or by content fingerprint (title + text, ignoring case/punctuation) within a product. Uploaded files are deleted after import; abandoned previews are pruned daily (`reviewlens:prune-imports`, needs `php artisan schedule:work` or a cron entry `* * * * * php artisan schedule:run`).

## NLP architecture
Pipeline (`app/Services/Analysis/AnalysisPipeline.php`): normalize/validate → drop unusable & duplicate → normalize ratings → sentiment → aspects → aggregate → summarize → link evidence → persist with provenance (all inside one transaction; status becomes `completed` only after commit).

**Baseline (always available)**
- Sentiment: hand-curated lexicon with negation, intensifiers and contrast handling, combined with the star rating. Labels: positive/negative/neutral/mixed. If text carries no signal, the rating is used and the method is recorded as `rating`. Rating/text disagreement is flagged.
- Confidence: qualitative low/medium/high. These are **not calibrated probabilities**.
- Themes: eight predefined aspects (quality, price, performance, ease of use, design, comfort, delivery, support) matched per clause, plus frequency-based "discovered" terms. Discovered terms are heuristics, shown with a warning and never asserted as findings.
- Findings: templated statements built from counts; each cites up to 3 real reviews with excerpts that are verbatim substrings of the review. Contradictory themes become "divided" findings with no conclusion.
- This is a transparent keyword method, **not a trained model**. It misses sarcasm, context and non-English text.

**Optional model-backed engine** (`AnthropicModelAnalyzer`, behind the `ModelAnalyzer` interface): active only if `ANTHROPIC_API_KEY` is set and the user picks "Model-backed". Up to `REVIEWLENS_AI_MAX_REVIEWS` reviews are sent to the provider (this transmits review text to a third party). Output is strictly validated: unknown review ids, invalid labels and quotes that are not verbatim in the review are discarded. On timeout/HTTP error/invalid JSON the run falls back to the baseline and records why in its provenance and a caveat. Keys are never stored in analysis records or logs. This path is tested with faked HTTP only; it has **not** been exercised against the live API.

## Integrations
CSV upload is the only ingestion method. There are no scrapers and no retailer credentials; do not scrape sites that forbid it. To add a source, produce a CSV or create a service that builds `reviews` rows through `CsvImportService`-style validation.

## Security notes
Session auth with login/registration throttling; every resource is checked with policies (other users' records return 404); mass assignment is blocked (`#[Fillable]`/guarded models); CSV exports neutralise formula injection; review HTML is stripped on import; uploads are validated for type/size/rows and stored outside the web root; expensive routes are rate limited. Passwords are hashed. No email verification or password-reset flow is included.

## Deployment (manual)
1. Provision PHP 8.3, a database, and a process manager for `php artisan queue:work --tries=1` (Supervisor/systemd) plus a scheduler cron.
2. `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
3. Configure `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY` (`php artisan key:generate --show`), DB settings, `SESSION_SECURE_COOKIE=true`, optional `ANTHROPIC_API_KEY`.
4. `php artisan migrate --force && php artisan config:cache route:cache view:cache`
5. Point the web server at `public/`. Ensure `storage/` and `bootstrap/cache/` are writable. Restart workers after each deploy (`php artisan queue:restart`).
The demo seeder is skipped in production unless `REVIEWLENS_ALLOW_DEMO_SEED=true`; users can also add demo products from the dashboard.

## Troubleshooting
- *Analysis stays "Queued"*: start `php artisan queue:work` (or use `QUEUE_CONNECTION=sync`).
- *Vite manifest not found*: run `npm run build` or `npm run dev`.
- *Upload rejected*: check size/row limits and PHP `upload_max_filesize`/`post_max_size`.
- *Everything shows no themes*: themes need ≥2 mentions; import more reviews.

## Known limitations
Baseline NLP is English-only and keyword-based; discovered aspects can be noisy; no scraping or retailer APIs; no email verification/password reset or team sharing (all data is per user); analyses read all reviews of a product (not a chosen subset); deleting an import also deletes that product's analyses; MySQL and the live AI API were not tested here; accessibility was designed for (labels, focus states, text alternatives for charts) but not audited with assistive tech.
