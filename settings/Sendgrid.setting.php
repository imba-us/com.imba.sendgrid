<?php

return array(
  'sendgrid_username' => array(
    'group_name' => 'SendGrid Preferences',
    'group' => 'sendgrid',
    'name' => 'sendgrid_username',
    'type' => 'String',
    'default' => NULL,
  ),
  'sendgrid_password' => array(
    'group_name' => 'SendGrid Preferences',
    'group' => 'sendgrid',
    'name' => 'sendgrid_password',
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