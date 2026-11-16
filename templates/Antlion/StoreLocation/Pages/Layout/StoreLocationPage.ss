<%--
    A location: details, today's status and weekly hours beside the contact
    form, then the map and staff. Tailwind CSS 4 + daisyUI 5; the theme must
    scan this module's templates and src/ (see README). Themes can override
    any part, e.g. foundation-theme ships its own copy of this layout.
--%>
<% include PageHeader %>

<% if $Content %>
    <div class="sw-container pb-section">
        <div class="prose max-w-narrow">$Content</div>
    </div>
<% else_if $ElementalArea.Elements %>
    <div class="<% if not $HidePageTitle %>sw-after-header<% end_if %>">$ElementalArea</div>
<% end_if %>

<div class="sw-container pb-section<% if $HidePageTitle %> pt-section<% end_if %>">
    <div class="grid gap-8 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-5">
            <section class="card border border-base-300 bg-base-100" aria-labelledby="location-visit">
                <div class="card-body gap-5">
                    <% if $Schedule %><% include Antlion/StoreLocation/Includes/TodaysHours %><% end_if %>

                    <% if $FullAddress %>
                        <div>
                            <h2 id="location-visit" class="mb-2 text-lg font-semibold"><%t Antlion\\StoreLocation.VisitUs 'Visit us' %></h2>
                            <address class="not-italic leading-relaxed">
                                <% if $LocationName %><strong class="block">$LocationName.XML</strong><% end_if %>
                                <% if $Address %><span class="block">$Address.XML</span><% end_if %>
                                <% if $Address2 %><span class="block">$Address2.XML</span><% end_if %>
                                <% if $City || $State || $Zip %><span class="block">$City.XML<% if $City && $State %>, <% end_if %>$State.XML $Zip.XML</span><% end_if %>
                            </address>
                            <% if $MapURL %>
                                <a href="$MapURL.ATT" class="btn btn-outline btn-sm mt-3" target="_blank" rel="noopener noreferrer">
                                    <%t Antlion\\StoreLocation.Directions 'Get directions' %><span class="sr-only"> <%t Antlion\\StoreLocation.NewTab '(opens in a new tab)' %></span>
                                </a>
                            <% end_if %>
                        </div>
                    <% else %>
                        <h2 id="location-visit" class="sr-only"><%t Antlion\\StoreLocation.Details 'Location details' %></h2>
                    <% end_if %>

                    <% if $Phone || $Email %>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
                            <% if $Phone %>
                                <dt class="opacity-70"><%t Antlion\\StoreLocation.Phone 'Phone' %></dt>
                                <dd><a href="$PhoneHref.ATT" class="link link-hover font-medium">$Phone.XML</a></dd>
                            <% end_if %>
                            <% if $Email %>
                                <dt class="opacity-70"><%t Antlion\\StoreLocation.Email 'Email' %></dt>
                                <dd class="min-w-0 break-words"><a href="mailto:$Email.ATT" class="link link-hover font-medium">$Email.XML</a></dd>
                            <% end_if %>
                        </dl>
                    <% end_if %>

                    <% if $Notes %><p class="text-sm opacity-80">$Notes.XML</p><% end_if %>
                </div>
            </section>

            <% if $WeeklyHours %>
                <section class="card border border-base-300 bg-base-100" aria-labelledby="location-hours">
                    <div class="card-body">
                        <h2 id="location-hours" class="text-lg font-semibold"><%t Antlion\\StoreLocation.OpeningHours 'Opening hours' %></h2>
                        <% include Antlion/StoreLocation/Includes/WeeklyHours %>
                    </div>
                </section>
            <% end_if %>
        </div>

        <section class="lg:col-span-7" aria-labelledby="location-contact">
            <div class="card h-full border border-base-300 bg-base-200/40">
                <div class="card-body">
                    <h2 id="location-contact" class="text-2xl font-bold tracking-tight"><%t Antlion\\StoreLocation.SendMessage 'Send us a message' %></h2>
                    <p class="mb-4 opacity-80"><%t Antlion\\StoreLocation.SendMessageIntro 'We reply within one working day.' %></p>
                    $ContactForm
                </div>
            </div>
        </section>
    </div>

    <% if $MapEmbedURL %>
        <div class="mt-8 aspect-[16/7] min-h-72 overflow-hidden rounded-box border border-base-300 bg-base-200">
            <iframe src="$MapEmbedURL.ATT" title="<%t Antlion\\StoreLocation.MapOf 'Map of {name}' name=$DisplayName.ATT %>" class="size-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
    <% end_if %>

    <% if $AllStaffSections %>
        <div class="mt-section space-y-10">
            <% loop $AllStaffSections %>
                <section aria-labelledby="staff-section-$Pos">
                    <h2 id="staff-section-$Pos" class="mb-6 text-2xl font-bold tracking-tight">$Title.XML</h2>
                    <% include Antlion/StoreLocation/Includes/StaffGrid Staff=$Staff, Heading='h3' %>
                </section>
            <% end_loop %>
        </div>
    <% end_if %>
</div>

<script type="application/ld+json">$LocalBusinessSchemaJSON.RAW</script>
