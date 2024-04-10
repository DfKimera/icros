<?php
require_once('vendor/autoload.php');
require_once('functions.php');

use Intervention\Image\ImageManagerStatic as Image;

const MAX_WIDTH = 1980;
const MAX_HEIGHT = 1980;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$debugMode = getenv('APP_DEBUG') === 'true';

try {
    $bugsnag = Bugsnag\Client::make(getenv('BUGSNAG_API_KEY'));
    Bugsnag\Handler::register($bugsnag);
} catch (Exception $e) {}

if(!$debugMode) {
	error_reporting('Off');
}

$queryPos = strpos($_SERVER['REQUEST_URI'], '?');
$path = substr($_SERVER['REQUEST_URI'], 1, ($queryPos !== false) ? ($queryPos - 1) : strlen($_SERVER['REQUEST_URI']));

if(strlen($path) <= 0 || $path === '/') {
	echo "Icros 1.1";

    if ($debugMode) {
        echo "<br>DEBUG MODE<br><pre>" . json_encode(getenv(), JSON_PRETTY_PRINT) . "</pre>";
    }

    exit();
}

$queryString = clearQueryString($_SERVER['QUERY_STRING']);

debug("Handling GET: {$path} (QS: {$queryString})", $debugMode);

$storeDir = str_finish(getenv('STORE_PATH'), '/');
$fetchDir = str_finish(getenv('FETCH_PATH'), '/');

$fullName = clearRelatives($path . ((strlen($queryString) > 0) ? "@{$queryString}" : ''));

$fetchPath = resolvePathPrefix($path, $storeDir, $fetchDir);
$storePath = resolvePathPrefix($fullName, $storeDir);

$ext = substr($path, strrpos($path, '.') + 1);

if(strlen($queryString) <= 0) {

    debug("\t No query string detected, redirecting to original file...", $debugMode);

	http_response_code(301);
	header("Location: /{$path}?mORIGINAL.{$ext}");

	exit();
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

if(!in_array(strtolower($ext), $allowedExtensions)) {
    debug("\t Extension not allowed: $ext", $debugMode);

	http_response_code(404);
	die("403 Extension not allowed " . ($debugMode ? "[{$ext}]" : ''));
}
	
$options = parseOptions($queryString);

if(!file_exists($fetchPath)) {
    debug("\t File not found in fetch path: {$fetchPath}", $debugMode);

	http_response_code(404);
	die("404 Not Found " . ($debugMode ? " - {$fetchPath}" : ''));
}

$img = Image::make($fetchPath);

if($options['mode'] !== 'ORIGINAL') {
	if($options['width'] > MAX_WIDTH) die('Width over limit');
	if($options['height'] > MAX_HEIGHT) die('Height over limit');
	if($options['x'] > MAX_WIDTH) die('X over limit');
	if($options['y'] > MAX_HEIGHT) die('Y over limit');

	if($options['width'] <= 0) die('Width under 0');
	if($options['height'] <= 0) die('Height under 0');
	if($options['x'] < 0) die('X under zero');
	if($options['y'] < 0) die('Y under zero');
}

debug("\t OPTS: " . json_encode($options), $debugMode);

switch($options['mode']) {
	case "CROP":

		$img->crop($options['width'], $options['height'], $options['x'], $options['y']);

		break;

	case "COVER":

		$img->fit($options['width'], $options['height'], function ($constraint) {
			$constraint->upsize();
		});

		break;

	case "CONTAIN":

		$img
			->resize($options['width'], $options['height'], function ($constraint) {
				$constraint->aspectRatio();
				$constraint->upsize();
			})
			->resizeCanvas($options['width'], $options['height']);

		break;

	case "SCALE":

		$img
			->resize($options['width'], $options['height'], function ($constraint) {
				$constraint->aspectRatio();
				$constraint->upsize();
			});

		break;

	case "RESIZE":

		$img->resize($options['width'], $options['height']);

		break;

}

debug("\t STORE: {$storePath}", $debugMode);

$img->save($storePath);

echo $img->response($options['extension'], $options['quality']);