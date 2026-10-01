<?php

namespace MetForm\Core\Integrations\Onboard\Classes;

defined( 'ABSPATH' ) || exit;

class Auto_Install_Notice {

	const DISMISSED_KEY       = 'metform_auto_install_notice_dismissed';
	const EMAIL_COLLECTED_KEY = 'wpmet_onboard_email_collected';

	public static function init(): void {
		add_action( 'admin_notices', [ __CLASS__, 'render' ] );
		add_action( 'wp_ajax_metform_dismiss_auto_install_notice', [ __CLASS__, 'handle_dismiss' ] );
		add_action( 'wp_ajax_metform_confirm_auto_install_notice', [ __CLASS__, 'handle_confirm' ] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_option( self::DISMISSED_KEY ) ) {
			return;
		}

		if ( \MetForm\Utils\Util::get_settings( 'metform_user_consent_for_banner', 'yes' ) !== 'yes' ) {
			return;
		}

		// Only show when MetForm was auto-installed by another wpmet plugin.
		$registry       = Auto_Install_Tracker::get_registry();
		$auto_installed = isset( $registry['metform/metform.php'] );

		if ( ! $auto_installed ) {
			return;
		}

		// Only show on the MetForm Forms list page and the Get Help page.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$screen       = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type    = $screen ? (string) $screen->post_type : '';
		$is_allowed_page = ( $post_type === 'metform-form' ) || ( $current_page === 'metform_get_help' );
		if ( ! $is_allowed_page ) {
			return;
		}

		$nonce = wp_create_nonce( 'metform_auto_install_notice_nonce' );
		?>
		<div class="notice notice-info wpmet-metform-auto-install-notice" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; min-height: 104px; box-sizing: border-box; margin: 5px 0 15px; padding: 16px 20px; border-left: 4px solid #E91E8C;">
			<div style="flex: 1 1 auto;">
				<h3 style="margin: 0 0 6px; font-family: Inter, sans-serif; font-size: 24px; font-weight: 700; line-height: 24px; letter-spacing: 0;">
					<?php esc_html_e( 'Get more from your new plugins 🚀', 'metform' ); ?>
				</h3>
				<p style="margin: 0; color: #50575E; font-family: Inter, sans-serif; font-weight: 500; font-size: 16px; line-height: 24px; letter-spacing: 0;">
					<?php esc_html_e( 'Allow us to use non-sensitive data from your site to improve our products and get personalized performance tips.', 'metform' ); ?>
				</p>
			</div>
			<div style="flex: 0 0 auto; display: flex; gap: 10px;">
				<button class="button wpmet-metform-notice-btn-skip" id="wpmet-metform-notice-skip" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Skip for Now', 'metform' ); ?>
				</button>
				<button class="button button-primary wpmet-metform-notice-btn-confirm" id="wpmet-metform-notice-confirm" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Confirm & Apply', 'metform' ); ?>
				</button>
			</div>
		</div>
		<style>
			@property --angle {
				syntax: '<angle>';
				initial-value: 162.14deg;
				inherits: false;
			}
			#wpmet-metform-notice-skip,
			#wpmet-metform-notice-confirm {
				display: flex;
				align-items: center;
				justify-content: center;
				min-height: unset;
				line-height: unset;
				box-sizing: border-box;
				padding: 9px 20px;
				border-radius: 8px;
				flex: none;
				font-size: 13px;
				white-space: nowrap;
				cursor: pointer;
			}
			#wpmet-metform-notice-skip {
				background: #fff;
				border: 1px solid #d7d7db;
				color: #000;
				transition: background 250ms ease, border-color 250ms ease, color 250ms ease;
			}
			#wpmet-metform-notice-skip:hover {
				background: #dbeafe;
				border: 1px solid #dbeafe;
				color: #000;
			}
			#wpmet-metform-notice-confirm {
				position: relative;
				overflow: hidden;
				isolation: isolate;
				--angle: 162.14deg;
				background: linear-gradient(var(--angle), #4371F8 7.19%, #2B58DE 44.76%);
				border: none;
				color: #fff;
			}
			#wpmet-metform-notice-confirm::after {
				content: "";
				position: absolute;
				top: -60%;
				right: -10%;
				width: 65%;
				height: 200%;
				background: rgba(255, 255, 255, 0.10);
				border-radius: 60% 0 0 10%;
				transform: rotate(20deg);
				transform-origin: bottom left;
				z-index: -1;
				pointer-events: none;
				transition: top 300ms ease, right 300ms ease, opacity 300ms ease;
			}
			#wpmet-metform-notice-confirm:hover::after {
				top: -70%;
				right: 0%;
				opacity: 0.7;
			}
		</style>
		<script>
		(function($) {
			$('#wpmet-metform-notice-skip').on('click', function() {
				$.post(ajaxurl, {
					action: 'metform_dismiss_auto_install_notice',
					nonce:  $(this).data('nonce')
				});
				$('.wpmet-metform-auto-install-notice').fadeOut(300, function() { $(this).remove(); });
			});

			$('#wpmet-metform-notice-confirm').on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Applying…', 'metform' ) ); ?>');
				$.post(ajaxurl, {
					action: 'metform_confirm_auto_install_notice',
					nonce:  $btn.data('nonce')
				}, function() {
					$('.wpmet-metform-auto-install-notice').fadeOut(300, function() { $(this).remove(); });
				});
			});
		}(jQuery));
		</script>
		<?php
	}

	public static function handle_dismiss(): void {
		check_ajax_referer( 'metform_auto_install_notice_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		update_option( self::DISMISSED_KEY, true, false );
		wp_send_json_success();
	}

	public static function handle_confirm(): void {
		check_ajax_referer( 'metform_auto_install_notice_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$stored_email = get_option( 'wpmet_onboard_collected_email' );
		$email        = $stored_email
			? sanitize_email( $stored_email )
			: sanitize_email( get_option( 'admin_email' ) );

		Plugin_Data_Sender::instance()->sendEmailSubscribeData( 'plugin-subscribe', [
			'email' => $email,
			'slug'  => 'metform',
		] );

		// Also send for auto-installed plugins that don't have their own notice banner.
		$registry = Auto_Install_Tracker::get_registry();
		$slug_map = [
			'elementskit-lite/elementskit-lite.php'           => 'elementskit-lite',
			'emailkit/EmailKit.php'                           => 'emailkit',
			'gutenkit-blocks-addon/gutenkit-blocks-addon.php' => 'gutenkit-blocks-addon',
			'getgenie/getgenie.php'                           => 'getgenie',
			'popup-builder-block/popup-builder-block.php'     => 'popupkit',
		];
		foreach ( array_keys( $registry ) as $plugin_file ) {
			if ( isset( $slug_map[ $plugin_file ] ) ) {
				Plugin_Data_Sender::instance()->sendEmailSubscribeData( 'plugin-subscribe', [
					'email' => $email,
					'slug'  => $slug_map[ $plugin_file ],
				] );
			}
		}

		update_option( self::EMAIL_COLLECTED_KEY, true, false );
		update_option( self::DISMISSED_KEY, true, false );
		wp_send_json_success();
	}
}
