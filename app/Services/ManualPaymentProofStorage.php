<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ManualPaymentProofStorage
{
    public function __construct(private readonly ManualPaymentProofObjectStorage $objectStorage) {}

    /** @return array{path: string, sha256: string} */
    public function store(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new RuntimeException('Unsupported payment proof type.'),
        };
        $temporaryPath = $file->getRealPath();
        $sha256 = is_string($temporaryPath) ? hash_file('sha256', $temporaryPath) : false;
        if (!is_string($sha256)) {
            throw new RuntimeException('The payment proof could not be fingerprinted.');
        }

        $path = Str::uuid().'.'.$extension;
        if (config('payments.manual_proof_disk') === 's3') {
            $this->objectStorage->store($path, $file, $mime);

            return ['path' => $path, 'sha256' => $sha256];
        }

        $storedPath = $this->localDisk()->putFileAs('', $file, $path, ['visibility' => 'private']);
        if (!is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('The payment proof could not be stored privately.');
        }

        return ['path' => $storedPath, 'sha256' => $sha256];
    }

    public function contents(string $path): string
    {
        if (!preg_match('/^[a-f0-9-]{36}\.(?:pdf|jpg|png)$/i', $path)) {
            throw new RuntimeException('The stored payment proof path is invalid.');
        }

        if (config('payments.manual_proof_disk') === 's3') {
            return $this->objectStorage->contents($path);
        }

        $disk = $this->localDisk();
        if (!$disk->exists($path)) {
            throw new RuntimeException('The stored payment proof is missing.');
        }

        $contents = $disk->get($path);
        if (!is_string($contents)) {
            throw new RuntimeException('The stored payment proof could not be read.');
        }

        return $contents;
    }

    public function matchesHash(string $path, string $expectedHash): bool
    {
        try {
            $actualHash = hash('sha256', $this->contents($path));
        } catch (\Throwable) {
            return false;
        }

        return is_string($actualHash)
            && preg_match('/^[a-f0-9]{64}$/i', $expectedHash) === 1
            && hash_equals(strtolower($expectedHash), strtolower($actualHash));
    }

    public function delete(string $path): void
    {
        if (!preg_match('/^[a-f0-9-]{36}\.(?:pdf|jpg|png)$/i', $path)) {
            throw new RuntimeException('The stored payment proof path is invalid.');
        }

        if (config('payments.manual_proof_disk') === 's3') {
            $this->objectStorage->delete($path);

            return;
        }

        $this->localDisk()->delete($path);
    }

    private function localDisk()
    {
        $disk = (string) config('payments.manual_proof_disk', 'local');
        if ($disk !== 'local') {
            throw new RuntimeException('Payment proof storage disk is not supported.');
        }

        return Storage::build([
            'driver' => 'local',
            'root' => (string) config('payments.manual_proof_root', storage_path('app/private/manual-payment-proofs')),
            'throw' => true,
            'visibility' => 'private',
        ]);
    }
}
