<?php
ob_start(); // Output buffering ensures any errors or warnings get discarded when rendering the image

require_once('vendor/autoload.php');
require_once('functions.php');

use Intervention\Image\ImageManagerStatic as Image;

const MAX_WIDTH = 3000;
const MAX_HEIGHT = 3000;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$debugMode = getenv('APP_DEBUG') === 'true';

// Sets up error display & logging accordingly
ini_set('display_errors', $debugMode ? 'On' : 'Off');
error_reporting($debugMode ? (E_ALL | E_STRICT) : 0);

$querySymbolPosition = strpos($_SERVER['REQUEST_URI'], '?');

$path = $_GET['p'] ?? substr(
    $_SERVER['REQUEST_URI'],
    1,
    ($querySymbolPosition !== false)
        ? ($querySymbolPosition - 1)
        : strlen($_SERVER['REQUEST_URI'])
);

if(strlen($path) <= 0 || $path === '/') {
	echo "Icros 1.1";

    if ($debugMode) {
        echo " - DEBUG MODE";
    }

    exit();
}

$queryString = clearQueryString($_GET['q'] ?? $_SERVER['QUERY_STRING']);

$sourceExt = substr($path, strrpos($path, '.') + 1);
$targetExt = strrpos($queryString, '.') !== false
    ? substr($queryString, strrpos($queryString, '.') + 1)
    : $sourceExt;

debug("Handling GET: {$path} (QS: {$queryString})", $debugMode);

$storeDir = str_finish(getenv('STORE_PATH'), '/');
$fetchDir = str_finish(getenv('FETCH_PATH'), '/');

$fullName = clearRelatives($path . ((strlen($queryString) > 0) ? "!{$queryString}" : ''));

$fetchPath = resolvePathPrefix($path, $storeDir, $fetchDir);
$storePath = resolvePathPrefix($fullName, $storeDir);


if(strlen($queryString) <= 0) {

    debug("\t No query string detected, redirecting to original file...", $debugMode);

	http_response_code(301);
	header("Location: /{$path}?mORIGINAL.{$sourceExt}");

	exit();
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

if(!in_array(strtolower($sourceExt), $allowedExtensions) || !in_array(strtolower($targetExt), $allowedExtensions)) {
    debug("\t Extension not allowed: $sourceExt", $debugMode);

	http_response_code(404);
	die("403 Extension not allowed " . ($debugMode ? "[{$sourceExt}]" : ''));
}
	
$options = parseOptions($queryString);

if(!file_exists($fetchPath)) {
    debug("\t File not found in fetch path: {$fetchPath}", $debugMode);

	http_response_code(404);
	die("404 Not Found " . ($debugMode ? " - {$fetchPath}" : ''));
}

if (file_exists($storePath)) {
    ob_end_clean();
    ob_implicit_flush(true);

    header('X-Icros-Origin: cache-generated');

    header('Content-Type: ' . getMimeTypeFromExtension($targetExt));

    $cachedFile = fopen($storePath, 'r');
    fpassthru($cachedFile);

    exit();
}

if ($sourceExt === 'png' && ($targetExt === 'jpg' || $targetExt === 'jpeg')) {
    $sourceImg = Image::make($fetchPath);

    $img = Image::canvas($sourceImg->width(), $sourceImg->height(), '#ffffff');
    $img->insert($sourceImg);
} else {
    $img = Image::make($fetchPath);
}

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

// Persist the cached image on disk
$img->save($storePath);

ob_end_clean();
ob_implicit_flush(true);

header('Content-Type: ' . getMimeTypeFromExtension($targetExt));
header('X-Icros-Origin: fresh-generated');

echo $img->response($options['extension'], $options['quality']);

ob_end_flush();

exit();