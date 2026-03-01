<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

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
            'mileageUnit' => 'kilometer',
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
        );
    }
}
