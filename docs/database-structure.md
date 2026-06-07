# Scanwell Database Structure

This document is a written database structure reference for the current Scanwell Laravel application, based on the migration files in `database/migrations` and the active Eloquent models in `app/Models`.

## Overview

- Main app records mostly use `uuid` primary keys.
- Laravel framework tables such as jobs, cache, tokens, roles, and permissions use integer or string keys where Laravel created them that way.
- The schema is organized around these modules:
  - authentication and users
  - product catalog and scoring
  - scanning and scan providers
  - subscriptions and billing
  - roles and permissions
  - notifications
  - support/ticketing
  - framework/runtime tables

## 1. Authentication And Users

### `users`
Main user account table.

Key columns:
- `id` `uuid` primary key
- `name`
- `email` unique
- `email_verified_at`
- `password`
- `remember_token`
- `provider`, `provider_id`, `avatar` for OAuth/social sign-in
- `stripe_customer_id`
- `role` default `user`
- `reputation_points` default `0`
- `approved_contributions_count` default `0`
- `rejected_contributions_count` default `0`
- `is_banned` default `false`
- `created_at`, `updated_at`
- `deleted_at` soft delete

Relationships:
- one-to-one with `user_preferences`
- one-to-many with `scans`
- one-to-many with `product_contributions`
- one-to-many with `subscriptions`
- one-to-many with `subscription_events`
- one-to-many with `billing_invoices`, `billing_transactions`, `billing_refunds`
- one-to-many with `notification_campaigns`
- one-to-many with `user_notifications`
- one-to-many with `user_push_tokens`
- one-to-many with `support_cases`
- one-to-many with `support_messages`
- many-to-many with `products` through `user_favorites`
- logical role link to `roles.slug` through `users.role`

### `user_preferences`
Per-user scan and lifestyle preferences.

Key columns:
- `user_id` `uuid` primary key and foreign key to `users.id`
- `diet_type`
- `allergies` JSON
- `skin_type`
- `health_goals` JSON
- `avoid_ingredients` JSON
- `min_score_threshold` default `50`
- timestamps

### `personal_access_tokens`
Laravel Sanctum API tokens.

Key columns:
- `id` primary key
- `tokenable_type`, `tokenable_id` polymorphic owner
- `name`
- `token` unique
- `abilities`
- `last_used_at`
- `expires_at`
- timestamps

### `password_reset_tokens`
Password reset token storage.

Key columns:
- `email` primary key
- `token`
- `created_at`

### `sessions`
Laravel session storage.

Key columns:
- `id` primary key
- `user_id` nullable indexed session owner field
- `ip_address`
- `user_agent`
- `payload`
- `last_activity`

## 2. Product Catalog And Analysis

### `products`
Master catalog table for scanned products.

Key columns:
- `id` `uuid` primary key
- `barcode` unique
- `name`
- `brand`
- `category_id` indexed nullable integer
- `product_family` indexed nullable string
- `category_name`
- `image_url`
- `ingredients_text`
- `additives` JSON
- `allergens` JSON
- `region_availability` JSON
- `manual_overrides` JSON
- `source`
- `raw_data` JSON
- `created_by` nullable foreign key to `users.id`
- `approved_by` nullable foreign key to `users.id`
- `approved_at`
- timestamps
- `deleted_at` soft delete

Relationships:
- many-to-many with `ingredients` through `product_ingredients`
- one-to-one with `food_nutrition`
- one-to-one with `food_scores`
- one-to-one with `cosmetic_scores`
- one-to-many with `scans`
- one-to-many with `product_barcodes`
- one-to-many with `product_images`
- one-to-many with `product_contributions`
- one-to-many with `product_audit_logs`
- many-to-many self-reference through `product_alternatives`
- many-to-many with `users` through `user_favorites`

### `ingredients`
Reference table for ingredients used in products.

Key columns:
- `id` `uuid` primary key
- `name` unique
- `category` indexed
- `risk_level` indexed nullable
- `scientific_reference`
- `description`
- `aliases` JSON
- `health_effects` JSON
- `regulatory_status` JSON
- timestamps

### `product_ingredients`
Pivot table between products and ingredients.

Key columns:
- composite primary key: `product_id`, `ingredient_id`
- `product_id` foreign key to `products.id`
- `ingredient_id` foreign key to `ingredients.id`
- `percentage`
- `is_additive` default `false`
- `origin`
- timestamps

### `food_nutrition`
Food nutrition profile, one row per product.

Key columns:
- `product_id` primary key and foreign key to `products.id`
- `calories`
- `fat`, `saturated_fat`, `trans_fat`
- `cholesterol`
- `sodium`
- `carbohydrates`, `fiber`, `sugars`, `added_sugars`
- `protein`
- `vitamin_d`, `calcium`, `iron`, `potassium`
- `vitamins` JSON
- `minerals` JSON
- `serving_size`
- `servings_per_container`
- timestamps

### `food_scores`
Computed health score for food-family products.

Key columns:
- `product_id` primary key and foreign key to `products.id`
- `overall_score`
- `nutrition_score`
- `ingredient_score`
- `additive_score`
- `processing_score`
- `nova_group`
- `nutriscore_grade`
- `score_breakdown` JSON
- `explanation_text`
- `warnings` JSON
- `benefits` JSON
- `calculated_at`
- timestamps

### `cosmetic_scores`
Computed score for cosmetic-family products.

Key columns:
- `product_id` primary key and foreign key to `products.id`
- `overall_score`
- `irritant_score`
- `endocrine_score`
- `allergen_score`
- `environmental_score`
- `score_breakdown` JSON
- `explanation_text`
- `warnings` JSON
- `benefits` JSON
- `skin_types_suitable` JSON
- `calculated_at`
- timestamps

### `scans`
History of product scans from users/devices.

Key columns:
- `id` `uuid` primary key
- `user_id` nullable foreign key to `users.id`
- `product_id` nullable foreign key to `products.id`
- `scan_timestamp`
- `device_type`
- `barcode`
- `status` default `pending`
- `scan_metadata` JSON
- timestamps

### `product_alternatives`
Self-referencing pivot that stores healthier or better alternatives.

Key columns:
- composite primary key: `product_id`, `alternative_product_id`
- `product_id` foreign key to `products.id`
- `alternative_product_id` foreign key to `products.id`
- `reason`
- `score_improvement`
- `comparison_data` JSON
- `rank` default `1`
- timestamps

### `product_contributions`
User-submitted additions, corrections, or moderation requests for products.

Key columns:
- `id` `uuid` primary key
- `user_id` nullable foreign key to `users.id`
- `product_id` nullable foreign key to `products.id`
- `change_type`
- `field_name`
- `old_data` JSON
- `new_data` JSON
- `moderated_data` JSON
- `reason`
- `evidence` JSON
- `barcode`
- `product_name`
- `status` default `pending`
- `reviewed_by_admin` nullable foreign key to `users.id`
- `review_notes`
- `flag_reason`
- `flagged_at`
- `reputation_points_awarded` default `0`
- `meta` JSON
- `reviewed_at`
- timestamps

### `user_favorites`
Pivot table for saved/favorited products.

Key columns:
- composite primary key: `user_id`, `product_id`
- `user_id` foreign key to `users.id`
- `product_id` foreign key to `products.id`
- timestamps

### `product_barcodes`
Additional barcodes attached to a product.

Key columns:
- `id` `uuid` primary key
- `product_id` foreign key to `products.id`
- `barcode` unique
- `label`
- `is_primary` default `false`
- `created_by` nullable foreign key to `users.id`
- timestamps

### `product_images`
Managed images for a product.

Key columns:
- `id` `uuid` primary key
- `product_id` foreign key to `products.id`
- `disk`
- `path`
- `url`
- `source` default `manual`
- `is_primary` default `false`
- `sort_order` default `0`
- `uploaded_by` nullable foreign key to `users.id`
- timestamps

### `product_audit_logs`
Audit trail for moderation and product changes.

Key columns:
- `id` `uuid` primary key
- `actor_id` nullable foreign key to `users.id`
- `product_id` nullable foreign key to `products.id`
- `contribution_id` nullable foreign key to `product_contributions.id`
- `action`
- `description`
- `changes` JSON
- `metadata` JSON
- timestamps

## 3. Subscriptions And Billing

### `subscription_plans`
Plan catalog such as free, pro, and team.

Key columns:
- `id` primary key
- `slug` unique
- `name`
- `description`
- `features` JSON
- `is_active`
- `is_default`
- `display_order`
- timestamps

### `subscription_prices`
Price points under a plan.

Key columns:
- `id` primary key
- `plan_id` foreign key to `subscription_plans.id`
- `name`
- `amount`
- `currency`
- `billing_interval`
- `billing_interval_count`
- `trial_days`
- `stripe_price_id`
- `is_active`
- `is_default`
- `metadata` JSON
- timestamps

### `subscriptions`
Actual user subscription records.

Key columns:
- `id` `uuid` primary key
- `user_id` foreign key to `users.id`
- `plan_id` foreign key to `subscription_plans.id`
- `price_id` nullable foreign key to `subscription_prices.id`
- `provider` default `system`
- `status`
- `quantity`
- `currency`
- `amount`
- `stripe_customer_id`
- `stripe_subscription_id` unique nullable
- `stripe_checkout_session_id`
- `stripe_payment_intent_id`
- `stripe_invoice_id`
- `starts_at`
- `current_period_starts_at`
- `current_period_ends_at`
- `trial_ends_at`
- `canceled_at`
- `ends_at`
- `refunded_at`
- `metadata` JSON
- timestamps

### `subscription_events`
Audit/event history for subscription lifecycle changes.

Key columns:
- `id` `uuid` primary key
- `user_id` foreign key to `users.id`
- `subscription_id` nullable foreign key to `subscriptions.id`
- `event_type`
- `source`
- `actor_id` nullable foreign key to `users.id`
- `from_plan_id`, `to_plan_id` nullable foreign keys to `subscription_plans.id`
- `from_price_id`, `to_price_id` nullable foreign keys to `subscription_prices.id`
- `status_before`, `status_after`
- `reason`
- `effective_at`
- `metadata` JSON
- timestamps

### `billing_invoices`
Stored invoice history from Stripe or other billing providers.

Key columns:
- `id` `uuid` primary key
- `user_id` foreign key to `users.id`
- `subscription_id` nullable foreign key to `subscriptions.id`
- `provider`
- `provider_invoice_id` unique
- `provider_payment_intent_id`
- `provider_charge_id`
- `currency`
- `subtotal`
- `tax`
- `total`
- `amount_paid`
- `amount_due`
- `status`
- `billing_reason`
- `hosted_invoice_url`
- `invoice_pdf_url`
- `issued_at`
- `due_at`
- `paid_at`
- `failed_at`
- `metadata` JSON
- timestamps

### `billing_transactions`
Recorded charges and billing transaction attempts.

Key columns:
- `id` `uuid` primary key
- `user_id` foreign key to `users.id`
- `subscription_id` nullable foreign key to `subscriptions.id`
- `billing_invoice_id` nullable foreign key to `billing_invoices.id`
- `provider`
- `provider_transaction_id` unique nullable
- `type`
- `status`
- `amount`
- `currency`
- `description`
- `payment_method_brand`
- `payment_method_last4`
- `failure_code`
- `failure_message`
- `occurred_at`
- `metadata` JSON
- timestamps

### `billing_refunds`
Refund history linked to invoices and transactions.

Key columns:
- `id` `uuid` primary key
- `user_id` foreign key to `users.id`
- `subscription_id` nullable foreign key to `subscriptions.id`
- `billing_invoice_id` nullable foreign key to `billing_invoices.id`
- `billing_transaction_id` nullable foreign key to `billing_transactions.id`
- `provider`
- `provider_refund_id` unique
- `amount`
- `currency`
- `reason`
- `status`
- `requested_by_type`
- `requested_by_id`
- `refunded_at`
- `metadata` JSON
- timestamps

## 4. Roles And Permissions

### `roles`
Admin/user role definitions.

Key columns:
- `id` primary key
- `name`
- `slug` unique
- `description`
- `is_system`
- timestamps

### `permissions`
Permission catalog used by RBAC.

Key columns:
- `id` primary key
- `name`
- `slug` unique
- `group`
- `description`
- `is_system`
- timestamps

### `permission_role`
Pivot linking permissions to roles.

Key columns:
- `id` primary key
- `role_id` foreign key to `roles.id`
- `permission_id` foreign key to `permissions.id`
- timestamps
- unique pair: `role_id`, `permission_id`

## 5. Notifications

### `notification_campaigns`
Admin-created notification/email/push campaigns.

Key columns:
- `id` `uuid` primary key
- `created_by` nullable foreign key to `users.id`
- `type`
- `status`
- `title`
- `subject`
- `body`
- `cta_label`
- `cta_url`
- `audience_type`
- `audience_filters` JSON
- `channels` JSON
- `recipients_count`
- `read_count`
- `delivery_summary` JSON
- `scheduled_at`
- `sent_at`
- `meta` JSON
- timestamps

### `user_notifications`
Per-user delivered notifications.

Key columns:
- `id` `uuid` primary key
- `campaign_id` nullable foreign key to `notification_campaigns.id`
- `user_id` foreign key to `users.id`
- `type`
- `title`
- `body`
- `cta_label`
- `cta_url`
- `channels` JSON
- `channel_statuses` JSON
- `data` JSON
- `delivered_at`
- `read_at`
- timestamps

Constraint:
- unique pair: `campaign_id`, `user_id`

### `user_push_tokens`
Push-notification device tokens.

Key columns:
- `id` primary key
- `user_id` foreign key to `users.id`
- `platform`
- `token` unique
- `device_name`
- `is_active`
- `last_used_at`
- timestamps

## 6. Support Module

### `support_cases`
Tickets or support conversations opened by users.

Key columns:
- `id` `uuid` primary key
- `reference` unique
- `user_id` foreign key to `users.id`
- `assigned_to` nullable foreign key to `users.id`
- `type`
- `subject`
- `description`
- `status`
- `priority`
- `source`
- `attachments` JSON
- `metadata` JSON
- `customer_last_read_at`
- `support_last_read_at`
- `last_message_at`
- `resolved_at`
- `closed_at`
- timestamps

### `support_messages`
Messages inside a support case.

Key columns:
- `id` `uuid` primary key
- `support_case_id` foreign key to `support_cases.id`
- `user_id` nullable foreign key to `users.id`
- `sender_type`
- `message`
- `attachments` JSON
- `is_internal`
- `read_at`
- timestamps

## 7. Scan Provider Orchestration

### `scan_providers`
Configurable external product lookup providers.

Key columns:
- `id` `uuid` primary key
- `name`
- `provider_key` unique
- `driver`
- `is_active`
- `priority`
- `supported_families` JSON
- `settings` JSON
- `credentials` text, cast in model as encrypted array
- `timeout_seconds`
- `retry_attempts`
- `cache_ttl_minutes`
- `health_status`
- `last_success_at`
- `last_failure_at`
- `last_error`
- `notes`
- timestamps

### `scan_provider_lookups`
Per-provider lookup attempts linked to scans.

Key columns:
- `id` `uuid` primary key
- `scan_provider_id` nullable foreign key to `scan_providers.id`
- `scan_id` nullable foreign key to `scans.id`
- `barcode`
- `status`
- `matched`
- `product_family`
- `confidence`
- `latency_ms`
- `error_message`
- `response_summary` JSON
- timestamps

## 8. Framework Runtime Tables

### `cache`
- `key` primary key
- `value`
- `expiration`

### `cache_locks`
- `key` primary key
- `owner`
- `expiration`

### `jobs`
- `id` primary key
- `queue`
- `payload`
- `attempts`
- `reserved_at`
- `available_at`
- `created_at`

### `job_batches`
- `id` primary key
- `name`
- `total_jobs`
- `pending_jobs`
- `failed_jobs`
- `failed_job_ids`
- `options`
- `cancelled_at`
- `created_at`
- `finished_at`

### `failed_jobs`
- `id` primary key
- `uuid` unique
- `connection`
- `queue`
- `payload`
- `exception`
- `failed_at`

## 9. Relationship Summary

- `users` is the central parent for preferences, scans, contributions, subscriptions, billing history, notifications, push tokens, support records, and audit activity.
- `products` is the central catalog parent for ingredients, nutrition, scores, scans, alternatives, contributions, barcodes, images, favorites, and audit logs.
- `subscriptions` connects users to `subscription_plans` and `subscription_prices`, then fans out into `subscription_events`, `billing_invoices`, `billing_transactions`, and `billing_refunds`.
- `roles` and `permissions` use the `permission_role` pivot, while `users.role` stores the assigned role slug.
- `scan_providers` and `scan_provider_lookups` support multi-provider product resolution and logging for each scan.

## 10. Notes

- Default plans and provider seed data are inserted during migrations for subscriptions and scan providers.
- `products.barcode` remains unique even though extra codes can also exist in `product_barcodes`; in practice this makes the main product row hold the canonical barcode.
- Some tables store flexible JSON payloads for provider data, moderation metadata, audience filters, and analysis results.
