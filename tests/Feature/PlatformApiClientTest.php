<?php

namespace Tests\Feature;

use App\Services\PlatformApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformApiClientTest extends TestCase
{
    public function test_it_sends_the_bearer_token_to_the_clinical_api(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/me' => Http::response(['data' => ['id' => 'clinician-1']], 200),
        ]);

        $identity = app(PlatformApiClient::class)->get('/v1/vets/me', 'access-token');

        $this->assertSame('clinician-1', $identity['id']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-token'));
    }
}
