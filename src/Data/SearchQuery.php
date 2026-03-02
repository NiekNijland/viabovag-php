<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

use InvalidArgumentException;

interface SearchQuery
{
    public function mobilityType(): MobilityType;

    /**
     * @return string[]
     */
    public function toFilterSlugs(): array;

    /**
     * Current 1-based page number.
     */
    public function page(): int;

    /**
     * Create a new instance with a different page number.
     *
     * @throws InvalidArgumentException
     */
    public function withPage(int $page): static;
}
