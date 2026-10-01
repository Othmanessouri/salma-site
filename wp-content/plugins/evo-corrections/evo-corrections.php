<?php
/**
 * Plugin Name:       EVO Corrections
 * Description:       Applique la liste de corrections de Salma (30/09/2026) sur evoindustries.ma sans changer le design : textes, liens, formulaires, header/footer, SEO, sécurité. Outils > Corrections EVO.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            EVO Industries
 * Text Domain:       evo-corrections
 */

defined( 'ABSPATH' ) || exit;

define( 'EVO_CORR_VERSION', '1.0.1' );
define( 'EVO_CORR_FILE', __FILE__ );
define( 'EVO_CORR_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVO_CORR_URL', plugin_dir_url( __FILE__ ) );

require_once EVO_CORR_DIR . 'includes/class-settings.php';
require_once EVO_CORR_DIR . 'includes/class-tree.php';
require_once EVO_CORR_DIR . 'includes/class-store.php';
require_once EVO_CORR_DIR . 'includes/class-migration.php';
require_once EVO_CORR_DIR . 'includes/steps-global.php';
require_once EVO_CORR_DIR . 'includes/steps-pages.php';
require_once EVO_CORR_DIR . 'includes/steps-forms.php';
require_once EVO_CORR_DIR . 'includes/steps-site.php';
require_once EVO_CORR_DIR . 'includes/class-frontend.php';
require_once EVO_CORR_DIR . 'includes/class-seo.php';
require_once EVO_CORR_DIR . 'includes/class-security.php';
require_once EVO_CORR_DIR . 'includes/class-admin.php';

Evo_Frontend::init();
Evo_Seo::init();
Evo_Security::init();
Evo_Admin::init();

register_activation_hook( __FILE__, [ 'Evo_Security', 'flush_on_activation' ] );
