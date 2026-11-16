<%-- Location contact enquiry. Values are escaped by the template engine --%>
<h1>Website enquiry: $LocationTitle</h1>
<p><strong>From:</strong> $Name ($Email)</p>
<% if $Phone %><p><strong>Phone:</strong> $Phone</p><% end_if %>
<% if $DepartmentTitle %><p><strong>Department:</strong> $DepartmentTitle</p><% end_if %>
<p><strong>Submitted:</strong> $SubmittedAt</p>
<p><strong>Page:</strong> <a href="$PageUrl.ATT">$PageUrl</a></p>
<hr>
<pre style="white-space:pre-wrap;font-family:inherit">$Message</pre>
