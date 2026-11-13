<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use App\Shared\Name;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\StringType;
use InvalidArgumentException;

use function is_string;

/**
 * Stores a Name in a VARCHAR column (length from the mapping, 255 by default).
 */
final class NameType extends StringType
{
    public const string NAME = 'name';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Name
    {
        if (null === $value || $value instanceof Name) {
            return $value;
        }

        if (!is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string', Name::class]);
        }

        try {
            return Name::of($value);
        } catch (InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, self::class, null, $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Name) {
            return $value->value;
        }

        // Plain strings are allowed as query parameters, e.g.
        // findBy(['name' => 'Acme']); they're normalized like a Name.
        if (is_string($value)) {
            try {
                return Name::of($value)->value;
            } catch (InvalidArgumentException $e) {
                throw ValueNotConvertible::new($value, self::class, null, $e);
            }
        }

        throw InvalidType::new($value, self::class, ['null', 'string', Name::class]);
    }
}
