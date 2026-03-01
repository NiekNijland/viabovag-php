<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum DriveType: string
{
    case FrontWheel = 'voorwiel';
    case RearWheel = 'achterwiel';
    case FourWheel = 'vierwiel';

    public function slug(): string
    {
        return 'aandrijving-'.$this->value;
    }
}
