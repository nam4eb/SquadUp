# Authentication strategy

- Passwords are hashed with Laravel's configured adaptive hasher.
- Mobile clients use scoped Sanctum tokens stored in OS secure storage.
- Tokens have names, last-used timestamps, expiration and explicit revocation.
- Login, registration, reset and verification endpoints are rate limited.
- Email verification is required before social writes and activity creation.
- Google and Facebook use OAuth/OIDC authorization code flow; social passwords
  are never collected or stored.
- `social_accounts` stores provider, provider subject, user UUID and provider
  metadata safe for account linking. `(provider, provider_subject)` is unique.
- A social identity may link to an existing account only when verified email
  ownership is established or the signed-in user explicitly confirms linking.
- Public user resources never expose password hashes, tokens, private email,
  date of birth or moderation metadata.

The Flutter app will expose auth state through Riverpod. UI widgets call an
auth application service and never call Dio directly.

