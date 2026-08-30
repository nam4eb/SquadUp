# Realtime architecture

Laravel Reverb publishes private and presence channel events. Redis stores
ephemeral presence and typing state; PostgreSQL stores durable messages,
membership, notification and read state.

Channels are authorized through Laravel Policies:

```text
private-user.{userUuid}
private-conversation.{conversationUuid}
private-activity.{activityUuid}
private-clan.{clanUuid}
presence-conversation.{conversationUuid}
```

Events are emitted after database commit. Clients recover from disconnects by
requesting durable state from REST using cursors; WebSocket delivery is not the
source of truth. Presence has a Redis TTL and does not continually write to
PostgreSQL.

