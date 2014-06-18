BruteForceBlock
=================

Automatic brute force attack prevention class with PHP. Stores all failed login attempts site-wide in a database and compares the
number of recent failed attempts against a set threshold. Responds with time delay between login requests and/or captcha requirement.

Implementation by Evan Francis for use in [AlpineAuth](https://github.com/ejfrancis/AlpineAuth) library, 2014. 
Inspired by work of Corey Ballou, http://stackoverflow.com/questions/2090910/how-can-i-throttle-user-login-attempts-in-php.
MIT License http://opensource.org/licenses/MIT

    ======================== 	Setup 	  ===============================
	1) setup database connection in $_db array.
		1a. The 'auto_clear' option determines whether or not older database entries are cleared automatically
	2) (optional) set default throttle settings in $default_throttle_settings_array
	
	NOTE: The throttle settings should be determined by the size and activity of your user base. The default settings should not be relied on.
	
	==================== To Create MySQL Database ====================
	Use the included 'user_failed_logins.sql' file or the following statement:
	
	CREATE TABLE IF NOT EXISTS `user_failed_logins` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `user_id` bigint(20) NOT NULL,
	  `ip_address` int(11) unsigned DEFAULT NULL,
	  `attempted_at` datetime NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

	==================== 	Usage	 ====================
    === get login status. use this when building your login form ==
	//build throttle settings array. (# recent failed logins => response). if this 
	$throttle_settings = [
			50 => 2, 			//delay in seconds
			150 => 4, 			//delay in seconds
			300 => 'captcha'	//captcha
	];
	
 	$BFBresponse = BruteForceBlock::getLoginStatus($throttle_settings);	
	//$throttle_settings is an optional parameter. if it's not included, the default settings array in BruteForceBlock.php will be used
	switch ($BFBresponse['status']){
		case 'safe':
			//safe to login
			break;
		case 'error':
			//error occured. get message
			$error_message = $BFBresponse['message'];
			break;
		case 'delay':
			//time delay required before next login
			$remaining_delay_in_seconds = $BFBresponse['message'];
			break;
		case 'captcha':
			//captcha required
			break;
		
	}
	
	
	== add a failed login attempt ==
	$BFBresponse = BruteForceBlock::addFailedLoginAttempt($user_id, $ip_address);
	if($BFBresponse !== true){
		//get error
		$error_message = $BFBresponse;
	}
	
	== clear the database ==
	$BFBresponse = BruteForceBlock::clearDatabase();
	if($BFBresponse !== true){
		//get error
		$error_message = $BFBresponse;
	}
 
