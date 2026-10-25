# CareConnect design

## Architecture

```text
Visitor ──► Vercel (Next.js static export, CDN)
              │  build time: fetch content (retry, WP may be asleep)
              │  run time (browser): search + contact POST
              ▼
        Render Web Service (Docker, free, Singapore) ── WordPress + plugin + headless theme
              │  mysqli over TLS, port 3306, allow-listed source IPs
              ▼
        MySQL 8 on the user's VPS (ssh alias: do-sg), UFW restricted, TLS required
```

WordPress supplies the editorial interface, familiar draft/review workflow and capability model. The `careconnect-services` plugin owns the content schema, validation, API, seed records and publishing hook. The supplied theme only redirects public requests and renders the shortcode demonstration. We rejected Sanity for this build because it would replace the required WordPress administration and plugin capability exercises with a separate editorial platform.

Next.js App Router renders static HTML for the home, article, service-detail and policy pages. Its build helper fetches published records from WordPress with a 30-second timeout, six retries and ten-second back-off. The npm prebuild step clears the persistent fetch-response cache so each export uses current CMS data while retaining compiler caches. Dynamic routes enumerate every published slug; article and service enumeration traverses every API page. There are no server Route Handlers, middleware, runtime secrets, ISR or image-optimization service. Search and contact use small client components. A mobile navigation toggle is the only shared client interaction.

A visitor receives cached HTML and small local SVG illustrations from the CDN. Searching sends a credential-free GET to WordPress, combining keyword, type and pagination. Contact sends one JSON POST after the form has been displayed for at least three seconds. Its result confirms private database storage; email delivery is best-effort. Queries taking over four seconds show a cold-start hint. GET requests retry automatically; ambiguous contact POST failures require a deliberate retry to avoid duplicates.

Publication and home-option changes call a debounced Vercel deploy hook. Content changes require a successful rebuild before static pages reflect them. Previously published HTML can remain publicly cached until that rebuild; immediate withdrawal is not guaranteed by this architecture. Drafts that have never been published are never exported or returned by the API. A production requirement for immediate withdrawal would need an authenticated runtime gate or an operational CDN removal process, changing the static-only proposal.

## Content model

| Entity | Storage and key fields | Public representation |
|---|---|---|
| Service | `cc_service`; title/body; one `cc_service_type`; `_cc_` descriptions, eligibility, address, phone, hours, image URL/alt, related IDs, review date, optional coordinates | Card and sanitized HTML detail |
| Article | `cc_article`; title, body, excerpt summary, post date; one `cc_article_category`; URL/alt | Card and sanitized HTML detail |
| Contact | Private `cc_message`; name, message, `_cc_email` | Only a generic submission acknowledgement |
| Home | `ccs_home` option: hero copy and ordered featured IDs | Hero, published featured services, three latest articles |
| Settings | `ccs_settings`: contact destination, featured count, optional deploy hook | Administrator-only option; never serialized publicly |

All three post types disable core REST exposure and public queries. The custom API hard-codes published status for listings, details, featured items, related items and type counts. Input cannot override that status. Service validation enforces required metadata, bounded descriptions, Singapore phone normalization, single taxonomy selection, real calendar dates, safe image schemes/alt text, coordinate bounds and a maximum of three published related services. Invalid submissions remain drafts and retain prior valid field values.

Editors work on their own drafts and can submit Pending Review. Publishers can review others’ content, publish and maintain home content. Administrators also manage settings and private messages. WordPress maps object-level editing capabilities so that an Editor cannot alter published or another author’s content. Messages have a read-only content panel. Upload capability is denied for all roles; images are static frontend files or URLs.

## Hosting

The demonstration uses free Render and Vercel plans plus an existing MySQL VPS. The WordPress container filesystem is disposable. Content, options and users persist in MySQL; stable authentication salts come from Render. Bootstrap reinstalls filesystem assets, activates the plugin/theme and runs idempotent seed setup. The provided WordPress image family currently resolves to 6.9.4; supplied deployment assets are preserved. The test image pins its verified full tag and digest. Production should review and pin application base images as part of release preparation.

For production, propose AWS `ap-southeast-1` (Singapore) or an equivalent Singapore region: CDN/static frontend, managed container hosting for WordPress, managed MySQL with private networking, and encrypted backups. This is a residency proposal, not a guarantee: separately verify CDN caches, logs, email providers, support access and subprocessors. Keep dev, staging and production databases, salts, origins and deployment hooks separate.

CI/CD proposal: Git push → install from lockfiles → PHP style/security checks and disposable MySQL tests → build an immutable WordPress image → deploy to staging → build/export the frontend against staging → browser/accessibility checks → approved production image and frontend deployment. Production database migrations and restore checks precede promotion. Roll back application images and frontend releases together; do not restore a database merely to roll back code.

## Security

HTTPS protects browser and administrative traffic. Public API routes have explicit permission callbacks; contact is the only public write operation. Admin writes use WordPress nonces and mapped capabilities. Input is sanitized, output escaped and content HTML allow-listed; the shortcode inserts API values with `textContent`. Parameterized WordPress query APIs implement search and filtering. The plugin disables XML-RPC and anonymous core user enumeration. Demo pages have `noindex` metadata and a robots disallow rule; neither mechanism is access control.

CORS uses exact configured origins, no credentials, a `Vary: Origin` header and bounded allowed methods/headers. Origin-less clients can still call this public endpoint; CORS is not authentication. Contact combines a honeypot, a three-second/two-hour form window, 16 KiB body bound, per-field limits and five attempts per connection-IP hash per hour. Hashes expire; application rate limiting does not persist raw IPs. Hosting access logs are a separate concern. Reverse-proxy address handling must be verified before production: a shared proxy IP may conservatively throttle multiple visitors. Transients are adequate for the demo but are not an atomic distributed rate limiter.

Keep salts, DB/admin passwords and deploy-hook URLs outside source control and public frontend variables. Administrators should enable MFA in production, use unique accounts, rotate secrets and audit role changes. Restrict database traffic to reviewed Render outbound sources and grant each source account only the CareConnect database. `MYSQLI_CLIENT_SSL` encrypts traffic but the supplied setup does not verify the self-signed server certificate. Production should use a verified CA or private tunnel/network. The VPS setup script should receive individual IPv4 addresses: its per-IP database user syntax is not a verified CIDR implementation. Review provider firewall rules before use.

## Operations

Planning load is 20,000 visits/month and five editors. Static delivery removes WordPress from most page views. Monitor published API availability, cold-start latency, contact storage errors, failed email, database health and Vercel hook/build failures. Use a synthetic search and a private-storage contact test with fictional information, controlled frequency and cleanup. Redact message bodies, emails, credentials and deploy-hook URLs from operational logs. Investigate repeated rate limits and abuse; add alerting for failed rebuilds because an old export can otherwise remain visible.

Proposed production objectives are **RPO 24 hours** and **RTO 4 hours**. Take encrypted daily database backups with retention appropriate to policy, separate access from application credentials and test restoration quarterly into an isolated environment. Retain versioned container images and static exports. A recovery drill restores the DB, checks roles and private-content boundaries, deploys the matching application and rebuilds the frontend. These targets and procedures are proposals, not achieved service guarantees for the free-tier demo.

## Cost

Planning assumptions: low traffic above, five editors, one small application workload, modest database/storage, daily backups and no paid SMS or production email campaign. Figures are illustrative monthly USD **estimates, verify** with the selected provider; no current price quotation is implied.

| Item | Demo | Production estimate, verify |
|---|---|---|
| Static delivery | Vercel free tier, subject to eligibility and limits | $0–25 |
| CMS application | Render free tier, sleeps when idle | $15–40 |
| MySQL | Existing VPS; incremental hosting not calculated | $25–70 |
| Backups, logs, transactional email | Not production provisioned | $10–30 |
| Total incremental platform budget | Free tiers + existing VPS costs | $50–165, excluding tax and engineering |

## Risks and next steps

Free-tier sleep and rebuild failures affect freshness and search availability. Demo email has no delivery guarantee. Static exports retain withdrawn content until deployment/cache removal; the deploy hook has a sixty-second debounce and does not implement a durable job queue. Add monitoring, retryable queued rebuilds and a content-withdrawal policy before production. The application base image and bundled WP-CLI supplied by the brief remain rolling references and need release pinning/upgrade automation.

Other explicit next steps are Admin MFA, certificate-verified DB TLS, stricter atomic edge rate limiting/CAPTCHA if abuse warrants it, a privacy retention schedule, verified SMTP, persistent media only if uploads become necessary, and backup/restore drills. English is the only language; PHP strings and a generated POT support future translation, while frontend localization would require a deliberate dictionary/routes change. Optional service maps are included and use fictional illustrative coordinates; OpenStreetMap introduces a third-party network request on those detail pages.
