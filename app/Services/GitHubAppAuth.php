<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class GitHubAppAuth
{
    public function getInstallationToken(): string
    {
        return Cache::remember('github:installation_token', 55 * 60, function (): string {
            $response = Http::withToken($this->getJwt())
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->post($this->baseUrl().'/app/installations/'.$this->installationId().'/access_tokens');

            $response->throw();

            /** @var array{token: string}|null $data */
            $data = $response->json();

            throw_unless(isset($data['token']), RuntimeException::class, 'Failed to get GitHub App installation token');

            return $data['token'];
        });
    }

    public function getJwt(): string
    {
        $now = time();

        return JWT::encode(
            payload: [
                'iat' => $now,
                'exp' => $now + 600,
                'iss' => $this->appId(),
            ],
            key: $this->privateKey(),
            alg: 'RS256',
        );
    }

    private function baseUrl(): string
    {
        $url = config('services.github.base_url');
        throw_unless(is_string($url) && $url !== '', RuntimeException::class, 'Invalid GitHub base URL configuration');

        return $url;
    }

    private function appId(): string
    {
        $id = config('services.github_app.app_id');
        throw_unless(is_string($id) && $id !== '', RuntimeException::class, 'GitHub App ID not configured');

        return $id;
    }

    private function installationId(): string
    {
        $id = config('services.github_app.installation_id');
        throw_unless(is_string($id) && $id !== '', RuntimeException::class, 'GitHub App installation ID not configured');

        return $id;
    }

    /**
     * @return non-empty-string
     */
    private function privateKey(): string
    {
        $path = config('services.github_app.private_key_path');
        throw_unless(is_string($path) && $path !== '', RuntimeException::class, 'GitHub App private key path not configured');

        $contents = @file_get_contents($path);
        throw_unless(is_string($contents) && $contents !== '', RuntimeException::class, 'Failed to read GitHub App private key at: '.$path);

        return $contents;
    }
}
