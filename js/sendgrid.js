CRM.$(function($) {

	function disable_tracking() {
		var i = $('#tab-tracking').length;

		if (i > 0) {
			$('input[name="url_tracking"]').attr('disabled', true);
			$('input[name="open_tracking"]').attr('disabled', true);
		}
		else setTimeout(disable_tracking, 250);
	}

	if ( (typeof(CRM.crmMailing) == 'object') && (CRM.vars.sendgrid.track_optional == '0') )
		disable_tracking();

})