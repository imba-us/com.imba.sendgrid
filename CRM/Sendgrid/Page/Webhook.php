<?php

require_once 'CRM/Core/Page.php';

class CRM_Sendgrid_Page_Webhook extends CRM_Core_Page {
  public function run() {
    $events = json_decode(file_get_contents('php://input'));

    if (!$events || !is_array($events)) {
      // SendGrid sends a json encoded array of events
      // if that's not what we get, we're done here
      header("HTTP/1.0 404 Not Found");
      CRM_Utils_System::civiExit();
    }

    $config = CRM_Core_Config::singleton();
    $delivered = array();

    foreach ($events as $event) {

      if (!empty($event->job_id)) {
        /************
         * CiviMail *
         ************/
        $job_id = $event->job_id;
        $event_queue_id = $event->event_queue_id;
        $hash = $event->hash;

        switch ($event->event) {
          case 'delivered':
            /*
            $ts = $event->timestamp;
            if (empty($delivered[$ts]))
              $delivered[$ts] = array();
            $delivered[$ts][] = $event_queue_id;
            */
            break;

          case 'deferred':
            // temp failure, just write it to the log
            CRM_Core_Error::debug_log_message("Sendgrid webhook (deferred)\n" . print_r($event, TRUE));
            break;

          case 'bounce':
            self::bounce($job_id, $event_queue_id, $hash, $event->reason);
            break;

          case 'spamreport':
            self::spamreport($job_id, $event_queue_id, $hash, $event->event);
            break;

          case 'unsubscribe':
            self::unsubscribe($job_id, $event_queue_id, $hash, $event->event);
            break;

          case 'dropped':
            // if dropped because of previous bounce, unsubscribe, or spam report, treat it as such...
            // ...otherwise log it
            if ($event->reason == 'Bounced Address') {
              self::bounce($job_id, $event_queue_id, $hash, $event->reason);
            }
            elseif ($event->reason == 'Unsubscribed Address') {
              self::unsubscribe($job_id, $event_queue_id, $hash, $event->event);
            }
            elseif ($event->reason == 'Spam Reporting Address') {
              self::spamreport($job_id, $event_queue_id, $hash, $event->event);
            }
            else {
              CRM_Core_Error::debug_log_message("Sendgrid webhook (dropped)\n" . print_r($event, TRUE));
            }
            break;

          case 'open':
            CRM_Mailing_Event_BAO_Opened::open($event_queue_id);
            break;

          case 'click':
            // first off, strip off any utm_??? query parameters for google analytics
            $info = parse_url($event->url);
            if (!empty($info['query'])) {
              $qs = array();
              $pairs = explode('&', $info['query']);
              foreach ($pairs as $pair) {
                if (strpos($pair, 'utm_') !== 0) {
                  $qs[] = $pair;
                }
              }
              $info['query'] = implode('&', $qs);

              $event->url = $info['scheme'] . '://';
              if (!empty($info['user']) && !empty($info['pass'])) {
                $event->url .= $info['user'] . ':' . $info['pass'] . '@';
              }
              $event->url .= $info['host'];
              $event->url .= CRM_Utils_Array::value('path', $info, '');
              $event->url .= empty($info['query']) ? '' : '?' . $info['query'];
              $event->url .= empty($info['fragment']) ? '' : '#' . $info['fragment'];
            }
            try {
              $url = CRM_Core_DAO::escapeString($event->url);
              $mailing_id = CRM_Core_DAO::singleValueQuery("SELECT mailing_id FROM civicrm_mailing_job WHERE id='$job_id'");
              if ($url_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_mailing_trackable_url WHERE mailing_id='$mailing_id' AND url='$url'")) {
                CRM_Mailing_Event_BAO_TrackableURLOpen::track($event_queue_id, $url_id);
              }
            }
            catch (Exception $e) {
              CRM_Core_Error::debug_log_message("SendGrid webhook (click)\n" . $e->getMessage());
            }
            break;
        }
      }
    }

    parent::run();
  }

  public static function bounce($job_id, $event_queue_id, $hash, $reason) {
    try {
      civicrm_api3('Mailing', 'event_bounce', array(
        'job_id' => $job_id,
        'event_queue_id' => $event_queue_id,
        'hash' => $hash,
        'body' => $reason,
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      CRM_Core_Error::debug_log_message("SendGrid webhook (bounce)\n" . $e->getMessage());
    }
  }

  public static function unsubscribe($job_id, $event_queue_id, $hash, $event) {
    try {
      civicrm_api3('MailingGroup', 'event_unsubscribe', array(
        'job_id' => $job_id,
        'event_queue_id' => $event_queue_id,
        'hash' => $hash,
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      CRM_Core_Error::debug_log_message("SendGrid webhook ($event)\n" . $e->getMessage());
    }
  }

  public static function spamreport($job_id, $event_queue_id, $hash, $event) {
    CRM_Mailing_Event_BAO_SpamReport::report($event_queue_id);
    self::unsubscribe($job_id, $event_queue_id, $hash, $event);
  }

}
