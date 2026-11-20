<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('<title>Zigpaw Partners</title>', false)
            ->assertSee('A workspace for vet clinics, groomers, sitters and other pet services.')
            ->assertDontSee('Zigpaw business');
    }
}
