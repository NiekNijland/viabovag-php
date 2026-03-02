<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

use NiekNijland\ViaBOVAG\Data\MileageUnit;
use NiekNijland\ViaBOVAG\Data\Vehicle;

class VehicleFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function make(array $overrides = []): Vehicle
    {
        $defaults = [
            'type' => 'motor',
            'brand' => 'Suzuki',
            'model' => 'GSX-R 1300 Hayabusa',
            'mileage' => 15469,
            'mileageUnit' => MileageUnit::Kilometer,
            'year' => 2018,
            'month' => 5,
            'fuelTypes' => [],
            'color' => 'wit',
            'bodyType' => 'superSport',
            'transmissionType' => 'Handgeschakeld',
            'engineCapacity' => 1340,
            'enginePower' => 197,
            'warranties' => ['bovag12maanden'],
            'certaintyKeys' => ['BovagChecklist40Point'],
            'fullyServiced' => false,
            'hasBovagChecklist' => true,
            'bovagWarranty' => 'TwaalfMaanden',
            'hasReturnWarranty' => true,
            'servicedOnDelivery' => true,
            'edition' => null,
            'condition' => null,
            'modelYear' => null,
            'frameType' => null,
            'primaryFuelType' => null,
            'secondaryFuelType' => null,
            'isHybridVehicle' => null,
            'energyLabel' => null,
            'fuelConsumptionCombined' => null,
            'gearCount' => null,
            'isImported' => null,
            'hasNapLabel' => null,
            'wheelSize' => null,
            'emptyWeight' => null,
            'maxWeight' => null,
            'bedCount' => null,
            'sanitary' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return new Vehicle(
            type: $data['type'],
            brand: $data['brand'],
            model: $data['model'],
            mileage: $data['mileage'],
            mileageUnit: $data['mileageUnit'],
            year: $data['year'],
            month: $data['month'],
            fuelTypes: $data['fuelTypes'],
            color: $data['color'],
            bodyType: $data['bodyType'],
            transmissionType: $data['transmissionType'],
            engineCapacity: $data['engineCapacity'],
            enginePower: $data['enginePower'],
            warranties: $data['warranties'],
            certaintyKeys: $data['certaintyKeys'],
            fullyServiced: $data['fullyServiced'],
            hasBovagChecklist: $data['hasBovagChecklist'],
            bovagWarranty: $data['bovagWarranty'],
            hasReturnWarranty: $data['hasReturnWarranty'],
            servicedOnDelivery: $data['servicedOnDelivery'],
            edition: $data['edition'],
            condition: $data['condition'],
            modelYear: $data['modelYear'],
            frameType: $data['frameType'],
            primaryFuelType: $data['primaryFuelType'],
            secondaryFuelType: $data['secondaryFuelType'],
            isHybridVehicle: $data['isHybridVehicle'],
            energyLabel: $data['energyLabel'],
            fuelConsumptionCombined: $data['fuelConsumptionCombined'],
            gearCount: $data['gearCount'],
            isImported: $data['isImported'],
            hasNapLabel: $data['hasNapLabel'],
            wheelSize: $data['wheelSize'],
            emptyWeight: $data['emptyWeight'],
            maxWeight: $data['maxWeight'],
            bedCount: $data['bedCount'],
            sanitary: $data['sanitary'],
        );
    }
}
