<?php
namespace MetForm\Core\Integrations\Onboard;

use MetForm\Base\Assets_Enqueue;
use MetForm\Core\Integrations\Onboard\Classes\Utils;
use MetForm\Plugin;
use MetForm\Traits\Singleton;

defined( 'ABSPATH' ) || exit;

class Attr{

    use Singleton;

    public $utils;

    public static function get_dir(){
        return Plugin::instance()->core_dir() . 'integrations/onboard/';
    }

    public static function get_url(){
        return Plugin::instance()->core_url() . 'integrations/onboard/';
    }

    public function __construct() {

        $this->utils = Utils::instance();

        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts'] );
    }

    public function enqueue_scripts() {

        //onboard icon css
        Assets_Enqueue::get_style( 'metform-onboard-icon' );
        //onboard css
        Assets_Enqueue::get_style( 'metform-init-css-admin' );

        //onboard script
        Assets_Enqueue::get_script( 'mf-admin-core' );
        wp_set_script_translations( 'mf-admin-core', 'metform' );

        $data['rest_url']   = get_rest_url();
	    $data['nonce']      = wp_create_nonce('wp_rest');

	    wp_localize_script('mf-admin-core', 'rest_config', $data);

        wp_localize_script('mf-admin-core', 'mf_ajax_var', array(
            'nonce' => wp_create_nonce('ajax-nonce')
        ));
    }
}
