# Mobile authentication API v1

Base path: `/api/mobile/v1/auth`. Send `Accept: application/json` and `Content-Type: application/json`.

Mobile routes are defined in `routes/api/mobile.php`, loaded by `routes/api.php`
with the shared API middleware and `/api` prefix.

## OpenAPI

Student lists and dashboard are outside the authentication prefix:
`GET /api/mobile/v1/students` and `GET /api/mobile/v1/dashboard`.
Both require a Bearer token.
The student list additionally requires `students.manage` permission and a school
assigned in the user profile or school scope. Missing either returns 403.
Query parameters cannot select another school. Parent/child and student self-service
access are not implemented and do not grant access to this school-wide list.

The OpenAPI 3.0.3 specification is in [openapi-mobile.json](openapi-mobile.json)
and is available from the running application at `GET /api/openapi.json`.
Import the file into Postman or Swagger Editor. For an imported local file,
set the server URL to your backend's absolute `/api/mobile/v1` URL.
The specification covers all 10 routes in `routes/api/mobile.php`; registration is not implemented.

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/phone/login` | Existing active account: phone and password |
| POST | `/phone/request-otp` | Request development OTP |
| POST | `/phone/verify-otp` | Verify OTP and obtain a token |
| GET | `/me` | Current profile, roles, permissions and scopes |
| POST | `/logout` | Revoke current device token |
| POST | `/logout-all` | Revoke all API tokens for current user |

## Password login

```json
{"phone":"+77016667788","password":"your-password","device_name":"iPhone"}
```

Successful login (200):

```json
{
  "token": {
    "token": "opaque-bearer-token",
    "expires_at": "2026-11-18T12:00:00+00:00",
    "token_type": "Bearer"
  },
  "user": {
    "id": 1,
    "full_name": "Example User",
    "first_name": "User",
    "last_name": "Example",
    "middle_name": null,
    "phone": "+77016667788",
    "status": "active",
    "preferred_locale": "ru",
    "roles": [],
    "permissions": [],
    "scopes": []
  }
}
```

Phones are normalized across all three phone endpoints: punctuation is removed,
11-digit numbers starting with 8 are converted to +7. Device name is optional (100 characters maximum).
Inactive accounts cannot log in. Existing tokens stop working while an account is inactive.

## OTP status

SMS delivery is not connected. Requesting OTP outside local/testing environments returns
503 and does not create a code. Do not enable a production OTP flow until a real SMS provider is integrated.

In local/testing, send `{"phone":"+77016667788"}` to `/phone/request-otp`.
The 202 response contains `status: pending_otp`, `phone`, `purpose`, `expires_at`, and `debug_code`.
Send the phone and six-digit `code` to `/phone/verify-otp`; optional `device_name` is accepted.
Only `purpose: login` is supported. Codes expire after five minutes by default and can be used once.
Requesting a new code invalidates the previous one. A new phone creates a profile with no roles
or access scopes; school access must be assigned separately. OTP never activates an inactive account.

## Authenticated requests

Use `Authorization: Bearer <token.token>` for `/me`, `/logout`, and `/logout-all`.
Logout endpoints have no body and return 204. Logout-all affects API tokens, including tokens
issued via EDS, but does not end browser sessions. Store tokens in iOS Keychain / Android secure storage,
clear personal cached data after logout, and prompt for login after 401. No refresh endpoint is provided;
token lifetime is configured with `PHONE_AUTH_TOKEN_TTL_MINUTES` (default 43200 minutes).

## Errors and limits

- 401: missing, invalid, expired or revoked token, or inactive account on protected routes.
- 403: inactive account during OTP verification.
- 422: invalid input, incorrect credentials or invalid/expired/used OTP.
- 429: authentication limit reached; respect `Retry-After`.
- 503: SMS delivery unavailable.

Phone login, OTP request and OTP verification share limits of 5 requests per minute per normalized
phone and 30 per minute per IP. Use a shared cache store for multi-instance deployments.
Validation errors use Laravel's JSON `message` / `errors` format; other errors include `message`.
Password recovery, mobile registration with school approval, phone changes and mobile EDS integration
are separate follow-up work.
