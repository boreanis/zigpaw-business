<?php

namespace Tests\Feature;

use App\Services\PlatformApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformApiClientTest extends TestCase
{
    public function test_it_sends_the_active_organization_header_only_when_selected(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/partners/me' => Http::response(['data' => ['id' => 'partner-1']], 200),
        ]);

        $partner = app(PlatformApiClient::class)->get('/v1/partners/me', 'access-token', 'organization-1');

        $this->assertSame('partner-1', $partner['id']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-token')
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1'));
    }
}
