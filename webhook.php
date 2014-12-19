<?php

$events = json_decode(file_get_contents('php://input'));

if (!$events || !is_array($events)) {
	// SendGrid sends a json encoded array of events
	// if that's not what we get, we're done here
	header("HTTP/1.0 404 Not Found");
}
else {
	// if we made it this far then we need to boostrap CiviCRM
	session_start();

	require_once 'sendgrid.php';
	$settings = sendgrid_get_settings();
	$dir = $settings['base_civi_dir'];
	
	require_once $dir . '/civicrm.config.php';
	require_once 'CRM/Core/Config.php';
	require_once 'api/api.php';

	$config = CRM_Core_Config::singleton();
	
	// now we can process the events
	
	require_once 'CRM/Core/Error.php';
	$delivered = array();
	
	foreach($events as $event) {
		
		if (!empty($event->job_id)) {
			/************
			 * CiviMail *
			 ************/
			$job_id = $event->job_id;
			$event_queue_id = $event->event_queue_id;
			$hash = $event->hash;

			switch ($event->event) {
				case 'dropped':
					CRM_Core_Error::debug_log_message("Sendgrid webhook (dropped)\n" . print_r($event, true));
					break;

				case 'delivered':
					$ts = $event->timestamp;
					if (empty($delivered[$ts]))
						$delivered[$ts] = array();
					$delivered[$ts][] = $event_queue_id;
					break;

				case 'deferred':
					CRM_Core_Error::debug_log_message("Sendgrid webhook (deferred)\n" . print_r($event, true));
					break;

				case 'bounce':
					try {
						civicrm_api3('Mailing', 'event_bounce', array(
							'job_id' => $job_id,
							'event_queue_id' => $event_queue_id,
							'hash' => $hash,
							'body' => $event->reason
						));
					}
					catch (CiviCRM_API3_Exception $e) {
						CRM_Core_Error::debug_log_message("SendGrid webhook (bounce)\n" . $e->getMessage());
					}
					break;

				case 'spamreport':
					require_once 'CRM/Mailing/Event/BAO/SpamReport.php';
					CRM_Mailing_Event_BAO_SpamReport::report($event_queue_id);
					// fall through to unsbuscribe....
				case 'unsubscribe':
					try {
						civicrm_api3('MailingGroup', 'event_unsubscribe', array(
							'job_id' => $job_id,
							'event_queue_id' => $event_queue_id,
							'hash' => $hash
						));
					}
					catch (CiviCRM_API3_Exception $e) {
						CRM_Core_Error::debug_log_message("SendGrid webhook ({$event->event})\n" . $e->getMessage());
					}
					break;
				case 'open':
					require_once 'CRM/Mailing/Event/BAO/Opened.php';
					CRM_Mailing_Event_BAO_Opened::open($event_queue_id);
					break;
				case 'click':
					try {
						$url = CRM_Core_DAO::escapeString($event->url);
						$mailing_id = CRM_Core_DAO::singleValueQuery("SELECT mailing_id FROM civicrm_mailing_job WHERE id='$job_id'");
						$url_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_mailing_trackable_url WHERE mailing_id='$mailing_id' AND url='$url'");
					
						require_once 'CRM/Mailing/Event/BAO/TrackableURLOpen.php';
						$url = CRM_Mailing_Event_BAO_TrackableURLOpen::track($event_queue_id, $url_id);
					}
					catch (Exception $e) {
						CRM_Core_Error::debug_log_message("SendGrid webhook (click)\n" . $e->getMessage());
					}
					break;
			}
		}
	}
	// bulk add the deliveries to the database
	if (!empty($delivered)) {
		require_once 'CRM/Mailing/Event/BAO/Delivered.php';
	
		foreach($delivered as $ts => $event_queue_ids) {
			$time = date('YmdHis', $ts);
			CRM_Mailing_Event_BAO_Delivered::bulkCreate($event_queue_ids, $time);
		}
	}
	// that's all she wrote
}

?>
