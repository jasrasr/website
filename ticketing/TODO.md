<!--
File: TODO.md
File Revision: 1.0.0
Modified: 2026-09-14
History:
1.0.0 - Updated future backlog after authentication and persistent directories were implemented.
-->

# Ticketing Future Features / TODO

## Authentication and account management
- [x] Requester login.
- [x] Agent login.
- [x] Requester self-registration.
- [x] First-agent bootstrap.
- [x] Persistent requester/agent directory.
- [ ] Password reset / forgot-password workflow.
- [ ] Agent password reset by administrator.
- [ ] Disable/enable requester and agent accounts.
- [ ] Change password while signed in.
- [ ] MFA / passkey support.
- [ ] Login throttling and account lockout protection.
- [ ] CSRF protection for write operations.
- [ ] Microsoft 365 / Entra ID single sign-on.
- [ ] Role levels such as Agent, Supervisor, Admin, and Read Only.

## Email integration
- [ ] Outbound email notifications when tickets are created, assigned, replied to, resolved, or reopened.
- [ ] Email requester when an agent sends a public reply.
- [ ] Email assigned agent/group when a ticket changes.
- [ ] Configurable sender address and SMTP/API provider.
- [ ] Inbound email-to-ticket creation.
- [ ] Convert email replies into public ticket replies.
- [ ] Preserve email thread/message IDs to reduce duplicate tickets.
- [ ] Email templates and per-event notification settings.
- [ ] Agent notification preferences.

## Ticket workflow
- [ ] Departments and agent groups.
- [ ] Configurable categories and subcategories.
- [ ] Ticket types: Incident, Service Request, Problem, Change, Question.
- [ ] Custom fields.
- [ ] Tags.
- [ ] Due dates.
- [ ] SLA policies by priority/type/category.
- [ ] Business hours and holiday schedules.
- [ ] Escalation rules.
- [ ] Auto-assignment / round-robin routing.
- [ ] Ticket watchers / CC users.
- [ ] Merge duplicate tickets.
- [ ] Split tickets.
- [ ] Parent/child tickets.
- [ ] Related tickets.
- [ ] Reopen closed/resolved tickets.
- [ ] Closure reason / resolution code.
- [ ] Ticket source options such as Portal, Email, Phone, Teams, Monitoring, API.
- [ ] Approval workflow.

## Replies and collaboration
- [ ] Canned responses.
- [ ] @mentions for agents.
- [ ] Rich-text replies.
- [ ] Reply templates/signatures.
- [ ] Internal activity timeline separate from conversation.
- [ ] Track every field change in an audit log.
- [ ] Show who changed assignment/status/priority and when.
- [ ] Draft replies.

## Attachments
- [ ] Requester attachments when creating or replying to a ticket.
- [ ] Agent attachments in replies/private notes.
- [ ] File type and size restrictions.
- [ ] Virus/malware scanning integration.
- [ ] Attachment cleanup/retention policy.

## Reporting and dashboards
- [ ] Tickets created/resolved by day, week, and month.
- [ ] Open backlog trend.
- [ ] Average first-response time.
- [ ] Average resolution time.
- [ ] SLA compliance percentage.
- [ ] Tickets by priority/category/source/requester/agent/group.
- [ ] Agent workload dashboard.
- [ ] Aging buckets for unresolved tickets.
- [ ] CSV/Excel export.
- [ ] Saved filters/views.

## Automation
- [ ] Rule engine: when conditions match, change fields/assign/send notifications.
- [ ] Scheduled rules for stale tickets.
- [ ] Auto-close resolved tickets after configurable number of days.
- [ ] Auto-reopen on requester reply.
- [ ] Webhooks for ticket events.
- [ ] REST API tokens.
- [ ] PowerShell helper/module for creating, updating, searching, and closing tickets.

## Assets / CMDB
- [ ] Assets linked to tickets.
- [ ] Computers, users, software, printers, network devices, and other CI types.
- [ ] Asset ownership / assigned user.
- [ ] Asset history and related tickets.
- [ ] Import asset data from scripts or CSV.

## Knowledge and self service
- [ ] Knowledge-base articles.
- [ ] Suggested articles while requester creates a ticket.
- [ ] Service catalog with structured request forms.
- [ ] Frequently requested services.
- [ ] Announcements / outage banner.
- [ ] Known issues page.

## Integrations
- [ ] Microsoft Teams notifications/actions.
- [ ] Entra ID user lookup.
- [ ] Freshservice import/migration utility.
- [ ] Monitoring-system integration.
- [ ] GitHub issue/PR linking when relevant.

## Data and maintenance
- [ ] Automated JSON backups.
- [ ] Backup/restore screen.
- [ ] Archive old closed tickets.
- [ ] Data-retention policy.
- [ ] Integrity validation / repair utility for JSON files.
- [ ] Migration path to SQLite or MySQL if ticket volume outgrows JSON.
- [ ] Installer/setup health check for writable folders, PHP version, and required extensions.
