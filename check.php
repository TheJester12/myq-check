<?php

require __DIR__ . '/vendor/autoload.php';

use Twilio\Rest\Client as Twilio;
use GuzzleHttp\Client as Guzzle;

date_default_timezone_set('America/Chicago');

$username = ''; //Typically your email address
$password = ''; //Your password
$deviceid = ; //Which device you are wanting to control, you could use function getDevices() below to find this
$phones = array('+18888888888'); //One or multiple phones that will receive the text
$time = '22:30'; //Time you want to check the garage door and alert you if is open, 24 hour format

$sid = ''; //Twilio ID
$token = ''; //Twilio token
$twilio = new Twilio($sid, $token);

//Mostly static values
$ApplicationId = 'NWknvuBd7LoFHfXmKNMBcgajXtZEgKUh4V7WNzMidrpUUluDpVYVZx+xT4PCM5Kx';
$useragent = 'Chamberlain/3.73';
$brandid = 'BrandId';
$apiversion = '4.1';
$culture = 'en';

$guzzle = new Guzzle();

$securitytoken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	
	$number = $_POST['From'];
	$body = $_POST['Body'];
	
	if (in_array($number, $phones)) {
		
		if (strtolower(trim($body)) == 'close') {
			
			changeDoorState('close');
			
			header('Content-Type: text/xml');
			echo '<Response>';
			echo '    <Message>';
			echo '        Ok, trying to close the door for you.';
			echo '    </Message>';
			echo '</Response>';
		}
		
		if (strtolower(trim($body)) == 'status') {
			
			$devices = getDevices();
			
			$mygaragedoor = null;
			$mygaragedoorstatus = null;
			
			foreach($devices->Devices as $device) {
				if ($device->MyQDeviceId == $deviceid) {
					$mygaragedoor = $device;
					foreach($device->Attributes as $attribute) {
						if ($attribute->AttributeDisplayName == 'doorstate') {
							$mygaragedoorstatus = $attribute->Value;
						}
					}
				}
			}
			
			//print $mygaragedoorstatus;
			//blahhM&ML?O31h&aK
			
			if ($mygaragedoorstatus !== null) {
				if ($mygaragedoorstatus == 1) {
					header('Content-Type: text/xml');
					echo '<Response>';
					echo '    <Message>';
					echo '        The garage door is open';
					echo '    </Message>';
					echo '</Response>';
				} elseif ($mygaragedoorstatus == 2) {
					header('Content-Type: text/xml');
					echo '<Response>';
					echo '    <Message>';
					echo '        The garage door is closed';
					echo '    </Message>';
					echo '</Response>';
				}
			}
			
		}
		
	}
	
} else {
	
	//Check the time
	$now = new DateTime();
	
	echo $now->format('H:i');
	
	if ($now->format('H:i') == $time) {
			
		$devices = getDevices();
		
		$mygaragedoor = null;
		$mygaragedoorstatus = null;
		
		
		foreach($devices->Devices as $device) {
			if ($device->MyQDeviceId == $deviceid) {
				$mygaragedoor = $device;
				foreach($device->Attributes as $attribute) {
					if ($attribute->AttributeDisplayName == 'doorstate') {
						$mygaragedoorstatus = $attribute->Value;
					}
				}
			}
		}
				
		if ($mygaragedoorstatus == 1) {
			
			foreach ($phones as $phone) {
				$twilio->messages->create(
			    // the number you'd like to send the message to
				    $phone,
				    array(
				        'from' => '+16513763832',
				        'body' => 'Your garage door is still open! Reply "Close" to close it now'
				    )
				);
			}
			
			print "Door still open! Message sent!";
			
		} else {
			print "All is well";
		}
		
	} else {
		print "It is not the appointed time";
		die;
	}
}


function logIn() {
	
	global $guzzle, $useragent, $brandid, $apiversion, $culture, $ApplicationId, $securitytoken, $deviceid, $username, $password;
	
	$response = $guzzle->request('POST', 'https://myqexternal.myqdevice.com/api/v4/User/Validate', [
	    'headers' => [
		    'User-Agent' => $useragent,
		    'BrandId' => $brandid,
		    'ApiVersion' => $apiversion,
		    'Culture' => $culture,
	        'MyQApplicationId' => $ApplicationId,
	    ],
	    'form_params' => [
	        'username' => $username,
	        'password' => $password
	    ]
	]);
	
	if ($response->getStatusCode() == 200) {
	    $response_json = json_decode($response->getBody()->getContents());
	    $securitytoken = $response_json->SecurityToken;
	    return $response_json;
	} else {
		print "Error logging in";
		die;
	}
}

function getDevices() {
	
	global $guzzle, $useragent, $brandid, $apiversion, $culture, $ApplicationId, $securitytoken, $deviceid;
	
	//Get a security token by logging in
	$login = logIn();
		
	$response = $guzzle->request('GET', 'https://myqexternal.myqdevice.com/api/v4/userdevicedetails/get', [
	    'headers' => [
		    'User-Agent' => $useragent,
		    'BrandId' => $brandid,
		    'ApiVersion' => $apiversion,
		    'Culture' => $culture,
	        'MyQApplicationId' => $ApplicationId,
	        'SecurityToken' => $securitytoken,
	    ],
		'query' => [
			'appId' => $ApplicationId,
			'SecurityToken' => $securitytoken,
			'format' => 'json',
			'nojsoncallback' => 1
		]
	]);
	
	if ($response->getStatusCode() == 200) {    
	    $response_json = json_decode($response->getBody()->getContents());
	    return $response_json;
	} else {
		print "Error getting devices";
		die;
	}
}

function changeDoorState($state) {
	
	global $guzzle, $useragent, $brandid, $apiversion, $culture, $ApplicationId, $securitytoken, $deviceid;
	
	//Get a security token by logging in
	$login = logIn();
	
	$newstate = null;
	if ($state == 'open') {
		$newstate = 1;
	} elseif ($state == 'close') {
		$newstate = 0;
	}
	
	$response = $guzzle->request('PUT', 'https://myqexternal.myqdevice.com/api/v4/DeviceAttribute/PutDeviceAttribute', [
	    'headers' => [
		    'User-Agent' => $useragent,
		    'BrandId' => $brandid,
		    'ApiVersion' => $apiversion,
		    'Culture' => $culture,
	        'MyQApplicationId' => $ApplicationId,
	        'SecurityToken' => $securitytoken,
	    ],
		'form_params' => [
			'AttributeName' => 'desireddoorstate',
			'MyQDeviceId' => $deviceid,
			'ApplicationId' => $ApplicationId,
			'AttributeValue' => $newstate,
			'SecurityToken' => $securitytoken,
			'format' => 'json',
			'nojsoncallback' => 1
		]
	]);
	
	if ($response->getStatusCode() == 200) {    
	    $response_json = json_decode($response->getBody()->getContents());
	    return $response_json;
	} else {
		print "Error changing door state";
		die;
	}
}