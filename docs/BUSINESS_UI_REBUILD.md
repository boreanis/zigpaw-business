# Business UI rebuild

The business browser uses one reusable operational design system for the general partner workspace and the clinical workspace. The UI is intentionally quiet and information-dense: regular or light type, clear status colour, restrained borders, and interaction feedback that remains usable at laptop and mobile widths.

## Route and action parity

| Surface | Routes | Actions and overlays retained |
| --- | --- | --- |
| General access | `/`, `/auth/login`, `/auth/callback`, `/auth/logout` | OAuth redirect/callback, organisation selection, forbidden/unavailable states, sign out |
| Overview | Livewire `BusinessWorkspace` → `overview` | Provider, booking and programme metrics; jump to booking queue; empty and error states |
| Profile | `profile` | Edit legal identity, registration/tax identifiers, primary contact and website; validation and save toast |
| Listings | `listings` | Search directory, select claim candidate, submit claim, edit provider details, cancel edit, pagination |
| Bookings | `bookings` | Review request, open pet context, propose time, accept, decline, complete, permission-aware errors |
| Booking setup | `booking-setup` | Enable/disable requests, edit lead time, windows and instructions, validation and save |
| Services | `services` | Add, edit and remove offering; confirmation for destructive remove; pagination |
| Programs and revenue | `programs`, `revenue` | Apply for referral programme; review referral activity, commissions and agreements |
| Team | `team` | Invite member, resend invitation, edit role, revoke access with confirmation, pagination |
| Clinical access | `/clinical`, `/clinical/auth/login`, `/clinical/auth/callback`, `/clinical/auth/logout` | Organisation selection, access denied/unavailable states, secure sign in/out |
| Clinical patients | `/clinical/patients`, `/clinical/patients/{grantId}` | Search, status filter, pagination, approved grant detail, record timeline and media links |
| Clinical submissions | `/clinical/submissions`, `/clinical/submissions/{submissionId}` | Status filtering, pagination, detail/review context, read-only patient context |
| Care submission | `/clinical/patients/{grantId}/care-submissions/new` | Visit, vaccination, medication and condition repeaters; validation; cancel/submit; review explanation |

## Reusable component contract

Components live under `resources/views/components/business` and are deliberately small so Livewire actions remain in their owning screen:

- `shell`: authenticated sidebar, current section, organisation switcher, mobile section selector, alert region and slot.
- `section-heading`: eyebrow/title/description/actions rhythm shared by overview, forms and list screens.
- `status`: `neutral`, `good`, `warn` and `blue` tones with one accessible semantic surface.
- `button`: `primary`, `secondary`, `danger`, and `text` variants; all use regular weight and shared focus treatment.
- `empty-state`: title/description/action slot for no-data states.
- `data-table` and `filter-bar`: labelled data regions and search/filter composition points.
- `modal` and `drawer`: one accessible overlay contract for claims, location edits, service edits and team-role edits. Both accept `heading`, `body`, `footer`, and `actions` named slots. They render closed and inert by default; `open` is the explicit server state.
- `field`: shared labelled form primitive with optional hint/error output.
- `toast`, `toast-region`, and `confirmation`: live, dismissible transient feedback with timed removal and a consistent destructive-action boundary.
- Clinical screens use their own domain-level components on the same tokens: `button`, `status`, `empty-state`, `alert`, `pagination`, `segmented-filter`, `search`, `filter-select`, `field`, `select`, and `textarea`. This keeps clinical semantics explicit while removing page-specific action, status, feedback, filter, and validation markup.

The clinical layout owns the clinical header/navigation and reuses the same token system. Clinical tables, repeaters and patient cards use the same border, control, status and empty-state contracts rather than page-specific colour values. Every labelled clinical field has a stable `for`/`id` pair, its hint and error are connected through `aria-describedby`, and invalid state is exposed through `aria-invalid`.

### Overlay lifecycle

`resources/js/business.js` owns the behaviour once for every modal and drawer. Page templates must not add page-specific Escape, focus, scroll, or backdrop handlers.

1. A closed overlay has `hidden`, `inert`, `aria-hidden="true"`, and `data-overlay-open-state="false"`.
2. Opening stores the invoking element, removes `hidden`/`inert`, locks body scrolling, and moves focus to `[data-overlay-initial-focus]`, the first focusable control, or the dialog panel.
3. `Tab` and `Shift+Tab` remain inside the active overlay. `Escape`, the close control, and an enabled backdrop close request all use the same close path.
4. Closing restores body styles and focus. Removed Livewire nodes are cleaned from the overlay stack, including during `wire:navigate`.
5. Multiple overlays are stacked safely, although normal workflows should show one at a time.

Footer actions use the HTML `form` attribute so the form remains in the `body` slot while the primary and secondary actions stay in the fixed footer. Headings and action rows must not be repeated inside the body.

### Toast lifecycle

All notices render inside `x-business.toast-region`. The controller discovers server-rendered and Livewire-added toasts, dismisses them after the declared timeout, preserves an explicit close control, and supports safe client notices through a `business:toast` custom event. Event message text is assigned through `textContent`, never HTML.

## Appearance and responsive behavior

`resources/js/business.js` cycles `system → light → dark`, persists `zigpaw-business-theme`, and updates every `[data-theme-toggle]`. Both layouts set an early saved override to avoid a flash. Explicit `data-theme="light|dark"` selectors are separate from the OS media query; the stylesheet does not depend on an invalid combined media selector.

Instrument Sans is bundled only at regular (400) and medium (500) weights. Body text, fields, buttons, rows, headings, metrics, and status labels use 400. The restrained 500 weight is reserved for compact eyebrows; no synthetic bold or light face is requested.

- Desktop: fixed business rail, wide multi-column metrics/listing grids, clinical patient cards and media grids.
- Small laptop/tablet: reduced rail, two-column listings/patients, wrapped headings and filters.
- Mobile: stacked rail, native section selector, one-column forms/cards, horizontally scrollable clinical nav, two-column metrics, stacked care form footer.
- Keyboard: skip links, visible focus rings, labelled selectors/search fields, current navigation state, disabled pagination controls, trapped overlay focus, Escape close, and focus restoration.

## Verification commands

Run these from the `zigpaw-business` repository before calling a UI change complete:

```bash
php artisan test
npm run build
php artisan view:clear
php artisan view:cache
```

`BusinessUiComponentsTest` verifies the closed/open ARIA contract, named overlay slots, close/initial-focus hooks, toast live-region/dismissal hooks, the keyboard/focus/scroll/backdrop controller contract, and the maximum 500 font weight. `BusinessWorkspaceTest` verifies the API-backed claim modal and service drawer states in their real Livewire workflows. `ClinicalUiComponentsTest` verifies clinical label/hint/error relationships, reusable feedback/action/status/pagination markup, segmented-filter state, and prevents raw page-specific action, status, or empty-state primitives from returning.

## Follow-up contract

When a new business screen is added, it must use the shell and component primitives, keep destructive actions behind a confirmation, expose loading/error/empty states, and add its route/action/state row to this matrix. New overlays must use the shared components and named slots; do not render a custom `role="dialog"` in a page. API failures should be shown as a concise inline alert or toast; never expose provider URLs, tokens or internal architecture details.
