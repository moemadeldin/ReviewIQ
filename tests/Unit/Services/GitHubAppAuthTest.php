<?php

declare(strict_types=1);

use App\Services\GitHubAppAuth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

const TEST_PRIVATE_KEY = <<<'KEY_WRAP'
-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQClJYmgTEwWKkuu
F0ELEznmKugAmTd4CIZuDi5WkD266kdMVGv5uVNtp7YDxntL7L8RVrSFFCYHfB60
14gk0m2NShBVss4MIKv5cgIf/2ezIjmoKG3H1nF/29z019kM3/RblaUGYFCJ/gwv
p8QxVG6405870iwNf/ES9d76mdxeIIwOCiDvr7cPfOsTjyz3+ygiIcvFwv8GxfSv
LH/1035QcD1A/zSUvZlTTbYKvD1807h2TpU4cKBpoOwTww/AEcN3gncEmqRw6LLH
jVxBnfiowlXCEpa8euYa/XVaOiukf3Mm4CHXswhI5iV1r6FFBJ60IyB8arMeUOh/
FiAkVac/AgMBAAECggEAGLUmAwqHM6W+TtyBybNlrS7sKPLDXrz/x8VtX1wTMDzO
z/etc94rQjOeQrBWUASqjWCIf4SFMAd83JeGcePdqg7TpM6sjxnwQNCyyrC+hglv
0N3DlutZbcSqKSOGAKwc9frMhsiwJAUTM6oI60xziEl5AE0wdBCZM7n/U0TjuF0u
rvK5OG64IGG6SR1fSz1YWA0L2NoZvbxJe4EUqdI7HMSfeTe2OZTPkZjlIh0tIoHp
Pgzzw7648P8WthsRgabMB7ejuzi+c+Cv/LzAC1k0p1E9XTV6L28M2FRFH4nRzvtt
lXDcLgjkXipnpj6Y+Z5c/2rgQiF44ZYW5pSpdayi2QKBgQDQnEXdnH30sDkqxCe1
3w+T/7TmgQpoDypg/EECeriRHfj3tejTLYL8/P0AsK3dCoKhvhsoUBjUnnLV3iZY
OjUeRtl1b8zLfV5MPktKtxZrVZsnuST9Z52NwE2PZ2hwnZXMethfkA54Z8q0wsxv
DFnNQWbYwFwCTFfwBLrppzwSOwKBgQDKqaEq/Lj2vpEYWrE2fMVdombyAymTFMwH
oP6LonRTcMTp9OcjNCHoVIcZyxnA+vHStM02h7n5ffLM8S+XQmNTED3Dp28AYtBn
EsgeVP/Phh7wCkOKN3Wok+OifM64DkTwndC7E5RPx6ge/II/S9/vI9ZEayb4zTIB
n/BSLoJKzQKBgBxtn2vC3rtQpIm6b3ruae4OQ7XB0gw6PNk4pxdSaAKGph4DsTXO
FvKo+0Vzzk24F/M4t/S3bZrT+OxCONF/JSv6FbpWQP9eF1KmjpYg+zInWVyBc5QA
4cymbytiuS3Xm8lg2Em1lPM9mbcmcLuVYEuDZSOWmzNI+hbgXiRnQN1vAoGAd9sg
dRLntQ35M8UXP1lFRF4ysfiK0vCexfhB8oUOdPahjpgHRrujPgsXp3qFbas771h8
cT6OD26cdPZDJhreMRbO4HKaZEkMZZkm/0FX1PzGOUJotUqdbCiinMthWlseDIvZ
EXq/4Pr8g+7kfNi7xGuWYfpZHxYD+BAGCiR1bBUCgYBFijZ6wTsmbIqZpIAjsd1Q
yrsrVbIZxRxXB4dGHN7PMt2yjHmLCRjoByRw6GWw/QS0+wsFyrwghF7JBqUcbU7M
I0MRrgnKCo0wpuGKnUqTpYMybHYZfPGkvlqojybiLr1dEyR1osGnawqwQ2p4sQeW
ETyLdestDMece8UhMGJt1g==
-----END PRIVATE KEY-----
KEY_WRAP;

const TEST_PUBLIC_KEY = <<<'KEY_WRAP'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApSWJoExMFipLrhdBCxM5
5iroAJk3eAiGbg4uVpA9uupHTFRr+blTbae2A8Z7S+y/EVa0hRQmB3wetNeIJNJt
jUoQVbLODCCr+XICH/9nsyI5qChtx9Zxf9vc9NfZDN/0W5WlBmBQif4ML6fEMVRu
uNOfO9IsDX/xEvXe+pncXiCMDgog76+3D3zrE48s9/soIiHLxcL/BsX0ryx/9dN+
UHA9QP80lL2ZU022Crw9fNO4dk6VOHCgaaDsE8MPwBHDd4J3BJqkcOiyx41cQZ34
qMJVwhKWvHrmGv11WjorpH9zJuAh17MISOYlda+hRQSetCMgfGqzHlDofxYgJFWn
PwIDAQAB
-----END PUBLIC KEY-----
KEY_WRAP;

beforeEach(function (): void {
    Config::set('services.github.base_url', 'https://api.github.com');
    Config::set('services.github_app.app_id', '3912217');
    Config::set('services.github_app.installation_id', '136722736');
});

afterEach(function (): void {
    if (property_exists($this, 'tempKeyPath') && $this->tempKeyPath !== null) {
        @unlink($this->tempKeyPath);
    }
});

it('generates a valid JWT', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gh-test-key-');
    $this->tempKeyPath = $path;
    file_put_contents($path, TEST_PRIVATE_KEY);
    Config::set('services.github_app.private_key_path', $path);

    $auth = new GitHubAppAuth();
    $jwt = $auth->getJwt();

    expect($jwt)->toBeString()->not->toBeEmpty();

    $decoded = JWT::decode($jwt, new Key(TEST_PUBLIC_KEY, 'RS256'));

    expect($decoded->iss)->toBe('3912217');
    expect($decoded->iat)->toBeInt();
    expect($decoded->exp)->toBeInt();
    expect($decoded->exp - $decoded->iat)->toBe(600);
});

it('gets installation token from GitHub API', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gh-test-key-');
    $this->tempKeyPath = $path;
    file_put_contents($path, TEST_PRIVATE_KEY);
    Config::set('services.github_app.private_key_path', $path);

    Http::fake([
        'api.github.com/app/installations/136722736/access_tokens' => Http::response(['token' => 'ghs_test_installation_token'], 201),
    ]);

    $auth = new GitHubAppAuth();
    $token = $auth->getInstallationToken();

    expect($token)->toBe('ghs_test_installation_token');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization')
        && str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ')
        && $request->url() === 'https://api.github.com/app/installations/136722736/access_tokens');
});

it('caches the installation token', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gh-test-key-');
    $this->tempKeyPath = $path;
    file_put_contents($path, TEST_PRIVATE_KEY);
    Config::set('services.github_app.private_key_path', $path);

    Cache::shouldReceive('remember')
        ->once()
        ->with('github:installation_token:136722736', 55 * 60, Mockery::on(fn ($closure): bool => is_callable($closure)))
        ->andReturn('cached_token');

    $auth = new GitHubAppAuth();
    $token = $auth->getInstallationToken();

    expect($token)->toBe('cached_token');
});

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
    $path = tempnam(sys_get_temp_dir(), 'gh-test-key-');
    $this->tempKeyPath = $path;
    file_put_contents($path, TEST_PRIVATE_KEY);
    Config::set('services.github_app.private_key_path', $path);
    Config::set('services.github_app.installation_id', '');

    $auth = new GitHubAppAuth();
    $auth->getInstallationToken();
})->throws(RuntimeException::class, 'GitHub App installation ID not configured');

it('throws when GitHub base URL is missing', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gh-test-key-');
    $this->tempKeyPath = $path;
    file_put_contents($path, TEST_PRIVATE_KEY);
    Config::set('services.github_app.private_key_path', $path);
    Config::set('services.github.base_url', '');

    $auth = new GitHubAppAuth();
    $auth->getInstallationToken();
})->throws(RuntimeException::class, 'Invalid GitHub base URL configuration');

it('throws when GitHub API returns no token', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'gh-test-key-');
    $this->tempKeyPath = $path;
    file_put_contents($path, TEST_PRIVATE_KEY);
    Config::set('services.github_app.private_key_path', $path);

    Http::fake([
        'api.github.com/app/installations/136722736/access_tokens' => Http::response([], 200),
    ]);

    $auth = new GitHubAppAuth();
    $auth->getInstallationToken();
})->throws(RuntimeException::class, 'Failed to get GitHub App installation token');
