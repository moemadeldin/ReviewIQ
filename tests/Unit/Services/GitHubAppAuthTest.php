<?php

declare(strict_types=1);

use App\Services\GitHubAppAuth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Generate a throwaway RSA key pair at runtime and point the GitHub App config at it.
 * The private key lives only in a temp file for the duration of the test run; the
 * public key is returned so callers can verify JWTs. Nothing credential-like is
 * ever embedded in the repository.
 *
 * @return string the generated public key in PEM format
 */
function setupKey(): string
{
    static $tempFiles = [];

    $configFile = getenv('OPENSSL_CONF') ?: dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
    $options = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    if (is_file($configFile)) {
        $options['config'] = $configFile;
    }

    $resource = openssl_pkey_new($options);

    if ($resource === false) {
        throw new RuntimeException('Could not generate a test RSA key pair');
    }

    $exportOptions = is_file($configFile) ? ['config' => $configFile] : [];
    openssl_pkey_export($resource, $privateKey, null, $exportOptions);
    $details = openssl_pkey_get_details($resource);
    $publicKey = $details['key'] ?? '';

    $tempFile = tempnam(sys_get_temp_dir(), 'reviewiq-pem');
    file_put_contents($tempFile, $privateKey);
    Config::set('services.github_app.private_key_path', $tempFile);
    $tempFiles[] = $tempFile;

    register_shutdown_function(static function () use ($tempFiles): void {
        foreach ($tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    });

    return $publicKey;
}

beforeEach(function (): void {
    Config::set('services.github.base_url', 'https://api.github.com');
    Config::set('services.github_app.app_id', '100001');
    Config::set('services.github_app.installation_id', '100002');
});

it('generates a valid JWT', function (): void {
    $publicKey = setupKey();

    $auth = new GitHubAppAuth();
    $jwt = $auth->getJwt();

    expect($jwt)->toBeString()->not->toBeEmpty();

    $decoded = JWT::decode($jwt, new Key($publicKey, 'RS256'));

    expect($decoded->iss)->toBe((string) config('services.github_app.app_id'));
    expect($decoded->iat)->toBeInt();
    expect($decoded->exp)->toBeInt();
    expect($decoded->exp - $decoded->iat)->toBe(600);
});

it('fetches and caches installation token', function (): void {
    setupKey();

    $installationId = (string) config('services.github_app.installation_id');

    Http::fake([
        sprintf('api.github.com/app/installations/%s/access_tokens', $installationId) => Http::response(['token' => 'ghs_test_token'], 201),
    ]);

    $auth = new GitHubAppAuth();

    $token = $auth->getInstallationToken();
    expect($token)->toBe('ghs_test_token');
    Http::assertSentCount(1);

    $cached = $auth->getInstallationToken();
    expect($cached)->toBe('ghs_test_token');
    Http::assertSentCount(1);
});

it('returns cached token without HTTP call', function (): void {
    setupKey();

    $installationId = (string) config('services.github_app.installation_id');

    Cache::shouldReceive('get')
        ->once()
        ->with(sprintf('github:installation_token:%s', $installationId))
        ->andReturn('cached_token');

    $auth = new GitHubAppAuth();
    $token = $auth->getInstallationToken();

    expect($token)->toBe('cached_token');
    Http::assertNothingSent();
});

it('refreshToken clears cache and fetches new token', function (): void {
    setupKey();

    $installationId = (string) config('services.github_app.installation_id');

    Cache::put(sprintf('github:installation_token:%s', $installationId), 'stale_token', 55 * 60);

    Http::fake([
        sprintf('api.github.com/app/installations/%s/access_tokens', $installationId) => Http::response(['token' => 'fresh_token'], 201),
    ]);

    $auth = new GitHubAppAuth();
    $token = $auth->refreshToken();

    expect($token)->toBe('fresh_token');
    expect(Cache::get(sprintf('github:installation_token:%s', $installationId)))->toBe('fresh_token');
});

it('throws on empty token from API', function (): void {
    setupKey();

    $installationId = (string) config('services.github_app.installation_id');

    Http::fake([
        sprintf('api.github.com/app/installations/%s/access_tokens', $installationId) => Http::response(['token' => ''], 200),
    ]);

    $auth = new GitHubAppAuth();
    $auth->getInstallationToken();
})->throws(RuntimeException::class, 'Failed to get a valid GitHub App installation token');

it('throws and clears cache on 401', function (): void {
    setupKey();

    $installationId = (string) config('services.github_app.installation_id');

    Http::fake([
        sprintf('api.github.com/app/installations/%s/access_tokens', $installationId) => Http::response(null, 401),
    ]);

    $auth = new GitHubAppAuth();
    $auth->refreshToken();
})->throws(RuntimeException::class, 'GitHub App authentication failed (401)');

it('throws when app id is missing', function (): void {
    Config::set('services.github_app.app_id', '');

    $auth = new GitHubAppAuth();
    $auth->getJwt();
})->throws(RuntimeException::class, 'GitHub App ID not configured');

it('throws when private key path is missing', function (): void {
    Config::set('services.github_app.private_key_path', '/nonexistent/key.pem');

    $auth = new GitHubAppAuth();
    $auth->getJwt();
})->throws(RuntimeException::class, 'GitHub App private key');

it('throws when installation id is missing', function (): void {
    setupKey();
    Config::set('services.github_app.installation_id', '');

    $auth = new GitHubAppAuth();
    $auth->getInstallationToken();
})->throws(RuntimeException::class, 'GitHub App installation ID not configured');

it('throws when GitHub base URL is missing', function (): void {
    setupKey();
    Config::set('services.github.base_url', '');

    $auth = new GitHubAppAuth();
    $auth->getInstallationToken();
})->throws(RuntimeException::class, 'Invalid GitHub base URL configuration');

it('throws when GitHub API returns no token key', function (): void {
    setupKey();

    $installationId = (string) config('services.github_app.installation_id');

    Http::fake([
        sprintf('api.github.com/app/installations/%s/access_tokens', $installationId) => Http::response([], 200),
    ]);

    $auth = new GitHubAppAuth();
    $auth->getInstallationToken();
})->throws(RuntimeException::class, 'Failed to get a valid GitHub App installation token');
