<?php
function collector_restore_backup()
{
	if(!file_exists(COLLECTOR_PLAYGROUND_FLAG))
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

		if(substr($file, -6) === '.jsonl')
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
		$table = substr(basename($recordFile), 0, -6);
		$handle = fopen($recordFile, 'r');
		$buffer = '';

		while($bytes = fgets($handle))
		{
			$buffer .= $bytes;

			if(substr($buffer, -1, 1) !== "\n")
			{
				continue;
			}

			$record = json_decode($buffer, JSON_OBJECT_AS_ARRAY);
			$buffer = '';

			$wpdb->query(sprintf(
				'INSERT INTO `%s` (%s) VALUES (%s)',
				$table,
				implode(', ', array_map(fn($f) => "`$f`", array_keys($record))),
				implode(', ', array_map(fn($f) => "'$f'", array_values($record))),
			));
		}

		fclose($handle);
		unlink($recordFile);
	}

	rmdir('/wordpress/schema');
}
