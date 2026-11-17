<%--
    Store locator: every location as a card, a map (Leaflet + OpenStreetMap)
    and "Use my location", which sorts the cards by distance in the browser
    (client/dist/store-locator.js). Without JavaScript it's a plain list.
--%>
<% include PageHeader %>

<% if $Content %>
    <div class="sw-container pb-section">
        <div class="prose max-w-narrow">$Content</div>
    </div>
<% else_if $ElementalArea.Elements %>
    <div class="<% if not $HidePageTitle %>sw-after-header<% end_if %>">$ElementalArea</div>
<% end_if %>

<div class="sw-container pb-section<% if $HidePageTitle %> pt-section<% end_if %>"
     x-data="storeLocator" @consent-activated="showMap()"
     data-leaflet="$resourceURL('antlion/store-location: client/vendor/leaflet/leaflet.js')"<% if $MapConsentGroup %>
     data-consent-group="$MapConsentGroup.ATT"<% end_if %>
     data-msg-locating="<%t Antlion\\StoreLocation.Locating 'Finding your location…' %>"
     data-msg-nearest="<%t Antlion\\StoreLocation.Nearest 'Nearest to you: {name}, {distance} away. The list is now sorted by distance.' %>"
     data-msg-denied="<%t Antlion\\StoreLocation.LocationDenied 'Location access was blocked. Allow it in your browser to sort by distance.' %>"
     data-msg-failed="<%t Antlion\\StoreLocation.LocationFailed 'We could not find your location. Please try again.' %>"
     data-msg-unsupported="<%t Antlion\\StoreLocation.LocationUnsupported 'Your browser cannot share your location.' %>"
     data-msg-map-failed="<%t Antlion\\StoreLocation.MapFailed 'The map could not be loaded.' %>"
     data-msg-you="<%t Antlion\\StoreLocation.You 'You are here' %>"
     data-msg-view="<%t Antlion\\StoreLocation.ViewLocation 'View location' %>">
    <script type="application/json" x-ref="data">$LocatorJSON.RAW</script>

    <% if $Locations %>
        <div class="mb-6 flex flex-wrap items-center gap-x-4 gap-y-2">
            <button type="button" class="btn btn-primary" @click="locate()" :disabled="locating" x-cloak x-show="'geolocation' in navigator">
                <span x-show="locating" class="loading loading-spinner loading-sm" aria-hidden="true"></span>
                <svg x-show="!locating" class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="m9.69 18.933.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 0 0 .281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 1 0 3 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 0 0 2.273 1.765 11.842 11.842 0 0 0 .976.544l.062.029.018.008.006.003ZM10 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" clip-rule="evenodd"/></svg>
                <%t Antlion\\StoreLocation.UseMyLocation 'Use my location' %>
            </button>
            <p class="text-sm opacity-80" role="status" aria-live="polite" x-text="message"></p>
        </div>

        <div class="grid gap-8<% if $HasMap %> lg:grid-cols-12<% end_if %>">
            <ul x-ref="list" role="list" class="grid content-start gap-6 sm:grid-cols-2<% if $HasMap %> lg:col-span-5 lg:grid-cols-1<% else %> lg:grid-cols-3<% end_if %>">
                <% loop $Locations %>
                    <li data-location-id="$ID"><% include Antlion/StoreLocation/Includes/LocationCard %></li>
                <% end_loop %>
            </ul>

            <% if $HasMap %>
                <div class="lg:col-span-7">
                    <%-- isolate: keeps Leaflet's high z-indexes below the site header and menus --%>
                    <div class="relative isolate h-96 overflow-hidden rounded-box border border-base-300 bg-base-200 lg:sticky lg:top-24 lg:h-[36rem]">
                        <div x-ref="map" class="size-full" role="region" aria-label="<%t Antlion\\StoreLocation.MapOfLocations 'Map of our locations' %>"></div>
                        <% if $MapConsentGroup %>
                            <%-- The theme's consent gate fires consent-activated here when the group is accepted --%>
                            <div data-cookie-consent="$MapConsentGroup.ATT" hidden></div>
                            <div x-show="!mapShown" class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
                                <p class="max-w-sm text-sm opacity-80"><%t Antlion\\StoreLocation.MapTilesConsent 'The map loads its images from the OpenStreetMap servers.' %></p>
                                <button type="button" class="btn btn-primary btn-sm" @click="showMap()"><%t Antlion\\StoreLocation.LoadMap 'Load map' %></button>
                            </div>
                        <% end_if %>
                    </div>
                </div>
            <% end_if %>
        </div>
    <% else %>
        <div class="alert"><%t Antlion\\StoreLocation.NoLocations 'No locations yet.' %></div>
    <% end_if %>
</div>
