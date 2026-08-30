# Implementation status

Updated: 2026-08-29.

## Phase 0 — complete

Architecture, domain model, ERD, API taxonomy, authentication, realtime,
permission matrix, mobile, CRM, activity, clan, deployment and roadmap docs.

## Phase 1 — foundation and email identity slice complete

- Laravel 12 modular backend and `/api/v1` routing.
- Docker PHP 8.3, PostgreSQL 16 and Redis 7 runtime.
- UUID user, social account, token and audit schema migrated on PostgreSQL.
- Sanctum register/login/me/logout/logout-all.
- Forgot/reset/change password and email verification.
- Profile update, account status enforcement and auth rate limits.
- Flutter Riverpod/Dio/secure-storage registration and login integration.
- Backend identity: 11 meaningful feature tests.
- Flutter: analyzer clean and one UI smoke test passing.

Google/Facebook provider exchange is intentionally not marked complete until
real provider credentials and redirect URIs are configured and tested.

## Phase 2A — social graph core implemented

- Authenticated user search and public profile serialization.
- Friend request send, incoming/outgoing list, accept, reject and cancel.
- Symmetric friendships, friend list and remove friend.
- UUID records, normalized pair keys, unique constraints and transaction locks.
- Domain error codes for self-request, duplicate pending request, authorization
  and missing friendships.
- 7 social graph feature tests.

## Phase 2B — safety, notifications and Friends UI implemented

- Block/unblock APIs with friendship removal and pending-request transition.
- Blocked users are excluded from search/profile and cannot create new requests.
- Database notifications for received and accepted friend requests.
- Notification list, unread filter and mark-one/mark-all-as-read APIs.
- Flutter Friends tab connected to the live API for search, incoming requests,
  accept/reject, add/remove friend and pull-to-refresh.
- Flutter block management and Notifications screen with all/unread filters,
  mark-one and mark-all-as-read actions.
- PostgreSQL migrations verified against the Docker runtime.
- Full backend suite: 24 tests, 94 assertions.
- Flutter analyzer clean and UI smoke test passing.

## Next vertical slice

## Phase 3A — dynamic activity taxonomy implemented

- UUID category/topic schema with status, visibility, media, color and sorting.
- Self-referencing topics support future subtopic hierarchy.
- Authenticated category and per-category topic APIs return only active,
  visible records in administrator-defined order.
- Idempotent development seed provides 10 categories and 30 topics.
- Flutter Home loads and renders categories from the API instead of hard-coding
  the taxonomy.
- PostgreSQL migration verified; full backend suite: 27 tests, 103 assertions.
- PHP formatting, Flutter analyzer and Flutter smoke test all pass.

## Next vertical slice

## Phase 3B — activity create/view foundation implemented

- Normalized activities and participant-history tables with UUIDs and indexes.
- Activity status and visibility enums cover the designed lifecycle vocabulary.
- Verified users can create activities; the host becomes the first joined
  participant in the same database transaction.
- Category/topic consistency, future scheduling, timezone, location, capacity,
  optional password hashing, age, rules and lobby flags are validated.
- Policy-protected detail and feed enforce public/friends/private visibility,
  hidden lifecycle states and bilateral blocks.
- Flutter Home feed, Activity Detail and Create flow use the live API and
  dynamic taxonomy; obsolete mock-detail widgets were removed.
- PostgreSQL migration verified; full backend suite: 32 tests, 124 assertions.
- PHP formatting, Flutter analyzer and Flutter smoke test all pass.

## Next vertical slice

## Phase 3C — transactional participation implemented

- Join/leave lock the activity row and preserve one participant-history record
  per user instead of deleting participation.
- Eligibility enforces lifecycle, bilateral blocks, password hash, minimum age,
  duplicate participation and host constraints.
- Capacity changes switch open/full status atomically; full activities reject or
  create ordered waitlist records according to lobby configuration.
- Leaving a joined slot promotes the first waitlisted user; approval-required
  lobbies promote them to requested rather than bypassing host review.
- Hosts can accept/reject requests; non-host review is forbidden.
- Participant listing exposes joined users to viewers and pending/waitlisted
  records only to the host.
- Flutter Activity Detail supports join/request/leave, password prompt,
  participant list and host accept/reject controls.
- Full backend suite: 39 tests, 159 assertions. PHP formatting, Flutter analyzer
  and Flutter smoke test all pass.

Docker runtime verification is temporarily blocked because the Windows
`com.docker.service` is stopped and this session cannot start Administrator
services. No database reset or destructive operation was performed.

## Next vertical slice

## Phase 3D — invitations and lifecycle management implemented

- Direct invitations are bound to a specific invitee, create an in-app
  notification and grant policy-controlled invite-only visibility.
- Link/code secrets are generated cryptographically, returned once and stored
  only as deterministic SHA-256 hashes for lookup.
- Secrets support expiry, revocation, optional usage limits and atomic usage
  accounting. Invitation codes are case-insensitive.
- Redeeming a secret joins through the same locked eligibility/capacity action;
  password-protected activities still require the password.
- Centralized lifecycle transitions support lock/unlock, start,
  complete and cancel while rejecting invalid terminal transitions.
- Flutter supports invitation-code redemption, one-time host code display/copy,
  and host lifecycle controls with cancellation confirmation.
- Full backend suite: 46 tests, 199 assertions. PHP formatting, Flutter analyzer
  and Flutter smoke test all pass.

The invitation migration passes the SQLite migration suite but is not yet
applied to PostgreSQL because Docker Desktop's Windows service requires manual
Administrator startup.

## Next vertical slice

## Phase 3E — host activity management implemented

- Hosts can edit taxonomy selection, title/content, schedule, location, lobby
  flags, password, age/rules and capacity while the activity is editable.
- Capacity cannot fall below joined participants and expanding a full lobby
  atomically reopens it when appropriate.
- Remove/ban preserves participant history, promotes waitlist users and banned
  users cannot rejoin.
- Ownership transfer requires a joined participant and atomically changes the
  activity owner plus old/new participant roles and authorization.
- Host invitation listing exposes direct/token metadata without secret hashes;
  pending direct invitations and generated secrets can be revoked.
- Flutter host controls support lobby edit, friend invitation, remove/ban and
  ownership transfer with confirmation prompts.
- Full backend suite: 51 tests, 228 assertions. PHP formatting, Flutter analyzer
  and Flutter smoke test all pass.

The Phase 3D/3E migration remains pending on PostgreSQL until Docker Desktop is
started manually with Administrator permission. All migration paths pass the
SQLite test suite.

## Next vertical slice

## Phase 4A — clan lifecycle and membership implemented

- UUID clans support public/private visibility, open/approval/invite-only join
  policies and durable active status.
- Creation transaction seeds Owner, Leader, Vice Leader, Moderator and Member
  roles plus normalized stable permission records; membership references role
  UUIDs rather than a hard-coded admin/member column.
- Membership history covers invited, requested, active, left, removed and banned.
- Join/request, invite notification, leave, approve/reject and remove/ban flows
  enforce visibility and delegated RBAC permissions.
- Private clans are hidden from outsiders; invited/requested members retain the
  minimum visibility needed for their workflow. Pending memberships are only
  visible to authorized managers.
- Owners cannot leave or be removed, preserving the one-owner invariant until
  ownership-transfer support is added.
- Flutter Clans tab uses the live API for discovery, create, join/request,
  accept invite and leave.
- Full backend suite: 58 tests, 265 assertions. PHP formatting, Flutter analyzer
  and Flutter smoke test all pass.

Migrations `050000` (activity invitations) and `060000` (clan core) pass the
SQLite suite but remain pending on PostgreSQL until Docker Desktop is started
manually with Administrator permission.

## Phase 4B — clan roles and ownership management implemented

- Owners and members with delegated `manage_roles` permission can create and
  update custom roles using a server-side whitelist of stable permissions.
- Owner and built-in system roles are protected from mutation/deletion; custom
  roles currently assigned to members cannot be deleted.
- Authorized managers can assign roles to active members. Assignments generate
  an in-app notification and cannot grant the protected Owner role.
- Ownership transfer requires an active target member and atomically swaps the
  owner reference plus old/new member roles, preserving exactly one Owner.
- Flutter clan detail shows clan metadata, roles and members. Owner workflows
  include approve/reject, assign role, remove/ban, custom-role creation and
  ownership transfer with confirmations and API-state refresh.
- Full backend suite: 63 tests, 292 assertions. Flutter analyzer is clean and
  the UI smoke test passes.

Migrations `050000` and `060000` remain pending on PostgreSQL until Docker
Desktop is started manually with Administrator permission. Their complete
migration and domain behavior are covered by the passing SQLite suite.

## Phase 4C — clan events and leaderboard baseline implemented

- A normalized `clan_events` link preserves the Activity aggregate while
  associating an activity with exactly one clan and its creating member.
- Activity creation accepts clan context and enforces active membership plus
  delegated `create_events`; `manage_events` is honored consistently by the
  request, policy and locked update action.
- Clan-visibility policy and the global feed expose clan events only to active
  members. Outsiders receive not-found/forbidden responses as appropriate.
- Clan event listing is paginated and reuses the existing Activity resource.
- Statistics report total/completed events, active members and dynamic
  per-topic counts behind `view_statistics`.
- Leaderboards support all-time/yearly/monthly/weekly periods, dynamic topic
  filtering, and `events_joined` or `events_created`; read models are cached for
  five minutes to avoid repeated aggregation.
- Flutter clan detail shows activity metrics, recent clan events and leaderboard
  entries, and creates an activity with clan context and clan visibility.
- Full backend suite: 66 tests, 319 assertions. PHP formatting and Flutter
  analyzer are clean; the Flutter smoke test passes.

Migrations `050000`, `060000` and `070000` pass the SQLite suite but remain
pending on PostgreSQL until Docker Desktop is started manually with
Administrator permission.

## Phase 4D — attendance and durable leaderboard snapshots implemented

- Activity managers can mark or correct `attended`/`absent` only for joined
  participants and only while an activity is ongoing or completed.
- Attendance writes preserve marker identity and timestamp, reject cross-event
  participants, and honor delegated clan `manage_events` consistently through
  policy, controller and locked domain actions.
- Clan statistics include attended, absent and attendance-rate totals.
- Leaderboards add `events_attended` and `attendance_rate` metrics while
  preserving period and dynamic-topic filters.
- Durable `clan_leaderboard_snapshots` store generated read models. Versioned
  cache invalidation reacts to clan event, participant and relevant activity
  changes instead of relying only on the five-minute TTL.
- Flutter event management exposes Attended/Absent actions and delegated clan
  event controls; clan overview displays attendance rate.

## Phase 4E — dedicated clan chat baseline implemented

- Every new clan receives one dedicated conversation in the creation
  transaction; the schema is reusable by the broader chat phase.
- Active members can send text messages and retrieve history with cursor
  pagination. Pending members and outsiders are denied.
- Messages support reply references and soft deletion. Senders can delete their
  own messages; delegated `manage_chat` roles can moderate other messages.
- Flutter clan chat supports latest-message history, pull-to-refresh, sending,
  own-message deletion and deleted-message placeholders.
- Full backend suite: 71 tests, 370 assertions. PHP formatting and Flutter
  analyzer are clean; the Flutter smoke test passes.

Migrations `050000` through `100000` pass the SQLite suite but remain pending
on PostgreSQL until Docker Desktop is started manually with Administrator
permission.

## Next vertical slice

## Phase 5A — direct/group messaging foundation implemented

- Direct conversations use a canonical pair key, are idempotent, reject
  self-messaging and enforce bilateral blocks.
- Group creation persists owner/member roles and validates member identities,
  uniqueness, capacity and blocked relationships.
- Conversation listing combines direct, group and clan conversations while
  loading latest-message previews without N+1 queries.
- Message history uses cursor pagination. Members can send text/location/event/
  clan message records, reply, edit their own messages and soft-delete; group
  owners/admins and clan chat managers can moderate.
- Normalized reactions and read receipts are idempotent and protected against
  cross-conversation message IDs.
- Flutter Chat tab replaces the placeholder and supports direct/group creation,
  conversation history, send, reply, edit, delete, reactions and read state.
- Full backend suite: 75 tests, 406 assertions. PHP formatting and Flutter
  analyzer are clean; the Flutter smoke test passes.

Migrations `050000` through `110000` pass the SQLite suite but remain pending
on PostgreSQL until Docker Desktop is started manually with Administrator
permission.

## Phase 5B — realtime messaging and presence implemented

- Laravel Reverb provides Pusher-compatible WebSocket delivery in a dedicated
  Docker service; broadcast configuration supports explicit allowed origins.
- Conversation events cover message create/update/delete, reactions, read
  receipts and typing state through authenticated private channels.
- Channel authorization uses Sanctum and the same conversation membership
  policy as the REST API, preventing outsiders from subscribing.
- Online/away/offline presence uses a TTL-backed configurable cache store
  (Redis outside tests), avoiding frequent relational-database writes.
- Flutter connects lazily after authentication, supplies the bearer token to
  `/api/broadcasting/auth`, refreshes visible chat state from realtime events,
  shows typing indicators and emits lifecycle-aware presence heartbeats.
- REST remains the fallback when Reverb is unavailable, so message operations
  do not depend on a persistent socket.
- Full backend suite: 78 tests, 430 assertions. PHP formatting and Flutter
  analyzer are clean; the Flutter smoke test passes. Docker Compose validates.

Migrations `050000` through `110000` and a live Redis/Reverb/PostgreSQL E2E run
remain pending until Docker Desktop is started manually with Administrator
permission. All migration and domain paths pass the SQLite test suite.

## Phase 5C — chat media, mentions and notification preferences implemented

- A polymorphic `media` abstraction stores disk/path metadata independently of
  local filesystem assumptions and is ready for S3-compatible disks.
- Direct/group messages accept image, video, audio and document uploads using
  server-side MIME allowlisting and a configurable 20 MB limit; message type is
  derived from trusted server MIME inspection rather than the filename.
- Media resources expose URL, original name, MIME, byte size and optional
  dimensions/duration fields. Flutter uploads multipart files and renders image
  previews or document metadata.
- Explicit mention IDs and `@username` parsing are restricted to active
  conversation members. Mentioned users receive a dedicated notification.
- Chat-message and mention notifications are queued and honor per-user in-app
  preferences. Push toggles and notification categories are persisted now so
  an FCM delivery channel can be added without changing the preference API.
- Flutter exposes all notification categories with independent in-app and push
  toggles from the Notifications screen.
- Existing friend, event and clan notifications also honor the same in-app
  preference policy, with default-on behavior for users without overrides.
- Full backend suite: 83 tests, 459 assertions. PHP formatting and Flutter
  analyzer are clean; the Flutter smoke test passes.

Migrations `050000` through `120000` pass the SQLite suite but remain pending
on PostgreSQL until Docker Desktop is started manually with Administrator
permission.

## Next vertical slice

## Phase 5D — clan-chat parity and group management implemented

- Clan chat now supports the same media validation, member-only mentions,
  notifications and Reverb message events as direct/group conversations.
- Group owners/admins can edit name, description and avatar, add/remove members
  and enforce the 100-member limit. Owners can promote/demote admins and
  transfer ownership while preserving exactly one owner.
- Members can leave groups; owners must transfer ownership first. Admins cannot
  remove the owner or other admins without owner authority.
- Flutter exposes group settings, profile editing, avatar selection, member
  administration, ownership transfer and leave workflows. Clan chat can upload
  and render media and mention members with `@username`.
- Docker Compose includes a Redis queue worker for queued chat/mention
  notifications in addition to API, Reverb, PostgreSQL and Redis services.
- Full backend suite: 88 tests, 486 assertions. PHP formatting and Flutter
  analyzer are clean; the Flutter smoke test passes. Compose validates.

Migrations `050000` through `130000` pass the SQLite suite but remain pending
on PostgreSQL until Docker Desktop is started manually with Administrator
permission.

The remaining gaps across Phase 0–5 are tracked in `docs/phase-0-5-audit.md`.

## Phase 0–5 completion pass

- Identity completion: OAuth verification/linking, sessions, account
  deactivation, profile media and reusable idempotency.
- FCM push registry/delivery and ranked friend suggestions.
- Geospatial activity feed, recurrence, event chat, activity social/reporting
  and deduplicated lifecycle reminders.
- Clan profile media and moderated announcements.
- Validated structured chat payloads for locations, activities and clans.
- Final automated baseline: 113 backend tests / 623 assertions, Pint clean,
  Flutter analyzer clean and Flutter smoke test passing.

Production-like infrastructure and credential gates are recorded in
`docs/phase-0-5-audit.md`; they are intentionally not represented as complete.

## Phase 6 mobile completion — active

- Added a real Profile screen backed by authenticated activity history with
  Upcoming, Created, Joined, Completed and Cancelled scopes.
- Added Explore search with dynamic categories and coordinate/radius filters.
- Activity detail now exposes like, comments, hide, report and event chat.
- Clan detail now exposes member announcements and delegated moderation.
- Flutter analyzer and smoke test remain clean after these additions.

## Phase 7 CRM — implemented

- Filament 5.7.6 panel is installed at `/admin`.
- Only verified, active users with `is_admin` can access the panel.
- Dashboard widgets expose platform totals and the most popular activity
  categories.
- User administration controls account status and administrator access.
- Dynamic activity categories, topics and report reasons are manageable;
  taxonomy records use recoverable soft deletion without force-delete actions.
- Existing activities and clans can be reviewed and moderated, while direct
  creation remains in the domain/API workflows.
- Reports support review status, reviewer attribution and moderation notes.
- Every administrator mutation records actor, target, IP address and change
  metadata through the shared audit service.
- Audit records are append-only at the model layer and exposed through a
  read-only, filterable resource with no create, edit or delete routes.
- A clean migration run including report-reason seeding succeeds, and the full
  backend regression suite passes with 120 tests and 669 assertions.

## Phase 8 hardening — active

- Authenticated API traffic is rate-limited per user, with a stricter report
  submission quota; authentication endpoints retain IP and identity limits.
- API responses carry validated correlation IDs and baseline browser security
  headers. Structured completion logs include status and request duration.
- `/up` remains the liveness probe; `/api/v1/ready` verifies database and cache
  access and fails closed with HTTP 503.
- Chat media now uses expiring signed URLs and authorizes conversation
  membership again at download time instead of exposing public storage URLs.
- A daily, single-node lifecycle job prunes expired idempotency keys, stale push
  devices and orphaned chat media. Its command defaults to dry-run and uses a
  configurable grace window before deleting storage objects.
- An executable route-security contract audits the complete API v1 registry:
  only the documented authentication/readiness endpoints are public; every
  other route requires Sanctum and idempotency middleware, and media downloads
  additionally require a valid URL signature.
- PostgreSQL contention/load evidence, malware scanning, production service
  integration and real-device E2E remain launch-environment gates.
