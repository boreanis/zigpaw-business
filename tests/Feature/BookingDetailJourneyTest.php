<?php

namespace Tests\Feature;

use App\Livewire\BusinessWorkspace;
use App\Support\PortalAccessTokenStore;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

final class BookingDetailJourneyTest extends TestCase
{
    private const BOOKING = '01915000-0011-7000-8000-000000000011';

    private const OTHER = '01915000-0011-7000-8000-000000000022';

    private const PET = '01915000-0011-7000-8000-000000000033';

    private const GRANT = '01915000-0011-7000-8000-000000000044';

    private const WINDOW = '01915000-0011-7000-8000-000000000055';

    private string $bookingStatus = 'requested';

    private string $receiptId = self::BOOKING;

    private bool $profileAllowed = true;

    private bool $responseWindowOpen = true;

    private bool $multipleOrganizations = false;

    private bool $capacityChanged = false;

    /** @var array<string, string>|null */
    private ?array $proposal = null;

    private ?string $appointmentTimezone = 'America/Edmonton';

    /** @var list<string|null> */
    private array $mutationKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23T15:00:00+00:00');
        app(PortalAccessTokenStore::class)->put([
            'access_token' => 'synthetic-booking-operator-token',
            'refresh_token' => 'synthetic-booking-refresh-token',
            'expires_in' => 900,
        ]);
        Http::fake(fn (Request $request) => $this->platform($request));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_overview_and_filtered_queue_link_to_exact_booking_and_load_current_detail(): void
    {
        $link = '/bookings/'.self::BOOKING.'?status=requested';
        $this->get('/')->assertOk()->assertSee($link)->assertSee('Synthetic Pet')->assertSee('Respond by')
            ->assertSee('23/11/2026 · 14:00 MST');
        $this->get('/?section=bookings&status=requested')
            ->assertOk()->assertSee($link)->assertSee('Respond by')->assertSee('23/11/2026 · 14:00 MST')
            ->assertDontSee('Accept this time')->assertDontSee('Synthetic private appointment note');
        $this->get($link)
            ->assertOk()->assertSee('Back to booking requests')->assertSee('Respond by')->assertSee('23/11/2026 · 14:00 MST')
            ->assertSee('Synthetic private appointment note')->assertSee('Accept this time')->assertSee('Decline');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/bookings/'.self::BOOKING)
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/bookings?status=requested&page=1&per_page=25'));
    }

    public function test_message_history_stays_with_the_exact_booking_and_outside_the_browser_snapshot(): void
    {
        $component = Livewire::test(BusinessWorkspace::class, ['booking' => self::BOOKING])
            ->assertSee('Updated arrival instructions')->assertSee('Older messages')
            ->call('showBookingMessagePage', 2)->assertSee('Earlier arrival instructions')->assertDontSee('Updated arrival instructions')
            ->assertSee('Newer messages');
        self::assertStringNotContainsString('Earlier arrival instructions', json_encode($component->snapshot, JSON_THROW_ON_ERROR));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/bookings/'.self::BOOKING.'/messages?page=2&per_page=10'));
    }

    public function test_returning_from_booking_restores_its_queue_page_and_row(): void
    {
        $this->get('/?section=bookings&status=requested&booking_page=2')->assertOk()
            ->assertSee(route('bookings.show', ['booking' => self::BOOKING, 'status' => 'requested', 'booking_page' => 2]));
        $this->get('/bookings/'.self::BOOKING.'?status=requested&booking_page=2')->assertOk()
            ->assertSee(route('dashboard', ['section' => 'bookings', 'status' => 'requested', 'booking_page' => 2]).'#booking-'.self::BOOKING);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/bookings?status=requested&page=2&per_page=25'));

        Livewire::withQueryParams(['section' => 'bookings', 'status' => 'requested', 'booking_page' => 99])
            ->test(BusinessWorkspace::class)->assertSet('bookingPage', 2)
            ->assertSee('SYNTHETIC-BOOKING-001')->assertDontSee('No requests need a response');
    }

    public function test_exact_request_explains_why_platform_no_longer_offers_response_controls(): void
    {
        $this->responseWindowOpen = false;

        $this->get('/bookings/'.self::BOOKING.'?status=requested')->assertOk()
            ->assertSee('This request’s response window has closed.')
            ->assertDontSee('Respond by')
            ->assertDontSee('Accept this time')
            ->assertDontSeeText('Decline');
    }

    public function test_exact_booking_requires_the_chosen_workspace_and_stays_on_that_booking(): void
    {
        $this->multipleOrganizations = true;
        $path = '/bookings/'.self::BOOKING.'?status=requested';
        $this->get($path)->assertOk()->assertSee('Choose your organisation');
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/bookings/'.self::BOOKING));

        $this->withSession(['portal.organization_id' => 'organization-2'])->get($path)
            ->assertOk()->assertSee('Booking details unavailable')->assertDontSee('Synthetic private appointment note');

        Livewire::withQueryParams(['status' => 'requested'])->test(BusinessWorkspace::class, ['booking' => self::BOOKING])
            ->call('selectOrganization', 'organization-1')
            ->assertRedirect(route('bookings.show', ['booking' => self::BOOKING, 'status' => 'requested']));
        $this->get($path)->assertOk()->assertSee('Synthetic private appointment note');
    }

    public function test_booking_link_keeps_its_id_and_filter_when_requesting_sign_in(): void
    {
        app(PortalAccessTokenStore::class)->forget();

        $this->get('/bookings/'.self::BOOKING.'?status=accepted')
            ->assertOk()->assertSee('Sign in to Partners')
            ->assertSee(route('auth.login', ['booking' => self::BOOKING, 'booking_status' => 'accepted']));
        Http::assertNothingSent();
    }

    public function test_booking_shows_the_branch_and_appointment_times_across_daylight_saving(): void
    {
        $this->get('/bookings/'.self::BOOKING)->assertOk()
            ->assertSee('North Edmonton Branch, 1 Synthetic Way, Edmonton, AB')
            ->assertSee('08/03/2026 · 01:30 MST')
            ->assertSee('08/03/2026 · 03:30 MDT')
            ->assertDontSee('08/03/2026 · 02:30 MST')
            ->assertSee('23/11/2026 · 14:00 MST');
    }

    public function test_booking_explicitly_labels_utc_when_the_appointment_timezone_is_unknown(): void
    {
        $this->appointmentTimezone = null;

        $this->get('/bookings/'.self::BOOKING)->assertOk()
            ->assertSee('08/03/2026 · 08:30 UTC')
            ->assertSee('08/03/2026 · 09:30 UTC');
    }

    public function test_booking_decision_reuses_unconfirmed_identity_and_pet_context_expires_privately(): void
    {
        $component = Livewire::test(BusinessWorkspace::class, ['booking' => self::BOOKING]);
        $component->set('bookingReply', 'Arrival instructions.');
        $this->receiptId = self::OTHER;
        $component->call('acceptBooking', self::BOOKING, self::WINDOW)
            ->assertSee('Zigpaw could not confirm the update')
            ->assertDontSee('Booking accepted.')->assertSet('bookingReply', 'Arrival instructions.');
        $this->receiptId = self::BOOKING;
        $component->call('acceptBooking', self::BOOKING, self::WINDOW)
            ->assertSee('Booking accepted.')->assertSee('Confirmed for')->assertSee('View pet profile')
            ->call('toggleBookingPetProfile', self::BOOKING)->assertSee('Synthetic private breed');
        self::assertCount(2, $this->mutationKeys);
        self::assertIsString($this->mutationKeys[0]);
        $component->assertSet('bookingReply', '');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['message'] === 'Arrival instructions.');
        self::assertSame($this->mutationKeys[0], $this->mutationKeys[1]);
        self::assertStringNotContainsString('Synthetic private breed', json_encode($component->snapshot['data'], JSON_THROW_ON_ERROR));

        $this->profileAllowed = false;
        $component->call('$refresh')->assertDontSee('Synthetic private breed')
            ->assertSee('This appointment no longer grants access to the pet profile.')
            ->assertSet('bookingPetProfileOpen', false);

        $this->profileAllowed = true;
        $component->call('toggleBookingPetProfile', self::BOOKING)->assertSee('Synthetic private breed')
            ->call('completeBooking', self::BOOKING)->assertSee('Booking marked complete.')
            ->assertDontSee('Synthetic private breed')->assertSee('View pet profile')
            ->assertSet('bookingPetProfileOpen', false);
    }

    public function test_an_unavailable_window_refreshes_the_booking_and_staff_can_offer_an_exact_local_time(): void
    {
        $component = Livewire::test(BusinessWorkspace::class, ['booking' => self::BOOKING])
            ->assertSee('Accept this time');
        $this->capacityChanged = true;
        $component->call('acceptBooking', self::BOOKING, self::WINDOW)
            ->assertSee('That time is now unavailable.')
            ->assertDontSee('Booking accepted.')
            ->assertSee('Propose another time');
        $component->set('bookingProposedTime', '2026-11-25T11:45')
            ->call('proposeBooking', self::BOOKING)
            ->assertSee('New time proposed.')
            ->assertSee('Awaiting customer confirmation')
            ->assertSet('bookingProposedTime', '');
        self::assertSame('2026-11-25T11:45:00-06:00', $this->proposal['starts_at']);
    }

    public function test_detail_actions_reject_another_booking_and_a_changed_workspace(): void
    {
        $component = Livewire::test(BusinessWorkspace::class, ['booking' => self::BOOKING]);
        $component->call('acceptBooking', self::OTHER, self::WINDOW)->assertNotFound();
        session()->put('portal.organization_id', 'organization-other');
        $component->call('acceptBooking', self::BOOKING, self::WINDOW)->assertNotFound();
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    private function platform(Request $request): Response|PromiseInterface
    {
        $prefix = 'https://api.zigpaw.test/v1/business';
        if ($request->url() === $prefix.'/organizations') {
            $organizations = [['id' => 'organization-1', 'name' => 'Synthetic Clinic', 'role' => 'manager']];
            if ($this->multipleOrganizations) {
                $organizations[] = ['id' => 'organization-2', 'name' => 'Other Synthetic Clinic', 'role' => 'manager'];
            }

            return Http::response(['data' => $organizations]);
        }
        if ($request->url() === $prefix.'/me') {
            return Http::response(['data' => [
                'organization' => ['id' => 'organization-1', 'name' => 'Synthetic Clinic'],
                'membership' => ['id' => 'membership-1', 'role' => 'manager', 'capabilities' => ['portal.view', 'bookings.manage']],
                'features' => ['booking_requests' => true],
                'display_preferences' => ['timezone' => 'America/Phoenix'],
            ]]);
        }

        $summary = [
            'id' => self::BOOKING,
            'request_number' => 'SYNTHETIC-BOOKING-001',
            'status' => $this->bookingStatus,
            'response_due_at' => '2026-11-23T21:00:00+00:00',
            'pet' => ['id' => self::PET, 'name' => 'Synthetic Pet'],
            'provider' => ['name' => 'Synthetic Clinic', 'service_name' => 'Wellness check', 'location_name' => 'North Edmonton Branch',
                'address' => '1 Synthetic Way', 'city' => 'Edmonton', 'state' => 'AB'],
        ];
        if (str_starts_with($request->url(), $prefix.'/bookings?')) {
            $page = (int) $request['page'];

            return Http::response([
                'data' => $page > 2 ? [] : [$summary],
                'meta' => ['current_page' => $page, 'last_page' => 2, 'per_page' => 25, 'total' => 26],
            ]);
        }

        $bookingPath = $prefix.'/bookings/'.self::BOOKING;
        if ($request->url() === $bookingPath.'/response' && $request->method() === 'POST') {
            $this->mutationKeys[] = $request->header('Idempotency-Key')[0] ?? null;
            if ($this->capacityChanged && $request['action'] === 'accept') {
                return Http::response(['errors' => ['window_id' => ['This time is at capacity.']]], 422);
            }
            if ($request['action'] === 'propose') {
                $this->proposal = ['id' => self::OTHER, 'source' => 'provider', 'status' => 'proposed', 'starts_at' => $request['proposed_windows'][0]['starts_at']];
            }
            $status = match ($request['action']) {
                'accept' => 'accepted',
                'propose' => 'proposed',
                'complete' => 'completed',
                default => 'declined',
            };
            if ($this->receiptId === self::BOOKING) {
                $this->bookingStatus = $status;
            }

            return Http::response(['data' => ['id' => $this->receiptId, 'status' => $status, 'requested_windows' => $this->proposal === null ? [] : [$this->proposal]]]);
        }
        if ($request->url() === $bookingPath) {
            if ($request->hasHeader('X-Zigpaw-Organization-ID', 'organization-2')) {
                return Http::response(['message' => 'This booking is unavailable in the selected workspace.'], 404);
            }

            return Http::response(['data' => $summary + [
                'appointment_timezone' => $this->appointmentTimezone,
                'requested_windows' => [
                    ['id' => self::WINDOW, 'source' => 'customer', 'status' => 'requested', 'starts_at' => '2026-03-08T08:30:00+00:00', 'availability' => ['at_capacity' => $this->capacityChanged]],
                    ['id' => self::OTHER, 'source' => 'customer', 'status' => 'requested', 'starts_at' => '2026-03-08T09:30:00+00:00', 'availability' => ['at_capacity' => $this->capacityChanged]],
                    ...($this->proposal === null ? [] : [$this->proposal]),
                ],
                'available_actions' => match ($this->bookingStatus) {
                    'requested', 'proposed' => $this->responseWindowOpen ? ['accept', 'propose', 'decline'] : [],
                    'accepted' => ['complete'],
                    default => [],
                },
                'customer_message' => 'Synthetic private appointment note',
                'confirmed_starts_at' => $this->bookingStatus === 'accepted' ? '2026-11-24T21:00:00+00:00' : null,
                'pet_access' => ['id' => self::GRANT, 'status' => in_array($this->bookingStatus, ['accepted', 'completed'], true) ? 'active' : 'revoked',
                    'expires_at' => '2026-11-25T21:00:00+00:00'],
            ]]);
        }
        if (str_starts_with($request->url(), $bookingPath.'/messages?')) {
            $page = (int) $request['page'];

            return Http::response([
                'data' => [['id' => 'message-'.$page, 'sender_type' => 'customer',
                    'created_at' => '2026-11-24T19:00:00Z', 'message' => $page === 1 ? 'Updated arrival instructions' : 'Earlier arrival instructions']],
                'meta' => ['current_page' => $page, 'last_page' => 2, 'per_page' => 10, 'total' => 11],
            ]);
        }
        if ($request->url() === $bookingPath.'/pet-context') {
            if (! $this->profileAllowed) {
                return Http::response([], 404);
            }

            return Http::response(['data' => [
                'access' => ['id' => self::GRANT, 'status' => 'active', 'expires_at' => '2026-11-25T21:00:00+00:00'],
                'pet' => ['id' => self::PET, 'name' => 'Synthetic Pet', 'species' => 'Dog', 'breed' => 'Synthetic private breed'],
            ]]);
        }

        throw new \LogicException('Unexpected synthetic request.');
    }
}
