<?php

require_once 'CRM/Core/Form.php';
require_once 'sendgrid.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Sendgrid_Form_SendGrid extends CRM_Core_Form {

  public function buildQuickForm() {
    $settings = sendgrid_get_settings();

    $q = empty($settings['secretcode']) ? 'reset=1' : "reset=1&secretcode={$settings['secretcode']}";
    $url = CRM_Utils_System::url('civicrm/sendgrid/webhook', $q, TRUE, NULL, FALSE, TRUE);

    $attr = NULL;

    $el = $this->add('text', 'secretcode', ts('Secret Code'), $attr);
    $el->setSize(40);
    $el = $this->add('select', 'open_click_processor', ts('Open / Click Processing'));
    $el->loadArray(array('Never' => ts('Do No Track'), 'CiviMail' => ts('CiviMail'), 'SendGrid' => ts('SendGrid')));
    $el = $this->add('checkbox', 'track_optional', ts('Optional'), ts('When tracking, make it optional per mailing.'));
    $el->setChecked((bool) $settings['track_optional']);

    $this->addButtons(array(
      array(
        'type' => 'done',
        'name' => 'Save Configuration',
      ),
    ));
    $this->setDefaults($settings);

    $this->assign('url', $url);

    parent::buildQuickForm();
  }

  public function postProcess() {
    // save settings to database
    $vars = $this->getSubmitValues();

    if (!isset($vars['track_optional'])) {
      $vars['track_optional'] = '0';
    }

    $settings = sendgrid_get_settings();
    foreach ($vars as $k => $v) {
      if (array_key_exists($k, $settings)) {
        $settings[$k] = $v;
      }
    }

    sendgrid_save_settings($settings);

    parent::postProcess();

    CRM_Core_Session::singleton()->pushUserContext('sendgrid');
  }

}
