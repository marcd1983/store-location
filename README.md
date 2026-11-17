# Store Location

Store locations for Silverstripe CMS 6: addresses, weekly and holiday hours,
departments, staff, and contact forms that route each message to the right
people.

- **Locations page**: a store locator, see below.
- **Location page**: address, directions, phone/email, an "Open now" badge,
  this week's hours (today highlighted), upcoming holiday hours, a map embed,
  staff grouped by department, a contact form, and LocalBusiness JSON-LD
  including opening hours.
- **Locations holder page**: every location as a card with today's status.
- **Staff & Hours admin**: staff, departments and schedules. A schedule is
  created for each new location (Mon–Sun 08:00–17:00), and U.S. federal
  holidays can be generated per year.
- **Blocks** (Elemental): *Staff List (curated)* and *Department Staff*.
- **Userforms fields**: *Location dropdown* and *Department dropdown*.

The PHP is theme-neutral. The default templates use **Tailwind CSS 4 and
daisyUI 5**, matching the Silverwind theme; any theme can override them (see
[Other themes](#other-themes)).

## Requirements

- Silverstripe CMS 6, PHP 8.3+
- dnadesign/silverstripe-elemental 6, silverstripe/userforms 7,
  symbiote/silverstripe-gridfieldextensions 5
- Optional: silverstripe/spamprotection (forms use it when installed),
  antlion/silverwind-blocks (block heading levels and spacing)

## Installation

```sh
composer require antlion/store-location:^2
vendor/bin/sake db:build --flush
```

### Tailwind themes

Tailwind only generates classes it finds in the files it scans. Add the
module to the theme's stylesheet (Silverwind: `src/css/silverwind.css`) and
rebuild the theme:

```css
@source "../../../../vendor/antlion/store-location/templates";
@source "../../../../vendor/antlion/store-location/src";
```

`src/` is scanned because the staff grid's column classes live in PHP
(`Traits/StaffGrid.php`).

## Store locator

The locations holder page lists every location with a **map** (Leaflet +
OpenStreetMap tiles) and a **Use my location** button that sorts the list by
distance and marks the nearest. Distances are worked out in the browser: the
visitor's position is never sent anywhere. Without JavaScript the page is a
plain list.

- **Coordinates** come from the address when a location is saved, using
  OpenStreetMap's Nominatim geocoder (free, no key). Editors can correct
  the Latitude/Longitude under *Location details*; a manual correction is
  kept. A location without coordinates is listed but not on the map.
- **Leaflet 1.9.4** ships in `client/vendor/leaflet` (no CDN, works with a
  strict `script-src 'self'`) and is loaded only when the map is shown.
- **Cookie consent:** with innoweb/silverstripe-cookie-consent, the map
  waits for the `External` group (tiles come from a third party), with a
  "Load map" button meanwhile.

```yaml
Antlion\StoreLocation\Pages\StoreLocationHolderPage:
  distance_unit: km                 # default mi
  map_consent_group: External       # null: always load the map
  # Busy sites: use a tile provider rather than OpenStreetMap's own servers
  map_tile_url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
  map_attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'

Antlion\StoreLocation\Pages\StoreLocationPage:
  geocode_on_save: true             # false: coordinates by hand only

Antlion\StoreLocation\Geocoding\NominatimGeocoder:
  email: you@example.com            # contact for Nominatim's operators
```

Nominatim's usage policy allows light use like this (one request when an
address is saved, never per page view). Bulk imports or busy sites should
use their own or a commercial geocoder: implement
`Antlion\StoreLocation\Geocoding\Geocoder` and bind it with the Injector.

## Routing contact messages

The location page's contact form sends to the first of these that has a
valid address:

1. staff in the chosen department who are linked to this location
2. the department's email
3. the page's *Location Recipients* (comma separated)
4. the site email (Settings)

The sender is SiteConfig `DefaultFromEmail`/`DefaultFromName` when set,
otherwise `noreply@<site host>`; Reply-To is the visitor.

`?DepartmentID=123` or `?dept=Sales` on the page URL preselects a department.

## Configuration

```yaml
Antlion\StoreLocation\Pages\StoreLocationPage:
  # schema.org type for the JSON-LD, e.g. HardwareStore, AutoPartsStore
  schema_type: LocalBusiness

Antlion\StoreLocation\Pages\StoreLocationPage:
  # Cookie consent group the Google Maps embed waits for (null = always load)
  map_consent_group: External

Antlion\StoreLocation\Pages\StoreLocationPageController:
  # Classes on the form submit buttons (Foundation: 'button primary')
  submit_button_class: 'btn btn-primary'
```

**Map embeds and cookie consent.** With innoweb/silverstripe-cookie-consent
installed and the `map_consent_group` group configured, the map isn't loaded
until the visitor accepts that group: a placeholder offers "Load map" (this
map only) and "Open in Google Maps". The template marks the iframe
`data-cookie-consent`/`data-src`, which Silverwind's consent gate activates
when the group is accepted in the banner. Without the consent module, or if
the group isn't configured, the map loads normally.

**Timezone.** "Open now", today's hours and upcoming holidays use the
server's PHP timezone. Set `date.timezone` to the business's timezone, or
visitors will see the wrong day near midnight.

## Extension hooks

| Hook | On | Use |
|---|---|---|
| `onAfterLocationEnquiry(array $enquiry)` | `StoreLocationPageController` | After each send attempt. `$enquiry` has `FormName`, `FormAction`, `PageID`, `Context`, `ContextLink`, `SubmitterName`, `SubmitterEmail`, `SubmitterPhone`, `Message`, `SentTo`, `Status` (`Emailed`/`Error`), `ErrorMessage`, `RawData` (the form's fields). Log enquiries to your own model here. |
| `updateLocalBusinessSchema(array &$node)` | `StoreLocationPage` | Adjust the JSON-LD, e.g. add `priceRange` or `image`. |

## Templates

| Template | Purpose |
|---|---|
| `Antlion/StoreLocation/Pages/Layout/StoreLocationPage.ss` | Location page |
| `Antlion/StoreLocation/Pages/Layout/StoreLocationHolderPage.ss` | Locations list |
| `Antlion/StoreLocation/Elements/ElementStaff.ss`, `ElementDepartments.ss` | Staff blocks |
| `Antlion/StoreLocation/Includes/TodaysHours.ss` | Open-now badge and today's hours |
| `Antlion/StoreLocation/Includes/WeeklyHours.ss` | Hours table and holiday hours |
| `Antlion/StoreLocation/Includes/StaffGrid.ss`, `StaffCard.ss` | Staff cards |
| `Antlion/StoreLocation/Includes/LocationCard.ss` | A location in the list |
| `Antlion/StoreLocation/Email/ContactEmailLocation.ss`, `StaffContactEmail.ss` | Notification emails |

Template data comes from methods on the page rather than template logic:
`$TodaysHours` (with `IsOpenNow`), `$WeeklyHours`, `$UpcomingHolidays`,
`$AllStaffSections`, `$MapURL`, `$PhoneHref`, `$DisplayName`,
`$LocalBusinessSchemaJSON`.

The layouts include the theme's `PageHeader` and use Silverwind's layout
classes (`sw-container`, `pb-section`, `sw-after-header`).

### Other themes

Put templates at the same paths in your theme to override the module's.
`silverstripe-themes/foundation-theme` (this project's Foundation theme) ships Foundation versions of all of them; Foundation
sites should also set `submit_button_class: 'button primary'`.

## Upgrading from 1.x

- **Templates are Tailwind/daisyUI.** Foundation sites: use a theme that
  overrides them (foundation-theme does) and set `submit_button_class`.
  The module's old `Includes/*.ss` (StaffCard, StoreSchedule, Departments,
  GoogleMap, StaffMembers, FormMessageToast) are gone; new includes live
  under `Antlion/StoreLocation/Includes/`.
- **Enquiries are no longer written to `App\Models\FormSubmission`.** The
  module no longer depends on project code; to keep logging, add an
  extension with `onAfterLocationEnquiry()` to
  `StoreLocationPageController`.
- **Userforms fields moved namespace** to `Antlion\StoreLocation\Fields\`.
  `db:build` rewrites existing records (`classname_value_remapping`).
- **Removed** the unused `StoreLocationPageExtension` (it duplicated the
  page's own schedule relation) and the `envcheck` action.
- **Fixed:** today's hours and holiday dates used the ICU pattern `Y-m-d`
  (week-year / minutes), so they could show the wrong day; the staff contact
  form's missing email template; the page URL in emails is now taken from
  the server, not the submission; staff contact only reaches visible staff
  at that location; the JSON-LD is always valid JSON.

## Tests

`tests/` covers the hours logic (weekly, holiday overrides, recurring
holidays, open now, upcoming holidays) and the JSON-LD. Run them from a
project that has the module installed:

```sh
vendor/bin/phpunit vendor/antlion/store-location/tests
```

## License

BSD-3-Clause
