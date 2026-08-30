# Clan system design

Clans own memberships and customizable roles. Default roles are seeded but not
hardcoded into authorization. Permission keys are stable application constants;
each clan role maps to any supported subset.

Owner transfer is transactional and a clan must always have exactly one owner.
Membership history is retained across leave, removal and ban. Clan conversation
access derives from active membership. Clan events use the normal Activity
aggregate with a clan association rather than a separate event implementation.

Statistics are updated asynchronously from durable attendance events and stored
in periodic snapshots for weekly, monthly, yearly and all-time leaderboards.

