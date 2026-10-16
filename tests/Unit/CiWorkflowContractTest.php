<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

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
        $root = dirname(__DIR__, 2);
        $document = new \DOMDocument;
        self::assertTrue($document->load($root.'/phpunit.xml'));

        $configuration = $document->documentElement;
        self::assertNotNull($configuration);
        foreach (['failOnWarning', 'failOnRisky', 'failOnSkipped', 'failOnIncomplete'] as $attribute) {
            self::assertSame('true', $configuration->getAttribute($attribute));
        }

        $environment = $root.'/tests/Fixtures/hermetic.env';
        self::assertFileExists($environment);
        self::assertDoesNotMatchRegularExpression('/^[A-Z][A-Z0-9_]*=/m', (string) file_get_contents($environment));
        self::assertStringContainsString(
            "->useEnvironmentPath(__DIR__.'/Fixtures')->loadEnvironmentFrom('hermetic.env')",
            (string) file_get_contents($root.'/tests/TestCase.php'),
        );

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
        self::assertSame('https://auth.zigpaw.test', $values['PLATFORM_AUTH_URL'] ?? null);
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

    public function test_native_phpunit_bootstrap_overrides_poisoned_inherited_runtime_settings(): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = new \DOMDocument;
        self::assertTrue($configuration->load($root.'/phpunit.xml', LIBXML_NONET));
        $variables = $configuration->getElementsByTagName('env');
        self::assertGreaterThan(0, $variables->length);

        // Child checks receive no undeclared parent application credentials.
        $environment = array_fill_keys(array_unique([
            ...array_keys(getenv() ?: []), ...array_keys($_ENV), ...array_keys($_SERVER),
        ]), false);
        $environment['PATH'] = getenv('PATH') ?: '/usr/bin:/bin';
        $environment['TMPDIR'] = sys_get_temp_dir();
        foreach ($variables as $variable) {
            self::assertSame('true', $variable->getAttribute('force'), $variable->getAttribute('name'));
            $environment[$variable->getAttribute('name')] = 'synthetic-inherited-poison';
        }
        $environment['SESSION_DRIVER'] = 'redis';
        $environment['CACHE_STORE'] = 'redis';
        $environment['QUEUE_CONNECTION'] = 'redis';
        $environment['APP_ENV'] = 'production';

        // This probe never boots Laravel or resolves a Redis/database adapter,
        // so an isolation regression cannot touch another runtime's state.
        $process = new Process([
            PHP_BINARY, $root.'/vendor/bin/phpunit', '--configuration', $root.'/phpunit.xml',
            '--no-logging', '--fail-on-warning', '--fail-on-risky', '--fail-on-skipped',
            $root.'/tests/Fixtures/PhpunitEnvironmentProbeTest.php',
        ], $root, $environment, timeout: 30);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }
}
