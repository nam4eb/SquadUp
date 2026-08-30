# Implementation roadmap

## Phase 0 — architecture

Architecture, domain boundaries, ERD, API taxonomy, authentication and
permissions are frozen as the initial implementation baseline.

## Phase 1 — backend foundation

Laravel 12, PostgreSQL, Redis, Docker development services, API v1 conventions,
users, Sanctum authentication, validation, policies, logging and tests.

## Phase 2 — social graph

Profile, user search, friend requests, friendships, blocks and notifications.

## Phase 3 — activity system

Dynamic taxonomy followed by create, view, join/leave, capacity locking,
waitlist, invitations, passwords and lifecycle vertical slices.

## Phase 4 — clan system

Clan lifecycle, membership, customizable RBAC, clan activities, statistics and
cached leaderboards.

## Phase 5 — chat and realtime

Conversation membership, messages, reactions, reads, Reverb events, presence,
typing and asynchronous notifications.

## Phase 6 — mobile completion

Riverpod feature architecture and complete Home, Explore, Create, Friends,
Chat, Clans, Notifications, Profile and Settings flows.

## Phase 7 — CRM

Filament dashboard and resources for users, taxonomy, activities, clans,
reports, moderation, analytics and immutable audit review.

## Phase 8 — hardening

Authorization audit, concurrency and load tests, query review, rate limits,
media validation, observability, deployment and recovery procedures.

