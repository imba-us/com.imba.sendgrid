<?php

$events = json_decode(file_get_contents('php://input'));

if (!$events || !is_array($events)) {
	// SendGrid sends a json encoded array of events
	// if that's not what we get, we're done here
	header("HTTP/1.0 404 Not Found");
	exit();
}

function sendgrid_bounce($job_id, $event_queue_id, $hash, $reason) {
	try {
		civicrm_api3('Mailing', 'event_bounce', array(
			'job_id' => $job_id,
			'event_queue_id' => $event_queue_id,
			'hash' => $hash,
			'body' => $reason
		));
	}
	catch (CiviCRM_API3_Exception $e) {
		CRM_Core_Error::debug_log_message("SendGrid webhook (bounce)\n" . $e->getMessage());
	}
}

function sendgrid_unsubscribe($job_id, $event_queue_id, $hash, $event) {
	try {
		civicrm_api3('MailingGroup', 'event_unsubscribe', array(
			'job_id' => $job_id,
			'event_queue_id' => $event_queue_id,
			'hash' => $hash
		));
	}
	catch (CiviCRM_API3_Exception $e) {
		CRM_Core_Error::debug_log_message("SendGrid webhook ($event)\n" . $e->getMessage());
	}
}

function sendgrid_spamreport($job_id, $event_queue_id, $hash, $event) {
	CRM_Mailing_Event_BAO_SpamReport::report($event_queue_id);
	sendgrid_unsubscribe($job_id, $event_queue_id, $hash, $event);
}

	// if we made it this far then we need to boostrap CiviCRM
	session_start();

	$dir = dirname(shell_exec('find ' . $_SERVER['DOCUMENT_ROOT'] . ' -name "civicrm.config.php"'));

	require_once $dir . '/civicrm.config.php';
	require_once 'CRM/Core/Config.php';
	require_once 'api/api.php';

	$config = CRM_Core_Config::singleton();
	
	// now we can process the events
	
	require_once 'CRM/Core/Error.php';
	require_once 'CRM/Mailing/Event/BAO/SpamReport.php';
	require_once 'CRM/Mailing/Event/BAO/Opened.php';
	require_once 'CRM/Mailing/Event/BAO/TrackableURLOpen.php';
	require_once 'CRM/Mailing/Event/BAO/Delivered.php';

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
				case 'delivered':
					$ts = $event->timestamp;
					if (empty($delivered[$ts]))
						$delivered[$ts] = array();
					$delivered[$ts][] = $event_queue_id;
					break;

				case 'deferred':
					// temp failure, just write it to the log
					CRM_Core_Error::debug_log_message("Sendgrid webhook (deferred)\n" . print_r($event, true));
					break;

				case 'bounce':
					sendgrid_bounce($job_id, $event_queue_id, $hash, $event->reason);
					break;

				case 'spamreport':
					sendgrid_spamreport($job_id, $event_queue_id, $hash, $event->event);
					break;

				case 'unsubscribe':
					sendgrid_unsubscribe($job_id, $event_queue_id, $hash, $event->event);
					break;

				case 'dropped':
					// if dropped because of previous bounce, unsubscribe, or spam report, treat it as such...
					// ...otherwise log it
					if ($event->reason == 'bounced???') {
						sendgrid_bounce($job_id, $event_queue_id, $hash, $event->reason);
					}
					elseif ($event->reason == 'unsubscribed???') {
						sendgrid_unsubscribe($job_id, $event_queue_id, $hash, $event->event);
					}
					elseif ($event->reason == 'spammed???') {
						sendgrid_spamreport($job_id, $event_queue_id, $hash, $event->event);
					}
					else {
						CRM_Core_Error::debug_log_message("Sendgrid webhook (dropped)\n" . print_r($event, true));
					}
					break;

				case 'open':
					CRM_Mailing_Event_BAO_Opened::open($event_queue_id);
					break;

				case 'click':
					try {
						$url = CRM_Core_DAO::escapeString($event->url);
						$mailing_id = CRM_Core_DAO::singleValueQuery("SELECT mailing_id FROM civicrm_mailing_job WHERE id='$job_id'");
						$url_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_mailing_trackable_url WHERE mailing_id='$mailing_id' AND url='$url'");
					
						CRM_Mailing_Event_BAO_TrackableURLOpen::track($event_queue_id, $url_id);
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
		foreach($delivered as $ts => $event_queue_ids) {
			$time = date('YmdHis', $ts);
			CRM_Mailing_Event_BAO_Delivered::bulkCreate($event_queue_ids, $time);
		}
	}
	// that's all she wrote

?>
