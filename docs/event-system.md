# Activity system design

Lifecycle: `draft -> open -> full|locked -> ongoing -> completed`, with
`cancelled` and `expired` terminal alternatives. Transitions are centralized
in an ActivityLifecycle service and invalid transitions raise domain errors.

Join runs inside one PostgreSQL transaction. It locks the activity row, checks
visibility, blocks, bans, password, duplicate participation and current
capacity, then creates or transitions the participant to joined/requested/
waitlisted. Notifications and realtime events dispatch only after commit.

Invitation tokens are random high-entropy values; only hashes are stored.
Codes are revocable and optionally expire. Participant history is transitioned,
not deleted.

Direct invitations use a user-bound workflow. Link/code secrets are returned
only when created and stored as SHA-256 hashes with optional expiry and usage
limits. Redeeming any invitation reuses the same locked join action, so an
invitation never bypasses block, password, age, lifecycle or capacity checks.
