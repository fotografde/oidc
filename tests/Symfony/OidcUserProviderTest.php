<?php

declare(strict_types=1);

namespace Tests\Symfony;

use Gpht\Oidc\Symfony\OidcUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\OidcUser;

final class OidcUserProviderTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private OidcUserProvider $userProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->userProvider = new OidcUserProvider();
    }

    public function testLoadUserByIdentifierWithMinimalAttributes(): void
    {
        $identifier = 'user123';
        $attributes = [
            'sub' => 'subject123',
            'email' => 'user@example.com',
        ];

        $user = $this->userProvider->loadUserByIdentifier($identifier, $attributes);

        $this->assertInstanceOf(OidcUser::class, $user);
        $this->assertSame($identifier, $user->getUserIdentifier());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
        $this->assertSame('subject123', $user->getSub());
        $this->assertSame('user@example.com', $user->getEmail());
        $this->assertNull($user->getName());
        $this->assertEmpty($user->getAdditionalClaims());
    }

    public function testLoadUserByIdentifierWithAllStandardClaims(): void
    {
        $identifier = 'user456';
        $updatedAt = time();
        $attributes = [
            'sub' => 'subject456',
            'name' => 'John Doe',
            'given_name' => 'John',
            'family_name' => 'Doe',
            'middle_name' => 'William',
            'nickname' => 'johnny',
            'preferred_username' => 'john.doe',
            'profile' => 'https://example.com/profile',
            'picture' => 'https://example.com/avatar.jpg',
            'website' => 'https://johndoe.com',
            'email' => 'john@example.com',
            'email_verified' => true,
            'gender' => 'male',
            'birthdate' => '1990-01-01',
            'zoneinfo' => 'America/New_York',
            'locale' => 'en-US',
            'phone_number' => '+1234567890',
            'phone_number_verified' => true,
            'address' => ['street' => '123 Main St', 'city' => 'New York'],
            'updated_at' => $updatedAt,
        ];

        $user = $this->userProvider->loadUserByIdentifier($identifier, $attributes);

        $this->assertInstanceOf(OidcUser::class, $user);
        $this->assertSame($identifier, $user->getUserIdentifier());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
        $this->assertSame('subject456', $user->getSub());
        $this->assertSame('John Doe', $user->getName());
        $this->assertSame('John', $user->getGivenName());
        $this->assertSame('Doe', $user->getFamilyName());
        $this->assertSame('William', $user->getMiddleName());
        $this->assertSame('johnny', $user->getNickname());
        $this->assertSame('john.doe', $user->getPreferredUsername());
        $this->assertSame('https://example.com/profile', $user->getProfile());
        $this->assertSame('https://example.com/avatar.jpg', $user->getPicture());
        $this->assertSame('https://johndoe.com', $user->getWebsite());
        $this->assertSame('john@example.com', $user->getEmail());
        $this->assertTrue($user->getEmailVerified());
        $this->assertSame('male', $user->getGender());
        $this->assertSame('1990-01-01', $user->getBirthdate());
        $this->assertSame('America/New_York', $user->getZoneinfo());
        $this->assertSame('en-US', $user->getLocale());
        $this->assertSame('+1234567890', $user->getPhoneNumber());
        $this->assertTrue($user->getPhoneNumberVerified());
        $this->assertSame(['street' => '123 Main St', 'city' => 'New York'], $user->getAddress());
        $this->assertEquals((new \DateTimeImmutable())->setTimestamp($updatedAt), $user->getUpdatedAt());
        $this->assertEmpty($user->getAdditionalClaims());
    }

    public function testLoadUserByIdentifierWithAdditionalClaims(): void
    {
        $identifier = 'user789';
        $attributes = [
            'sub' => 'subject789',
            'email' => 'user@example.com',
            'custom_role' => 'admin',
            'organization' => 'ACME Corp',
            'department' => 'Engineering',
        ];

        $user = $this->userProvider->loadUserByIdentifier($identifier, $attributes);

        $expectedAdditionalClaims = [
            'custom_role' => 'admin',
            'organization' => 'ACME Corp',
            'department' => 'Engineering',
        ];

        $this->assertInstanceOf(OidcUser::class, $user);
        $this->assertSame($identifier, $user->getUserIdentifier());
        $this->assertSame('subject789', $user->getSub());
        $this->assertSame('user@example.com', $user->getEmail());
        $this->assertSame($expectedAdditionalClaims, $user->getAdditionalClaims());
    }

    public function testLoadUserByIdentifierWithInvalidAddressType(): void
    {
        $identifier = 'user999';
        $attributes = [
            'sub' => 'subject999',
            'email' => 'user@example.com',
            'address' => 'not an array',
        ];

        $user = $this->userProvider->loadUserByIdentifier($identifier, $attributes);

        $this->assertInstanceOf(OidcUser::class, $user);
        $this->assertNull($user->getAddress());
    }

    public function testLoadUserByIdentifierWithInvalidUpdatedAtType(): void
    {
        $identifier = 'user111';
        $attributes = [
            'sub' => 'subject111',
            'email' => 'user@example.com',
            'updated_at' => 'not an integer',
        ];

        $user = $this->userProvider->loadUserByIdentifier($identifier, $attributes);

        $this->assertInstanceOf(OidcUser::class, $user);
        $this->assertNull($user->getUpdatedAt());
    }

    public function testRefreshUserWithValidOidcUser(): void
    {
        $originalUser = new OidcUser(
            userIdentifier: 'user123',
            roles: ['ROLE_USER'],
            sub: 'subject123',
            email: 'user@example.com'
        );

        $refreshedUser = $this->userProvider->refreshUser($originalUser);

        $this->assertSame($originalUser, $refreshedUser);
    }

    public function testRefreshUserWithInvalidUserType(): void
    {
        $invalidUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected OidcUser instance');

        $this->userProvider->refreshUser($invalidUser);
    }

    public function testSupportsClassWithOidcUser(): void
    {
        $this->assertTrue($this->userProvider->supportsClass(OidcUser::class));
    }

    public function testSupportsClassWithOtherUserClass(): void
    {
        $this->assertFalse($this->userProvider->supportsClass(\Symfony\Component\Security\Core\User\InMemoryUser::class));
    }
}
