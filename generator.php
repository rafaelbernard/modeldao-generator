<?php

include 'functions.php';

// Get the arguments
argumentos($argv);

$output_directory = '/tmp';

if (isset($args['od'])) {
    $output_directory = $args['od'];
}

define('PATH_OUTPUT_DIRECTORY', $output_directory . '/gerador');

$server = isset($args['server']) ? $args['server'] : '';

if (!$server) {
    echo "Server: ";
    $server = fgets(STDIN);
}

if (trim($server) == '' || !isset($server))
    die('Informe server');

$dbname = isset($args['dbname']) ? $args['dbname'] : '';
$user = isset($args['user']) ? $args['user'] : die('Informe user');

echo "Password: ";
$password = preg_replace('/\r?\n$/', '', `stty -echo; head -n1 ; stty echo`);
echo "\n";
//echo "Your password was: {$password}.\n";

$connection_string = "host=$server port=5432 dbname=$dbname user=$user password=$password";
//echo "Connection string: $connection_string\n";

$connection = pg_connect($connection_string);
//var_dump($connection);

$result_tables = query_tables();

if ($result_tables)
{
    $string = '';

    $directory = PATH_OUTPUT_DIRECTORY;

    # handle output directory
    if(is_dir($directory)) {
        $now = time();
        rename($directory, "{$directory}.{$now}");
    }
    if (!is_dir($directory)) mkdir($directory);

    # handle form directory
    form_directoty_handle();

    $path_tables_file = "$directory/tables.txt";
    $handle = fopen($path_tables_file, "w");

    $schema_row = '';

    while ($data = pg_fetch_object($result_tables))
    {
        schema_directory_handle($data->schemaname);

        $namespace_name = ucfirst($data->schemaname);
        $class_name = to_class_name($data->tablename);

        $po_file = PATH_OUTPUT_DIRECTORY . "/po/{$data->schemaname}/{$class_name}.php";
        $po_file_handle = fopen($po_file, "w");

        $po_file_string = "<?php" . PHP_EOL . PHP_EOL;
        $po_file_string .= "{$data->tablename}" . PHP_EOL;
        $po_file_string .= PHP_EOL;

        $string = "\n=====\n";
        $string .= "tabela - {$data->schemaname}.{$data->tablename}\n";
        $string .= "=====\n\n";

        # `attnum` negativos sao colunas de sistema
        $query_attributes = sprintf("
            SELECT  *
            FROM    pg_catalog.pg_attribute
            WHERE   attrelid = %d
            AND     attnum > 0
            "
            , $data->tableoid
            );
        $result_tables_attributes = pg_query($query_attributes);

        if ($result_tables_attributes)
        {
            $string .= "Atributos:\n";
            $string .= "-----\n\n";

            $array_attributes_names = array();

            while ($data_attributes = pg_fetch_object($result_tables_attributes))
            {
                $string .= "- {$data_attributes->attname}\n";
                $array_attributes_names[] = $data_attributes->attname;
            }

            $string .= implode(',', $array_attributes_names)."\n";
        }

        fwrite($handle, $string);
        fwrite($po_file_handle, $po_file_string);
        fclose($po_file_handle);
    }

    fclose($handle);
    echo "Output directory: " . PATH_OUTPUT_DIRECTORY . PHP_EOL;
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
