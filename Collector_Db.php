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

	// Process in reverse order so wp_users comes before wp_options
	// meaning the fakepass will be cleared before transients are
	// dumped to the schema backup in the zip
	foreach(array_reverse($tables) as $table)
	{
		$recordFile = collector_get_tmpfile($table, 'jsonl');
		$recordList = collector_dump_db_records($table);

		while($record = $recordList->fetch_assoc())
		{
			if($table === 'wp_users' && (int) $record['ID'] === (int) wp_get_current_user()->ID)
			{
				$record['user_pass'] = wp_hash_password(collector_use_fakepass());
			}

			file_put_contents($recordFile, json_encode($record) . "\n", FILE_APPEND);
		}

		$zip->addFile($recordFile, 'schema/' . $table . '.jsonl');

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
	->query(sprintf('SELECT * FROM `%s`', $mysqli->real_escape_string($table)));
}
