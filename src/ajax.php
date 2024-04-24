<?php
// ***
// *** phpSimpleBookmar ... yehaaa!
// ***

// Header agains Cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Initiate Configuration Array
$GLOBALS['CONFIG'] = Array();

// Database Connection - SQLite
$GLOBALS['CONFIG']['DB_DSN'] = 'sqlite:bookmarks.sqlite';

// Database Connection - mySQL
/*
$GLOBALS['CONFIG']['DB_DSN']    = 'mysql:host=192.168.1.2;dbname=phpsimplebookmark';
$GLOBALS['CONFIG']['DB_USER']   = 'phpsimplebookmark';
$GLOBALS['CONFIG']['DB_PASSWD'] = 'phpsimplebookmark';
*/

// Timezone / Language
date_default_timezone_set('Europe/Berlin');
setlocale(LC_TIME, 'de_DE@euro', 'de_DE', 'de', 'de');

// Connect
try {
	$GLOBALS['DB'] = new PDO($GLOBALS['CONFIG']['DB_DSN']);
	// $GLOBALS['DB'] = new PDO($GLOBALS['CONFIG']['DB_DSN'], $GLOBALS['CONFIG']['DB_USER'], $GLOBALS['CONFIG']['DB_PASSWD'], Array(PDO::MYSQL_ATTR_FOUND_ROWS => true));
} catch(PDOException $e) {
	die('Connection failed: ' . $e->getMessage());
}

try {
    $stmt = $GLOBALS['DB']->query("SELECT * FROM settings");
} catch(PDOException $e) {
	$GLOBALS['DB']->exec("CREATE TABLE settings (s_key TEXT, s_value TEXT)");

	$GLOBALS['DB']->exec("CREATE TABLE category (ca_id INTEGER PRIMARY KEY, ca_title TEXT, ca_icon TEXT, ca_pos NUMBER)");
	$GLOBALS['DB']->exec("INSERT INTO  category (ca_title, ca_icon, ca_pos) VALUES ('General', 'bi-globe'   , 0)");
	$GLOBALS['DB']->exec("INSERT INTO  category (ca_title, ca_icon, ca_pos) VALUES ('Social' , 'bi-telegram', 1)");

	$GLOBALS['DB']->exec("CREATE TABLE bookmark (bm_id INTEGER PRIMARY KEY, bm_title TEXT, bm_url TEXT, bm_icon TEXT, bm_category NUMBER, bm_x NUMBER, bm_y NUMBER)");
    $GLOBALS['DB']->exec("INSERT INTO  bookmark (bm_title, bm_url, bm_icon, bm_category, bm_x, bm_y) VALUES ('Google'  , 'https://www.google.com/' , 'www.google.com' , 1, 0, 0)");
    $GLOBALS['DB']->exec("INSERT INTO  bookmark (bm_title, bm_url, bm_icon, bm_category, bm_x, bm_y) VALUES ('YouTube' , 'https://www.youtube.com/', 'www.youtube.com', 1, 1, 1)");
    $GLOBALS['DB']->exec("INSERT INTO  bookmark (bm_title, bm_url, bm_icon, bm_category, bm_x, bm_y) VALUES ('X'       , 'https://twitter.com/'    , 'twitter.com'    , 2, 0, 0)");
    $GLOBALS['DB']->exec("INSERT INTO  bookmark (bm_title, bm_url, bm_icon, bm_category, bm_x, bm_y) VALUES ('reddit'  , 'https://www.reddit.com/' , 'www.reddit.com' , 2, 1, 0)");

	if(!is_dir("icons")) mkdir("icons");
	file_put_contents("icons/www.google.com.png" , file_get_contents("https://icon.horse/icon/www.google.com"));
	file_put_contents("icons/www.youtube.com.png", file_get_contents("https://icon.horse/icon/www.youtube.com"));
	file_put_contents("icons/twitter.com.png"    , file_get_contents("https://icon.horse/icon/twitter.com"));
	file_put_contents("icons/www.reddit.com.png" , file_get_contents("https://icon.horse/icon/www.reddit.com"));
}

// Feedback
$feedback = Array();
$feedback['status'] = 1;
$feedback['message'] = "Unknown Query!";

// AJAX
if(isset($_REQUEST['action'])) {
	// Just that all other start with elseif
	if($_REQUEST['action'] == "DUMMY") {

	// Category - List
    } elseif($_REQUEST['action'] == "getCategoryList") {
        $feedback['categories'] = Array();
        $stmt = $GLOBALS['DB']->query("SELECT * FROM category");
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			array_push($feedback['categories'], $row);
		}
		$feedback['status'] = 0; $feedback['message'] = "OK";

	// Category - Save
	} elseif($_REQUEST['action'] == "saveCategory" && isset($_REQUEST['category'])) {
		$ca = json_decode($_REQUEST['category'], true);

		// Write 2 DB
		if($ca['ca_id'] > 0) {
			$stmt = $GLOBALS['DB']->prepare("UPDATE category SET ca_title = :ca_title, ca_icon = :ca_icon, ca_pos = :ca_pos WHERE ca_id = :ca_id");
			$stmt->bindValue(':ca_id'      , $ca['ca_id']      , PDO::PARAM_INT);
		} else {
			$stmt = $GLOBALS['DB']->prepare("INSERT INTO  category (ca_title, ca_icon, ca_pos) VALUES (:ca_title, :ca_icon, :ca_pos)");
		}
		$stmt->bindValue(':ca_title', $ca['ca_title'], PDO::PARAM_STR);
		$stmt->bindValue(':ca_icon' , $ca['ca_icon'] , PDO::PARAM_STR);
		$stmt->bindValue(':ca_pos'  , $ca['ca_pos']  , PDO::PARAM_INT);
		if($stmt->execute()) {
			// Return updated Category
			if($ca['ca_id'] == 0) $ca['ca_id'] = $GLOBALS['DB']->lastInsertId();
			$feedback['category'] = $ca;
			$feedback['status'] = 0; $feedback['message'] = "OK";
		} else {
			$sqlerror = $stmt->errorInfo(); $feedback['status'] = 99; $feedback['message'] = $sqlerror[2];
		}

	// Category - Delete
	} elseif($_REQUEST['action'] == "deleteCategory" && isset($_REQUEST['ca_id'])) {
		// TBD

	// Bookmark - List
	} elseif($_REQUEST['action'] == "getBookmarkList") {
        $feedback['bookmarks'] = Array();
        $stmt = $GLOBALS['DB']->query("SELECT * FROM bookmark");
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			array_push($feedback['bookmarks'], $row);
		}
		$feedback['status'] = 0; $feedback['message'] = "OK";

	// Bookmark - Save
    } elseif($_REQUEST['action'] == "saveBookmark" && isset($_REQUEST['bookmark'])) {
		$bm = json_decode($_REQUEST['bookmark'], true);

		// Get Icons from icon.horse
		$bm['bm_icon'] = parse_url($bm['bm_url'], PHP_URL_HOST);
		if(strlen($bm['bm_icon']) > 0) {
			if(!file_exists("icons/" . $bm['bm_icon'] . ".png")) {
				try {
					$context = stream_context_create(array('http'=>array('timeout' => 60)));
					@file_put_contents("icons/" . $bm['bm_icon'] . ".png", @file_get_contents("https://icon.horse/icon/" . $bm['bm_icon'], false, $context));
					if(!(filesize("icons/" . $bm['bm_icon'] . ".png") > 0)) {
						unlink("icons/" . $bm['bm_icon'] . ".png");
					}
				} catch(Exception $e) {
					$bm['bm_icon'] = "dummy";
				}
			}
		} else {
			$bm['bm_icon'] = "dummy";
		}

		// Write 2 DB
		if($bm['bm_id'] > 0) {
			$stmt = $GLOBALS['DB']->prepare("UPDATE bookmark SET bm_title = :bm_title, bm_url = :bm_url, bm_icon = :bm_icon, bm_category = :bm_category, bm_x = :bm_x, bm_y = :bm_y WHERE bm_id = :bm_id");
			$stmt->bindValue(':bm_id'      , $bm['bm_id']      , PDO::PARAM_INT);
		} else {
			$stmt = $GLOBALS['DB']->prepare("INSERT INTO  bookmark (bm_title, bm_url, bm_icon, bm_category, bm_x, bm_y) VALUES (:bm_title, :bm_url, :bm_icon, :bm_category, :bm_x, :bm_y)");
		}
		$stmt->bindValue(':bm_title'   , $bm['bm_title']   , PDO::PARAM_STR);
		$stmt->bindValue(':bm_url'     , $bm['bm_url']     , PDO::PARAM_STR);
		$stmt->bindValue(':bm_icon'    , $bm['bm_icon']    , PDO::PARAM_STR);
		$stmt->bindValue(':bm_category', $bm['bm_category'], PDO::PARAM_INT);
		$stmt->bindValue(':bm_x'       , $bm['bm_x']       , PDO::PARAM_INT);
		$stmt->bindValue(':bm_y'       , $bm['bm_y']       , PDO::PARAM_INT);
		if($stmt->execute()) {
			// Return updated Bookmark
			if($bm['bm_id'] == 0) $bm['bm_id'] = $GLOBALS['DB']->lastInsertId();
			$feedback['bookmark'] = $bm;
			$feedback['status'] = 0; $feedback['message'] = "OK";
		} else {
			$sqlerror = $stmt->errorInfo(); $feedback['status'] = 99; $feedback['message'] = $sqlerror[2];
		}

	// Bookmark - Save
	} elseif($_REQUEST['action'] == "deleteBookmark" && isset($_REQUEST['bm_id'])) {
		// TBD


	} // End of ACTION
}


// Response
header("Content-type: application/json; charset=UTF-8");
echo json_encode($feedback);
?>
