<?php

global $args;
$args = array();

function argumentos($argv) {
    global $args;
    foreach($argv as $arg) {
        $ex = explode('=', $arg);
        if (isset($ex[0]) AND isset($ex[1])) {
            $args[$ex[0]] = $ex[1];
        }
    }
}

function dvd($expression, $return) {
    $var_dump = var_dump($expression, true);

    if ($return) {
        return $var_dump;
    }

    die($var_dump);
}

function tolog($text) {
    $directory = PATH_OUTPUT_DIRECTORY;
    $file_path = "$directory/log.txt";

    $handle = fopen($file_path, "a+");

    fwrite($handle, $text);
    fclose($handle);
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

function form_directory_handle() {
    verify_directory(PATH_OUTPUT_DIRECTORY."/form/");
}

function to_class_name($table_name) {
    $class_name_no_tb = str_replace('tb', '', $table_name);
    $class_name_no_underline = str_replace('_', ' ', $class_name_no_tb);
    $class_name_uc = ucwords($class_name_no_underline);
    $class_name = str_replace(' ', '', $class_name_uc);
    return $class_name;
}

function to_attribute_name($table_name) {
    $attribute_name_temp = lcfirst(to_class_name($table_name));
    $attribute_name = $attribute_name_temp;
    return $attribute_name;
}

function handle_output_directory($path) {
    if(is_dir($path)) {
        $now = time();
        rename($path, "{$path}.{$now}");
    }
    if (!is_dir($path)) mkdir($path);
}

function create_directory($path) {
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
        SELECT  attname, *
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
            //print_r($data_attributes);
            //exit;
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

        foreach ($table->attributes_list as $attribute) {
            $text .= "- {$attribute->attname}" . PHP_EOL;
        }

        foreach ($table->attributes_list as $attribute) {
            $text .= "{$attribute->attname},";
        }

        $text .= PHP_EOL;
    }

    fwrite($handle, $text);
    fclose($handle);

}

function normalize_as_namespaces_and_classes($tables) {
    echo "normalize_as_namespaces_and_classes". PHP_EOL;
    $database = array();
    $database['schemas'] = array();

    $table_schema = '';
    $actual_schema = '';

    foreach($tables as $table) {
        $table_schema = to_class_name($table->schemaname);

        if ($actual_schema !== $table_schema) {
            $actual_schema = $table_schema;
            //$dabatase['schemas'][$actual_schema] = array();
            $database['schemas']["$actual_schema"]['name'] = $actual_schema;
            $database['schemas']["$actual_schema"]['tables'] = array();
        }

        $class = new stdClass();
        $class->name = to_class_name($table->tablename);
        $class->attributes = array();

        foreach ($table->attributes_list as $attribute_data) {
            $attribute = new stdClass();
            $attribute->name = to_attribute_name($attribute_data->attname);
            $class->attributes[] = $attribute;

            $attribute->attributeData = $attribute_data;
        }

        $class->tableData = $table;

        $database['schemas']["$actual_schema"]['tables'][] = $class;

    }

    return $database;
}

function create_po_directories($database) {
    echo "create_po_directories". PHP_EOL;
    mkdir(PATH_OUTPUT_DIRECTORY . '/Po');
    foreach ($database['schemas'] as $schema) {
        mkdir(PATH_OUTPUT_DIRECTORY . '/Po/' . $schema['name']);
    }
}

function create_dao_directories($database) {
    echo "create_dao_directories". PHP_EOL;
    mkdir(PATH_OUTPUT_DIRECTORY . '/Dao');
    foreach ($database['schemas'] as $schema) {
        mkdir(PATH_OUTPUT_DIRECTORY . '/Dao/' . $schema['name']);
    }
}

function create_class_files($database) {
    echo "create_class_files". PHP_EOL;
    $po_path = PATH_OUTPUT_DIRECTORY . '/Po';
    foreach ($database['schemas'] as $schema) {
        $schema_name = '/' . $schema['name'] . '/';
        $schema_po_path = $po_path . $schema_name;
        //echo $schema_po_path . PHP_EOL;
        foreach ($schema['tables'] as $table) {
            $class_file = "{$table->name}.php";
            $class_path = "{$schema_po_path}{$class_file}";
            // echo $table->name . PHP_EOL;
            // echo $class_file.PHP_EOL;

            $handle = fopen($class_path, "w");

            $text = "<?php" . PHP_EOL . PHP_EOL;
            $text .= "namespace Sis\\Po\\{$schema['name']};" . PHP_EOL . PHP_EOL;
            $text .= "class {$table->name} {" . PHP_EOL;

            fwrite($handle, $text);

            write_class_attributes($handle, $table);

            $end_class = "}" . PHP_EOL . PHP_EOL;
            fwrite($handle, $end_class);

            fclose($handle);
        }
    }
}

function write_class_attributes($handle, $table) {
    foreach ($table->attributes as $attribute) {
        $text = '';
        $text = "    public \${$attribute->name} = '';" . PHP_EOL;
        $text .= print_r($attribute, true);
        fwrite($handle, $text);
    }
    // $text = print_r($table, true);
    // fwrite($handle, $text);
    // $text = print_r($table->attributes, true);
    // fwrite($handle, $text);
}
