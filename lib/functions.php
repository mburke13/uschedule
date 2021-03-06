<?php
require(__DIR__ . '/../vendor/autoload.php');
require_once('course.php');
require_once('department.php');

define('API_ROOT', 'http://web-app.usc.edu/web/soc/api/');
define('API_COURSES', API_ROOT . 'classes/%s/%s/');
define('API_DEPARTMENTS', API_ROOT . 'depts/%s/');
define('API_TERMS', API_ROOT . 'terms/');
define('API_SESSIONS', API_ROOT . 'session/%s/%s/');

function do_google_authorization() {
  $_SESSION['authorization']['service'] = 'Calendar';
	header('Location: http://' . $_SERVER['HTTP_HOST'] .
		((strcmp($_SERVER['HTTP_HOST'], 'localhost') == 0) ?
		substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], '/', 1)) : '' ). '/auth/google/');
}

function get_all_semesters() {
	$json_object = get_json(API_TERMS);
	return json_decode($json_object, true)['term'];
}

function get_all_courses($dept, $semester) {
	$json_object = get_json(sprintf(API_COURSES, $dept, $semester));
	$courses = array();
	$json_object = json_decode($json_object, true);
	if(array_key_exists('course', $json_object['OfferedCourses'])) {
		$course = $json_object['OfferedCourses']['course'];
		if (array_key_exists('ScheduledCourseID', $course)) {
			array_push($courses, new Course($course, $semester));
		} else {
			foreach ($course as $course2) {
				array_push($courses, new Course($course2, $semester));
			}
		}
	}
	return $courses;
}

function get_all_departments($semester) {
	$json_object = get_json(sprintf(API_DEPARTMENTS, $semester));
	$departments = array();
	$json_object = json_decode($json_object, true);
	foreach($json_object['department'] as $department) {
		if (array_key_exists('department', $department)) {
			$department2 = $department['department'];
			if (array_key_exists('code', $department2)) {
				$d = new Department($department2);
				if (!isDuplicate($d, $departments)) {
					array_push($departments, $d);
				}
			} else {
				foreach ($department2 as $department3) {
					$d = new Department($department3);
					if (!isDuplicate($d, $departments)) {
						array_push($departments, $d);
					}
				}
			}
		} else {
			$d = new Department($department);
			if (!isDuplicate($d, $departments)) {
				array_push($departments, $d);
			}
		}

	}
	sort($departments);
	return $departments;
}

function isDuplicate($department, $departments) {
	foreach ($departments as $d) {
		if (strcmp($department->getCode(), $d->getCode()) == 0) {
			return true;
		}
	}
	return false;
}

function get_all_sections($course, $semester) {
	foreach (get_all_courses(substr($course, 0, strpos($course, '-')), $semester) as $c) {
		if (strcmp($c->getId(), $course) == 0) {
			return $c->getSections();
		}
	}
	return null;
}

function get_section($section, $course, $semester) {
	foreach(get_all_sections($course, $semester) as $s) {
		if (strcmp($section, $s->getId()) == 0) {
			return $s;
		}
	}
	return null;
}

function get_session($id, $semester) {
	$json_object = get_json(sprintf(API_SESSIONS, $id, $semester));
	return json_decode($json_object, true);
}

function get_all_terms() {
	$json_object = get_json(API_TERMS);
	return json_decode($json_object, true)['terms'];
}

function retrieve_calendar($cal, $semester) {
  $calendarList = $cal->calendarList->listCalendarList();
  foreach($calendarList->getItems() as $calendarEntry) {
    if (strcmp($semester, base64_decode($calendarEntry->getDescription())) == 0) {
      return $calendarEntry;
    }
  }
	return null;
}

function get_or_create_calendar($cal, $semester) {
	$calendar = retrieve_calendar($cal, $semester);
	if ($calendar == null) {
		$calendar = new Google_Service_Calendar_Calendar();
		$calendar->setSummary('USC Classes - ' . semester_to_string($semester));
		$calendar->setTimeZone(DEFAULT_TZ);
		$calendar->setDescription(base64_encode($semester));
		$calendar = $cal->calendars->insert($calendar);
	}
	return $calendar;
}

function add_section_to_calendar($cal, $calendar, Section $section) {
	return $cal->events->insert($calendar->getId(), $section->toCalendarEvent())->getHtmlLink();
}

function add_all_sections_to_calendar($cal, $calendar, $sections) {
	$useBatch = $cal->getClient()->shouldDefer();
	$cal->getClient()->setUseBatch(true);
	$batch = new Google_Http_Batch($cal->getClient());
	foreach ($sections as $section) {
		$batch->add($cal->events->insert($calendar->getId(), $section->toCalendarEvent()), $section->title);
	}
  $batch->execute();
	$cal->getClient()->setUseBatch($useBatch);
}

function semester_to_string($semester) {
	$seasons = array(0 => "", 1 => "Spring", 2 => "Summer", 3 => "Fall");
    return sprintf('%s %s', $seasons[intval(substr($semester, 4, 5))], substr($semester, 0, 4));
}

function get_json($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);
    return $result;
}
?>
