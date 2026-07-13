<?php

namespace Tests\Feature\Livewire;

use App\Livewire\VetDashboard;
use Livewire\Livewire;
use Tests\TestCase;

class VetDashboardTest extends TestCase
{
    public function test_it_prompts_for_platform_sign_in_when_no_portal_token_exists(): void
    {
        Livewire::test(VetDashboard::class)
            ->assertSee('Submit care context with the family in control.');
    }
}
