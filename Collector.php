<?php
/**
 * @package Collector
 * @version 0.0.0
 */
/*
Plugin Name: Collector
Plugin URI: https://github.com/seanmorris/collector
Description: Packages your WordPress install and sends it to Playground.
Author: Sean Morris
Version: 0.0.0
Author URI: https://github.com/seanmorris/
*/

const COLLECTOR_PLAYGROUND_FLAG = '/tmp/690013d3-b53b-43f2-8371-b293a3bdc4fb';
const COLLECTOR_DOWNLOAD_PATH   = '/wp-admin/?page=collector_download_package';
const COLLECTOR_FINAL_ZIP       = '/tmp/collector-package.zip';

define('COLLECTOR_PLAYGROUND_URL', ($_SERVER['SERVER_NAME'] === 'localhost')
	? 'http://localhost:5400/website-server/'
	: 'https://playground.wordpress.net/'
);

define('COLLECTOR_WP_VERSION', $wp_version);
define('COLLECTOR_PHP_VERSION', implode('.',sscanf(phpversion(), '%d.%d')));

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
	<iframe id = "wp-playground" src = "<?=COLLECTOR_PLAYGROUND_URL;?>?url=/wp-admin/&wp=<?=COLLECTOR_WP_VERSION;?>&php=<?=COLLECTOR_PHP_VERSION;?>"></iframe>
	<iframe id = "wp-playground-loader" srcdoc = "<?=htmlentities(collector_get_preloader('Initializing Environment'));?>"></iframe>
	<script type = "text/javascript">
		const loader = document.getElementById('wp-playground-loader');
		const frame  = document.getElementById('wp-playground');
		const zipUrl = <?=json_encode(COLLECTOR_DOWNLOAD_PATH);?>;

		const username = <?=json_encode(wp_get_current_user()->user_login);?>;
		const fakepass = <?=json_encode(collector_get_fakepass());?>;
		const pluginUrl = new URLSearchParams(window.location.search).get('pluginUrl');
        const pluginName = new URLSearchParams(window.location.search).get('pluginName');
		const fetchZip = fetch(zipUrl);
		const fetchPlugin = fetch(pluginUrl);

		const fetchPreload  = fetch('data:text/html;base64,<?=base64_encode(collector_get_preloader('Loading Resources'));?>');
		const fetchPostload = fetch('data:text/html;base64,<?=base64_encode(collector_get_preloader('Activating Plugin'));?>');

		(async () => {
			const preloader = await (await fetchPreload).arrayBuffer();
			const postloader = await (await fetchPostload).arrayBuffer();

			frame.addEventListener('load', () => {
				frame.contentWindow.postMessage(
					{type :'collector-init', preloader},
					new URL('<?=COLLECTOR_PLAYGROUND_URL?>').origin,
					[structuredClone(preloader)],
				);
			}, {once: true});

			window.addEventListener('message', event => {
				if(event?.data?.type !== 'preview-service-listening')
				{
					return;
				}
				Promise.all([fetchZip, fetchPlugin])
				.then(r => Promise.all(r.map(rr => rr.arrayBuffer())))
				.then(([zipPackage, plugin]) => {
					frame.contentWindow.postMessage(
						{zipPackage, plugin, preloader, postloader, pluginName, username, fakepass, type:'collector-zip-package'},
						new URL('<?=COLLECTOR_PLAYGROUND_URL?>').origin,
						[zipPackage, plugin, preloader, postloader]
					);
					loader.remove();
				}, {once: true});
			}, {once: true});
		})();
    </script>
    <a href = "<?=COLLECTOR_DOWNLOAD_PATH;?>">Download Zip</a>
    <style type = "text/css">
        #wpbody-content, #wpcontent { padding-left: 0px !important; }
        #wpwrap, #wpbody, #wpbody-content {padding-bottom: 0px; height: 100%;}
        #wpbody-content { position: relative; }
        #wp-playground, #wp-playground-loader {
			position: absolute; top: 0; left: 0; width:100%; height:100%; z-index:999; background-color: #FFF;
		}
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
