# API taxonomy

Base path: `/api/v1`. JSON responses use stable error codes and HTTP semantics.

## Phase 1 identity

```text
POST   /auth/register
POST   /auth/login
POST   /auth/logout                 authenticated
POST   /auth/logout-all             authenticated
GET    /auth/me                     authenticated
POST   /auth/forgot-password
POST   /auth/reset-password
POST   /auth/email/verification-notification
GET    /auth/email/verify/{id}/{hash}
PATCH  /users/me                    authenticated
PATCH  /users/me/password           authenticated
GET    /activity-categories         authenticated
GET    /activity-categories/{category}/topics authenticated
GET    /activities                    authenticated
POST   /activities                    verified user
GET    /activities/{activity}         authenticated, visibility policy
GET    /activities/{activity}/participants authenticated, visibility-scoped
POST   /activities/{activity}/join    authenticated
POST   /activities/{activity}/leave   authenticated
PATCH  /activities/{activity}/participants/{participant}/accept host only
PATCH  /activities/{activity}/participants/{participant}/reject host only
PATCH  /activities/{activity}/participants/{participant}/attendance manager; `attended|absent`
POST   /activities/{activity}/invitations host only
POST   /activities/{activity}/invitation-secrets host only
DELETE /activities/{activity}/invitation-secrets/{token} host only
POST   /activity-invitations/{invitation}/accept invitee only
POST   /activity-invitations/{invitation}/decline invitee only
POST   /activity-invitations/redeem authenticated
PATCH  /activities/{activity}/status host only
PATCH  /activities/{activity}        host only
POST   /activities/{activity}/transfer-ownership host only
DELETE /activities/{activity}/participants/{participant} host only
GET    /activities/{activity}/invitations host only
DELETE /activities/{activity}/invitations/{invitation} host only
GET    /clans
POST   /clans                       verified user
GET    /clans/{clan}                visibility policy
GET    /clans/{clan}/members        visibility/permission scoped
POST   /clans/{clan}/join
POST   /clans/{clan}/leave
POST   /clans/{clan}/invitations    `invite_members`
PATCH  /clans/{clan}/members/{member}/review `manage_members`
DELETE /clans/{clan}/members/{member} remove/ban permission
GET    /clans/{clan}/roles          clan member
POST   /clans/{clan}/roles          `manage_roles`
PATCH  /clans/{clan}/roles/{role}   `manage_roles`
DELETE /clans/{clan}/roles/{role}   `manage_roles`
PATCH  /clans/{clan}/members/{member}/role `manage_roles`
POST   /clans/{clan}/transfer-ownership owner only
GET    /clans/{clan}/activities        active clan member
GET    /clans/{clan}/statistics        `view_statistics`
GET    /clans/{clan}/leaderboard       active clan member; period/topic/metric filters
GET    /clans/{clan}/messages          active member; cursor paginated
POST   /clans/{clan}/messages          active member
DELETE /clans/{clan}/messages/{message} sender or `manage_chat`
```

Creating an activity accepts optional `clan_id`. The caller must have
`create_events`; clan visibility is restricted to active members. Clan event
updates accept the delegated `manage_events` permission.

Leaderboard query parameters:

```text
period=all_time|yearly|monthly|weekly
metric=events_joined|events_attended|events_created|attendance_rate
topic_id={dynamic activity topic UUID}
```

## Planned resources

```text
/users, /friend-requests, /friends, /blocks
/activity-categories, /activity-topics, /activities
/activities/{activity}/participants
/activities/{activity}/invitations
/clans, /clans/{clan}/members, /clans/{clan}/roles
/conversations, /conversations/{conversation}/messages
/notifications, /notification-preferences
/reports, /leaderboards
```

Implemented social graph endpoints:

```text
GET    /users?query=&page=&per_page=
GET    /users/{user}
GET    /friend-requests?direction=incoming|outgoing
POST   /friend-requests
PATCH  /friend-requests/{request}/accept
PATCH  /friend-requests/{request}/reject
PATCH  /friend-requests/{request}/cancel
GET    /friends
DELETE /friends/{user}
GET    /blocks
POST   /blocks/{user}
DELETE /blocks/{user}
GET    /notifications?unread=1
PATCH  /notifications/{notification}/read
POST   /notifications/read-all
```

Activity collections can be filtered by `category_id` or `topic_id` and use a
bounded `per_page`. Visibility and blocks are enforced server-side. Collections
use `page`; chat uses cursor pagination.
Write requests accept an idempotency key where retries could duplicate effects.

## Phase 5 messaging

```text
GET    /conversations
POST   /conversations/direct
POST   /conversations/group
GET    /conversations/{conversation}
PATCH  /conversations/{conversation}/group                       owner/admin
POST   /conversations/{conversation}/members                     owner/admin
PATCH  /conversations/{conversation}/members/{member}/role       owner
DELETE /conversations/{conversation}/members/{member}            owner/admin
POST   /conversations/{conversation}/transfer-ownership/{member} owner
POST   /conversations/{conversation}/leave
GET    /conversations/{conversation}/messages              cursor paginated
POST   /conversations/{conversation}/messages
PATCH  /conversations/{conversation}/messages/{message}     sender
DELETE /conversations/{conversation}/messages/{message}     sender/admin
POST   /conversations/{conversation}/messages/{message}/reactions
DELETE /conversations/{conversation}/messages/{message}/reactions
POST   /conversations/{conversation}/messages/{message}/read
POST   /conversations/{conversation}/typing
POST   /presence/heartbeat                                  online|away
DELETE /presence                                            offline
GET    /presence/{user}
GET    /notification-preferences
PUT    /notification-preferences
```

Direct conversations are unique per canonical user pair and enforce bilateral
blocks. Every message/reaction/read route verifies both conversation membership
and that the message belongs to the route conversation.

Message creation accepts multipart `file`, optional `body`, `reply_to_id` and
`mention_ids[]`. Allowed media are JPEG/PNG/WebP/GIF, MP4/WebM, common audio,
PDF, text and ZIP up to the configured size. Text messages may also mention an
active conversation member with `@username`.

Realtime authentication is exposed at `POST /api/broadcasting/auth` (outside
the versioned REST prefix). Authorized clients subscribe to
`private-conversation.{conversationId}` and receive `message.created`,
`message.updated`, `message.deleted`, `message.reaction`, `conversation.read`
and `conversation.typing`. Presence state expires automatically when heartbeat
delivery stops.

## Response contract

Successful resources are wrapped in `data`; paginated resources additionally
return `meta` and `links`. Errors use:

```json
{
  "success": false,
  "message": "Unable to complete the request.",
  "code": "ACTIVITY_FULL",
  "errors": {"activity": ["This activity is already full."]}
}
```
