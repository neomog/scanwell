# Support Module

## Summary

The support module provides a unified internal and customer-facing support workflow for:

- user complaints
- support tickets
- chat support
- bug reports

It is implemented as one shared support-case system with threaded messages so the admin dashboard and mobile app consume the same source of truth.

## Core Design

The module uses a shared `support_cases` table and a `support_messages` table.

Case types:

- `complaint`
- `ticket`
- `bug_report`
- `chat_support`

This allows:

- one support queue in admin
- one mobile support inbox/history
- one status and assignment workflow
- threaded customer and agent replies

## Data Model

### Support Cases

Stored in `support_cases`.

Key fields:

- `reference`
- `user_id`
- `assigned_to`
- `type`
- `subject`
- `description`
- `status`
- `priority`
- `source`
- `attachments`
- `metadata`
- `customer_last_read_at`
- `support_last_read_at`
- `last_message_at`
- `resolved_at`
- `closed_at`

### Support Messages

Stored in `support_messages`.

Key fields:

- `support_case_id`
- `user_id`
- `sender_type`
- `message`
- `attachments`
- `is_internal`

## Status Workflow

Supported statuses:

- `open`
- `pending_support`
- `pending_user`
- `resolved`
- `closed`

Behavior:

- customer-created cases start as `pending_support`
- customer replies move the case to `pending_support`
- support replies move the case to `pending_user`
- support can mark a case `resolved`
- either side can close a case through the appropriate flow

## Admin Dashboard

Web routes:

- `GET /admin/support`
- `GET /admin/support/{supportCase}`
- `POST /admin/support/{supportCase}`
- `POST /admin/support/{supportCase}/messages`

Capabilities:

- view all complaints, tickets, bug reports, and chat support threads
- filter by type, status, priority, and assignment
- assign cases to support agents or admins
- change status and priority
- reply to users
- leave internal notes

## Mobile API

Authenticated user routes:

- `GET /api/v1/support/cases`
- `POST /api/v1/support/cases`
- `GET /api/v1/support/cases/{supportCase}`
- `POST /api/v1/support/cases/{supportCase}/messages`
- `POST /api/v1/support/cases/{supportCase}/close`

Capabilities:

- submit complaints
- open standard support tickets
- report bugs with device metadata
- initiate chat-style support threads
- view message history
- send follow-up replies
- close an existing case

## Admin API

Authenticated admin routes:

- `GET /api/v1/admin/support/cases`
- `GET /api/v1/admin/support/cases/{supportCase}`
- `POST /api/v1/admin/support/cases/{supportCase}`
- `POST /api/v1/admin/support/cases/{supportCase}/messages`

Capabilities:

- list support queue items
- inspect case details and thread history
- assign support owners
- change case status and priority
- reply or add internal notes

## Permissions

New support permissions:

- `support.view`
- `support.manage`
- `complaints.manage`
- `bugs.manage`
- `tickets.view`
- `tickets.manage`

Existing support permissions still remain:

- `complaints.view`
- `bugs.view`
- `logs.view`

Default roles:

- `super_admin`: full access
- `admin`: full support access
- `support`: full support access
- `moderator`: no support queue access by default

## Mobile Notes

For bug reports, the mobile app can pass optional metadata such as:

- `platform`
- `app_version`
- `os_version`
- `device_name`
- `screen`

Optional `attachments` accepts an array of URLs for screenshots or logs already uploaded by the client.

## Related Files

- `app/Models/SupportCase.php`
- `app/Models/SupportMessage.php`
- `app/Services/SupportCaseService.php`
- `app/Http/Controllers/Admin/SupportCaseController.php`
- `app/Http/Controllers/Api/SupportCaseController.php`
- `app/Http/Controllers/Api/Admin/SupportCaseController.php`
- `app/Http/Resources/SupportCaseResource.php`
- `app/Http/Resources/SupportMessageResource.php`
- `resources/views/admin/support/*`
- `routes/web.php`
- `routes/api.php`
