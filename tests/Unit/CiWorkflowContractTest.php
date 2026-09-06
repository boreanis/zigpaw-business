<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CiWorkflowContractTest extends TestCase
{
    public function test_static_analysis_step_has_isolated_application_configuration(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/ci.yml');
        $stepStart = strpos($workflow, '      - name: Run static analysis');

        self::assertNotFalse($stepStart);

        $step = substr($workflow, $stepStart, strpos($workflow, "\n      - name:", $stepStart + 1) - $stepStart);

        self::assertStringContainsString('run: composer analyse', $step);
        self::assertStringContainsString('APP_ENV: testing', $step);
        self::assertStringContainsString('APP_DEBUG: false', $step);
        self::assertStringContainsString('APP_KEY: base64:', $step);
        self::assertStringContainsString('LOG_CHANNEL: stderr', $step);
        self::assertStringContainsString('CACHE_STORE: array', $step);
        self::assertStringContainsString('SESSION_DRIVER: array', $step);
        self::assertStringContainsString('QUEUE_CONNECTION: sync', $step);
    }

    public function test_static_analysis_script_has_the_release_gate_memory_budget(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertStringContainsString('--memory-limit=1G', $composer['scripts']['analyse']);
    }

    public function test_phpunit_bootstrap_has_a_disposable_well_formed_application_key(): void
    {
        $document = new \DOMDocument;
        self::assertTrue($document->load(dirname(__DIR__, 2).'/phpunit.xml'));

        $nodes = (new \DOMXPath($document))->query('/phpunit/php/env[@name="APP_KEY"]');
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length);

        $value = $nodes->item(0)->attributes?->getNamedItem('value')?->nodeValue;
        self::assertIsString($value);
        self::assertStringStartsWith('base64:', $value);

        $decoded = base64_decode(substr($value, 7), true);
        self::assertIsString($decoded);
        self::assertSame(32, strlen($decoded));
    }

    public function test_phpunit_bootstrap_uses_canonical_fake_service_origins(): void
    {
        $document = new \DOMDocument;
        self::assertTrue($document->load(dirname(__DIR__, 2).'/phpunit.xml'));

        $nodes = (new \DOMXPath($document))->query('/phpunit/php/env');
        self::assertNotFalse($nodes);

        $values = [];
        foreach ($nodes as $node) {
            $name = $node->attributes?->getNamedItem('name')?->nodeValue;
            $value = $node->attributes?->getNamedItem('value')?->nodeValue;
            if (is_string($name) && is_string($value)) {
                $values[$name] = $value;
            }
        }

        self::assertSame('https://business.zigpaw.test', $values['APP_URL'] ?? null);
        self::assertSame('https://api.zigpaw.test', $values['PLATFORM_API_URL'] ?? null);
        self::assertSame('https://login.zigpaw.test', $values['PLATFORM_AUTH_URL'] ?? null);
        self::assertSame('https://business.zigpaw.test/auth/callback', $values['PLATFORM_OAUTH_REDIRECT_URI'] ?? null);
        self::assertSame('https://business.zigpaw.test/clinical/auth/callback', $values['PLATFORM_CLINICAL_OAUTH_REDIRECT_URI'] ?? null);
    }

    public function test_ci_builds_assets_before_portal_tests_need_the_vite_manifest(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/ci.yml');
        $build = strpos($workflow, '      - name: Build production assets');
        $tests = strpos($workflow, '      - name: Run portal tests');

        self::assertIsInt($build);
        self::assertIsInt($tests);
        self::assertLessThan($tests, $build);
    }
}
