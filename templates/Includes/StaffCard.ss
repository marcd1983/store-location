<div class="card">
                  <% if $Image %>
                    <picture>
                      <source media="(min-width:1024px)" srcset="$Image.Fill(600,600).URL">
                      <source media="(max-width:1023px)" srcset="$Image.Fill(600,600).URL">
                      <img src="$Image.ScaleMaxWidth(600).URL" alt="$Image.Title.ATT" width="600" height="600" style="width:100%;height:auto;">
                    </picture>
                  <% end_if %>
                  <div class="card-section">
                    <h4 class="card-title">$FirstName.XML $LastName.XML</h4>
                    <% if $Title %><p>$Title.XML</p><% end_if %>
                    <% if $Phone %><p><a href="tel:$Phone.Plain">$Phone.XML</a></p><% end_if %>
                    <% if $Email %><a class="button small hollow" href="mailto:$Email.XML">Email</a><% end_if %>
                  </div>
                </div>