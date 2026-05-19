<?php

namespace App\Service;

use Aws\S3\S3Client;

class StorageService
{
    private S3Client $client;

    public function __construct(
        private string $endpoint,
        private string $region,
        private string $key,
        private string $secret,
        private string $bucket,
        private string $publicUrl,
    ) {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret,
            ],
        ]);
    }

    public function upload(string $objectKey, string $sourceFilePath, string $mimeType): void
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
            'SourceFile' => $sourceFilePath,
            'ContentType' => $mimeType,
            'ACL' => 'public-read',
        ]);
    }

    public function delete(string $objectKey): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
        ]);
    }

    public function getPublicUrl(string $objectKey): string
    {
        return rtrim($this->publicUrl, '/') . '/' . $this->bucket . '/' . ltrim($objectKey, '/');
    }
}
