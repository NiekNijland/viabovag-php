<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum SortOrder: string
{
    case BestResult = 'besteResultaat';
    case LastAdded = 'laatstToegevoegd';
    case PriceAscending = 'prijsOplopend';
    case PriceDescending = 'prijsAflopend';
    case YearDescending = 'bouwjaarAflopend';
    case YearAscending = 'bouwjaarOplopend';
    case MileageAscending = 'kilometerstandOplopend';
    case MileageDescending = 'kilometerstandAflopend';
    case Distance = 'afstand';

    public function slug(): string
    {
        return 'sortering-'.strtolower($this->value);
    }
}
