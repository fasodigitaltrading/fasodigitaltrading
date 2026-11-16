<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

class ManualPaymentProofObjectStorage
{
    public function store(string $path, UploadedFile $file, string $contentType): void
    {
        $realPath = $file->getRealPath();
        $stream = is_string($realPath) ? fopen($realPath, 'rb') : false;
        if (!is_resource($stream)) {
            throw new RuntimeException('The payment proof could not be opened for storage.');
        }

        try {
            // R2 does not support per-object ACLs. Privacy is enforced by the private bucket.
            $this->client()->putObject([
                'Bucket' => $this->bucket(),
                'Key' => $path,
                'Body' => $stream,
                'ContentType' => $contentType,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The payment proof could not be stored in private object storage.', 0, $exception);
        } finally {
            fclose($stream);
        }
    }

    public function contents(string $path): string
    {
        try {
            $response = $this->client()->getObject([
                'Bucket' => $this->bucket(),
                'Key' => $path,
            ]);
            $body = $response['Body'] ?? null;
            if ($body instanceof StreamInterface) {
                $body->rewind();
                return $body->getContents();
            }
            if (is_string($body)) {
                return $body;
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('The stored payment proof could not be read from private object storage.', 0, $exception);
        }

        throw new RuntimeException('The stored payment proof could not be read from private object storage.');
    }

    public function delete(string $path): void
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket(),
                'Key' => $path,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The payment proof could not be removed from private object storage.', 0, $exception);
        }
    }

    protected function client(): S3Client
    {
        $config = (array) config('filesystems.disks.s3', []);
        $endpoint = trim((string) ($config['endpoint'] ?? ''));
        $parsedEndpoint = parse_url($endpoint);
        $key = trim((string) ($config['key'] ?? ''));
        $secret = trim((string) ($config['secret'] ?? ''));
        $region = trim((string) ($config['region'] ?? ''));

        if (!is_array($parsedEndpoint)
            || strtolower((string) ($parsedEndpoint['scheme'] ?? '')) !== 'https'
            || trim((string) ($parsedEndpoint['host'] ?? '')) === ''
            || $key === ''
            || $secret === ''
            || $region === '') {
            throw new RuntimeException('Private object storage is not configured.');
        }

        return new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'credentials' => ['key' => $key, 'secret' => $secret],
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
        ]);
    }

    private function bucket(): string
    {
        $bucket = trim((string) config('filesystems.disks.s3.bucket'));
        if ($bucket === '') {
            throw new RuntimeException('Private object storage is not configured.');
        }

        return $bucket;
    }
}
