# SquadUP product completion plan

## Audit snapshot

- Mobile: Flutter/Dart (`sdk ^3.9.0`), feature-oriented folders, Riverpod,
  GoRouter, Dio, Flutter Secure Storage, Geolocator, Firebase Messaging, and
  Laravel Reverb/Pusher client.
- Backend: Laravel 12 modular monolith with Sanctum, PostgreSQL, Redis queues,
  Reverb, Filament admin, Docker, and optional Octane/RoadRunner runtime.
- There is no Go backend or active Go migration. New functionality remains in
  Laravel according to the backend decision rule.
- Canonical backend term remains `Activity`. It already owns mature lifecycle,
  participation, invitation, chat, notification, and authorization behavior.
  Mobile copy may use Match/Kèo. Renaming persisted entities now would add risk
  without product value.
- Verification baseline before this pass: backend 132 tests / 985 assertions;
  Flutter has only one smoke test and still requires full analyze/test/build
  verification after completion work.

## Gap matrix

| Feature | Existing | Working | Partial / missing | Backend | Mobile | Database | Tests | Priority | Action |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Authentication | Yes | Yes | No refresh-token flow; Sanctum session renewal policy needs documentation | Complete core | Login/register/account settings | Users, tokens, OAuth | Strong backend | P0 | Preserve; verify redirects/session restore |
| Onboarding | No | No | Profile, sport/skill selection, area, permission steps | Missing | Missing | Missing completion state | Missing | P0 | Add resumable onboarding API and screens |
| Sports configuration | Taxonomy topics approximate sports | Partial | No first-class sport contract or constraints | Missing | Categories are used as filters | Missing `sports` | Missing | P0 | Add backend-driven sports and seed four sports |
| Sport-specific skill | No | No | Entire domain missing | Missing | Missing | Missing profiles/history | Missing | P0/P1 | Add profile CRUD and deterministic level mapping |
| Explore list | Yes | Yes | Limited filters/ranking | Activity endpoint supports radius/search/category | Working list | Lat/lng columns and indexes | Partial | P0 | Add sport/date/slot/skill filters and ranking service |
| Explore map | No | No | Pins, clustering, map/list toggle, search-area | Bounding/radius query exists | Missing map SDK/UI | Venue model absent | Missing | P0 | Add venues/geospatial contract, map adapter and clustering |
| Location permissions | Yes | Partial | No onboarding/default area persistence; settings CTA incomplete | Coordinates are ephemeral | Graceful deny fallback exists | No location history | No mobile tests | P0 | Complete permission states and default area |
| Create activity/match | Yes | Yes | Sport/venue/skill fields absent | Transactional create | Working form | Activities/taxonomy | Backend tests | P0 | Extend without breaking current clients |
| Join/leave/waitlist | Yes | Yes | Explicit concurrent HTTP test missing | Row locks and atomic promotion exist | Detail actions exist | Unique participation | Strong functional tests | P0 | Add concurrency test and stable error codes |
| Host lifecycle | Yes | Yes | Post-match prompts absent | Validated transitions/attendance | Management UI partial | Status enum | Backend tests | P0 | Complete mobile controls and completion handoff |
| Match chat | Yes | Yes | Client reconnect/offline delivery hardening incomplete | Event chat and membership authorization | Realtime conversation UI | Durable messages/read/reactions | Backend realtime tests | P0 | Add client message IDs, recovery and protocol doc |
| Push notifications | Yes | Partial | External Firebase credentials/environment not verified | FCM service/devices/preferences | Registration and preferences | Push devices | Backend tests | P0 | Verify dev fallback, deep links and delivery policy |
| Stories | Yes | Partial | Video/object-storage pipeline and match link missing | Feed/view/viewers/expiry/delete | Viewer/create integration exists | Stories/views/media | Backend tests | P1 | Add match linkage/privacy and post-match CTA |
| Ratings | No | No | Entire participant rating flow missing | Missing | Missing | Missing | Missing | P1 | Add authorization, uniqueness, aggregation and UI |
| Reputation | No | No | Reliability and public aggregates missing | Missing | Missing | Missing | Missing | P1 | Add auditable aggregates derived from attendance/ratings |
| Friends/people | Yes | Yes core | Sport/skill/area discovery filters missing | Requests/friends/suggestions/block | Friends UI | Social graph | Backend tests | P1 | Extend discovery after sport profiles |
| Invite/QR/share | Yes | Yes | Deep-link terminated-state verification needed | Secret/direct invitations | QR scanner and sharing UI | Invitation tokens | Backend tests | P1 | Verify routing and add mobile tests |
| Blocking/reporting | Yes | Partial | Cross-domain rating rules and story/report coverage need audit | Global checks on key domains, reports | Partial actions | Blocks/reports/reasons | Security tests | P1 | Add E2E blocking/IDOR matrix |
| Pagination | Yes | Partial | Explore page-based; story feed unpaginated | Cursor chat, page activities | Load-more in Explore | Relevant indexes | Partial | P1 | Cursor for high-volume feeds |
| Offline/cache | Provider cache only | Partial | Chat outbox and durable recent data missing | N/A | Connectivity and basic cache utilities | N/A | Missing | P2 | Add bounded cache and retry queue |
| Admin/moderation | Yes | Partial | Story/chat report-specific workflows and health detail missing | Filament resources/audit | N/A | Reports/audit | Backend tests | P2 | Extend only after product core |
| Analytics | No | No | Privacy-conscious funnel events missing | Missing | Missing | Missing | Missing | P2 | Add event schema without private payloads |
| Performance/runtime | Yes | Yes | Multi-hour canary still external | Octane optional; p95 105 ms at 30 VU soak | Cached images; loading varies | Indexed PostgreSQL | Load/soak reports | P1 | Retain FPM rollback and run canary monitoring |
| Accessibility/loading | Widgets exist | Partial | Forced text scale 1.0 violates scaling; coverage inconsistent | N/A | Skeleton/empty widgets exist | N/A | Missing | P2 | Restore scaling and audit semantics/tap targets |
| CI/CD/build | Docker exists | Partial | No visible CI workflow; Android build not reverified this pass | Production images verified earlier | Build pending | Clean migration pending | Backend baseline only | P1 | Add repeatable verification and final report |

## Delivery phases

1. P0-A: first-class sports, user sport profiles, resumable onboarding.
2. P0-B: venues and production Explore API with filters/ranking/privacy.
3. P0-C: Flutter Explore map/list, clustering, persisted filters and permission UX.
4. P0-D: match contract extensions, concurrency test, mobile lifecycle controls.
5. P0-E: realtime reconnect/deduplication/offline recovery and push deep links.
6. P1-A: post-match ratings, reputation and deterministic sport skill service.
7. P1-B: match-linked stories and cross-domain block/report verification.
8. P2: offline cache, analytics, moderation gaps, accessibility and UI states.
9. Final: golden-flow E2E, clean migrations, static analysis, tests, Android
   build, production Docker verification, and final documentation.

## Current execution

- P0-A complete: first-class sports, per-sport profiles, resumable onboarding,
  backend and Flutter verification.
- P0-B backend complete: venues, sport/venue/skill/match activity contract,
  `/explore` compatibility endpoint, radius/privacy/skill/format/open-slot
  filters, canonical venue snapshots, seed data, and regression coverage.
- P0-C complete: Flutter consumes first-class sports, supports Explore filters,
  OpenStreetMap list/map mode, marker clustering, search-this-area, permission
  fallback, and persisted filter state.
- P0-D substantially complete: create captures sport, verified venue, skill
  range, match format and fee; detail exposes those fields; host lifecycle has
  guarded confirmations and actionable API errors. Row-lock capacity behavior
  now has stale-client regression coverage. A true parallel HTTP test remains
  gated on an isolated PostgreSQL test database (the default suite uses
  in-memory SQLite).
