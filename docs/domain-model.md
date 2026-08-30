# Domain model

- User is an identity and account aggregate; public profile data is separate
  from authentication and moderation attributes.
- Friendship is a symmetric accepted relationship. FriendRequest records the
  directed workflow. Block overrides social interactions in either direction.
- Activity is the central aggregate. It owns lifecycle, host, visibility,
  capacity policy and participation. Participant records preserve history.
- Invitation grants an attempt to join; it never bypasses visibility, block,
  capacity, ban, password or approval rules.
- Clan is a persistent group with memberships and clan-defined RBAC roles.
- Conversation is a membership boundary for messages and can belong to a
  direct pair, group, activity or clan.
- Notification records user-visible intent; delivery attempts are asynchronous.
- Report starts a moderation workflow. AuditLog records privileged decisions.

Core loop: User -> Friends -> Activities -> Clans -> Chat -> Participation ->
Statistics -> Leaderboards.

