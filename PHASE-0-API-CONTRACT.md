# Phase 0 — API Contract and Database Gap Audit

Status: Complete

Scope: Backend API, database, authentication, authorization, and contract design only. No UI/frontend changes are included in this phase.

## 1. Contract rules

- Use the existing frontend field names and values.
- Do not rename or remove an existing field without approval.
- Use `snake_case` for JSON fields.
- Preserve existing amount fields in paisa/minor units where already defined.
- Use a consistent response envelope.
- Keep visual frontend behavior unchanged.

## 2. Existing API contract

### Authentication

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/v1/auth/login` | Unified admin/owner login |
| GET | `/api/v1/auth/me` | Current authenticated profile |
| POST | `/api/v1/auth/logout` | Revoke current authentication |
| POST | `/api/v1/owner/auth/login` | Owner login |
| GET | `/api/v1/owner/auth/me` | Current owner profile |
| POST | `/api/v1/owner/auth/password` | Change owner password |
| POST | `/api/v1/owner/auth/logout` | Owner logout |
| POST | `/api/v1/admin/auth/login` | Platform admin login |
| GET | `/api/v1/admin/auth/me` | Current platform admin profile |
| POST | `/api/v1/admin/auth/logout` | Platform admin logout |

### Refresh token addition

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/v1/auth/refresh` | Issue a new access token |
| POST | `/api/v1/owner/auth/refresh` | Refresh owner authentication |
| POST | `/api/v1/admin/auth/refresh` | Refresh admin authentication |

Refresh-token rules:

- Use an HttpOnly, Secure cookie.
- Store only a hash of the refresh token in the database.
- Rotate the refresh token on every successful refresh.
- Revoke the previous token after rotation.
- Revoke the active refresh token on logout.
- Expired or revoked refresh tokens require login again.
- No failed-login rate limit is included.

## 3. Existing admin API

### Businesses

- `GET /api/v1/admin/businesses`
- `POST /api/v1/admin/businesses`
- `GET /api/v1/admin/businesses/{id}`
- `PATCH /api/v1/admin/businesses/{id}`
- `DELETE /api/v1/admin/businesses/{id}`
- `GET /api/v1/admin/businesses/owners`
- `POST /api/v1/admin/businesses/{id}/credential-reissue`
- `POST /api/v1/admin/credential-deliveries/{id}/retry`

Existing business fields:

```text
name, slug, phone, email, address, district_city,
owner_name, owner_username, owner_email, owner_phone,
onboarding_plan_id, logo
```

### Plans

- `GET /api/v1/admin/plans`
- `POST /api/v1/admin/plans`
- `GET /api/v1/admin/plans/{id}`
- `PUT /api/v1/admin/plans/{id}`
- `POST /api/v1/admin/plans/{id}/publish`
- `POST /api/v1/admin/plans/{id}/archive`

Existing plan fields:

```text
name, slug, description, monthly_price_paisa,
yearly_price_paisa, modules, max_turfs,
max_branches, max_staff
```

### Business subscriptions and payments

- `GET /api/v1/admin/subscription-plans/published`
- `GET /api/v1/admin/businesses/{id}/subscription`
- `POST /api/v1/admin/businesses/{id}/subscription/payments`
- `POST /api/v1/admin/businesses/{id}/subscription/complete-payment`
- `GET /api/v1/admin/subscription-payments/pending`
- `POST /api/v1/admin/subscription-payments/{id}/verify`
- `POST /api/v1/admin/subscription-payments/{id}/reject`

Existing payment fields:

```text
subscription_plan_version_id, billing_cycle, billing_periods,
discount_type, discount_value_basis_points, payment_method,
transaction_reference, payment_date, payment_received_confirmation
```

## 4. Missing owner API modules

These modules are required by the existing frontend routes but are not currently represented in `api/routes/api.php`.

### Turfs

- `GET /api/v1/owner/turfs`
- `POST /api/v1/owner/turfs`
- `GET /api/v1/owner/turfs/{id}`
- `PATCH /api/v1/owner/turfs/{id}`
- `DELETE /api/v1/owner/turfs/{id}`
- `POST /api/v1/owner/turfs/{id}/status`
- `GET /api/v1/owner/turfs/{id}/slots`

Frontend field areas:

```text
name, sport_type, format, player_capacity, location_label,
feature_image, regular_price, peak_hour_price, peak_hours,
applicable_days, opening_time, closing_time, slot_duration,
buffer_between_slots, break_time, closed_days, status
```

### Bookings and availability

- `GET /api/v1/owner/bookings`
- `POST /api/v1/owner/bookings`
- `GET /api/v1/owner/bookings/{id}`
- `PATCH /api/v1/owner/bookings/{id}`
- `POST /api/v1/owner/bookings/{id}/confirm-payment`
- `POST /api/v1/owner/bookings/{id}/reschedule`
- `POST /api/v1/owner/bookings/{id}/cancel`
- `GET /api/v1/owner/availability`
- `GET /api/v1/owner/available-slots`

Booking rules:

- Prevent duplicate bookings for the same turf/date/slot.
- Create, slot lock, and payment state update in one transaction where applicable.
- Rescheduling releases the old slot and reserves the new slot atomically.
- Cancellation releases the slot.

### Customers

- `GET /api/v1/owner/customers`
- `POST /api/v1/owner/customers`
- `GET /api/v1/owner/customers/{id}`
- `PATCH /api/v1/owner/customers/{id}`
- `GET /api/v1/owner/customers/{id}/bookings`
- `GET /api/v1/owner/customers/{id}/payments`

### Staff and permissions

- `GET /api/v1/owner/staff`
- `POST /api/v1/owner/staff`
- `GET /api/v1/owner/staff/{id}`
- `PATCH /api/v1/owner/staff/{id}`
- `POST /api/v1/owner/staff/{id}/activate`
- `POST /api/v1/owner/staff/{id}/deactivate`
- `GET /api/v1/owner/roles`
- `GET /api/v1/owner/roles/{id}/permissions`
- `PUT /api/v1/owner/roles/{id}/permissions`

### Owner payments and subscriptions

- `GET /api/v1/owner/payments`
- `GET /api/v1/owner/payments/{id}`
- `POST /api/v1/owner/payments`
- `POST /api/v1/owner/payments/{id}/refund`
- `GET /api/v1/owner/payments/summary`
- `GET /api/v1/owner/subscription`
- `GET /api/v1/owner/subscription/plans`
- `POST /api/v1/owner/subscription/change-plan`
- `POST /api/v1/owner/subscription/pause`
- `POST /api/v1/owner/subscription/cancel`
- `GET /api/v1/owner/subscription/history`

### Reports, settings, and notifications

- `GET /api/v1/owner/reports/summary`
- `GET /api/v1/owner/reports/revenue`
- `GET /api/v1/owner/reports/bookings`
- `GET /api/v1/owner/reports/turfs`
- `GET /api/v1/admin/reports/summary`
- `GET /api/v1/admin/reports/revenue`
- `GET /api/v1/admin/reports/businesses`
- `GET /api/v1/owner/settings`
- `PUT /api/v1/owner/settings`
- `POST /api/v1/owner/settings/logo`
- `GET /api/v1/notifications`
- `GET /api/v1/notifications/history`
- `POST /api/v1/notifications/{id}/read`
- `POST /api/v1/notifications/read-all`

## 5. Authorization model

Every protected request must verify:

1. The user is authenticated.
2. The user has the required role or permission.
3. The user can access the requested business/resource.

Base roles:

- `platform-admin`
- `business-owner`
- `staff`
- `customer`

Examples:

- A business owner cannot read another business owner's turf.
- Staff can manage only assigned turfs.
- Only platform admins can verify or reject subscription payments.
- Only an owner with permission can manage staff or permissions.

## 6. Database gap list

Existing migrations cover identity, platform admins, businesses, owners, plans, subscriptions, payments, credential delivery, and audit history.

New domain areas still require schema design:

- `refresh_tokens`
- `turfs`
- `turf_operating_hours`
- `turf_peak_hours`
- `turf_closed_days`
- `customers`
- `bookings`
- `booking_status_histories`
- `booking_payments`
- `staff`
- `roles`
- `permissions`
- `role_permissions`
- `staff_turf_access`
- `notifications`

Required database protections:

- Foreign keys for all business-owned records.
- Indexes for business, status, date, and transaction lookups.
- Unique constraint for a turf/date/slot booking lock.
- Soft delete where historical records must remain available.
- Audit log entries for sensitive state changes.
- Transactions for booking, payment, and subscription state changes.

## 7. Standard response format

Success:

```json
{
  "data": {},
  "message": "Success"
}
```

Validation error:

```json
{
  "message": "Validation failed",
  "errors": {
    "field_name": ["The field name is required."]
  }
}
```

## 8. Implementation order after Phase 0

1. Refresh-token authentication and authorization foundation.
2. Existing admin API contract corrections.
3. Turf and customer APIs.
4. Booking and availability APIs.
5. Staff and permissions APIs.
6. Owner payments and subscriptions.
7. Reports, settings, and notifications.
8. Feature, authorization, transaction, and integration tests.

## 9. Approved product decisions

These decisions were confirmed during the Phase 0 review:

- Refresh tokens will be used.
- Access and refresh tokens will use HttpOnly and Secure cookies.
- Refresh tokens will be hashed in the database and rotated on refresh.
- Failed-login rate limiting is excluded.
- Authentication roles are `platform-admin`, `business-owner`, `staff`, and `customer`.
- Customer is included in the backend design now, even if the customer UI is delivered later.
- A business owner can manage multiple businesses.
- A staff member belongs to a business and can access multiple turfs within that business.
- A customer can book turfs across multiple businesses.
- Booking statuses are `pending`, `confirmed`, `cancelled`, `completed`, and `refunded`.
- Booking cancellation requires a cancellation reason.
- Payment methods are `SSLCommerz`, `Cash`, and `Other`.
- Platform timezone is `Asia/Dhaka`.
- Platform currency is `BDT`.
- Business, turf, customer, and staff records use soft deletes.
- Notifications are persisted in the database with read/unread state and history.
- Entity identifiers will use UUIDs.
- Access token lifetime is 1 hour; refresh token lifetime is 30 days.

## 10. Phase 0 completion checklist

- [x] Frontend route inventory completed.
- [x] Frontend API-connected areas identified.
- [x] Frontend-only/mock owner areas identified.
- [x] Existing admin API surface recorded.
- [x] Missing owner API modules recorded.
- [x] Existing frontend field names preserved as the contract baseline.
- [x] Authentication actors and role scope confirmed.
- [x] Refresh-token behavior confirmed.
- [x] Authorization ownership rules confirmed.
- [x] Booking statuses and cancellation rule confirmed.
- [x] Payment methods confirmed.
- [x] Timezone and currency confirmed.
- [x] Soft-delete policy confirmed.
- [x] Notification persistence confirmed.
- [x] UUID identifier policy confirmed.
- [x] Database gap areas recorded.
- [x] Booking transaction and duplicate-slot rules recorded.
- [x] Standard response envelope recorded.
- [x] Implementation order and Phase 1 handoff recorded.

## 11. Phase 1 handoff

Phase 0 is complete. Phase 1 may begin only against this contract and must implement:

1. Refresh-token persistence, issuance, rotation, and revocation.
2. Authentication for platform admin, business owner, staff, and customer.
3. Authenticated profile resolution.
4. Role and permission middleware.
5. Business/resource ownership checks.
6. Session expiry and invalid-token responses.
7. Authentication and authorization tests.

Any change to the approved fields, roles, token behavior, booking statuses, payment methods, delete policy, timezone, currency, or identifier policy must be discussed before implementation.
