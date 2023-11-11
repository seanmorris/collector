<?php
function collector_restore_backup()
{
    if(!file_exists('/tmp/690013d3-b53b-43f2-8371-b293a3bdc4fb'))
    {
        return;
    }

    if(!file_exists('/wordpress/schema'))
    {
        return;
    }

    $files = scandir('/wordpress/schema');

    $schemaFile  = null;
    $recordFiles = [];

    foreach($files as $file)
    {
        if($file === '.' || $file === '..')
        {
            continue;
        }

        if(substr($file, -4) === '.sql')
        {
            $schemaFile = '/wordpress/schema/' . $file;
        }

        if(substr($file, -5) === '.json')
        {
            $recordFiles[] = '/wordpress/schema/' . $file;
        }
    }

    $queries = explode("\n", file_get_contents($schemaFile));
    
    global $wpdb;
    foreach($queries as $query)
    {
        $wpdb->query($query);
    }

    foreach($recordFiles as $recordFile)
    {
        $table = substr(basename($recordFile), 0, -5);
        $records = json_decode(file_get_contents($recordFile), JSON_OBJECT_AS_ARRAY);

        foreach($records as $record)
        {
            $wpdb->query(sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $table,
                implode(', ', array_map(fn($f) => "`$f`", array_keys($record))),
                implode(', ', array_map(fn($f) => "'$f'", array_values($record))),
            ));
        }
    }

    rmdir('/wordpress/schema');
}