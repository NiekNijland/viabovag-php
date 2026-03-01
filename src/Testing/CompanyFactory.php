<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

use NiekNijland\ViaBOVAG\Data\Company;

class CompanyFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function make(array $overrides = []): Company
    {
        $defaults = [
            'name' => 'Gebben Motoren',
            'city' => 'Rogat',
            'phoneNumber' => '0522 443820',
            'websiteUrl' => 'http://www.gebbenmotoren.nl',
            'callTrackingCode' => 'AEN2100-2710',
            'street' => null,
            'postalCode' => null,
            'latitude' => null,
            'longitude' => null,
            'reviewScore' => null,
            'reviewCount' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return new Company(
            name: $data['name'],
            city: $data['city'],
            phoneNumber: $data['phoneNumber'],
            websiteUrl: $data['websiteUrl'],
            callTrackingCode: $data['callTrackingCode'],
            street: $data['street'],
            postalCode: $data['postalCode'],
            latitude: $data['latitude'],
            longitude: $data['longitude'],
            reviewScore: $data['reviewScore'],
            reviewCount: $data['reviewCount'],
        );
    }
}
