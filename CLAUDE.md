# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

This workspace contains two separate PHP + MySQL projects. The primary working directory is the **as-is** app; `/Users/wellis/Desktop/Cursor/sor-system` is a related but independent system.

---

## AS-IS Management System (`/Users/wellis/Desktop/Cursor/as-is`)

A structured process-map manager that replaces hand-drawn "as-is" diagrams with a database-backed swimlane/step editor. The view page renders a live Mermaid.js flowchart generated from stored data.

### Running locally

```bash
# Option A — convenience script (defaults to port 8890)
./start.sh

# Option B — explicit
php -S localhost:8890 -t public
```

Database requires MAMP running with MySQL on port 8889. Copy `.env.example` to `.env` and adjust credentials.

### Database setup

```bash
# In phpMyAdmin or mysql CLI — run in order:
sql/schema.sql     # creates tables (as_is_documents, lanes, systems, steps, step_systems, step_connections)
sql/seed.sql       # sample data
```

The `setup.php` page in the app also runs schema + seed automatically via the browser.

### Architecture

All domain logic lives in `includes/helpers.php` as plain functions (`fetch_documents`, `create_step`, `build_mermaid`, etc.). Pages in `public/` call these functions and pass the result into `render_layout()`, which inlines all CSS and outputs the full HTML. There is no framework, no autoloader, and no build step.

| Layer | Location |
|---|---|
| DB connection | `includes/db.php` — two PDO singletons: `db()` (with database) and `db_server()` (without, for `CREATE DATABASE`) |
| Config | `config/database.php` — reads `.env` via `includes/env.php` |
| Domain logic | `includes/helpers.php` |
| Pages | `public/*.php` — each uses `ob_start()` / `ob_get_clean()` then `render_layout()` |
| Layout + CSS | `render_layout()` in `includes/helpers.php` — all styles are inline |

**Data model:**
- `as_is_documents` — top-level process document (title, slug, description, status)
- `lanes` — swimlanes belonging to a document (name, colour, sort_order)
- `systems` — named systems referenced by steps
- `steps` — ordered steps in a lane (step_type: `start` | `task` | `decision` | `end`)
- `step_systems` — many-to-many: steps ↔ systems
- `step_connections` — directed edges between steps (optional label)

`build_mermaid()` in `helpers.php` converts lanes/steps/connections into a Mermaid `flowchart LR` with subgraphs per lane.

Documents are addressed by `slug` (URL-safe, auto-generated from title). `resolve_document_request()` accepts either `?slug=` or `?id=` and returns the document row.

---

## ERC SOR Management System (`/Users/wellis/Desktop/Cursor/sor-system`)

Schedule of Rates management for East Renfrewshire Council housing repairs. 12,188 SOR codes with categories, trades, accreditations, review workflows, CSV import/export, REST API, and optional Microsoft Entra SSO.

### Running locally

```bash
# Requires MAMP with MySQL on port 8889, document root = project root
php -S localhost:8080
```

### Database setup (run in order)

```bash
sql/schema.sql
sql/seed_trades.sql
sql/seed_sor_codes.sql
sql/seed_users.sql        # copy seed_users.example.sql first and fill in real users
```

Optional migrations live in `sql/migrate_*.sql`. Run `migrate_entra_auth.sql` only if enabling Microsoft SSO.

### Composer (SSO only)

```bash
composer install --no-dev
```

Not required for local dev when `ENTRA_ENABLED=false`.

### Architecture

Same pattern as the as-is app: plain PHP functions, no framework. Every module (sor, categories, trades, accreditations, etc.) follows the same file layout:

```
<module>/index.php     list
<module>/create.php    form + POST handler
<module>/edit.php      form + POST handler
<module>/delete.php    confirmation + POST handler
```

| Layer | Location |
|---|---|
| Bootstrap | `includes/config.php` — loads `.env`, defines constants, sets security headers, starts hardened session |
| DB | `includes/db.php` — PDO singleton `db()` |
| Auth | `includes/auth.php` — `require_login()`, `attempt_login()`, Entra provisioning |
| Permissions | `includes/permissions.php` — `require_min_role()`, `can_edit_records()`, `can_delete_records()`, etc. |
| Domain logic | `includes/functions.php` + per-feature includes (`includes/api_sor.php`, `includes/review.php`, etc.) |
| API | `api/v1/*.php` — REST endpoints; `api/v1/openapi.php` serves the OpenAPI spec |

**Roles:** `viewer` (read + export) → `editor` (create/edit records) → `admin` (delete, import, API clients, audit log). Checked via `user_has_min_role()`.

**Microsoft Entra SSO:** Entra app roles `SOR.Admin`, `SOR.Editor`, `SOR.Viewer` map to internal roles. `login_user_from_entra()` provisions or links users on first login.

**CSS cache busting:** `?v=<filemtime>` is appended to CSS/JS URLs so Hostinger's LiteSpeed CDN picks up new files after deploy.

### Production deployment (Hostinger)

Push to `origin/main`, then either trigger Git deployment in hPanel or manually upload changed files. Create `.env` on the server via File Manager (never commit it). Run SQL files in phpMyAdmin after first deploy. Clear cache in hPanel → Website → Cache Manager after every deploy.

### REST API

Authenticate with an API key created via **Tools → API access**. Endpoints are under `/api/v1/`. Webhooks fire `sor.created`, `sor.updated`, `sor.deleted` to URLs in `API_WEBHOOK_URLS`.

### Key conventions across both projects

- All user output escaped with `h()` (`htmlspecialchars`)
- All DB queries use PDO prepared statements — no string interpolation in SQL
- CSRF tokens on every POST form
- `redirect()` helper wraps `header('Location: …'); exit;`
- `declare(strict_types=1)` at the top of every PHP file

---

## Design Context

### Users
Mixed internal and external. Primary users are analysts, process improvement leads, and business change staff within a public sector organisation — people who document how processes currently work. Secondary audience: external partners, auditors, and organisations who might adopt the tool as a product. Non-technical to moderately technical; desktop-first; working at a desk during or after workshops.

### Brand Personality
**Clear. Considered. Credible.** Government-trusted, not governmental-dull. The interface should feel like a senior analyst designed it — someone with good taste who cares about clarity, not someone trying to be flashy.

### Aesthetic Direction
**GOV.UK-influenced, product-grade.** Typographically restrained, high-contrast, accessible, purposeful. Differentiated from generic GOV.UK by better typography pairing, a warmer palette, and stronger design through restraint rather than ornament.

- **Fonts**: IBM Plex Sans (body/UI) + IBM Plex Serif (display). Never Inter, Roboto, or system-ui.
- **Colour**: Deep slate-navy dominant, warm near-white background, single teal accent. No gradients, no dark mode.
- **Layout**: Left-aligned, comfortable max-width, asymmetric spacing rhythm, no card-within-card nesting.
- **Motion**: Near zero. Subtle opacity on state changes only.
- **Anti-references**: No startup SaaS gradients, no dark-mode dashboards, no generic blue admin panels.

### Design Principles
1. **Content is the interface** — Typography and whitespace carry the work; decoration doesn't.
2. **Credible at first glance** — An external auditor should immediately trust it.
3. **Accessible by default** — WCAG AA, high contrast, visible focus, no colour-only communication.
4. **Purposeful restraint** — Every element earns its place.
5. **Product-grade polish** — The micro-details (spacing, empty states, errors) signal whether this is trustworthy.
