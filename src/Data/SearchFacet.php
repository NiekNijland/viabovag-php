<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class SearchFacet
{
    /**
     * @param  SearchFacetOption[]  $options
     * @param  SearchFacetOptionCategory[]  $optionCategories
     * @param  string[]  $selectedValues
     */
    public function __construct(
        public string $name,
        public string $label,
        public bool $disabled = false,
        public bool $selected = false,
        public bool $hidden = false,
        public array $options = [],
        public array $optionCategories = [],
        public array $selectedValues = [],
        public ?string $tooltip = null,
        public bool $hasIcons = false,
    ) {}
}
