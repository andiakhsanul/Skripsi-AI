<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveUploadService;
use Illuminate\Console\Command;

class GoogleDriveAuthorizeCommand extends Command
{
    protected $signature = 'google:authorize';

    protected $description = 'Generate Google Drive OAuth refresh token (jalankan sekali, hasilnya tersimpan di token file).';

    public function handle(GoogleDriveUploadService $service): int
    {
        try {
            $client = $service->buildClient();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $authUrl = $client->createAuthUrl();

        $this->line('');
        $this->info('1) Buka URL berikut di browser, login dengan akun Google yang punya akses ke folder Drive:');
        $this->line('');
        $this->line($authUrl);
        $this->line('');
        $this->info('2) Setujui akses. Browser akan redirect ke "http://localhost/?code=...&state=..." (halaman tidak terbuka — itu normal).');
        $this->info('3) Copy parameter "code" dari address bar (string panjang antara "code=" dan "&scope"), lalu paste di sini.');
        $this->line('');

        $authCode = trim((string) $this->ask('Masukkan authorization code'));
        $authCode = urldecode($authCode);

        if ($authCode === '') {
            $this->error('Authorization code kosong. Dibatalkan.');

            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($token['error'])) {
            $this->error('Gagal menukar code menjadi token: ' . ($token['error_description'] ?? $token['error']));

            return self::FAILURE;
        }

        if (empty($token['refresh_token'])) {
            $this->warn('Token diterima tetapi tanpa refresh_token — pastikan Anda memilih akun yang belum pernah memberi consent, atau revoke akses lama di myaccount.google.com/permissions lalu ulangi.');
        }

        $service->persistToken($token);

        $this->info('Token tersimpan di: ' . $service->tokenFilePath());
        $this->info('Setup OAuth Google Drive berhasil.');

        return self::SUCCESS;
    }
}
