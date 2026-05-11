# Mobile Support API

## Overview

This document is for the mobile developer consuming the support module.

The mobile support API covers:

- complaints
- tickets
- chat support
- bug reports

All responses follow the standard API envelope:

- `success`
- `message`
- `data`
- `errors`

Base URL example:

- `https://scanwell.ohiare.com`

## Authentication

All endpoints require:

```bash
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```

## Case Types

Use one of:

- `complaint`
- `ticket`
- `bug_report`
- `chat_support`

## Case Statuses

Cases returned by the API can be in one of these states:

- `open`
- `pending_support`
- `pending_user`
- `resolved`
- `closed`

Status behavior:

- newly created cases start as `pending_support`
- customer replies move the case to `pending_support`
- support replies move the case to `pending_user`
- closed cases cannot receive new customer messages

## Create Case Payload

Required fields:

- `type`
- `subject`
- `description`

Optional fields:

- `priority`
- `attachments`
- `metadata`

Allowed `priority` values:

- `low`
- `normal`
- `high`
- `urgent`

Supported `metadata` keys:

- `platform`
- `app_version`
- `os_version`
- `device_name`
- `screen`

## 1. List My Support Cases

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/support/cases \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Optional query params:

- `type`
- `status`
- `per_page`

Example with filters:

```bash
curl --request GET \
  --url "https://scanwell.ohiare.com/api/v1/support/cases?type=bug_report&status=pending_support&per_page=10" \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

```json
{
  "success": true,
  "message": "Support cases loaded",
  "data": {
    "cases": [
      {
        "id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
        "reference": "SUP-20260503-AB12CD",
        "type": "bug_report",
        "subject": "App crashes on scan",
        "description": "The app crashes whenever I scan a barcode.",
        "status": "pending_support",
        "priority": "high",
        "source": "mobile",
        "attachments": [
          "https://example.com/screenshot.png"
        ],
        "metadata": {
          "platform": "android",
          "app_version": "1.4.2",
          "device_name": "Pixel 8"
        },
        "last_message_at": "2026-05-03T11:00:00.000000Z",
        "resolved_at": null,
        "closed_at": null,
        "created_at": "2026-05-03T11:00:00.000000Z",
        "updated_at": "2026-05-03T11:00:00.000000Z",
        "assignee": null
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    }
  },
  "errors": null
}
```

## 2. Create A Support Case

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/support/cases \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "type": "bug_report",
    "subject": "App crashes on scan",
    "description": "The app crashes whenever I scan a barcode.",
    "priority": "high",
    "attachments": ["https://example.com/screenshot.png"],
    "metadata": {
      "platform": "android",
      "app_version": "1.4.2",
      "os_version": "14",
      "device_name": "Pixel 8",
      "screen": "scanner"
    }
  }'
```

Sample response:

```json
{
  "success": true,
  "message": "Support case created successfully",
  "data": {
    "case": {
      "id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
      "reference": "SUP-20260503-AB12CD",
      "type": "bug_report",
      "subject": "App crashes on scan",
      "description": "The app crashes whenever I scan a barcode.",
      "status": "pending_support",
      "priority": "high",
      "source": "mobile",
      "attachments": [
        "https://example.com/screenshot.png"
      ],
      "metadata": {
        "platform": "android",
        "app_version": "1.4.2",
        "os_version": "14",
        "device_name": "Pixel 8",
        "screen": "scanner"
      },
      "last_message_at": "2026-05-03T11:00:00.000000Z",
      "resolved_at": null,
      "closed_at": null,
      "created_at": "2026-05-03T11:00:00.000000Z",
      "updated_at": "2026-05-03T11:00:00.000000Z"
    }
  },
  "errors": null
}
```

## 3. Get One Support Case

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/support/cases/09e18c46-d3b5-45ba-86fb-95ce86a20fa4 \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

```json
{
  "success": true,
  "message": "Support case loaded",
  "data": {
    "case": {
      "id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
      "reference": "SUP-20260503-AB12CD",
      "type": "chat_support",
      "subject": "Need help changing my plan",
      "description": "I want to move from free to pro.",
      "status": "pending_user",
      "priority": "normal",
      "source": "mobile",
      "attachments": [],
      "metadata": {},
      "last_message_at": "2026-05-03T11:15:00.000000Z",
      "resolved_at": null,
      "closed_at": null,
      "created_at": "2026-05-03T11:00:00.000000Z",
      "updated_at": "2026-05-03T11:15:00.000000Z",
      "assignee": {
        "id": "53ddf788-df4f-4b5c-983c-3e2c6929284c",
        "name": "Support Agent",
        "email": "support@example.com"
      }
    },
    "messages": [
      {
        "id": "7f9b41ea-b9c6-4cf4-8dbf-3c3c1deae07a",
        "support_case_id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
        "sender_type": "customer",
        "message": "I want to move from free to pro.",
        "attachments": [],
        "is_internal": false,
        "read_at": null,
        "created_at": "2026-05-03T11:00:00.000000Z",
        "user": {
          "id": "c4c586a3-8fd8-4ec8-b894-cb0c9d6015bb",
          "name": "John Doe",
          "email": "john@example.com",
          "role": "user"
        }
      },
      {
        "id": "c9d0cf8c-5c79-4ca9-b8ec-e5d4746c2f5f",
        "support_case_id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
        "sender_type": "support",
        "message": "Sure, I can guide you through it.",
        "attachments": [],
        "is_internal": false,
        "read_at": null,
        "created_at": "2026-05-03T11:15:00.000000Z",
        "user": {
          "id": "53ddf788-df4f-4b5c-983c-3e2c6929284c",
          "name": "Support Agent",
          "email": "support@example.com",
          "role": "support"
        }
      }
    ]
  },
  "errors": null
}
```

## 4. Reply To A Support Case

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/support/cases/09e18c46-d3b5-45ba-86fb-95ce86a20fa4/messages \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "message": "Additional detail: it crashes after I sign in."
  }'
```

Reply with attachments example:

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/support/cases/09e18c46-d3b5-45ba-86fb-95ce86a20fa4/messages \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "message": "Here is an updated screen recording of the issue.",
    "attachments": [
      "https://example.com/crash-video.mp4"
    ]
  }'
```

Sample response:

```json
{
  "success": true,
  "message": "Support message sent successfully",
  "data": {
    "message_item": {
      "id": "5e2c86b5-feb5-433a-8d4e-3b50288f43bc",
      "support_case_id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
      "sender_type": "customer",
      "message": "Additional detail: it crashes after I sign in.",
      "attachments": [],
      "is_internal": false,
      "read_at": null,
      "created_at": "2026-05-03T11:25:00.000000Z",
      "user": {
        "id": "c4c586a3-8fd8-4ec8-b894-cb0c9d6015bb",
        "name": "John Doe",
        "email": "john@example.com",
        "role": "user"
      }
    },
    "case": {
      "id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
      "reference": "SUP-20260503-AB12CD",
      "type": "bug_report",
      "subject": "App crashes on scan",
      "description": "The app crashes whenever I scan a barcode.",
      "status": "pending_support",
      "priority": "high",
      "source": "mobile",
      "attachments": [
        "https://example.com/screenshot.png"
      ],
      "metadata": {
        "platform": "android",
        "app_version": "1.4.2"
      },
      "last_message_at": "2026-05-03T11:25:00.000000Z",
      "resolved_at": null,
      "closed_at": null,
      "created_at": "2026-05-03T11:00:00.000000Z",
      "updated_at": "2026-05-03T11:25:00.000000Z"
    }
  },
  "errors": null
}
```

## 5. Close A Support Case

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/support/cases/09e18c46-d3b5-45ba-86fb-95ce86a20fa4/close \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

```json
{
  "success": true,
  "message": "Support case closed successfully",
  "data": {
    "case": {
      "id": "09e18c46-d3b5-45ba-86fb-95ce86a20fa4",
      "reference": "SUP-20260503-AB12CD",
      "type": "ticket",
      "subject": "Need help changing my plan",
      "description": "I want to move from free to pro.",
      "status": "closed",
      "priority": "normal",
      "source": "mobile",
      "attachments": [],
      "metadata": {},
      "last_message_at": "2026-05-03T11:25:00.000000Z",
      "resolved_at": null,
      "closed_at": "2026-05-03T11:30:00.000000Z",
      "created_at": "2026-05-03T11:00:00.000000Z",
      "updated_at": "2026-05-03T11:30:00.000000Z"
    }
  },
  "errors": null
}
```

## Validation Error Example

```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "type": [
      "The selected type is invalid."
    ]
  }
}
```

## Closed Case Reply Error Example

If the customer tries to reply to a resolved or closed case, the API returns `422 Unprocessable Entity`.

Sample response:

```json
{
  "success": false,
  "message": "Resolved or closed support cases cannot receive new customer messages.",
  "data": null,
  "errors": null
}
```

## Quick Mobile Flow

Typical mobile app flow:

1. Create a case with `POST /api/v1/support/cases`.
2. Show the customer ticket list with `GET /api/v1/support/cases`.
3. Open a thread with `GET /api/v1/support/cases/{supportCase}`.
4. Send follow-up messages with `POST /api/v1/support/cases/{supportCase}/messages`.
5. Let the customer close the thread with `POST /api/v1/support/cases/{supportCase}/close`.
