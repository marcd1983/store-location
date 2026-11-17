<?php

namespace Antlion\StoreLocation\Pages;

use PageController;
use SilverStripe\View\Requirements;

/**
 * Loads the store locator script (an Alpine component) and Leaflet's
 * stylesheet. Leaflet's script itself is loaded by the locator only when
 * the map is shown, so a visitor who never loads the map never downloads it.
 *
 * @method StoreLocationHolderPage data()
 */
class StoreLocationHolderPageController extends PageController
{
    protected function init()
    {
        parent::init();

        if ($this->data()->getHasMap() || $this->data()->Locations()->exists()) {
            Requirements::css('antlion/store-location: client/vendor/leaflet/leaflet.css');
            Requirements::css('antlion/store-location: client/dist/store-locator.css');
            Requirements::javascript('antlion/store-location: client/dist/store-locator.js', ['type' => 'module']);
        }
    }
}
