# API Conventions

Rules every `/api/v1` endpoint follows. Source: project documentation, sections 7, 8, 9 and 14.

## 1. Versioning and URLs

- All routes live under `/api/v1/...` from day one (`routes/api.php`, prefix `v1`).
- Resource names are plural, lowercase, kebab-case: `/customers`, `/order-items`.
- Standard resource routes:

| Method | URL | Action | Success status |
|---|---|---|---|
| GET | `/api/v1/customers` | list (paginated) | 200 |
| POST | `/api/v1/customers` | create | 201 |
| GET | `/api/v1/customers/{customer}` | show | 200 |
| PUT/PATCH | `/api/v1/customers/{customer}` | update | 200 |
| DELETE | `/api/v1/customers/{customer}` | delete | 200 (or 204 with no body) |

- Non-CRUD actions are sub-resources with a verb: `POST /orders/{order}/confirm`, `POST /orders/{order}/cancel`.
- Route model binding uses numeric IDs. Never expose internal columns that are not in the API Resource.
- Route names: `api.v1.customers.index`, and so on.

## 2. Authentication

- Laravel Sanctum, `Authorization: Bearer <token>` (SPA cookie auth may be added later for the Inertia app).
- Every route except register, login, password reset and webhooks sits behind `auth:sanctum`.
- Webhooks (Stripe) are unauthenticated but must verify the provider signature.

## 3. Tenant (organization) context

- Every tenant-owned request runs inside exactly one organization.
- The client sends `X-Organization-Id: <id>`. If the header is missing, the user's `current_organization_id` is used.
- Middleware checks the user is a member of that organization (`organization_users`) and binds the organization for the request. Otherwise return `403`.
- Tenant-owned models use a global scope on `organization_id`. Never trust `organization_id` from the request body; it is set server-side.
- A record from another organization returns `404` (not `403`), so its existence is not revealed.

## 4. Response envelope

Success:

```json
{
  "success": true,
  "message": "Customer created successfully.",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Unable to create customer.",
  "errors": {}
}
```

- `data` is an Eloquent API Resource (single) or a Resource Collection (list). No raw models or arrays.
- `errors` is an object keyed by field for validation errors (`{"email": ["The email has already been taken."]}`). For non-validation errors it may hold `{"code": "..."}`.
- `message` is always a human-readable sentence ending in a period.
- Build responses through one helper (`App\Support\ApiResponse`: `success()`, `error()`, `paginated()`) so the shape can't drift.

### Paginated lists

```json
{
  "success": true,
  "message": "Customers retrieved successfully.",
  "data": [],
  "meta": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

## 5. HTTP status codes

| Code | When |
|---|---|
| 200 | Successful read, update or action |
| 201 | Resource created |
| 204 | Success with no body (optional for delete) |
| 401 | Missing or invalid token |
| 403 | Authenticated but not a member, or lacks the permission |
| 404 | Not found, including records from another organization |
| 409 | Conflict, such as an invalid state transition or duplicate idempotency key with a different payload |
| 422 | Validation failed |
| 429 | Rate limit exceeded (`Retry-After` header) |
| 500 | Unexpected error (generic message, details only in logs) |

Exceptions are turned into the error envelope in `bootstrap/app.php` (`withExceptions`) for any `api/*` request.

## 6. Validation and authorization

- Validation lives in Form Requests, one per action (`StoreCustomerRequest`, `UpdateCustomerRequest`). No inline `validate()` in controllers.
- Authorization lives in Policies, called with `$this->authorize()` or `Gate::authorize()`. Checks run in this order: authenticated, organization member, permission (see `roles-and-permissions.md`), resource ownership (same organization).
- Controllers stay thin: validate, authorize, call an Action or Service, return a Resource.
- Mass assignment: every model defines `$fillable` (never `$guarded = []`).

## 7. Listing: search, filter, sort, paginate

Query string conventions for every list endpoint:

| Param | Example | Notes |
|---|---|---|
| `page` | `?page=2` | 1-based |
| `per_page` | `?per_page=25` | default 15, max 100 |
| `search` | `?search=acme` | matches the fields the resource documents |
| `filter[field]` | `?filter[status]=active` | only whitelisted fields |
| `sort` | `?sort=-created_at,name` | `-` prefix means descending; only whitelisted fields |
| `include` | `?include=orders` | only whitelisted relations, eager loaded to avoid N+1 |
| `from`, `to` | `?from=2026-01-01&to=2026-01-31` | date range on the resource's date field |

Unknown filter or sort fields return `422`.

## 8. Data formats

- JSON keys are `snake_case`.
- Dates and times are ISO 8601 in UTC (`2026-09-21T09:17:16Z`). The client converts to the organization timezone.
- Money is sent as an integer in the smallest currency unit (cents) plus the `currency` code (`"price": 1999, "currency": "USD"`). No floats.
- Enums are lowercase strings (`"status": "pending"`).
- Empty values are `null`, never `""`.

## 9. Idempotency and concurrency

- `POST` endpoints that move money or create orders and payments accept an `Idempotency-Key` header. The same key with the same payload returns the original response. The same key with a different payload returns `409`.
- State changes (orders, payments, subscriptions) go through explicit transitions in an Action inside a database transaction. Invalid transitions return `409`.
- Payment status is never taken from the client. It comes only from the provider webhook or a server-side API call.

## 10. Rate limiting

| Group | Limit |
|---|---|
| Authentication endpoints (login, register, password reset) | 5 per minute per IP and email |
| General API | 60 per minute per user |
| AI endpoints (`/ai/*`) | 20 per minute per user, plus the subscription AI quota |

## 11. AI endpoints

- `POST /api/v1/ai/chat` runs inside the organization context, and the AI never queries the database directly. It calls validated tool handlers only.
- Long-running AI work is queued and returns `202` with a status resource to poll (or a broadcast event later).
- An AI failure returns the standard error envelope and must not affect core business endpoints.

## 12. Definition of done for an endpoint

- Validation added (Form Request)
- Authorization verified (Policy)
- Tenant isolation verified and tested (organization A cannot reach organization B)
- Error handling returns the envelope
- Feature tests written (success, validation, 401, 403, cross-tenant 404)
- API documentation updated
- Logging added where required
