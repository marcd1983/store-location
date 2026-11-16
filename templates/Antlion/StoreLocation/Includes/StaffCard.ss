<%-- A staff member. Scope: StaffMember. Pass Heading (h2-h6) --%>
<article class="card h-full overflow-hidden border border-base-300 bg-base-100 shadow-sm">
    <% if $Image %>
        <figure class="aspect-square bg-base-200">
            <% with $Image.Fill(600, 600) %>
                <img src="$URL" alt="" width="$Width" height="$Height" loading="lazy" class="size-full object-cover">
            <% end_with %>
        </figure>
    <% end_if %>
    <div class="card-body gap-1 p-5">
        <{$Heading} class="text-lg font-semibold">$FullName.XML</{$Heading}>
        <% if $Title %><p class="text-sm opacity-70">$Title.XML</p><% end_if %>
        <% if $Phone || $Email %>
            <div class="mt-3 flex flex-wrap gap-2">
                <% if $Phone %><a href="tel:$Phone.ATT" class="btn btn-outline btn-sm"><%t Antlion\\StoreLocation.Call 'Call' %><span class="sr-only"> $FullName.XML</span></a><% end_if %>
                <% if $Email %><a href="mailto:$Email.ATT" class="btn btn-ghost btn-sm"><%t Antlion\\StoreLocation.EmailStaff 'Email' %><span class="sr-only"> $FullName.XML</span></a><% end_if %>
            </div>
        <% end_if %>
    </div>
</article>
