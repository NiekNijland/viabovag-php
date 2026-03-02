<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class Brand
{
    public function __construct(
        public string $slug,
        public string $label,
        public ?int $count = null,
    ) {}
}
