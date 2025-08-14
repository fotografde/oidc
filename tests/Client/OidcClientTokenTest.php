<?php

declare(strict_types=1);

namespace Tests\Client;

use Gpht\Oidc\Client\OidcClientToken;
use PHPUnit\Framework\TestCase;
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
        $oidcClient = new OidcClientToken(
            $mockClient,
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
        $responseBody = json_encode([
            'access_token' => $expectedToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $mockClient = $this->createMockClient(200, $responseBody);
        $oidcClient = new OidcClientToken(
            $mockClient,
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
        $oidcClient = new OidcClientToken(
            $mockClient,
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
        $oidcClient = new OidcClientToken(
            $mockClient,
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
        $oidcClient = new OidcClientToken(
            $mockClient,
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
}