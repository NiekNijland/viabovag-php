<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data\Concerns;

use InvalidArgumentException;

/**
 * Creates a new instance with a different page number.
 *
 * Works with readonly classes that have promoted constructor properties.
 * Requires `@phpstan-consistent-constructor` on the using class.
 */
trait HasWithPage
{
    public function withPage(int $page): static
    {
        self::assertValidPage($page);

        return new static(...[...get_object_vars($this), 'page' => $page]);
    }

    protected static function assertValidPage(int $page): void
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be greater than or equal to 1.');
        }
    }
}
