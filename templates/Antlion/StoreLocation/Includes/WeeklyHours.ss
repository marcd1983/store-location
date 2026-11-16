<%-- Mon–Sun hours with today highlighted, then upcoming holiday hours. Scope: StoreLocationPage --%>
<% if $WeeklyHours %>
    <table class="table table-sm">
        <caption class="sr-only"><%t Antlion\\StoreLocation.OpeningHours 'Opening hours' %></caption>
        <tbody>
            <% loop $WeeklyHours %>
                <tr class="<% if $IsToday %>bg-base-200 font-semibold<% end_if %>">
                    <th scope="row" class="font-medium"<% if $IsToday %> aria-current="date"<% end_if %>>$Day.XML</th>
                    <td class="text-right tabular-nums"><% if $IsClosed %><span class="opacity-60"><%t Antlion\\StoreLocation.Closed 'Closed' %></span><% else %>$Opens.XML – $Closes.XML<% end_if %></td>
                </tr>
            <% end_loop %>
        </tbody>
    </table>
<% end_if %>
<% if $UpcomingHolidays %>
    <details class="group mt-4 rounded-box border border-base-300">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-medium">
            <%t Antlion\\StoreLocation.UpcomingHolidays 'Holiday and special hours' %>
            <svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
        </summary>
        <ul class="divide-y divide-base-300 border-t border-base-300 text-sm">
            <% loop $UpcomingHolidays %>
                <li class="flex flex-wrap justify-between gap-x-4 px-4 py-2.5">
                    <span><time datetime="$Date.Format('y-MM-dd')" class="font-medium">$Date.Format('EEE d MMM')</time><% if $Name %> · $Name.XML<% end_if %></span>
                    <span class="tabular-nums opacity-80"><% if $IsClosed %><%t Antlion\\StoreLocation.Closed 'Closed' %><% else %>$Opens.XML – $Closes.XML<% end_if %></span>
                </li>
            <% end_loop %>
        </ul>
    </details>
<% end_if %>
