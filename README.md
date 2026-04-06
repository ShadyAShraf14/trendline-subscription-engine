# Subscription Lifecycle Engine - Trendline Assessment

## Overview
This project is a standalone Subscription Management API built with Laravel 12 for the Trendline backend challenge.

It supports:
- Dynamic subscription plans
- Multiple billing cycles
- Multi-currency pricing
- Trial periods
- Subscription lifecycle transitions
- Grace period handling after failed payments
- Daily automated processing using Laravel Scheduler

---

## Challenge Requirements Covered

### 1. Dynamic Plan Management
The API supports:
- Creating plans
- Updating plans
- Deactivating plans
- Multiple billing cycles per plan (`monthly`, `yearly`)
- Multiple currencies per plan (`AED`, `USD`, `EGP`)
- Configurable trial periods per plan

### 2. Subscription Lifecycle Engine
The subscription lifecycle is implemented using explicit states:
- `trialing`
- `active`
- `past_due`
- `canceled`

### 3. Grace Period Logic
When a payment fails:
- The subscription moves to `past_due`
- Access remains granted
- A 3-day grace period starts
- If no successful payment is recorded during the grace period, the subscription is canceled automatically

### 4. Automation & Scheduling
A scheduled command processes subscription transitions daily:
- Expired trials move to `past_due`
- Expired active subscriptions move to `past_due`
- Expired grace periods move to `canceled`

---

## Main Architecture Decisions

### 1. Separate `plans` from `plan_prices`
A plan represents the product definition itself, while pricing is stored separately in `plan_prices`.

This allows one plan to support:
- more than one billing cycle
- more than one currency
- clean extensibility without duplicating plan records

### 2. Explicit enums for domain state
The implementation uses enums for:
- subscription statuses
- currencies
- billing cycles
- payment attempt statuses

This improves readability and makes lifecycle transitions safer.

### 3. Explicit payment attempt tracking
A `payment_attempts` table stores:
- successful payment attempts
- failed payment attempts
- failure reasons
- timestamps

This makes the billing lifecycle auditable and allows the API to model failed and successful payments clearly.

### 4. Single access-granting subscription policy
For simplicity, the implementation assumes one user can have only one access-granting subscription at a time.

If a user subscribes to a new plan, any existing `trialing`, `active`, or `past_due` subscription is canceled first.

### 5. Safe plan deletion
Deleting a plan through the API does not physically remove historical data.
Instead, the plan and its prices are deactivated by setting `is_active = false`.

---

## Database Structure

### `plans`
Stores the plan definition:
- `name`
- `description`
- `trial_days`
- `is_active`

### `plan_prices`
Stores prices per plan:
- `currency`
- `amount`
- `billing_cycle`
- `is_active`

### `subscriptions`
Stores the user subscription lifecycle:
- `status`
- `started_at`
- `trial_ends_at`
- `ends_at`
- `grace_period_ends_at`
- `canceled_at`

### `payment_attempts`
Stores payment history:
- `status` (`success`, `failed`)
- `amount`
- `currency`
- `failure_reason`
- `attempted_at`
- `paid_at`

---

## Lifecycle Rules

### Trial plan flow
If the selected plan has a trial period:
- subscription starts as `trialing`
- `trial_ends_at` is set
- no initial payment is required

When the trial expires:
- the scheduler moves the subscription to `past_due`
- a 3-day grace period begins

### Non-trial plan flow
If the selected plan has no trial:
- the API requires `initial_payment_status`
- `success` creates an `active` subscription
- `failed` creates a `past_due` subscription and starts grace immediately

### Successful payment
When a successful payment is recorded:
- subscription becomes `active`
- `grace_period_ends_at` is cleared
- `ends_at` is extended based on billing cycle

### Failed payment
When a failed payment is recorded:
- subscription becomes `past_due`
- grace period starts if not already started

### Grace period expiration
If a `past_due` subscription reaches `grace_period_ends_at` without a successful payment:
- the scheduler changes it to `canceled`

### Canceled subscriptions
Canceled subscriptions cannot accept new payments.
A new subscription must be created instead.

---

## API Endpoints

### Authentication
- `POST /api/login`

### Plans
- `GET /api/plans`
- `GET /api/plans/{plan}`
- `POST /api/plans`
- `PUT /api/plans/{plan}`
- `DELETE /api/plans/{plan}`

### Subscriptions
- `GET /api/subscriptions`
- `GET /api/subscriptions/current`
- `GET /api/subscriptions/{subscription}`
- `POST /api/subscriptions`
- `POST /api/subscriptions/{subscription}/payments`
- `POST /api/subscriptions/{subscription}/cancel`

---

## Requirements
- PHP 8.2+
- Composer
- MySQL
- SQLite available for tests

---

## Installation

### 1. Clone the repository
```bash
git clone <your-repository-url>
cd trendline-subscription-engine