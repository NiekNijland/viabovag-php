<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum Condition: string
{
    case Occasion = 'Occasion';
    case Nieuw = 'Nieuw';

    public function slug(): string
    {
        return 'staat-'.strtolower($this->value);
    }
}
