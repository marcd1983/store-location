<%-- Curated staff list. $RenderedTitle comes from silverwind-blocks when installed --%>
<% if $RenderedTitle %>$RenderedTitle<% else_if $Title && $ShowTitle %><h2 class="mb-6 text-2xl font-bold tracking-tight sm:text-3xl">$Title.XML</h2><% end_if %>
<% if $OrderedStaff %>
    <% include Antlion/StoreLocation/Includes/StaffGrid Staff=$OrderedStaff, GridClass=$GridClass, Heading=$CardHeading %>
<% end_if %>
