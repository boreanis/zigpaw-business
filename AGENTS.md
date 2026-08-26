# Zigpaw Business Agent Start

Canonical constitution: https://github.com/boreanis/zigpaw-platform/blob/main/docs/CONSTITUTION.md

Before changing this repository, read the binding
[Zigpaw Constitution](../zigpaw-platform/docs/CONSTITUTION.md), the platform
[documentation index](../zigpaw-platform/docs/README.md),
[project rules](../zigpaw-platform/ZIGPAW_RULES.md), and this repository's
`README.md`. For provider or clinical work, also read the current platform
architecture, pets/health, provider, regional, deployment, and matching API
contract documents.

This repository is one independently deployed provider workspace. Its
veterinary, grooming, boarding, sitting, breeding, shelter, mobile-care, and
other templates are presentation/capability modes—not separate applications.
It has no Platform/domain database access; it may own only narrowly scoped
host-local encrypted session/cache/queue support state. It is API-only: never
add Platform models, domain data, Platform/provider/payment credentials,
direct web-controller calls, shared sessions, or client-owned authorization,
pricing, clinical, booking, or workflow rules. Use released Business and
clinical contracts with separate scopes, token stores, and capability checks;
the Platform remains the system of record.

Use reusable components/tokens and accessible responsive states. Run focused
tests, static/style checks, contract drift checks, production build, and
production-shaped browser QA for user-facing or transport changes. Never send
customer, pet, clinical, support, order, log, or uploaded-document data to
AI/OCR or automated document-comprehension services. The retired
`zigpaw-vets` repository and `/v1/vets/*` routes are historical references,
not fallbacks. Preserve unrelated changes; do not commit or push unless
explicitly instructed.
