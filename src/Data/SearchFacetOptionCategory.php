<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class SearchFacetOptionCategory
{
    /**
     * @param  SearchFacetOption[]  $options
     */
    public function __construct(
        public string $label,
        public array $options,
    ) {}
}
