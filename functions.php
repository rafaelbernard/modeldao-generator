<?php

global $args;
$args = array();

function argumentos($argv) {
    global $args;
    foreach($argv as $arg) {
        $ex = explode('=', $arg);
        if (isset($ex[0]) AND isset($ex[1]))
            $args[$ex[0]] = $ex[1];
    }
}

function dvd($expression, $return) {
    $var_dump = var_dump($expression, true);

    if ($return) {
        return $var_dump;
    }

    die($var_dump);
}

function schema_directory_handle($schema) {
    if ($schema) {
        verify_directory(PATH_OUTPUT_DIRECTORY."/po/");
        $path_schema = PATH_OUTPUT_DIRECTORY."/po/$schema";
        verify_directory($path_schema);
    }
}

function verify_directory($path) {
    if (!is_dir($path)) mkdir($path);
}

function form_directoty_handle() {
    verify_directory(PATH_OUTPUT_DIRECTORY."/form/");
}
