<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class Media
{
    public function __construct(
        public MediaType $type,
        public string $url,
    ) {}
}
