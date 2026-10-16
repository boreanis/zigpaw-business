<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$configuration = new DOMDocument;
if (! $configuration->load(dirname(__DIR__).'/phpunit.xml', LIBXML_NONET)) {
    throw new RuntimeException('The synthetic PHPUnit configuration could not be loaded.');
}

// PHPUnit forces getenv()/$_ENV, while Dotenv also reads $_SERVER first.
// Keep that adapter on the same explicitly declared synthetic test values.
foreach ($configuration->getElementsByTagName('env') as $variable) {
    $name = $variable->getAttribute('name');
    $value = $_ENV[$name] ?? null;
    if ($variable->getAttribute('force') !== 'true' || ! is_string($value) || getenv($name) !== $value) {
        throw new RuntimeException('PHPUnit must force its synthetic environment variable: '.$name);
    }

    $_SERVER[$name] = $value;
}
