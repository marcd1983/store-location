/**
 * Store locator (StoreLocationHolderPage): a Leaflet map of the locations,
 * and "Use my location", which sorts the server-rendered list by distance.
 *
 * Everything happens in the browser: the visitor's position is never sent
 * anywhere. Leaflet's script is loaded only when the map is shown (straight
 * away, or after cookie consent / "Load map" when the page asks to wait).
 *
 * Registered as an Alpine component; the Silverwind theme starts Alpine
 * after module scripts have run. Plain JS on purpose: no build step.
 */

const EARTH_RADIUS = { km: 6371, mi: 3958.8 };

/** Great-circle distance between two {lat, lng} points */
function distance(a, b, unit) {
  const rad = (deg) => (deg * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat);
  const dLng = rad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;

  return 2 * EARTH_RADIUS[unit] * Math.asin(Math.sqrt(h));
}

function formatDistance(value, unit) {
  const rounded = value < 10 ? value.toFixed(1) : Math.round(value).toString();

  return `${rounded} ${unit}`;
}

let leafletLoading;
function loadLeaflet(src) {
  leafletLoading ??= new Promise((resolve, reject) => {
    if (window.L) {
      resolve(window.L);
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.onload = () => resolve(window.L);
    script.onerror = () => reject(new Error('Leaflet failed to load'));
    document.head.append(script);
  });

  return leafletLoading;
}

/** Popup for a marker, built with text nodes (no HTML from data) */
function popupContent(location, linkLabel) {
  const box = document.createElement('div');
  const name = document.createElement('strong');
  name.textContent = location.name;
  box.append(name);
  if (location.address) {
    const address = document.createElement('div');
    address.textContent = location.address;
    box.append(address);
  }
  const link = document.createElement('a');
  link.href = location.url;
  link.textContent = linkLabel;
  box.append(link);

  return box;
}

document.addEventListener('alpine:init', () => {
  window.Alpine.data('storeLocator', () => ({
    data: null,
    map: null,
    youMarker: null,
    mapShown: false,
    locating: false,
    message: '',

    init() {
      this.data = JSON.parse(this.$refs.data.textContent);
      // Without a consent group to wait for, show the map straight away
      if (this.$refs.map && !this.$root.dataset.consentGroup) {
        this.showMap();
      }
    },

    async showMap() {
      if (this.map || !this.$refs.map) return;
      this.mapShown = true;

      let L;
      try {
        L = await loadLeaflet(this.$root.dataset.leaflet);
      } catch {
        this.message = this.$root.dataset.msgMapFailed;
        return;
      }
      L.Icon.Default.imagePath = this.$root.dataset.leaflet.replace(/leaflet\.js(\?.*)?$/, 'images/');

      this.map = L.map(this.$refs.map, { scrollWheelZoom: false });
      L.tileLayer(this.data.tiles, { attribution: this.data.attribution, maxZoom: 19 }).addTo(this.map);

      const points = [];
      for (const location of this.data.locations) {
        if (location.lat === null) continue;
        L.marker([location.lat, location.lng], { title: location.name, alt: location.name })
          .addTo(this.map)
          .bindPopup(() => popupContent(location, this.$root.dataset.msgView));
        points.push([location.lat, location.lng]);
      }
      this.fit(points);
    },

    fit(points) {
      if (!this.map || !points.length) return;
      if (points.length === 1) {
        this.map.setView(points[0], 13);
      } else {
        this.map.fitBounds(points, { padding: [32, 32], maxZoom: 14 });
      }
    },

    locate() {
      if (!navigator.geolocation) {
        this.message = this.$root.dataset.msgUnsupported;
        return;
      }
      this.locating = true;
      this.message = this.$root.dataset.msgLocating;

      navigator.geolocation.getCurrentPosition(
        (position) => {
          this.locating = false;
          this.sortByDistance({ lat: position.coords.latitude, lng: position.coords.longitude });
        },
        (error) => {
          this.locating = false;
          this.message = error.code === error.PERMISSION_DENIED
            ? this.$root.dataset.msgDenied
            : this.$root.dataset.msgFailed;
        },
        { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
      );
    },

    /** Reorders the list nearest first, shows distances, and fits the map */
    sortByDistance(here) {
      const unit = this.data.unit;
      const distances = new Map(
        this.data.locations
          .filter((location) => location.lat !== null)
          .map((location) => [String(location.id), distance(here, location, unit)]),
      );

      const items = [...this.$refs.list.querySelectorAll('[data-location-id]')];
      items.sort((a, b) => (distances.get(a.dataset.locationId) ?? Infinity) - (distances.get(b.dataset.locationId) ?? Infinity));

      items.forEach((item, index) => {
        this.$refs.list.append(item);
        const value = distances.get(item.dataset.locationId);
        const label = item.querySelector('[data-distance]');
        if (label && value !== undefined) {
          label.textContent = formatDistance(value, unit);
          label.hidden = false;
        }
        item.toggleAttribute('data-nearest', index === 0 && value !== undefined);
      });

      const nearest = this.data.locations.find((location) => String(location.id) === items[0]?.dataset.locationId);
      const nearestDistance = nearest && distances.get(String(nearest.id));
      this.message = nearestDistance !== undefined
        ? this.$root.dataset.msgNearest.replace('{name}', nearest.name).replace('{distance}', formatDistance(nearestDistance, unit))
        : '';

      if (this.map) {
        const L = window.L;
        this.youMarker?.remove();
        this.youMarker = L.circleMarker([here.lat, here.lng], { radius: 8, weight: 3, fillOpacity: 0.9 })
          .addTo(this.map)
          .bindTooltip(this.$root.dataset.msgYou);
        this.fit(nearest ? [[here.lat, here.lng], [nearest.lat, nearest.lng]] : [[here.lat, here.lng]]);
      }
    },
  }));
});
