# CRM architecture

Filament is an administration adapter, not the business layer. Resources call
the same Actions, Policies and Services as API workflows. Admin roles are
separate from clan roles.

The `/admin` panel is restricted to verified, active accounts explicitly marked
as administrators. It currently provides:

- dashboard totals and a popular-category chart;
- user status and administrator-access management;
- activity category, topic and report-reason configuration;
- activity and clan review/moderation without bypassing creation workflows;
- report triage with reviewer attribution and moderation notes; and
- filterable, read-only audit-log review.

Administrator mutations use `AdminAuditService` to capture the actor, target,
IP address and before/after metadata. Audit records reject update and delete
operations at the model layer. Taxonomy deletion is recoverable; force-delete
actions are deliberately unavailable. Business mutations remain in domain
services/actions rather than Filament resources.
