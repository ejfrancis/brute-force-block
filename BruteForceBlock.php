<?php
namespace ejfrancis;
/**
 * 				Brute Force Block class
 *
 * 	Implementation by Evan Francis for use in AlpineAuth library, 2014. 
 *  Inspired by work of Corey Ballou, http://stackoverflow.com/questions/2090910/how-can-i-throttle-user-login-attempts-in-php.
 * 	MIT License http://opensource.org/licenses/MIT
 *
    ======================== 	Setup 	  ===============================
	1) setup database connection in $_db array.
		1a. The 'auto_clear' option determines whether or not older database entries are cleared automatically
	2) (optional) set default throttle settings in $default_throttle_settings_array
	
	==================== To Create MySQL Database ====================
	CREATE TABLE IF NOT EXISTS `user_failed_logins` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `user_id` bigint(20) NOT NULL,
	  `ip_address` int(11) unsigned DEFAULT NULL,
	  `attempted_at` datetime NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;

	==================== 	Usage	 ====================
    === get login status. use this when building your login form ==
 	$BFBresponse = BruteForceBlock::getLoginStatus();
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
	
	== clear the database ==
	$BFBresponse = BruteForceBlock::clearDatabase();
	if($BFBresponse !== true){
		$error_message = $BFBresponse;
	}
 */
//brute force block
class BruteForceBlock {
	// array of throttle settings. # failed_attempts => response
	private static $default_throttle_settings = [
			50 => 2, 			//delay in seconds
			150 => 4, 			//delay in seconds
			300 => 'captcha'	//captcha
	];
	
	//database config
	private static $_db = [
		'driver' => DB_DRIVER,
		'host'	=> DB_HOST,
		'database'	=> DB_DATABASE,
		'charset' => DB_CHARSET,
		'username' => DB_USERNAME,
		'password' => DB_PASSWORD,
		'auto_clear' => true
	];
	
	//time frame to use when retrieving the number of recent failed logins from database
	private static $time_frame_minutes = 10;
	
	//setup and return database connection
	private static function _databaseConnect(){
		//connect to database
		$db = new PDO(self::$_db['driver'].
			':host='.self::$_db['host'].
			';dbname='.self::$_db['database'].
			';charset='.self::$_db['charset'], 
			DB_USERNAME, DB_PASSWORD);
		
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		
		//return the db connection object
		return $db;
	}
	//add a failed login attempt to database. returns true, or error 
	public static function addFailedLoginAttempt($user_id, $ip_address){
		//get db connection
		$db = BruteForceBlock::_databaseConnect();
		
		//get current timestamp
		$timestamp = date('Y-m-d H:i:s');
		
		//attempt to insert failed login attempt
		try{
			$stmt = $db->query('INSERT INTO user_failed_logins SET user_id = '.$user_id.', ip_address = INET_ATON("'.$ip_address.'"), attempted_at = NOW()');
			//$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			return true;
		} catch(PDOException $ex){
			//return errors
			return $ex;
		}
	}
	//get the current login status. either safe, delay, catpcha, or error
	public static function getLoginStatus($options = null){
		//get db connection
		$db = BruteForceBlock::_databaseConnect();
		
		//setup response array
		$response_array = array(
			'status' => 'safe',
			'message' => null
		);
		
		//attempt to retrieve latest failed login attempts
		$stmt = null;
		$latest_failed_logins = null;
		$row = null;
		$latest_failed_attempt_datetime = null;
		try{
			$stmt = $db->query('SELECT MAX(attempted_at) AS attempted_at FROM user_failed_logins');
			$latest_failed_logins = $stmt->rowCount();
			$row = $stmt-> fetch();
			//get latest attempt's timestamp
			$latest_failed_attempt_datetime = (int) date('U', strtotime($row['attempted_at']));
		} catch(PDOException $ex){
			//return error
			$response_array['status'] = 'error';
			$response_array['message'] = $ex;
		}
		
		//get local var of throttle settings. check if options parameter set
		if($options == null){
			$throttle_settings = self::$default_throttle_settings;
		}else{
			//use options passed in
			$throttle_settings = $options;
		}
		//grab first throttle limit from key
		reset($throttle_settings);
		$first_throttle_limit = key($throttle_settings);

		//attempt to retrieve latest failed login attempts
		try{
			//get all failed attempst within time frame
			$get_number = $db->query('SELECT * FROM user_failed_logins WHERE attempted_at > DATE_SUB(NOW(), INTERVAL '.self::$time_frame_minutes.' MINUTE)');
			$number_recent_failed = $get_number->rowCount();
			//reverse order of settings, for iteration
			krsort($throttle_settings);
			
			//if number of failed attempts is >= the minimum threshold in throttle_settings, react
			if($number_recent_failed >= $first_throttle_limit ){				
				//it's been decided the # of failed logins is troublesome. time to react accordingly, by checking throttle_settings
				foreach ($throttle_settings as $attempts => $delay) {
					if ($number_recent_failed > $attempts) {
						// we need to throttle based on delay
						if (is_numeric($delay)) {
							//find the time of the next allowed login
							$next_login_minimum_time = $latest_failed_attempt_datetime + $delay;
							
							//if the next allowed login time is in the future, calculate the remaining delay
							if(time() < $next_login_minimum_time){
								$remaining_delay = $next_login_minimum_time - time();
								// add status to response array
								$response_array['status'] = 'delay';
								$response_array['message'] = $remaining_delay;
							}else{
								// delay has been passed, safe to login
								$response_array['status'] = 'safe';
							}
							//$remaining_delay = $delay - (time() - $latest_failed_attempt_datetime); //correct
							//echo 'You must wait ' . $remaining_delay . ' seconds before your next login attempt';

							
						} else {
							// add status to response array
							$response_array['status'] = 'captcha';
						}
						break;
					}
				}  
				
			}
			//clear database if config set
			if(self::$_db['auto_clear'] == true){
				//attempt to delete all records that are no longer recent/relevant
				try{
					//get current timestamp
					$now = date('Y-m-d H:i:s');
					$stmt = $db->query('DELETE from user_failed_logins WHERE attempted_at < DATE_SUB(NOW(), INTERVAL '.(self::$time_frame_minutes * 2).' MINUTE)');
					$stmt->execute();
					
				} catch(PDOException $ex){
					$response_array['status'] = 'error';
					$response_array['message'] = $ex;
				}
			}
			
		} catch(PDOException $ex){
			//return error
			$response_array['status'] = 'error';
			$response_array['message'] = $ex;
		}
		
		//return the response array containing status and message 
		return $response_array;
	}
	
	//clear the database
	public static function clearDatabase(){
		//get db connection
		$db = BruteForceBlock::_databaseConnect();
		
		//attempt to delete all records
		try{
			$stmt = $db->query('DELETE from user_failed_logins');
			return true;
		} catch(PDOException $ex){
			//return errors
			return $ex;
		}
	}
}