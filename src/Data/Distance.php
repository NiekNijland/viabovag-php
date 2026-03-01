<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum Distance: string
{
    case Five = 'Five';
    case Ten = 'Ten';
    case Twenty = 'Twenty';
    case Thirty = 'Thirty';
    case Forty = 'Forty';
    case Fifty = 'Fifty';
    case OneHundred = 'OneHundred';
    case TwoHundred = 'TwoHundred';
    case ThreeHundred = 'ThreeHundred';

    public function slug(): string
    {
        return 'afstand-'.strtolower($this->value);
    }
}
