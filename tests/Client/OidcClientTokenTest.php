<?php

declare(strict_types=1);

namespace Tests\Client;

use Gpht\Oidc\Client\OidcClientToken;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class OidcClientTokenTest extends TestCase
{
    private const string TOKEN_ENDPOINT = 'https://example.com/oauth2/token';
    private const string CLIENT_ID = 'test-client-id';
    private const string CLIENT_SECRET = 'test-client-secret';

    public function testClientCredentialTokenSuccess(): void
    {
        $expectedToken = 'test-access-token';
        $responseBody = json_encode([
            'access_token' => $expectedToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $mockClient = $this->createMockClient(200, $responseBody);
        $mockCache = $this->createMockCache(false);
        $oidcClient = new OidcClientToken(
            $mockClient,
            $mockCache,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $token = $oidcClient->clientCredentialToken();

        $this->assertSame($expectedToken, $token);
    }

    public function testClientCredentialTokenWithCache(): void
    {
        $expectedToken = 'cached-access-token';
        $responseBody = json_encode([
            'access_token' => $expectedToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $mockClient = $this->createMockClient(200, $responseBody);
        $mockCache = $this->createMockCache(false); // Cache miss first time
        
        $oidcClient = new OidcClientToken(
            $mockClient,
            $mockCache,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $token = $oidcClient->clientCredentialToken();
        $this->assertSame($expectedToken, $token);
    }

    public function testClientCredentialTokenFromCache(): void
    {
        $cachedToken = 'cached-token';
        $mockClient = $this->createMockClient(200, '{}'); // This should not be called
        $mockCache = $this->createMockCache(true, $cachedToken); // Cache hit
        
        $oidcClient = new OidcClientToken(
            $mockClient,
            $mockCache,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $token = $oidcClient->clientCredentialToken();
        $this->assertSame($cachedToken, $token);
    }

    public function testClientCredentialTokenWithCustomScopes(): void
    {
        $expectedToken = 'test-access-token-with-scopes';
        $customScopes = ['custom/read', 'custom/write'];
        $responseBody = json_encode([
            'access_token' => $expectedToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $mockClient = $this->createMockClient(200, $responseBody);
        $mockCache = $this->createMockCache(false);
        $oidcClient = new OidcClientToken(
            $mockClient,
            $mockCache,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $token = $oidcClient->clientCredentialToken($customScopes);

        $this->assertSame($expectedToken, $token);
    }

    public function testClientCredentialTokenHttpError(): void
    {
        $mockClient = $this->createMockClient(401, 'Unauthorized');
        $mockCache = $this->createMockCache(false);
        $oidcClient = new OidcClientToken(
            $mockClient,
            $mockCache,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get OIDC token: HTTP 401 - Unauthorized');

        $oidcClient->clientCredentialToken();
    }

    public function testClientCredentialTokenMissingAccessToken(): void
    {
        $responseBody = json_encode([
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $mockClient = $this->createMockClient(200, $responseBody);
        $mockCache = $this->createMockCache(false);
        $oidcClient = new OidcClientToken(
            $mockClient,
            $mockCache,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OIDC token response missing access_token field');

        $oidcClient->clientCredentialToken();
    }

    public function testClientCredentialTokenInvalidJson(): void
    {
        $mockClient = $this->createMockClient(200, 'invalid json response');
        $mockCache = $this->createMockCache(false);
        $oidcClient = new OidcClientToken(
            $mockClient,
            $mockCache,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OIDC token response missing access_token field');

        $oidcClient->clientCredentialToken();
    }

    private function createMockClient(int $statusCode, string $responseBody): ClientInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($responseBody);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($stream);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        return $client;
    }

    private function createMockCache(bool $cacheHit, ?string $cachedToken = null): CacheItemPoolInterface
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn($cacheHit);
        
        if ($cacheHit && $cachedToken !== null) {
            $cacheItem->method('get')->willReturn([
                'token' => $cachedToken,
                'expires_at' => time() + 1800, // Valid for 30 minutes
            ]);
        }
        
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAt')->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->method('save')->willReturn(true);

        return $cache;
    }
}