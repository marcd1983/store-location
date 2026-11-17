<?php

namespace Antlion\StoreLocation;

use SilverStripe\Core\Config\Config;

/**
 * Optional integration with innoweb/silverstripe-cookie-consent, used by
 * the map embed (location page) and the store locator map (holder page).
 */
class Consent
{
    private const string MODULE = 'Innoweb\\CookieConsent\\CookieConsent';

    /**
     * The group a third-party map must wait for, or null when it can load
     * now: no consent module, the group isn't configured (or null), or the
     * visitor has already accepted it.
     */
    public static function waitingFor(?string $group): ?string
    {
        if (!$group || !class_exists(self::MODULE)) {
            return null;
        }
        if (!array_key_exists($group, (array) Config::inst()->get(self::MODULE, 'cookies'))) {
            return null;
        }

        return (self::MODULE)::check($group) ? null : $group;
    }
}
