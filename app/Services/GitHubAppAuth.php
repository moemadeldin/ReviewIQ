<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GitHubAppAuth as GitHubAppAuthContract;
use App\Utilities\Constants;
use Firebase\JWT\JWT;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class GitHubAppAuth implements GitHubAppAuthContract
{
    public function __construct(
        private string $baseUrl,
        private GitHubHttp $http,
    ) {
        throw_if($this->baseUrl === '' || $this->baseUrl === '0', RuntimeException::class, 'Invalid GitHub base URL configuration');
    }

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
        return $this->fetchAndCache();
    }

    public function getJwt(): string
    {
        $now = time();

        return JWT::encode(
            payload: [
                'iat' => $now,
                'exp' => $now + Constants::GITHUB_JWT_TTL_SECONDS,
                'iss' => $this->appId(),
            ],
            key: $this->privateKey(),
            alg: 'RS256',
        );
    }

    private function fetchAndCache(): string
    {
        $response = $this->http->appAuth($this->getJwt())
            ->post('/app/installations/'.$this->installationId().'/access_tokens');

        if ($response->status() === Response::HTTP_UNAUTHORIZED) {
            /** @var string|null $error */
            $error = $response->json('message');
            $detail = is_string($error) && $error !== '' ? $error : $response->body();

            Log::error('GitHub App authentication failed (401)', ['error' => $detail]);

            throw new RuntimeException('GitHub App authentication failed (401): '.$detail);
        }

        $response->throw();

        /** @var array{token: string}|null $data */
        $data = $response->json();
        $token = $data['token'] ?? null;

        throw_unless(
            is_string($token) && $token !== '',
            RuntimeException::class,
            'Failed to get a valid GitHub App installation token',
        );

        Cache::put($this->cacheKey(), $token, Constants::GITHUB_INSTALLATION_TOKEN_TTL_SECONDS);

        return $token;
    }

    private function cacheKey(): string
    {
        return 'github:installation_token:'.$this->installationId();
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
        $path = config('services.github_app.private_key_path');
        throw_unless(is_string($path) && $path !== '', RuntimeException::class, 'GitHub App private key path not configured');
        throw_unless(is_file($path), RuntimeException::class, 'GitHub App private key not found at: '.$path);
        throw_unless(is_readable($path), RuntimeException::class, 'GitHub App private key is not readable at: '.$path);

        $contents = file_get_contents($path);
        throw_unless(is_string($contents) && $contents !== '', RuntimeException::class, 'Failed to read GitHub App private key at: '.$path);

        return $contents;
    }
}
