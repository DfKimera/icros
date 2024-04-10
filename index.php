<?php
use Intervention\Image\ImageManagerStatic as Image;

require_once('vendor/autoload.php');

const MAX_WIDTH = 1980;
const MAX_HEIGHT = 1980;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$debugMode = getenv('APP_DEBUG') === 'true';

if(!$debugMode) {
	error_reporting('Off');
}

function clearRelatives($in) {
	return str_replace('../', '', $in);
}

function clearQueryString($in) {
	return str_replace(['/','#','$','!','%','&'], '', $in);
}

function resolvePathPrefix($incomingPath, $baseDir, $rootDir = null) {
	if(!$rootDir) $rootDir = $baseDir;

	if(substr($incomingPath, 0, strlen($baseDir)) === $baseDir) {
		$incomingPath = substr($incomingPath, strlen($baseDir));
	}

	return str_finish($rootDir, '/') . clearRelatives($incomingPath);
}

function parseOptions($queryString) {
	$opts = explode(',', $queryString);
	$options = [
		'mode' => 'ORIGINAL',
		'width' => 100,
		'height' => 100,
		'x' => 0,
		'y' => 0,
		'extension' => 'jpg',
		'quality' => 90,
	];

	if(sizeof($opts) <= 0) return $options;

	foreach($opts as $opt) {

		if(strlen(trim($opt)) <= 0) continue;

		$dotPos = strpos($opt, '.');

		if($dotPos !== false) { // strip extension from the last option
			$options['extension'] = substr($opt, $dotPos + 1);
			$opt = substr($opt, 0, $dotPos);
		}

		if(strlen(trim($opt)) <= 0) continue;

		switch(strtolower($opt[0])) {
			case 'w': $options['width'] = intval(substr($opt, 1)); break;
			case 'h': $options['height'] = intval(substr($opt, 1)); break;
			case 'x': $options['x'] = intval(substr($opt, 1)); break;
			case 'y': $options['y'] = intval(substr($opt, 1)); break;
			case 'm': $options['mode'] = strtoupper(substr($opt, 1)); break;
			case 'q': $options['quality'] = intval(substr($opt, 1)); break;
		}
	}

	return $options;
}

$queryPos = strpos($_SERVER['REQUEST_URI'], '?');
$path = substr($_SERVER['REQUEST_URI'], 1, ($queryPos !== false) ? ($queryPos - 1) : strlen($_SERVER['REQUEST_URI']));

if(strlen($path) <= 0 || $path === '/') {
	die("Icros 1.0");
}

$queryString = clearQueryString($_SERVER['QUERY_STRING']);

$storeDir = str_finish(getenv('STORE_PATH'), '/');
$fetchDir = str_finish(getenv('FETCH_PATH'), '/');

$fullName = clearRelatives($path . ((strlen($queryString) > 0) ? "@{$queryString}" : ''));

$fetchPath = resolvePathPrefix($path, $storeDir, $fetchDir);
$storePath = resolvePathPrefix($fullName, $storeDir);

$ext = substr($path, strrpos($path, '.') + 1);

if(strlen($queryString) <= 0) {

	http_response_code(301);
	header("Location: /{$path}?mORIGINAL.{$ext}");

	exit();
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

if(!in_array(strtolower($ext), $allowedExtensions)) {
	http_response_code(404);
	die("403 Extension not allowed " . ($debugMode ? "[{$ext}]" : ''));
}
	
$options = parseOptions($queryString);

if(!file_exists($fetchPath)) {
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

$img->save($storePath);

echo $img->response($options['extension'], $options['quality']);