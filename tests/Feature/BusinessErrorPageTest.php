<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BusinessErrorPageTest extends TestCase
{
    #[DataProvider('errorStatuses')]
    public function test_error_component_renders_safe_status_copy_and_recovery(string $status, string $title): void
    {
        $html = Blade::render('<x-business.error-page status="'.$status.'" />');

        $this->assertStringContainsString('id="business-error-title"', $html);
        $this->assertStringContainsString('>'.$status.'</div>', $html);
        $this->assertStringContainsString($title, $html);
        $this->assertStringContainsString('href="'.route('auth.login').'"', $html);
        $this->assertStringNotContainsString('exception', strtolower($html));
        $this->assertStringNotContainsString('stack trace', strtolower($html));
    }

    public function test_signed_in_business_user_gets_workspace_recovery(): void
    {
        session()->put('platform.oauth.token_handle', 'opaque-session-handle');

        $html = Blade::render('<x-business.error-page status="503" />');

        $this->assertStringContainsString('The workspace is taking a short break', $html);
        $this->assertStringContainsString('href="'.route('dashboard').'"', $html);
        $this->assertStringNotContainsString('API', $html);
        $this->assertStringNotContainsString('database', strtolower($html));
    }

    public function test_each_framework_error_view_is_available(): void
    {
        foreach (array_column($this->errorStatuses(), 0) as $status) {
            $this->assertFileExists(resource_path('views/errors/'.$status.'.blade.php'));
        }
    }

    public function test_missing_routes_render_the_branded_not_found_response(): void
    {
        $response = $this->get('/__missing-page-for-error-qa');

        $response->assertNotFound();
        $response->assertSee('That page is not here');
        $response->assertSee('Business sign in');
        $response->assertDontSee('Whoops');
        $response->assertDontSee('stack trace');
    }

    /** @return array<string, array{string, string}> */
    public static function errorStatuses(): array
    {
        return [
            'forbidden' => ['403', 'You do not have access to this page'],
            'missing' => ['404', 'That page is not here'],
            'expired' => ['419', 'That form has expired'],
            'rate_limited' => ['429', 'Please take a short pause'],
            'failed' => ['500', 'We could not complete that request'],
            'gateway' => ['502', 'The workspace is briefly out of reach'],
            'unavailable' => ['503', 'The workspace is taking a short break'],
            'gateway_timeout' => ['504', 'The workspace took too long to respond'],
        ];
    }
}
