<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum BicycleFuelType: string
{
    case Electric = 'Elektriciteit';
    case Other = 'Overige';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
