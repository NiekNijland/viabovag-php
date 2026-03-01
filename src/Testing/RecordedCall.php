<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

readonly class RecordedCall
{
    /**
     * @param  array<mixed>  $args
     */
    public function __construct(
        public string $method,
        public array $args,
    ) {}
}
