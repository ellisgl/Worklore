<?php

declare(strict_types=1);

namespace App\Journal\Media;

use App\Shared\ByteSize;
use App\Shared\InvalidValueException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function implode;
use function in_array;
use function sprintf;
use function strtolower;
use function trim;

/**
 * What may be attached: files up to MEDIA_MAX_FILE_SIZE, of the types in
 * MEDIA_ALLOWED_TYPES. The type must come from the file's content.
 */
final readonly class MediaRules
{
    /** @var list<string> */
    public array $allowedTypes;

    /**
     * @param list<string> $allowedTypes
     */
    public function __construct(
        #[Autowire('%env(bytes:MEDIA_MAX_FILE_SIZE)%')]
        public int $maxFileSize,
        #[Autowire('%env(csv:MEDIA_ALLOWED_TYPES)%')]
        array $allowedTypes,
    ) {
        $types = [];
        foreach ($allowedTypes as $type) {
            $type = strtolower(trim($type));
            if ('' !== $type) {
                $types[] = $type;
            }
        }
        $this->allowedTypes = $types;
    }

    /**
     * @throws InvalidValueException if the file is too big or of a type not allowed
     */
    public function check(int $size, string $mediaType): void
    {
        if ($size > $this->maxFileSize) {
            throw $this->tooLarge();
        }
        if (!in_array(strtolower($mediaType), $this->allowedTypes, true)) {
            throw new InvalidValueException(sprintf('Files of type %s can\'t be attached. Allowed: %s.', $mediaType, implode(', ', $this->allowedTypes)));
        }
    }

    public function tooLarge(): InvalidValueException
    {
        return new InvalidValueException(sprintf('The file is larger than the %s limit.', ByteSize::format($this->maxFileSize)));
    }
}
