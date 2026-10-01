<?php
/**
 * Opérations sur l'arbre d'éléments Elementor (tableau décodé de _elementor_data).
 * Les éléments sont repérés par leur ID Elementor (data-id visible dans le HTML du site).
 */

defined( 'ABSPATH' ) || exit;

class Evo_Tree {

	/** Clés de réglages contenant du texte affiché (et jamais une URL). */
	const TEXT_KEYS = [
		'title', 'editor', 'text', 'title_text', 'description_text', 'testimonial_content', 'testimonial_name',
		'testimonial_job', 'ekit_heading_title', 'ekit_heading_sub_title', 'ekit_heading_extra_title', 'caption',
		'mf_input_label', 'mf_input_placeholder', 'mf_input_help_text', 'mf_btn_text', 'mf_gdpr_consent_option_text',
		'mf_input_option_text', 'label', 'tab_title', 'tab_content', 'alert_title', 'alert_description',
	];

	/** Clés contenant du HTML (on ne transforme que le texte hors balises). */
	const HTML_KEYS = [ 'editor', 'description_text', 'testimonial_content', 'tab_content', 'alert_description', 'ekit_heading_title', 'title' ];

	private static $null = null;

	/**
	 * Référence vers l'élément $id (ou null).
	 */
	public static function &get( array &$els, $id ) {
		foreach ( $els as &$el ) {
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $id ) {
				return $el;
			}
			if ( ! empty( $el['elements'] ) ) {
				$found = &self::get( $el['elements'], $id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		unset( $el );
		self::$null = null;
		return self::$null;
	}

	public static function has( array $els, $id ) {
		$copy = $els;
		return null !== self::get( $copy, $id );
	}

	/** Appelle $cb( &$el, $parent_id ) sur chaque élément. */
	public static function walk( array &$els, callable $cb, $parent_id = null ) {
		foreach ( $els as &$el ) {
			$cb( $el, $parent_id );
			if ( ! empty( $el['elements'] ) ) {
				self::walk( $el['elements'], $cb, $el['id'] ?? null );
			}
		}
		unset( $el );
	}

	/** Liste (copies) des éléments qui satisfont $pred( $el ). */
	public static function find_all( array $els, callable $pred ) {
		$out = [];
		self::walk(
			$els,
			function ( &$el ) use ( $pred, &$out ) {
				if ( $pred( $el ) ) {
					$out[] = $el;
				}
			}
		);
		return $out;
	}

	public static function parent_id( array $els, $id, $parent = null ) {
		foreach ( $els as $el ) {
			if ( (string) ( $el['id'] ?? '' ) === (string) $id ) {
				return $parent;
			}
			if ( ! empty( $el['elements'] ) ) {
				$p = self::parent_id( $el['elements'], $id, $el['id'] );
				if ( false !== $p ) {
					return $p;
				}
			}
		}
		return false;
	}

	/** Retire l'élément $id ; renvoie l'élément retiré ou null. */
	public static function remove( array &$els, $id ) {
		foreach ( $els as $i => &$el ) {
			if ( (string) ( $el['id'] ?? '' ) === (string) $id ) {
				$removed = $el;
				unset( $el );
				array_splice( $els, $i, 1 );
				return $removed;
			}
			if ( ! empty( $el['elements'] ) ) {
				$r = self::remove( $el['elements'], $id );
				if ( null !== $r ) {
					return $r;
				}
			}
		}
		unset( $el );
		return null;
	}

	/**
	 * Insère $new relativement à $target_id : before | after | prepend | append (dans les enfants).
	 */
	public static function insert( array &$els, $target_id, array $new, $position = 'after' ) {
		foreach ( $els as $i => &$el ) {
			if ( (string) ( $el['id'] ?? '' ) === (string) $target_id ) {
				if ( 'before' === $position ) {
					array_splice( $els, $i, 0, [ $new ] );
				} elseif ( 'after' === $position ) {
					array_splice( $els, $i + 1, 0, [ $new ] );
				} elseif ( 'prepend' === $position ) {
					$el['elements'] = array_merge( [ $new ], $el['elements'] ?? [] );
				} else {
					$el['elements']   = $el['elements'] ?? [];
					$el['elements'][] = $new;
				}
				return true;
			}
			if ( ! empty( $el['elements'] ) && self::insert( $el['elements'], $target_id, $new, $position ) ) {
				return true;
			}
		}
		unset( $el );
		return false;
	}

	public static function move( array &$els, $id, $target_id, $position = 'after' ) {
		if ( ! self::has( $els, $id ) || ! self::has( $els, $target_id ) ) {
			return false;
		}
		$el = self::remove( $els, $id );
		return self::insert( $els, $target_id, $el, $position );
	}

	public static function replace( array &$els, $id, array $new ) {
		if ( (string) ( $new['id'] ?? '' ) === (string) $id ) {
			$ref = &self::get( $els, $id );
			if ( null === $ref ) {
				return false;
			}
			$ref = $new;
			return true;
		}
		if ( ! self::insert( $els, $id, $new, 'before' ) ) {
			return false;
		}
		self::remove_second( $els, $id );
		return true;
	}

	/** Après un replace, l'ancien élément suit le nouveau : on le retire. */
	private static function remove_second( array &$els, $id ) {
		foreach ( $els as $i => &$el ) {
			if ( (string) ( $el['id'] ?? '' ) === (string) $id ) {
				unset( $el );
				array_splice( $els, $i, 1 );
				return true;
			}
			if ( ! empty( $el['elements'] ) && self::remove_second( $el['elements'], $id ) ) {
				return true;
			}
		}
		unset( $el );
		return false;
	}

	/** Copie profonde avec de nouveaux IDs (uniques). */
	public static function clone_el( array $el ) {
		$el['id'] = self::new_id();
		if ( ! empty( $el['settings'] ) ) {
			$el['settings'] = self::renew_repeater_ids( $el['settings'] );
		}
		if ( ! empty( $el['elements'] ) ) {
			foreach ( $el['elements'] as $k => $child ) {
				$el['elements'][ $k ] = self::clone_el( $child );
			}
		}
		return $el;
	}

	private static function renew_repeater_ids( $settings ) {
		foreach ( $settings as $k => $v ) {
			if ( is_array( $v ) && isset( $v[0] ) && is_array( $v[0] ) ) {
				foreach ( $v as $i => $item ) {
					if ( isset( $item['_id'] ) ) {
						$settings[ $k ][ $i ]['_id'] = substr( self::new_id(), 0, 7 );
					}
				}
			}
		}
		return $settings;
	}

	public static function new_id() {
		static $seen = [];
		do {
			$id = substr( md5( wp_generate_uuid4() ), 0, 7 );
		} while ( isset( $seen[ $id ] ) || ctype_digit( $id ) );
		$seen[ $id ] = true;
		return $id;
	}

	public static function container( array $settings = [], array $children = [], $inner = true ) {
		return [
			'id'       => self::new_id(),
			'elType'   => 'container',
			'isInner'  => $inner,
			'settings' => array_merge( [ 'content_width' => 'full' ], $settings ),
			'elements' => $children,
		];
	}

	public static function widget( $type, array $settings = [] ) {
		return [
			'id'         => self::new_id(),
			'elType'     => 'widget',
			'isInner'    => false,
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => [],
		];
	}

	/** Ajoute des classes CSS (_css_classes pour un widget, css_classes pour un conteneur). */
	public static function add_class( array &$el, $classes ) {
		$key     = ( 'widget' === ( $el['elType'] ?? '' ) ) ? '_css_classes' : 'css_classes';
		$current = preg_split( '/\s+/', trim( (string) ( $el['settings'][ $key ] ?? '' ) ) );
		foreach ( preg_split( '/\s+/', trim( $classes ) ) as $c ) {
			if ( $c && ! in_array( $c, $current, true ) ) {
				$current[] = $c;
			}
		}
		$el['settings'][ $key ] = trim( implode( ' ', array_filter( $current ) ) );
	}

	public static function has_class( array $el, $class ) {
		$key = ( 'widget' === ( $el['elType'] ?? '' ) ) ? '_css_classes' : 'css_classes';
		return in_array( $class, preg_split( '/\s+/', (string) ( $el['settings'][ $key ] ?? '' ) ), true );
	}

	public static function link( $url, $external = false ) {
		return [ 'url' => $url, 'is_external' => $external ? 'on' : '', 'nofollow' => '', 'custom_attributes' => '' ];
	}

	/** Texte visible d'un widget (pour les heuristiques). */
	public static function plain_text( array $el ) {
		$s = $el['settings'] ?? [];
		foreach ( [ 'title', 'editor', 'text', 'title_text', 'ekit_heading_title' ] as $k ) {
			if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
				return trim( html_entity_decode( wp_strip_all_tags( $s[ $k ] ), ENT_QUOTES, 'UTF-8' ) );
			}
		}
		return '';
	}

	/**
	 * Applique $fn( string ) aux textes affichés d'un élément (y compris repeaters).
	 * Pour les clés HTML, $fn ne voit que le texte entre les balises.
	 */
	public static function map_texts( array &$settings, callable $fn ) {
		foreach ( $settings as $key => &$value ) {
			if ( is_string( $value ) && in_array( $key, self::TEXT_KEYS, true ) ) {
				$value = in_array( $key, self::HTML_KEYS, true ) ? self::map_html_text( $value, $fn ) : $fn( $value );
			} elseif ( is_array( $value ) && isset( $value[0] ) && is_array( $value[0] ) ) {
				foreach ( $value as &$item ) {
					if ( is_array( $item ) ) {
						self::map_texts( $item, $fn );
					}
				}
				unset( $item );
			}
		}
		unset( $value );
	}

	public static function map_html_text( $html, callable $fn ) {
		$parts = preg_split( '/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $html;
		}
		foreach ( $parts as $i => $part ) {
			if ( '' !== $part && '<' !== $part[0] ) {
				$parts[ $i ] = $fn( $part );
			}
		}
		return implode( '', $parts );
	}

	/** Applique $fn( &$link_array ) à tous les réglages de type lien (tableaux avec 'url'). */
	public static function map_links( array &$settings, callable $fn ) {
		foreach ( $settings as $key => &$value ) {
			if ( is_array( $value ) ) {
				if ( array_key_exists( 'url', $value ) && ! array_key_exists( 'id', $value ) && is_string( $value['url'] ) ) {
					$fn( $value, $key );
				} else {
					self::map_links( $value, $fn );
				}
			}
		}
		unset( $value );
	}

	/** Applique $fn( &$media_array ) aux réglages média (tableaux avec 'url' et 'id'). */
	public static function map_media( array &$settings, callable $fn ) {
		foreach ( $settings as $key => &$value ) {
			if ( is_array( $value ) ) {
				if ( array_key_exists( 'url', $value ) && array_key_exists( 'id', $value ) ) {
					$fn( $value, $key );
				} else {
					self::map_media( $value, $fn );
				}
			}
		}
		unset( $value );
	}

	/** Remplacement de chaîne dans toutes les valeurs texte (récursif), y compris URLs. */
	public static function replace_everywhere( $data, $search, $replace ) {
		if ( is_string( $data ) ) {
			return str_replace( $search, $replace, $data );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = self::replace_everywhere( $v, $search, $replace );
			}
		}
		return $data;
	}
}
