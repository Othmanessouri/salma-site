<?php
/**
 * Plugin Name: MetForm
 * Plugin URI: http://wpmet.com/plugin/metform/
 * Description: Most flexible and design friendly form builder for Elementor
 * Version: 4.3.0
 * Author: Wpmet
 * Author URI:  https://wpmet.com
 * Text Domain: metform
 * Domain Path: /languages
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 */

defined( 'ABSPATH' ) || exit;

define( 'METFORM_PLUGIN_FILE', __FILE__ );
define( 'METFORM_VERSION', get_file_data( METFORM_PLUGIN_FILE, [ 'version' => 'Version' ] )['version'] );  
define( 'METFORM_FREE_PATH', plugin_dir_path( METFORM_PLUGIN_FILE ));
define( 'METFORM_FREE_URL', plugin_dir_url( METFORM_PLUGIN_FILE ));

require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/notice/notice.php';
require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/banner/banner.php';
require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/apps/apps.php';
require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/emailkit/emailkit.php';
require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/stories/stories.php';
require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/pro-awareness/pro-awareness.php';
require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/rating/rating.php';
require_once plugin_dir_path( METFORM_PLUGIN_FILE ) . 'utils/feedback/plugin-unsubscribe.php';

require plugin_dir_path( METFORM_PLUGIN_FILE ) .'autoloader.php';
require plugin_dir_path( METFORM_PLUGIN_FILE ) .'plugin.php';

// init notice class
\Oxaim\Libs\Notice::init();
// \Wpmet\Rating\Rating::init();
\Wpmet\Libs\Pro_Awareness::init();


register_activation_hook( METFORM_PLUGIN_FILE, [ MetForm\Plugin::instance(), 'flush_rewrites'] );

add_action( 'plugins_loaded', function(){
    do_action('metform/before_load');
    MetForm\Plugin::instance()->init();
    do_action('metform/after_load');
}, 111);


// Added Date: 20/07/2022
add_action('plugins_loaded', function(){

    add_action('init', function(){

        if(class_exists('MetForm_Pro\Core\Integrations\Crm\Hubspot\Integration')){
        return;
    }
    require trailingslashit(plugin_dir_path(METFORM_PLUGIN_FILE)) . "core/integrations/crm/hubspot/loader.php";
    }, 20);

}, 222);
