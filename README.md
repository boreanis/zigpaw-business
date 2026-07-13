# Zigpaw Clinical Portal

The separately deployed portal for authenticated veterinary organisations. It uses Laravel 13 and Livewire 4 as an OAuth BFF and presents only API-authorized clinical context.

## Boundary

- Production host: `vets.zigpaw.com`.
- Identity: `auth.zigpaw.com` Authorization Code + PKCE.
- API: `api.zigpaw.com/v1/vets/*` and future purpose-scoped clinical contracts.
- Local state: encrypted portal session plus local cache/jobs support tables only.
- Does not own: platform MySQL, pet/clinical Eloquent models, finder data, billing data, provider credentials, or profile-manager approval rules.

Every clinical data action remains constrained by an active veterinary organisation membership, a matching portal OAuth client/scope, and (where a pet record is involved) an explicit, purpose-bound grant. Submitted records remain pending until the pet profile manager reviews them.

## Current Contract Coverage

This repository currently proves authenticated veterinary portal identity through `GET /v1/vets/me`. The external clinical API accepts only a pending care submission under an explicit provider grant. A clinical workspace must grow from those grant-scoped contracts; it must never add general pet lookup, household access, finder PII, billing data, or a direct clinical database connection.

For every new clinical action, add the platform grant/policy/provenance behavior, Form Request, API Resource, versioned contract, idempotency for writes, and contract tests before building the Livewire screen here.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Configure `PLATFORM_API_URL`, `PLATFORM_AUTH_URL`, `PLATFORM_OAUTH_CLIENT_ID`, and the callback URI that is allowlisted on the platform's veterinary portal OAuth client. The seeded client ID in `.env.example` is public configuration, not a secret.

## Verification

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

Deploy its release assets only beneath `cdn.zigpaw.com/vets/<release>/`.

The authoritative rules are in the sibling [architecture](../zigpaw/docs/09_ARCHITECTURE.md), [provider integrations](../zigpaw/docs/11_PROVIDER_INTEGRATIONS.md), and [pet health/Pawsport](../zigpaw/docs/07_PETS_HEALTH_PAWSPORT.md) documents.
