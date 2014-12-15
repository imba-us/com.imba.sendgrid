<h3>SendGrid Event Notification Processor</h3>

<p>Documentation goes here.</p>

{ts}<p>
	No additional configuration is required if your site is served by Apache (httpd).
	The nginx web server requires one of the following location directives be added to the
	configuration file, in the proper virtual server definition for this domain.
	{strip}<pre>
		<strong>Authentication Disabled</strong>
		{$nginx.no}
		<strong>Authentication Enabled</strong>
		{$nginx.yes}
	</pre>{/strip}</p>
	<p>After updating the configuration file, restart nginx or issue the following command to have it load
	the new configuration.<pre>sudo nginx -s reload</pre></p>
</p>{/ts}
                	