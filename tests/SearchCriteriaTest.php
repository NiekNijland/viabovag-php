<?php

declare(strict_types=1);

namespace NiekNijland\ViaBOVAG\Tests;

use InvalidArgumentException;
use NiekNijland\ViaBOVAG\Data\AccessoryFilter;
use NiekNijland\ViaBOVAG\Data\AvailableSince;
use NiekNijland\ViaBOVAG\Data\BicycleBodyType;
use NiekNijland\ViaBOVAG\Data\BicycleFuelType;
use NiekNijland\ViaBOVAG\Data\BicycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\BovagWarranty;
use NiekNijland\ViaBOVAG\Data\Brand;
use NiekNijland\ViaBOVAG\Data\CamperSearchCriteria;
use NiekNijland\ViaBOVAG\Data\CarBodyType;
use NiekNijland\ViaBOVAG\Data\CarFuelType;
use NiekNijland\ViaBOVAG\Data\CarSearchCriteria;
use NiekNijland\ViaBOVAG\Data\Condition;
use NiekNijland\ViaBOVAG\Data\CylinderCount;
use NiekNijland\ViaBOVAG\Data\Distance;
use NiekNijland\ViaBOVAG\Data\DriversLicense;
use NiekNijland\ViaBOVAG\Data\DriveType;
use NiekNijland\ViaBOVAG\Data\FilterOption;
use NiekNijland\ViaBOVAG\Data\GearCount;
use NiekNijland\ViaBOVAG\Data\MobilityType;
use NiekNijland\ViaBOVAG\Data\Model;
use NiekNijland\ViaBOVAG\Data\MotorcycleBodyType;
use NiekNijland\ViaBOVAG\Data\MotorcycleFuelType;
use NiekNijland\ViaBOVAG\Data\MotorcycleSearchCriteria;
use NiekNijland\ViaBOVAG\Data\SeatCount;
use NiekNijland\ViaBOVAG\Data\SortOrder;
use NiekNijland\ViaBOVAG\Data\TransmissionType;
use PHPUnit\Framework\TestCase;

class SearchCriteriaTest extends TestCase
{
    // --- Mobility Types ---

    public function test_car_criteria_returns_car_mobility_type(): void
    {
        $criteria = new CarSearchCriteria;

        $this->assertSame(MobilityType::Car, $criteria->mobilityType());
    }

    public function test_motorcycle_criteria_returns_motorcycle_mobility_type(): void
    {
        $criteria = new MotorcycleSearchCriteria;

        $this->assertSame(MobilityType::Motorcycle, $criteria->mobilityType());
    }

    public function test_bicycle_criteria_returns_bicycle_mobility_type(): void
    {
        $criteria = new BicycleSearchCriteria;

        $this->assertSame(MobilityType::Bicycle, $criteria->mobilityType());
    }

    public function test_camper_criteria_returns_camper_mobility_type(): void
    {
        $criteria = new CamperSearchCriteria;

        $this->assertSame(MobilityType::Camper, $criteria->mobilityType());
    }

    // --- Shared Filter Slugs ---

    public function test_shared_filter_slugs_year_range(): void
    {
        $criteria = new CarSearchCriteria(yearFrom: 2015, yearTo: 2020);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('bouwjaar-vanaf-2015', $slugs);
        $this->assertContains('bouwjaar-tot-en-met-2020', $slugs);
    }

    public function test_shared_filter_slugs_model_year_range(): void
    {
        $criteria = new CarSearchCriteria(modelYearFrom: 2016, modelYearTo: 2021);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('modeljaar-vanaf-2016', $slugs);
        $this->assertContains('modeljaar-tot-en-met-2021', $slugs);
    }

    public function test_shared_filter_slugs_mileage_range(): void
    {
        $criteria = new MotorcycleSearchCriteria(mileageFrom: 0, mileageTo: 50000);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('kilometerstand-vanaf-0', $slugs);
        $this->assertContains('kilometerstand-tot-en-met-50000', $slugs);
    }

    public function test_shared_filter_slugs_engine_power(): void
    {
        $criteria = new CarSearchCriteria(enginePowerFrom: 100, enginePowerTo: 300);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('vermogen-pk-vanaf-100', $slugs);
        $this->assertContains('vermogen-pk-tot-en-met-300', $slugs);
    }

    public function test_shared_filter_slugs_lease_price(): void
    {
        $criteria = new CarSearchCriteria(leasePriceFrom: 200, leasePriceTo: 500);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('leaseprijs-vanaf-200', $slugs);
        $this->assertContains('leaseprijs-tot-en-met-500', $slugs);
    }

    public function test_shared_filter_slugs_model_keywords(): void
    {
        $criteria = new CarSearchCriteria(modelKeywords: 'Sport');
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('model-trefwoorden-sport', $slugs);
    }

    public function test_shared_filter_slugs_model_uses_model_slug(): void
    {
        $criteria = new CarSearchCriteria(
            model: new Model(slug: 'golf-8', label: 'Golf 8'),
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('model-golf-8', $slugs);
    }

    public function test_shared_filter_slugs_brand_uses_brand_slug(): void
    {
        $criteria = new CarSearchCriteria(
            brand: new Brand(slug: 'mercedes-benz', label: 'Mercedes-Benz'),
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('merk-mercedes-benz', $slugs);
    }

    public function test_shared_filter_slugs_colors(): void
    {
        $criteria = new CarSearchCriteria(colors: ['Zwart', 'Wit']);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('kleur-zwart', $slugs);
        $this->assertContains('kleur-wit', $slugs);
    }

    public function test_shared_filter_slugs_condition(): void
    {
        $criteria = new CarSearchCriteria(condition: Condition::New);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('nieuw', $slugs);
    }

    public function test_shared_filter_slugs_location_ignores_distance(): void
    {
        $criteria = new CarSearchCriteria(postalCode: '1234AB', distance: Distance::Twenty);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('postcode-1234AB', $slugs);
        $this->assertNotContains('afstand-twenty', $slugs);
    }

    public function test_shared_filter_slugs_warranty(): void
    {
        $criteria = new CarSearchCriteria(warranty: BovagWarranty::TwelveMonths);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('bovag12maanden', $slugs);
    }

    public function test_shared_filter_slugs_support_multi_select_condition_and_warranty(): void
    {
        $criteria = new CarSearchCriteria(
            conditions: [Condition::New, Condition::Used],
            warranties: [BovagWarranty::TwelveMonths, BovagWarranty::Manufacturer],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('nieuw', $slugs);
        $this->assertContains('occasion', $slugs);
        $this->assertContains('bovag12maanden', $slugs);
        $this->assertContains('fabrieksgarantie', $slugs);
    }

    public function test_shared_filter_slugs_keywords(): void
    {
        $criteria = new MotorcycleSearchCriteria(keywords: 'Sportbike');
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('trefwoorden-sportbike', $slugs);
    }

    public function test_shared_filter_slugs_available_since(): void
    {
        $criteria = new CarSearchCriteria(availableSince: AvailableSince::OneWeek);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('aangeboden-sinds-een-week', $slugs);
    }

    public function test_shared_filter_slugs_boolean_filters(): void
    {
        $criteria = new CarSearchCriteria(
            fullyServiced: true,
            hasBovagChecklist: true,
            hasBovagMaintenanceFree: true,
            hasBovagImportOdometerCheck: true,
            servicedOnDelivery: true,
            hasNapWeblabel: true,
            vatDeductible: true,
            isFinanceable: true,
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('100-procent-onderhouden', $slugs);
        $this->assertContains('40-puntencheck', $slugs);
        $this->assertContains('onderhoudsvrij', $slugs);
        $this->assertContains('import-teller-check', $slugs);
        $this->assertContains('afleverbeurt', $slugs);
        $this->assertContains('nap-weblabel', $slugs);
        $this->assertContains('btw-verrekenbaar', $slugs);
        $this->assertContains('online-te-financieren', $slugs);
    }

    public function test_shared_filter_slugs_import_yes(): void
    {
        $criteria = new CarSearchCriteria(isImported: true);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('import-ja', $slugs);
    }

    public function test_shared_filter_slugs_import_no(): void
    {
        $criteria = new CarSearchCriteria(isImported: false);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('import-nee', $slugs);
    }

    // --- Car-Specific Filters ---

    public function test_car_criteria_body_types(): void
    {
        $criteria = new CarSearchCriteria(
            bodyTypes: [CarBodyType::Hatchback, CarBodyType::SuvOffRoad],
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('hatchback', $slugs);
        $this->assertContains('suvterreinwagen', $slugs);
    }

    public function test_car_criteria_fuel_types(): void
    {
        $criteria = new CarSearchCriteria(
            fuelTypes: [CarFuelType::Petrol, CarFuelType::Electric],
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('benzine', $slugs);
        $this->assertContains('elektriciteit', $slugs);
    }

    public function test_car_criteria_transmission(): void
    {
        $criteria = new CarSearchCriteria(transmission: TransmissionType::Automatic);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('automatisch', $slugs);
    }

    public function test_car_criteria_gear_counts(): void
    {
        $criteria = new CarSearchCriteria(gearCounts: [GearCount::Six, GearCount::Seven]);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('versnellingen-6', $slugs);
        $this->assertContains('versnellingen-7', $slugs);
    }

    public function test_car_criteria_cylinder_counts(): void
    {
        $criteria = new CarSearchCriteria(cylinderCounts: [CylinderCount::Four, CylinderCount::Six]);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('cilinders-4', $slugs);
        $this->assertContains('cilinders-6', $slugs);
    }

    public function test_car_criteria_seat_counts(): void
    {
        $criteria = new CarSearchCriteria(seatCounts: [SeatCount::Five]);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('zitplaatsen-5', $slugs);
    }

    public function test_car_criteria_drive_types(): void
    {
        $criteria = new CarSearchCriteria(driveTypes: [DriveType::FourWheel]);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('aandrijving-vierwiel', $slugs);
    }

    public function test_car_criteria_accessories(): void
    {
        $criteria = new CarSearchCriteria(
            accessories: [AccessoryFilter::Navigation, AccessoryFilter::Towbar],
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('navigatie', $slugs);
        $this->assertContains('trekhaak', $slugs);
    }

    public function test_car_criteria_performance_filters(): void
    {
        $criteria = new CarSearchCriteria(
            engineCapacityFrom: 1500,
            engineCapacityTo: 3000,
            accelerationTo: 8,
            topSpeedFrom: 200,
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('motorinhoud-cc-vanaf-1500', $slugs);
        $this->assertContains('motorinhoud-cc-tot-en-met-3000', $slugs);
        $this->assertContains('acceleratie-tot-en-met-8', $slugs);
        $this->assertContains('topsnelheid-vanaf-200', $slugs);
    }

    public function test_car_criteria_dimension_filters(): void
    {
        $criteria = new CarSearchCriteria(
            emptyMassTo: 1500,
            doorCountFrom: 3,
            doorCountTo: 5,
            wheelSizeFrom: 16,
            wheelSizeTo: 20,
            brakedTowingWeightFrom: 750,
            brakedTowingWeightTo: 2500,
            maximumMassTo: 3500,
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('gewicht-tot-en-met-1500', $slugs);
        $this->assertContains('deuren-vanaf-3', $slugs);
        $this->assertContains('deuren-tot-en-met-5', $slugs);
        $this->assertContains('wielmaat-vanaf-16', $slugs);
        $this->assertContains('wielmaat-tot-en-met-20', $slugs);
        $this->assertContains('geremde-aanhangermassa-vanaf-750', $slugs);
        $this->assertContains('geremde-aanhangermassa-tot-en-met-2500', $slugs);
        $this->assertContains('maximale-massa-tot-en-met-3500', $slugs);
    }

    public function test_car_criteria_ev_filters(): void
    {
        $criteria = new CarSearchCriteria(
            batteryCapacityFrom: 40,
            batteryCapacityTo: 100,
            maxChargingPowerHome: 11,
            maxQuickChargingPower: 150,
            isPluginHybrid: true,
            energyLabel: new FilterOption(slug: 'a', label: 'A'),
            specifiedBatteryRange: new FilterOption(slug: '300-400', label: '300-400 km'),
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('batterijcapaciteit-vanaf-40', $slugs);
        $this->assertContains('batterijcapaciteit-tot-en-met-100', $slugs);
        $this->assertContains('max-laadvermogen-thuis-vanaf-11', $slugs);
        $this->assertContains('max-snellaadvermogen-vanaf-150', $slugs);
        $this->assertContains('plug-in-hybride', $slugs);
        $this->assertContains('energielabel-a', $slugs);
        $this->assertContains('opgegeven-bereik-300-400', $slugs);
    }

    public function test_car_criteria_leaseable_and_city(): void
    {
        $criteria = new CarSearchCriteria(
            city: new FilterOption(slug: 'amsterdam', label: 'Amsterdam'),
            isLeaseable: true,
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('online-te-leasen', $slugs);
        $this->assertContains('stad-amsterdam', $slugs);
    }

    public function test_car_criteria_support_multi_select_city_and_ev_filter_options(): void
    {
        $criteria = new CarSearchCriteria(
            cities: [
                new FilterOption(slug: 'amsterdam', label: 'Amsterdam'),
                new FilterOption(slug: 'utrecht', label: 'Utrecht'),
            ],
            energyLabels: [
                new FilterOption(slug: 'a', label: 'A'),
                new FilterOption(slug: 'b', label: 'B'),
            ],
            specifiedBatteryRanges: [
                new FilterOption(slug: '300-400', label: '300-400 km'),
                new FilterOption(slug: '400-500', label: '400-500 km'),
            ],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('stad-amsterdam', $slugs);
        $this->assertContains('stad-utrecht', $slugs);
        $this->assertContains('energielabel-a', $slugs);
        $this->assertContains('energielabel-b', $slugs);
        $this->assertContains('opgegeven-bereik-300-400', $slugs);
        $this->assertNotContains('opgegeven-bereik-400-500', $slugs);
    }

    public function test_car_criteria_empty_returns_no_slugs(): void
    {
        $criteria = new CarSearchCriteria;

        $this->assertEmpty($criteria->toFilterSlugs());
    }

    // --- Motorcycle-Specific Filters ---

    public function test_motorcycle_criteria_drivers_license(): void
    {
        $criteria = new MotorcycleSearchCriteria(driversLicense: DriversLicense::A2);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('rijbewijs-a2', $slugs);
    }

    public function test_motorcycle_criteria_throws_for_frame_type_filter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FrameType filters are not supported for motorcycles. Use bodyTypes (category) instead.');

        new MotorcycleSearchCriteria(
            frameType: new FilterOption(slug: 'dubbel-wieg', label: 'Dubbel wieg'),
        );
    }

    public function test_motorcycle_criteria_accessory_and_performance_filters(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            accelerationTo: 8,
            topSpeedFrom: 150,
            accessory: new FilterOption(slug: 'cruisecontrol', label: 'Cruise Control'),
            accessories: [new FilterOption(slug: 'buddyseat', label: 'Buddyseat')],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('cruisecontrol', $slugs);
        $this->assertContains('buddyseat', $slugs);
        $this->assertContains('acceleratie-tot-en-met-8', $slugs);
        $this->assertContains('topsnelheid-vanaf-150', $slugs);
    }

    public function test_motorcycle_criteria_support_multi_select_transmission_and_license(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            transmissions: [TransmissionType::Manual, TransmissionType::Automatic],
            driversLicenses: [DriversLicense::A, DriversLicense::A2],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('handgeschakeld', $slugs);
        $this->assertContains('automatisch', $slugs);
        $this->assertContains('rijbewijs-a', $slugs);
        $this->assertContains('rijbewijs-a2', $slugs);
    }

    public function test_motorcycle_criteria_throws_for_frame_types_filter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FrameType filters are not supported for motorcycles. Use bodyTypes (category) instead.');

        new MotorcycleSearchCriteria(
            frameTypes: [
                new FilterOption(slug: 'dubbel-wieg', label: 'Dubbel wieg'),
                new FilterOption(slug: 'trellis', label: 'Trellis'),
            ],
        );
    }

    public function test_motorcycle_criteria_body_types(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            bodyTypes: [MotorcycleBodyType::Naked, MotorcycleBodyType::Chopper],
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('naked', $slugs);
        $this->assertContains('chopper', $slugs);
    }

    public function test_motorcycle_criteria_fuel_types(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            fuelTypes: [MotorcycleFuelType::Petrol, MotorcycleFuelType::Electric],
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('benzine', $slugs);
        $this->assertContains('elektriciteit', $slugs);
    }

    public function test_motorcycle_criteria_engine_capacity(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            engineCapacityFrom: 500,
            engineCapacityTo: 1200,
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('motorinhoud-cc-vanaf-500', $slugs);
        $this->assertContains('motorinhoud-cc-tot-en-met-1200', $slugs);
    }

    // --- Bicycle-Specific Filters ---

    public function test_bicycle_criteria_body_types(): void
    {
        $criteria = new BicycleSearchCriteria(
            bodyTypes: [BicycleBodyType::CityBike, BicycleBodyType::Mountainbike],
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('stadsfiets', $slugs);
        $this->assertContains('mountainbike', $slugs);
    }

    public function test_bicycle_criteria_fuel_types(): void
    {
        $criteria = new BicycleSearchCriteria(
            fuelTypes: [BicycleFuelType::Electric],
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('elektriciteit', $slugs);
    }

    public function test_bicycle_criteria_frame_and_battery_filters(): void
    {
        $criteria = new BicycleSearchCriteria(
            frameHeightFrom: 50,
            frameHeightTo: 60,
            frameMaterial: new FilterOption(slug: 'aluminium', label: 'Aluminium'),
            brakeType: new FilterOption(slug: 'schijfrem', label: 'Schijfrem'),
            batteryRemovable: true,
            batteryCapacityFrom: 300,
            batteryCapacityTo: 600,
            engineBrand: new FilterOption(slug: 'bosch', label: 'Bosch'),
            specifiedBatteryRange: new FilterOption(slug: '80-100', label: '80-100 km'),
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('framehoogte-vanaf-50', $slugs);
        $this->assertContains('framehoogte-tot-en-met-60', $slugs);
        $this->assertContains('framemateriaal-aluminium', $slugs);
        $this->assertContains('remtype-schijfrem', $slugs);
        $this->assertContains('batterij-verwijderbaar', $slugs);
        $this->assertContains('batterijcapaciteit-vanaf-300', $slugs);
        $this->assertContains('batterijcapaciteit-tot-en-met-600', $slugs);
        $this->assertContains('motormerk-bosch', $slugs);
        $this->assertContains('opgegeven-bereik-80-100', $slugs);
    }

    public function test_bicycle_criteria_support_multi_select_filter_options(): void
    {
        $criteria = new BicycleSearchCriteria(
            frameMaterials: [
                new FilterOption(slug: 'aluminium', label: 'Aluminium'),
                new FilterOption(slug: 'carbon', label: 'Carbon'),
            ],
            brakeTypes: [
                new FilterOption(slug: 'schijfrem', label: 'Schijfrem'),
                new FilterOption(slug: 'velgrem', label: 'Velgrem'),
            ],
            engineBrands: [
                new FilterOption(slug: 'bosch', label: 'Bosch'),
                new FilterOption(slug: 'shimano', label: 'Shimano'),
            ],
            specifiedBatteryRanges: [
                new FilterOption(slug: '80-100', label: '80-100 km'),
                new FilterOption(slug: '100-120', label: '100-120 km'),
            ],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('framemateriaal-aluminium', $slugs);
        $this->assertContains('framemateriaal-carbon', $slugs);
        $this->assertContains('remtype-schijfrem', $slugs);
        $this->assertContains('remtype-velgrem', $slugs);
        $this->assertContains('motormerk-bosch', $slugs);
        $this->assertContains('motormerk-shimano', $slugs);
        $this->assertContains('opgegeven-bereik-80-100', $slugs);
        $this->assertNotContains('opgegeven-bereik-100-120', $slugs);
    }

    public function test_bicycle_criteria_empty_returns_no_slugs(): void
    {
        $criteria = new BicycleSearchCriteria;

        $this->assertEmpty($criteria->toFilterSlugs());
    }

    // --- Camper-Specific Filters ---

    public function test_camper_criteria_interior_filters(): void
    {
        $criteria = new CamperSearchCriteria(
            bedCount: 4,
            bedLayout: new FilterOption(slug: 'dwarsbed', label: 'Dwarsbed'),
            seatingLayout: new FilterOption(slug: 'halfrond', label: 'Halfrond'),
            sanitaryLayout: new FilterOption(slug: 'douche', label: 'Douche'),
            kitchenLayout: new FilterOption(slug: 'l-vormig', label: 'L-vormig'),
            interiorHeightFrom: 190,
            camperChassisBrand: new FilterOption(slug: 'fiat', label: 'Fiat'),
            maximumMassTo: 3500,
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('slaapplaatsen-4', $slugs);
        $this->assertContains('bedindeling-dwarsbed', $slugs);
        $this->assertContains('zitindeling-halfrond', $slugs);
        $this->assertContains('sanitaire-indeling-douche', $slugs);
        $this->assertContains('keukenindeling-l-vormig', $slugs);
        $this->assertContains('stahoogte-vanaf-190', $slugs);
        $this->assertContains('chassis-merk-fiat', $slugs);
        $this->assertContains('maximale-massa-tot-en-met-3500', $slugs);
    }

    public function test_camper_criteria_support_multi_select_layout_filter_options(): void
    {
        $criteria = new CamperSearchCriteria(
            bedLayouts: [
                new FilterOption(slug: 'dwarsbed', label: 'Dwarsbed'),
                new FilterOption(slug: 'frans-bed', label: 'Frans bed'),
            ],
            seatingLayouts: [
                new FilterOption(slug: 'halfrond', label: 'Halfrond'),
                new FilterOption(slug: 'treinzit', label: 'Treinzit'),
            ],
            sanitaryLayouts: [
                new FilterOption(slug: 'douche', label: 'Douche'),
                new FilterOption(slug: 'toilet', label: 'Toilet'),
            ],
            kitchenLayouts: [
                new FilterOption(slug: 'l-vormig', label: 'L-vormig'),
                new FilterOption(slug: 'hoek', label: 'Hoek'),
            ],
            camperChassisBrands: [
                new FilterOption(slug: 'fiat', label: 'Fiat'),
                new FilterOption(slug: 'mercedes', label: 'Mercedes'),
            ],
        );

        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('bedindeling-dwarsbed', $slugs);
        $this->assertContains('bedindeling-frans-bed', $slugs);
        $this->assertContains('zitindeling-halfrond', $slugs);
        $this->assertContains('zitindeling-treinzit', $slugs);
        $this->assertContains('sanitaire-indeling-douche', $slugs);
        $this->assertContains('sanitaire-indeling-toilet', $slugs);
        $this->assertContains('keukenindeling-l-vormig', $slugs);
        $this->assertContains('keukenindeling-hoek', $slugs);
        $this->assertContains('chassis-merk-fiat', $slugs);
        $this->assertContains('chassis-merk-mercedes', $slugs);
    }

    public function test_camper_criteria_engine_and_transmission(): void
    {
        $criteria = new CamperSearchCriteria(
            engineCapacityFrom: 2000,
            engineCapacityTo: 3000,
            transmission: TransmissionType::Automatic,
        );
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('motorinhoud-cc-vanaf-2000', $slugs);
        $this->assertContains('motorinhoud-cc-tot-en-met-3000', $slugs);
        $this->assertContains('automatisch', $slugs);
    }

    public function test_camper_criteria_empty_returns_no_slugs(): void
    {
        $criteria = new CamperSearchCriteria;

        $this->assertEmpty($criteria->toFilterSlugs());
    }

    // --- Sorting ---

    public function test_shared_filter_slugs_sort_order_price_ascending(): void
    {
        $criteria = new CarSearchCriteria(sortOrder: SortOrder::PriceAscending);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('sortering-prijsoplopend', $slugs);
    }

    public function test_shared_filter_slugs_sort_order_price_descending(): void
    {
        $criteria = new MotorcycleSearchCriteria(sortOrder: SortOrder::PriceDescending);
        $slugs = $criteria->toFilterSlugs();

        $this->assertContains('sortering-prijsaflopend', $slugs);
    }

    public function test_shared_filter_slugs_sort_order_all_values(): void
    {
        $expectedSlugs = [
            [SortOrder::BestResult, 'sortering-besteresultaat'],
            [SortOrder::LastAdded, 'sortering-laatsttoegevoegd'],
            [SortOrder::PriceAscending, 'sortering-prijsoplopend'],
            [SortOrder::PriceDescending, 'sortering-prijsaflopend'],
            [SortOrder::YearDescending, 'sortering-bouwjaaraflopend'],
            [SortOrder::YearAscending, 'sortering-bouwjaaroplopend'],
            [SortOrder::MileageAscending, 'sortering-kilometerstandoplopend'],
            [SortOrder::MileageDescending, 'sortering-kilometerstandaflopend'],
            [SortOrder::Distance, 'sortering-afstand'],
        ];

        foreach ($expectedSlugs as [$sortOrder, $expectedSlug]) {
            $criteria = new CarSearchCriteria(sortOrder: $sortOrder);
            $slugs = $criteria->toFilterSlugs();

            $this->assertContains($expectedSlug, $slugs, 'Failed for sort order: '.$sortOrder->name);
        }
    }

    public function test_shared_filter_slugs_no_sort_order_by_default(): void
    {
        $criteria = new CarSearchCriteria(
            brand: new Brand(slug: 'volkswagen', label: 'Volkswagen'),
        );
        $slugs = $criteria->toFilterSlugs();

        foreach ($slugs as $slug) {
            $this->assertStringNotContainsString('sortering-', $slug);
        }
    }

    public function test_sort_order_works_on_all_criteria_types(): void
    {
        $this->assertContains(
            'sortering-prijsoplopend',
            new CarSearchCriteria(sortOrder: SortOrder::PriceAscending)->toFilterSlugs(),
        );
        $this->assertContains(
            'sortering-prijsoplopend',
            new MotorcycleSearchCriteria(sortOrder: SortOrder::PriceAscending)->toFilterSlugs(),
        );
        $this->assertContains(
            'sortering-prijsoplopend',
            new BicycleSearchCriteria(sortOrder: SortOrder::PriceAscending)->toFilterSlugs(),
        );
        $this->assertContains(
            'sortering-prijsoplopend',
            new CamperSearchCriteria(sortOrder: SortOrder::PriceAscending)->toFilterSlugs(),
        );
    }

    // --- Pagination ---

    public function test_page_defaults_to_one(): void
    {
        $this->assertSame(1, (new CarSearchCriteria)->page());
        $this->assertSame(1, (new MotorcycleSearchCriteria)->page());
        $this->assertSame(1, (new BicycleSearchCriteria)->page());
        $this->assertSame(1, (new CamperSearchCriteria)->page());
    }

    public function test_page_can_be_set(): void
    {
        $criteria = new CarSearchCriteria(page: 5);

        $this->assertSame(5, $criteria->page());
    }

    public function test_constructor_throws_for_page_less_than_one(): void
    {
        $factories = [
            fn (): CarSearchCriteria => new CarSearchCriteria(page: 0),
            fn (): MotorcycleSearchCriteria => new MotorcycleSearchCriteria(page: 0),
            fn (): BicycleSearchCriteria => new BicycleSearchCriteria(page: 0),
            fn (): CamperSearchCriteria => new CamperSearchCriteria(page: 0),
        ];

        foreach ($factories as $factory) {
            try {
                $factory();
                $this->fail('Expected InvalidArgumentException was not thrown.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                $this->assertStringContainsString('Page must be greater than or equal to 1.', $invalidArgumentException->getMessage());
            }
        }
    }

    // --- withPage ---

    public function test_with_page_creates_new_instance(): void
    {
        $criteria = new CarSearchCriteria(
            brand: new Brand(slug: 'volkswagen', label: 'Volkswagen'),
            priceFrom: 5000,
            page: 1,
        );
        $newCriteria = $criteria->withPage(3);

        $this->assertSame(3, $newCriteria->page());
        $this->assertNotSame($criteria, $newCriteria);
    }

    public function test_with_page_preserves_all_properties(): void
    {
        $criteria = new MotorcycleSearchCriteria(
            brand: new Brand(slug: 'yamaha', label: 'Yamaha'),
            model: new Model(slug: 'mt-07', label: 'MT-07'),
            priceFrom: 3000,
            priceTo: 8000,
            sortOrder: SortOrder::PriceAscending,
            page: 1,
        );

        $newCriteria = $criteria->withPage(5);

        $this->assertSame(5, $newCriteria->page());
        $this->assertSame('yamaha', $newCriteria->brand?->slug);
        $this->assertSame('mt-07', $newCriteria->model?->slug);
        $this->assertSame(3000, $newCriteria->priceFrom);
        $this->assertSame(8000, $newCriteria->priceTo);
        $this->assertSame(SortOrder::PriceAscending, $newCriteria->sortOrder);
    }

    public function test_with_page_works_on_all_criteria_types(): void
    {
        $this->assertSame(2, (new CarSearchCriteria)->withPage(2)->page());
        $this->assertSame(3, (new MotorcycleSearchCriteria)->withPage(3)->page());
        $this->assertSame(4, (new BicycleSearchCriteria)->withPage(4)->page());
        $this->assertSame(5, (new CamperSearchCriteria)->withPage(5)->page());
    }

    public function test_with_page_returns_same_type(): void
    {
        $this->assertInstanceOf(CarSearchCriteria::class, (new CarSearchCriteria)->withPage(2));
        $this->assertInstanceOf(MotorcycleSearchCriteria::class, (new MotorcycleSearchCriteria)->withPage(2));
        $this->assertInstanceOf(BicycleSearchCriteria::class, (new BicycleSearchCriteria)->withPage(2));
        $this->assertInstanceOf(CamperSearchCriteria::class, (new CamperSearchCriteria)->withPage(2));
    }

    public function test_with_page_throws_for_page_less_than_one(): void
    {
        $criteriaList = [
            new CarSearchCriteria,
            new MotorcycleSearchCriteria,
            new BicycleSearchCriteria,
            new CamperSearchCriteria,
        ];

        foreach ($criteriaList as $criteria) {
            try {
                $criteria->withPage(0);
                $this->fail('Expected InvalidArgumentException was not thrown.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                $this->assertStringContainsString('Page must be greater than or equal to 1.', $invalidArgumentException->getMessage());
            }
        }
    }
}
