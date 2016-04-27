<?php

return array(
  'sendgrid_secretcode' => array(
    'group_name' => 'SendGrid Preferences',
    'group' => 'sendgrid',
    'name' => 'sendgrid_secretcode',
    'type' => 'String',
    'default' => NULL,
  ),
  'sendgrid_open_click_processor' => array(
    'group_name' => 'SendGrid Preferences',
    'group' => 'sendgrid',
    'name' => 'sendgrid_open_click_processor',
    'type' => 'String',
    'default' => 'CiviMail',
  ),
  'sendgrid_track_optional' => array(
    'group_name' => 'SendGrid Preferences',
    'group' => 'sendgrid',
    'name' => 'sendgrid_track_optional',
    'type' => 'Integer',
    'default' => 1,
  ),
);
