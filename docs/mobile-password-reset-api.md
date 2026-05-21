# Mobile Password Reset API

## Overview

This document is for the mobile developer implementing forgot-password and reset-password in the Scanwell app.

The API exposes two public endpoints:

- `POST /api/forgot-password`
- `POST /api/reset-password`

All responses use the standard API envelope:

- `success`
- `message`
- `data`
- `errors`

Base URL example:

- `https://scanwell.ohiare.com`

## Backend Behavior

When the mobile user requests a password reset:

1. The app calls `POST /api/forgot-password`.
2. The backend emails the user a reset link.
3. That email link should open the mobile app using the configured mobile deep link base.
4. The app reads `token` and `email` from the deep link.
5. The app submits those values plus the new password to `POST /api/reset-password`.

The backend supports a configurable mobile link:

- `MOBILE_RESET_PASSWORD_URL=scanwell://reset-password`

Example generated link:

```text
scanwell://reset-password?token=RESET_TOKEN&email=user%40example.com
```

If mobile deep linking is not configured, the backend falls back to the normal web reset page.

## 1. Forgot Password

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/forgot-password \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "email": "user@example.com"
  }'
```

Request body:

- `email` required, valid email

Successful response example:

```json
{
  "success": true,
  "message": "We have emailed your password reset link.",
  "data": null,
  "errors": null
}
```

Notes for mobile:

- Always show a generic success state when the API returns `success: true`.
- Do not tell the user whether the email exists beyond what the API returns.
- The user completes the flow from the email they receive.

## 2. Reset Password

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/reset-password \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "token": "RESET_TOKEN",
    "email": "user@example.com",
    "password": "new-password-123",
    "password_confirmation": "new-password-123"
  }'
```

Request body:

- `token` required
- `email` required, valid email
- `password` required
- `password_confirmation` required and must match `password`

Successful response example:

```json
{
  "success": true,
  "message": "Your password has been reset.",
  "data": null,
  "errors": null
}
```

Important backend behavior:

- the reset token is consumed
- the user password is updated
- all existing Sanctum access tokens for that user are revoked

Because existing access tokens are revoked, the mobile app should redirect the user to the login screen after a successful reset.

## Validation Error Example

If required fields are missing or passwords do not match, the API returns `422 Unprocessable Entity`.

```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "password": [
      "The password field confirmation does not match."
    ]
  }
}
```

## Invalid Or Expired Token Example

If the token is invalid or expired, the API returns `400 Bad Request`.

```json
{
  "success": false,
  "message": "This password reset token is invalid.",
  "data": null,
  "errors": null
}
```

## Mobile UI Flow

Recommended app flow:

1. Add a `Forgot password` action on the login screen.
2. On submit, call `POST /api/forgot-password`.
3. Show a confirmation screen telling the user to check their email.
4. Register a deep link route in the mobile app for the backend reset URL base, for example `scanwell://reset-password`.
5. When the app opens from the email link, read `token` and `email` from the query string.
6. Open a `Create new password` screen.
7. Submit the new password form to `POST /api/reset-password`.
8. On success, show a short success message and take the user to the login screen.

## UI Requirements

- The reset-password screen must include:
- `email`
- `new password`
- `confirm new password`

- The email field should be prefilled from the deep link and usually read-only.
- The submit button should be disabled while the request is in progress.
- If the API returns `422`, render field errors from `errors`.
- If the API returns `400`, show the message as a top-level error and let the user restart the flow.

## Recommended Deep Link Handling

Deep link query params expected by the app:

- `token`
- `email`

Example:

```text
scanwell://reset-password?token=abc123&email=user%40example.com
```

If the app opens without either value:

- show an invalid-link message
- send the user back to the forgot-password flow

## QA Checklist For Mobile

- Request reset with a valid email and confirm success response.
- Confirm the email link opens the app, not the browser, when deep linking is configured.
- Confirm `token` and `email` are extracted from the link.
- Confirm matching passwords reset successfully.
- Confirm mismatched passwords show field validation errors.
- Confirm an invalid or expired token shows a recoverable error state.
- Confirm the user is sent back to login after success.
- Confirm any previously logged-in device is forced to log in again after the reset.
