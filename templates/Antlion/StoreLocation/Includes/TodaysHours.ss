<%-- Open-now badge and today's hours. Scope: StoreLocationPage. Not cached: "open now" changes through the day --%>
<% with $TodaysHours %>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <% if $IsOpenNow %>
            <span class="badge badge-success gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span><%t Antlion\\StoreLocation.OpenNow 'Open now' %></span>
        <% else %>
            <span class="badge badge-neutral badge-outline gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span><%t Antlion\\StoreLocation.ClosedNow 'Closed now' %></span>
        <% end_if %>
        <span class="text-sm opacity-80">
            <% if $IsClosed %><%t Antlion\\StoreLocation.ClosedToday 'Closed today' %><% else %><%t Antlion\\StoreLocation.TodayHours 'Today {open}–{close}' open=$OpenTimeNice close=$CloseTimeNice %><% end_if %>
        </span>
    </div>
    <% if $Note || $Source == 'holiday' %>
        <p class="mt-1 text-sm opacity-70"><% if $Note %>$Note.XML · <% end_if %><%t Antlion\\StoreLocation.HolidayHours 'Holiday hours' %></p>
    <% end_if %>
<% end_with %>
