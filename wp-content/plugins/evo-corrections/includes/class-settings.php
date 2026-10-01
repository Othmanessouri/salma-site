<?php
/**
 * Coordonnées, fichiers « À fournir » et identifiants de suivi.
 * Tout ce qui n'est pas encore transmis par Salma reste vide : les éléments
 * correspondants (boutons, mentions RC/ICE, GA4) ne s'affichent pas tant qu'ils sont vides.
 */

defined( 'ABSPATH' ) || exit;

class Evo_Settings {

	const OPTION = 'evo_corrections_settings';

	public static function defaults() {
		return [
			'phone_display'      => '07 05 94 40 36',
			'phone_intl'         => '+212705944036',
			'whatsapp'           => '212705944036',
			'email'              => 'info@evoindustries.ma',
			'sales_email'        => 'info@evoindustries.ma',
			'address'            => 'Casablanca, Maroc',
			'hours'              => 'Lun–Ven, 9h–18h',
			'linkedin'           => 'https://www.linkedin.com/in/evoindustriesmorocco',
			'instagram'          => 'https://www.instagram.com/evoindustriesmorocco/',
			'company_rc'         => '',
			'company_ice'        => '',
			'catalogue_enzy'     => '',
			'cert_enzy-floor'    => '',
			'cert_enzy-carpet'   => '',
			'cert_enzy-degrese'  => '',
			'cert_enzy-power'    => '',
			'cert_enzy-glass'    => '',
			'ga4_id'             => '',
			'topbar_enabled'     => '1',
		];
	}

	public static function all() {
		$saved = get_option( self::OPTION, [] );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	public static function save( array $input ) {
		$clean = [];
		foreach ( self::defaults() as $key => $default ) {
			$value = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
			if ( in_array( $key, [ 'email', 'sales_email' ], true ) ) {
				$value = sanitize_email( $value );
			} elseif ( in_array( $key, [ 'linkedin', 'instagram', 'catalogue_enzy' ], true ) || 0 === strpos( $key, 'cert_' ) ) {
				$value = esc_url_raw( trim( $value ) );
			} elseif ( 'topbar_enabled' === $key ) {
				$value = $value ? '1' : '';
			} else {
				$value = sanitize_text_field( $value );
			}
			$clean[ $key ] = $value;
		}
		update_option( self::OPTION, $clean, false );
	}

	public static function whatsapp_url( $text = '' ) {
		$url = 'https://wa.me/' . preg_replace( '/\D+/', '', self::get( 'whatsapp' ) );
		if ( $text ) {
			$url .= '?text=' . rawurlencode( $text );
		}
		return $url;
	}

	/**
	 * Fichiers téléchargeables servis par /telechargement/{slug}/ (catalogue, certifications).
	 */
	public static function download_targets() {
		$targets = [ 'catalogue-enzy' => self::get( 'catalogue_enzy' ) ];
		foreach ( Evo_Migration::PRODUCTS as $slug => $label ) {
			$targets[ 'certification-' . $slug ] = self::get( 'cert_' . $slug );
		}
		return $targets;
	}
}
