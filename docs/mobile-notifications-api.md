# Mobile Notifications API

## Overview

This document is for the mobile developer consuming the Scanwell notifications API.

The notifications system supports:

- in-app inbox notifications
- push notification token registration
- unread badge counts
- read-state updates

All responses follow the platform standard:

- `success`
- `message`
- `data`
- `errors`

Base URL example:

- `https://scanwell.ohiare.com`

## Authentication

All endpoints in this document require an authenticated user with a valid Sanctum bearer token.

Required headers:

```bash
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```

## Endpoints

### 1. Get Notifications Inbox

Endpoint:

- `GET /api/v1/notifications`

Purpose:

- load the notifications listing screen
- return unread count with paginated inbox items

Example request:

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/notifications \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Example response:

```json
{
  "success": true,
  "message": "Notifications loaded",
  "data": {
    "notifications": [
      {
        "id": "2eecae57-f4a4-497a-aec5-4170b58d3f09",
        "campaign_id": "ca0f4221-2d73-4fd6-95a1-196230111667",
        "type": "announcement",
        "title": "Maintenance window",
        "body": "We are performing scheduled maintenance tonight.",
        "cta_label": "View Details",
        "cta_url": "scanwell://announcements/maintenance-window",
        "channels": [
          "in_app",
          "push",
          "email"
        ],
        "channel_statuses": {
          "in_app": {
            "status": "sent",
            "sent_at": "2026-05-03T10:15:00.000000Z"
          },
          "email": {
            "status": "sent",
            "message": "Email sent successfully."
          },
          "push": {
            "status": "sent",
            "message": "Push sent successfully.",
            "sent_at": "2026-05-03T10:15:01.000000Z",
            "tokens": 1
          }
        },
        "data": {
          "campaign_status": "sent"
        },
        "is_read": false,
        "delivered_at": "2026-05-03T10:15:01.000000Z",
        "read_at": null,
        "created_at": "2026-05-03T10:15:01.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    },
    "unread_count": 1
  },
  "errors": null
}
```

### 2. Get Unread Count

Endpoint:

- `GET /api/v1/notifications/unread-count`

Purpose:

- update the app badge count
- poll unread count without reloading the full inbox

Example request:

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/notifications/unread-count \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Example response:

```json
{
  "success": true,
  "message": "Unread notifications count",
  "data": {
    "unread_count": 3
  },
  "errors": null
}
```

### 3. Get Notification Detail

Endpoint:

- `GET /api/v1/notifications/{notification}`

Purpose:

- load a single notification detail screen

Example request:

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/notifications/2eecae57-f4a4-497a-aec5-4170b58d3f09 \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Example response:

```json
{
  "success": true,
  "message": "Notification loaded",
  "data": {
    "notification": {
      "id": "2eecae57-f4a4-497a-aec5-4170b58d3f09",
      "campaign_id": "ca0f4221-2d73-4fd6-95a1-196230111667",
      "type": "announcement",
      "title": "Maintenance window",
      "body": "We are performing scheduled maintenance tonight.",
      "cta_label": "View Details",
      "cta_url": "scanwell://announcements/maintenance-window",
      "channels": [
        "in_app",
        "push",
        "email"
      ],
      "channel_statuses": {
        "in_app": {
          "status": "sent",
          "sent_at": "2026-05-03T10:15:00.000000Z"
        }
      },
      "data": {
        "campaign_status": "sent"
      },
      "is_read": false,
      "delivered_at": "2026-05-03T10:15:01.000000Z",
      "read_at": null,
      "created_at": "2026-05-03T10:15:01.000000Z"
    }
  },
  "errors": null
}
```

### 4. Mark One Notification As Read

Endpoint:

- `POST /api/v1/notifications/{notification}/read`

Purpose:

- mark a notification as read after it is opened

Example request:

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/notifications/2eecae57-f4a4-497a-aec5-4170b58d3f09/read \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Example response:

```json
{
  "success": true,
  "message": "Notification marked as read",
  "data": {
    "notification": {
      "id": "2eecae57-f4a4-497a-aec5-4170b58d3f09",
      "campaign_id": "ca0f4221-2d73-4fd6-95a1-196230111667",
      "type": "announcement",
      "title": "Maintenance window",
      "body": "We are performing scheduled maintenance tonight.",
      "cta_label": "View Details",
      "cta_url": "scanwell://announcements/maintenance-window",
      "channels": [
        "in_app",
        "push",
        "email"
      ],
      "channel_statuses": {
        "in_app": {
          "status": "sent",
          "sent_at": "2026-05-03T10:15:00.000000Z"
        }
      },
      "data": {
        "campaign_status": "sent"
      },
      "is_read": true,
      "delivered_at": "2026-05-03T10:15:01.000000Z",
      "read_at": "2026-05-03T10:19:42.000000Z",
      "created_at": "2026-05-03T10:15:01.000000Z"
    }
  },
  "errors": null
}
```

### 5. Mark All Notifications As Read

Endpoint:

- `POST /api/v1/notifications/read-all`

Purpose:

- support a "mark all as read" action

Example request:

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/notifications/read-all \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Example response:

```json
{
  "success": true,
  "message": "All notifications marked as read",
  "data": {
    "unread_count": 0
  },
  "errors": null
}
```

### 6. Register Push Token

Endpoint:

- `POST /api/v1/devices/push-tokens`

Purpose:

- register a device token after login
- update or reactivate the token when it changes

Request fields:

- `platform`: `ios`, `android`, or `web`
- `token`: device token string
- `device_name`: optional readable label

Example request:

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/devices/push-tokens \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "platform": "android",
    "token": "expo-device-token-or-fcm-token",
    "device_name": "Samsung Galaxy S24"
  }'
```

Example response:

```json
{
  "success": true,
  "message": "Push token registered",
  "data": {
    "push_token": {
      "id": 1,
      "platform": "android",
      "device_name": "Samsung Galaxy S24",
      "is_active": true,
      "last_used_at": "2026-05-03T10:21:00.000000Z"
    }
  },
  "errors": null
}
```

### 7. Unregister Push Token

Endpoint:

- `DELETE /api/v1/devices/push-tokens`

Purpose:

- deactivate a token on logout
- deactivate a token when push permission is revoked

Example request:

```bash
curl --request DELETE \
  --url https://scanwell.ohiare.com/api/v1/devices/push-tokens \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "token": "expo-device-token-or-fcm-token"
  }'
```

Example response:

```json
{
  "success": true,
  "message": "Push token deactivated",
  "data": null,
  "errors": null
}
```

## Field Reference

### Notification Object

- `id`: unique inbox notification id
- `campaign_id`: parent campaign id created by admin
- `type`: `announcement` or `campaign`
- `title`: notification title
- `body`: notification body text
- `cta_label`: optional action button label
- `cta_url`: optional deep link or URL
- `channels`: channels selected by admin
- `channel_statuses`: delivery status per channel
- `data`: extra metadata
- `is_read`: boolean read state
- `delivered_at`: time the inbox record was delivered
- `read_at`: time the user read the notification
- `created_at`: record creation time

### Pagination Object

- `current_page`
- `last_page`
- `per_page`
- `total`

## Integration Notes

- render the inbox from `data.notifications`
- use `data.unread_count` for badge count
- use `is_read` for styling read vs unread state
- call the single read endpoint when a user opens a message
- call the read-all endpoint only for explicit user action
- treat `cta_url` as a deep-link target where applicable
- `channel_statuses` is mostly diagnostic and can be ignored for a basic mobile inbox UI

## Error Contract

### Validation Failure Example

```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "platform": [
      "The selected platform is invalid."
    ]
  }
}
```

### Unauthenticated Example

```json
{
  "success": false,
  "message": "Unauthenticated. Please login.",
  "data": null,
  "errors": "Unauthenticated."
}
```
