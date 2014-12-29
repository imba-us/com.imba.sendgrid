<?php

require_once 'CRM/Core/Form.php';
require_once 'sendgrid.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Sendgrid_Form_SendGrid extends CRM_Core_Form {

	function buildQuickForm() {
		require_once('CRM/Core/Resources.php');

		$settings = sendgrid_get_settings();
	
		$is_writable = is_writable(EXT_DIR);
		$url = CRM_Core_Resources::singleton()->getUrl('com.imba.sendgrid') . 'webhook.php';
		if ($settings['username']) {
			$p = parse_url($url);
			$url = "{$p['scheme']}://{$settings['username']}:{$settings['password']}@{$p['host']}" .
						(!empty($p['port']) ? ":{$p['port']}" : '') . "{$p['path']}";
		}
		
		if (!$is_writable) {
			$settings['username'] = $settings['password'] = 'DISABLED: see warning above';
			$attr = 'disabled="disabled"';
		}
		else $attr = null;

		$el = $this->add('text', 'username', ts('Username'), $attr);
		if (!$is_writable)
			$el->setSize(40);
		$el = $this->add($is_writable ? 'password' : 'text', 'password', ts('Password'), $attr);
		if (!$is_writable)
			$el->setSize(40);
		$el = $this->add('select', 'open_click_processor', ts('Open / Click Processing'));
		$el->loadArray(array('Never' => ts('Do No Track'), 'CiviMail' => ts('CiviMail'), 'SendGrid' => ts('SendGrid')));
		$el = $this->add('checkbox', 'track_optional', ts('Optional'), ts('When tracking, make it optional per mailing.'));
		$el->setChecked((bool)$settings['track_optional']);
		
		$this->addButtons(array(
			array(
				'type' => 'done',
				'name' => 'Save Configuration',
			)
		));
		$this->setDefaults($settings);

		$this->assign('ext_dir', EXT_DIR);
		$this->assign('is_writable', $is_writable);
		$this->assign('url', $url);

		parent::buildQuickForm();
	}
	
	function postProcess() {
		$is_writable = is_writable(EXT_DIR);
		// save settings to database
		$vars = $this->getSubmitValues();

		if (!$is_writable || !$vars['username'] || !$vars['password'])
			$vars['username'] = $vars['password'] = '';
		if (!isset($vars['track_optional']))
			$vars['track_optional'] = '0';
		
		$settings = sendgrid_get_settings();
		foreach($vars as $k => $v) {
			if (array_key_exists($k, $settings))
				$settings[$k] = $v;
		}
	
		sendgrid_save_settings($settings);

		// generate .htaccess and .htpasswd, but only if we have write access
		if ($is_writable) {

			if ($username = $vars['username']) {
				$plainpasswd = $vars['password'];
				// begin code I found on Stack Overflow...
				// generates an apr1/md5 password for use in htpasswd files
				$tmp = '';
				$salt = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
				$len = strlen($plainpasswd);
				$text = $plainpasswd . '$apr1$' . $salt;
				$bin = pack('H32', md5($plainpasswd . $salt . $plainpasswd));
				for($i = $len; $i > 0; $i -= 16) {
					$text .= substr($bin, 0, min(16, $i));
				}
				for($i = $len; $i > 0; $i >>= 1) {
					$text .= ($i & 1) ? chr(0) : $plainpasswd{0};
				}
				$bin = pack('H32', md5($text));
				for($i = 0; $i < 1000; $i++) {
					$new = ($i & 1) ? $plainpasswd : $bin;
					if ($i % 3)
						$new .= $salt;
					if ($i % 7)
						$new .= $plainpasswd;
					$new .= ($i & 1) ? $bin : $plainpasswd;
					$bin = pack('H32', md5($new));
				}
				for ($i = 0; $i < 5; $i++) {
					$k = $i + 6;
					$j = $i + 12;
					if ($j == 16) $j = 5;
						$tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
				}
				$tmp = chr(0) . chr(0) . $bin[11] . $tmp;
				$tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
								'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
								'./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');
				$passwd = '$apr1$' . $salt . '$' . $tmp;
				// ...end code I found on Stack Overflow
	
				// create the .htpasswd file; works with both apache and nginx
				file_put_contents(HTPASSWD, "$username:$passwd\n");
				chmod(HTPASSWD, 0644);
		
				// create the .htaccess for use with apache
				file_put_contents(HTACCESS, "Options -Indexes\n\n" .
													"AuthType Basic\n" .
													"AuthName \"You Shall Not Pass\"\n" .
													"AuthUserFile ". HTPASSWD . "\n" .
													"Require valid-user\n");
				chmod(HTACCESS, 0644);
			}
			else {
				// if there is no username, delete the password file and reduce .htaccess to not produce indices
				if (file_exists(HTPASSWD))
					unlink(HTPASSWD);
				file_put_contents(HTACCESS, "Options -Indexes\n");
				chmod(HTACCESS, 0644);
			}
		}

		parent::postProcess();
		
		CRM_Core_Session::singleton()->pushUserContext('sendgrid');
	}

}
