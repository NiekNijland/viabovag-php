<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class SearchFacetOption
{
    public function __construct(
        public string $name,
        public string $label,
        public ?int $count = null,
        public bool $selected = false,
    ) {}
}
