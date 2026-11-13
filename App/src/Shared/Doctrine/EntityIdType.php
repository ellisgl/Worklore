<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use App\Shared\EntityId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

/**
 * Maps one EntityId subclass to a native UUID column. Subclasses name the id
 * class and the type name it's registered under in doctrine.yaml.
 */
abstract class EntityIdType extends Type
{
    /**
     * @return class-string<EntityId>
     */
    abstract protected function idClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EntityId
    {
        $class = $this->idClass();

        if (null === $value || $value instanceof $class) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, static::class, ['null', 'string', $class]);
        }

        try {
            return $class::fromString($value);
        } catch (InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, static::class, null, $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $class = $this->idClass();

        if (null === $value) {
            return null;
        }

        // Only this type's own id class: a TagId bound to a company_id column
        // is a bug, not something to convert.
        if ($value instanceof $class) {
            return $value->toRfc4122();
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, static::class, ['null', 'string', $class]);
        }

        try {
            return $class::fromString($value)->toRfc4122();
        } catch (InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, static::class, null, $e);
        }
    }
}
