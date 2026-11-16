<%-- Staff cards in a grid. Pass Staff (list), GridClass and Heading --%>
<ul class="<% if $GridClass %>$GridClass<% else %>grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4<% end_if %>" role="list">
    <% loop $Staff %>
        <li><% include Antlion/StoreLocation/Includes/StaffCard Heading=$Up.Heading %></li>
    <% end_loop %>
</ul>
