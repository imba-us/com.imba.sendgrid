<?php

require_once 'CRM/Core/Page.php';
require_once 'sendgrid.php';

class CRM_Sendgrid_Page_Doc extends CRM_Core_Page {
	function run() {
		$this->assign('nginx', sendgrid_get_nginx());
	
		parent::run();
	}
}
