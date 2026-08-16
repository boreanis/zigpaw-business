<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BusinessWorkspace;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerDashboardTest extends TestCase
{
    public function test_it_prompts_for_platform_sign_in_when_no_portal_token_exists(): void
    {
        Livewire::test(BusinessWorkspace::class)
            ->assertSee('Manage the work around every pet.');
    }
}
