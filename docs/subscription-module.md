# Subscription Module Documentation

## Summary

This document describes the Scanwell subscription and monetization module across:

- mobile API usage
- web billing usage
- admin plan and subscription management
- feature gating behavior
- Stripe checkout flow

It also includes:

- end-to-end flow notes
- sample `curl` requests
- sample JSON responses
- Postman usage guidance

## Current Plan Catalog

The module currently seeds these plans:

- `free`
- `pro`
- `team`

By default:

- every newly registered user is assigned the `free` plan automatically

## Current Billing Model

### Providers

The local subscription record can be created by:

- `system`
  Used for the default free plan and manual admin overrides.
- `stripe`
  Used for paid subscriptions created or managed through Stripe.

### Subscription States

The main statuses in use are:

- `active`
- `trialing`
- `past_due`
- `canceling`
- `canceled`
- `refunded`
- `replaced`
- `incomplete`
- `unpaid`

## Feature Gating Model

Plans carry feature flags and limits. Current feature keys include:

- `scans.enabled`
- `scans.monthly_limit`
- `recommendations.enabled`
- `contributions.enabled`
- `history.export.enabled`
- `priority_support.enabled`
- `team.seats`

Feature gating is enforced in the API.

Examples:

- `GET /api/v1/recommendations` requires `recommendations.enabled`
- `POST /api/v1/products/{barcode}/contribute` requires `contributions.enabled`
- `POST /api/v1/scan` checks both `scans.enabled` and `scans.monthly_limit`

If access is blocked, the API returns `403`.

## Mobile App Flow

This section assumes:

- your mobile app is built with React Native Expo
- the app is still in development
- the app is not yet distributed through Play Store or App Store

That means the current Stripe hosted checkout approach is acceptable for development and internal testing.

### Recommended Mobile Flow

1. User signs up or logs in.
2. Mobile stores the auth token.
3. Mobile fetches current subscription status.
4. Mobile fetches the active plan catalog.
5. User selects a paid price.
6. Mobile requests a Stripe checkout URL from the API.
7. Mobile opens the checkout URL using Expo browser or in-app browser.
8. Stripe redirects back to your Expo deep link or app URL.
9. Mobile refreshes subscription status.
10. Feature-gated screens read the updated entitlements from the API.

### Expo Checkout Return URLs

For development, the mobile app can send custom deep links such as:

```text
scanwell://billing/success
scanwell://billing/cancel
```

Example Expo-side idea:

- request checkout session from backend
- open `checkout_url` with `expo-web-browser`
- on app resume or deep-link return, call `GET /api/v1/billing/subscription`

## Web User Flow

The web UI uses:

- `GET /billing`
- `POST /billing/prices/{price}/checkout`
- `POST /billing/cancel`

The web page displays:

- current plan
- plan feature comparison
- active prices
- cancel action for paid Stripe subscriptions

## Admin Flow

### Plan Management

Admins can:

- create plans
- edit plans
- create and edit prices
- archive and reactivate plans
- archive and reactivate prices
- hard-delete only unused plans and prices

Deletion is intentionally restricted:

- a plan cannot be deleted if it is default
- a plan cannot be deleted if it has prices
- a plan cannot be deleted if it has subscription history
- a price cannot be deleted if it has subscription history

### User Subscription Reassignment

Admins can reassign a user’s effective plan from the user detail page using one of:

- `billing_now`
- `billing_next_cycle`
- `manual_override`

Behavior:

- `billing_now`
  Updates Stripe immediately for paid Stripe-managed subscriptions, or moves to a free/system plan immediately.
- `billing_next_cycle`
  Schedules the current Stripe subscription to end at period close, then applies the new plan after webhook completion.
- `manual_override`
  Creates a local `system` subscription and is intended for complimentary access, support intervention, or internal testing.

## API Base URL

Examples below assume:

```text
https://scanwell.ohiare.com/api
```

Use your actual environment host if different.

## Headers

### Public endpoints

```text
Accept: application/json
```

### Protected endpoints

```text
Accept: application/json
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

## Postman Setup

Recommended Postman environment variables:

- `base_url`
- `user_token`
- `admin_token`

Suggested values:

```text
base_url=https://scanwell.ohiare.com/api
user_token=YOUR_USER_BEARER_TOKEN
admin_token=YOUR_ADMIN_BEARER_TOKEN
```

You can import any `curl` example below into Postman:

1. Open Postman
2. Click `Import`
3. Choose `Raw text`
4. Paste the `curl`
5. Import

## API Endpoints

### 1. Register User

Creates a user and automatically assigns the default `free` subscription.

#### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/register \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "name": "Mayor",
    "email": "mayor@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

#### Sample Response

```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": "1e1b7f48-2f33-4ab4-93cf-9d5f63633411",
      "name": "Mayor",
      "email": "mayor@example.com",
      "avatar": null,
      "provider": null,
      "subscription": {
        "plan": {
          "id": 1,
          "slug": "free",
          "name": "Free"
        },
        "price": {
          "id": 1,
          "name": "Free"
        },
        "status": "active",
        "ends_at": null,
        "current_period_ends_at": null
      },
      "created_at": "2026-04-26T12:00:00.000000Z",
      "updated_at": "2026-04-26T12:00:00.000000Z"
    },
    "token": "1|example-token",
    "token_type": "Bearer"
  },
  "errors": null
}
```

### 2. Login User

#### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/login \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "email": "mayor@example.com",
    "password": "password123"
  }'
```

#### Sample Response

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": "1e1b7f48-2f33-4ab4-93cf-9d5f63633411",
      "name": "Mayor",
      "email": "mayor@example.com",
      "avatar": null,
      "provider": null,
      "subscription": {
        "plan": {
          "id": 1,
          "slug": "free",
          "name": "Free"
        },
        "price": {
          "id": 1,
          "name": "Free"
        },
        "status": "active",
        "ends_at": null,
        "current_period_ends_at": null
      },
      "created_at": "2026-04-26T12:00:00.000000Z",
      "updated_at": "2026-04-26T12:00:00.000000Z"
    },
    "token": "2|example-login-token",
    "token_type": "Bearer"
  },
  "errors": null
}
```

### 3. Google Mobile Auth

Current mobile Google auth also returns subscription-aware user data.

#### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/auth/google \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "token": "GOOGLE_ID_TOKEN"
  }'
```

#### Sample Response

```json
{
  "success": true,
  "message": "Mobile Google authentication successful",
  "data": {
    "token": "3|example-google-token",
    "user": {
      "id": "8ce8d2fb-21c2-4c1e-a4f4-b101ab44b0a3",
      "name": "Mayor",
      "email": "mayor@example.com",
      "avatar": "https://lh3.googleusercontent.com/example-photo",
      "provider": "google",
      "subscription": {
        "plan": {
          "id": 1,
          "slug": "free",
          "name": "Free"
        },
        "price": {
          "id": 1,
          "name": "Free"
        },
        "status": "active",
        "ends_at": null,
        "current_period_ends_at": null
      },
      "created_at": "2026-04-26T12:15:00.000000Z",
      "updated_at": "2026-04-26T12:15:00.000000Z"
    }
  }
}
```

### 4. Get Available Plans

Endpoint:

```text
GET /api/v1/billing/plans
```

This endpoint is public.

#### cURL

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/billing/plans \
  --header "Accept: application/json"
```

#### Sample Response

```json
{
  "success": true,
  "message": "Available plans",
  "data": {
    "plans": [
      {
        "id": 1,
        "slug": "free",
        "name": "Free",
        "description": "For individual users getting started with Scanwell.",
        "is_default": true,
        "display_order": 1,
        "features": {
          "scans.enabled": {
            "label": "Barcode scans",
            "type": "boolean",
            "value": true
          },
          "scans.monthly_limit": {
            "label": "Monthly scan limit",
            "type": "integer",
            "value": 50
          },
          "recommendations.enabled": {
            "label": "Personalized recommendations",
            "type": "boolean",
            "value": false
          },
          "contributions.enabled": {
            "label": "Community contributions",
            "type": "boolean",
            "value": true
          },
          "history.export.enabled": {
            "label": "History export",
            "type": "boolean",
            "value": false
          },
          "priority_support.enabled": {
            "label": "Priority support",
            "type": "boolean",
            "value": false
          },
          "team.seats": {
            "label": "Team seats",
            "type": "integer",
            "value": 1
          }
        },
        "prices": [
          {
            "id": 1,
            "name": "Free",
            "amount": 0,
            "currency": "usd",
            "formatted_amount": "USD 0.00",
            "billing_interval": "month",
            "billing_interval_count": 1,
            "trial_days": 0,
            "is_default": true,
            "is_free": true,
            "stripe_enabled": false
          }
        ]
      },
      {
        "id": 2,
        "slug": "pro",
        "name": "Pro",
        "description": "For power users who want more scans and premium insights.",
        "is_default": false,
        "display_order": 2,
        "features": {
          "scans.enabled": {
            "label": "Barcode scans",
            "type": "boolean",
            "value": true
          },
          "scans.monthly_limit": {
            "label": "Monthly scan limit",
            "type": "integer",
            "value": null
          },
          "recommendations.enabled": {
            "label": "Personalized recommendations",
            "type": "boolean",
            "value": true
          }
        },
        "prices": [
          {
            "id": 2,
            "name": "Monthly",
            "amount": 1900,
            "currency": "usd",
            "formatted_amount": "USD 19.00",
            "billing_interval": "month",
            "billing_interval_count": 1,
            "trial_days": 7,
            "is_default": true,
            "is_free": false,
            "stripe_enabled": true
          },
          {
            "id": 3,
            "name": "Annual",
            "amount": 19000,
            "currency": "usd",
            "formatted_amount": "USD 190.00",
            "billing_interval": "year",
            "billing_interval_count": 1,
            "trial_days": 7,
            "is_default": false,
            "is_free": false,
            "stripe_enabled": true
          }
        ]
      }
    ],
    "feature_catalog": {
      "scans.enabled": {
        "label": "Barcode scans",
        "type": "boolean"
      },
      "scans.monthly_limit": {
        "label": "Monthly scan limit",
        "type": "integer"
      }
    },
    "stripe": {
      "configured": true
    }
  },
  "errors": null
}
```

### 5. Get Current Subscription

Endpoint:

```text
GET /api/v1/billing/subscription
```

#### cURL

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/billing/subscription \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN"
```

#### Sample Response

```json
{
  "success": true,
  "message": "Current subscription",
  "data": {
    "subscription": {
      "id": "c0df67c4-4b87-4f01-a95f-0384ec0d9a75",
      "provider": "system",
      "status": "active",
      "is_paid": false,
      "amount": 0,
      "currency": "usd",
      "quantity": 1,
      "starts_at": "2026-04-26T12:00:00.000000Z",
      "current_period_starts_at": "2026-04-26T12:00:00.000000Z",
      "current_period_ends_at": null,
      "trial_ends_at": null,
      "ends_at": null,
      "plan": {
        "id": 1,
        "slug": "free",
        "name": "Free",
        "description": "For individual users getting started with Scanwell.",
        "is_default": true,
        "display_order": 1,
        "features": {
          "scans.enabled": {
            "label": "Barcode scans",
            "type": "boolean",
            "value": true
          },
          "scans.monthly_limit": {
            "label": "Monthly scan limit",
            "type": "integer",
            "value": 50
          }
        },
        "prices": []
      },
      "price": {
        "id": 1,
        "name": "Free",
        "amount": 0,
        "currency": "usd",
        "formatted_amount": "USD 0.00",
        "billing_interval": "month",
        "billing_interval_count": 1,
        "trial_days": 0,
        "is_free": true
      },
      "pending_change": null
    },
    "usage": {
      "monthly_scans": {
        "used": 5,
        "limit": 50,
        "remaining": 45
      }
    },
    "feature_catalog": {
      "scans.enabled": {
        "label": "Barcode scans",
        "type": "boolean"
      },
      "scans.monthly_limit": {
        "label": "Monthly scan limit",
        "type": "integer"
      }
    },
    "stripe": {
      "configured": true,
      "can_checkout": true,
      "can_cancel": false
    }
  },
  "errors": null
}
```

### 6. Create Stripe Checkout Session

Endpoint:

```text
POST /api/v1/billing/checkout
```

Used by mobile to obtain a Stripe hosted checkout URL.

For Expo development, pass deep links in `success_url` and `cancel_url`.

#### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/billing/checkout \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "price_id": 2,
    "success_url": "scanwell://billing/success",
    "cancel_url": "scanwell://billing/cancel"
  }'
```

#### Sample Response

```json
{
  "success": true,
  "message": "Checkout session created",
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_a1b2c3d4",
    "session_id": "cs_test_a1b2c3d4",
    "price": {
      "id": 2,
      "name": "Monthly",
      "amount": 1900,
      "currency": "usd"
    },
    "plan": {
      "id": 2,
      "slug": "pro",
      "name": "Pro"
    }
  },
  "errors": null
}
```

### 7. Cancel Current Paid Subscription

Endpoint:

```text
POST /api/v1/billing/cancel
```

This schedules cancellation at period end for the current paid Stripe subscription.

#### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/billing/cancel \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json"
```

#### Sample Response

```json
{
  "success": true,
  "message": "Subscription will cancel at the end of the current billing period.",
  "data": {
    "subscription": {
      "id": "8fdd6ebc-9df8-4d75-a8d2-ff2c5f0955a7",
      "provider": "stripe",
      "status": "canceling",
      "is_paid": true,
      "amount": 1900,
      "currency": "usd",
      "quantity": 1,
      "starts_at": "2026-04-26T12:30:00.000000Z",
      "current_period_starts_at": "2026-04-26T12:30:00.000000Z",
      "current_period_ends_at": "2026-05-26T12:30:00.000000Z",
      "trial_ends_at": null,
      "ends_at": "2026-05-26T12:30:00.000000Z",
      "plan": {
        "id": 2,
        "slug": "pro",
        "name": "Pro",
        "description": "For power users who want more scans and premium insights.",
        "is_default": false,
        "display_order": 2,
        "features": {
          "recommendations.enabled": {
            "label": "Personalized recommendations",
            "type": "boolean",
            "value": true
          }
        },
        "prices": []
      },
      "price": {
        "id": 2,
        "name": "Monthly",
        "amount": 1900,
        "currency": "usd",
        "formatted_amount": "USD 19.00",
        "billing_interval": "month",
        "billing_interval_count": 1,
        "trial_days": 7,
        "is_free": false
      },
      "pending_change": null
    }
  },
  "errors": null
}
```

### 8. Authenticated User Snapshot

Endpoint:

```text
GET /api/user
```

This is useful after checkout completion to refresh the current user and plan badge.

#### cURL

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/user \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN"
```

#### Sample Response

```json
{
  "success": true,
  "message": "Authenticated user",
  "data": {
    "id": "1e1b7f48-2f33-4ab4-93cf-9d5f63633411",
    "name": "Mayor",
    "email": "mayor@example.com",
    "avatar": null,
    "provider": null,
    "subscription": {
      "plan": {
        "id": 2,
        "slug": "pro",
        "name": "Pro"
      },
      "price": {
        "id": 2,
        "name": "Monthly"
      },
      "status": "active",
      "ends_at": null,
      "current_period_ends_at": "2026-05-26T12:30:00.000000Z"
    },
    "created_at": "2026-04-26T12:00:00.000000Z",
    "updated_at": "2026-04-26T12:31:00.000000Z"
  },
  "errors": null
}
```

## Feature Gating Response Samples

### 9. Recommendation Access Blocked By Plan

#### cURL

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/recommendations \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN"
```

#### Sample Response

```json
{
  "success": false,
  "message": "This feature is not available on your current plan.",
  "feature": "recommendations.enabled"
}
```

### 10. Monthly Scan Limit Reached

#### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/scan \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "barcode": "12345678"
  }'
```

#### Sample Response

```json
{
  "success": false,
  "message": "You have reached the monthly scan limit for your current plan.",
  "feature": "scans.monthly_limit"
}
```

## Admin Web Routes

These are web/admin routes, not mobile API routes.

### Plan Management

- `GET /admin/plans`
- `GET /admin/plans/create`
- `POST /admin/plans`
- `GET /admin/plans/{plan}/edit`
- `POST /admin/plans/{plan}`
- `POST /admin/plans/{plan}/toggle-active`
- `DELETE /admin/plans/{plan}`

### Price Management

- `GET /admin/plans/{plan}/prices/create`
- `POST /admin/plans/{plan}/prices`
- `GET /admin/prices/{price}/edit`
- `POST /admin/prices/{price}`
- `POST /admin/prices/{price}/toggle-active`
- `DELETE /admin/prices/{price}`

### Subscription Administration

- `GET /admin/subscriptions`
- `GET /admin/subscriptions/{subscription}`
- `POST /admin/subscriptions/{subscription}/cancel`
- `POST /admin/subscriptions/{subscription}/refund`
- `POST /admin/users/{user}/subscription/change`

## Admin Reassignment Flow

### Immediate Paid Plan Change

1. Admin selects user.
2. Admin chooses a paid target price.
3. Admin chooses `Billing change now`.
4. Backend updates Stripe immediately.
5. Backend syncs the local subscription record.
6. User entitlements update immediately.

### End-Of-Cycle Change

1. Admin selects user.
2. Admin chooses target price.
3. Admin chooses `Billing change next cycle`.
4. Backend marks current Stripe subscription to cancel at period end.
5. Pending change metadata is stored locally.
6. When Stripe confirms subscription deletion, webhook applies the next plan.

### Manual Override

1. Admin selects user.
2. Admin chooses target plan/price.
3. Admin chooses `Manual override`.
4. Backend creates a local `system` subscription.
5. If the user currently has an active Stripe subscription, admin must explicitly cancel it for a clean override.

## React Native Expo Notes

For your current development stage:

- the mobile app can safely use the hosted Stripe checkout URL returned by `/api/v1/billing/checkout`
- after returning from Stripe, the app should call:
  - `GET /api/v1/billing/subscription`
  - or `GET /api/user`
- if the app uses deep linking, use the same scheme in checkout success/cancel URLs

Suggested client flow:

1. call `GET /api/v1/billing/plans`
2. render plan cards
3. user taps a price
4. call `POST /api/v1/billing/checkout`
5. open `checkout_url`
6. on return, refresh subscription state
7. unlock gated screens based on returned plan and usage

## Stripe Webhook Notes

Stripe updates are finalized by:

```text
POST /api/stripe/webhook
```

This webhook is responsible for:

- completing checkout-created subscriptions
- syncing Stripe subscription changes
- applying scheduled next-cycle plan changes after Stripe period-end cancellation
- updating invoice and payment metadata

## Recommended Client Handling

For mobile and frontend clients:

1. Treat `GET /api/v1/billing/plans` as the source of truth for what can be purchased.
2. Treat `GET /api/v1/billing/subscription` as the source of truth for current entitlement state.
3. Refresh subscription state after:
   - checkout return
   - app resume
   - successful cancel
4. Handle `403` feature-gating responses explicitly.
5. Display remaining scan balance when a monthly limit exists.
6. Show `pending_change` when a downgrade or reassignment is scheduled.

## Related Files

- [routes/api.php](../routes/api.php)
- [app/Http/Controllers/Api/BillingController.php](../app/Http/Controllers/Api/BillingController.php)
- [app/Http/Controllers/BillingController.php](../app/Http/Controllers/BillingController.php)
- [app/Services/SubscriptionManager.php](../app/Services/SubscriptionManager.php)
- [app/Services/StripeSubscriptionService.php](../app/Services/StripeSubscriptionService.php)
- [config/subscriptions.php](../config/subscriptions.php)
