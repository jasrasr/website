<!--
File: TODO.md
File Revision: 0.1.0
Modified: 2026-09-14
History:
0.1.0 - Created future-features and task backlog.
-->

# Ticketing Future Features / Backlog

This file is the holding area for ideas that are intentionally not part of the current build yet.

## High priority

- [ ] Authentication and role-based access
  - Requester login
  - Agent login
  - Admin role
  - Password reset
  - Session timeout
  - Prevent requesters from accessing another user's tickets
- [ ] Email service integration
  - Email requester when ticket is created
  - Email requester when an agent posts a public reply
  - Email assigned agent when a ticket is assigned or updated
  - Create tickets from inbound email
  - Reply-by-email support
  - Configurable SMTP/provider settings
- [ ] Attachments
  - Requester uploads
  - Agent uploads
  - File type/size restrictions
  - Attachment history on ticket
- [ ] Agent/user management
  - Agent profiles
  - Active/inactive agents
  - Agent groups/teams
  - Departments
  - Requester directory

## Ticket management

- [ ] Categories, subcategories, and service/item fields
- [ ] Ticket type: Incident, Service Request, Question, Problem, Change
- [ ] Ticket tags
- [ ] Custom fields
- [ ] Due dates
- [ ] SLA policies and breach timers
- [ ] Business hours and holiday calendars
- [ ] Ticket source tracking: Portal, Email, Phone, Chat, API, Manual
- [ ] Ticket followers/watchers/CC list
- [ ] Parent/child tickets
- [ ] Merge duplicate tickets
- [ ] Split ticket/conversation into a new ticket
- [ ] Soft delete/archive/restore
- [ ] Reopen closed tickets
- [ ] Resolution field and closure reason
- [ ] Escalation reason and escalation level
- [ ] Agent transfer/reassignment history
- [ ] Bulk update tickets
- [ ] Saved views and custom filters
- [ ] Configurable columns in agent ticket table
- [ ] Pagination for large ticket volumes

## Communication and workflow

- [ ] Canned responses / reply templates
- [ ] Agent signatures
- [ ] @mentions for agents
- [ ] Internal notifications
- [ ] Browser/push notifications
- [ ] Automation rules
  - Auto-assign by category/requester/site
  - Auto-set priority
  - Auto-close resolved tickets after a configurable period
  - Escalate overdue tickets
- [ ] Approval workflows
- [ ] Satisfaction survey after closure
- [ ] Requester-facing status messages/banners

## Reporting and dashboards

- [ ] Ticket volume trends
- [ ] Opened vs resolved charts
- [ ] Average first-response time
- [ ] Average resolution time
- [ ] SLA compliance
- [ ] Tickets by agent
- [ ] Tickets by requester
- [ ] Tickets by category/location/department
- [ ] Agent workload dashboard
- [ ] Aging buckets
- [ ] Export reports to CSV/Excel
- [ ] Scheduled reports

## IT service management expansion

- [ ] Asset inventory and ticket-to-asset linking
- [ ] Requester device history
- [ ] Knowledge base
- [ ] Suggested knowledge articles while creating tickets
- [ ] Service catalog
- [ ] Service request forms
- [ ] Problem management
- [ ] Change management
- [ ] Release management
- [ ] Incident/problem/change relationships

## Integrations

- [ ] Microsoft 365 / Entra ID sign-in
- [ ] Active Directory/LDAP directory lookup
- [ ] Microsoft Teams notifications
- [ ] Webhooks
- [ ] REST API with API keys/tokens
- [ ] Freshservice import/migration utility
- [ ] CSV import/export
- [ ] PowerShell module/API helper for creating and updating tickets

## Administration and reliability

- [ ] Admin settings page
- [ ] Configurable statuses and priorities
- [ ] Full audit log of ticket field changes
- [ ] JSON backup/restore
- [ ] Automated rolling backups
- [ ] Data validation/repair utility
- [ ] Concurrent-write protection across full read/modify/write transactions
- [ ] Archive old tickets into separate JSON files
- [ ] Optional migration path from JSON files to SQLite/MySQL
- [ ] Application error log
- [ ] Health/status page
- [ ] Security hardening and CSRF protection
- [ ] Rate limiting / spam protection for public ticket submission

## User experience

- [ ] Light/dark theme toggle
- [ ] Mobile/PWA improvements
- [ ] Ticket number quick-jump
- [ ] Keyboard shortcuts for agents
- [ ] Rich-text replies
- [ ] Markdown support
- [ ] Drag/drop attachments
- [ ] Requester profile page
- [ ] Agent personal queue / "My Tickets"
- [ ] Recently viewed tickets
- [ ] Favorites/pinned tickets
