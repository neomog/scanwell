# Notifications Module

## Summary

The notifications module adds a single communication pipeline for:

- push notifications
- emails
- announcements
- campaigns

It is designed to work across:

- the admin dashboard
- admin API endpoints
- the mobile app inbox

The implementation follows the platform's existing request and response contract:

- `success`
- `message`
- `data`
- `errors`

## Core Data Model

### Notification Campaigns

Stored in `notification_campaigns`.

Purpose:

- define the message an admin wants to send
- store audience and channel configuration
- track schedule and delivery status

Important fields:

- `type`: `announcement` or `campaign`
- `channels`: `in_app`, `email`, `push`
- `audience_type`: `all_users`, `roles`, or `users`
- `audience_filters`
- `status`
- `scheduled_at`
- `sent_at`
- `delivery_summary`

### User Notifications

Stored in `user_notifications`.

Purpose:

- create the mobile-consumable inbox record per recipient
- persist read state
- keep channel-level delivery metadata

Important fields:

- `campaign_id`
- `user_id`
- `title`
- `body`
- `channels`
- `channel_statuses`
- `delivered_at`
- `read_at`

### Push Tokens

Stored in `user_push_tokens`.

Purpose:

- register mobile device tokens for push delivery
- support multiple devices per user

## Admin Dashboard

Web routes:

- `GET /admin/notifications`
- `GET /admin/notifications/create`
- `POST /admin/notifications`
- `GET /admin/notifications/{notification}`
- `POST /admin/notifications/{notification}/send`

Capabilities:

- create announcements and campaigns
- choose channels
- target all users, roles, or selected users
- schedule for later
- resend a previous campaign
- inspect recipient delivery and read state

Navigation and dashboard integration:

- sidebar link is shown through `notifications.view`
- dashboard overview now shows notification metrics and activity

## Mobile API

Authenticated app routes:

- `GET /api/v1/notifications`
- `GET /api/v1/notifications/unread-count`
- `GET /api/v1/notifications/{notification}`
- `POST /api/v1/notifications/{notification}/read`
- `POST /api/v1/notifications/read-all`
- `POST /api/v1/devices/push-tokens`
- `DELETE /api/v1/devices/push-tokens`

Authentication:

- all mobile notification endpoints require `auth:sanctum`
- send `Authorization: Bearer {token}`
- send `Accept: application/json`

Base URL example:

- `https://scanwell.ohiare.com`

### Inbox Response Shape

`GET /api/v1/notifications`

Returns:

- `notifications`
- `pagination`
- `unread_count`

Each notification includes:

- `id`
- `campaign_id`
- `type`
- `title`
- `body`
- `cta_label`
- `cta_url`
- `channels`
- `channel_statuses`
- `is_read`
- `delivered_at`
- `read_at`
- `created_at`

### Push Token Registration

Request:

- `platform`
- `token`
- `device_name` optional

Response:

- registered push token metadata in the standard API envelope

## Mobile Developer Consumption Guide

This section is intended for the developer integrating notifications into the mobile application.

### Standard Headers

Use these headers on every authenticated request:

```bash
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```

### 1. Get Notifications Inbox

Use this to populate the notifications list screen.

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/notifications \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

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

Use this for a badge count on the app tab, bell icon, or home screen.

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/notifications/unread-count \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

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

Use this when the user opens a single notification details screen.

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/notifications/2eecae57-f4a4-497a-aec5-4170b58d3f09 \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

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

Call this after the user opens a notification or when your UX decides it should transition to read state.

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/notifications/2eecae57-f4a4-497a-aec5-4170b58d3f09/read \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

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

Use this for a "mark all as read" action.

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/notifications/read-all \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json"
```

Sample response:

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

Call this after login or whenever the device token changes.

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

Sample response:

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

Call this on logout, uninstall cleanup flow, or when push permission is revoked.

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

Sample response:

```json
{
  "success": true,
  "message": "Push token deactivated",
  "data": null,
  "errors": null
}
```

### Mobile Integration Notes

- notifications are user-specific records from `user_notifications`
- the mobile app should render from `data.notifications`
- use `unread_count` directly for the badge instead of counting client-side
- `cta_url` can be used for deep linking inside the app
- `channels` shows which delivery channels were selected for the campaign
- `channel_statuses` is useful for support or diagnostics but is not required for basic inbox rendering
- `is_read` is the main flag the app should rely on for read state
- if a campaign is resent, the same user can receive a refreshed inbox item with updated delivery timing

### Validation And Error Shape

When validation fails, the API follows the existing app contract.

Sample validation error:

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

## Admin API

Authenticated admin routes:

- `GET /api/v1/admin/notifications`
- `POST /api/v1/admin/notifications`
- `GET /api/v1/admin/notifications/{notification}`
- `POST /api/v1/admin/notifications/{notification}/send`

These endpoints are intended for internal dashboards or future admin clients and follow the same response contract as the rest of the API.

## Permissions

New permissions:

- `notifications.view`
- `notifications.manage`

Default role behavior:

- `super_admin`: full access
- `admin`: view and manage
- `moderator`: view only
- `support`: view only

## Delivery Flow

1. Admin creates a campaign or announcement.
2. Audience is resolved from all users, selected roles, or selected users.
3. A `user_notifications` record is created or updated for each recipient.
4. In-app delivery becomes immediately available to the mobile inbox.
5. Email delivery is attempted through Laravel Mail when the `email` channel is selected.
6. Push delivery is attempted through the configured push endpoint when the `push` channel is selected.
7. Read state from the mobile app updates the parent campaign's `read_count`.

## Push Configuration

Environment variables:

- `PUSH_NOTIFICATIONS_ENABLED`
- `PUSH_NOTIFICATIONS_ENDPOINT`
- `PUSH_NOTIFICATIONS_TOKEN`
- `PUSH_NOTIFICATIONS_TIMEOUT`

Current behavior:

- if push is not configured, push delivery is marked as `skipped`
- inbox delivery still succeeds

## Implementation Files

- `app/Services/NotificationDeliveryService.php`
- `app/Services/NotificationAudienceResolver.php`
- `app/Services/PushNotificationService.php`
- `app/Http/Controllers/Admin/NotificationController.php`
- `app/Http/Controllers/Api/NotificationController.php`
- `app/Http/Controllers/Api/Admin/NotificationController.php`
- `app/Http/Resources/NotificationCampaignResource.php`
- `app/Http/Resources/UserNotificationResource.php`
- `resources/views/admin/notifications/*`
- `routes/web.php`
- `routes/api.php`
