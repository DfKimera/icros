<?php
use Silex\Application;
use Intervention\Image\ImageManagerStatic as Image;

require_once('vendor/autoload.php');

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

if(getenv('APP_DEBUG') !== 'true') {
	error_reporting('Off');
}

function clearRelatives($in) {
	return str_replace('../', '', $in);
}

function clearQueryString($in) {
	return str_replace(['/','#','$','!','%','&'], '', $in);
}

function resolvePathPrefix($incomingPath, $baseDir) {
	if(substr($incomingPath, 0, strlen($baseDir)) === $baseDir) {
		return clearRelatives($incomingPath);
	}

	return str_finish($baseDir, '/') . clearRelatives($incomingPath);
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

$fetchPath = resolvePathPrefix($path, $storeDir);
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
	die("403 Extension not allowed");
}
	
$options = parseOptions($queryString);

if(!file_exists($fetchPath)) {
	http_response_code(404);
	die("404 Not Found");
}

$img = Image::make($fetchPath);
$imgW = $img->width();
$imgH = $img->height();

switch($options['mode']) {
	case "CROP":

		$img->crop($options['width'], $options['height'], $options['x'], $options['y']);

		break;

	case "COVER":

		$img->fit($options['width'], $options['height'], function ($constraint) {
			$constraint->upsize();
		});

		break;

	case "RESIZE":

		$img->resize($options['width'], $options['height']);

		break;

}

$img->save($storePath);

die($img->response($options['extension'], $options['quality']));