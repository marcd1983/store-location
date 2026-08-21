<% if $MapEmbedURL %>
<% cached $ID, $LastEdited %>
<div class="grid-x grid-padding-x grid-padding-y">
<div class="cell">
  <div class="responsive-embed panormama">
    <iframe
      src="$MapEmbedURL.ATT"
      width="600" height="450" style="border:0;" allowfullscreen=""
      loading="lazy" referrerpolicy="no-referrer-when-downgrade">
      </iframe>
  </div>
</div>
</div>
<% end_cached %>
<% end_if %>