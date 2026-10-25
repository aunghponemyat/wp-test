# AGENT.md — CareConnect (take-home build spec)

You are an AI coding agent building **CareConnect**, a fictional community-care directory. This file is the single source of truth. Follow it literally. Where it is silent, make the smallest sensible choice and record it in `README.md` under "Assumptions". Do not invent endpoints, field names, env vars or package versions that are not defined here.

## 0. Ground rules (read first)

1. **Fictional data only.** No real names, phones, addresses, emails. Emails use the reserved `example.test` / `example.com` domains. Phones use the fictional `6555 01xx` range.
2. **Never guess versions.** Install the latest stable release with the package manager, then pin the exact resolved version (`package.json`, `composer.json`). Do not write version numbers from memory into docs.
3. **Services/Articles logic lives in the plugin `careconnect-services`.** Not in the theme, not in ACF, not in a page builder. Core WordPress + small helper libraries only.
4. **Security in every PHP file:** `defined( 'ABSPATH' ) || exit;`, capability checks, nonces, sanitise on input, escape on output, `$wpdb->prepare()` for any raw SQL (prefer `WP_Query`, which avoids raw SQL entirely).
5. **WordPress Coding Standards (WPCS)**, text domain `careconnect-services`, every user-visible string wrapped in `__()`/`esc_html__()`.
6. **No uploads anywhere.** Images are URLs (see §4.2). The upload UI/capability is disabled.
7. **Do not touch** the files already provided: `Dockerfile`, `render.yaml`, `docker/*`, `scripts/vps-mysql-setup.sh`, theme `careconnect-headless`. Change them only if a bug is proven, and note it in the README.
8. Work in the order of §10 and tick the Definition of Done (§11) before declaring completion. When something cannot be verified (e.g. no access to the VPS or Render), say so explicitly; never claim it was tested.

## 1. Product summary

- Visitors (Next.js site on Vercel) browse/search community care **Services** and read **Articles**, and send a **Contact** message.
- Editors/Publishers (WordPress admin on Render) manage content. Unpublished content must never be reachable publicly.
- WordPress is **headless**: its public front end redirects to Next.js. It exposes a custom, read-mostly REST API (§5).

### Assumptions already decided
| Topic | Decision |
|---|---|
| Brief: "one other language" (Nice) | **Out of scope by default** (§12). Build English only, but keep all strings translatable. |
| Brief: "image with alt text" | Image stored as **URL + alt text** meta (no media library). |
| Brief: Articles "category" | Custom taxonomy `cc_article_category`. |
| Brief: "Home content editable" | Plugin admin screen "Home Content" (§4.6). |
| Brief: "mobile first, 360px" | Design CSS mobile-first; test at 360px. |
| Contact submissions | Saved to a private CPT `cc_message` AND emailed (email is best-effort on the demo). |

## 2. Architecture

```
Visitor ──► Vercel (Next.js static export, CDN)
              │  build time: fetch content (retry, WP may be asleep)
              │  run time (browser): search + contact POST
              ▼
        Render Web Service (Docker, free, Singapore)  ── WordPress + plugin + headless theme
              │  mysqli over TLS, port 3306, allow-listed source IPs
              ▼
        MySQL 8 on the user's VPS (ssh alias: do-sg), UFW restricted, TLS required
```

| Layer | Choice |
|---|---|
| CMS | WordPress (current stable) on `wordpress:6-php8.3-apache` (pin the full tag after checking Docker Hub) |
| Plugin | `careconnect-services` (PHP ≥ 8.1) |
| Theme | `careconnect-headless` (provided; redirects to Next.js, serves only `/wp-services-demo/`) |
| DB | MySQL 8 on VPS, TLS enforced |
| Frontend | Next.js (latest stable, **App Router**, TypeScript, static export `output: 'export'`) + Tailwind CSS |
| Tests | PHPUnit (plugin), Vitest + React Testing Library (web, a few smoke tests), ESLint |
| Hosting | Render (WP), Vercel (Next.js), VPS (MySQL) |

**Free-tier realities (document in README, show a UI hint):**
- Render free web services **spin down after ~15 min idle** and take a while to wake. The frontend must show a "Waking up the demo server…" state if a request takes >4 s, and retry.
- Render free has **no persistent disk**: all of `/var/www/html` resets on redeploy/restart. All state lives in MySQL. Hence: no uploads, stable salts via env (`generateValue`), and auto-bootstrap (`docker/cc-bootstrap.sh`).
- Static export means new content appears only after a **Vercel rebuild** → plugin triggers a Vercel Deploy Hook (§4.8).

## 3. Repository layout

```
careconnect/
├─ AGENT.md                      (this file)
├─ README.md                     (you write: setup, deploy, assumptions, "media is non-persistent")
├─ Dockerfile  .dockerignore  render.yaml     (provided)
├─ docker/cc-entrypoint.sh  cc-bootstrap.sh    (provided)
├─ scripts/vps-mysql-setup.sh                  (provided)
├─ docs/design.md                (you write: 2–3 page design doc, see §13)
├─ wordpress/wp-content/
│  ├─ themes/careconnect-headless/             (provided)
│  └─ plugins/careconnect-services/            (YOU BUILD)
│     ├─ careconnect-services.php              header, constants, bootstrap
│     ├─ uninstall.php
│     ├─ includes/ class-cpt.php class-meta-boxes.php class-roles.php class-rest.php
│     │            class-shortcode.php class-admin-columns.php class-settings.php
│     │            class-home.php class-contact.php class-cors.php class-uploads-lock.php
│     │            class-seed.php class-cli.php class-vercel-hook.php
│     ├─ assets/ services-list.js (vanilla, no deps), admin.css
│     ├─ languages/                            (.pot)
│     ├─ tests/  (PHPUnit)  phpunit.xml.dist  composer.json  .phpcs.xml.dist
└─ web/                          (YOU BUILD: Next.js app)
```

## 4. WordPress plugin spec — "CareConnect Services"

Plugin header name must be exactly `CareConnect Services`. Prefix PHP symbols with `CCS_` / `ccs_`; meta keys with `_cc_`; post type and taxonomy slugs as below (≤ 20 chars).

### 4.1 Post types & taxonomies (P1)
| Item | Slug | Notes |
|---|---|---|
| CPT Service | `cc_service` | `public=false`, `show_ui=true`, `show_in_rest=false`, `publicly_queryable=false`, supports `title`, `editor`; `capability_type => ['cc_service','cc_services']`, `map_meta_cap=true`; menu icon `dashicons-heart` |
| Taxonomy Service type | `cc_service_type` | non-hierarchical, `show_ui=true`, `show_in_rest=false`, `meta_box_cb=false` (we render our own single-select dropdown) |
| CPT Article | `cc_article` | same flags; supports `title`, `editor`, `excerpt` (= **summary**); caps `['cc_article','cc_articles']` |
| Taxonomy Article category | `cc_article_category` | non-hierarchical |
| CPT Contact message | `cc_message` | `public=false`, `show_ui=true`, admin-only (`capability_type => ['cc_message','cc_messages']`, caps given to administrator only), no editing UI for content |

`show_in_rest=false` is deliberate: no core `/wp/v2/` exposure of drafts; all public data goes through our endpoints. Also unregister the core users endpoints for unauthenticated requests (`rest_endpoints` filter: remove `/wp/v2/users` and `/wp/v2/users/(?P<id>[\d]+)` unless `is_user_logged_in()`).

### 4.2 Service fields (P2) — meta box "Service details"
Single dropdown "Service type" maps to term in `cc_service_type` (required, exactly one). Title = service **name**; editor content = **full description**.

| Field | Meta key | Type / validation | Required |
|---|---|---|---|
| Short description | `_cc_short_description` | textarea, `sanitize_textarea_field`, **max 200 chars** (`mb_strlen`), counter in UI | yes |
| Eligibility | `_cc_eligibility` | textarea, `sanitize_textarea_field` | no |
| Address | `_cc_address` | text, `sanitize_text_field` | yes |
| Phone | `_cc_phone` | text, regex `^(\+65[\s-]?)?[3689]\d{3}[\s-]?\d{4}$` (Singapore); store normalised as `+65 XXXX XXXX` | yes |
| Opening hours | `_cc_opening_hours` | textarea, one line per day-range, `sanitize_textarea_field` | no |
| Image URL | `_cc_image_url` | `esc_url_raw`; must be `https://…` or start with `/` | no |
| Image alt text | `_cc_image_alt` | `sanitize_text_field`; **required when image URL set** | conditional |
| Related services | `_cc_related_ids` | multi-select of other published `cc_service` IDs, **max 3**, no self-reference, `absint` each | no |
| Last reviewed | `_cc_last_reviewed` | date `Y-m-d`, validated with `DateTime::createFromFormat`, not in the future | yes |
| Latitude / Longitude | `_cc_lat` / `_cc_lng` | floats; lat −90..90, lng −180..180 (for the optional map) | no |

Saving (`save_post_cc_service`): bail on autosave, verify nonce `ccs_service_nonce` / action `ccs_save_service`, `current_user_can( 'edit_post', $post_id )`, sanitise every field, validate. On validation failure: do not save the invalid value, keep the post as **draft** (never publish an invalid post: hook `wp_insert_post_data` to downgrade `publish`→`draft`), and show an admin notice (transient keyed by user) listing each error. Register meta with `register_post_meta()` with `sanitize_callback`, `show_in_rest=false`.

**Article fields** (meta box "Article details"): `_cc_image_url`, `_cc_image_alt` (same rules), category dropdown (single `cc_article_category` term). Title, summary (excerpt), body (editor), **publish date = post date** (`post_date`). Same nonce/cap/sanitise pattern with its own nonce.

### 4.3 Roles & capabilities (P5, P8)
Create on activation (names avoid clashing with core "editor"):

| Role slug | Display name | Capabilities |
|---|---|---|
| `cc_editor` | CareConnect Editor | `read`; for `cc_services` and `cc_articles`: `edit_*s`, `delete_*s`, (own drafts only) — **NOT** `publish_*s`, **NOT** `edit_published_*s`, **NOT** `edit_others_*s` |
| `cc_publisher` | CareConnect Publisher | everything the editor has **plus** `publish_*s`, `edit_others_*s`, `edit_published_*s`, `delete_others_*s`, `delete_published_*s`, `read_private_*s`, and `ccs_manage_home` |
| administrator | (core) | gets all of the above caps added on activation, plus `ccs_manage_settings` |

(`*s` = `cc_services`, `cc_articles`.) Editors save as Draft or **Pending Review**; the "Publish" button is absent for them (WordPress derives this from the missing `publish_*` cap). Add a unit test asserting editor `!can('publish_cc_services')` and publisher `can(...)`.

### 4.4 No uploads (README must say media is non-persistent)
`class-uploads-lock.php`: filter `user_has_cap` to force `upload_files => false` for **every** role; `add_filter( 'upload_mimes', '__return_empty_array' )`; remove the "Add Media" button (`remove_action( 'media_buttons', 'media_buttons' )` for our post types). Seed images are static SVG files in the Next.js `public/images/seed/` referenced by relative path `/images/seed/<file>.svg`.

### 4.5 Admin list column & filter (P6)
On `cc_service` list: sortable column **Last reviewed** (date + text badge "Overdue" when older than 12 months — do not rely on colour alone); a dropdown filter above the list `ccs_review` = All / "Reviewed in last 12 months" / "Older than 12 months" (implemented via `pre_get_posts` + `meta_query` on `_cc_last_reviewed`, `DATE` type). Rows older than 12 months get a CSS class (via `post_class` filter, admin only) styled with amber `#F0A93C` left border/background tint (`admin.css`). Also add a **Type** column.

### 4.6 Settings & Home content (P7, requirement 5)
Use the Settings API (`register_setting` with `sanitize_callback`), menu **CareConnect** (top-level, `dashicons-heart`), two submenus:

1. **Settings** (cap `ccs_manage_settings`): option `ccs_settings` =
   `contact_email` (`sanitize_email`, default `admin_email`), `featured_count` (int 1–6, default **3**), `vercel_deploy_hook` (`esc_url_raw`, must start with `https://api.vercel.com/`, optional).
2. **Home Content** (cap `ccs_manage_home`): option `ccs_home` = `hero_title` (text, ≤ 80), `hero_subtitle` (text, ≤ 160, "one sentence"), `featured_ids` (ordered list of up to `featured_count` published `cc_service` IDs, picked via multi-select). Nonce + cap check on save.

### 4.7 REST API (P3) — namespace `careconnect/v1` (exact)
All routes `GET`, `permission_callback => '__return_true'` (public by design, read-only), **published items only** (`post_status => 'publish'` hard-coded in the query; never trust request input for status). Validate/sanitise args via `args` with `validate_callback` / `sanitize_callback`.

| Route | Args | Response |
|---|---|---|
| `/services` | `search` (string, ≤100), `type` (term slug, must exist else empty result), `page` (int ≥1, default 1), `per_page` (1–50, default 9) | `{ items: ServiceCard[], total, total_pages, page, per_page }` + headers `X-WP-Total`, `X-WP-TotalPages` |
| `/services/(?P<slug>[a-z0-9-]+)` | – | `ServiceDetail` or `404 {code:"ccs_not_found"}` |
| `/service-types` | – | `[{slug,name,count}]` (only terms with published services) |
| `/articles` | `page`, `per_page` (default 9, max 50), `category` (slug) | `{ items: ArticleCard[], total, total_pages, page, per_page }`, ordered by date desc |
| `/articles/(?P<slug>…)` | – | `ArticleDetail` or 404 |
| `/home` | – | `{ hero:{title,subtitle}, featured_services: ServiceCard[], latest_articles: ArticleCard[3] }` |
| `/contact` | **POST** JSON `{name,email,message,website,ts}` | `201 {ok:true}` or `4xx {code,message,errors?:{field:msg}}` |

Search: `WP_Query` `s` (title + full description) plus type via `tax_query`; order by `title ASC`. Empty search returns all. Cache nothing in PHP; send `Cache-Control: public, max-age=60` on GET responses.

```jsonc
// ServiceCard
{ "id": 12, "slug": "sunrise-home-visits", "name": "…",
  "type": { "slug": "home-care", "name": "Home Care" },
  "short_description": "…", "image": { "url": "/images/seed/home-care.svg", "alt": "…" } | null }
// ServiceDetail = ServiceCard + {
  "content_html": "…wp_kses_post( wpautop() )…", "eligibility": "…", "address": "…", "phone": "+65 6555 0101",
  "opening_hours": "…", "last_reviewed": "2026-01-15", "lat": 1.3, "lng": 103.8,
  "related": ServiceCard[≤3 published only] }
// ArticleCard   { id, slug, title, summary, date: "2026-03-02", category:{slug,name}|null, image|null }
// ArticleDetail = ArticleCard + { content_html }
```

**Contact (`POST /contact`)** — validation: `name` 1–100, `email` `is_email`, `message` 10–2000; all via `sanitize_*`. Spam protection: (a) honeypot field `website` must be empty, (b) `ts` (ms epoch when form rendered) must be ≥ 3 s old and ≤ 2 h old, (c) rate limit 5 / hour per IP using a transient keyed by `wp_hash( IP )` (never store raw IP). Reject with generic message. On success: insert `cc_message` (`post_status=private`, title = name, content = message, meta `_cc_email`), then `wp_mail()` to `contact_email` (ignore failure; log via `error_log`). Because this is a public endpoint there is no nonce; abuse is mitigated by (a)–(c) + CORS + size limits.

**CORS (`class-cors.php`)**: `remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' )` (core reflects any origin) and add our own for the `careconnect/v1` namespace only: allow the exact origins in env `CC_ALLOWED_ORIGINS` (comma-separated), methods `GET, POST, OPTIONS`, header `Content-Type`, `Vary: Origin`, **no credentials**. Handle preflight.

**Shortcode (P4)**: `[careconnect_services]` renders a `<form role="search">` (labelled search input + type `<select>`), a results `<div aria-live="polite">`, and enqueues `assets/services-list.js` **only when the shortcode is present**. JS (vanilla, no libraries) calls `/wp-json/careconnect/v1/services` using `rest_url()` passed through `wp_localize_script`, renders cards with `textContent` (never `innerHTML` with API data), shows "No services match your search." when empty, and has Previous/Next paging. It is demonstrated on the page `/wp-services-demo/` created by the bootstrap script.

### 4.8 Vercel rebuild hook (Should)
`class-vercel-hook.php`: on `transition_post_status` involving `publish` for `cc_service`/`cc_article`, and on `update_option_ccs_home`, if `vercel_deploy_hook` is set: `wp_remote_post( $hook, ['blocking'=>false,'timeout'=>2] )`, debounced with a 60 s transient. Never throw on failure.

### 4.9 Activation / deactivation / uninstall (P8)
- **Activation:** register CPTs/taxonomies, create the two roles, grant caps to administrator, store `ccs_db_version`, `flush_rewrite_rules()`. Do **not** seed here (seeding is the WP-CLI command below, run by the bootstrap).
- **Deactivation:** `flush_rewrite_rules()` only.
- **`uninstall.php`:** guard `WP_UNINSTALL_PLUGIN`; delete all posts of the four CPTs (force), their terms, options `ccs_settings`, `ccs_home`, `ccs_db_version`, `ccs_seeded`, transients with prefix `ccs_` (use `$wpdb->prepare()` + `$wpdb->esc_like()` for the LIKE), remove roles `cc_editor`, `cc_publisher`, and remove the custom caps from administrator.

### 4.10 Seed data (idempotent)
WP-CLI command `wp careconnect seed` (class-cli.php, only registered when `WP_CLI`). Skips if option `ccs_seeded` is set; `--force` re-seeds. Creates **3 service types** and **≥ 8 services**, **4 articles**, all `publish`, and sets home options (hero text; featured = first 3). Use these exact fictional records (dates relative to *today*):

| # | Service name | Type (slug) | Phone | Last reviewed |
|---|---|---|---|---|
| 1 | Sunrise Home Visit Programme | Home Care (`home-care`) | +65 6555 0101 | −2 months |
| 2 | Evergreen Meal Delivery | Home Care | +65 6555 0102 | −5 months |
| 3 | Maple Respite Support | Home Care | +65 6555 0103 | **−14 months** (overdue demo) |
| 4 | Harbour Community Clinic | Community Clinic (`community-clinic`) | +65 6555 0104 | −3 months |
| 5 | Lantern Health Screening Hub | Community Clinic | +65 6555 0105 | −8 months |
| 6 | Willow Rehabilitation Corner | Community Clinic | +65 6555 0106 | **−18 months** (overdue demo) |
| 7 | Bamboo Seniors Activity Centre | Senior Activity (`senior-activity`) | +65 6555 0107 | −1 month |
| 8 | Coral Weekend Wellness Club | Senior Activity | +65 6555 0108 | −10 months |
| 9 | Pebble Digital Skills Corner | Senior Activity | +65 6555 0109 | −4 months |

Each gets: short description (< 200 chars, invent neutral text), full description (2 short paragraphs), eligibility sentence, address `"<n> Example Lane, Singapore 0000NN"`, opening hours (2–3 lines), image `/images/seed/<type-slug>.svg` with meaningful alt text, 0–3 related services within the list. Services 1, 4, 7 have lat/lng (fictional points inside Singapore). Articles (4): "Five tips for choosing a care service", "How to prepare for a first clinic visit", "Staying active: gentle exercises at home", "Understanding eligibility for community programmes" — categories `Guides` and `Wellbeing`, summaries 1–2 sentences, 3-paragraph bodies, spaced weekly into the past.

### 4.11 Tests (P10) — PHPUnit, ≥ 3 (aim for 6)
Use `wp scaffold plugin-tests careconnect-services` to generate `bin/install-wp-tests.sh`, `phpunit.xml.dist`, `tests/bootstrap.php` (do not hand-write these). Tests: (1) phone validator accepts `+65 6555 0101`, `65550101` rejects `12345`; (2) short description > 200 chars rejected; (3) REST `/services` never returns draft/pending; (4) REST `type` filter and `search` work; (5) editor role cannot publish, publisher can; (6) `/contact` rejects filled honeypot. Run against a throw-away local MySQL; never against the VPS database.

## 5. Headless theme (provided)
Do not modify except for proven bugs. Behaviour: redirects every front-end request to `CC_FRONTEND_URL`, except page slug `wp-services-demo`.

## 6. Next.js spec (`web/`)

### 6.1 Setup
`create-next-app` latest, TypeScript, App Router, Tailwind, ESLint, `src/` dir. `next.config.ts`: `output: 'export'`, `trailingSlash: true`, `images: { unoptimized: true }`. Build output dir: `out`. **No server-only features** (no Route Handlers, no middleware, no `revalidate`, no `next/image` optimisation, no cookies/headers). Use plain `<img>` with explicit `width`/`height`, `loading="lazy"` (not for the first/LCP image), `decoding="async"`. Fonts: system font stack (no font downloads).

Env (public; no secrets in this app): `NEXT_PUBLIC_WP_API_URL` (e.g. `https://careconnect-wp.onrender.com/wp-json/careconnect/v1`), `NEXT_PUBLIC_SITE_URL`. Provide `.env.example`. Fail the build with a clear message if `NEXT_PUBLIC_WP_API_URL` is missing.

### 6.2 Routes (all statically generated; dynamic ones use `generateStaticParams` + `export const dynamicParams = false`)
| Path | Data | Notes |
|---|---|---|
| `/` | `GET /home` (build time) | Hero (title, sentence, search box → GET to `/services/?q=…`), featured service cards, latest 3 articles |
| `/services/` | client-side `GET /services` & `/service-types` | Search box + type `<select>`, cards (name, type, short description, link), paging, **"No services match your search."** message; `?q=` and `?type=` read via `useSearchParams` inside `<Suspense>`; "Waking up…" state |
| `/services/[slug]/` | `GET /services/{slug}` (build time) | breadcrumb (Home › Services › Name), title + type badge, image, description (`content_html`), eligibility, details panel (address, phone as `tel:` link, hours), optional OSM map (see below), related services |
| `/articles/` | `GET /articles` (build time, loop all pages) | cards: image, title, summary, date |
| `/articles/[slug]/` | `GET /articles/{slug}` | title, date, image, body |
| `/contact/` | – | intro, form (name, email, message, hidden honeypot `website`, hidden `ts`), inline per-field errors, success + error banners |
| `/privacy/` | static | short fictional privacy text |
| `/sitemap.xml`, `/robots.txt` | via `app/sitemap.ts`, `app/robots.ts` with `export const dynamic = 'force-static'` | demo is `noindex` (robots disallow + `<meta name="robots" content="noindex">`) |
| `not-found.tsx` | – | friendly 404 |

**Build-time fetching** goes through one helper `lib/api.ts` with: typed responses (types mirror §4.7 exactly), `fetch` with timeout 30 s, **up to 6 retries with 10 s back-off** (the free Render instance may be asleep), and a thrown error with the failing URL if all fail. Detail pages render `content_html` inside a `.prose` container (trusted: server-sanitised with `wp_kses_post` and written only by authenticated users).

**Map (Nice):** if `lat`/`lng` present, render an `<iframe>` of the OpenStreetMap embed (`https://www.openstreetmap.org/export/embed.html?bbox=…&marker=…`), `loading="lazy"`, `title="Map showing <name>"`, plus a plain text link "View larger map". No map JS library.

**Contact form (client component):** `fetch(POST {API}/contact)` JSON; disable the button while sending; map `errors` to fields with `aria-describedby` and `aria-invalid`; success message in `role="status"`, errors in `role="alert"`; set `ts = Date.now()` on mount; honeypot input visually hidden with `tabindex="-1"` `autocomplete="off"` and `aria-hidden="true"`.

### 6.3 SEO (requirement 8)
Every page exports `generateMetadata` (or `metadata`): unique `<title>` (`"<Page> | CareConnect"`), `description` (service → `short_description`; article → `summary`; others static ≤ 160 chars), canonical URL, Open Graph basics. Clean URLs come from the slugs above.

### 6.4 Design system (from the brief; implement as Tailwind theme tokens / CSS variables)
| Token | Value |
|---|---|
| `--cc-primary` | `#16294D` (header, headings, footer) |
| `--cc-accent` | `#0E8F9C` (decorative accents, focus ring, borders) |
| `--cc-accent-strong` | `#0B7580` (**buttons and text links** — see note) |
| `--cc-bg` / surface | `#EDF2F7` page background; `#FFFFFF` cards |
| `--cc-warn` | `#F0A93C` (highlights, badges; use dark text on it) |
| `--cc-text` | `#1A1A1A` |

**Documented deviation (explain in README):** white text on `#0E8F9C` is ≈ 3.9:1, which fails the 4.5:1 target for normal-size text. Buttons and text links therefore use `#0B7580` (≈ 5.4:1 on white; verify with a contrast checker) and the brief's teal is kept for accents. Hover darkens further.

- One sans-serif family: `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`. Body 16 px / line-height 1.6; h1 40 px (32 px under 640 px), h2 32, h3 24. Do not exceed 40 px.
- Cards: white, `border-radius: 8px`, light shadow, 16 px padding (mobile) / 24 px (≥ 768 px). Buttons: teal background (strong variant), white text, min 44×44 px target, **visible focus outline** (3 px `--cc-accent`, 2 px offset) on all interactive elements.
- Layout: mobile-first CSS; 1 column at 360 px, 2 columns ≥ 640 px, 3 columns ≥ 1024 px; max content width 1120 px; no horizontal scroll at 360 px.
- Components (each its own file in `src/components/`): `Header` (4 links: Home, Services, Articles, Contact; mobile menu button with `aria-expanded`; works without JS dropdown trickery — a `<details>`-style or a small client toggle), `Hero`, `ServiceCard`, `ArticleCard`, `SearchBox`, `TypeFilter`, `DetailsPanel`, `Breadcrumb` (`nav aria-label="Breadcrumb"`, `aria-current="page"`), `FormField` (label + error), `Footer` (the four links + Privacy).

### 6.5 Accessibility & performance checklist (enforce in code review)
- "Skip to content" link first in the DOM; landmarks `header`, `nav`, `main`, `footer`; exactly one `h1` per page, no skipped levels.
- Every `<img>` has `alt` (from API; decorative → `alt=""`); every input has a `<label>`; errors tied by `aria-describedby`.
- Full keyboard operation; respect `prefers-reduced-motion`; colour never the only signal.
- No client JS on pages that don't need it (only `/services/`, `/contact/`, mobile menu are client components). No analytics, no third-party scripts, no web fonts.
- Seed images are small SVGs (< 5 KB each) in `public/images/seed/` — create three simple original illustrations (`home-care.svg`, `community-clinic.svg`, `senior-activity.svg`) plus `article.svg`.

## 7. Environment variables

**Render (WordPress)** — defined in `render.yaml`; secrets are prompted (`sync: false`).
| Var | Purpose |
|---|---|
| `WORDPRESS_DB_HOST` | `<vps-public-ip>:3306` |
| `WORDPRESS_DB_NAME` / `WORDPRESS_DB_USER` | `careconnect` / `careconnect` |
| `WORDPRESS_DB_PASSWORD` | from `/root/.careconnect-db-pass` on the VPS |
| `CC_DB_SSL` | `1` (default). `0` only for local dev without TLS |
| `WORDPRESS_*_KEY` / `*_SALT` | auto-generated by Render |
| `WORDPRESS_CONFIG_EXTRA` | proxy HTTPS fix, TLS flag, `WP_HOME`/`WP_SITEURL`, `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS` |
| `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL` | first-run admin |
| `CC_DEMO_PASSWORD` | password for `demo_editor` / `demo_publisher` (optional) |
| `CC_FRONTEND_URL` | Vercel URL (theme redirect target) |
| `CC_ALLOWED_ORIGINS` | exact Vercel origin(s) for CORS |
| `CC_SITE_URL` | optional override; defaults to Render's `RENDER_EXTERNAL_URL` |

**Vercel (Next.js):** `NEXT_PUBLIC_WP_API_URL`, `NEXT_PUBLIC_SITE_URL`.

TLS note for README: `MYSQLI_CLIENT_SSL` **encrypts** the connection but does not verify the server certificate (WordPress has no CA option; the VPS cert is self-signed). Risk is mitigated by the IP allow-list + per-IP DB users. Next step if more assurance is needed: mount a CA and patch the connection, or put MySQL behind a private network/tunnel.

## 8. Deployment runbook (write this into README.md)

### 8.1 MySQL on the VPS
1. `scp scripts/vps-mysql-setup.sh do-sg:/tmp/` then `ssh do-sg`.
2. Get Render's **outbound IPs** for the Singapore region (Render Dashboard → service → Connect → Outbound, or Render docs).
3. `sudo bash /tmp/vps-mysql-setup.sh "<ip1> <ip2> …"` — installs MySQL 8 only if missing, enforces TLS, creates DB + per-IP users, configures UFW (SSH kept open first). Re-running is safe.
4. Read the password once: `sudo cat /root/.careconnect-db-pass`. If the VPS has a provider cloud firewall, also allow TCP 3306 from the same IPs.
5. Verify from the VPS: `mysql -e "SHOW VARIABLES LIKE 'require_secure_transport'"` → `ON`.

### 8.2 WordPress on Render (Docker + Blueprint)
Render deploys this as a **Docker web service defined by `render.yaml`** (a Blueprint). Note: no dedicated one-click "WordPress template" was verified in Render's current docs — do not assume one exists; the Blueprint below is the supported mechanism.
1. Push the repo to GitHub/GitLab.
2. Render Dashboard → **New → Blueprint** → select the repo → Apply. Region `singapore`, plan `free`.
3. Fill the prompted secrets (`WORDPRESS_DB_HOST`, `WORDPRESS_DB_PASSWORD`, `WP_ADMIN_PASSWORD`, `CC_DEMO_PASSWORD`, `CC_FRONTEND_URL`, `CC_ALLOWED_ORIGINS`). If the Vercel URL isn't known yet, put a placeholder and update after step 8.3, then redeploy.
4. Watch the logs for `[cc-bootstrap] bootstrap complete`. First start takes a few minutes. Then check `https://<service>.onrender.com/wp-json/careconnect/v1/services` returns JSON with 9 items, and log in at `/wp-login.php`.
5. Every restart/redeploy resets the container filesystem; the DB persists, so the bootstrap simply re-activates the plugin (idempotent).

### 8.3 Next.js on Vercel (static)
1. Vercel → Add New Project → same repo, **Root Directory `web`**, framework Next.js, build `next build`, output `out` (auto with `output: 'export'`).
2. Env vars: `NEXT_PUBLIC_WP_API_URL=https://<render-service>.onrender.com/wp-json/careconnect/v1`, `NEXT_PUBLIC_SITE_URL=https://<project>.vercel.app`.
3. Deploy. **The Render service must be reachable during the build** (the retry helper wakes it).
4. Update Render `CC_FRONTEND_URL` and `CC_ALLOWED_ORIGINS` to the Vercel URL → redeploy Render.
5. Optional auto-rebuild: Vercel → Settings → Git → Deploy Hooks → create hook → paste into WP admin → CareConnect → Settings → "Vercel deploy hook".

### 8.4 Local development
`docker-compose.yml` (local only; do not deploy): `db` (`mysql:8`, root + `careconnect` db) and `wordpress` (build from `Dockerfile`, port 8080, env as in §7 with `CC_DB_SSL=0`, `WP_ADMIN_PASSWORD=dev`). `cd web && npm run dev` with `NEXT_PUBLIC_WP_API_URL=http://localhost:8080/wp-json/careconnect/v1` and `CC_ALLOWED_ORIGINS=http://localhost:3000`.

## 9. Security checklist (verify, don't assume)
- [ ] Every REST route has explicit `permission_callback`; only `/contact` accepts POST.
- [ ] Draft/pending/private items never appear in any endpoint (test exists).
- [ ] Output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`); no `innerHTML` with API data in `services-list.js`.
- [ ] Nonces + `current_user_can()` on every admin save/settings handler.
- [ ] CORS restricted to `CC_ALLOWED_ORIGINS`, no credentials.
- [ ] `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`; XML-RPC disabled (`add_filter( 'xmlrpc_enabled', '__return_false' )` in plugin); users endpoint hidden for anonymous; `blog_public=0`.
- [ ] No secrets in the repo; `.env*` ignored; seed/demo passwords come from env.
- [ ] DB reachable only from allow-listed IPs, TLS required, per-IP user with DB-scoped grant.
- [ ] Admin MFA is **not** implemented in the demo (state it in README as a production next step).

## 10. Build order
1. Plugin skeleton, CPTs/taxonomies, roles, uploads lock, activation/uninstall.
2. Meta boxes + validation + sanitised saving; admin column/filter; settings + home screens.
3. REST endpoints, CORS, contact, shortcode + JS; WP-CLI seed.
4. PHPUnit tests + `phpcs` clean.
5. `docker-compose.yml` local run; confirm seed shows 9 services / 4 articles and `/wp-services-demo/` works.
6. Next.js app: `lib/api.ts` + types → layout/components → pages → metadata/sitemap → a11y pass → Vitest smoke tests.
7. README + `docs/design.md`.
8. Deployment per §8 (only if credentials/access are available; otherwise leave the runbook and say it is untested).

## 11. Definition of Done
- [ ] Brief requirements 1–8 and P1–P8 satisfied; P9/P10 done; #9 (second language) and #10 (map) per §12/§6.2.
- [ ] `composer phpcs` and `composer test` pass; `npm run lint`, `npm run build` (static export) and `npm test` pass.
- [ ] Seed: 9 services across 3 types (≥ 8 required), 4 articles; 2 services show "Overdue".
- [ ] Editor can create/edit but not publish; Publisher can publish; unpublished never public.
- [ ] Services search + type filter + paging + "no match" message work on Next.js **and** via the shortcode.
- [ ] 360 px layout has no horizontal scroll; keyboard-only run-through of all pages passes; contrast verified.
- [ ] README includes: setup, deploy steps, assumptions, **"Media is non-persistent in the demo (no uploads; images are static files/URLs)"**, free-tier cold start note, TLS note, design deviations (button colour).
- [ ] No real personal data anywhere.

## 12. Out of scope / known limitations (list in README "Risks and next steps")
Second language (Nice #9), Admin MFA, cert-verified DB TLS, persistent media storage, ISR/on-demand revalidation (static export + deploy hook instead), CAPTCHA (honeypot + timing + rate limit only), real SMTP (email is best-effort on the demo), monitoring/alerting.
Optional stretch if time remains: Chinese (`zh`) strings dictionary with `/zh/` routes and English fallback.

## 13. `docs/design.md` (required by the brief; 2–3 pages, Markdown)
Sections in this order, each concise: **Architecture** (include the diagram from §2 and a request-flow description; CMS choice WordPress; rejected alternative, e.g. Strapi or Sanity, with one-sentence reason) · **Content model** (§4.2, roles/workflow draft → pending → publish) · **Hosting** (production proposal: AWS `ap-southeast-1` Singapore or equivalent for data residency; dev/staging/prod; CI/CD via Git → Docker image → deploy; and note how the demo differs) · **Security** (HTTPS, MFA, roles, backups, secrets, OWASP mitigations) · **Operations** (monitoring, logs, backups, proposed RPO 24 h / RTO 4 h for 20,000 visits/month and 5 editors) · **Cost** (small table with stated assumptions; demo cost = free tiers + existing VPS) · **Risks and next steps**. Do not state prices as facts unless you looked them up; label them "estimates, verify".
