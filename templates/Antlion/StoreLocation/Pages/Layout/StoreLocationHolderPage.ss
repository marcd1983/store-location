<%-- All locations as cards: name, address, open-now status, phone --%>
<% include PageHeader %>

<% if $Content %>
    <div class="sw-container pb-section">
        <div class="prose max-w-narrow">$Content</div>
    </div>
<% else_if $ElementalArea.Elements %>
    <div class="<% if not $HidePageTitle %>sw-after-header<% end_if %>">$ElementalArea</div>
<% end_if %>

<div class="sw-container pb-section<% if $HidePageTitle %> pt-section<% end_if %>">
    <% if $Locations %>
        <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" role="list">
            <% loop $Locations %>
                <li><% include Antlion/StoreLocation/Includes/LocationCard %></li>
            <% end_loop %>
        </ul>
    <% else %>
        <div class="alert"><%t Antlion\\StoreLocation.NoLocations 'No locations yet.' %></div>
    <% end_if %>
</div>
