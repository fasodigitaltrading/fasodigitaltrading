<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ManualPaymentProofStorage
{
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
        $storedPath = $this->disk()->putFileAs('', $file, $path);
        if (!is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('The payment proof could not be stored privately.');
        }

        return ['path' => $storedPath, 'sha256' => $sha256];
    }

    public function downloadPath(string $path): string
    {
        if (!preg_match('/^[a-f0-9-]{36}\.(?:pdf|jpg|png)$/i', $path)) {
            throw new RuntimeException('The stored payment proof path is invalid.');
        }

        $disk = $this->disk();
        $fullPath = $disk->path($path);
        if (!$disk->exists($path) || !is_file($fullPath)) {
            throw new RuntimeException('The stored payment proof is missing.');
        }

        return $fullPath;
    }

    public function matchesHash(string $path, string $expectedHash): bool
    {
        try {
            $actualHash = hash_file('sha256', $this->downloadPath($path));
        } catch (RuntimeException) {
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

        $this->disk()->delete($path);
    }

    private function disk()
    {
        return Storage::build([
            'driver' => 'local',
            'root' => (string) config('payments.manual_proof_root', storage_path('app/private/manual-payment-proofs')),
            'throw' => true,
        ]);
    }
}
