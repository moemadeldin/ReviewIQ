<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GitHubAppAuth as GitHubAppAuthContract;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class GitHubAppAuth implements GitHubAppAuthContract
{
    private const int TOKEN_TTL_SECONDS = 55 * 60;

    private const int JWT_TTL_SECONDS = 600;

    public function getInstallationToken(): string
    {
        $cached = Cache::get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCache();
    }

    public function refreshToken(): string
    {
        Cache::forget($this->cacheKey());

        return $this->fetchAndCache();
    }

    public function getJwt(): string
    {
        $now = time();

        return JWT::encode(
            payload: [
                'iat' => $now,
                'exp' => $now + self::JWT_TTL_SECONDS,
                'iss' => $this->appId(),
            ],
            key: $this->privateKey(),
            alg: 'RS256',
        );
    }

    private function fetchAndCache(): string
    {
        $response = Http::withToken($this->getJwt())
            ->withHeaders([
                'Accept' => config('services.github.accept_json'),
                'X-GitHub-Api-Version' => config('services.github.api_version'),
            ])
            ->post($this->baseUrl().'/app/installations/'.$this->installationId().'/access_tokens');

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
            throw new RuntimeException('GitHub App authentication failed (401)');
        }

        $response->throw();

        /** @var array{token: string}|null $data */
        $data = $response->json();

        throw_unless(
            isset($data['token']) && is_string($data['token']) && $data['token'] !== '',
            RuntimeException::class,
            'Failed to get a valid GitHub App installation token',
        );

        Cache::put($this->cacheKey(), $data['token'], self::TOKEN_TTL_SECONDS);

        return $data['token'];
    }

    private function cacheKey(): string
    {
        return 'github:installation_token:'.$this->installationId();
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

    /** @return non-empty-string */
    private function privateKey(): string
    {
        return Cache::remember('github:app:private_key', now()->addDay(), function (): string {
            $path = config('services.github_app.private_key_path');
            throw_unless(is_string($path) && $path !== '', RuntimeException::class, 'GitHub App private key path not configured');
            throw_unless(is_file($path), RuntimeException::class, 'GitHub App private key not found at: '.$path);
            throw_unless(is_readable($path), RuntimeException::class, 'GitHub App private key is not readable at: '.$path);

            $contents = file_get_contents($path);
            throw_unless(is_string($contents) && $contents !== '', RuntimeException::class, 'Failed to read GitHub App private key at: '.$path);

            return $contents;
        });
    }
}
