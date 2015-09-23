<?php

require_once 'sendgrid.civix.php';

// misc filename constants
define('HTACCESS', __DIR__ . '/.htaccess');
define('HTPASSWD', __DIR__ . '/.htpasswd');
define('EXT_DIR', __DIR__);

$sendgrid_settings = array();

/*
 * hook_civicrm_alterMailParams
 */
function sendgrid_civicrm_alterMailParams(&$params, $context) {

	static $job_cache = array();
	static $mailing_cache = array();

	if ($context == 'civimail') {
	
		$config = CRM_Core_Config::singleton();
		@list($ignore, $job_id, $event_queue_id, $hash) = explode($config->verpSeparator, substr($params['Return-Path'], 0, strpos($params['Return-Path'], '@')));
		
		if (!$job_id)
			return;
		
		require_once('api/api.php');
		
		try {
			if (empty($job_cache[$job_id]))
				$job_cache[$job_id] = civicrm_api3('MailingJob', 'getsingle', array('id' => $job_id));;
			$job = $job_cache[$job_id];
			
			if (empty($mailing_cache[$job['mailing_id']]))
				$mailing_cache[$job['mailing_id']] = civicrm_api3('Mailing', 'getsingle', array('id' => $job['mailing_id']));
			$mailing = $mailing_cache[$job['mailing_id']];

			$settings = sendgrid_get_settings();
			
			$clicktrack = ($settings['open_click_processor'] == 'SendGrid') && $mailing['url_tracking'] ? '1' : '0';
			$opentrack = ($settings['open_click_processor'] == 'SendGrid') && $mailing['open_tracking'] ? '1' : '0';
			
			// prepare the SendGrid SMTP API header
			$header = array(
				'filters' => array(
					'clicktrack' => array(
						'settings' => array(
							'enable' => $clicktrack
						)
					),
					'opentrack' => array(
						'settings' => array(
							'enable' => $opentrack
						)
					)
				),
				'unique_args' => array(
					'job_id' => $job_id,
					'event_queue_id' => $event_queue_id,
					'hash' => $hash
				)
			);
			$params['X-SMTPAPI'] = trim(substr(preg_replace('/(.{1,70})(,|:|\})/', '$1$2' . "\n", 'X-SMTPAPI: ' . json_encode($header)), 11));
			
			if ($opentrack && !empty($params['html'])) {
				// remove the CiviMail generated open tracking img
				$img = '#<img src="' . $config->userFrameworkResourceURL . "extern/open\.php\?q=$event_queue_id\".*?>#";
				$params['html'] = preg_replace($img, '', $params['html']);
			}
		}
		catch (CiviCRM_API3_Exception $e) {
			require_once('CRM/Core/Error.php');
			CRM_Core_Error::debug_log_message($e->getMessage() . print_r($params, true));
		}
	}
}


/*
 * hook_civicrm_buildForm
 *
 * set tracking options for mailing
 */
function sendgrid_civicrm_buildForm($formName, &$form) {
	if (($formName == 'CRM_Mailing_Form_Settings') && ($form->elementExists('url_tracking'))) {
	
		$settings = sendgrid_get_settings();
		$track = $settings['open_click_processor'] != 'Never';
		$freeze = !$track || !$settings['track_optional'];

		$el = $form->getElement('url_tracking');
		if ($freeze)
			$el->freeze();
		$el = $form->getElement('open_tracking');
		if ($freeze)
			$el->freeze();
		$form->setDefaults(array('url_tracking' => $track, 'open_tracking' => $track));
	}
	elseif ($formName == 'CRM_Report_Form_Mailing_Detail') {
		// this will check Spam Report in the report criteria,
		// but it still doesn't cause that column to be displayed by default.
		// I can't figure out how to make that happen.
		
		$grp = $form->getElement('fields');
		$els = $grp->getElements();
		foreach($els as $el) {
			if ($el->getName() == 'spam_id') {
				$el->setChecked(true);
				break;
			}
		}
	}
}

/*
 * hook_civicrm_install
 */
function sendgrid_civicrm_install() {
	CRM_Core_DAO::executeQuery("CREATE TABLE IF NOT EXISTS `civicrm_mailing_event_spam_report` (
									  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
									  `event_queue_id` int(10) unsigned NOT NULL COMMENT 'FK to EventQueue',
									  `time_stamp` datetime NOT NULL COMMENT 'When this open event occurred.',
									  PRIMARY KEY (`id`),
									  KEY `FK_civicrm_mailing_event_opened_event_queue_id` (`event_queue_id`),
									  CONSTRAINT `FK_civicrm_mailing_event_spam_report_event_queue_id` FOREIGN KEY (`event_queue_id`) REFERENCES `civicrm_mailing_event_queue` (`id`) ON DELETE CASCADE
									) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
  _sendgrid_civix_civicrm_install();
}

/*
 * hook_civicrm_navigationMenu
 *
 * add "SendGrid Configuration" to the Mailings menu
 */
function sendgrid_civicrm_navigationMenu(&$params) {
	require_once('CRM/Core/BAO/Navigation.php');
	// Check that our item doesn't already exist
	$menu_item_search = array('url' => 'civicrm/sendgrid');
	$menu_items = array();
	CRM_Core_BAO_Navigation::retrieve($menu_item_search, $menu_items);
	if (!empty($menu_items))
		return;
		
	$navID = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
	if (is_integer($navID))
		$navID++;

	// Find the CiviMail menu
	$parentID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Mailings', 'id', 'name');
	$params[$parentID]['child'][$navID] = array(
		'attributes' => array(
			'label' => ts('SendGrid Configuration'),
			'name' => 'SendGrid Configuration',
			'url' => 'civicrm/sendgrid',
			'permission' => 'access CiviMail,administer CiviCRM',
			'operator' => 'AND',
			'separator' => 1,
			'parentID' => $parentID,
			'navID' => $navID,
			'active' => 1
		)
	);
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function sendgrid_civicrm_uninstall() {
	CRM_Core_DAO::executeQuery("DROP TABLE `civicrm_mailing_event_spam_report`");
  _sendgrid_civix_civicrm_uninstall();
}

/*
 * sendgrid_get_settings
 *
 * return settings from the database, or empty defaults
 */
function sendgrid_get_settings() {
	global $sendgrid_settings;
	
	if (empty($sendgrid_settings)) {
		require_once 'CRM/Core/BAO/Setting.php';
		$sendgrid_settings = array(
			'username' => CRM_Core_BAO_Setting::getItem('sendgrid', 'username', null, ''),
			'password' => CRM_Core_BAO_Setting::getItem('sendgrid', 'password', null, ''),
			'open_click_processor' => CRM_Core_BAO_Setting::getItem('sendgrid', 'open_click_processor', null, 'CiviMail'),
			'track_optional' => CRM_Core_BAO_Setting::getItem('sendgrid', 'track_optional', null, '1')
		);
	}
	return $sendgrid_settings;
}

/*
 * sendgrid_save_settings
 *
 * save settings to database
 */
function sendgrid_save_settings($settings) {
	global $sendgrid_settings;
	require_once 'CRM/Core/BAO/Setting.php';
	
	foreach($settings as $k => $v) {
		$sendgrid_settings[$k] = $v;
		try {
			CRM_Core_BAO_Setting::setItem($v, 'sendgrid', $k);
		}
		catch (Exception $e) {
			require_once 'CRM/Core/Error.php';
			CRM_Core_Error::debug_log_message($e->getMessage());
		}
	}
}

CRM_Core_Resources::singleton()
	->addScriptFile('com.imba.sendgrid', 'js/sendgrid.js')
	->addVars('sendgrid', sendgrid_get_settings());

// *************************************
// THE REST IS JUST STANDARD BOILERPLATE
// *************************************

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function sendgrid_civicrm_config(&$config) {
  _sendgrid_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function sendgrid_civicrm_xmlMenu(&$files) {
  _sendgrid_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function sendgrid_civicrm_enable() {
  _sendgrid_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function sendgrid_civicrm_disable() {
  _sendgrid_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function sendgrid_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _sendgrid_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function sendgrid_civicrm_managed(&$entities) {
  _sendgrid_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function sendgrid_civicrm_caseTypes(&$caseTypes) {
  _sendgrid_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function sendgrid_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _sendgrid_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
