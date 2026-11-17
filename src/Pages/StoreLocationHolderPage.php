<?php

namespace Antlion\StoreLocation\Pages;

use Antlion\StoreLocation\Consent;
use Page;
use SilverStripe\ORM\DataList;

/**
 * Lists every location, with a store locator: a map of the locations
 * (Leaflet + OpenStreetMap tiles) and "Use my location", which sorts the
 * list by distance in the browser. The visitor's position never leaves
 * their browser.
 */
class StoreLocationHolderPage extends Page
{
    private static string $table_name = 'StoreLocationHolderPage';

    private static string $description = 'A holder page that lists all store locations, with a map.';

    private static $icon_class = 'font-icon-p-map';

    private static array $allowed_children = [
        StoreLocationPage::class,
    ];

    /**
     * Map tiles. The default is OpenStreetMap's tile server, which is fine
     * for modest traffic (see operations.osmfoundation.org/policies/tiles);
     * busy sites should point this at a tile provider and set its
     * attribution.
     */
    private static string $map_tile_url = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    private static string $map_attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    /**
     * Cookie consent group the map waits for (tiles come from a third
     * party). null, or a group not in the consent config, loads it straight away.
     */
    private static ?string $map_consent_group = 'External';

    /** mi or km */
    private static string $distance_unit = 'mi';

    /**
     * Location pages under this holder, by title.
     *
     * @return DataList<StoreLocationPage>
     */
    public function Locations(): DataList
    {
        return StoreLocationPage::get()->filter('ParentID', $this->ID)->sort('Title');
    }

    /** Whether any location can go on the map */
    public function getHasMap(): bool
    {
        foreach ($this->Locations() as $location) {
            if ($location->getHasCoordinates()) {
                return true;
            }
        }

        return false;
    }

    public function getMapConsentGroup(): ?string
    {
        return Consent::waitingFor(static::config()->get('map_consent_group'));
    }

    /**
     * Everything the locator script needs, as JSON for a
     * <script type="application/json"> block.
     */
    public function getLocatorJSON(): string
    {
        $config = static::config();
        $data = [
            'locations' => array_map(fn (StoreLocationPage $page) => $page->getLocatorData(), $this->Locations()->toArray()),
            'tiles' => $config->get('map_tile_url'),
            'attribution' => $config->get('map_attribution'),
            'unit' => $config->get('distance_unit') === 'km' ? 'km' : 'mi',
        ];

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}';
    }
}
