<?php

namespace Antlion\StoreLocation\Geocoding;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use Throwable;

/**
 * Geocoding with OpenStreetMap's Nominatim service (free, no key).
 *
 * Follows the Nominatim usage policy (operations.osmfoundation.org/policies/nominatim):
 * an identifying User-Agent, one request when a location's address is
 * saved (never per page view), and no bulk geocoding. Set `email` so the
 * operators can reach you; for heavy use, point `endpoint` at your own or a
 * commercial Nominatim instance.
 *
 * Failures (no match, timeout, network) return null and are logged: saving
 * a location never fails because of geocoding.
 */
class NominatimGeocoder implements Geocoder
{
    use Configurable;

    private static string $endpoint = 'https://nominatim.openstreetmap.org/search';

    /** Contact address sent with requests, as the usage policy asks */
    private static ?string $email = null;

    private static int $timeout = 8;

    private ?Client $client = null;

    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        try {
            $query = ['q' => $address, 'format' => 'jsonv2', 'limit' => 1];
            if ($email = static::config()->get('email')) {
                $query['email'] = $email;
            }
            $response = $this->client()->get(static::config()->get('endpoint'), ['query' => $query]);
            $result = json_decode((string) $response->getBody(), true)[0] ?? null;

            if (!isset($result['lat'], $result['lon'])) {
                return null;
            }

            return ['lat' => round((float) $result['lat'], 6), 'lng' => round((float) $result['lon'], 6)];
        } catch (Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->warning(
                sprintf('Store location geocoding failed for "%s": %s', $address, $e->getMessage())
            );

            return null;
        }
    }

    private function client(): Client
    {
        return $this->client ??= new Client([
            'timeout' => static::config()->get('timeout'),
            'connect_timeout' => 4,
            'headers' => [
                // Identifies the application, as Nominatim requires
                'User-Agent' => 'antlion/store-location (' . Director::absoluteBaseURL() . ')',
            ],
        ]);
    }
}
