# Pixelforge App: Plan

## Key decisions

1. **Money is stored as whole numbers of nano-dollars (bigint, $0.000000001). Never use floats.**
   Per-token rates in nano-USD: input text 5,000 · input image 10,000 · output 40,000 · reasoning 12,500.
   With the 25% markup (×5/4): 6,250 · 12,500 · 50,000 · 15,625. All whole numbers, so prices stay exact with no rounding.
   Show users rounded dollars; keep the exact values in the database.
2. **Use the `rates` field on each generation, not hard-coded rates.** Pixelforge locks the rates in when the generation is created.
3. **The database prevents double charges:**
    - `webhook_events.event_id` has a unique index.
    - Charging happens inside a transaction only if the generation isn't already `succeeded` or `failed`.
    - A finished generation never changes status.

## Steps

### 1. Setup

- [x] `laravel new pixelforge-app --vue --pest` (the official Vue + Inertia + auth starter kit)
- [x] `composer require laravel/boost --dev && php artisan boost:install`
- [x] Add `.ai/guidelines/no-comments.md` with the line "Never write code comments."
- [x] Choose the database (MySQL or Postgres); use the same one locally and in production

### 2. Pixelforge account

- [x] Create 2 API keys: local and prod, and a webhook secret
- [x] Add `PIXELFORGE_API_KEY_LOCAL`, `PIXELFORGE_API_KEY_PROD`, and `PIXELFORGE_WEBHOOK_SECRET` to `.env`, read through `config/services.php`

### 3. Database

- [x] `generations` table:
    - `user_id`
    - `pixelforge_id` (unique)
    - `idempotency_key` (unique)
    - `prompt`, `size`, `quality`, `reference_images` (json)
    - `status`, `rates` (json), `usage` (json)
    - `estimated_cost`, `actual_cost`, `provider_cost` (bigint nano-USD)
    - `image_url`, `image_url_expires_at`, `error`, `completed_at`
- [x] `webhook_events` table: `event_id` (unique), `type`, `payload`, `processed_at`

### 4. PricingService + test

- [x] Estimate (an upper limit):
    - exact output tokens, based on size and quality
    - 765 tokens per reference image
    - `ceil(chars / 4)` tokens for the prompt text
    - the maximum reasoning tokens for that quality
- [x] Actual cost = `usage × rates`
- [x] Test against the docs' example: high quality, 1536x1024, 400-character prompt, 2 reference images = **$0.373875**

### 5. PixelforgeClient

- [x] Use Laravel's `Http::` client with `->retry()`
- [x] Retry on 503, and on 429 using the `Retry-After` header; cap the number of retries
- [x] Always send an `Idempotency-Key` so a retry never creates a second generation

### 6. Generate page
- [X] Form fields: prompt, size, quality, and up to 4 reference URLs
- [X] Show a live cost estimate before submitting
- [X] Use a Form Request for validation
- [X] Save the row first, then send the request to Pixelforge from a queued job
- [X] A webhook can arrive before the POST returns, so look the row up by `pixelforge_id` or `idempotency_key`

### 7. Webhook endpoint
- [X] Exclude the route from CSRF
- [X] Verify the HMAC on the raw body with `hash_equals`; reject requests older than 300 seconds
- [X] Insert the event ID; if it already exists, return 200 and stop
- [X] Update the status in a transaction with a row lock (`lockForUpdate`)
- [X] Ignore an event that would move the generation backwards (e.g. `processing` arriving after `succeeded`)
- [X] On `succeeded`, fill in `actual_cost` and `provider_cost`; on `failed`, the cost is 0

### 8. Polling fallback

- [ ] Scheduled command: fetch generations that are still unfinished after about 2 minutes
- [ ] It goes through the same status-update method as the webhook

### 9. History page

- [ ] Show each generation's status badge, estimated cost, actual cost, image and error
- [ ] Use Inertia's `usePoll` while any generation is unfinished

### 10. Test the failure cases

- [ ] Write Pest tests
- [ ] Try each test keyword in the prompt: `[fail]`, `[duplicate]`, `[slow]`, `[overloaded]`, `[blocked]`
- [ ] Use the dashboard's resend button to deliver an event twice

### 11. Deploy

- [ ] Laravel Cloud: app, database, queue worker and scheduler in one place
- [ ] Set the deployed key's webhook URL to `https://your-app/webhooks/pixelforge`
- [ ] Deploy right after step 7, since webhooks can't reach localhost (or use an ngrok tunnel)
- [ ] Leave a few generations on the deployed app for the reviewers

### 12. Stretch goals

- [ ] Admin report: `actual_cost` vs `provider_cost` per day and per user
    - group by the UTC day each generation completed, the same as `/v1/usage`
    - show `/v1/usage` beside your totals for comparison
- [ ] Balances: a `credit_transactions` ledger table
    - hold the estimate when a generation starts
    - when it finishes, charge the actual cost and release the rest of the hold
    - if it fails, release the whole hold
    - add a Buy credits button
    - block generating when the user can't afford the estimate
