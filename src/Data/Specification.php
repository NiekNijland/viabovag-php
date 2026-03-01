<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class Specification
{
    public function __construct(
        public string $label,
        public ?string $value,
        public ?string $formattedValue,
    ) {}
}
