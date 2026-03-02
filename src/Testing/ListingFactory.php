<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Testing;

use NiekNijland\ViaBOVAG\Data\Listing;
use NiekNijland\ViaBOVAG\Data\MobilityType;

class ListingFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function make(array $overrides = []): Listing
    {
        $defaults = [
            'id' => '96363b6a-38de-4ba9-a681-7335a86f8c08',
            'mobilityType' => MobilityType::Motorcycle,
            'url' => 'https://www.viabovag.nl/motor/aanbod/suzuki-gsx-r-1300-hayabusa-f0fe1ht',
            'friendlyUriPart' => 'suzuki-gsx-r-1300-hayabusa-f0fe1ht',
            'externalAdvertisementUrl' => '',
            'imageUrl' => 'https://stsharedprdweu.blob.core.windows.net/vehicles-media/96363b6a-38de-4ba9-a681-7335a86f8c08/media.0001.jpg',
            'title' => 'Suzuki GSX-R 1300 HAYABUSA',
            'price' => 15499,
            'isFinanceable' => false,
            'vehicle' => null,
            'company' => null,
            'priceExcludesVat' => false,
        ];

        $data = array_merge($defaults, $overrides);

        return new Listing(
            id: $data['id'],
            mobilityType: $data['mobilityType'],
            url: $data['url'],
            friendlyUriPart: $data['friendlyUriPart'],
            externalAdvertisementUrl: $data['externalAdvertisementUrl'],
            imageUrl: $data['imageUrl'],
            title: $data['title'],
            price: $data['price'],
            isFinanceable: $data['isFinanceable'],
            vehicle: $data['vehicle'] ?? VehicleFactory::make(),
            company: $data['company'] ?? CompanyFactory::make(),
            priceExcludesVat: $data['priceExcludesVat'],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return Listing[]
     */
    public static function makeMany(int $count, array $overrides = []): array
    {
        return array_map(
            fn (int $i): Listing => self::make(array_merge($overrides, [
                'id' => $overrides['id'] ?? sprintf('%s-%04d', '96363b6a-38de-4ba9-a681', $i),
            ])),
            range(1, $count),
        );
    }
}
