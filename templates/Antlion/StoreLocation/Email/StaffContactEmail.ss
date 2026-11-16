<%-- Message to one staff member (StaffContactForm). Values are escaped by the template engine --%>
<h1>Website enquiry for $StaffName</h1>
<p><strong>From:</strong> $Name ($Email)</p>
<p><strong>Location:</strong> $LocationTitle</p>
<p><strong>Submitted:</strong> $SubmittedAt</p>
<p><strong>Page:</strong> <a href="$PageUrl.ATT">$PageUrl</a></p>
<hr>
<pre style="white-space:pre-wrap;font-family:inherit">$Message</pre>
