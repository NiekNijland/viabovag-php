<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum Condition: string
{
    case Used = 'Occasion';
    case New = 'Nieuw';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
