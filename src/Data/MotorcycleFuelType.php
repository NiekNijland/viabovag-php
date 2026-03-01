<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum MotorcycleFuelType: string
{
    case Benzine = 'Benzine';
    case Elektriciteit = 'Elektriciteit';
    case Overige = 'Overige';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
