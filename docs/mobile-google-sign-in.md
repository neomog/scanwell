# Mobile Google Sign-In Flow

## Overview

This document is for the mobile developer implementing the user-facing `Continue with Google` flow in the Scanwell app.

The mobile app completes Google account selection on-device, then exchanges the Google ID token with the Scanwell backend.

Current backend endpoint:

- `POST /api/auth/google`

Base URL example:

- `https://scanwell.ohiare.com`

## Backend Behavior

When the mobile user signs in with Google:

1. The app opens the Google account chooser.
2. Google returns an ID token for the selected account.
3. The app sends that ID token to `POST /api/auth/google`.
4. The backend verifies the token and checks that its audience matches an allowed Google client ID.
5. The backend finds an existing user by email or creates a new one.
6. If the user already exists and does not yet have Google linked, the backend fills `provider`, `provider_id`, `avatar`, and `email_verified_at` where missing.
7. The backend returns a Scanwell API token plus the normalized user payload.
8. The app stores the Scanwell token securely and enters the authenticated app experience.

Important behavior for mobile:

- the backend expects a Google ID token, not just an access token
- Google-authenticated users are marked as email-verified automatically
- the response includes a Scanwell token to use for future API requests
- unlike the email/password login response, this endpoint does not return `token_type`

## Endpoint Contract

### 1. Complete Google Sign-In

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/auth/google \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "token": "GOOGLE_ID_TOKEN"
  }'
```

Request body:

- `token` required, Google ID token from the device sign-in flow
- `accessToken` optional, currently accepted by the backend but not required for account creation or login

Successful response example:

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

Notes:

- use `data.token` as the bearer token for authenticated API calls
- build the authorization header as `Authorization: Bearer YOUR_ACCESS_TOKEN`
- cache the returned `user` object to hydrate the app shell quickly after login

## User States Supported By The Backend

The current backend supports these user-facing outcomes:

- first-time Google user: a new Scanwell user is created automatically
- returning Google user: the existing account is reused and a new API token is issued
- existing email/password user with the same email: the existing account is reused and Google details are linked where missing

This means the app does not need a separate "link Google account" screen for the first release. The backend already resolves users by email.

## Mobile UI Flow

Recommended app flow:

1. Show a `Continue with Google` button on the login and registration entry points.
2. When the button is tapped, disable repeated taps and show a loading state.
3. Launch the device Google sign-in flow.
4. If the user cancels account selection, stop loading and return to the same screen without treating it as an API failure.
5. If Google returns an ID token, call `POST /api/auth/google`.
6. If the API succeeds, store `data.token` in secure storage and persist the returned `user`.
7. Update in-memory auth state and navigate to the authenticated landing screen.
8. After navigation, optionally trigger post-login work such as loading profile data, plans, preferences, and push-token registration.

Recommended first-screen behavior after success:

- returning users: go directly to the app home/dashboard
- new users: go to the app home/dashboard, then optionally show a lightweight welcome or profile-completion prompt

## Screen Requirements

### Login Screen

- render a clear `Continue with Google` action near the main sign-in controls
- keep the email/password login available as a separate path
- disable the Google button while the device flow or API exchange is running
- show a top-level error message if the backend rejects the login

### Loading State

- show progress immediately after the Google button is tapped
- keep the screen interactive enough for the user to back out only before the token exchange starts
- prevent duplicate submissions during the API request

### Success State

- do not ask the user to verify their email after Google sign-in
- display the returned avatar and name where the app supports a signed-in profile header
- treat the returned subscription block as the source of truth for plan-aware UI

## Session Handling

After a successful Google sign-in:

1. Save `data.token` in secure device storage.
2. Save the returned `user` payload in app state or local cache.
3. Use the token for all authenticated requests.
4. On app launch, if a token exists, call `GET /api/user` to rehydrate the current user.
5. If `GET /api/user` returns `401`, clear local auth state and send the user back to login.

Logout behavior:

1. Call `POST /api/logout` with the bearer token.
2. Remove the stored token and cached user data locally even if the logout request fails.
3. If the app uses push notifications, also unregister the current push token during logout.

## Error Handling

The mobile app should separate device-level Google flow failures from backend API failures.

### User Cancels Google Sign-In

Recommended behavior:

- close the Google flow
- stop loading
- keep the user on the same screen
- do not show a hard error banner

### Invalid Google Token

If the backend cannot verify the ID token, it returns `401 Unauthorized`.

Example:

```json
{
  "success": false,
  "message": "Invalid Google token"
}
```

Recommended UI:

- show a generic error such as `Google sign-in failed. Please try again.`
- allow the user to retry immediately

### Google Account Email Not Available

If the token is valid but no email is available, the backend returns `422 Unprocessable Entity`.

Example:

```json
{
  "success": false,
  "message": "Google account email not available"
}
```

Recommended UI:

- show a recoverable message telling the user to choose a different Google account
- do not create a partial local session

### Network Or Server Failure

Recommended UI:

- show a retryable error state
- preserve the current screen
- do not move into the authenticated app shell until the Scanwell token is saved successfully

Important contract note:

- other Scanwell endpoints usually return `success`, `message`, `data`, and `errors`
- this Google auth endpoint currently does not guarantee an `errors` key
- the mobile app should rely on HTTP status, `success`, and `message` for this flow

## Implementation Notes For The Mobile Team

- the app only needs to send the Google ID token to the backend
- the app should not treat a Google SDK success as a login success until the Scanwell API exchange succeeds
- a local Google session and a Scanwell API session are different things; both steps must complete
- because the backend identifies users by email, use the returned Scanwell `user` as the canonical profile after login

Generic client-side flow:

```text
Tap Google button
-> complete device Google sign-in
-> get Google ID token
-> POST /api/auth/google
-> store Scanwell token securely
-> store user payload
-> navigate into authenticated app
```

## Current Backend Caveat

There is one implementation detail the mobile team should know about before shipping native iOS Google sign-in.

The backend validation logic checks for these possible Google client IDs:

- Expo client ID
- Android client ID
- iOS client ID
- generic web client ID

However, the current backend config file only exposes:

- `GOOGLE_EXPO_CLIENT_ID`
- `GOOGLE_ANDROID_CLIENT_ID`
- `GOOGLE_CLIENT_ID`

The `GOOGLE_IOS_CLIENT_ID` config entry is currently not enabled in `config/services.php`.

Practical impact:

- Android flows should work if the Android audience matches the configured backend client ID
- Expo-based flows can work if the Expo audience matches
- a native iOS ID token whose audience is only the iOS client ID may be rejected as `Invalid Google token` until the backend config is updated

If the mobile team plans to use native iOS Google sign-in, this backend config item should be enabled before release.

## QA Checklist For Mobile

- Confirm a brand-new Google user can enter the app successfully.
- Confirm a returning Google user receives a fresh Scanwell token and enters the app.
- Confirm an existing email/password account with the same email can sign in through Google.
- Confirm canceling the Google chooser does not create an error state or partial session.
- Confirm `data.token` is stored securely and reused on app restart.
- Confirm `GET /api/user` can restore the signed-in session on cold start.
- Confirm logout clears local auth state and returns the user to login.
- Confirm a bad or expired Google ID token shows a retryable failure state.
- Confirm an account without an available email shows a recoverable message.
- Confirm protected API calls work with `Authorization: Bearer {data.token}`.
- Confirm Google-authenticated users are not blocked by email verification screens.
- Confirm Android and iOS each use a Google client ID that the backend accepts.
