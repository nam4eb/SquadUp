# Mobile architecture

Flutter uses feature-first modules with Riverpod, GoRouter and Dio:

```text
lib/
  app/
  core/network, auth, storage, realtime, errors
  features/<feature>/data, domain, application, presentation
  shared/
```

Repositories translate API DTOs into domain entities. Providers expose
application state. Widgets contain rendering and short-lived visual state only.
Sanctum tokens are stored in platform secure storage, never SharedPreferences.
Every async screen supports loading, empty, error and retry states.

The existing three prototype screens can be reused visually but their hardcoded
maps and delayed callbacks will be replaced one vertical slice at a time.

