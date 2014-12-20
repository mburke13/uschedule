<?php
require_once('../lib/functions.php');
session_start();
$client = new Google_Client();
$client->setUseObjects(true);
$cal = new Google_CalendarService($client);
$semester = isset($_SESSION['authorization']['calendar']) && $_SESSION['authorization']['calendar'] != '' ? $_SESSION['authorization']['calendar'] : (array_key_exists('semester', $_POST) ? $_POST['semester'] : null);

if (isset($_SESSION['token'])) {
	$client->setAccessToken($_SESSION['token']);
} else {
	$_SESSION['authorization']['redirect'] = $_SERVER['HTTP_REFERER'];
}

if ($client->getAccessToken() && $semester) {
	$calendar = retrieve_calendar($cal, $semester);
	if ($calendar == null) {
		echo json_encode(array('success' => false));
	} else {
		$sections = array();
		$_SESSION['sections'][$semester] = array();
		foreach($cal->events->listEvents($calendar->getId())->getItems() as $event) {
			$properties = $event->getExtendedProperties()->private;
			if (isset($properties['scal'])) {
				$arr = explode(';', base64_decode($properties['scal']));
				$s = get_section($arr[2], $arr[1], $arr[0]);
				$success = true;
				if (isset($_SESSION['section'][$semester])) {
					foreach($_SESSION['sections'][$semester] as $section2) {
						if (strcmp($section2->id, $s->id) == 0) {
							$success = false;
							break;
						}
					}
				}
				if ($success) {
					array_push($_SESSION['sections'][$semester], $s);
					array_push($sections, $s);
				}
			}
		}
		if (isset($_SESSION['authorization'])) {
			unset($_SESSION['authorization']);
		}
		echo json_encode(array('success' => true, 'sections' => $sections));
	}
	$_SESSION['token'] = $client->getAccessToken();
} else {
	if (isset($_POST['semester'])) {
		$_SESSION['authorization']['calendar'] = $semester;
	}
	$_SESSION['authorization']['service'] = 'Google_CalendarService';
	header('Location: http://' . $_SERVER['HTTP_HOST'] .
		((strcmp($_SERVER['HTTP_HOST'], 'localhost') == 0) ?
		substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], '/', 1)) : '' ). '/auth/google/');
}
?>
