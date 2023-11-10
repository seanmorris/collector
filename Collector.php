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
	: 'https://playground.wordpress.net/'
);

define('COLLECTOR_WP_VERSION', $wp_version);

require __DIR__ . '/Collector_Content.php';
require __DIR__ . '/Collector_Db.php';
require __DIR__ . '/Collector_Helpers.php';
require __DIR__ . '/Collector_Restore.php';
require __DIR__ . '/Collector_Zip.php';

add_action('admin_menu', 'collector_plugin_top_menu');
add_action('plugins_loaded', 'collector_plugins_loaded');
add_filter('plugin_install_action_links', 'collector_plugin_install_action_links', 10, 2);

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
        'collector_render_playground_page',
        'collector_render_playground_page',
        NULL
    );
}

function collector_render_playground_page()
{?>
    <iframe
		id = "wp-playground"
		src = "<?=COLLECTOR_PLAYGROUND_URL;?>?url=/wp-admin/&wp=<?=COLLECTOR_WP_VERSION;?>"
		frameBorder = "0"
	></iframe>
    <script type = "text/javascript">
        const frame = document.getElementById('wp-playground');
        const zipUrl = '<?=COLLECTOR_DOWNLOAD_PATH;?>';
        const pluginUrl = new URLSearchParams(window.location.search).get('pluginUrl');
        const pluginName = new URLSearchParams(window.location.search).get('pluginName');
        frame.addEventListener('load', event => {
            fetch(zipUrl)
            .then(r=>r.arrayBuffer())
            .then(zipPackage => frame.contentWindow.postMessage(
                {zipPackage, pluginUrl, pluginName, type:'collector-zip-package'},
                new URL('<?=COLLECTOR_PLAYGROUND_URL?>').origin,
                [zipPackage]
            ));
        });
    </script>
    <a href = "<?=COLLECTOR_DOWNLOAD_PATH;?>">Download Zip</a>
    <style type = "text/css">
        #wpbody-content, #wpcontent { padding: 0px; }
        #wpwrap, #wpbody, #wpbody-content {padding-bottom: 0px; height: 100%;}
        #wpbody-content { position: relative; }
        #wp-playground { position: absolute; top: 0; left: 0; width:100%; height:100%; z-index:999; background-color: #FFF; }
    </style>
<?php
}

function collector_plugin_install_action_links($action_links, $plugin)
{
    $preview_button = sprintf(
        '<a class="preview-now button" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
        esc_attr( $plugin['slug'] ),
        '/wp-admin/admin.php?page=collector_render_playground_page&pluginUrl=' . esc_url( $plugin['download_link'] ) . '&pluginName=' . esc_attr( $plugin['slug'] ),
        /* translators: %s: Plugin name and version. */
        esc_attr( sprintf( _x( 'Install %s now', 'plugin' ), $plugin['name'] ) ),
        esc_attr( $plugin['name'] ),
        __( 'Preview Now' )
    );

    array_unshift($action_links, $preview_button);

    return $action_links;
}
