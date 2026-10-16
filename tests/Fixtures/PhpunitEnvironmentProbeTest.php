<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Support\Env;
use PHPUnit\Framework\TestCase;

final class PhpunitEnvironmentProbeTest extends TestCase
{
    public function test_native_bootstrap_replaces_inherited_values_in_every_environment_adapter(): void
    {
        $configuration = new \DOMDocument;
        self::assertTrue($configuration->load(dirname(__DIR__, 2).'/phpunit.xml', LIBXML_NONET));
        foreach ($configuration->getElementsByTagName('env') as $variable) {
            $name = $variable->getAttribute('name');
            $expected = match ($variable->getAttribute('value')) {
                'true' => '1',
                'false' => '',
                default => $variable->getAttribute('value'),
            };
            self::assertSame($expected, getenv($name), $name);
            self::assertSame($expected, $_ENV[$name] ?? null, $name);
            self::assertSame($expected, $_SERVER[$name] ?? null, $name);
            self::assertSame($expected, Env::getRepository()->get($name), $name);
        }
    }
}
