<?php

include 'functions.php';
// alimenta os argumentos
argumentos($argv);

$server = isset($args['server']) ? $args['server'] : '';

if (!$server) {
    echo "Server: ";
    $server = fgets(STDIN);
}

if (trim($server) == '' || !isset($server))
    die('Informe server');

//$server = isset($args['server']) ? $args['server'] : die('Informe server');
$dbname = isset($args['dbname']) ? $args['dbname'] : '';
$user = isset($args['user']) ? $args['user'] : die('Informe user');

echo "Password: ";
$password = preg_replace('/\r?\n$/', '', `stty -echo; head -n1 ; stty echo`);
echo "\n";
echo "Your password was: {$password}.\n";

$connection_string = "host=$server port=5432 dbname=$dbname user=$user password=$password";
echo "Connection string: $connection_string\n";

$connection = pg_connect($connection_string);
var_dump($connection);

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
    ";
$result = pg_query($query_tables);

if ($result)
{
    $string = '';

    $directory = '/tmp/gerador';
    if (!is_dir($directory)) mkdir($directory);
    $path_tables_file = "$directory/tables.txt";
    $handle = fopen($path_tables_file, "w");

    while ($data = pg_fetch_object($result))
    {
        //$string = dvd($data, true)."\n";
        //$string = print_r($data, true)."\n";
        $string .= "\n=====\n";
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
        $result_attributes = pg_query($query_attributes);

        if ($result_attributes)
        {
            $string .= "Atributos:\n";
            $string .= "-----\n\n";

            $array_attributes_names = array();

            while ($data_attributes = pg_fetch_object($result_attributes))
            {
                $string .= "- {$data_attributes->attname}\n";
                $array_attributes_names[] = $data_attributes->attname;
            }

            $string .= implode(',', $array_attributes_names)."\n";
        }

        fwrite($handle, $string);
    }

    fclose($handle);
}


// $dsn = "postgres:host=$server";
//
// $pdo = new PDO($dsn);
// var_dump($pdo);
