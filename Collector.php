<?php
/**
 * @package Collector
 * @version 0.0.0
 */
/*
Plugin Name: Collector
Plugin URI:
Description:
Author: Sean Morris
Version: 0.0.0
Author URI:
*/

const COLLECTOR_DOWNLOAD_PATH  = '/wp-admin/?page=collector_download_package';
const COLLECTOR_FINAL_ZIP      = '/tmp/collector-package.zip';

define('COLLECTOR_PLAYGROUND_URL', ($_SERVER['SERVER_NAME'] === 'localhost')
    ? 'http://localhost:5400/website-server/'
    : 'https://playground.wordpress.net'
);

require __DIR__ . '/Collector_Content.php';
require __DIR__ . '/Collector_Db.php';
require __DIR__ . '/Collector_Helpers.php';
require __DIR__ . '/Collector_Zip.php';

add_action('admin_menu', 'collector_plugin_top_menu');
add_action('plugins_loaded', 'collector_plugins_loaded');
register_activation_hook(__FILE__, 'collector_restore_backup');

function collector_plugins_loaded()
{
    if(!current_user_can('manage_options'))
    {
        return;
    }

    if(urldecode($_SERVER['REQUEST_URI']) === COLLECTOR_DOWNLOAD_PATH)
    {
        collector_zip_collect();
        collector_zip_send();
        collector_zip_delete();
        exit();
    }
}

function collector_plugin_top_menu()
{
    add_submenu_page(
        NULL,
        'Collector',
        'Collector',
        'manage_options',
        'collector_render_plugin_page',
        'collector_render_plugin_page',
        NULL
    );
}

function collector_render_plugin_page()
{?>
    <iframe id = "wp-playground" style = "width:100%;height:100%;" src = "<?=COLLECTOR_PLAYGROUND_URL;?>?url=/wp-admin/"></iframe>
    <script type = "text/javascript">
        const frame = document.getElementById('wp-playground');
        const zipUrl = '<?=COLLECTOR_DOWNLOAD_PATH;?>';
        const pluginUrl = new URLSearchParams(window.location.search).get('pluginUrl');
        const pluginName = new URLSearchParams(window.location.search).get('pluginName');
        frame.addEventListener('load', event => {
            fetch(zipUrl)
            .then(r=>r.arrayBuffer())
            .then(zip=>frame.contentWindow.postMessage(
                {zip, pluginUrl, pluginName, type:'collector-zip-package'}, new URL('<?=COLLECTOR_PLAYGROUND_URL?>').origin, [zip]
            ));
        });
    </script>
    <a href = "<?=COLLECTOR_DOWNLOAD_PATH;?>">Download Zip</a>
    <style type = "text/css">
        #wpbody-content, #wpcontent { padding: 0px; }
        #wpwrap, #wpbody, #wpbody-content {padding-bottom: 0px; height: 100%;}
    </style>
<?php
}

function collector_restore_backup()
{
    var_dump(file_exists('/tmp/690013d3-b53b-43f2-8371-b293a3bdc4fb'));

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

    global $wpdb;
    $queries = explode("\n", file_get_contents($schemaFile));

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