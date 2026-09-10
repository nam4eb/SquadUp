# SquadUP API

Laravel 12 API for SquadUP. The implemented Phase 1 identity slice provides
UUID users, Sanctum device tokens, registration, login, logout, password reset,
email verification and self-profile updates under `/api/v1`.

From the repository root:

```bash
docker compose up -d --build
docker compose exec api php artisan test
```

API: `http://localhost:8000/api/v1`; health: `http://localhost:8000/up`.
PostgreSQL is published on port 5433 and Redis on 6379 for development.

Never commit `backend/.env`. Copy `.env.example`, generate an application key,
and replace development passwords for any shared environment.

## Demo data

Build a fresh local demo database with:

```bash
php artisan migrate:fresh --seed
```

All demo accounts use password `DemoPass123!`: `demo@squadup.test`,
`alice@squadup.test`, `bryan@squadup.test` and `mila@squadup.test`.

The seed includes dynamic activity taxonomy and activity scenarios for open,
full, approval-required, completed and cancelled states. Participation data
includes hosts, joined users, a pending request and attendance history. Chat
data includes direct, group and event conversations with replies, reactions,
read state, a mention and a structured location message.

`DatabaseSeeder` is idempotent and may be run again without duplicating these
activities, conversations or messages:

```bash
php artisan db:seed
```
