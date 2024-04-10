<?php
require_once('vendor/autoload.php');

function clearRelatives(string $in) : string {
    return str_replace('../', '', $in);
}

function clearQueryString(string $in) : string {
    return str_replace(['/','#','$','!','%','&'], '', $in);
}

function resolvePathPrefix(string $incomingPath, string $baseDir, ?string $rootDir = null) : string {
    if(!$rootDir) $rootDir = $baseDir;

    if(substr($incomingPath, 0, strlen($baseDir)) === $baseDir) {
        $incomingPath = substr($incomingPath, strlen($baseDir));
    }

    return str_finish($rootDir, '/') . clearRelatives($incomingPath);
}

function parseOptions($queryString) : array {
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

    if(sizeof($opts) <= 0) {
        return $options;
    }

    foreach($opts as $opt) {

        if(strlen(trim($opt)) <= 0) continue;

        $dotPos = strpos($opt, '.');

        if($dotPos !== false) { // strip extension from the last option
            $options['extension'] = substr($opt, $dotPos + 1);
            $opt = substr($opt, 0, $dotPos);
        }

        if(strlen(trim($opt)) <= 0) {
            continue;
        }

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

function debug(string $message, bool $debugMode) : void {
    if (!$debugMode) return;

    error_log('<' .  date('Y-m-d H:i:s') . '> ' . $message, 3, str_finish(__DIR__, '/') . 'debug.log');
}