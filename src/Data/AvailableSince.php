<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum AvailableSince: string
{
    case Today = 'vandaag';
    case Yesterday = 'gisteren';
    case TheDayBeforeYesterday = 'eergisteren';
    case OneWeek = 'een-week';
    case TwoWeeks = 'twee-weken';
    case OneMonth = 'een-maand';

    public function slug(): string
    {
        return 'aangeboden-sinds-'.$this->value;
    }
}
