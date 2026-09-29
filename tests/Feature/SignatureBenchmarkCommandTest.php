<?php

namespace Tests\Feature;

use App\Services\Crypto\KeyPairService;
use App\Services\Crypto\SignatureService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureBenchmarkCommandTest extends TestCase
{
    public function test_command_benchmarks_production_service_and_writes_complete_csv_reports(): void
    {
        Storage::fake('local');

        $exitCode = Artisan::call('benchmark:signature', ['--runs' => 3]);
        $output = Artisan::output();
        $files = Storage::disk('local')->allFiles('benchmarks');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('SIGNING', $output);
        $this->assertStringContainsString('VERIFICATION', $output);
        $this->assertStringContainsString('SIZES', $output);
        $this->assertCount(3, $files);

        $signingPath = $this->matchingPath($files, '/signature_sign_.*\.csv\z/');
        $verificationPath = $this->matchingPath($files, '/signature_verify_.*\.csv\z/');
        $summaryPath = $this->matchingPath($files, '/signature_summary_.*\.csv\z/');
        $signingRows = $this->csvRows($signingPath);
        $verificationRows = $this->csvRows($verificationPath);
        $summary = $this->summary($summaryPath);

        $this->assertSame(['run', 'elapsed_ms'], $signingRows[0]);
        $this->assertSame(['run', 'elapsed_ms'], $verificationRows[0]);
        $this->assertCount(4, $signingRows);
        $this->assertCount(4, $verificationRows);
        $this->assertSame(['1', '2', '3'], array_column(array_slice($signingRows, 1), 0));
        $this->assertSame(['1', '2', '3'], array_column(array_slice($verificationRows, 1), 0));

        foreach ([...array_slice($signingRows, 1), ...array_slice($verificationRows, 1)] as $row) {
            $this->assertMatchesRegularExpression('/\A\d+\.\d{6}\z/', $row[1]);
            $this->assertGreaterThanOrEqual(0.0, (float) $row[1]);
        }

        $this->assertSame('3', $summary['signing.runs']['value']);
        $this->assertSame('3', $summary['verification.runs']['value']);
        $this->assertSame('3', $summary['verification.successful_samples']['value']);
        $this->assertSame('256', $summary['sizes.signature_raw_bytes']['value']);
        $this->assertSame('344', $summary['sizes.signature_base64_length']['value']);
        $this->assertGreaterThan(0, (int) $summary['sizes.public_key_pem_bytes']['value']);

        foreach (['mean', 'median', 'minimum', 'maximum', 'sample_standard_deviation'] as $metric) {
            $this->assertArrayHasKey('signing.'.$metric, $summary);
            $this->assertArrayHasKey('verification.'.$metric, $summary);
            $this->assertGreaterThanOrEqual(0.0, (float) $summary['signing.'.$metric]['value']);
            $this->assertGreaterThanOrEqual(0.0, (float) $summary['verification.'.$metric]['value']);
        }

        $signingSamples = array_map(
            fn (array $row): float => (float) $row[1],
            array_slice($signingRows, 1),
        );
        $this->assertEqualsWithDelta(
            array_sum($signingSamples) / count($signingSamples),
            (float) $summary['signing.mean']['value'],
            0.000001,
        );

        $keyPair = (new KeyPairService)->generate();

        try {
            $productionSignature = (new SignatureService)->sign(
                hash('sha256', 'production-size-check', true),
                $keyPair['private_key'],
            );
            $this->assertSame(strlen($productionSignature), (int) $summary['sizes.signature_raw_bytes']['value']);
            $this->assertSame(
                strlen(base64_encode($productionSignature)),
                (int) $summary['sizes.signature_base64_length']['value'],
            );
        } finally {
            sodium_memzero($keyPair['private_key']);
        }
    }

    public function test_command_rejects_runs_less_than_one_without_writing_reports(): void
    {
        Storage::fake('local');

        $exitCode = Artisan::call('benchmark:signature', ['--runs' => 0]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'The --runs option must be an integer greater than or equal to 1.',
            Artisan::output(),
        );
        $this->assertSame([], Storage::disk('local')->allFiles('benchmarks'));
    }

    /** @param list<string> $files */
    private function matchingPath(array $files, string $pattern): string
    {
        $matches = array_values(array_filter(
            $files,
            fn (string $path): bool => preg_match($pattern, $path) === 1,
        ));

        $this->assertCount(1, $matches);

        return $matches[0];
    }

    /** @return list<list<string>> */
    private function csvRows(string $path): array
    {
        $contents = Storage::disk('local')->get($path);
        $lines = preg_split('/\R/', trim($contents));
        $this->assertIsArray($lines);

        return array_map(
            fn (string $line): array => str_getcsv($line, ',', '"', ''),
            $lines,
        );
    }

    /** @return array<string, array{value: string, unit: string}> */
    private function summary(string $path): array
    {
        $rows = $this->csvRows($path);
        $this->assertSame(['section', 'metric', 'value', 'unit'], array_shift($rows));
        $summary = [];

        foreach ($rows as [$section, $metric, $value, $unit]) {
            $summary[$section.'.'.$metric] = ['value' => $value, 'unit' => $unit];
        }

        return $summary;
    }
}
