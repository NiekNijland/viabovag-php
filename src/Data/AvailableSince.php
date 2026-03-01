<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum AvailableSince: string
{
    case Today = 'today';
    case ThreeDays = 'threedays';
    case SevenDays = 'sevendays';
    case FourteenDays = 'fourteendays';

    public function slug(): string
    {
        return 'aangeboden-sinds-'.$this->value;
    }
}
