<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

use NiekNijland\ViaBOVAG\Data\ListingDetail;

class ListingDetailFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function make(array $overrides = []): ListingDetail
    {
        $defaults = [
            'id' => '96363b6a-38de-4ba9-a681-7335a86f8c08',
            'title' => 'Suzuki GSX-R 1300 HAYABUSA',
            'price' => 15499,
            'description' => '<p>Beautiful Suzuki Hayabusa in excellent condition.</p>',
            'media' => [],
            'vehicle' => null,
            'company' => null,
            'specificationGroups' => [],
            'accessories' => [],
            'optionGroups' => [],
            'licensePlate' => '77MJRJ',
            'externalNumber' => '0025173',
            'structuredData' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return new ListingDetail(
            id: $data['id'],
            title: $data['title'],
            price: $data['price'],
            description: $data['description'],
            media: $data['media'],
            vehicle: $data['vehicle'] ?? VehicleFactory::make(),
            company: $data['company'] ?? CompanyFactory::make(),
            specificationGroups: $data['specificationGroups'],
            accessories: $data['accessories'],
            optionGroups: $data['optionGroups'],
            licensePlate: $data['licensePlate'],
            externalNumber: $data['externalNumber'],
            structuredData: $data['structuredData'],
        );
    }
}
