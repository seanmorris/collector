<?php
function collector_dump_wp_content($zip)
{
	collector_zip_add_directory($zip, ABSPATH . '/' . 'wp-content/');
}

function collector_zip_add_directory($zip, $path)
{
	$handle = opendir($path);

	while($entry = readdir($handle))
	{
		if($entry === '.' || $entry === '..')
		{
			continue;
		}

		$realPath = realpath($path . '/' . $entry);
		$packPath = substr($realPath, strlen(ABSPATH));

		if(is_file($realPath))
		{
			$zip->addFile($realPath, $packPath);
		}
		else if(is_dir($realPath))
		{
			$zip->addEmptyDir($packPath);

			collector_zip_add_directory($zip, $realPath);
		}
	}
}
