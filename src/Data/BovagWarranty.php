<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum BovagWarranty: string
{
    case TwelveMonths = 'Bovag12maanden';
    case TwentyFourMonths = 'Bovag24maanden';
    case Manufacturer = 'Fabrieksgarantie';
    case Brand = 'Merkgarantie';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
