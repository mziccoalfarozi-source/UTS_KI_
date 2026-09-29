<?php

namespace App\Console\Commands;

use App\Services\Crypto\KeyPairService;
use App\Services\Crypto\SignatureService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('benchmark:signature {--runs=30 : Number of signing and verification samples}')]
#[Description('Benchmark the production RSA-PSS signature service')]
class SignatureBenchmark extends Command
{
    private const WARM_UP_RUNS = 3;

    public function handle(KeyPairService $keyPairService, SignatureService $signatureService): int
    {
        $runs = $this->resolveRuns();

        if ($runs === null) {
            return self::FAILURE;
        }

        $keyPair = null;

        try {
            $keyPair = $keyPairService->generate();
            $digest = hash('sha256', 'secure-document-signature-benchmark-fixture', true);

            $this->warmUp($signatureService, $digest, $keyPair['private_key'], $keyPair['public_key']);
            $signingSamples = $this->benchmarkSigning(
                $signatureService,
                $digest,
                $keyPair['private_key'],
                $keyPair['public_key'],
                $runs,
            );

            $verificationSignature = $signatureService->sign($digest, $keyPair['private_key']);

            if (! $signatureService->verify($digest, $verificationSignature, $keyPair['public_key'])) {
                throw new RuntimeException('Verification fixture failed its sanity check.');
            }

            $verificationSamples = $this->benchmarkVerification(
                $signatureService,
                $digest,
                $verificationSignature,
                $keyPair['public_key'],
                $runs,
            );
            $signingStatistics = $this->statistics($signingSamples);
            $verificationStatistics = $this->statistics($verificationSamples);
            $sizes = [
                'signature_raw_bytes' => strlen($verificationSignature),
                'signature_base64_length' => strlen(base64_encode($verificationSignature)),
                'public_key_pem_bytes' => strlen($keyPair['public_key']),
            ];

            $paths = $this->writeReports(
                $signingSamples,
                $verificationSamples,
                $signingStatistics,
                $verificationStatistics,
                $sizes,
            );

            $this->renderResults('SIGNING', $signingStatistics);
            $this->newLine();
            $this->renderResults('VERIFICATION', $verificationStatistics);
            $this->newLine();
            $this->line('<info>SIZES</info>');
            $this->table(['Metric', 'Value'], [
                ['Signature raw bytes', (string) $sizes['signature_raw_bytes']],
                ['Signature Base64 length', (string) $sizes['signature_base64_length']],
                ['Public key PEM bytes', (string) $sizes['public_key_pem_bytes']],
            ]);
            $this->newLine();
            $this->line('CSV reports:');

            foreach ($paths as $path) {
                $this->line(Storage::disk('local')->path($path));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error('Signature benchmark failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (is_array($keyPair) && isset($keyPair['private_key'])) {
                sodium_memzero($keyPair['private_key']);
            }
        }
    }

    private function resolveRuns(): ?int
    {
        $option = $this->option('runs');

        if (! is_string($option) && ! is_int($option)) {
            $this->components->error('The --runs option must be an integer greater than or equal to 1.');

            return null;
        }

        $runs = filter_var($option, FILTER_VALIDATE_INT);

        if ($runs === false || $runs < 1) {
            $this->components->error('The --runs option must be an integer greater than or equal to 1.');

            return null;
        }

        return $runs;
    }

    private function warmUp(
        SignatureService $signatureService,
        string $digest,
        string $privateKey,
        string $publicKey,
    ): void {
        for ($run = 0; $run < self::WARM_UP_RUNS; $run++) {
            $signature = $signatureService->sign($digest, $privateKey);

            if (! $signatureService->verify($digest, $signature, $publicKey)) {
                throw new RuntimeException('A warm-up signature failed verification.');
            }
        }
    }

    /** @return list<float> */
    private function benchmarkSigning(
        SignatureService $signatureService,
        string $digest,
        string $privateKey,
        string $publicKey,
        int $runs,
    ): array {
        $samples = [];

        for ($run = 0; $run < $runs; $run++) {
            $startedAt = hrtime(true);
            $signature = $signatureService->sign($digest, $privateKey);
            $samples[] = (hrtime(true) - $startedAt) / 1_000_000;

            if (! $signatureService->verify($digest, $signature, $publicKey)) {
                throw new RuntimeException('A timed signing sample failed self-verification.');
            }
        }

        return $samples;
    }

    /** @return list<float> */
    private function benchmarkVerification(
        SignatureService $signatureService,
        string $digest,
        string $signature,
        string $publicKey,
        int $runs,
    ): array {
        $samples = [];

        for ($run = 0; $run < $runs; $run++) {
            $startedAt = hrtime(true);
            $verified = $signatureService->verify($digest, $signature, $publicKey);
            $samples[] = (hrtime(true) - $startedAt) / 1_000_000;

            if (! $verified) {
                throw new RuntimeException('A timed verification sample failed.');
            }
        }

        return $samples;
    }

    /**
     * @param  list<float>  $samples
     * @return array{runs: int, mean: float, median: float, minimum: float, maximum: float, sample_standard_deviation: float}
     */
    private function statistics(array $samples): array
    {
        $runs = count($samples);
        $mean = array_sum($samples) / $runs;
        $sorted = $samples;
        sort($sorted, SORT_NUMERIC);
        $middle = intdiv($runs, 2);
        $median = $runs % 2 === 0
            ? ($sorted[$middle - 1] + $sorted[$middle]) / 2
            : $sorted[$middle];
        $sumOfSquaredDifferences = array_sum(array_map(
            fn (float $sample): float => ($sample - $mean) ** 2,
            $samples,
        ));

        return [
            'runs' => $runs,
            'mean' => $mean,
            'median' => $median,
            'minimum' => min($samples),
            'maximum' => max($samples),
            'sample_standard_deviation' => $runs > 1
                ? sqrt($sumOfSquaredDifferences / ($runs - 1))
                : 0.0,
        ];
    }

    /**
     * @param  list<float>  $signingSamples
     * @param  list<float>  $verificationSamples
     * @param  array{runs: int, mean: float, median: float, minimum: float, maximum: float, sample_standard_deviation: float}  $signingStatistics
     * @param  array{runs: int, mean: float, median: float, minimum: float, maximum: float, sample_standard_deviation: float}  $verificationStatistics
     * @param  array{signature_raw_bytes: int, signature_base64_length: int, public_key_pem_bytes: int}  $sizes
     * @return list<string>
     */
    private function writeReports(
        array $signingSamples,
        array $verificationSamples,
        array $signingStatistics,
        array $verificationStatistics,
        array $sizes,
    ): array {
        $timestamp = now()->format('Ymd_His_u');
        $paths = [
            'benchmarks/signature_sign_'.$timestamp.'.csv',
            'benchmarks/signature_verify_'.$timestamp.'.csv',
            'benchmarks/signature_summary_'.$timestamp.'.csv',
        ];
        $reports = [
            $this->rawCsv($signingSamples),
            $this->rawCsv($verificationSamples),
            $this->summaryCsv($signingStatistics, $verificationStatistics, $sizes),
        ];
        $writtenPaths = [];

        try {
            foreach ($paths as $index => $path) {
                if (! Storage::disk('local')->put($path, $reports[$index])) {
                    throw new RuntimeException('A benchmark CSV report could not be written.');
                }

                $writtenPaths[] = $path;
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($writtenPaths);

            throw $exception;
        }

        return $paths;
    }

    /** @param list<float> $samples */
    private function rawCsv(array $samples): string
    {
        $rows = [['run', 'elapsed_ms']];

        foreach ($samples as $index => $sample) {
            $rows[] = [(string) ($index + 1), $this->formatFloat($sample)];
        }

        return $this->csv($rows);
    }

    /**
     * @param  array{runs: int, mean: float, median: float, minimum: float, maximum: float, sample_standard_deviation: float}  $signing
     * @param  array{runs: int, mean: float, median: float, minimum: float, maximum: float, sample_standard_deviation: float}  $verification
     * @param  array{signature_raw_bytes: int, signature_base64_length: int, public_key_pem_bytes: int}  $sizes
     */
    private function summaryCsv(array $signing, array $verification, array $sizes): string
    {
        $rows = [['section', 'metric', 'value', 'unit']];

        foreach (['signing' => $signing, 'verification' => $verification] as $section => $statistics) {
            foreach ($statistics as $metric => $value) {
                $rows[] = [
                    $section,
                    $metric,
                    is_float($value) ? $this->formatFloat($value) : (string) $value,
                    $metric === 'runs' ? 'count' : 'ms',
                ];
            }
        }

        $rows[] = ['verification', 'successful_samples', (string) $verification['runs'], 'count'];
        $rows[] = ['sizes', 'signature_raw_bytes', (string) $sizes['signature_raw_bytes'], 'bytes'];
        $rows[] = ['sizes', 'signature_base64_length', (string) $sizes['signature_base64_length'], 'characters'];
        $rows[] = ['sizes', 'public_key_pem_bytes', (string) $sizes['public_key_pem_bytes'], 'bytes'];

        return $this->csv($rows);
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            throw new RuntimeException('A temporary CSV stream could not be opened.');
        }

        try {
            foreach ($rows as $row) {
                if (fputcsv($stream, $row, ',', '"', '') === false) {
                    throw new RuntimeException('A benchmark CSV row could not be written.');
                }
            }

            rewind($stream);
            $contents = stream_get_contents($stream);

            if ($contents === false) {
                throw new RuntimeException('A benchmark CSV report could not be read.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array{runs: int, mean: float, median: float, minimum: float, maximum: float, sample_standard_deviation: float}  $statistics
     */
    private function renderResults(string $title, array $statistics): void
    {
        $this->line('<info>'.$title.'</info>');
        $this->table(['Metric', 'Value'], [
            ['Runs', (string) $statistics['runs']],
            ['Mean (ms)', $this->formatFloat($statistics['mean'])],
            ['Median (ms)', $this->formatFloat($statistics['median'])],
            ['Min (ms)', $this->formatFloat($statistics['minimum'])],
            ['Max (ms)', $this->formatFloat($statistics['maximum'])],
            ['Sample Std Dev (ms)', $this->formatFloat($statistics['sample_standard_deviation'])],
        ]);
    }

    private function formatFloat(float $value): string
    {
        return number_format($value, 6, '.', '');
    }
}
