# Leaderboard Documentation

## Summary

Scanwell exposes contributor leaderboard data in two places:

- the admin web leaderboard at `/admin/leaderboard`
- the authenticated API endpoint at `GET /api/v1/contributions/leaderboard`

Both now derive contributor ranking from `product_contributions` history instead of relying only on cached user counters.

That means the leaderboard includes:

- historical approved contributions
- rejected-only contributors
- total submission counts
- most recent contribution activity

## Scoring Rules

Leaderboard reputation is calculated from contribution moderation outcomes using `config/contributions.php`.

Current point values:

- approved `add`: `+25`
- approved `update`: `+15`
- approved `correct`: `+12`
- approved `report_issue`: `+8`
- rejected `add`: `-3`
- rejected `update`: `-2`
- rejected `correct`: `-2`
- rejected `report_issue`: `0`

Displayed reputation is never shown below `0`.

Current levels:

- `Scout`: `0+`
- `Verifier`: `50+`
- `Curator`: `150+`
- `Guardian`: `300+`
- `Steward`: `600+`

## Admin Web Leaderboard

### Route

```text
GET /admin/leaderboard
```

### Access

- requires a logged-in admin user

### Supported query parameters

- `search`
  Filters by contributor name or email.
- `level`
  Filters by derived level name such as `Scout` or `Verifier`.
- `sort`
  Supported values:
  - `reputation`
  - `approved`
  - `recent`
  - `rejected`

### Example URLs

```text
/admin/leaderboard
/admin/leaderboard?sort=approved
/admin/leaderboard?level=Verifier&sort=rejected
/admin/leaderboard?search=mayor
```

### Columns shown in the UI

- rank
- contributor
- level
- points
- approved
- rejected
- total submissions
- last contribution

## Leaderboard API

### Endpoint

```text
GET /api/v1/contributions/leaderboard
```

### Auth

This endpoint is protected by Sanctum authentication.

Required headers:

```text
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

### Query parameters

- `per_page`
  Optional. Default comes from `config('contributions.leaderboard_limit')`.
  Maximum is `50`.

### Response shape

The endpoint returns:

- `success`
- `data`
- `meta`

Each item in `data` contains:

- `id`
- `name`
- `avatar`
- `reputation_points`
- `approved_contributions_count`
- `rejected_contributions_count`
- `level`

## cURL Example

```bash
curl --request GET \
  --url "https://scanwell.ohiare.com/api/v1/contributions/leaderboard?per_page=3" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN"
```

## Sample Response

```json
{
  "success": true,
  "data": [
    {
      "id": "b7af8d15-6f38-47df-87b0-0195b77fbd01",
      "name": "Mayor",
      "avatar": "https://cdn.example.com/avatars/mayor.png",
      "reputation_points": 52,
      "approved_contributions_count": 3,
      "rejected_contributions_count": 1,
      "level": "Verifier"
    },
    {
      "id": "8db85a06-b7e3-499d-b4f5-9831d4d3285f",
      "name": "Amina Bello",
      "avatar": null,
      "reputation_points": 25,
      "approved_contributions_count": 1,
      "rejected_contributions_count": 0,
      "level": "Scout"
    },
    {
      "id": "6872be6a-a9e9-44a0-8de2-26078465a1db",
      "name": "Rejected Only User",
      "avatar": null,
      "reputation_points": 0,
      "approved_contributions_count": 0,
      "rejected_contributions_count": 2,
      "level": "Scout"
    }
  ],
  "meta": {
    "total": 18,
    "per_page": 3,
    "current_page": 1,
    "last_page": 6
  }
}
```

## Notes For Clients

- The leaderboard API is intentionally lightweight and does not include email addresses.
- The admin web leaderboard contains richer operational fields like total submissions and last activity.
- If historical contributions already exist, leaderboard ranking is rebuilt from contribution records, so contributors do not need fresh moderation activity to appear.
