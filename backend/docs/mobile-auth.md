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


## Teacher cabinet API

- `GET /api/mobile/v1/auth/me`: `user.school_id` and `user.school_name`
  resolve the profile school or first school scope. Both are null when unassigned.
- `GET /api/mobile/v1/students?search=Name&classroom_id=123&page=1&per_page=50`:
  requires `students.manage` and school assignment. `students` remains an array;
  `meta` contains `current_page`, `per_page`, `last_page`, `total`.
  Page size defaults to 50, maximum 100; invalid pagination/filter values return 422.
  A classroom from another school returns no students. IIN and meal benefit are
  omitted for every mobile caller; IIN search is removed.
- `GET /api/mobile/v1/dashboard`: requires teacher, students.manage and a valid
  assigned school (403 otherwise). Returns data only for that school.

Current mobile routes do not expose schedules, grades, homework or attendance.
Teacher-to-class and subject assignments are not implemented. Delivery dates and
production deployment branch are not established by this repository; confirm them
with the deployment owner before mobile release.

Student list items include `photo_url` (public storage URL, or null without a photo).
The existing boolean `photo` is preserved. The URL uses the public disk URL
configured from `APP_URL`; local public storage requires `php artisan storage:link`.


## Teacher student management

All paths below are relative to `/api/mobile/v1`. Send a Bearer token and
`Accept: application/json`. These methods require both role `teacher` and
permission `students.manage`, plus a school assignment (profile or first school
scope). Missing access/assignment returns 403. The whole assigned school is
available; there is no teacher-to-class restriction yet.

- `POST /students` creates a student, returns 201 with `student`.
- `GET /students/{student}` returns 200 with editable fields in `student`.
- `PATCH /students/{student}` and `PUT /students/{student}` update supplied fields,
  preserve omitted fields, return 200 with `student`.
- `DELETE /students/{student}` archives the student, preserving related records
  and photo; returns 204 without a body.

Creation requires `first_name`, `last_name`, `school_year` (e.g. `2026-2027`).
Optional fields: `middle_name`, `iin`, `birth_date`,
`gender`, `phone`, `address`, `meal_benefit_type`, `classroom_id`, `student_number`, `language`
(`ru`/`kk`), `shift` (1/2). Optional fields may be cleared with null; first/last name and school year cannot be cleared. Validation errors return 422.
School is assigned by the server. Sending `school_id`, `benefit`, `photo` or `photo_url` with a value returns 422.
These methods do not upload photos. Other unrecognized fields are ignored.
Classrooms use the existing shared grade/letter catalog (grade > 0).

A missing student or a student outside the teacher's school returns 404, before
update validation. Detail/create/update responses include `iin` and `meal_benefit_type` for the edit form; the general student list omits them. Detail
responses include separate name fields and editable fields alongside the list
fields, including `photo_url`.

Example create body:

```json
{
  "first_name": "Dana",
  "last_name": "Student",
  "classroom_id": 12,
  "school_year": "2026-2027"
}
```


## Teacher order lists

- `GET /api/mobile/v1/orders` lists orders of students in the assigned school.
- `GET /api/mobile/v1/students/{student}/orders` lists orders of one student
  in the assigned school. Foreign/missing student: 404; no orders: empty list.

Both require role `teacher`, permission `students.manage` and school assignment
(profile or first school scope). Otherwise 403; unauthenticated requests: 401.
No class restriction is applied. These are read-only endpoints.

Response: `orders` array and `meta` (`current_page`, `per_page`, `last_page`,
`total`). Page size defaults to 50, maximum 100; ordering is order date descending,
then ID descending. Filters combine with AND:
`student_id`, `classroom_id`, `school_year`, `status` (`created`, `issued`,
`completed`, `failed`), `transaction_status` (`0`/`1`), `search` (name tokens),
`order_date`, `date_from`, `date_to` (inclusive, `YYYY-MM-DD`). Invalid filters
return 422. The path student ID cannot be overridden by query parameters.
Classroom/year come from the historical order, not the student's current class.

Each order includes `id`, `student: {id, full_name}`, `classroom_id`, `classroom`,
`school_year`, `dish: {id, name}` (or null), `order_date`, `order_time`, `status`,
`completed_at`, `transaction_status` (boolean or null). Student IIN, benefit,
creator details and upstream transaction error text are not included.

Examples:

```text
GET /api/mobile/v1/orders?date_from=2026-10-01&date_to=2026-10-22&page=1&per_page=50
GET /api/mobile/v1/students/123/orders?school_year=2026-2027
```


Teacher create/edit fields now match the website form. `iin` is optional, unique,
max 12 characters. `meal_benefit_type`: `susn`, `voucher`, `paid`; a non-null changed
type appends benefit history, null/omitted preserves it (same as the website).
`gender`: `male`/`female`; phone whitespace is removed. On create, birth date and
gender are derived from IIN if not supplied and derivation is possible. On update,
these fields are edited independently, as on the website. No schema migration is
needed. Photo upload is a separate mobile endpoint, documented below.

First and last name are required for website create/update and mobile create.
Mobile partial updates may omit names but cannot clear them. Middle name is optional.


## Student photos

`POST /api/mobile/v1/students/{student}/photo`: upload/replace using
`multipart/form-data`, required file field `photo_file`, image up to 2 MiB
(SVG rejected). For a new student, create the record first and use its ID.
Send Bearer token and `Accept: application/json`; let the HTTP client set the
multipart boundary. No base64 payload is needed.

`DELETE /api/mobile/v1/students/{student}/photo`: removes the photo; repeated
requests succeed. Both endpoints require `teacher`, `students.manage` and school
assignment. Other schools/missing student: 404; missing access: 403; invalid or
missing upload: 422. Replacement removes the old file, and resets FaceID photo
sync state. Responses are 200 with `student: {id, photo, photo_url}`; removal
returns `photo: false, photo_url: null`. The list/detail photo URL updates too.


## Student references, search and archive

- `GET /api/mobile/v1/classrooms`: `classrooms: [{id, grade, letter, full_name}]`.
  Shared catalog of grades > 0 sorted by grade/letter, usable for forms and filters.
- `GET /api/mobile/v1/students/options`: `default_school_year`, `school_years`,
  `school_years_are_suggestions`, `languages`, `shifts`, `genders`,
  `meal_benefit_types`, `required_fields`, `photo_max_kb`.
  Choices contain `value` and `label: {ru, kk}`. Shift values are integers.
  School years include current academic year (September boundary) and stored
  years for the assigned school. They are suggestions, not a validation whitelist.
  Both reference endpoints require teacher, students.manage and school assignment.
- `GET /students?search=Last%20First`: matches all name/number tokens, ignoring
  repeated whitespace and token order, within the assigned school.
- `DELETE /students/{student}`: soft archive, repeated requests return 204.
  `GET /students` excludes archived records; `?archived=1` lists only archived
  records with the same pagination, search and school boundary.
- `POST /students/{student}/restore`: restore, returns 200 with `student`.
  Previous status, photo, benefit and order history are preserved. Restore of an
  already active record succeeds. Foreign-school/missing records return 404.
- `GET /students/{student}` includes archived students and `archived_at`.
  Editing and photo changes on archived records return 404 until restored.
  Order-list endpoints retain archived student history; new orders are blocked.

Deploy requires migration `2026_10_22_100000_add_deleted_at_to_students_table`
(`php artisan migrate --force` in the deployment workflow). The Student model now
uses soft deletes, so website deletions also archive records. Hard deletion is not
exposed by the mobile API. An archived IIN remains reserved for that student;
restore the record instead of creating a duplicate.


## Select students and create orders

1. When the mobile date changes, fetch
   `GET /api/mobile/v1/students?order_date=2026-10-22` (plus normal search,
   classroom and pagination). Response echoes `order_date`; every student contains
   `has_order`, `order_id`, `order_status`. If `has_order` is true, show the student
   checked and disabled. An order of ANY status counts, including failed orders.
   Without `order_date`, today in the application timezone is used. Ignore stale
   responses for an older selected date. StudentDetail schema is unchanged.
2. Submit only newly selected IDs using `POST /api/mobile/v1/orders`:

```json
{
  "student_ids": [123, 124],
  "order_date": "2026-10-22",
  "order_time": "12:30",
  "school_year": "2026-2027"
}
```

`student_ids` must be a nonempty array of distinct IDs. `order_time` is optional. Requires
teacher, students.manage and school assignment. At least one foreign, missing or
archived student rejects the WHOLE batch with 403 and creates nothing. Invalid
fields, future date/time, dates outside school year and general blocked school
days return 422. Existing calendar and eligibility services are reused; historical
enrollment must belong to the assigned school. Grades 1?4 follow existing free
meal rules; older pupils require a qualifying benefit for the selected date.

Response 200: `order_date`, `created_count`, `already_exists_count`, `skipped_count`,
`results: [{student_id, result, order_id, reason}]`. `result` is `created`,
`already_exists` or `skipped`; `reason` is null, `not_eligible` or
`blocked_school_day`. Creation is synchronous; social-wallet processing is queued
only for newly created orders after commit. An existing order is never changed
or re-dispatched by this API. Results are sorted by student ID.

3. Refresh the date's student list after submitting; newly ordered pupils become
   checked/disabled too. Server row locks and the unique student/date constraint
   protect against repeat/concurrent submissions even if the UI sends checked IDs.
   Do not treat `skipped` as success; leave those students unmarked.


## Soft-delete an order

`DELETE /api/mobile/v1/orders/{order}` requires teacher, students.manage and
school assignment; foreign/missing order: 404, missing access: 403. Returns 204,
including on repeat deletion. The row is preserved with `deleted_at`; transaction
status and other data remain unchanged. This also applies to website deletion
through the shared Order model. No mobile hard-delete endpoint is exposed.

Deleted orders are hidden from normal lists/dashboard and do not set `has_order`.
Refresh `/students?order_date=...` after deleting to uncheck/unlock the student.
A new active order for the same date is allowed; uniqueness applies to active
orders only. It creates a new record and follows the normal transaction flow.
Deleting does not reverse an external social-wallet transaction. Queued jobs
skip deleted orders when starting; external requests already in flight cannot
be cancelled by this endpoint.

Deploy requires migration `2026_10_22_110000_add_soft_deletes_to_orders_table`.
PostgreSQL/SQLite use a partial unique index; MySQL/MariaDB use a generated active
key. Migration rollback refuses duplicate student/date history to avoid data loss.


## Teacher school dashboard

`GET /api/mobile/v1/dashboard?date_from=2026-10-01&date_to=2026-10-22`
requires `teacher` and `students.manage`, with a resolvable assigned school
(profile or first school scope). The standard teacher role can now access it.
Query scope/school/district/region parameters are ignored. Additional operator or
super-admin roles never broaden this mobile endpoint. Only the teacher's school
is used for summaries, charts and daily order rows. Missing school: 403.
The web dashboard retains its existing role-based scope behavior.


## One format for mobile order creation

`POST /api/mobile/v1/orders` always accepts `student_ids`, `order_date`,
optional `school_year` and optional `order_time`. There is no target_type/classroom_ids
mode in the mobile API; nonempty values for these fields return 422. Website
class-selection behavior remains supported through the shared service.

The mobile class checkbox selects every available student ID in that class.
Fetch ALL pages of `/students?classroom_id=12&order_date=2026-10-22` before
submitting a whole class, not just its loaded page. Individually selected students
and whole-class selections are combined into one unique student_ids array.
Students with has_order=true remain checked/disabled and need not be submitted.
There is no 100-student creation limit; list pagination still has per_page <= 100.
Server school, archive, enrollment, eligibility and duplicate checks still apply.


For mobile `POST /orders`, `school_year` is now optional. When omitted, the
server derives it from order_date using September 1 as the boundary, independently
of the student's current school year. Explicit valid school_year is still accepted
for compatibility, but mismatching date/year is rejected. Website fields are unchanged.

```json
{
  "student_ids": [123, 124],
  "order_date": "2026-10-22"
}
```

## Mobile attendance

`GET /api/mobile/v1/attendance?date=2026-10-28&per_page=15`
requires a Bearer token and teacher or director role with an assigned school
(profile or first school scope). Other roles return 403; unauthenticated requests return 401.

Optional filters: `date` (Y-m-d, defaults to today), `search` (name, max 100),
`classroom` (class ID), `direction` (entry/exit), `page`, `per_page` (1?100).
Response: `date`, `stats` (total/inside/outside/absent), `latest`, `classrooms`,
`events`, and `meta` (current_page/per_page/last_page/total).
Each event contains id, student (id/full_name/classroom_id/classroom),
direction (nullable for legacy scans), create_time (ISO 8601).

Matches the web attendance page: list shows the first scan per student that day,
or first matching direction scan. Stats and latest are school-wide, independent
of list filters; the last scan by event time and ID determines inside/outside.
Legacy null direction counts as inside. No scan that day means absent.
Archived students and events with a different school BIN are excluded.

## Mobile library

Base: `/api/mobile/v1/library`, Bearer token and `Accept: application/json`.
Teacher can read; director/library can read and write. An assigned school is
required (profile or first school scope). Client school_id never broadens access.
The book catalog is global; school stock, loans and mutations are scoped.
Standalone super_admin has no mobile access.

- `GET /books?search=Math&barcode=0012345&page=1&per_page=20` ? shared catalog.
- `GET /books/{book}` ? book and stock in assigned school (stock may be null).
- `POST /books`, `PUT /books/{book}` ? shared catalog create/update; require
  barcode, title, literature_type (educational/fiction). Optional author, publisher,
  publication_year, grade, language, part, subject. Edits affect every school.
- `GET /stocks` ? school inventory, search/barcode/page/per_page.
- `POST /stocks` ? barcode plus quantity to add (default 1), OR total_quantity
  to set. Cannot set below outstanding copies.
- `GET /loans` ? search, barcode, student_id, status (active/returned/overdue),
  from/to (inclusive issue dates), page/per_page.
- `GET /reports` ? same loan filters; school-wide totals and overdue, filtered
  period issued/returned quantities. Returns include all returns on matching loans.
- `GET /lookup?type=book&q=0012345&exact=1` ? school books; type=student
  supports name or student QR code. Maximum 20 results.
- `POST /operations` ? atomic issue/return batch, one copy per barcode occurrence.

List responses use books/stocks/loans plus meta(current_page, per_page,
last_page, total). Default per_page=20, maximum=100. OpenAPI includes all fields,
request/response examples and cURL for every endpoint.

```json
{
  "student_code": "student:123",
  "barcodes": ["0012345", "0012345"],
  "mode": "issue",
  "due_at": "2026-12-01"
}
```

Student codes: numeric ID, `student:123`, or JSON encoded as a string with
student_id. For returns use mode=return and omit due_at. Repeated barcodes
represent multiple copies. Any failure rolls back the entire batch.
Operations require no web session/token. Each successful POST is a new mutation;
after an ambiguous network result check loans before repeating it.
Overdue counts loan rows; returns are allocated to oldest outstanding loans.
Spreadsheet import/export remain web features. No new database migration is required.

## Student attendance history

`GET /api/mobile/v1/students/123/attendance?date_from=2026-10-01&date_to=2026-10-28`

Bearer token; teacher/director with assigned school. Missing or foreign student: 404.
Archived students within the assigned school retain history access. Only events
matching the student's ID and assigned school's BIN are returned.

Optional filters: date (one day), date_from/date_to (inclusive, either may be
omitted), direction (entry/exit), page, per_page (default 50, max 100). Combined
date filters intersect. No dates means all available history. Dates use app timezone.
Unlike the daily attendance list, history includes every scan, ordered by event
time descending and ID descending. Legacy null direction is preserved.

Response: student(id/full_name/classroom_id/classroom), events(id/direction/
create_time in ISO 8601, nullable if terminal omitted timestamp), and
meta(current_page/per_page/last_page/total). Empty history returns events=[].
OpenAPI includes the response schema, JSON example, query examples and cURL.

## Student book history

`GET /api/mobile/v1/students/123/books?from=2026-10-01&to=2026-10-28&per_page=20`

Bearer authentication; teacher/director/library with assigned school. Foreign
or missing student returns 404. Archived students retain school-scoped access.
Client school_id/student_id cannot redirect this history to another student/school.

Filters: search (book title or partial barcode), barcode (exact), status
(active/returned/overdue), from/to (inclusive issue dates, either independently),
page, per_page (default 20, max 100). No dates means all available history.

Response: student(id/full_name/classroom_id/classroom), loans, meta.
Loans have the same fields as `/library/loans`, plus returns[] containing
id, quantity, returned_at. quantity/returned/outstanding show issued, returned
and remaining copies. Every individual return on a matching loan is included,
regardless of return date, oldest first. Database timestamps use app timezone.
Loans sort by issued_at descending, then ID descending. Empty history: loans=[].
OpenAPI includes full schemas, JSON response example and a cURL request example.
