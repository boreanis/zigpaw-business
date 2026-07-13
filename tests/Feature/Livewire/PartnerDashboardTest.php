<?php

namespace Tests\Feature\Livewire;

use App\Livewire\PartnerDashboard;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerDashboardTest extends TestCase
{
    public function test_it_prompts_for_platform_sign_in_when_no_portal_token_exists(): void
    {
        Livewire::test(PartnerDashboard::class)
            ->assertSee('Give every pet a prepared start.');
    }
}
