<?php

declare(strict_types=1);

namespace Gpht\Oidc\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;

final readonly class OidcClientToken
{
    private const array SCOPES_DEFAULT = ['m2m/read', 'm2m/write'];

    public function __construct(
        private ClientInterface $client,
        private CacheItemPoolInterface $cache,
        private string $tokenEndpoint,
        private string $cognitoClientId,
        private string $cognitoClientSecret,
    ) {
    }

    /**
     * @param array<non-empty-string> $scope
     *
     * @return non-empty-string
     */
    public function clientCredentialToken(array $scope = []): string
    {
        $cacheKey = $this->generateCacheKey($scope);

        // Try to get token from cache first
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $cachedData = $cacheItem->get();
            if (is_array($cachedData) && isset($cachedData['token']) && is_string($cachedData['token']) && '' !== $cachedData['token'] && isset($cachedData['expires_at']) && is_int($cachedData['expires_at'])) {
                if ($cachedData['expires_at'] > time()) {
                    return $cachedData['token'];
                }
            }
        }

        $credentials = base64_encode($this->cognitoClientId.':'.$this->cognitoClientSecret);

        $postData = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => join(' ', array_merge(self::SCOPES_DEFAULT, $scope)),
        ]);

        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', $this->tokenEndpoint)
            ->withHeader('Authorization', 'Basic '.$credentials)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($factory->createStream($postData));

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('Failed to get OIDC token: HTTP %d - %s', $response->getStatusCode(), $response->getBody()->getContents()));
        }

        $responseData = json_decode($response->getBody()->getContents(), true);

        if (!is_array($responseData) || !isset($responseData['access_token']) || !is_string($responseData['access_token']) || '' === $responseData['access_token']) {
            throw new \RuntimeException('OIDC token response missing access_token field');
        }

        $token = $responseData['access_token'];

        // Cache the token with expiration
        $expiresIn = isset($responseData['expires_in']) && is_int($responseData['expires_in']) ? $responseData['expires_in'] : 3600;
        $expiresAt = time() + $expiresIn - 60; // Subtract 60 seconds for buffer

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set([
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);
        $cacheItem->expiresAt(new \DateTimeImmutable('@'.$expiresAt));
        $this->cache->save($cacheItem);

        return $token;
    }

    private function generateCacheKey(array $scope): string
    {
        /** @var array<string> $mergedScopes */
        $mergedScopes = array_merge(self::SCOPES_DEFAULT, $scope);
        $scopeString = join(' ', $mergedScopes);

        return 'oidc_token_'.hash('sha256', $this->cognitoClientId.':'.$scopeString);
    }
}
