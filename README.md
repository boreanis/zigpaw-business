# Zigpaw Partner Portal

The independently deployed operations portal for Zigpaw partners. It uses Laravel 13 and Livewire 4 as an OAuth BFF, keeping partner presentation separate from platform data ownership.

## Boundary

- Production host: `partners.zigpaw.com`.
- Identity: `auth.zigpaw.com` Authorization Code + PKCE.
- API: `api.zigpaw.com/v1/partners/*` using scoped OAuth tokens.
- Organisation context: an explicit `X-Zigpaw-Organization-ID` header selected from the authenticated portal context. It is never borrowed from a browser session in the platform.
- Local state: encrypted portal session plus local cache/jobs support tables only.
- Does not own: pet models, partner models, primary MySQL, commerce, queues, or provider credentials.

The platform checks both token scope and the authenticated user's active organisation membership before it returns partner data or accepts a write. Do not add direct database access as a shortcut.

## Current Contract Coverage

The partner API currently supports portal identity, prepared-profile reads/creation, private pet-group reads/creation, and organisation machine analytics. A write uses the platform's idempotency and authorization rules; all organisation context is explicit. Referral, fulfilment, and broader operational workflows remain platform-hosted until their matching contracts are designed and verified.

Add new portal behavior API-first in `zigpaw`: use case, policy, Form Request, API Resource, route, contract tests, then portal UI. Never reproduce platform rules or borrow organisation context from a platform browser session.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Configure `PLATFORM_API_URL`, `PLATFORM_AUTH_URL`, `PLATFORM_OAUTH_CLIENT_ID`, and the callback URI that is allowlisted on the platform's partner portal OAuth client. The seeded client ID in `.env.example` is public configuration, not a secret.

## Verification

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

Deploy its release assets only beneath `cdn.zigpaw.com/partners/<release>/`.

The canonical rules are in the sibling [platform architecture](../zigpaw/docs/09_ARCHITECTURE.md) and [provider integration guide](../zigpaw/docs/11_PROVIDER_INTEGRATIONS.md).
