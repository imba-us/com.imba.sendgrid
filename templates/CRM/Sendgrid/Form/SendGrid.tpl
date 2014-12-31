{literal}
<style type="text/css">
	.info, .warn {
		border-radius: 4px;
		color: #3e3e3e;
		font-size: 12px;
		margin: 0 0 8px;
		padding: 4px;
	}
	.info {
		background-color: #F1F8EB;
		border: 1px solid #B0D730;
	}
	.warn {
		background-color: #F8EBEB;
		border: 1px solid red;
	}
	.crm-container fieldset {
		border-top: none;
		padding: 0px;
	}
</style>
{/literal}

<div class="crm-block crm-form-block">

	{if !$is_writable}
		<p class="warn">
			<strong>{ts}WARNING{/ts}:</strong>
			{ts}PHP requires write access to the com.imba.sendgrid extension directory, {$ext_dir}, 
			in order to generate the access and password files to support authenticated notifications.
			If you do not desire authenticated notifications then you can safely ignore this warning.{/ts}
		</p>
	{/if}
	
	<div class="info">
		<h1>{ts}SendGrid Event Notification Processor Configuration{/ts}</h1>
		<fieldset>
			 <table class="form-layout">
				<tr>
					<td class="label">{$form.username.label}</td>
					<td>{$form.username.html}</td>
				</tr>
				<tr>
					<td class="label">{$form.password.label}</td>
					<td>{$form.password.html}<br />
						<span class="description">{ts}If either of the username or password are omitted, then authentication will be disabled.{/ts}</span>
					</td>
				</tr>
				<tr>
					<td class="label">{$form.open_click_processor.label}</td>
					<td>{$form.open_click_processor.html}<br />
						<span class="description">{ts}Select where open and click-throughs should be processed. Either way, the same data is collected, stored, and reported.{/ts}</span>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>{$form.track_optional.html}</td>
				</tr>
				<tr>
					<td></td>
					<td><div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl"}</div></td>
				</tr>
			 </table>
		</fieldset>
	</div>

	<div class="info">
		<h1>{ts}Web Server Configuration{/ts}</h1>
		<p>{ts}No additional configuration is required if your site is served by Apache (httpd).
		The nginx web server requires one of the following location directives be added to the
		configuration file, in the proper virtual server definition for this domain.{/ts}</p>
		{strip}
			<pre>
				<strong>{ts}Authentication Disabled{/ts}</strong><br />
				location {$ext_dir} {ldelim}<br />
				&nbsp;&nbsp;&nbsp;auth_basic off;<br />
				{rdelim}<br />
				<br />
				<strong>{ts}Authentication Enabled{/ts}</strong><br />
				location {$ext_dir} {ldelim}<br />
				&nbsp;&nbsp;&nbsp;auth_basic "You Shall Not Pass";<br />
				&nbsp;&nbsp;&nbsp;auth_basic_user_file {$ext_dir}/.htpasswd;<br />
				{rdelim}<br />
			</pre>
		{/strip}
		<p>{ts}After updating the configuration file, restart nginx or issue the following command to have it load
		the new configuration.{/ts}</p>
		<pre>sudo nginx -s reload</pre>
	</div>
	
	<div class="info">
		<h1>{ts}SendGrid Event Notification Configuration{/ts}</h1>
		<p>{ts}We should probably put a link here to the event notification setup screen on SendGrid.{/ts}</p>
		<p>Based on the username and password provided above, your <em>HTTP Post URL</em> is...</p>
		<pre>{$url}</pre>
		<p>{ts}While it is safe to select all actions to be reported by the SendGrid Event Notification app,
		for better performance <em>Processed</em>, <em>ASM Group Unsubscribe</em>, and <em>ASM Group Resubscribe</em>
		should be deselected. They are essentially meaningless and therefore ignored. <em>Deferred</em> is simply
		a temporary failure that will be reattempted; this extension does nothing more that record it to the main
		CiviCRM log, so you may wish to deselect this action as well.{/ts}</p>
	</div>

</div>