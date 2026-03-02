<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum TransmissionType: string
{
    case Manual = 'Handgeschakeld';
    case Automatic = 'Automatisch';
    case SemiAutomatic = 'SemiAutomatisch';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
