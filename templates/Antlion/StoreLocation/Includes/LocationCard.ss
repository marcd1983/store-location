<%-- A location in the holder's list; the whole card links to it. Scope: StoreLocationPage --%>
<article class="card group relative h-full border border-base-300 bg-base-100 shadow-sm transition-shadow hover:shadow-md">
    <div class="card-body gap-3">
        <h2 class="card-title text-xl">
            <a href="$Link" class="after:absolute after:inset-0 after:rounded-box focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-primary">$DisplayName.XML</a>
        </h2>
        <% if $FullAddress %><address class="not-italic opacity-80">$FullAddress.XML</address><% end_if %>
        <% if $Schedule %><% include Antlion/StoreLocation/Includes/TodaysHours %><% end_if %>
        <% if $Phone %>
            <%-- Above the card link so it stays tappable --%>
            <a href="$PhoneHref.ATT" class="link link-hover relative z-10 w-fit font-medium">$Phone.XML</a>
        <% end_if %>
    </div>
</article>
