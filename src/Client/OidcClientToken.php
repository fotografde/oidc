<?php

declare(strict_types=1);

namespace Gpht\Oidc\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OidcClientToken
{
    private const array SCOPES_DEFAULT = ['m2m/read', 'm2m/write'];

    public function __construct(
        private HttpClientInterface $client,
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
        $credentials = base64_encode($this->cognitoClientId.':'.$this->cognitoClientSecret);

        $postData = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => join(' ', array_merge(self::SCOPES_DEFAULT, $scope)),
        ]);

        $response = $this->client->request('POST', $this->tokenEndpoint, [
            'headers' => [
                'Authorization' => 'Basic '.$credentials,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $postData,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('Failed to get OIDC token: HTTP %d - %s', $response->getStatusCode(), $response->getContent(false)));
        }

        $responseData = $response->toArray();

        if (!isset($responseData['access_token']) || !is_string($responseData['access_token']) || '' === $responseData['access_token']) {
            throw new \RuntimeException('OIDC token response missing access_token field');
        }

        return $responseData['access_token'];
    }
}
