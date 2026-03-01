<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

interface SearchQuery
{
    public function mobilityType(): MobilityType;

    /**
     * @return string[]
     */
    public function toFilterSlugs(): array;

    public function page(): int;
}
