<?php
/**
 * Settings Form - pro_features Group
 *
 * @package Click_To_Chat
 * @subpackage admin
 * @since 4.41
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Settings_Pro_Features' ) ) {
	/**
	 * Pro features settings class.
	 */
	class HT_CTC_Settings_Pro_Features {
		/**
		 * Retrieve fields settings for the settings page.
		 *
		 * @return array
		 */
		public static function fields() {

			// Using output buffering to grab the isolated layout's html
			ob_start();
			HT_CTC_Utils::load_file( 'new/admin2/views/panels/pro-features.php' );
			$html_content = ob_get_clean();

			$value = array(
				array(
					'field_type' => 'block_raw_html',
					'content'    => $html_content,
				),
			);

			return $value;
		}
	}
}
