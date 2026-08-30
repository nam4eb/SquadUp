# Permission matrix

| Capability | User | Activity host | Clan member | Clan role | Moderator | Admin |
|---|---:|---:|---:|---:|---:|---:|
| View public profile/activity | yes | yes | yes | yes | yes | yes |
| Update own profile | own | own | own | own | own | own |
| Create activity | verified | yes | by role | `create_events` | yes | yes |
| Manage activity | no | own | no | `manage_events` | intervention | yes |
| Join activity | eligible | eligible | eligible | eligible | eligible | eligible |
| Invite activity users | no | yes | no | `invite_members` | no | yes |
| Manage clan settings | no | no | no | `manage_clan` | intervention | yes |
| Manage clan members | no | no | no | `manage_members` | intervention | yes |
| Manage clan roles | no | no | no | `manage_roles` | no | yes |
| Moderate reported content | no | no | no | scoped content | yes | yes |
| Manage platform configuration | no | no | no | no | no | yes |

Authorization is enforced server-side with Policies/Gates. Client visibility is
only a UX optimization and is never an authorization boundary.

