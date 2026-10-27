# CareConnect

A fictional, accessible community-care directory with a WordPress editorial backend and a statically exported Next.js frontend. The supplied brief is **`AGENT.md`**, singular. The separate [production design](docs/design.md), also available as a [three-page PDF](docs/design.pdf), explains the seven design requirements for 20,000 monthly visits and five editors, using client-friendly language and diagrams. [Verification](docs/verification.md) records the checks actually run.

**Media is non-persistent in the demo (no uploads; images are static files/URLs).** All seed services, addresses, phones and contact examples are fictional. Do not submit real personal or medical information to this demonstration.

## Production design guide

| Guideline requirement                                           | Dedicated documentation                                                                |
| --------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| Architecture, request flow, CMS choice and rejected alternative | [1. Architecture](docs/design.md#1-architecture--how-visitors-reach-the-content)       |
| Content fields, roles and publishing workflow                   | [2. Content model](docs/design.md#2-content-model--what-the-team-manages)              |
| Cloud, Singapore region, environments and deployment            | [3. Hosting](docs/design.md#3-hosting--locations-environments-and-releases)            |
| HTTPS, MFA, permissions, secrets and attack protection          | [4. Security](docs/design.md#4-security--protecting-visitors-and-the-team)             |
| Monitoring, logs, backup/restore frequency and RPO/RTO          | [5. Operations](docs/design.md#5-operations--detecting-problems-and-recovering)        |
| Monthly estimate and assumptions                                | [6. Cost](docs/design.md#6-cost--estimated-monthly-budget)                             |
| Known gaps and another week's work                              | [7. Risks and next steps](docs/design.md#7-risks-and-next-steps--another-working-week) |

The guide distinguishes implemented features from production proposals. Vercel caches public pages globally, so the existing setup does **not** establish Singapore-only residency for all copies. MFA, monitored publishing, scheduled backups and recovery targets remain production work. The proposed Singapore hosting and costs do not describe changes applied to the running services.

## Local setup

Prerequisites: Docker with Compose, Node meeting `web/node_modules/next/package.json`'s engine requirement, and npm. Exact frontend and PHP dependency versions are recorded in their manifests and lockfiles. PHP/Composer on the host are optional because the PHP checks can run in Docker.

```bash
docker compose -p careconnect-local up -d --build
# Wait for: [cc-bootstrap] bootstrap complete
docker compose -p careconnect-local logs -f wordpress
```

WordPress: <http://localhost:8080/wp-login.php>. The shortcode demonstration is <http://localhost:8080/wp-services-demo/>. Local-only accounts are `ccadmin` / `dev`, and `demo_editor` or `demo_publisher` / `local-demo-only`. These credentials belong only to the disposable local configuration; production secrets come from Render's prompted environment variables.

```bash
cd web
cp .env.example .env.local
npm ci
npm run dev
```

Open <http://localhost:3000>. Home, article lists and service/article details fetch WordPress content during generation; WordPress must be available. Search and contact make browser requests directly to WordPress. The frontend intentionally fails early when `NEXT_PUBLIC_WP_API_URL` is missing.

```bash
cd web
npm run lint
npm run typecheck
npm test
npm run build
npm run preview
```

`npm run build` produces `web/out`; the preview serves that export at port 3000. Stop an existing dev/preview process before starting another on that port. The build uses webpack explicitly, with Tailwind's PostCSS integration. The app uses system fonts and plain `<img>` elements with dimensions, as required by the brief.

The `prebuild` step clears Next.js's persistent API-response cache before exporting. This prevents a rebuild from reusing old WordPress content, including when a hosting provider restores a previous build cache. Compiler caches are preserved. Use `npm run build` locally and as the Vercel build command; calling `next build` directly skips this freshness step. After rebuilding, reload any already-open browser page. The main service description appears under “About this service”; cards and the introductory sentence use the separate **Short description** field.

The local MySQL named volume persists demo state. Normal shutdown is `docker compose -p careconnect-local down`. Removing the named volume deletes only that compose project's local demo database; do so only when intentionally resetting it.

## Plugin and editorial workflow

Plugin location: `wordpress/wp-content/plugins/careconnect-services/`. Services and articles use classic WordPress editing, a single type/category dropdown, nonce-protected metadata, and a shared publish-validation gate. Invalid values retain the previous valid metadata and the submission becomes Draft. Image alt text is required whenever an image URL is present. Editors can manage their own unpublished content and submit it for review; Publishers can publish and manage Home Content; Administrators also manage settings and read private contact messages. Upload capability is denied to every role.

CareConnect → Settings configures the contact email, featured count (1–6) and optional Vercel Deploy Hook. CareConnect → Home Content edits the hero and featured services. Use the multi-select and Move selected up/down controls to order featured items. The service list has Type and sortable Last reviewed columns, a review-age filter and textual **Overdue** badges for reviews older than twelve months.

Bootstrap creates nine services across three types, four articles, two overdue review examples and the shortcode page. Seeding is explicit and idempotent, not part of activation:

```bash
docker compose -p careconnect-local exec wordpress wp --allow-root careconnect seed
# Deliberately reset the fictional seeded records and home content:
docker compose -p careconnect-local exec wordpress wp --allow-root careconnect seed --force
```

Public namespace: `/wp-json/careconnect/v1`.

| Method | Route                                       | Purpose                                                                    |
| ------ | ------------------------------------------- | -------------------------------------------------------------------------- |
| GET    | `/services?search=&type=&page=1&per_page=9` | Search, type filter, title order, bounded paging                           |
| GET    | `/services/{slug}`                          | Published detail and published related services                            |
| GET    | `/service-types`                            | Types with published-service counts                                        |
| GET    | `/articles?page=1&per_page=9&category=`     | Published articles, newest first                                           |
| GET    | `/articles/{slug}`                          | Published article detail                                                   |
| GET    | `/home`                                     | Editable hero, published featured services, three latest articles          |
| POST   | `/contact`                                  | JSON `{name,email,message,website,ts}`; private save and best-effort email |

You can test the sample public API for these endpoints using the following base url prefix hosted on Render: **https://careconnect-wp.onrender.com/wp-json/careconnect/v1**

GET success responses use `Cache-Control: public, max-age=60`; contact and error responses use `no-store`. List responses include totals and pagination headers. CORS accepts only exact `CC_ALLOWED_ORIGINS`, without credentials. Requests without an Origin remain possible because these endpoints are public. All content queries hard-code `publish`; core REST exposure is disabled for the custom types and anonymous core users endpoints are removed.

Contact checks a honeypot, a three-second minimum/two-hour maximum form age, per-field lengths, a 16 KiB body limit and five attempts per hour per salted IP hash. It saves `cc_message` privately before attempting email. The contact form does not automatically replay a POST with an uncertain outcome; a visitor can retry deliberately. Only GETs retry automatically. The server shows “Waking up the demo server…” in the UI after four seconds of waiting.

## Tests

The plugin harness (`bin/install-wp-tests.sh`, `phpunit.xml.dist`, `tests/bootstrap.php`) was generated with `wp scaffold plugin-tests careconnect-services`, then configured to load the installed PHPUnit polyfills and reject HTTP execution of the test bootstrap. It uses the WordPress 6.9.4 test library matching the supplied image family. PHPUnit 9 was selected for compatibility with that WordPress harness; Composer resolved and pinned the latest compatible release. Other direct Composer and npm dependencies are pinned to their resolved versions.

```bash
# Isolated throw-away database, never the VPS or the local content DB:
docker compose -p careconnect-check --profile test run --build --rm tests
# Remove that test project's disposable containers/network after the run:
docker compose -p careconnect-check --profile test down
```

The test image disables certificate checking only for its disposable MySQL client connection; it is not used by Render or the application. The plugin test script hard-codes the isolated `test-db` host and `careconnect_tests` database. On an existing PHP/Composer environment with its own disposable WordPress test DB, run `composer install`, `composer phpcs` and `composer test` from the plugin directory.

WPCS checks all authored runtime PHP and integration tests. Its narrow ruleset exceptions follow the brief's prescribed `CCS_` prefix/class filenames and required taxonomy/meta filters. Scaffold-generated bootstrap files run before WordPress defines `ABSPATH`, so they retain the upstream test bootstrap rather than a runtime-only guard. All authored application PHP is guarded.

`web/scripts/browser-check.mjs` checks the live static export and local WordPress in Chromium: every content route at 360/1280 px, axe WCAG A/AA checks, keyboard skip/menu navigation, search/type/empty states, both pagination UIs, actual contact submission, editorial review/publishing and cold-start recovery. It expects Linux Chromium at `/usr/bin/chromium`, the local credentials above, nine initial seeded services, and a running static preview. Run it from `web` with `npm run browser:check`; follow with `npm run keyboard:check` for Tab cycles and visible focus on every content route. It briefly creates a fictional workflow service, trashes it, and saves a fictional contact message. Reports/screenshots go in `docs/review/`.

## Configuration

Frontend `.env.local` (only public values):

| Variable                 | Local example                                  |
| ------------------------ | ---------------------------------------------- |
| `NEXT_PUBLIC_WP_API_URL` | `http://localhost:8080/wp-json/careconnect/v1` |
| `NEXT_PUBLIC_SITE_URL`   | `http://localhost:3000`                        |

Render's supplied `render.yaml` declares `WORDPRESS_DB_HOST`, `WORDPRESS_DB_NAME`, `WORDPRESS_DB_USER`, `WORDPRESS_DB_PASSWORD`, `WORDPRESS_TABLE_PREFIX`, `CC_DB_SSL`, eight `WORDPRESS_*_KEY`/`*_SALT` values, `WORDPRESS_CONFIG_EXTRA`, `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL`, `CC_DEMO_PASSWORD`, `CC_FRONTEND_URL` and `CC_ALLOWED_ORIGINS`. `CC_SITE_URL` optionally overrides the bootstrap default from `RENDER_EXTERNAL_URL`. Keep deploy-hook URLs and all secrets out of the frontend and source control. `.env*`, dependencies and build outputs are ignored; `.env.example` is committed.

## Deployment runbook

The owner reports that Vercel, Render and VPS MySQL are deployed and communicating. These instructions describe that setup; the agent has not changed those remote services or independently audited their private configuration, firewall rules or backup arrangements.

Bootstrap order: **create the Render service → obtain its outbound ranges → configure VPS MySQL → finish the Render deployment → deploy Vercel**. Creating the Render service does not require WordPress to have a working database yet. Apply the Blueprint first, using the VPS address for `WORDPRESS_DB_HOST` and a temporary placeholder for `WORDPRESS_DB_PASSWORD` if the generated database password is not available. The initial bootstrap can report that the database is unreachable; do not wait for a functional WordPress site before opening Connect → Outbound. After MySQL is ready, replace the placeholder with the real password and redeploy to rerun bootstrap.

### MySQL on the VPS

Check `free -h`, `swapon --show`, `df -h /` and existing workloads first. A shared 1 GiB VPS exhausted RAM during MySQL package setup in this session. The canonical script now refuses to start if available RAM plus free swap is below a conservative 512 MiB threshold; this is a protective heuristic, not a MySQL minimum or a capacity guarantee. On a small shared VPS, arrange memory headroom before installation and install the optional `scripts/mysql-low-memory.cnf` as `/etc/mysql/mysql.conf.d/zz-careconnect-memory.cnf` before starting or configuring the server. That profile was accepted by MySQL 8.0 and 8.4 configuration validation. It reduces buffers and connections while retaining Performance Schema for the TLS readiness check. It is not a hard process memory limit; monitor actual usage and retain enough headroom for existing applications. Swap created manually must be added to `/etc/fstab` if it should survive reboot.

1. Copy the provided script: `scp scripts/vps-mysql-setup.sh do-sg:/tmp/`, then connect with `ssh do-sg`.
2. Get the service's current Singapore **outbound** IP addresses from Render Dashboard → service → Connect → Outbound. Verify them for the actual service; do not assume inbound addresses are equivalent.
3. Review the script, then run `sudo bash /tmp/vps-mysql-setup.sh "<ip1> <ip2> ..."`. It installs MySQL only if missing, requires secure transport, creates database-scoped per-source accounts and preserves SSH before enabling UFW. Do not run it without the reviewed source list.
4. Read `/root/.careconnect-db-pass` once with sudo and enter it directly into Render's secret prompt. Do not copy it into this repository, logs or shell history. Restrict the VPS provider firewall to the same sources on TCP 3306.
5. Verify on the VPS: `mysql -e "SHOW VARIABLES LIKE 'require_secure_transport'"` should show `ON`. Check effective MySQL grants, UFW/provider rules and connectivity from Render. These external controls have not been tested here.

Pass Render's full CIDR ranges unchanged as the quoted, space-separated argument. MySQL supports CIDR account hosts from 8.0.23 onward; the script checks the server version before changing its configuration. The canonical script in `scripts/` was intentionally patched on 26 October to replace the removed MySQL 8.4 `have_ssl` check with the active `performance_schema.tls_channel_status` check and to add the pre-install memory guard after a confirmed remote OOM event. These are documented bug exceptions to the supplied-file protection rule. The updated TLS query was checked against local MySQL 8.4. The owner subsequently reported a working deployment, but the agent has not accessed the VPS; database grants, TLS settings and firewall rules remain independently unverified. The original flat starter copy is retained for reference. See the [MySQL CIDR account documentation](https://dev.mysql.com/doc/refman/8.4/en/account-names.html) and [MySQL 8.4 removal notes](https://dev.mysql.com/doc/relnotes/mysql/8.4/en/news-8-4-0.html).

TLS note: the supplied `MYSQLI_CLIENT_SSL` connection encrypts traffic but does not verify the self-signed server certificate. IP allow-lists and per-IP DB users reduce exposure but do not provide certificate authentication. A production deployment should mount a trusted CA and adapt the connection or use a private network/tunnel.

### WordPress on Render

1. Push the repository to GitHub/GitLab. Render Dashboard → New → **Blueprint** → select repository → Apply, if the service was not already created to obtain its outbound ranges. Use the supplied Docker web service Blueprint, Singapore region and free plan; no dedicated WordPress template is assumed.
2. Fill prompted DB/admin/demo secrets and the final `CC_FRONTEND_URL` / `CC_ALLOWED_ORIGINS`. Replace any temporary database password with the password generated during VPS setup, then redeploy. If the frontend URL is not yet known, use a temporary value and replace it before public testing.
3. Watch for `[cc-bootstrap] bootstrap complete`. The health GIF can return 200 before setup finishes, so also check the JSON endpoint and admin login.
4. Confirm `/wp-json/careconnect/v1/services` returns nine services and `/articles` returns four. Confirm the role, draft and contact boundaries against the deployed origin.
5. The Render free service spins down after roughly fifteen idle minutes and can take time to wake. Its filesystem resets on restart/redeploy; database content persists. Stable salts come from Render environment values. Do not enable uploads or depend on container filesystem media.

### Next.js on Vercel

1. Add a project from the same repository with Root Directory **`web`**, framework Next.js, build `npm run build`, output **`out`**.
2. Set `NEXT_PUBLIC_WP_API_URL=https://<render-service>.onrender.com/wp-json/careconnect/v1` and `NEXT_PUBLIC_SITE_URL=https://<project>.vercel.app`.
3. Deploy while WordPress is reachable. The build helper retries to wake a sleeping Render service; exhaustion fails with the affected URL instead of substituting mock content.
4. Update Render `CC_FRONTEND_URL` and the exact comma-separated `CC_ALLOWED_ORIGINS` to the Vercel origin, then redeploy Render. Include each allowed preview origin explicitly if previews are used; no wildcard is accepted.
5. Optional rebuild automation: Vercel Settings → Git → Deploy Hooks → create a hook; paste its HTTPS `api.vercel.com` URL into CareConnect → Settings. Changes involving published services/articles or Home Content request one non-blocking rebuild per minute. Monitor hook/build results externally.

Static export freshness: new or changed detail pages appear only after a successful rebuild. Live search can see a newly published service before its detail page exists in the current export. Withdrawn content disappears from the API immediately, but previous static HTML can persist until rebuild/cache replacement. This limitation is documented rather than described as immediate revocation.

## Assumptions and design decisions

- `AGENT.md` is the requested source of truth. Its flattened starter files were initially copied byte-for-byte into `docker/`, `scripts/` and `wordpress/wp-content/themes/careconnect-headless/`. Original flat files remain unchanged. The canonical VPS setup script now contains the documented MySQL 8.4 compatibility fix and pre-install memory guard; other copied supplied behavior remains unchanged.
- The provided Dockerfile's floating `wordpress:6-php8.3-apache` reference is preserved under the explicit do-not-modify rule. It resolved to **WordPress 6.9.4** during verification. The full `6.9.4-php8.3-apache` tag was checked in Docker Hub; the new test image pins that full tag and observed digest. Pin/review the application image and WP-CLI artifact before a production release; no unverified major upgrade was performed.
- The uninstall paragraph says “four CPTs” but defines three; uninstall removes the three specified types and both specified taxonomies. Deactivation only flushes rewrites; data deletion occurs on explicit uninstall.
- A single article category is required, matching the service single-type workflow. Empty optional coordinates are represented as `null` in the API; maps render only when both coordinates exist.
- Root-relative image paths belong to the frontend. The shortcode renders text cards, and does not try to load frontend SVGs from WordPress.
- The Home multi-select saves selected services in its visible list order; arrow buttons make that order editable. Invalid settings preserve the previous email/deploy-hook value.
- English only. PHP UI strings are translatable and the generated POT is included. A second-language frontend is out of scope.
- `NEXT_PUBLIC_SITE_URL` falls back to the local preview origin for development; set it explicitly on Vercel. `NEXT_PUBLIC_WP_API_URL` has no content fallback and is required.
- No third-party scripts, web fonts or analytics. Optional maps use a lazy OpenStreetMap iframe with an external larger-map link and fictional illustrative points.
- The brief's teal `#0E8F9C` is decorative. White normal-size text on it misses WCAG AA, so buttons and text links use `#0B7580`. The measured WCAG contrast ratios are recorded in verification; all controls have visible focus and buttons meet the 44 px target.

## Risks and next steps

Admin MFA is **not implemented** in the demo. Production also needs certificate-verified DB TLS, reviewed backups/restore drills, monitoring/alerting, transactional SMTP, retention policies and potentially atomic edge throttling/CAPTCHA. The current transient rate limiter is suitable for this demonstration, not a distributed abuse-control guarantee. A reverse proxy may expose a shared connection IP; validate trusted address handling before launch.

Second language, persistent media storage, ISR/on-demand revalidation and CAPTCHA remain out of scope. Vercel deploy-hook failures are non-fatal and need external monitoring; the sixty-second debounce is not a durable queue. Immediate removal of previously exported content requires an architecture/operations change. The demo uses no production SLA, SMTP delivery guarantee or verified remote database/firewall configuration. See [design](docs/design.md) for residency and cost estimates, RPO/RTO proposals and the production hosting approach.
