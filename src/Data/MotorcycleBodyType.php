<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Data;

enum MotorcycleBodyType: string
{
    case AllRoad = 'AllRoad';
    case Chopper = 'Chopper';
    case Classic = 'Classic';
    case Crosser = 'Crosser';
    case Cruiser = 'Cruiser';
    case Enduro = 'Enduro';
    case Minibike = 'Minibike';
    case Motorscooter = 'Motorscooter';
    case Naked = 'Naked';
    case Quad = 'Quad';
    case Racer = 'Racer';
    case Rally = 'Rally';
    case Sport = 'Sport';
    case SportTouring = 'SportTouring';
    case Supermotard = 'Supermotard';
    case SuperSport = 'SuperSport';
    case Tourer = 'Tourer';
    case TouringEnduro = 'TouringEnduro';
    case Trial = 'Trial';
    case Trike = 'Trike';
    case Sidecar = 'Zijspan';
    case Other = 'Overig';

    public function slug(): string
    {
        return strtolower($this->value);
    }
}
