<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class GoogleDriveUploadService
{
    private ?GoogleDrive $service = null;

    /**
     * @return array{file_id: string, web_view_link: string}
     */
    public function upload(UploadedFile $file, string $targetName): array
    {
        $service = $this->driveService();

        $metadata = new DriveFile([
            'name' => $targetName,
            'parents' => [$this->folderId()],
        ]);

        $created = $service->files->create($metadata, [
            'data' => file_get_contents($file->getRealPath()),
            'mimeType' => $file->getMimeType() ?: 'application/pdf',
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        $service->permissions->create($created->id, new Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]));

        $hydrated = $service->files->get($created->id, [
            'fields' => 'id,webViewLink',
        ]);

        return [
            'file_id' => $hydrated->id,
            'web_view_link' => $hydrated->webViewLink,
        ];
    }

    public function delete(string $fileId): void
    {
        $this->driveService()->files->delete($fileId);
    }

    public function buildClient(): GoogleClient
    {
        $clientFile = $this->resolvePath(config('google.drive.oauth_client_file'), 'GOOGLE_OAUTH_CLIENT_FILE');

        $client = new GoogleClient();
        $client->setApplicationName(config('google.drive.application_name'));
        $client->setAuthConfig($clientFile);
        $client->setScopes([GoogleDrive::DRIVE_FILE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setRedirectUri('http://localhost');

        return $client;
    }

    public function tokenFilePath(): string
    {
        $configured = config('google.drive.oauth_token_file');

        if (empty($configured)) {
            throw new RuntimeException('GOOGLE_OAUTH_TOKEN_FILE belum disetel pada konfigurasi.');
        }

        return str_starts_with($configured, '/') ? $configured : base_path($configured);
    }

    /**
     * @param array<string, mixed> $token
     */
    public function persistToken(array $token): void
    {
        $path = $this->tokenFilePath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($path, 0600);
    }

    private function driveService(): GoogleDrive
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $client = $this->buildClient();
        $tokenPath = $this->tokenFilePath();

        if (! is_file($tokenPath)) {
            throw new RuntimeException(
                "Token OAuth Google belum dibuat. Jalankan: php artisan google:authorize"
            );
        }

        $token = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            if (! $client->getRefreshToken()) {
                throw new RuntimeException(
                    'Refresh token Google tidak tersedia. Jalankan ulang: php artisan google:authorize'
                );
            }
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $this->persistToken($client->getAccessToken());
        }

        return $this->service = new GoogleDrive($client);
    }

    private function resolvePath(?string $configured, string $envName): string
    {
        if (empty($configured)) {
            throw new RuntimeException("{$envName} belum disetel pada konfigurasi.");
        }

        $path = str_starts_with($configured, '/') ? $configured : base_path($configured);

        if (! is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        return $path;
    }

    private function folderId(): string
    {
        $folderId = config('google.drive.folder_id');

        if (empty($folderId)) {
            throw new RuntimeException('GOOGLE_DRIVE_FOLDER_ID belum disetel pada konfigurasi.');
        }

        return $folderId;
    }
}
