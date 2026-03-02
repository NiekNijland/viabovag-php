<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class SpecificationGroup
{
    /**
     * @param  Specification[]  $specifications
     */
    public function __construct(
        public string $name,
        public array $specifications,
        public ?string $group = null,
        public ?string $iconName = null,
    ) {}
}
