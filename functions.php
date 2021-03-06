p<?php

global $args;
$args = array();

function exception_error_handler($severity, $message, $filename, $lineno)
{
    if (error_reporting() == 0)
    {
        return;
    }
    if (error_reporting() & $severity)
    {
        //die('err');
        tolog(print_r(debug_backtrace(), true), 'error.log');
        echo 'Erros encontrados - error.log.' . PHP_EOL;
        exit;
    }
}

function connect($connection_string) {
    $resource = @pg_connect($connection_string);
    if (!$resource)
        throw new Exception('Connection error.');
    return $resource;
}

function argumentos($argv) {
    global $args;
    foreach($argv as $arg) {
        $ex = explode('=', $arg);
        if (isset($ex[0]) AND isset($ex[1])) {
            $args[$ex[0]] = $ex[1];
        }
    }
}

function dvd($expression, $return = false) {
    ob_start();
    $var_dump = '';
    var_dump($expression, true);
    $var_dump .= ob_get_clean();

    if ($return) {
        return $var_dump;
    }

    die($var_dump);
}

function tolog($text, $logfile = 'out.log') {
    $directory = PATH_OUTPUT_DIRECTORY;
    $file_path = "$directory/$logfile";

    $handle = fopen($file_path, "a+");

    if (!is_array($text) OR !is_object($text)) $text = dvd($text, true);
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
        AND     n.nspname not in ('pg_catalog','information_schema')
        ORDER BY schemaname, tablename
        ";
    $result_tables = pg_query($query_tables);
    return $result_tables;
}

function normalize_result_tables($result_tables) {
    echo PHP_EOL . '[' . __FUNCTION__ . ']' . PHP_EOL;
    echo 'Normalizing tables' . PHP_EOL;
    $tables = array();
    $idx = 0;
    while ($data = pg_fetch_assoc($result_tables)) {
        array_push($tables, $data);
    }
    return $tables;
}

function get_attributes(&$tables) {
    echo PHP_EOL . '[' . __FUNCTION__ . ']' . PHP_EOL;
    echo 'Retrieving attributes from tables' . PHP_EOL;
    if ($tables) {
        foreach($tables as &$table) {
            $table['attributes_list'] = get_attributes_from_table($table['tableoid']);
            //dvd($table['attributes_list']);
        }
    }
}

function get_attributes_from_table($tableoid) {
    # `attnum` negativos sao colunas de sistema
    $query_attributes = sprintf("
        SELECT  attname,
                a.*,
                CASE
                    WHEN c.oid IS NOT NULL THEN
                        1
                    ELSE
                        0
                END AS isprimarykey
        FROM    pg_catalog.pg_attribute a
        LEFT JOIN
                pg_catalog.pg_constraint c
                ON  c.conrelid      = a.attrelid
                AND a.attnum        = ANY(c.conkey)
                AND c.contype       = 'p'
        WHERE   attrelid = %d
        AND     attnum > 0
        AND     attstattarget = -1
        ORDER BY attnum
        "
        , $tableoid
        );
    $result_tables_attributes = pg_query($query_attributes);

    if ($result_tables_attributes)
    {
        $array_attributes = array();

        while ($data_attributes = pg_fetch_assoc($result_tables_attributes))
        {
            $array_attributes[] = $data_attributes;
            //tolog($data_attributes);
        }

        return $array_attributes;
    }

    return;
}

function write_tables_file($tables) {
    echo PHP_EOL . '[' . __FUNCTION__ . ']' . PHP_EOL;
    echo "Writing tables file". PHP_EOL;

    $directory = PATH_OUTPUT_DIRECTORY;
    $file_path = "$directory/tables.txt";

    echo "File path: {$file_path} ". PHP_EOL;

    $handle = fopen($file_path, "w");

    $text = '';

    foreach($tables as $table) {
        $text .= PHP_EOL . "=====" . PHP_EOL;
        $text .= "tabela - {$table['schemaname']}.{$table['tablename']}\n";
        $text .= "=====" . PHP_EOL;

        foreach ($table['attributes_list'] as $attribute) {
            $text .= "- {$attribute['attname']}" . PHP_EOL;
        }

        foreach ($table['attributes_list'] as $attribute) {
            $text .= "{$attribute['attname']},";
        }

        $text .= PHP_EOL;
    }

    fwrite($handle, $text);
    fclose($handle);

}

function normalize_as_namespaces_and_classes($tables) {
    echo PHP_EOL . "normalize_as_namespaces_and_classes". PHP_EOL;
    $database = array();
    $database['schemas'] = array();

    $table_schema = '';
    $actual_schema = '';

    foreach($tables as $table) {
        $table_schema = to_class_name($table['schemaname']);

        if ($actual_schema !== $table_schema) {
            $actual_schema = $table_schema;
            $database['schemas']["$actual_schema"]['name'] = $actual_schema;
            $database['schemas']["$actual_schema"]['tables'] = array();
        }

        $class = array();
        $class['name'] = to_class_name($table['tablename']);
        $class['attributes'] = array();
        $class['primary_key_columns'] = array();

        foreach ($table['attributes_list'] as $attribute_data) {
            $attribute = array();
            $attribute['name'] = to_attribute_name($attribute_data['attname']);
            $attribute['name_ucfirst'] = ucfirst($attribute['name']);
            $attribute['name_as_column'] = $attribute_data['attname'];
            $attribute['is_primary_key'] = $attribute_data['isprimarykey'];
            $class['attributes'][] = $attribute;

            $attribute['attribute_data'] = $attribute_data;

            if (!$attribute['is_primary_key'] == '1') {
                array_push($class['primary_key_columns'], $attribute_data['attname']);
            }
        }

        $class['first_primary_key_column'] = $class['primary_key_columns'] ? $class['primary_key_columns'][0] : 'xxx';
        $class['first_primary_key_attribute_name'] = to_attribute_name($class['first_primary_key_column']);
        $class['table_data'] = $table;

        //tolog($class['name']);
        //tolog($class['primary_key_columns']);

        $database['schemas']["$actual_schema"]['tables'][] = $class;

    }

    return $database;
}

function create_entity_directories($database) {
    echo "create_entity_directories". PHP_EOL;
    mkdir(PATH_OUTPUT_DIRECTORY . '/Entity');
    foreach ($database['schemas'] as $schema) {
        mkdir(PATH_OUTPUT_DIRECTORY . '/Entity/' . $schema['name']);
    }
    rmdir(PATH_OUTPUT_DIRECTORY . '/Po/Public');
}

function create_datamapper_directories($database) {
    echo "create_datamapper_directories". PHP_EOL;
    mkdir(PATH_OUTPUT_DIRECTORY . '/DataMapper');
    foreach ($database['schemas'] as $schema) {
        mkdir(PATH_OUTPUT_DIRECTORY . '/DataMapper/' . $schema['name']);
    }
    rmdir(PATH_OUTPUT_DIRECTORY . '/Po/Public');
}

function create_dao_directories($database) {
    echo "create_dao_directories". PHP_EOL;
    mkdir(PATH_OUTPUT_DIRECTORY . '/Dao');
    foreach ($database['schemas'] as $schema) {
        mkdir(PATH_OUTPUT_DIRECTORY . '/Dao/' . $schema['name']);
    }
    rmdir(PATH_OUTPUT_DIRECTORY . '/Dao/Public');
}

function create_class_files($database) {
    echo "create_class_files". PHP_EOL;
    $po_path = PATH_OUTPUT_DIRECTORY . '/Po';
    foreach ($database['schemas'] as $schema) {
        $schema_name = $schema['name'] != 'Public' ? '/' . $schema['name'] . '/' : '/';
        $schema_po_path = $po_path . $schema_name;

        foreach ($schema['tables'] as $table) {
            $class_file = "{$table['name']}.php";
            $class_path = "{$schema_po_path}{$class_file}";
            $schema_name = $schema['name'];

            $namespace = ($schema_name == 'Public') ? "namespace Sis\\Po;" : "namespace Sis\\Po\\{$schema_name};";

            $handle = fopen($class_path, "w");

            $text = "<?php" . PHP_EOL . PHP_EOL;
            $text .= "$namespace" . PHP_EOL . PHP_EOL;
            $text .= "class {$table['name']} {" . PHP_EOL;

            fwrite($handle, $text);

            write_class_attributes($handle, $table);
            write_class_construct($handle, $table);
            write_class_getter_setters($handle, $table);

            $end_class = "}" . PHP_EOL . PHP_EOL;
            fwrite($handle, $end_class);

            fclose($handle);
        }
    }
}

function write_class_attributes($handle, $table) {
    foreach ($table['attributes'] as $attribute) {
        $text = '';
        $text = "    public \${$attribute['name']} = '';" . PHP_EOL;
        //$text .= print_r($attribute, true);
        fwrite($handle, $text);
    }
}

function write_class_construct($handle, $table) {

    $first_primary_key_column = $table['primary_key_columns'] ? $table['primary_key_columns'][0] : 'xxx';

    $text = "" . PHP_EOL;
    $text .= "    public function __construct(\$atrs = null) {" . PHP_EOL;
    $text .= "        if (\$atrs) { \$this->construir(\$atrs); }" . PHP_EOL;
    $text .= "    }" . PHP_EOL . PHP_EOL;
    $text .= "    public function construir(\$atrs) {".PHP_EOL;
    $text .= "        if (isset(\$atrs->{$first_primary_key_column})) {" . PHP_EOL;
    $text .= "            \$this->construirObjetoBanco(\$atrs);" . PHP_EOL;
    $text .= "            return;" . PHP_EOL;
    $text .= "        }" . PHP_EOL;
    $text .= "        \$this->construirObjeto(\$atrs);" . PHP_EOL;
    $text .= "    }" . PHP_EOL . PHP_EOL;
    $text .= "    public function construirObjetoBanco(\$atrs) {" . PHP_EOL;

    foreach($table['attributes'] as $attribute) {
        //$text .= "" . dvd($attribute, true);
        $text .= "      if (isset(\$atrs->{$attribute['name_as_column']}))" . PHP_EOL;
        $text .= "      {" . PHP_EOL;
        $text .= "          \$this->set{$attribute['name_ucfirst']}(\$atrs->{$attribute['name_as_column']});" . PHP_EOL;
        $text .= "      }" . PHP_EOL;
    }

    $text .= "    }" . PHP_EOL . PHP_EOL;
    $text .= "    public function construirObjeto(\$atrs) {" . PHP_EOL;

    foreach($table['attributes'] as $attribute) {
        $a_name = "\$atrs->{$attribute['name']}";

        $text .= "      if (isset({$a_name}))" . PHP_EOL;
        $text .= "      {" . PHP_EOL;
        $text .= "          \$this->set{$attribute['name_ucfirst']}({$a_name});" . PHP_EOL;
        $text .= "      }" . PHP_EOL;
    }

    $text .= "    }" . PHP_EOL . PHP_EOL;
    fwrite($handle, $text);
}


function write_class_getter_setters($handle, $table) {

    $first_primary_key_column = $table['primary_key_columns'] ? $table['primary_key_columns'][0] : 'xxx';

    $text = "" . PHP_EOL;

    foreach($table['attributes'] as $attribute) {
        $set = "set{$attribute['name_ucfirst']}(\${$attribute['name']})";
        $get = "get{$attribute['name_ucfirst']}()";
        $setting = "\$this->{$attribute['name']} = \${$attribute['name']};";
        $getting = "return \$this->{$attribute['name']};";

        $text .= "      public function {$set} {" . PHP_EOL;
        $text .= "          {$setting}" . PHP_EOL;
        $text .= "      }" . PHP_EOL;

        $text .= "      public function {$get} {" . PHP_EOL;
        $text .= "          {$getting}" . PHP_EOL;
        $text .= "      }" . PHP_EOL;
    }

    fwrite($handle, $text);
}

function create_dao_files($database) {
    echo "create_dao_files". PHP_EOL;
    $po_path = PATH_OUTPUT_DIRECTORY . 'Po';
    $dao_path = PATH_OUTPUT_DIRECTORY . '/Dao';
    foreach ($database['schemas'] as $schema) {
        $schema_name = $schema['name'] != 'Public' ? '/' . $schema['name'] . '/' : '/';
        $schema_dao_path = $dao_path . $schema_name;
        foreach ($schema['tables'] as $table) {
            $po_class_name = $table['name'];
            $class_name = "{$table['name']}DAO";
            $class_file = "{$class_name}.php";
            $class_path = "{$schema_dao_path}{$class_file}";
            $schema_name = $schema['name'];
            $schema_for_namespace_path = ($schema_name == 'Public') ? '' : "{$schema_name}\\";

            $namespace = ($schema_name == 'Public') ? "namespace Sis\\Dao;" : "namespace Sis\\Dao\\{$schema_name};";

            $handle = fopen($class_path, "w");

            $text = "<?php" . PHP_EOL . PHP_EOL;
            $text .= "$namespace" . PHP_EOL . PHP_EOL;
            $text .= "use Sis\\Po\\{$schema_for_namespace_path}{$po_class_name};" . PHP_EOL . PHP_EOL;
            $text .= "class {$class_name} extends GenericDAO {" . PHP_EOL;

            fwrite($handle, $text);

            write_dao_update($handle, $table);
            write_dao_insert($handle, $table);
            write_dao_delete($handle, $table);
            write_dao_get_list($handle, $table);
            //write_class_construct($handle, $table);

            $end_class = "}" . PHP_EOL . PHP_EOL;
            fwrite($handle, $end_class);

            fclose($handle);
        }
    }
}

function write_dao_update($handle, $table) {

    $text = "" . PHP_EOL;

    $className = $table['name'];
    $object = "\$" . strtolower(substr($className, 0, 1)). substr($className, 1);
    $schema_name = $table['table_data']['schemaname'];
    $table_name = $table['table_data']['tablename'];
    $full_table_name = $schema_name == 'public' ? $table_name : $schema_name . '.' . $table_name;

    $text .= "    public function update{$className}($className {$object}) {" . PHP_EOL;
    $text .= "        \$qry = sprintf('" . PHP_EOL;
    $text .= "            UPDATE $full_table_name" . PHP_EOL;
    $text .= "            SET" . PHP_EOL;

    $set = '';
    $first = true;
    $values = '';

    foreach($table['attributes'] as $attribute) {
        $column_name = $attribute['name_as_column'];
        $name_ucfirst = $attribute['name_ucfirst'];

        if ($first) {
            $set .= "          {$column_name} = %s" . PHP_EOL;
            $first = !$first;
        } else {
            $set .= "        , {$column_name} = %s" . PHP_EOL;
        }

        $values .= "        , nulo({$object}->get{$name_ucfirst}()) " . PHP_EOL;

    }

    $text .= " {$set} " . PHP_EOL;
    $text .= "         ' " . PHP_EOL;
    $text .= " {$values} " . PHP_EOL;

    $text .= "        " . PHP_EOL;
    $text .= "        );" . PHP_EOL;
    $text .= "    \$result = \$this->exec(\$qry);" . PHP_EOL;
    $text .= "    return \$result;" . PHP_EOL;
    $text .= "    }" . PHP_EOL;

    fwrite($handle, $text);
}

function write_dao_insert($handle, $table) {

    $text = "" . PHP_EOL;

    $className = $table['name'];
    $object = "\$" . strtolower(substr($className, 0, 1)). substr($className, 1);
    $schema_name = $table['table_data']['schemaname'];
    $table_name = $table['table_data']['tablename'];
    $full_table_name = $schema_name == 'public' ? $table_name : $schema_name . '.' . $table_name;

    $text .= "    public function insert{$className}($className {$object}) {" . PHP_EOL;
    $text .= "        \$qry = sprintf(\"" . PHP_EOL;
    $text .= "            INSERT INTO $full_table_name" . PHP_EOL;
    $text .= "            (" . PHP_EOL;

    $columns = '';
    $variables = '';
    $first = true;
    $values = '';

    foreach($table['attributes'] as $attribute) {
        $column_name = $attribute['name_as_column'];
        $name_ucfirst = $attribute['name_ucfirst'];

        if ($first) {
            $columns .= "          {$column_name}" . PHP_EOL;
            $variables .= "          %s" . PHP_EOL;
            $first = !$first;
        } else {
            $columns .= "        , {$column_name}" . PHP_EOL;
            $variables .= "         , %s" . PHP_EOL;
        }

        $values .= "        , nulo({$object}->get{$name_ucfirst}()) " . PHP_EOL;

    }

    $text .= " {$columns} " . PHP_EOL;
    $text .= " ) VALUES ( " . PHP_EOL;
    $text .= " {$variables} " . PHP_EOL;
    $text .= " ) " . PHP_EOL;
    $text .= "         \" " . PHP_EOL;
    $text .= " {$values} " . PHP_EOL;

    $text .= "        " . PHP_EOL;
    $text .= "        );" . PHP_EOL;
    $text .= "    \$result = \$this->exec(\$qry);" . PHP_EOL;
    $text .= "    return \$result;" . PHP_EOL;
    $text .= "    }" . PHP_EOL;

    fwrite($handle, $text);
}

function write_dao_delete($handle, $table) {

    $text = "" . PHP_EOL;

    tolog($table);
    $className = $table['name'];
    $object = "\$" . strtolower(substr($className, 0, 1)). substr($className, 1);
    $schema_name = $table['table_data']['schemaname'];
    $table_name = $table['table_data']['tablename'];
    $full_table_name = $schema_name == 'public' ? $table_name : $schema_name . '.' . $table_name;
    $first_primary_key_column = $table['first_primary_key_column'];
    $first_primary_key_variable = "\$" . $table['first_primary_key_attribute_name'];

    $text .= "    public function delete{$className}({$first_primary_key_variable}) {" . PHP_EOL;
    $text .= "        \$qry = sprintf(\"" . PHP_EOL;
    $text .= "            DELETE FROM $full_table_name" . PHP_EOL;
    $text .= "            WHERE {$first_primary_key_column} = %d" . PHP_EOL;
    $text .= "            \"" . PHP_EOL;
    $text .= "            , nuloi({$first_primary_key_variable}) " . PHP_EOL;
    $text .= "        );" . PHP_EOL;
    $text .= "    \$result = \$this->exec(\$qry);" . PHP_EOL;
    $text .= "    return \$result;" . PHP_EOL;
    $text .= "    }" . PHP_EOL;

    fwrite($handle, $text);
}

function write_dao_get_list($handle, $table) {

    $text = "" . PHP_EOL;

    tolog($table);
    $className = $table['name'];
    $object = "\$" . strtolower(substr($className, 0, 1)). substr($className, 1);
    $schema_name = $table['table_data']['schemaname'];
    $table_name = $table['table_data']['tablename'];
    $full_table_name = $schema_name == 'public' ? $table_name : $schema_name . '.' . $table_name;
    $first_primary_key_column = $table['first_primary_key_column'];
    $first_primary_key_variable = "\$" . $table['first_primary_key_attribute_name'];

    $columns = '';
    $first = true;
    $values = '';
    $variables = '';

    foreach($table['attributes'] as $attribute) {
        $column_name = $attribute['name_as_column'];
        $name_ucfirst = $attribute['name_ucfirst'];

        if ($first) {
            $columns .= "          {$column_name}" . PHP_EOL;
            $variables .= "          %s" . PHP_EOL;
            $first = !$first;
        } else {
            $columns .= "        , {$column_name}" . PHP_EOL;
            $variables .= "         , %s" . PHP_EOL;
        }

        $values .= "        , nulo({$object}->get{$name_ucfirst}()) " . PHP_EOL;

    }

    $text .= "    public function getLista{$className}() {" . PHP_EOL;
    $text .= "        \$qry = sprintf(\"" . PHP_EOL;
    $text .= "            SELECT {$columns}" . PHP_EOL;
    $text .= "            FROM $full_table_name" . PHP_EOL;
    $text .= "            \"" . PHP_EOL;
    $text .= "        );" . PHP_EOL;
    $text .= "    \$result = \$this->select(\$qry);" . PHP_EOL;
    $text .= "    return \$result;" . PHP_EOL;
    $text .= "    }" . PHP_EOL;

    fwrite($handle, $text);
}
