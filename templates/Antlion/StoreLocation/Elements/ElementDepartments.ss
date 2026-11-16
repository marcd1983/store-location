<%-- Staff grouped by department. $RenderedTitle comes from silverwind-blocks when installed --%>
<% if $RenderedTitle %>$RenderedTitle<% else_if $Title && $ShowTitle %><h2 class="mb-6 text-2xl font-bold tracking-tight sm:text-3xl">$Title.XML</h2><% end_if %>
<% if $Sections %>
    <div class="space-y-10">
        <% loop $Sections %>
            <section aria-labelledby="dept-{$Up.ID}-$Pos">
                <{$Up.CardHeading} id="dept-{$Up.ID}-$Pos" class="mb-4 text-xl font-semibold">$Title.XML</{$Up.CardHeading}>
                <% include Antlion/StoreLocation/Includes/StaffGrid Staff=$Staff, GridClass=$Up.GridClass, Heading=$Up.StaffHeading %>
            </section>
        <% end_loop %>
    </div>
<% end_if %>
