<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum Distance: int
{
    case Five = 5;
    case Ten = 10;
    case Twenty = 20;
    case Thirty = 30;
    case Forty = 40;
    case Fifty = 50;
    case OneHundred = 100;
    case TwoHundred = 200;
    case ThreeHundred = 300;

    public function slug(): string
    {
        return 'afstand-'.$this->value;
    }
}
