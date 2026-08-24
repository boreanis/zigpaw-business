<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

#[Signature('business:sync-contracts {business_source? : Absolute path to business-v1.json} {clinical_source? : Absolute path to clinical-v1.json} {--check : Verify contract copies and the generated route allowlist without writing}')]
#[Description('Copy the released Business contracts and generate the BFF route allowlist')]
class SyncBusinessApiContracts extends Command
{
    public function handle(): int
    {
        try {
            $sources = [
                'business-v1' => $this->source('business_source', 'business_contract_source'),
                'clinical-v1' => $this->source('clinical_source', 'clinical_contract_source'),
            ];
            $documents = [];
            $encoded = [];

            foreach ($sources as $contract => $source) {
                $encoded[$contract] = (string) File::get($source);
                $documents[$contract] = json_decode($encoded[$contract], true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($documents[$contract])) {
                    throw new RuntimeException("The {$contract} contract is not a JSON object.");
                }
            }

            $operations = [];

            foreach ($documents as $contract => $document) {
                $operations = [...$operations, ...$this->operations($contract, $document)];
            }

            $checksums = array_map(static fn (string $contents): string => hash('sha256', $contents), $encoded);
            $generated = $this->generatedClass($operations, $checksums);
            $targets = [
                'business-v1' => base_path('contracts/business-v1.json'),
                'clinical-v1' => base_path('contracts/clinical-v1.json'),
            ];
            $classTarget = app_path('Support/Generated/BusinessApiOperations.php');

            if ((bool) $this->option('check')) {
                foreach ($targets as $contract => $target) {
                    if (! File::exists($target) || ! hash_equals((string) File::get($target), $encoded[$contract])) {
                        throw new RuntimeException("The local {$contract} contract is stale. Run business:sync-contracts with the released contracts.");
                    }
                }

                if (! File::exists($classTarget) || ! hash_equals((string) File::get($classTarget), $generated)) {
                    throw new RuntimeException('The generated Business API allowlist is stale. Run business:sync-contracts with the released contracts.');
                }

                $this->components->info('Business contracts and generated route allowlist match.');

                return self::SUCCESS;
            }

            foreach ($targets as $contract => $target) {
                File::ensureDirectoryExists(dirname($target));
                File::put($target, $encoded[$contract]);
            }

            File::ensureDirectoryExists(dirname($classTarget));
            File::put($classTarget, $generated);
            $this->components->info('Synced Business contracts and generated BusinessApiOperations.');

            return self::SUCCESS;
        } catch (\JsonException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function source(string $argument, string $configKey): string
    {
        $source = $this->argument($argument) ?: config("platform.{$configKey}");

        if (! is_string($source) || $source === '' || ! File::isFile($source) || ! File::isReadable($source)) {
            throw new RuntimeException("Provide a readable absolute {$argument} path.");
        }

        return $source;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{contract: string, operation_id: string, method: string, path: string}>
     */
    private function operations(string $contract, array $document): array
    {
        if (($document['openapi'] ?? null) !== '3.1.2' || ! is_array($document['paths'] ?? null)) {
            throw new RuntimeException("The source is not the expected OpenAPI 3.1.2 {$contract} contract.");
        }

        $operations = [];

        foreach ($document['paths'] as $path => $pathItem) {
            if (! is_string($path) || ! str_starts_with($path, '/business/') || ! is_array($pathItem)) {
                throw new RuntimeException("The {$contract} contract contains an invalid path.");
            }

            foreach ($pathItem as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true) || ! is_array($operation)) {
                    throw new RuntimeException("The {$contract} contract contains an invalid HTTP operation.");
                }

                $operationId = $operation['operationId'] ?? null;

                if (! is_string($operationId) || ! str_starts_with($operationId, 'v1.business.')) {
                    throw new RuntimeException("The {$contract} contract contains an invalid operationId.");
                }

                $operations[] = [
                    'contract' => $contract,
                    'operation_id' => $operationId,
                    'method' => strtoupper($method),
                    'path' => '/v1'.$path,
                ];
            }
        }

        if ($operations === []) {
            throw new RuntimeException("The {$contract} contract contains no Business operations.");
        }

        return $operations;
    }

    /**
     * @param  list<array{contract: string, operation_id: string, method: string, path: string}>  $operations
     * @param  array<string, string>  $checksums
     */
    private function generatedClass(array $operations, array $checksums): string
    {
        usort($operations, static fn (array $left, array $right): int => [$left['path'], $left['method']] <=> [$right['path'], $right['method']]);
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace App\\Support\\Generated;',
            '',
            '/**',
            ' * GENERATED from contracts/business-v1.json and contracts/clinical-v1.json.',
            ' * Do not edit manually; run `php artisan business:sync-contracts` instead.',
            ' */',
            'final class BusinessApiOperations',
            '{',
            '    /** @var array<string, string> */',
            '    public const CONTRACT_SHA256 = [',
        ];

        foreach ($checksums as $contract => $checksum) {
            $lines[] = "        '{$contract}' => '{$checksum}',";
        }

        $lines = [...$lines,
            '    ];',
            '',
            '    /** @var list<array{contract: string, operation_id: string, method: string, path: string}> */',
            '    private const OPERATIONS = [',
        ];

        foreach ($operations as $operation) {
            $lines[] = '        [';
            $lines[] = "            'contract' => '{$operation['contract']}',";
            $lines[] = "            'operation_id' => '{$operation['operation_id']}',";
            $lines[] = "            'method' => '{$operation['method']}',";
            $lines[] = "            'path' => '{$operation['path']}',";
            $lines[] = '        ],';
        }

        $lines = [...$lines,
            '    ];',
            '',
            '    public static function allows(string $method, string $path): bool',
            '    {',
            '        $method = strtoupper($method);',
            "        \$path = '/'.ltrim((string) str(\$path)->before('?'), '/');",
            '',
            '        foreach (self::OPERATIONS as $operation) {',
            "            if (\$operation['method'] !== \$method) {",
            '                continue;',
            '            }',
            '',
            "            \$pattern = preg_quote(\$operation['path'], '#');",
            "            \$pattern = preg_replace('/\\\\\\{[^}]+\\\\\\}/', '[^/]+', \$pattern);",
            '',
            "            if (is_string(\$pattern) && preg_match('#^'.\$pattern.'\$#D', \$path) === 1) {",
            '                return true;',
            '            }',
            '        }',
            '',
            '        return false;',
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }
}
