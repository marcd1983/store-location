
<% include TitleBar %>
<% if $Content %>
		<div class="grid-x content">
			<div class="cell">
				$Content
			</div>
		</div>
	<% else %>
			$ElementalArea
	<% end_if %>
  <div class="grid-x grid-margin-x grid-margin-y">
    <div class="cell small-12 medium-6">
   
      <h3 class="section-title">Visit us</h3>
      <%-- Address block --%>
      <% if $Address || $City || $State || $Zip %>
  
          <address class="store-location-address">
            <% if $Address %>$Address.XML<br /><% end_if %>
            <% if $Address2 %>$Address2.XML<br /><% end_if %>
            <% if $City || $State || $Zip %>
              $City.XML<% if $City && $State %>, <% end_if %>$State.XML <% if $Zip %>$Zip.XML<br /><% end_if %>
            <% end_if %>
          </address>
      
          <% if $MapLinkURL %>
              <a class="button small hollow" href="$MapLinkURL.ATT" target="_blank" rel="noopener">View larger map</a>
          <% else %>
            <%-- Google Maps link (basic, no API key required) --%>
            <a target="_blank" rel="noopener" class="button small hollow store-location-map-link"
              href="https://www.google.com/maps/search/?api=1&query=$FullAddress.URLEncode">
              View on Google Maps
            </a>
          <% end_if %>
      <% end_if %>
      <h3 class="section-title">Contact Us</h3>
      <% if $Phone %>
        <p class="store-location-phone">
          <strong>Phone:</strong>
          <a href="tel:$Phone.Plain">$Phone.XML</a>
        </p>
      <% end_if %>
      <% if $Email %>
        <p class="store-location-email">
          <strong>Email:</strong>
          <a href="mailto:$Email.XML">$Email.XML</a>
        </p>
      <% end_if %>
      <h3 class="section-title">Today’s Hours</h3>
      <%-- Use your helper: returns ArrayData-like structure --%>
      <% with $effectiveHoursForDate($Now.Format('Y-m-d')) %>
        <% if $IsClosed %>
          <p class="store-location__todayhours is-closed">Closed today</p>
        <% else %>
          <p class="store-location__todayhours">
            Open today: <strong>$OpenTimeNice.XML</strong>–<strong>$CloseTimeNice.XML</strong>
          </p>
        <% end_if %>

        <% if $Note %>
          <p class="store-location__hours-note">$Note.XML</p>
        <% end_if %>

        <% if $Source == 'holiday' %>
          <p class="store-location__hours-source">Holiday hours in effect.</p>
        <% end_if %>
      <% end_with %>
    </div>

    <div class="cell small-12 medium-6">
      <div class="card">
        <div class="card-section">
          <h3 class="section-title">Send us a message</h3>
          <% include FormMessageToast %>
          $ContactForm
        </div>
      </div>
    </div>
  </div>

  <% include GoogleMap %>
  <% include StoreSchedule %>
  <% include Departments %>
  <%-- LocalBusiness JSON-LD. Adjust @type to your niche (e.g., "HardwareStore", "AutoPartsStore"). --%>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "$Title.XML",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "$Address.XML<% if $Address2 %>, $Address2.XML<% end_if %>",
      "addressLocality": "$City.XML",
      "addressRegion": "$State.XML",
      "postalCode": "$Zip.XML"
    },
    <% if $Phone %>"telephone": "$Phone.Plain",<% end_if %>
    <% if $Email %>"email": "$Email.XML",<% end_if %>
    "url": "$AbsoluteLink"
  }
  </script>
