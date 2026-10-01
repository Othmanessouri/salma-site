<?php
/**
 * Lecture / écriture des données Elementor avec sauvegarde avant toute modification,
 * suivi de ce qui est créé, et restauration complète.
 */

defined( 'ABSPATH' ) || exit;

class Evo_Store {

	const OPT_BACKUP_OPTIONS = 'evo_corrections_backup_options';
	const OPT_CREATED        = 'evo_corrections_created';
	const OPT_MENU_BACKUP    = 'evo_corrections_backup_menu';
	const META_BACKUP_DATA   = '_evo_backup_elementor_data';
	const META_BACKUP_POST   = '_evo_backup_post';
	const META_BACKUP_META   = '_evo_backup_meta';

	public $dry = false;

	private $data  = [];
	private $dirty = [];

	public function exists( $post_id ) {
		return $post_id > 0 && get_post( $post_id ) && '' !== (string) get_post_meta( $post_id, '_elementor_data', true );
	}

	/** Référence vers l'arbre Elementor du post (chargé une fois). */
	public function &els( $post_id ) {
		if ( ! isset( $this->data[ $post_id ] ) ) {
			$raw  = $post_id > 0 ? get_post_meta( $post_id, '_elementor_data', true ) : '';
			$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
			$this->data[ $post_id ] = is_array( $data ) ? $data : [];
		}
		return $this->data[ $post_id ];
	}

	public function set( $post_id, array $els ) {
		$this->data[ $post_id ] = $els;
		$this->mark( $post_id );
	}

	public function mark( $post_id ) {
		$this->dirty[ $post_id ] = true;
	}

	public function loaded_ids() {
		return array_keys( $this->data );
	}

	/** Enregistre les arbres modifiés (après sauvegarde de l'original). */
	public function flush() {
		$saved = [];
		foreach ( array_keys( $this->dirty ) as $post_id ) {
			if ( $this->dry || $post_id <= 0 ) {
				continue;
			}
			self::backup_post_data( $post_id );
			update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $this->data[ $post_id ] ) ) );
			delete_post_meta( $post_id, '_elementor_css' );
			delete_post_meta( $post_id, '_elementor_element_cache' );
			delete_post_meta( $post_id, '_elementor_page_assets' );
			$saved[] = $post_id;
		}
		$this->dirty = [];
		return $saved;
	}

	/* ---------- sauvegardes ---------- */

	public static function backup_post_data( $post_id ) {
		if ( '' === (string) get_post_meta( $post_id, self::META_BACKUP_DATA, true ) && ! self::is_created( $post_id ) ) {
			$raw = get_post_meta( $post_id, '_elementor_data', true );
			add_post_meta( $post_id, self::META_BACKUP_DATA, wp_slash( is_string( $raw ) ? $raw : wp_json_encode( $raw ) ), true );
		}
	}

	/** Sauvegarde titre / statut / slug / parent / contenu d'un post avant modification. */
	public static function backup_post_fields( $post_id ) {
		if ( self::is_created( $post_id ) || '' !== (string) get_post_meta( $post_id, self::META_BACKUP_POST, true ) ) {
			return;
		}
		$p = get_post( $post_id );
		if ( ! $p ) {
			return;
		}
		add_post_meta(
			$post_id,
			self::META_BACKUP_POST,
			wp_slash( wp_json_encode( [
				'post_title'   => $p->post_title,
				'post_status'  => $p->post_status,
				'post_name'    => $p->post_name,
				'post_parent'  => $p->post_parent,
				'post_content' => $p->post_content,
			] ) ),
			true
		);
	}

	/** Sauvegarde une méta quelconque (valeur brute) avant modification. */
	public static function backup_meta( $post_id, $key ) {
		if ( self::is_created( $post_id ) ) {
			return;
		}
		$saved = get_post_meta( $post_id, self::META_BACKUP_META, true );
		$saved = is_array( $saved ) ? $saved : [];
		if ( array_key_exists( $key, $saved ) ) {
			return;
		}
		$exists        = metadata_exists( 'post', $post_id, $key );
		$saved[ $key ] = [ 'exists' => $exists, 'value' => $exists ? get_post_meta( $post_id, $key, true ) : null ];
		update_post_meta( $post_id, self::META_BACKUP_META, $saved );
	}

	public static function backup_option( $name ) {
		$all = get_option( self::OPT_BACKUP_OPTIONS, [] );
		$all = is_array( $all ) ? $all : [];
		if ( ! array_key_exists( $name, $all ) ) {
			$value        = get_option( $name, '__evo_absent__' );
			$all[ $name ] = $value;
			update_option( self::OPT_BACKUP_OPTIONS, $all, false );
		}
	}

	public static function set_option( $name, $value, $dry = false ) {
		if ( $dry ) {
			return;
		}
		self::backup_option( $name );
		update_option( $name, $value );
	}

	public static function track_created( $post_id ) {
		$all   = get_option( self::OPT_CREATED, [] );
		$all   = is_array( $all ) ? $all : [];
		$all[] = (int) $post_id;
		update_option( self::OPT_CREATED, array_values( array_unique( $all ) ), false );
	}

	public static function is_created( $post_id ) {
		$all = get_option( self::OPT_CREATED, [] );
		return is_array( $all ) && in_array( (int) $post_id, $all, true );
	}

	public static function has_backup() {
		global $wpdb;
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN (%s,%s,%s)", self::META_BACKUP_DATA, self::META_BACKUP_POST, self::META_BACKUP_META ) );
		return $n > 0 || get_option( self::OPT_CREATED ) || get_option( self::OPT_BACKUP_OPTIONS ) || get_option( self::OPT_MENU_BACKUP );
	}

	/* ---------- restauration ---------- */

	public static function restore_all() {
		global $wpdb;
		$log = [];

		// 1. Données Elementor.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_BACKUP_DATA ) );
		foreach ( $rows as $row ) {
			update_post_meta( $row->post_id, '_elementor_data', wp_slash( $row->meta_value ) );
			delete_post_meta( $row->post_id, self::META_BACKUP_DATA );
			delete_post_meta( $row->post_id, '_elementor_css' );
			delete_post_meta( $row->post_id, '_elementor_element_cache' );
			$log[] = 'Contenu Elementor restauré : #' . $row->post_id;
		}

		// 2. Champs des posts (titre, statut, slug, parent).
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_BACKUP_POST ) );
		foreach ( $rows as $row ) {
			$fields = json_decode( $row->meta_value, true );
			if ( is_array( $fields ) ) {
				$fields['ID'] = (int) $row->post_id;
				wp_update_post( wp_slash( $fields ) );
				$log[] = 'Titre/statut/slug restaurés : #' . $row->post_id;
			}
			delete_post_meta( $row->post_id, self::META_BACKUP_POST );
		}

		// 3. Autres métas.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_BACKUP_META ) );
		foreach ( $rows as $row ) {
			$saved = get_post_meta( $row->post_id, self::META_BACKUP_META, true );
			foreach ( (array) $saved as $key => $info ) {
				if ( ! empty( $info['exists'] ) ) {
					update_post_meta( $row->post_id, $key, $info['value'] );
				} else {
					delete_post_meta( $row->post_id, $key );
				}
			}
			delete_post_meta( $row->post_id, self::META_BACKUP_META );
			$log[] = 'Réglages restaurés : #' . $row->post_id;
		}

		// 4. Menu.
		$menu = get_option( self::OPT_MENU_BACKUP );
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( ! get_post( $item['ID'] ) ) {
					continue;
				}
				wp_update_post( [ 'ID' => $item['ID'], 'post_title' => $item['title'], 'menu_order' => $item['order'] ] );
				update_post_meta( $item['ID'], '_menu_item_menu_item_parent', $item['parent'] );
				update_post_meta( $item['ID'], '_menu_item_url', $item['url'] );
				update_post_meta( $item['ID'], '_menu_item_type', $item['type'] );
				update_post_meta( $item['ID'], '_menu_item_object', $item['object'] );
				update_post_meta( $item['ID'], '_menu_item_object_id', $item['object_id'] );
			}
			delete_option( self::OPT_MENU_BACKUP );
			$log[] = 'Menu restauré.';
		}

		// 5. Posts, pages, formulaires et médias créés par le plugin.
		foreach ( (array) get_option( self::OPT_CREATED, [] ) as $post_id ) {
			$p = get_post( $post_id );
			if ( ! $p ) {
				continue;
			}
			if ( 'attachment' === $p->post_type ) {
				wp_delete_attachment( $post_id, true );
			} else {
				wp_delete_post( $post_id, true );
			}
			$log[] = 'Supprimé (créé par le plugin) : ' . $p->post_type . ' #' . $post_id . ' « ' . $p->post_title . ' »';
		}
		delete_option( self::OPT_CREATED );

		// 6. Options.
		foreach ( (array) get_option( self::OPT_BACKUP_OPTIONS, [] ) as $name => $value ) {
			if ( '__evo_absent__' === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
			$log[] = 'Option restaurée : ' . $name;
		}
		delete_option( self::OPT_BACKUP_OPTIONS );

		// 7. Fichiers copiés (fiches techniques renommées...).
		foreach ( (array) get_option( 'evo_corrections_copied_files', [] ) as $file ) {
			if ( is_string( $file ) && file_exists( $file ) && 0 === strpos( wp_normalize_path( $file ), wp_normalize_path( wp_get_upload_dir()['basedir'] ) ) ) {
				wp_delete_file( $file );
			}
		}
		delete_option( 'evo_corrections_copied_files' );

		delete_option( 'evo_corrections_applied' );
		self::clear_caches();
		$log[] = 'Caches Elementor et SpeedyCache vidés.';
		return $log;
	}

	/** Régénère les CSS Elementor (corrige aussi les fichiers post-XXX.css absents) et vide le cache de page. */
	public static function clear_caches() {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		// SpeedyCache : cache HTML statique.
		if ( class_exists( '\SpeedyCache\Delete' ) && method_exists( '\SpeedyCache\Delete', 'run' ) ) {
			try {
				\SpeedyCache\Delete::run( [ 'minified' => true ] );
			} catch ( \Throwable $e ) {
				self::rrmdir_cache();
			}
		} else {
			self::rrmdir_cache();
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	private static function rrmdir_cache() {
		$dir = WP_CONTENT_DIR . '/cache/speedycache';
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			if ( $f->isDir() ) {
				@rmdir( $f->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			} elseif ( preg_match( '/\.(html|gz|br)$/', $f->getFilename() ) ) {
				@unlink( $f->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
	}
}
