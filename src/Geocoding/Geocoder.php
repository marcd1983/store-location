<?php

namespace Antlion\StoreLocation\Geocoding;

/**
 * Turns an address into coordinates. Swap the implementation with the
 * Injector, e.g. for a commercial geocoder or a fake in tests:
 *
 *   SilverStripe\Core\Injector\Injector:
 *     Antlion\StoreLocation\Geocoding\Geocoder:
 *       class: App\Geocoding\MyGeocoder
 */
interface Geocoder
{
    /**
     * @return array{lat: float, lng: float}|null null when not found or unavailable
     */
    public function geocode(string $address): ?array;
}
