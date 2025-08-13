<?php

declare(strict_types=1);

namespace Tests\Client;

use Gpht\Oidc\Client\OidcClientToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OidcClientTokenTest extends TestCase
{
    private const string TOKEN_ENDPOINT = 'https://example.com/oauth2/token';
    private const string CLIENT_ID = 'test-client-id';
    private const string CLIENT_SECRET = 'test-client-secret';

    public function testClientCredentialTokenSuccess(): void
    {
        $expectedToken = 'test-access-token';
        $mockResponse = new MockResponse(json_encode([
            'access_token' => $expectedToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $oidcClient = new OidcClientToken(
            $httpClient,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $token = $oidcClient->clientCredentialToken();

        $this->assertSame($expectedToken, $token);
    }

    public function testClientCredentialTokenWithCustomScopes(): void
    {
        $expectedToken = 'test-access-token-with-scopes';
        $customScopes = ['custom/read', 'custom/write'];

        $mockResponse = new MockResponse(json_encode([
            'access_token' => $expectedToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $oidcClient = new OidcClientToken(
            $httpClient,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $token = $oidcClient->clientCredentialToken($customScopes);

        $this->assertSame($expectedToken, $token);
    }

    public function testClientCredentialTokenHttpError(): void
    {
        $mockResponse = new MockResponse('Unauthorized', ['http_code' => 401]);
        $httpClient = new MockHttpClient($mockResponse);

        $oidcClient = new OidcClientToken(
            $httpClient,
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
        $mockResponse = new MockResponse(json_encode([
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $oidcClient = new OidcClientToken(
            $httpClient,
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
        $mockResponse = new MockResponse('invalid json response');
        $httpClient = new MockHttpClient($mockResponse);

        $oidcClient = new OidcClientToken(
            $httpClient,
            self::TOKEN_ENDPOINT,
            self::CLIENT_ID,
            self::CLIENT_SECRET
        );

        $this->expectException(\Symfony\Component\HttpClient\Exception\JsonException::class);

        $oidcClient->clientCredentialToken();
    }
}
