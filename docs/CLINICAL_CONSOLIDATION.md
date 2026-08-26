# Clinical workspace consolidation

> **Historical consolidation record — current runtime guidance lives in**
> [`BUSINESS_UI_REBUILD.md`](./BUSINESS_UI_REBUILD.md), the local `AGENTS.md`,
> and the Platform Constitution/current API documents. This record explains
> how the former veterinary surface was consolidated into the single Business
> workspace; it is not a separate application, repository, route family, or
> compatibility target.

## Decision

The category-aware clinical workspace in `zigpaw-business` is the only supported browser application for veterinary providers. The retired `zigpaw-vets` repository is historical source, not a deployable runtime or compatibility target.

One Business deployment serves management and clinical templates. The templates share the encrypted, host-only Business browser session container, but their authorization material remains isolated:

| Boundary | Management | Clinical |
| --- | --- | --- |
| OAuth callback | `/auth/callback` | `/clinical/auth/callback` |
| OAuth client and secret | `PLATFORM_OAUTH_*` | `PLATFORM_CLINICAL_OAUTH_*` |
| Scope audience | `business:*` | `clinical:read`, `clinical:submit` |
| Session handle | `platform.oauth.token_handle` | `platform.oauth.clinical_token_handle` |
| Encrypted cache entry | Derived from management handle | Derived from clinical handle |
| API namespace | `/v1/business/*` | `/v1/business/clinical/*` |
| Revocation endpoint | `/v1/business/session` | `/v1/business/clinical/session` |

No access token, refresh token, client secret or provider record is exposed to browser JavaScript. The BFF stores only opaque token handles in the encrypted browser session and keeps token envelopes in the configured server-side cache.

## Preserved clinical capability

The consolidated template preserves the approved Vets workflows:

- separate clinical sign-in and sign-out;
- organization selection under clinical authorization;
- grant-scoped patient search and detail;
- capability-gated care context;
- provider-grant media proxying with no-store responses;
- care submissions that remain pending until the profile manager reviews them;
- submission history and status;
- request correlation, bounded upstream failures and explicit empty/error states.

The platform remains authoritative for identity, organization membership, provider grants, clinical capabilities, records, media and submissions. This repository has no platform database connection and stores no local clinical models.

## Security requirements

The BFF fails closed unless the application, API, identity origin, callback and revocation endpoint exactly match the environment allowlist:

| Environment | Business | API | Identity |
| --- | --- | --- | --- |
| Local/testing | `https://business.zigpaw.test` | `https://api.zigpaw.test` | `https://login.zigpaw.test` |
| Staging | `https://business.staging.zigpaw.app` | `https://api.staging.zigpaw.app` | `https://login.staging.zigpaw.app` |
| Production | `https://business.zigpaw.app` | `https://api.zigpaw.app` | `https://login.zigpaw.app` |

User info, ports other than 443, alternate paths, query strings, fragments, suffix lookalikes and callback variations are rejected before credentials are sent. Readiness requires distinct, non-placeholder management and clinical clients and their correct scope audiences.

Refresh failures are classified deliberately. Network failures, upstream 5xx responses, malformed successful payloads and non-definitive 400 responses preserve the encrypted token envelope for a later retry. Only `invalid_grant` and authentication failure invalidate it. Logout URLs must be signed identity URLs on the exact configured identity origin; malformed or cross-origin responses are rejected.

## Deliberately unavailable

Consolidation does not restore broad veterinary access or historical shortcuts. The Business application must not add:

- unrestricted pet lookup or private-document access;
- provider-grant creation, extension or revocation by the provider;
- media upload or direct private storage URLs;
- automatic acceptance of proposed clinical records;
- consumer, billing, Finder/recovery or tag-management data;
- direct database access, locally copied provider/clinical models, or a fallback to retired `/v1/vets/*` routes.

## Retirement gate

The standalone Vets repository can remain archived once the following gates pass for `zigpaw-business` and the matching platform contract:

1. full Business PHPUnit and static-analysis suites;
2. management and clinical OAuth origin/callback tests;
3. token refresh, revocation and malformed-response tests for both contexts;
4. clinical BFF route, grant-capability, media and care-submission tests;
5. production asset build, dependency audits and contract-sync check;
6. signed-out desktop/mobile smoke of management and clinical entry points.

Deletion should follow the organization's normal repository-retention policy. Archive provenance should retain the final Vets commit identifier and release history even after no runtime depends on it.
