<?php

declare(strict_types=1);

namespace Gpht\Oidc\Symfony;

use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @implements AttributesBasedUserProviderInterface<OidcUser>
 */
final readonly class OidcUserProvider implements AttributesBasedUserProviderInterface
{
    #[\Override]
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        // Define standard OIDC claim keys that we handle explicitly
        $standardClaims = [
            'sub', 'name', 'given_name', 'family_name', 'middle_name', 'nickname',
            'preferred_username', 'profile', 'picture', 'website', 'email',
            'email_verified', 'gender', 'birthdate', 'zoneinfo', 'locale',
            'phone_number', 'phone_number_verified', 'address', 'updated_at',
        ];

        // Extract additional claims (non-standard OIDC claims)
        $additionalClaims = array_diff_key($attributes, array_flip($standardClaims));

        return new OidcUser(
            userIdentifier: $identifier,
            roles: ['ROLE_USER'],
            sub: isset($attributes['sub']) ? (string) $attributes['sub'] : null,
            name: isset($attributes['name']) ? (string) $attributes['name'] : null,
            givenName: isset($attributes['given_name']) ? (string) $attributes['given_name'] : null,
            familyName: isset($attributes['family_name']) ? (string) $attributes['family_name'] : null,
            middleName: isset($attributes['middle_name']) ? (string) $attributes['middle_name'] : null,
            nickname: isset($attributes['nickname']) ? (string) $attributes['nickname'] : null,
            preferredUsername: isset($attributes['preferred_username']) ? (string) $attributes['preferred_username'] : null,
            profile: isset($attributes['profile']) ? (string) $attributes['profile'] : null,
            picture: isset($attributes['picture']) ? (string) $attributes['picture'] : null,
            website: isset($attributes['website']) ? (string) $attributes['website'] : null,
            email: isset($attributes['email']) ? (string) $attributes['email'] : null,
            emailVerified: isset($attributes['email_verified']) ? (bool) $attributes['email_verified'] : null,
            gender: isset($attributes['gender']) ? (string) $attributes['gender'] : null,
            birthdate: isset($attributes['birthdate']) ? (string) $attributes['birthdate'] : null,
            zoneinfo: isset($attributes['zoneinfo']) ? (string) $attributes['zoneinfo'] : null,
            locale: isset($attributes['locale']) ? (string) $attributes['locale'] : null,
            phoneNumber: isset($attributes['phone_number']) ? (string) $attributes['phone_number'] : null,
            phoneNumberVerified: isset($attributes['phone_number_verified']) ? (bool) $attributes['phone_number_verified'] : null,
            address: isset($attributes['address']) && is_array($attributes['address']) ? $attributes['address'] : null,
            updatedAt: isset($attributes['updated_at']) && is_int($attributes['updated_at']) ? (new \DateTimeImmutable())->setTimestamp($attributes['updated_at']) : null,
            additionalClaims: $additionalClaims,
        );
    }

    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        // For OIDC users, we typically don't refresh from a data source
        // since the token contains all necessary information
        if (!$user instanceof OidcUser) {
            throw new \InvalidArgumentException('Expected OidcUser instance');
        }

        return $user;
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return OidcUser::class == $class;
    }
}
