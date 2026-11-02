# Parent mobile linking

All endpoints require `Authorization: Bearer <token>` and `Accept: application/json`.
Sign in and verify the account phone through the existing phone OTP flow first.
Endpoints are limited to 30 requests per minute.

1. `POST /api/mobile/v1/parents/check-phone` with `{"phone":"+77001234567"}`.
   Only the account's OTP-verified phone is allowed. Returns `found` and `parents`
   (contact IDs and names). No match: `{"found":false,"parents":[]}`.
2. `POST /api/mobile/v1/parents/check-student-iin` with `{"iin":"160101500001"}`.
   Returns `found` and `student` (`id`, `full_name`). A match requires an existing
   contact for this child with the verified phone. No match: `{"found":false,"student":null}`.
3. `POST /api/mobile/v1/parents/link-student` with `{"parent_id":1,"student_id":21}`.
   Returns `{"linked":true,"parent_id":1,"student_id":21}`. Repeat requests are safe.
   Try each contact ID from step 1 if the phone occurs on several child records;
   mismatched pairs return 404 without changing any existing relationship.

New nullable field: `parents.user_id` references the authenticated `users.id`.
The existing `parents.student_id` identifies the child and is never reassigned by
these APIs. One account can claim multiple contact rows for multiple children.
No parent contact or student is created by these endpoints. A school must first
record the parent's phone on that student's form. Archived children and records
claimed by another account cannot be found or linked. Errors: 401 unauthenticated,
403 phone not verified or foreign phone, 404 invalid link, 422 validation, 429 limit.

## Current parent and children

`GET /api/mobile/v1/parents/me` (no request body). Requires the same Bearer token
and OTP-verified phone. Returns `parent` (`user_id`, `full_name`, nullable `iin`,
`phone`) and `children` with student identity, class, school and photo URL.
Each child also includes `parent_id` identifying the linked contact record.
Only explicitly linked active students matching the account phone are returned,
without duplicates. Before linking, `children` is an empty array. Parent name and
IIN come from the first non-empty linked contacts by ID; name falls back to the
account name. Other account IDs supplied in query parameters do not change scope.
Full response examples are in `/api/openapi.json` under `/parents/me`.
