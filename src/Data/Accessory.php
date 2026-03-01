<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class Accessory
{
    public function __construct(
        public string $name,
    ) {}
}
