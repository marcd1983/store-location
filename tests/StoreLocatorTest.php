<?php

namespace Antlion\StoreLocation\Tests;

use Antlion\StoreLocation\Geocoding\Geocoder;
use Antlion\StoreLocation\Pages\StoreLocationHolderPage;
use Antlion\StoreLocation\Pages\StoreLocationPage;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;

/**
 * Coordinates from the address on save, manual corrections winning, and
 * the locator data for the holder page. Uses a fake geocoder: no network.
 */
class StoreLocatorTest extends SapphireTest
{
    protected $usesDatabase = true;

    private FakeGeocoder $geocoder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geocoder = new FakeGeocoder();
        Injector::inst()->registerService($this->geocoder, Geocoder::class);
    }

    public function testCoordinatesComeFromTheAddressOnSave(): void
    {
        $page = $this->location(['Address' => '14 Mill Lane', 'City' => 'Westbrook']);

        $this->assertSame(['14 Mill Lane, Westbrook'], $this->geocoder->lookups);
        $this->assertEqualsWithDelta(45.1, (float) $page->Latitude, 0.0001);
        $this->assertEqualsWithDelta(-122.2, (float) $page->Longitude, 0.0001);
        $this->assertTrue($page->getHasCoordinates());
    }

    public function testUnchangedAddressIsNotLookedUpAgain(): void
    {
        $page = $this->location(['Address' => '14 Mill Lane', 'City' => 'Westbrook']);
        $page->Phone = '555';
        $page->write();

        $this->assertCount(1, $this->geocoder->lookups);
    }

    public function testChangedAddressIsLookedUpAgain(): void
    {
        $page = $this->location(['Address' => '14 Mill Lane', 'City' => 'Westbrook']);
        $page->Address = '2 Station Road';
        $page->write();

        $this->assertSame('2 Station Road, Westbrook', end($this->geocoder->lookups));
    }

    public function testManualCoordinatesWin(): void
    {
        $page = $this->location(['Address' => '14 Mill Lane', 'Latitude' => 40.5, 'Longitude' => -100.25]);
        $this->assertSame([], $this->geocoder->lookups, 'coordinates given: no lookup');

        // Editor corrects the pin and the address in the same save
        $page->Address = '15 Mill Lane';
        $page->Latitude = 40.6;
        $page->write();
        $this->assertSame([], $this->geocoder->lookups);
        $this->assertEqualsWithDelta(40.6, (float) $page->Latitude, 0.0001);
    }

    public function testNoMatchLeavesCoordinatesEmpty(): void
    {
        $this->geocoder->result = null;
        $page = $this->location(['Address' => 'Nowhere']);

        $this->assertFalse($page->getHasCoordinates());
    }

    public function testGeocodingCanBeTurnedOff(): void
    {
        StoreLocationPage::config()->set('geocode_on_save', false);
        $this->location(['Address' => '14 Mill Lane']);

        $this->assertSame([], $this->geocoder->lookups);
    }

    public function testLocatorDataListsTheHoldersLocations(): void
    {
        $holder = StoreLocationHolderPage::create(['Title' => 'Locations']);
        $holder->write();
        $this->location(['Title' => 'B yard', 'Address' => '1 B St', 'ParentID' => $holder->ID]);
        $this->geocoder->result = null;
        $this->location(['Title' => 'A yard', 'Address' => '1 A St', 'ParentID' => $holder->ID]);
        $this->location(['Title' => 'Elsewhere', 'Address' => '1 C St']);

        $data = json_decode($holder->getLocatorJSON(), true);

        $this->assertSame(['A yard', 'B yard'], array_column($data['locations'], 'name'), 'children only, by title');
        $this->assertNull($data['locations'][0]['lat'], 'no coordinates: listed, but not on the map');
        $this->assertSame(45.1, $data['locations'][1]['lat']);
        $this->assertSame('mi', $data['unit']);
        $this->assertTrue($holder->getHasMap());
        $this->assertStringNotContainsString('</', $holder->getLocatorJSON(), 'safe inside a script tag');
    }

    private function location(array $fields): StoreLocationPage
    {
        $page = StoreLocationPage::create(['Title' => 'Yard', ...$fields]);
        $page->write();

        return $page;
    }
}

class FakeGeocoder implements Geocoder, TestOnly
{
    public array $lookups = [];

    public ?array $result = ['lat' => 45.1, 'lng' => -122.2];

    public function geocode(string $address): ?array
    {
        $this->lookups[] = $address;

        return $this->result;
    }
}
