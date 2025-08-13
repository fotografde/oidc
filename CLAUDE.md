# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is a PHP library implementing OIDC (OpenID Connect) functionality with two main components:

- **src/Client/OidcClientToken.php**: Handles OIDC client credential token acquisition from AWS Cognito
- **src/Symfony/OidcUserProvider.php**: Symfony Security component integration for OIDC user authentication

### Key Components

**OidcClientToken** (src/Client/OidcClientToken.php:10):
- Namespace: `Gpht\Oidc\Client\OidcClientToken`
- Implements OAuth2 client credentials flow
- Designed specifically for AWS Cognito integration
- Uses HTTP Basic authentication with base64-encoded client credentials
- Default scopes: `m2m/read`, `m2m/write`
- Returns access tokens for machine-to-machine authentication

**OidcUserProvider** (src/Symfony/OidcUserProvider.php:14):
- Namespace: `Gpht\Oidc\Symfony\OidcUserProvider`
- Implements Symfony's `AttributesBasedUserProviderInterface`
- Maps OIDC claims to `OidcUser` objects
- Handles standard OIDC claims (sub, name, email, etc.) and additional custom claims
- Used within Symfony Security authentication system

### Code Conventions

- Uses PHP 8+ features including readonly classes, declare(strict_types=1), and override attributes
- Follows PSR standards for coding style
- Uses final readonly classes for immutability
- Proper namespace organization: root namespace for Client, Symfony namespace for framework integration

## Development Commands

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

### Development Notes

The library uses PHP-CS-Fixer with Symfony coding standards, PHPUnit 12 for testing, and Psalm for static analysis. Code follows strict typing and modern PHP practices for security-focused OIDC implementations. 

Tests are located in the `tests/` directory with full coverage of both main components.

### CI/CD

The repository includes GitHub Actions workflows (`.github/workflows/ci.yml`) that run:
- **Tests**: PHPUnit across PHP 8.2, 8.3, and 8.4
- **Code Style**: PHP-CS-Fixer validation
- **Static Analysis**: Psalm type checking

All checks must pass before merging pull requests.