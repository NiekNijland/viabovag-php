<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

readonly class OptionGroup
{
    /**
     * @param  string[]  $options
     */
    public function __construct(
        public string $name,
        public array $options,
    ) {}
}
