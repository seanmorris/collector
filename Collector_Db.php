<?php
function collector_dump_db($zip)
{
    $tables   = collector_get_db_tables();
    $sqlFile  = collector_get_tmpfile('schema', 'sql');    
    $tmpFiles = [$sqlFile];

    foreach($tables as $table)
    {
        file_put_contents($sqlFile, sprintf("DROP TABLE IF EXISTS `%s`;\n", $table), FILE_APPEND);
        file_put_contents($sqlFile, preg_replace("/\s+/", " ", collector_dump_db_schema($table)) . "\n", FILE_APPEND);
    }
    
    $zip->addFile($sqlFile, 'schema/_Schema.sql');

    foreach($tables as $table)
    {
        $recordFile = collector_get_tmpfile($table, 'json');
        $recordList = collector_dump_db_records($table);

        file_put_contents($recordFile, json_encode($recordList, JSON_PRETTY_PRINT));
        $zip->addFile($recordFile, 'schema/' . $table . '.json');

        $tmpFiles[] = $recordFile;
    }

    return $tmpFiles;
}

function collector_get_db()
{
    static $mysqli;
    if(!$mysqli)
    {
        $mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
        mysqli_select_db($mysqli, DB_NAME);
    }

    return $mysqli;
}

function collector_get_db_tables()
{
    $mysqli = collector_get_db();
    $query  = $mysqli->query('SHOW TABLES');
    $tables = $query->fetch_all();

    return array_map(fn($t) => $t[0], $tables);
}

function collector_dump_db_schema($table)
{
    $mysqli = collector_get_db();
    return $mysqli
    ->query(sprintf('SHOW CREATE TABLE `%s`', $mysqli->real_escape_string($table)))
    ->fetch_object()
    ->{'Create Table'};
}

function collector_dump_db_records($table)
{
    $mysqli = collector_get_db();
    return $mysqli
    ->query(sprintf('SELECT * FROM `%s`', $mysqli->real_escape_string($table)))
    ->fetch_all(MYSQLI_ASSOC);
}
