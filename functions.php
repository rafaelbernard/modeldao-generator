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

function to_class_name($table_name) {
    $class_name_no_tb = str_replace('tb', '', $table_name);
    $class_name_no_underline = str_replace('_', ' ', $class_name_no_tb);
    $class_name_uc = ucwords($class_name_no_underline);
    $class_name = str_replace(' ', '', $class_name_uc);
    return $class_name;
}

function handle_output_directory($path) {
    if(is_dir($path)) {
        $now = time();
        rename($path, "{$path}.{$now}");
    }
    if (!is_dir($path)) mkdir($path);
}

function query_tables() {
    $query_tables = "
        SELECT  c.oid AS tableoid
        ,       c.relname AS tablename
        ,       n.nspname AS schemaname
        FROM    pg_catalog.pg_class c
        LEFT JOIN
                pg_namespace n
                ON  n.oid = c.relnamespace
        WHERE   c.relkind = 'r' -- r = relation
        AND     n.nspname not in ('pg_catalog','information_schema') -- nspname = schemaname
        ORDER BY schemaname, tablename
        ";
    $result_tables = pg_query($query_tables);
    return $result_tables;
}

function normalize_result_tables($result_tables) {
    echo 'Normalizing tables' . PHP_EOL;
    $tables = array();
    $idx = 0;
    while ($data = pg_fetch_object($result_tables)) {
        $table = $data;
        $tables[$idx] = $table;
        $idx++;
    }
    return $tables;
}

function get_attributes(&$tables) {
    echo 'Retrieving attributes from tables' . PHP_EOL;
    if ($tables) {
        foreach($tables as $table) {
            $table->attributes_list = get_attributes_from_table($table->tableoid);
        }
    }
}

function get_attributes_from_table($tableoid) {
    # `attnum` negativos sao colunas de sistema
    $query_attributes = sprintf("
        SELECT  *
        FROM    pg_catalog.pg_attribute
        WHERE   attrelid = %d
        AND     attnum > 0
        ORDER BY attnum
        "
        , $tableoid
        );
    $result_tables_attributes = pg_query($query_attributes);

    if ($result_tables_attributes)
    {
        $array_attributes = array();

        while ($data_attributes = pg_fetch_object($result_tables_attributes))
        {
            $array_attributes[] = $data_attributes;
        }

        return $array_attributes;
    }

    return;
}

function write_tables_file($tables) {
    echo "Writing tables file". PHP_EOL;

    $directory = PATH_OUTPUT_DIRECTORY;
    $file_path = "$directory/tables.txt";

    echo "File path: {$file_path} ". PHP_EOL;

    $handle = fopen($file_path, "w");

    $text = '';

    foreach($tables as $table) {
        $text .= PHP_EOL . "=====" . PHP_EOL;
        $text .= "tabela - {$table->schemaname}.{$table->tablename}\n";
        $text .= "=====" . PHP_EOL;
    }

    fwrite($handle, $text);
    fclose($handle);

}
