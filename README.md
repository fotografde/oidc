# OIDC Library

A PHP library for OpenID Connect (OIDC) integration with AWS Cognito support and Symfony Security component integration.

## Features

- **OIDC Client Credentials Flow**: Machine-to-machine authentication with AWS Cognito
- **Symfony Security Integration**: User provider for OIDC-based authentication
- **Type Safety**: Full PHP 8.3+ type annotations with Psalm static analysis
- **Modern PHP**: Uses readonly classes, strict types, and latest PHP features

## Requirements

- PHP 8.3 or higher
- Symfony 6.4+ or 7.0+

## Installation

```bash
composer require gpht/oidc
```

## Usage

### 1. OIDC Client Token (Machine-to-Machine Authentication)

Use `OidcClientToken` to obtain access tokens for machine-to-machine communication:

```php
<?php

declare(strict_types=1);

use Gpht\Oidc\Client\OidcClientToken;
use Symfony\Component\HttpClient\HttpClient;

// Create HTTP client
$httpClient = HttpClient::create();

// Initialize OIDC client
$oidcClientToken = new OidcClientToken(
    client: $httpClient,
    tokenEndpoint: 'https://your-cognito-domain.auth.region.amazoncognito.com/oauth2/token',
    cognitoClientId: 'your-client-id',
    cognitoClientSecret: 'your-client-secret'
);

// Get access token with default scopes (m2m/read, m2m/write)
$token = $oidcClientToken->clientCredentialToken();

// Get access token with custom scopes
$token = $oidcClientToken->clientCredentialToken(['custom/read', 'custom/write']);
```

### 2. Symfony Service Configuration

#### Register Services

Configure the OIDC services in your Symfony service configuration:

```php
<?php

declare(strict_types=1);

use Gpht\Oidc\Client\OidcClientToken;
use Gpht\Oidc\Symfony\OidcUserProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $s = $containerConfigurator->services();
    $s
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Configure OIDC Client Token service
    $s
        ->set(OidcClientToken::class)
        ->arg('$tokenEndpoint', '%env(COGNITO_TOKEN_ENDPOINT)%')
        ->arg('$cognitoClientId', '%env(COGNITO_CLIENT_ID)%')
        ->arg('$cognitoClientSecret', '%env(COGNITO_CLIENT_SECRET)%');

    // Configure OIDC User Provider service
    $s
        ->set(OidcUserProvider::class);
};
```

#### Environment Variables

Add these environment variables to your `.env` file:

```bash
# AWS Cognito Configuration
COGNITO_TOKEN_ENDPOINT=https://your-cognito-domain.auth.region.amazoncognito.com/oauth2/token
COGNITO_CLIENT_ID=your-client-id
COGNITO_CLIENT_SECRET=your-client-secret
OIDC_BASE_URI=https://your-cognito-domain.auth.region.amazoncognito.com
```

### 3. Symfony Security Integration

#### Configure User Provider

```php
<?php

declare(strict_types=1);

use Gpht\Oidc\Symfony\OidcUserProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\SecurityConfig;

return static function (ContainerConfigurator $containerConfigurator, SecurityConfig $securityConfig): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(OIDC_BASE_URI)', 'https://your-cognito-domain.auth.region.amazoncognito.com');

    // Register OIDC user provider
    $securityConfig
        ->provider('oidc')
        ->id(OidcUserProvider::class);

    // Configure OIDC firewall
    $securityConfig
        ->firewall('api')
        ->provider('oidc')
        ->pattern('^/api/')
        ->stateless(true)
        ->accessToken()
            ->tokenHandler()
                ->oidc()
                    ->claim('sub')
                    ->algorithms(['ES256', 'RS256'])
                    ->audience('')
                    ->issuers(['%env(OIDC_BASE_URI)%'])
                    ->discovery()
                        ->baseUri('%env(OIDC_BASE_URI)%')
                        ->cache(['id' => 'cache.app']);
};
```

#### Use in Controllers

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
final readonly class ApiController
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {}

    #[Route('/profile', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function profile(): JsonResponse
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (null === $user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        return new JsonResponse([
            'user_id' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
        ]);
    }
}
```

### 4. Testing with Client Credentials

```php
<?php

declare(strict_types=1);

use Gpht\Oidc\Client\OidcClientToken;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTest extends WebTestCase
{
    public function testAuthenticatedEndpoint(): void
    {
        $client = static::createClient();
        
        /** @var OidcClientToken $oidcClientToken */
        $oidcClientToken = self::getContainer()->get(OidcClientToken::class);
        $token = $oidcClientToken->clientCredentialToken();

        $client->jsonRequest('GET', '/api/profile', [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
    }
}
```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run static analysis
composer psalm
```

## Architecture

- **`Gpht\Oidc\Client\OidcClientToken`**: Handles OAuth2 client credentials flow for machine-to-machine authentication
- **`Gpht\Oidc\Symfony\OidcUserProvider`**: Symfony Security user provider that creates `OidcUser` objects from OIDC claims

## License

MIT License. See [LICENSE](LICENSE) for details.