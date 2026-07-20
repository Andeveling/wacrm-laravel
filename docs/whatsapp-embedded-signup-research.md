# Embedded Signup vs. manual paste — research for WACRM v1

Context: WACRM runs **one shared Meta App**; each `Account` (tenant) connects
its own WABA/phone number. Inbound webhooks resolve tenant only by
`phone_number_id`. A prior session fixed "manual connection first" (client
pastes `phone_number_id` / `waba_id` / `access_token` / optional PIN into
`resources/js/pages/settings/whatsapp.tsx`) and deferred Embedded Signup to a
later phase. This doc checks that decision against Meta's primary docs before
task-list item 15 ("Sesión de configuración real") gets built.

## 1. Recommendation

**Keep manual paste for v1, but only because App Review is required either
way — this is not a friction-avoidance win, it's a scope-avoidance one.**
Meta gates `whatsapp_business_management` and `whatsapp_business_messaging` on
**Advanced Access** the moment your app touches a WABA it doesn't own — true
whether the client typed the token in by hand or arrived via Embedded Signup's
OAuth code-exchange (Sources: Permissions page, App Review page — §3 below).
So Embedded Signup does **not** let WACRM skip Business Verification + App
Review; it only replaces the client's manual "go find your System User token"
steps with a Facebook Login popup. That's a real UX upgrade, but it's
orthogonal to the "manual connection first" decision, which was about
*wiring item 15's endpoints*, not about avoiding Meta's gate.

Given that: build item 15 exactly as scoped (validate + register + subscribe
+ encrypt + persist against pasted credentials), and treat Embedded Signup as
a **strict additive replacement of the paste fields later** — same backend
validate/register/subscribe/persist logic, just fed by the OAuth code-exchange
token instead of a textarea. Do **not** pull it into v1: it adds a Tech
Provider registration, a Business Verification pass, and a JS SDK integration
before a single real client can connect — none of which shrinks once you also
had to do App Review for manual paste. If WACRM expects real (non-test)
clients to onboard themselves without a human relaying a token, Embedded
Signup should be the very next phase after item 15 ships, not a parallel
track.

## 2. What Embedded Signup requires end-to-end

1. **JS SDK load + init** — standard FB SDK bootstrap in
   `window.fbAsyncInit`. (Source: Implementation page, "SDK Loading and
   Initialization".)
2. **Launch** — `FB.login()` called with `config_id: '<CONFIG_ID>'`,
   `response_type: 'code'`, `override_default_response_type: true`. The
   `config_id` comes from a **Login Configuration** created under
   Facebook Login for Business → Configurations in the App Dashboard, scoped
   to WhatsApp accounts + chosen permissions. (Sources: Implementation page,
   "Launch method and callback registration"; WebSearch-derived description of
   the Configurations screen — **lower confidence**, no direct primary-source
   fetch of the App Dashboard Configurations page itself.)
3. **Dual response channel** — a JS callback receives an exchangeable
   `code` with a **30-second TTL**; a `message` (postMessage) event listener
   separately receives `phone_number_id`, `waba_id`, `business_id` (optionally
   `ad_account_ids`, `page_ids`, etc.). Both must be captured — the code alone
   isn't enough to know which WABA/number was just created. (Source:
   Implementation page, "Dual Response Handling".)
4. **Exchange the code for a token** — `GET
   https://graph.facebook.com/v21.0/oauth/access_token` with `client_id`,
   `client_secret`, `code`. This returns a **Business Integration System User
   access token** directly — i.e., Embedded Signup's whole point is that the
   client never has to create a System User or generate a token themselves;
   the exchange mints one scoped to what they granted. (Sources: Onboarding
   as Tech Provider page, "Step 1: Exchange the token code for a business
   token"; Access Tokens page, quote: "To generate a Business Integration
   System User access token, you must implement Embedded Signup... and
   exchange the code returned to you when a customer completes the flow.")
5. **Subscribe the app to the WABA's webhooks** — `POST
   /<WABA_ID>/subscribed_apps` with the business token, once per onboarded
   WABA (this is the same "subscribe the shared app" step item 15 already
   needs regardless of how the token arrived). (Source: Onboarding as Tech
   Provider page, "Step 2: Subscribe to webhooks on the customer's WABA".)
6. **Register the phone number** — `POST
   v21.0/<PHONE_NUMBER_ID>/register` with `messaging_product: "whatsapp"` and
   a 6-digit `pin`. Must happen **within 14 days** of the signup flow or the
   client has to redo Embedded Signup. Two-step-verification PIN, once set via
   this API, has **no API endpoint to disable** — only a UI reset flow in
   WhatsApp Manager, or an email-based disable process. Registration is
   rate-limited to **10 requests per phone number per 72-hour window**.
   (Sources: Onboarding as Tech Provider page, "Step 3: Register the
   customer's phone number"; Register API reference page, "Key Prerequisites"
   and rate-limit note — rate limit number confirmed only via WebSearch
   synthesis, **lower confidence**; Two-Step Verification page, "Important
   Limitation".)

This is materially the same set of Meta-side calls item 15 already has to
make for manually-pasted credentials (validate → register with PIN →
subscribe app → persist) — Embedded Signup only changes *how the token is
obtained*, not what happens after.

## 3. What's gated behind App Review / Business Verification vs. what works today

This is the actual crux, and it applies **identically to manual paste and to
Embedded Signup** because the gate is per-API-call, on WABA ownership — not
on how the token was produced:

- **Works today, no App Review needed:** a "Direct Developer" calling the API
  against **their own** WABA gets Standard Access automatically. This is
  Meta's own test-number flow (WhatsApp product added to your app, a Meta-
  provided test number). (Source: App Review page, quote: "if you are using
  the API for yourself as a Direct Developer, you do not need Advanced access
  or app review.") Test numbers are further capped to **broadcasting to 5
  manually-added recipient numbers** and cannot message real customers
  (WebSearch synthesis — **lower confidence**, no primary citation fetched
  directly for this exact number).
- **Blocked for any real client WABA (not owned by WACRM's business) until
  Advanced Access is granted:** both `whatsapp_business_management` and
  `whatsapp_business_messaging` require **Advanced Access** the moment the
  app acts on a WABA it doesn't own. Without it, calls using
  `whatsapp_business_management` on someone else's WABA return **error code
  200**. (Source: Permissions page, quote: "If your app uses the
  `whatsapp_business_management` permission to access WABAs not owned by your
  business, you must have Advanced access for that permission. Without it,
  API calls return error code 200.") This is the actual blocker for shipping
  to any real (non-test) client — regardless of paste-vs-OAuth.
- **Getting Advanced Access requires, in order:** (1) Meta **Business
  Verification** of WACRM's own business (name, address, phone, email,
  website, supporting docs), then (2) **App Review** — basic app settings
  (icon, privacy policy, category) plus a separate screen recording *and*
  written justification per permission (`whatsapp_business_messaging`,
  `whatsapp_business_management`), reviewed in roughly 24 hours. (Source:
  Become a Tech Provider page, "Step 1: Verify Your Business", "Step 2: App
  Review"; App Review page, "Submission Requirements".)
- **Embedded Signup itself has an additional default cap even after Advanced
  Access:** 10 new business customers per rolling 7-day window by default,
  raised to 200/week only after Business Verification + App Review + "Access
  Verification" all complete; above 200/week you must apply as a Meta
  Business Partner. (Source: Embedded Signup overview page, "Limitations" —
  quote: "By default, you can onboard up to 10 new business customers in a
  rolling 7-day window... If you complete Business Verification, App Review,
  and Access Verification, your limit is automatically increased to 200.")
  Manual paste has no equivalent Meta-imposed weekly cap — the client
  generates their own token outside any WACRM-owned flow, so this limit is
  Embedded-Signup-specific downside, not a manual-paste advantage in the App
  Review sense, but it is a concrete throughput difference if WACRM ever
  onboards >10 tenants/week before finishing verification.
- **Display name approval is separate from both.** Once WACRM's business
  completes Business Verification, display-name review is triggered for
  *all* phone numbers on the account; changes go through
  `AVAILABLE_WITHOUT_REVIEW` / `PENDING_REVIEW` / `DECLINED` states, and a
  `phone_number_name_update` webhook signals approval before you may
  re-register. (WebSearch synthesis of the Display Names page — **lower
  confidence**, not fetched directly.)
- **Messaging-limit tiers** (250 → 2,000 → 10,000 → 100,000 → unlimited
  messages/24h) scale independently via business verification + message
  quality, not via App Review per se. (Source: Messaging Limits page,
  "Messaging Limit Tiers", "Scaling paths".) Not a v1 blocker either way.
- **Tech Provider vs. Solution Partner vs. plain Business app:** the docs
  never state Embedded Signup requires Tech Provider status outright, but the
  Login Configuration (`config_id`) is created "under your Tech Provider
  app," and the onboarding-sequence doc is written entirely in Tech-Provider
  terms — practically, adopting Embedded Signup means going through the
  Tech Provider path (same Business Verification + App Review as above), not
  a lighter one. (Source: Embedded Signup overview page, "Partner
  Requirements" — confirms no explicit Tech-Provider mandate is stated;
  Become a Tech Provider page — the concrete steps found are all Tech-
  Provider-flavored; the "created under your Tech Provider app" phrasing is
  WebSearch-derived, **lower confidence**.)

**Bottom line:** nothing here blocks *development* — a shared test number
works today, no review needed. What blocks a *real client* is Advanced
Access via Business Verification + App Review, and that gate sits in front of
manual paste too, since WACRM's shared app is still the one making
`register`/`subscribed_apps` calls against a WABA it doesn't own.

## 4. Laravel package landscape

No maintained Laravel package implements Embedded Signup / Facebook Login
for Business OAuth. The only GitHub hit specific to this
(`ImAliSheraz/facebook-whatsapp-embeded-signup-laravel`) is a demo app, not a
published Packagist package: last pushed 2024-05-03, 4 stars, 1 fork, no
releases — effectively an abandoned tutorial repo, not something to depend
on.

Packagist has several **generic WhatsApp Cloud API message-sending**
wrappers (`crenspire/laravel-whatsapp`, `MissaelAnda/laravel-whatsapp`,
`sawirricardo/laravel-whatsapp`, `42dx/whatsapp-laravel-sdk`,
`devsfort/laravel-whatsapp-chat`) — these are a different problem (calling
`/messages` with a token you already have), not OAuth/Embedded Signup, and
none of them touch the code-exchange or Login Configuration flow.
`laravel/socialite` explicitly isn't adding new OAuth drivers; no
SocialiteProviders driver for this exists either.

**Confirmed gap, as expected: custom implementation via
`Illuminate\Http\Client` (`Http::` facade) is the answer** for both the code
exchange (`GET /oauth/access_token`) and the `register` /
`subscribed_apps` calls — there's nothing worth adding as a dependency.

## 5. Sequencing

Embedded Signup does **not** replace item 15's manual form in v1 — it wraps
around the same backend later. Rough order:

1. **Now — item 15 as scoped:** `whatsapp_config` GET/POST routes backing
   `resources/js/pages/settings/whatsapp.tsx`. Validate pasted
   `phone_number_id` / `waba_id` / `access_token` (+ optional PIN) against
   the Graph API, reject duplicate `phone_number_id` across accounts (409),
   call `register` (with PIN if given) and `subscribed_apps`, encrypt the
   token, persist to `whatsapp_config`. This is the full set of Meta-side
   calls needed either way — nothing here is throwaway once Embedded Signup
   lands.
2. **Before any real client is connected this way:** WACRM's own Business
   Verification + App Review submission for `whatsapp_business_management`
   and `whatsapp_business_messaging` (Advanced Access). This blocks real
   (non-test) tenants regardless of path 1 or path 3 — start this in
   parallel with item 15, since review takes real calendar time
   (~24h turnaround once submitted, but Business Verification can take
   longer and isn't instant).
3. **Later phase — Embedded Signup:** register as Tech Provider, create a
   Login Configuration (`config_id`), add the JS SDK + `FB.login` button to
   `whatsapp.tsx` (or a new onboarding entry point) as an alternative to the
   paste form. On completion, feed the exchanged Business Integration System
   User token into the **same** validate/register/subscribe/persist backend
   from step 1 — the only new code is the OAuth popup, the code-exchange
   call, and swapping which fields are read (event payload vs. form input).
4. **Optional, only if >10 tenants/week is realistic before step 2's Access
   Verification completes:** revisit the Embedded Signup 10-per-week /
   200-per-week cap; until then it's moot since manual paste has no
   Meta-imposed pace limit.

## 6. Sources

- https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview — "What It Is", "Limitations" (10 vs 200 customers/week caps), "App Review" (Advanced Access gate), "Partner Requirements" (no explicit Tech Provider mandate stated)
- https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/implementation — "SDK Loading and Initialization", "Launch method and callback registration" (`FB.login`, `config_id`, `response_type`, `override_default_response_type`), "Dual Response Handling" (code TTL, message-event payload fields)
- https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-customers-as-a-tech-provider — "Step 1: Exchange the token code for a business token" (`/oauth/access_token`), "Step 2: Subscribe to webhooks on the customer's WABA" (`/subscribed_apps`), "Step 3: Register the customer's phone number" (`/register`)
- https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-phone-number/register-api — request/response shape for `POST /{Phone-Number-ID}/register`, 14-day re-signup requirement, two-factor requirement note
- https://developers.facebook.com/documentation/business-messaging/whatsapp/business-phone-numbers/two-step-verification/ — PIN set via API vs. reset via WhatsApp Manager UI, no API endpoint to disable once enabled
- https://developers.facebook.com/documentation/business-messaging/whatsapp/access-tokens/ — Business Integration System User token minted directly from the Embedded Signup code exchange; System User vs. short-lived User access tokens
- https://developers.facebook.com/documentation/business-messaging/whatsapp/permissions/ — `whatsapp_business_management` and `whatsapp_business_messaging` definitions; Advanced Access required once a WABA isn't owned by your business (error code 200 otherwise)
- https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/app-review — Standard Access auto-approval for Direct Developers on their own WABA/account; Advanced Access submission requirements (per-permission video + written justification); ~24h review time
- https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/get-started-for-tech-providers — "Before You Start" (WhatsApp use case + business portfolio prerequisite), "Step 1: Verify Your Business", "Step 2: App Review"
- https://developers.facebook.com/documentation/business-messaging/whatsapp/messaging-limits — messaging tiers (250/2,000/10,000/100,000/unlimited) and scaling paths via verification + quality
- https://developers.facebook.com/docs/whatsapp/embedded-signup, https://developers.facebook.com/docs/whatsapp/cloud-api, https://developers.facebook.com/docs/whatsapp/business-management-api — legacy `/docs/whatsapp/*` tree; these URLs still resolve but return only shallow landing-page content. Meta's current canonical tree for this material is `/documentation/business-messaging/whatsapp/*` (found via search, not linked from the legacy pages during this research) — noted here since the task's suggested URLs redirect/thin out rather than 404.
- Lower-confidence (WebSearch synthesis only, not directly fetched/quoted from the primary page): Login Configuration / `config_id` creation steps in App Dashboard → Facebook Login for Business → Configurations; test-number 5-recipient broadcast cap; `register` endpoint's 10-requests/72h rate limit; Display Names page's `AVAILABLE_WITHOUT_REVIEW`/`PENDING_REVIEW`/`DECLINED` states and business-verification-triggers-display-name-review claim; "config_id creation happens under your Tech Provider app" phrasing.
- https://github.com/ImAliSheraz/facebook-whatsapp-embeded-signup-laravel — demo repo, last pushed 2024-05-03, 4 stars/1 fork, confirms no maintained Laravel Embedded Signup package exists
- https://packagist.org/packages/laravel/socialite — confirms Socialite is not accepting new OAuth drivers
