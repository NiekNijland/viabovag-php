<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum CarFuelType: string
{
    case Benzine = 'Benzine';
    case Diesel = 'Diesel';
    case Hybride = 'Hybride';
    case Elektriciteit = 'Elektriciteit';
    case Gas = 'Gas';
    case Waterstof = 'Waterstof';
    case Overige = 'Overige';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
