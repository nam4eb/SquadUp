# SquadUP operations runbook

## Release and rollback

1. Back up PostgreSQL and verify the backup artifact before deployment.
2. Put workers into restart mode, deploy immutable application artifacts, run
   `php artisan migrate --force` once, then restart API, queue and Reverb.
3. Verify `/up`, authentication, one database read/write, one queued job and one
   authenticated WebSocket subscription.
4. Application rollback uses the previous image. Database rollback is only
   allowed when the migration explicitly supports safe down migration and no
   new-format data has been committed; otherwise deploy a forward repair.

## PostgreSQL backup and restore drill

Create an encrypted, access-controlled backup using `pg_dump --format=custom`.
At least monthly, restore it into an isolated database with `pg_restore`, run
schema checks and execute the backend smoke suite against the restored copy.
Record duration, artifact checksum, row-count checks and the operator. A backup
that has not passed restoration is not considered valid.

## Incident response

1. Classify impact: authentication, data integrity, messaging, privacy or
   availability. Preserve timestamps, request IDs and audit logs.
2. Contain: disable affected credentials/features, revoke tokens or isolate a
   worker without deleting evidence.
3. Recover from a known-good release/data point, verify invariants and monitor.
4. Document timeline, root cause, affected users/data and corrective actions.
   Notify users/regulators when the applicable policy or law requires it.

## Secret rotation

- Store secrets only in the deployment secret manager. Never place private
  keys in Flutter assets or committed env files.
- Rotate database, Redis, SMTP, OAuth, FCM, storage and Reverb credentials one
  integration at a time using an overlap window where supported.
- Redeploy, verify the new credential, revoke the old credential and inspect
  authentication/error metrics. Emergency rotation also revokes active tokens
  when compromise could allow account access.

## Capacity and alerts

Measure API p95/p99 latency, error rate, PostgreSQL connections/slow queries,
Redis memory/evictions, queue age/failures, Reverb connections and delivery
errors, media storage growth and FCM failures. Initial alerts must be calibrated
from load tests rather than arbitrary production thresholds.

## Data lifecycle

Use private storage for conversation media, authorized or temporary URLs,
malware scanning and explicit retention. Purge expired idempotency records,
stale push devices, orphan media and operational logs through scheduled jobs.
Account deactivation revokes sessions immediately; irreversible anonymization
requires a separately reviewed retention/legal policy.

`operations:prune` previews eligible records without deleting them. The
scheduler runs `operations:prune --force` daily at 03:30 on one scheduler node.
It removes expired idempotency keys, push devices older than
`STALE_PUSH_DEVICE_DAYS`, and chat-media rows whose message no longer exists
after the `ORPHAN_MEDIA_GRACE_HOURS` safety window. Before changing either
retention value, review product, security and legal requirements. Operators can
preview the current impact with:

```bash
php artisan operations:prune
```

## Authorization regression gate

The security test suite inspects the registered API route table, not only a
hand-maintained endpoint list. A release must fail if a new private route lacks
Sanctum or idempotency middleware, or if the media download route loses signed
URL validation. Run the gate independently with:

```bash
php artisan test tests/Feature/Security/ApiRouteSecurityAuditTest.php
```
