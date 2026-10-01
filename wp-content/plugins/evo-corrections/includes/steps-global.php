<?php
/**
 * Corrections qui concernent tout le site : G1, G2, PER7, 2.1, G11–G16, PER4, PER6, SEO3.
 */

defined( 'ABSPATH' ) || exit;

const EVO_OLD_HOST = 'evo-industries.lf-signature-immobilier.com';

/* ======================================================================
 * G1 — Images chargées depuis evo-industries.lf-signature-immobilier.com
 * ==================================================================== */
function evo_step_g1( Evo_Migration $m ) {
	global $wpdb;
	$home     = untrailingslashit( home_url() );
	$new_host = wp_parse_url( $home, PHP_URL_HOST );
	$pairs    = [
		'https://' . EVO_OLD_HOST => $home,
		'http://' . EVO_OLD_HOST  => $home,
		'//' . EVO_OLD_HOST       => '//' . $new_host,
		EVO_OLD_HOST              => $new_host,
	];

	// 1. Données Elementor (pages, header, footer, formulaires, modèles).
	$fixed = 0;
	foreach ( $m->elementor_post_ids() as $pid ) {
		$raw = (string) get_post_meta( $pid, '_elementor_data', true );
		if ( false === strpos( $raw, 'lf-signature-immobilier' ) ) {
			continue;
		}
		$els = &$m->store->els( $pid );
		foreach ( $pairs as $from => $to ) {
			$els = Evo_Tree::replace_everywhere( $els, $from, $to );
		}
		unset( $els );
		$m->store->mark( $pid );
		$fixed++;
		$m->log( 'URLs corrigées dans « ' . get_the_title( $pid ) . ' » (#' . $pid . ')' );
	}

	// 2. Autres métadonnées, contenus, options (valeurs sérialisées gérées).
	$skip = [ '_elementor_data', Evo_Store::META_BACKUP_DATA, Evo_Store::META_BACKUP_POST, Evo_Store::META_BACKUP_META ];
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", '%lf-signature-immobilier%' ) );
	foreach ( $rows as $row ) {
		if ( in_array( $row->meta_key, $skip, true ) || '_wp_attachment_backup_sizes' === $row->meta_key ) {
			continue;
		}
		$value = maybe_unserialize( $row->meta_value );
		$new   = $value;
		foreach ( $pairs as $from => $to ) {
			$new = Evo_Tree::replace_everywhere( $new, $from, $to );
		}
		if ( $new !== $value && ! $m->dry ) {
			Evo_Store::backup_meta( (int) $row->post_id, $row->meta_key );
			update_metadata_by_mid( 'post', (int) $row->meta_id, $new );
			$m->log( 'Méta ' . $row->meta_key . ' corrigée (#' . $row->post_id . ')' );
		}
	}
	$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type NOT IN ('revision')", '%lf-signature-immobilier%' ) );
	foreach ( $posts as $p ) {
		if ( $m->dry ) {
			continue;
		}
		Evo_Store::backup_post_fields( (int) $p->ID );
		$content = $p->post_content;
		foreach ( $pairs as $from => $to ) {
			$content = str_replace( $from, $to, $content );
		}
		$wpdb->update( $wpdb->posts, [ 'post_content' => $content ], [ 'ID' => $p->ID ] );
		clean_post_cache( (int) $p->ID );
		$m->log( 'Contenu corrigé (#' . $p->ID . ')' );
	}
	$opts = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s AND option_name NOT LIKE %s", '%lf-signature-immobilier%', 'evo_corrections%' ) );
	foreach ( $opts as $name ) {
		if ( 0 === strpos( $name, '_transient' ) || 0 === strpos( $name, '_site_transient' ) ) {
			continue;
		}
		$value = get_option( $name );
		$new   = $value;
		foreach ( $pairs as $from => $to ) {
			$new = Evo_Tree::replace_everywhere( $new, $from, $to );
		}
		if ( $new !== $value ) {
			Evo_Store::set_option( $name, $new, $m->dry );
			$m->log( 'Option corrigée : ' . $name );
		}
	}

	// 3. Chaque image Elementor pointe vers un média de la médiathèque (ID correct).
	evo_fix_media_ids( $m );

	$m->log( $fixed . ' contenu(s) Elementor corrigé(s). Rappel : protéger la préprod lf-signature-immobilier par mot de passe + noindex (à faire sur l\'autre hébergement).', 'info' );
}

/** Associe chaque image (url) au bon média ; enregistre dans la médiathèque les fichiers présents mais non référencés. */
function evo_fix_media_ids( Evo_Migration $m, $post_ids = null ) {
	$up      = wp_get_upload_dir();
	$baseurl = set_url_scheme( $up['baseurl'] );
	$cache   = [];
	foreach ( ( $post_ids ?? $m->elementor_post_ids() ) as $pid ) {
		$els     = &$m->store->els( $pid );
		$changed = false;
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( $m, $up, $baseurl, &$cache, &$changed ) {
				if ( empty( $el['settings'] ) ) {
					return;
				}
				Evo_Tree::map_media(
					$el['settings'],
					function ( &$media ) use ( $m, $up, $baseurl, &$cache, &$changed ) {
						$url = set_url_scheme( (string) $media['url'] );
						if ( '' === $url || 0 !== strpos( $url, $baseurl ) ) {
							return;
						}
						$rel      = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
						$orig_rel = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $rel );
						if ( ! isset( $cache[ $orig_rel ] ) ) {
							$id = attachment_url_to_postid( $baseurl . '/' . $orig_rel );
							if ( ! $id && file_exists( $up['basedir'] . '/' . $orig_rel ) && ! $m->dry ) {
								$id = $m->register_attachment( $up['basedir'] . '/' . $orig_rel, pathinfo( $orig_rel, PATHINFO_FILENAME ) );
								$m->log( 'Ajouté à la médiathèque : ' . $orig_rel );
							}
							$cache[ $orig_rel ] = (int) $id;
						}
						$id = $cache[ $orig_rel ];
						if ( $id && (int) ( $media['id'] ?? 0 ) !== $id ) {
							$media['id'] = $id;
							$changed     = true;
						}
					}
				);
			}
		);
		unset( $els );
		if ( $changed ) {
			$m->store->mark( $pid );
		}
	}
}

/* ======================================================================
 * G2 — Blog Lorem Ipsum
 * ==================================================================== */
function evo_step_g2( Evo_Migration $m ) {
	foreach ( array_merge( [ Evo_Migration::BLOG ], Evo_Migration::BLOG_POSTS ) as $id ) {
		$p = get_post( $id );
		if ( ! $p ) {
			$m->warn( "#$id introuvable" );
			continue;
		}
		if ( 'publish' !== $p->post_status ) {
			$m->log( '« ' . $p->post_title . ' » déjà hors ligne (' . $p->post_status . ')', 'info' );
			continue;
		}
		if ( ! $m->dry ) {
			Evo_Store::backup_post_fields( $id );
			wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ] );
		}
		$m->log( 'Passé en brouillon : « ' . $p->post_title . ' »' );
	}
	$m->log( 'Les brouillons sortent automatiquement du sitemap. Gum Elementor Addon ne sert plus qu\'à la grille du blog : il pourra être désactivé (PER3).', 'info' );
}

/* ======================================================================
 * PER7 — Noms de fichiers descriptifs
 * ==================================================================== */
function evo_per7_map() {
	return [
		'2026/08/arriere-plan-du-couloir-vide_23-2149408811.jpg'                => [ 'enzy-ecoles-couloir.jpg', 'Couloir d\'école propre, entretien ENZY pour écoles et crèches' ],
		'2026/08/interieur-salle-urgence-moderne-poste-infirmiere-vide_587448-2137.jpg' => [ 'enzy-sante-clinique.jpg', 'Salle de soins de clinique, nettoyage enzymatique ENZY' ],
		'2026/08/jeune-femme-menage-hotel-met-oreiller-lit_171337-12699.jpg'     => [ 'enzy-hotellerie-chambre.jpg', 'Chambre d\'hôtel préparée, entretien ENZY pour l\'hôtellerie' ],
		'2026/08/interieur-atelier-usine-machines-fond-production-verre_645730-396.jpg' => [ 'enzy-industrie-atelier.jpg', 'Atelier industriel, dégraissage enzymatique ENZY-POWER' ],
		'2026/08/evo-industries-3.png'                                         => [ 'enzy-floor-bidon-5l.png', 'Bidon ENZY-FLOOR 5 L, nettoyant sol enzymatique' ],
		'2026/08/evo-industries-2.png'                                         => [ 'enzy-carpet-bidon-5l.png', 'Bidon ENZY-CARPET 5 L, nettoyant tapis et moquettes enzymatique' ],
		'2026/08/evo-industries-1.png'                                         => [ 'enzy-degrese-bidon-5l.png', 'Bidon ENZY-DEGRESE 5 L, dégraissant enzymatique tout usage' ],
		'2026/08/evo-industries.png'                                           => [ 'enzy-glass-bidon-5l.png', 'Bidon ENZY-GLASS 5 L, nettoyant vitres, miroirs et inox' ],
		'2026/09/power.png'                                                    => [ 'enzy-power-bidon-5l.png', 'Bidon ENZY-POWER 5 L, dégraissant industriel béton et huiles lourdes' ],
		'2026/09/images-removebg-preview.png'                                  => [ 'badge-origine-canada.png', 'Feuille d\'érable, origine Canada' ],
		'2026/09/7_843c3b96-7717-492e-bdfe-70ea84c2b9b5.png'                   => [ 'sens-eve-gamme.png', 'Gamme Sens Ēve, soins capillaires et aromathérapie' ],
		'2026/09/Image1.png'                                                   => [ 'x-pur-gamme.png', 'Gamme X-PUR, hygiène bucco-dentaire' ],
		'2026/09/8_fb98424d-1b4f-4bf6-89fd-3bd4974e4ab3.png'                   => [ 'v-kosmetik-gamme.png', 'Gamme V-Kosmetik, maquillage hypoallergénique' ],
		'2026/09/La_gamme_Herbal_Care_au_Maroc.webp'                           => [ 'herbal-care-gamme.webp', 'Gamme Herbal Care, soins capillaires botaniques' ],
		'2026/09/11_80ef2570-ef7c-4218-bfee-cd90f6c75990.png'                  => [ 'fruiteen-gamme.png', 'Gamme Fruiteen, soins capillaires aux extraits de fruits' ],
		'2026/09/Untitleddesign_0319eac3-a1d5-47dd-9cb6-3e54281f2523.png'      => [ 'salon-cosmepro-gamme.png', 'Gamme Salon Cosmepro, coiffage professionnel' ],
		'2026/09/9_a337f2da-e26a-421f-91c8-149329ad072d-1.png'                 => [ 'neolia-gamme.png', 'Gamme Néolia, soins du corps à l\'huile d\'olive biologique' ],
		'2026/09/1.png'                                                        => [ 'logo-x-pur.png', 'Logo X-PUR' ],
		'2026/09/2.png'                                                        => [ 'logo-v-kosmetik.png', 'Logo V-Kosmetik' ],
		'2026/09/3.png'                                                        => [ 'logo-sens-eve.png', 'Logo Sens Ēve' ],
		'2026/09/4.png'                                                        => [ 'logo-salon-cosmepro.png', 'Logo Salon Cosmepro' ],
		'2026/09/5.png'                                                        => [ 'logo-herbal-care.png', 'Logo Herbal Care' ],
		'2026/09/6.png'                                                        => [ 'logo-fruiteen.png', 'Logo Fruiteen' ],
		'2026/09/7.png'                                                        => [ 'logo-neolia.png', 'Logo Néolia' ],
	];
}

function evo_step_per7( Evo_Migration $m ) {
	$up      = wp_get_upload_dir();
	$baseurl = set_url_scheme( $up['baseurl'] );
	$copied  = get_option( 'evo_corrections_copied_files', [] );
	$copied  = is_array( $copied ) ? $copied : [];
	$renames = []; // regex => [ new_rel_without_ext, ext, id ]

	foreach ( evo_per7_map() as $rel => $info ) {
		list( $new_name, $alt ) = $info;
		$src = $up['basedir'] . '/' . $rel;
		if ( ! file_exists( $src ) ) {
			$m->warn( 'Fichier absent, ignoré : ' . $rel );
			continue;
		}
		$dest_rel = dirname( $rel ) . '/' . $new_name;
		$dest     = $up['basedir'] . '/' . $dest_rel;
		$new_id   = 0;
		if ( ! $m->dry ) {
			if ( ! file_exists( $dest ) ) {
				copy( $src, $dest );
				$copied[] = $dest;
			}
			$new_id = attachment_url_to_postid( $baseurl . '/' . $dest_rel );
			if ( ! $new_id ) {
				$new_id = $m->register_attachment( $dest, pathinfo( $new_name, PATHINFO_FILENAME ), $alt );
			} else {
				update_post_meta( $new_id, '_wp_attachment_image_alt', $alt );
			}
		}
		$ext       = pathinfo( $rel, PATHINFO_EXTENSION );
		$old_base  = pathinfo( $rel, PATHINFO_FILENAME );
		$dir       = dirname( $rel );
		$pattern   = '#/' . preg_quote( $dir, '#' ) . '/' . preg_quote( $old_base, '#' ) . '(-\d+x\d+)?\.' . preg_quote( $ext, '#' ) . '(?=$|[?"\'\s)])#';
		$renames[] = [ $pattern, $dir, pathinfo( $new_name, PATHINFO_FILENAME ), $ext, $new_id ];
		$m->log( $rel . ' → ' . $dest_rel );
	}
	if ( ! $m->dry ) {
		update_option( 'evo_corrections_copied_files', array_values( array_unique( $copied ) ), false );
	}

	// Remplacement dans toutes les données Elementor.
	$count = 0;
	foreach ( $m->elementor_post_ids() as $pid ) {
		$els     = &$m->store->els( $pid );
		$changed = false;
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( $renames, $up, &$changed ) {
				if ( empty( $el['settings'] ) ) {
					return;
				}
				// Médias : URL + ID.
				Evo_Tree::map_media(
					$el['settings'],
					function ( &$media ) use ( $renames, &$changed ) {
						foreach ( $renames as $r ) {
							if ( is_string( $media['url'] ) && preg_match( $r[0], $media['url'] ) ) {
								$media['url'] = preg_replace( $r[0], '/' . $r[1] . '/' . $r[2] . '.' . $r[3], $media['url'] );
								if ( $r[4] ) {
									$media['id'] = $r[4];
								}
								$changed = true;
							}
						}
					}
				);
				// Autres occurrences (HTML, liens) : on garde la taille si elle existe pour le nouveau fichier.
				$el['settings'] = evo_per7_replace_strings( $el['settings'], $renames, $up, $changed );
			}
		);
		unset( $els );
		if ( $changed ) {
			$m->store->mark( $pid );
			$count++;
		}
	}
	$m->log( $count . ' contenu(s) mis à jour avec les nouveaux noms. Les anciens fichiers sont conservés (aucun lien cassé).', 'info' );
}

function evo_per7_replace_strings( $data, $renames, $up, &$changed ) {
	if ( is_string( $data ) ) {
		foreach ( $renames as $r ) {
			if ( preg_match( $r[0], $data ) ) {
				$data    = preg_replace_callback(
					$r[0],
					function ( $mm ) use ( $r, $up ) {
						$size = $mm[1] ?? '';
						if ( $size && ! file_exists( $up['basedir'] . '/' . $r[1] . '/' . $r[2] . $size . '.' . $r[3] ) ) {
							$size = '';
						}
						return '/' . $r[1] . '/' . $r[2] . $size . '.' . $r[3];
					},
					$data
				);
				$changed = true;
			}
		}
		return $data;
	}
	if ( is_array( $data ) ) {
		foreach ( $data as $k => $v ) {
			$data[ $k ] = evo_per7_replace_strings( $v, $renames, $up, $changed );
		}
	}
	return $data;
}

/* ======================================================================
 * 2.1 — Noms de produits et de marques
 * ==================================================================== */
function evo_normalize_names( $text ) {
	$rules = [
		'/(?<![\p{L}\p{N}])A propos(?![\p{L}\p{N}])/u'                               => 'À propos',
		'/(?<![\p{L}\p{N}_\/#.-])ENZY[\s\-]?(FLOOR|CARPET|DEGRESE|POWER|GLASS)(?![\p{L}\p{N}])/iu' => null,
		'/(?<![\p{L}\p{N}])Sens\s+[EÈÉĒèéē]ve(?![\p{L}\p{N}])/u'                     => 'Sens Ēve',
		'/(?<![\p{L}\p{N}])X[\s\-]?pur(?![\p{L}\p{N}])/iu'                         => 'X-PUR',
		'/(?<![\p{L}\p{N}])V[\s\-]kosmetik(?![\p{L}\p{N}])/iu'                     => 'V-Kosmetik',
		'/(?<![\p{L}\p{N}])Herbal\s+care(?![\p{L}\p{N}])/iu'                       => 'Herbal Care',
		'/(?<![\p{L}\p{N}])Salon\s+cosmepro(?![\p{L}\p{N}])/iu'                    => 'Salon Cosmepro',
	];
	foreach ( $rules as $re => $to ) {
		if ( null === $to ) {
			$text = preg_replace_callback( $re, fn( $mm ) => 'ENZY-' . strtoupper( $mm[1] ), $text );
		} else {
			$text = preg_replace( $re, $to, $text );
		}
	}
	return $text;
}

function evo_step_texts( Evo_Migration $m ) {
	$n = 0;
	foreach ( $m->elementor_post_ids() as $pid ) {
		$els    = &$m->store->els( $pid );
		$before = wp_json_encode( $els );
		Evo_Tree::walk(
			$els,
			function ( &$el ) {
				if ( ! empty( $el['settings'] ) ) {
					Evo_Tree::map_texts( $el['settings'], 'evo_normalize_names' );
				}
			}
		);
		if ( wp_json_encode( $els ) !== $before ) {
			$m->store->mark( $pid );
			$n++;
			$m->log( 'Noms harmonisés dans « ' . get_the_title( $pid ) . ' » (#' . $pid . ')' );
		}
		unset( $els );
	}
	// Titres de pages et libellés de menus.
	$items = get_posts( [ 'post_type' => [ 'page', 'nav_menu_item' ], 'post_status' => 'any', 'numberposts' => -1 ] );
	foreach ( $items as $p ) {
		$new = evo_normalize_names( $p->post_title );
		if ( $new !== $p->post_title ) {
			if ( ! $m->dry ) {
				Evo_Store::backup_post_fields( $p->ID );
				wp_update_post( [ 'ID' => $p->ID, 'post_title' => $new ] );
			}
			$m->log( 'Titre « ' . $p->post_title . ' » → « ' . $new . ' »' );
		}
	}
	$m->log( $n . ' contenu(s) modifié(s).', 'info' );
}

/* ======================================================================
 * G11 · G12 — Polices et réglages globaux
 * ==================================================================== */
function evo_step_fonts( Evo_Migration $m ) {
	$keep     = [ 'Poppins', 'DM Sans', '' ];
	$replaced = [];
	foreach ( $m->elementor_post_ids() as $pid ) {
		$els     = &$m->store->els( $pid );
		$changed = false;
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( $keep, &$replaced, &$changed ) {
				if ( empty( $el['settings'] ) ) {
					return;
				}
				$is_heading = in_array( $el['widgetType'] ?? '', [ 'heading', 'elementskit-heading' ], true );
				foreach ( $el['settings'] as $key => $value ) {
					if ( is_string( $value ) && preg_match( '/_font_family$/', $key ) && ! in_array( $value, $keep, true ) ) {
						$title_like                = $is_heading || preg_match( '/title|heading/', $key );
						$el['settings'][ $key ]    = $title_like ? 'Poppins' : 'DM Sans';
						$replaced[ $value ]        = true;
						$changed                   = true;
					}
				}
			}
		);
		unset( $els );
		if ( $changed ) {
			$m->store->mark( $pid );
		}
	}
	if ( $replaced ) {
		$m->log( 'Polices remplacées par Poppins (titres) / DM Sans (textes) : ' . implode( ', ', array_keys( $replaced ) ) );
	}

	// Kit Elementor (Site Settings) : typographies et palette de marque.
	$kit_id = (int) get_option( 'elementor_active_kit' );
	if ( ! $kit_id ) {
		$m->warn( 'Kit Elementor actif introuvable.' );
		return;
	}
	$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
	$settings = is_array( $settings ) ? $settings : [];
	$typo     = [
		'primary'   => [ 'Primary', 'Poppins', '600' ],
		'secondary' => [ 'Secondary', 'Poppins', '500' ],
		'text'      => [ 'Text', 'DM Sans', '400' ],
		'accent'    => [ 'Accent', 'DM Sans', '500' ],
	];
	$system = [];
	foreach ( $typo as $id => $t ) {
		$system[] = [ '_id' => $id, 'title' => $t[0], 'typography_typography' => 'custom', 'typography_font_family' => $t[1], 'typography_font_weight' => $t[2] ];
	}
	$settings['system_typography'] = $system;
	foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $h ) {
		$settings[ $h . '_typography_typography' ]  = 'custom';
		$settings[ $h . '_typography_font_family' ] = 'Poppins';
		$settings[ $h . '_typography_font_weight' ] = '600';
	}
	$settings['body_typography_typography']    = 'custom';
	$settings['body_typography_font_family']   = 'DM Sans';
	$settings['button_typography_typography']  = 'custom';
	$settings['button_typography_font_family'] = 'Poppins';
	$settings['button_typography_font_weight'] = '600';
	$settings['button_border_radius']          = [ 'unit' => 'px', 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => true ];

	// Palette : les couleurs système actuelles sont conservées (le design en dépend) ; on ajoute celles de la marque.
	$custom = isset( $settings['custom_colors'] ) && is_array( $settings['custom_colors'] ) ? $settings['custom_colors'] : [];
	$have   = array_map( fn( $c ) => strtoupper( $c['color'] ?? '' ), $custom );
	$brand  = [
		'evoforet' => [ 'Vert forêt EVO', '#1B4332' ],
		'evovert'  => [ 'Vert EVO', '#40916C' ],
		'evoclair' => [ 'Vert clair EVO', '#52B788' ],
		'evotexte' => [ 'Texte EVO', '#5B6660' ],
		'evofond'  => [ 'Fond clair EVO', '#F8FFFB' ],
	];
	foreach ( $brand as $id => $c ) {
		if ( ! in_array( $c[1], $have, true ) ) {
			$custom[] = [ '_id' => $id, 'title' => $c[0], 'color' => $c[1] ];
		}
	}
	$titles = [ '#1A7A6E' => 'ENZY-FLOOR', '#E8841A' => 'ENZY-CARPET', '#2563A8' => 'ENZY-DEGRESE', '#7B2FBE' => 'ENZY-POWER', '#F5C518' => 'ENZY-GLASS' ];
	foreach ( $custom as &$c ) {
		$hex = strtoupper( $c['color'] ?? '' );
		if ( isset( $titles[ $hex ] ) ) {
			$c['title'] = $titles[ $hex ];
			unset( $titles[ $hex ] );
		}
	}
	unset( $c );
	foreach ( $titles as $hex => $title ) {
		$custom[] = [ '_id' => 'evo' . strtolower( substr( $title, 5, 4 ) ), 'title' => $title, 'color' => $hex ];
	}
	$settings['custom_colors'] = $custom;

	if ( ! $m->dry ) {
		Evo_Store::backup_meta( $kit_id, '_elementor_page_settings' );
		update_post_meta( $kit_id, '_elementor_page_settings', $settings );
		Evo_Store::set_option( 'elementor_font_display', 'swap' );
	}
	$m->log( 'Site Settings : typographies globales (H1–H6, texte, boutons) + palette de marque et couleurs produits nommées.' );
	$m->log( 'Les polices sont servies localement par le plugin (8 fichiers woff2, font-display: swap, préchargement de Poppins 600). Plus aucune requête vers fonts.googleapis.com.', 'info' );
}

/* ======================================================================
 * G13 · G14 · G15 — classes de design (produits, boutons, sur-titres, fonds sombres)
 * ==================================================================== */
function evo_step_design( Evo_Migration $m ) {
	$kit_colors = evo_kit_colors();
	$stats      = [ 'product' => 0, 'btn' => 0, 'sur' => 0, 'dark' => 0 ];
	foreach ( $m->elementor_post_ids() as $pid ) {
		$type = get_post_type( $pid );
		if ( 'metform-form' === $type ) {
			continue;
		}
		$els    = &$m->store->els( $pid );
		$before = wp_json_encode( $els );

		// Fonds sombres : les boutons et sur-titres y prennent la variante claire.
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( $kit_colors, &$stats ) {
				if ( 'container' !== ( $el['elType'] ?? '' ) ) {
					return;
				}
				$s  = $el['settings'] ?? [];
				$bg = $s['background_color'] ?? '';
				if ( ! empty( $s['__globals__']['background_color'] ) && preg_match( '/id=([a-z0-9]+)/i', $s['__globals__']['background_color'], $g ) ) {
					$bg = $kit_colors[ $g[1] ] ?? $bg;
				}
				if ( 'classic' === ( $s['background_background'] ?? '' ) && evo_is_dark( $bg ) && ! Evo_Tree::has_class( $el, 'evo-on-dark' ) ) {
					Evo_Tree::add_class( $el, 'evo-on-dark' );
					$stats['dark']++;
				}
			}
		);

		// Cartes produit : conteneur le plus haut qui ne contient qu'un seul titre ENZY-XXX.
		evo_tag_product_cards( $els, $stats );

		// Boutons.
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( &$stats ) {
				if ( 'button' !== ( $el['widgetType'] ?? '' ) || Evo_Tree::has_class( $el, 'evo-btn' ) ) {
					return;
				}
				$t = mb_strtolower( wp_strip_all_tags( (string) ( $el['settings']['text'] ?? '' ) ) );
				if ( preg_match( '/devis|revendeur/u', $t ) ) {
					Evo_Tree::add_class( $el, 'evo-btn evo-btn--primary' );
				} elseif ( preg_match( '/fiche technique|certification|catalogue|t[ée]l[ée]charger/u', $t ) ) {
					Evo_Tree::add_class( $el, 'evo-btn evo-btn--secondary' );
				} else {
					Evo_Tree::add_class( $el, 'evo-btn' );
				}
				$stats['btn']++;
			}
		);

		// Sur-titres : texte court placé juste avant un titre H1/H2.
		evo_tag_surtitles( $els, $stats );

		if ( wp_json_encode( $els ) !== $before ) {
			$m->store->mark( $pid );
		}
		unset( $els );
	}
	$m->log( sprintf( '%d carte(s) produit colorée(s), %d bouton(s) harmonisé(s), %d sur-titre(s), %d fond(s) sombre(s) repéré(s).', $stats['product'], $stats['btn'], $stats['sur'], $stats['dark'] ) );
}

function evo_kit_colors() {
	$kit = (int) get_option( 'elementor_active_kit' );
	$s   = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : [];
	$out = [ 'primary' => '#6EC1E4', 'secondary' => '#54595F', 'text' => '#7A7A7A', 'accent' => '#61CE70' ];
	foreach ( [ 'system_colors', 'custom_colors' ] as $k ) {
		foreach ( (array) ( $s[ $k ] ?? [] ) as $c ) {
			if ( isset( $c['_id'], $c['color'] ) ) {
				$out[ $c['_id'] ] = $c['color'];
			}
		}
	}
	return $out;
}

function evo_is_dark( $color ) {
	if ( ! is_string( $color ) || ! preg_match( '/^#?([0-9a-f]{6})([0-9a-f]{2})?$/i', trim( $color ), $mm ) ) {
		return false;
	}
	if ( isset( $mm[2] ) && hexdec( $mm[2] ) < 128 ) {
		return false; // transparent
	}
	$r = hexdec( substr( $mm[1], 0, 2 ) ) / 255;
	$g = hexdec( substr( $mm[1], 2, 2 ) ) / 255;
	$b = hexdec( substr( $mm[1], 4, 2 ) ) / 255;
	return ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) < 0.35;
}

function evo_product_slug_in( array $el ) {
	$slugs = [];
	Evo_Tree::walk(
		$el['elements'],
		function ( &$c ) use ( &$slugs ) {
			if ( in_array( $c['widgetType'] ?? '', [ 'heading', 'elementskit-heading' ], true ) ) {
				$t = strtoupper( trim( wp_strip_all_tags( (string) ( $c['settings']['title'] ?? $c['settings']['ekit_heading_title'] ?? '' ) ) ) );
				if ( preg_match( '/^ENZY[\s\-]?(FLOOR|CARPET|DEGRESE|POWER|GLASS)$/', $t, $mm ) ) {
					$slugs[] = 'enzy-' . strtolower( $mm[1] );
				}
			}
		}
	);
	return $slugs;
}

function evo_tag_product_cards( array &$els, array &$stats ) {
	foreach ( $els as &$el ) {
		if ( 'container' !== ( $el['elType'] ?? '' ) || empty( $el['elements'] ) ) {
			continue;
		}
		$slugs = evo_product_slug_in( $el );
		if ( 1 === count( $slugs ) ) {
			if ( ! Evo_Tree::has_class( $el, 'evo-product' ) ) {
				Evo_Tree::add_class( $el, 'evo-product evo-product--' . $slugs[0] );
				$stats['product']++;
			}
			continue;
		}
		if ( count( $slugs ) > 1 ) {
			evo_tag_product_cards( $el['elements'], $stats );
		}
	}
	unset( $el );
}

function evo_tag_surtitles( array &$els, array &$stats ) {
	$count = count( $els );
	for ( $i = 0; $i < $count; $i++ ) {
		$el = &$els[ $i ];
		if ( ! empty( $el['elements'] ) ) {
			evo_tag_surtitles( $el['elements'], $stats );
		}
		if ( $i + 1 >= $count || 'widget' !== ( $el['elType'] ?? '' ) ) {
			unset( $el );
			continue;
		}
		$next      = $els[ $i + 1 ];
		$next_type = $next['widgetType'] ?? '';
		$next_big  = ( 'heading' === $next_type && in_array( $next['settings']['header_size'] ?? 'h2', [ 'h1', 'h2' ], true ) )
			|| ( 'elementskit-heading' === $next_type );
		$type      = $el['widgetType'] ?? '';
		$small     = ( 'text-editor' === $type ) || ( 'heading' === $type && ! in_array( $el['settings']['header_size'] ?? 'h2', [ 'h1', 'h2' ], true ) );
		$text      = Evo_Tree::plain_text( $el );
		if ( $next_big && $small && '' !== $text && mb_strlen( $text ) <= 60 && ! Evo_Tree::has_class( $el, 'evo-surtitre' ) ) {
			Evo_Tree::add_class( $el, 'evo-surtitre' );
			$stats['sur']++;
		}
		unset( $el );
	}
}

/* ======================================================================
 * G16 — Animations d'entrée
 * ==================================================================== */
function evo_step_animations( Evo_Migration $m ) {
	$n = 0;
	foreach ( $m->elementor_post_ids() as $pid ) {
		$els     = &$m->store->els( $pid );
		$changed = false;
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( &$n, &$changed ) {
				foreach ( [ '_animation', 'animation', '_animation_tablet', '_animation_mobile', 'animation_tablet', 'animation_mobile' ] as $k ) {
					if ( ! empty( $el['settings'][ $k ] ) && 'none' !== $el['settings'][ $k ] ) {
						$el['settings'][ $k ] = 'none';
						$changed              = true;
						$n++;
					}
				}
			}
		);
		unset( $els );
		if ( $changed ) {
			$m->store->mark( $pid );
		}
	}
	$m->log( $n . ' animation(s) d\'entrée supprimée(s) (titres et textes : plus d\'effet « page vide » au chargement).' );
}

/* ======================================================================
 * PER6 — Tailles d'images
 * ==================================================================== */
function evo_step_per6( Evo_Migration $m ) {
	$targets = [
		Evo_Migration::HEADER => [ '0729c63', 'f17ed9e' ],
		Evo_Migration::FOOTER => [ 'c472647' ],
	];
	foreach ( $targets as $pid => $ids ) {
		foreach ( $ids as $id ) {
			if ( $m->set( $pid, $id, [ 'image_size' => 'medium_large' ] ) ) {
				$m->log( "Logo #$id : servi en 768 px au lieu de 1050 px" );
			}
		}
	}
	// Vignettes des cartes secteurs (modèle global + pages).
	$n = 0;
	foreach ( array_filter( [ Evo_Migration::id( 'tpl_secteurs' ), Evo_Migration::HOME, Evo_Migration::ENZY, Evo_Migration::ECOLES, Evo_Migration::SANTE, Evo_Migration::HOTEL, Evo_Migration::INDUS ] ) as $pid ) {
		if ( ! $m->has_post( $pid ) ) {
			continue;
		}
		$els = &$m->store->els( $pid );
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( &$n ) {
				$url = (string) ( $el['settings']['image']['url'] ?? '' );
				if ( in_array( $el['widgetType'] ?? '', [ 'image', 'image-box' ], true ) && preg_match( '/(enzy-(ecoles|sante|hotellerie|industrie)|ecoles-creches|clinique|hotellerie|industrie)[^\/]*\.jpe?g$/', $url ) ) {
					$el['settings']['image_size'] = 'medium_large';
					$n++;
				}
			}
		);
		unset( $els );
		$m->store->mark( $pid );
	}
	$m->log( $n . ' vignette(s) secteur servies en 768 px (au lieu de 1024–1060 px).' );
	$m->log( 'Conversion WebP : activer l\'option WebP de SpeedyCache Pro (déjà installé, règles .htaccess présentes).', 'info' );
}

/* ======================================================================
 * PER4 — Réglages Elementor
 * ==================================================================== */
function evo_step_per4( Evo_Migration $m ) {
	$opts = [
		'elementor_load_fa4_shim'                  => '',
		'elementor_experiment-e_font_icon_svg'     => 'active',
		'elementor_experiment-e_optimized_markup'  => 'active',
		'elementor_experiment-e_optimized_css_files' => 'active',
		'elementor_css_print_method'               => 'external',
	];
	foreach ( $opts as $name => $value ) {
		Evo_Store::set_option( $name, $value, $m->dry );
		$m->log( $name . ' = ' . ( '' === $value ? '(désactivé)' : $value ) );
	}
	$m->log( 'Le plugin retire aussi les feuilles Font Awesome 4 (shims) et charge MetForm uniquement sur les pages qui ont un formulaire.', 'info' );
}

/* ======================================================================
 * SEO3 — Textes alternatifs
 * ==================================================================== */
function evo_seo3_alts() {
	return [
		'logo-horizontal-evo-industries' => 'EVO Industries Morocco, filiale du groupe canadien EvoRoyal',
		'cropped-logo-horizontal-evo-industries' => 'EVO Industries Morocco',
		'4-secteurs-ENZY'                => 'Gamme ENZY par secteur : hôtels et restaurants, écoles et crèches, industrie, santé',
		'ecoles-creches'                 => 'Salle de classe propre, entretien ENZY pour écoles et crèches',
		'clinique'                       => 'Couloir de clinique, nettoyage enzymatique sans vapeurs toxiques',
		'hotellerie'                     => 'Chambre d\'hôtel, nettoyage ENZY sans odeur chimique',
		'industrie'                      => 'Site industriel, dégraissage enzymatique ENZY-POWER',
		'evo-industries-3'               => 'Bidon ENZY-FLOOR 5 L, nettoyant sol enzymatique',
		'evo-industries-2'               => 'Bidon ENZY-CARPET 5 L, nettoyant tapis et moquettes',
		'evo-industries-1'               => 'Bidon ENZY-DEGRESE 5 L, dégraissant enzymatique',
		'evo-industries'                 => 'Bidon ENZY-GLASS 5 L, nettoyant vitres, miroirs et inox',
		'power'                          => 'Bidon ENZY-POWER 5 L, dégraissant industriel',
		'telechargement'                 => 'Tableau des dilutions ENZY',
	];
}

function evo_step_seo3( Evo_Migration $m ) {
	$n    = 0;
	$alts = evo_seo3_alts();
	foreach ( evo_per7_map() as $rel => $info ) {
		$alts[ pathinfo( $info[0], PATHINFO_FILENAME ) ] = $info[1];
	}
	$atts = get_posts( [ 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => -1, 'post_status' => 'inherit' ] );
	foreach ( $atts as $a ) {
		$name = pathinfo( (string) get_post_meta( $a->ID, '_wp_attached_file', true ), PATHINFO_FILENAME );
		if ( isset( $alts[ $name ] ) && '' === (string) get_post_meta( $a->ID, '_wp_attachment_image_alt', true ) ) {
			if ( ! $m->dry ) {
				Evo_Store::backup_meta( $a->ID, '_wp_attachment_image_alt' );
				update_post_meta( $a->ID, '_wp_attachment_image_alt', $alts[ $name ] );
			}
			$n++;
		}
	}
	// Les widgets Elementor qui ont un alt vide « forcé » reprennent celui du média.
	foreach ( $m->elementor_post_ids() as $pid ) {
		$els     = &$m->store->els( $pid );
		$changed = false;
		Evo_Tree::walk(
			$els,
			function ( &$el ) use ( &$changed ) {
				if ( empty( $el['settings'] ) ) {
					return;
				}
				Evo_Tree::map_media(
					$el['settings'],
					function ( &$media ) use ( &$changed ) {
						if ( ! empty( $media['id'] ) && isset( $media['alt'] ) && '' === $media['alt'] ) {
							$alt = (string) get_post_meta( (int) $media['id'], '_wp_attachment_image_alt', true );
							if ( '' !== $alt ) {
								$media['alt'] = $alt;
								$changed      = true;
							}
						}
					}
				);
			}
		);
		unset( $els );
		if ( $changed ) {
			$m->store->mark( $pid );
		}
	}
	$m->log( $n . ' texte(s) alternatif(s) ajouté(s) dans la médiathèque. Les images restantes sans alt reçoivent un texte déduit du nom de fichier à l\'affichage.' );
}
