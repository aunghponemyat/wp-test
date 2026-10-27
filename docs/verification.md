# Verification — 25 October 2026

Local implementation verified against WordPress **6.9.4**, PHP 8.3 in the prescribed WordPress image family, disposable MySQL 8, Next.js **16.4.3**, and Chromium. Dependency manifests/lockfiles contain the resolved versions. The original flat supplied files match `files.zip` byte-for-byte. Their copies initially matched as well; the canonical VPS setup script later received the documented MySQL 8.4 compatibility correction below.

## Results

| Check | Result |
|---|---|
| WordPress application Docker build and local bootstrap | Pass; seed reports nine services and four articles |
| `composer phpcs` | Pass, all 17 authored PHP files clean under the documented WPCS ruleset |
| `composer test` | **17 tests, 81 assertions pass** against a throw-away test database |
| Isolated Compose test profile | Install/scaffolded test harness, WPCS and PHPUnit run successfully; separate from demo/VPS data |
| `npm run lint` | Pass, no warnings |
| `npm run typecheck` / build type checking | Pass |
| `npm test` | **9 tests pass** in Vitest/React Testing Library |
| `npm run build` | Pass; complete static export, all nine service and four article detail slugs |
| Missing API environment variable | Build deliberately fails with an actionable `NEXT_PUBLIC_WP_API_URL` message |
| `npm run format:check` | Pass |
| 360 px and 1280 px browser layouts | **36 route/width combinations**, no horizontal overflow |
| axe WCAG 2 A/AA and 2.1 AA checks | **36 scans, zero detected violations** |
| Keyboard traversal | All **18 content routes**; complete Tab cycles, expanded mobile navigation and visible focus rings |
| Metadata | 18 unique content-page titles; descriptions, canonical, noindex, one H1 and alt attributes checked on 20 exports including the two framework 404 aliases |
| SVG sizes | Home care 952 B; clinic 863 B; senior activity 1,090 B; article 768 B; hero 2,068 B; all original and below 5 KB |

Build and browser checks ran in Docker because the session sandbox restricted Next.js child-process output and host Chromium sockets. No runtime workaround was added to the application. The browser exercise blocked the optional OpenStreetMap embed network request with a local placeholder; real map rendering was not verified. Screenshots are [mobile](review/home-360.png) and [desktop](review/home-1280.png). Machine-readable evidence: [browser](review/browser-report.json) and [metadata](review/metadata-report.json).

The browser workflow verified live search plus type filtering and the empty state; both directory pagination controls using a temporary tenth service; a real cross-origin contact POST and success announcement; the shortcode; exact-origin CORS preflight and forbidden-origin rejection; invalid Editor submission retained as Draft; valid Pending Review; Publisher takeover/publication/withdrawal; two overdue review badges and the review-age filter; and the four-second cold-start hint followed by recovery. No frontend page errors were observed. Temporary workflow services were removed after testing; the local demo retains one fictional contact message for inspection.

PHP tests cover phone normalization, Unicode description length, draft/pending/private exclusion, combined filters, explicit empty-filter regression, role/upload boundaries, honeypot rejection, invalid publication, related/featured exclusion, seed idempotency/counts/overdue records, pagination/input bounds, CORS/user endpoints, private contact storage and rate limiting, invalid Pending Review field retention, timing/body bounds, uninstall including auto-drafts/trash, and read-only message content support.

## Definition of Done

- [x] Services/articles/contact/home content, WordPress editorial roles and admin controls implemented in the plugin.
- [x] Plugin tests, WPCS, web lint/type checking, static build and web smoke tests pass.
- [x] Nine fictional services across three types, four articles, two overdue examples.
- [x] Editor/Publisher workflow and published-only API boundaries verified.
- [x] Search, type filter, paging and empty state verified on Next.js and the shortcode.
- [x] 360 px layout, all-page keyboard traversal, visible focus and automated contrast/accessibility checks pass.
- [x] README includes setup, deployment runbook, assumptions, media persistence, cold start, TLS caveat and button-colour deviation.
- [x] English only as specified; PHP POT supplied. Optional iframe maps implemented.
- [x] Application/seed/test content uses fictional identities and reserved example domains/telephone range.
- [ ] Remote VPS firewall, MySQL TLS/grants, Render deployment, Vercel deployment and deployed hook execution: **not verified**.
- [ ] Production image/artifact pinning: supplied rolling application references deliberately preserved; full tag/digest pinned only in the new test image. See README assumptions.

## Security checklist

- [x] Explicit permission callbacks on custom routes; only contact is a public custom POST route.
- [x] Published-only queries for listings, details, home, related items and type counts; regression tests exist.
- [x] Escaped admin output and server-sanitized HTML; shortcode renders API strings using DOM `textContent`.
- [x] Nonces and capability checks on admin metadata/settings handlers; framework permission enforcement retained.
- [x] CORS exact allow-list, no credentials; allowed preflight and rejected origin checked over HTTP.
- [x] XML-RPC disabled, uploads denied, anonymous core users endpoints hidden, `blog_public=0` set.
- [x] File editing/modification disabled in the supplied Render configuration and local compose environment.
- [x] Runtime PHP guards; scaffold test bootstrap restricted to CLI before WordPress initialization.
- [x] No application secrets committed; environment files ignored and production passwords supplied externally.
- [ ] Production DB IP allow-list/TLS/per-source grants: runbook supplied; actual VPS not accessed.
- [x] Admin MFA explicitly documented as absent and required for production.

## Contrast and limits

Calculated using WCAG sRGB relative luminance: white on decorative teal `#0E8F9C` is **3.869:1**; white on button/link teal `#0B7580` is **5.426:1**. The latter exceeds normal-text AA's 4.5:1 threshold. Inline text links retain underlines. The warnings use dark text and a textual label.

Automated accessibility results do not replace a human assistive-technology audit. A tab traversal checks focus and operability, not every screen-reader announcement. Email delivery is best-effort; the contact test confirms private storage and success handling, not external SMTP delivery.

Static exports retain previously published HTML until rebuild/cache replacement. The API immediately rejects withdrawn records, and never-published drafts are not exported. Immediate removal of old static snapshots is not guaranteed. Real database certificate verification, MFA, production SMTP, monitoring, backups and remote hosting checks remain production work, as described in the design/runbook. The application base-image family is intentionally unchanged under the supplied-file protection rule.

## CMS freshness regression — 26 October 2026

A normal rebuild reproduced an outdated Coral Weekend Wellness Club description despite the live API containing the saved edit. Inspection found an old response in `.next/cache/fetch-cache` with a 31,536,000-second lifetime. The new npm `prebuild` hook removes that response-cache directory before each static export while retaining compiler caches. The corrected `npm run build` passed with `.env.local`, and the regenerated HTML and response cache both contained the current edit. A fresh Chromium session confirmed the live preview displays “Indeed!” under “About this service”. The unchanged introductory sentence correctly comes from the separate short-description field. ESLint and the formatting check passed. No CMS content was changed during this investigation.

## VPS script compatibility — 26 October 2026

The supplied script checked `have_ssl`, a variable removed in MySQL 8.4. A read-only query against the local MySQL **8.4.13** server reproduced the missing variable and confirmed the active `mysql_main` TLS channel reports `Yes`. The canonical script now checks that channel and rejects versions outside MySQL 8.x or below 8.0.23, the minimum for CIDR account hosts. Bash syntax checking passed; isolated version-guard checks accepted 8.0.23, 8.0.44 and 8.4.13 and rejected 8.0.22, 9.0.0 and MariaDB 10.11.8. Both user-provided Render ranges validate as canonical IPv4 `/24` networks. These checks did not install software, change MySQL configuration or modify firewall rules on either the local host or the VPS. Actual remote deployment remains untested.

## Small-VPS memory recovery — 26 October 2026

The user supplied kernel evidence of MySQL being OOM-killed on a shared 957 MiB VPS with no swap. The user subsequently enabled a 2 GiB swap file and reported 328 MiB available RAM and 13 GiB free disk. The setup script now checks available RAM plus free swap before package installation and refuses to proceed below a conservative 512 MiB combined threshold. Isolated guard checks rejected 12 MiB/no swap and a value immediately below the threshold, and accepted the threshold and 328 MiB plus 2 GiB swap. This heuristic does not guarantee capacity.

The optional `scripts/mysql-low-memory.cnf` passed `mysqld --validate-config` in the local MySQL 8.0 and 8.4 images; `--print-defaults` confirmed the MySQL 8.0 image loads every setting. It preserves Performance Schema, reduces the buffer pool and log buffer, and limits connections/caches/temporary-table memory. This validates configuration compatibility, not the VPS's runtime memory budget. The agent has not applied it remotely or verified completion of the interrupted package installation.
