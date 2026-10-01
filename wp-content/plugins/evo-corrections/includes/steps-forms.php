<?php
/**
 * 9. Formulaires MetForm : champs, consentement, anti-spam, redirection /merci/, notifications,
 * + nouveaux formulaires (page devis, devis rapide Accueil/Contact, revendeur) et pages /devis/, /merci/.
 */

defined( 'ABSPATH' ) || exit;

const EVO_SECTOR_OPTIONS = [ 'École / Crèche', 'Clinique / Santé', 'Hôtellerie / Restauration', 'Industrie', 'Autre' ];
const EVO_SURFACE_OPTIONS = [ 'Moins de 100 m²', '100 à 500 m²', '500 à 2 000 m²', 'Plus de 2 000 m²', 'Je ne sais pas' ];

/** Formulaires de devis existants => secteur à présélectionner. */
function evo_devis_forms() {
	return [
		Evo_Migration::FORM_ENZY   => '',
		Evo_Migration::FORM_ECOLES => 'École / Crèche',
		Evo_Migration::FORM_SANTE  => 'Clinique / Santé',
		Evo_Migration::FORM_HOTEL  => 'Hôtellerie / Restauration',
		Evo_Migration::FORM_INDUS  => 'Industrie',
	];
}

/** ID du widget MetForm dont le nom technique est $name (ou d'un type donné). */
function evo_mf_find( array $els, $name, $type = null ) {
	$found = null;
	Evo_Tree::walk(
		$els,
		function ( &$el ) use ( $name, $type, &$found ) {
			if ( $found || 'widget' !== ( $el['elType'] ?? '' ) ) {
				return;
			}
			$n = $el['settings']['mf_input_name'] ?? '';
			if ( ( null !== $name && $n === $name ) || ( null === $name && $type && ( $el['widgetType'] ?? '' ) === $type ) ) {
				$found = $el['id'];
			}
		}
	);
	return $found;
}

function evo_mf_select_list( array $labels, $selected = '' ) {
	$out = [];
	foreach ( $labels as $label ) {
		$out[] = [
			'_id'                      => substr( Evo_Tree::new_id(), 0, 7 ),
			'mf_input_option_text'     => $label,
			'mf_input_option_value'    => $label,
			'mf_input_option_status'   => '',
			'mf_input_option_selected' => ( $label === $selected ) ? 'selected' : '',
		];
	}
	return $out;
}

function evo_mf_multi_list( array $labels, array $selected = [] ) {
	$out = [];
	foreach ( $labels as $label ) {
		$out[] = [
			'_id'                      => substr( Evo_Tree::new_id(), 0, 7 ),
			'label'                    => $label,
			'value'                    => $label,
			'mf_input_option_status'   => '',
			'mf_input_option_selected' => in_array( $label, $selected, true ) ? 'selected' : '',
		];
	}
	return $out;
}

/** Réglages de largeur d'un widget (pour reproduire une ligne à 2 colonnes). */
function evo_width_keys( array $settings ) {
	return array_filter( $settings, fn( $k ) => (bool) preg_match( '/^(_element_width|_element_custom_width|_flex_)/', $k ), ARRAY_FILTER_USE_KEY );
}

function evo_strip_width( array $settings ) {
	return array_diff_key( $settings, evo_width_keys( $settings ) );
}

/**
 * Applique les corrections de la section 9 à un arbre de formulaire.
 * $sector : secteur présélectionné (pages secteurs) ; $model : widgets modèles (sélecteur secteur, multi) du formulaire ENZY.
 */
function evo_fix_form_tree( Evo_Migration $m, array &$els, $sector, array $model ) {
	$done = [];

	// Secteur.
	$sel = evo_mf_find( $els, 'mf-select' ) ?? evo_mf_find( $els, 'mf-secteur' );
	if ( $sel ) {
		$w = &Evo_Tree::get( $els, $sel );
		$w['settings']['mf_input_name']        = 'mf-secteur';
		$w['settings']['mf_input_placeholder'] = 'Choisissez votre secteur';
		$w['settings']['mf_input_list']        = evo_mf_select_list( EVO_SECTOR_OPTIONS, $sector );
		unset( $w );
		$done[] = 'secteur';
	}

	// Produits : liste complète, sélection multiple.
	$products = array_merge( array_values( Evo_Migration::PRODUCTS ), [ 'Je ne sais pas encore' ] );
	$multi    = evo_mf_find( $els, 'mf-multi-select' ) ?? evo_mf_find( $els, 'mf-produits' );
	$single   = evo_mf_find( $els, 'mf-select-produit' );
	if ( $multi ) {
		$w = &Evo_Tree::get( $els, $multi );
		$w['settings']['mf_input_name'] = 'mf-produits';
		$w['settings']['mf_input_list'] = evo_mf_multi_list( $products );
		unset( $w );
		$done[] = 'produits (liste complète)';
	} elseif ( $single ) {
		$w               = &Evo_Tree::get( $els, $single );
		$w['widgetType'] = 'mf-multi-select';
		$w['settings']   = array_merge(
			$w['settings'],
			! empty( $model['multi'] ) ? evo_width_keys( $model['multi']['settings'] ) : [],
			[
				'mf_input_name'        => 'mf-produits',
				'mf_input_placeholder' => 'Sélection multiple',
				'mf_input_list'        => evo_mf_multi_list( $products ),
			]
		);
		unset( $w );
		$done[] = 'produits → sélection multiple, 5 produits';
	}

	// Pages secteurs : ajout du champ Secteur présélectionné (SEC2).
	if ( ! $sel && $sector && ! empty( $model['select'] ) && ( $multi || $single ) ) {
		$new                                    = Evo_Tree::clone_el( $model['select'] );
		$new['settings']['mf_input_name']        = 'mf-secteur';
		$new['settings']['mf_input_placeholder'] = 'Choisissez votre secteur';
		$new['settings']['mf_input_list']        = evo_mf_select_list( EVO_SECTOR_OPTIONS, $sector );
		Evo_Tree::insert( $els, $multi ?: $single, $new, 'before' );
		$done[] = 'secteur « ' . $sector . ' » présélectionné';
	}

	// Surface : liste avec « Je ne sais pas » (FOR3).
	$surface = evo_mf_find( $els, 'mf-number' );
	if ( $surface && ! empty( $model['select'] ) ) {
		$old                                    = Evo_Tree::get( $els, $surface );
		$new                                    = Evo_Tree::clone_el( $model['select'] );
		$new['settings']                        = array_merge( evo_strip_width( $new['settings'] ), evo_width_keys( $old['settings'] ) );
		$new['settings']['mf_input_label']       = $old['settings']['mf_input_label'] ?? 'Surface à traiter (m²)';
		$new['settings']['mf_input_name']        = 'mf-surface';
		$new['settings']['mf_input_placeholder'] = 'Choisissez une surface';
		$new['settings']['mf_input_required']    = '';
		$new['settings']['mf_input_list']        = evo_mf_select_list( EVO_SURFACE_OPTIONS );
		Evo_Tree::replace( $els, $surface, $new );
		$done[] = 'surface (option « Je ne sais pas »)';
	}

	// Libellés et placeholders (tableau section 9).
	$texts = [
		'mf-number-site'   => [ null, 'ex : 2' ],
		'mf-first-name'    => [ 'Nom et prénom', 'Votre nom et prénom' ],
		'mf-first-societe' => [ null, 'Votre société' ],
		'mf-email'         => [ 'Email', 'vous@entreprise.ma' ],
		'mf-telephone'     => [ 'Téléphone', '06 XX XX XX XX' ],
	];
	foreach ( $texts as $name => $t ) {
		$id = evo_mf_find( $els, $name );
		if ( ! $id ) {
			continue;
		}
		$w = &Evo_Tree::get( $els, $id );
		if ( null !== $t[0] ) {
			$w['settings']['mf_input_label'] = $t[0];
		}
		$w['settings']['mf_input_placeholder'] = $t[1];
		unset( $w );
	}

	// Précisions : zone de texte multi-lignes.
	$msg = evo_mf_find( $els, 'mf-text' ) ?? evo_mf_find( $els, 'mf-message' );
	if ( $msg ) {
		$w               = &Evo_Tree::get( $els, $msg );
		$w['widgetType'] = 'mf-textarea';
		$w['settings']['mf_input_name']            = 'mf-message';
		$w['settings']['mf_input_placeholder']     = 'Précisez votre besoin…';
		$w['settings']['mf_textarea_field_height'] = [ 'unit' => 'px', 'size' => 120, 'sizes' => [] ];
		unset( $w );
		$done[] = 'précisions → zone de texte';
	}

	// Ville (FOR3) : à côté de la fréquence d'entretien, même largeur que Société.
	$freq    = evo_mf_find( $els, 'mf-entretien' );
	$societe = evo_mf_find( $els, 'mf-first-societe' );
	if ( $freq && $societe && ! evo_mf_find( $els, 'mf-ville' ) ) {
		$soc  = Evo_Tree::get( $els, $societe );
		$city = Evo_Tree::clone_el( $soc );
		$city['settings']['mf_input_label']       = 'Ville';
		$city['settings']['mf_input_name']        = 'mf-ville';
		$city['settings']['mf_input_placeholder'] = 'ex : Casablanca';
		$city['settings']['mf_input_required']    = '';
		$f             = &Evo_Tree::get( $els, $freq );
		$f['settings'] = array_merge( evo_strip_width( $f['settings'] ), evo_width_keys( $soc['settings'] ) );
		unset( $f );
		Evo_Tree::insert( $els, $freq, $city, 'after' );
		$done[] = 'ville';
	}

	// Anti-spam (honeypot) et consentement, juste avant le bouton.
	$button = evo_mf_find( $els, null, 'mf-button' );
	if ( $button && ! evo_mf_find( $els, 'mf-site-web' ) && $societe ) {
		$hp = Evo_Tree::clone_el( Evo_Tree::get( $els, $societe ) );
		$hp['settings']['mf_input_label']       = 'Site web';
		$hp['settings']['mf_input_name']        = 'mf-site-web';
		$hp['settings']['mf_input_placeholder'] = '';
		$hp['settings']['mf_input_required']    = '';
		$hp['settings']['_css_classes']         = 'evo-hp';
		Evo_Tree::insert( $els, $button, $hp, 'before' );
		$done[] = 'anti-spam';
	}
	if ( $button && ! evo_mf_find( $els, 'mf-consentement' ) ) {
		$consent = Evo_Tree::widget(
			'mf-gdpr-consent',
			[
				'mf_input_label_status'               => '',
				'mf_input_label'                      => 'Consentement',
				'mf_input_name'                       => 'mf-consentement',
				'mf_input_required'                   => 'yes',
				'mf_input_validation_warning_message' => 'Merci de cocher cette case pour envoyer votre demande.',
				'mf_gdpr_consent_option_text'         => 'Vos données sont utilisées uniquement pour traiter votre demande. Voir notre <a href="/politique-de-confidentialite/" target="_blank" rel="noopener">politique de confidentialité</a>.',
				'_css_classes'                        => 'evo-consent',
			]
		);
		Evo_Tree::insert( $els, $button, $consent, 'before' );
		$done[] = 'consentement (loi 09-08)';
	}
	return $done;
}

/** FOR4 · FOR5 : redirection /merci/, notification commerciale, accusé de réception. */
function evo_fix_form_settings( Evo_Migration $m, $form_id, $title = null, $kind = 'devis' ) {
	$s     = get_post_meta( $form_id, 'metform_form__form_setting', true );
	$s     = is_array( $s ) ? $s : [];
	$email = Evo_Settings::get( 'email' );
	$sales = Evo_Settings::get( 'sales_email' ) ? Evo_Settings::get( 'sales_email' ) : $email;
	$from  = 'EVO Industries <' . $email . '>';
	$subj  = 'revendeur' === $kind ? 'Nouvelle demande revendeur' : 'Nouvelle demande de devis';
	$page  = $title ? $title : get_the_title( $form_id );

	$s['form_title']                = $title ? $title : ( $s['form_title'] ?? $page );
	$s['store_entries']             = '1';
	$s['redirect_to']               = home_url( '/merci/' );
	$s['enable_admin_notification'] = '1';
	if ( empty( $s['admin_email_to'] ) ) {
		$s['admin_email_to'] = $sales;
	}
	$s['admin_email_subject']  = ! empty( $s['admin_email_subject'] ) ? $s['admin_email_subject'] : $subj . ' — ' . $page;
	$s['admin_email_from']     = ! empty( $s['admin_email_from'] ) ? $s['admin_email_from'] : $from;
	$s['admin_email_reply_to'] = ! empty( $s['admin_email_reply_to'] ) ? $s['admin_email_reply_to'] : '[mf-email]';
	if ( empty( $s['admin_email_body'] ) ) {
		$s['admin_email_body'] = 'Nouvelle demande reçue depuis le site evoindustries.ma. Répondre sous 24 h ouvrées.';
	}
	$s['enable_user_notification'] = '1';
	if ( empty( $s['user_email_subject'] ) ) {
		$s['user_email_subject'] = 'revendeur' === $kind ? 'Votre demande revendeur — EVO Industries' : 'Votre demande de devis — EVO Industries';
	}
	$s['user_email_from']     = ! empty( $s['user_email_from'] ) ? $s['user_email_from'] : $from;
	$s['user_email_reply_to'] = ! empty( $s['user_email_reply_to'] ) ? $s['user_email_reply_to'] : $sales;
	if ( empty( $s['user_email_body'] ) ) {
		$s['user_email_body'] = "Bonjour,\n\nNous avons bien reçu votre demande et nous revenons vers vous sous 24 h ouvrées (du lundi au vendredi, 9h–18h).\n\nPour toute question urgente : WhatsApp " . Evo_Settings::get( 'phone_display' ) . ".\n\nL'équipe EVO Industries Morocco";
	}
	$s['user_email_attach_submission_copy'] = '1';

	if ( ! $m->dry ) {
		Evo_Store::backup_meta( $form_id, 'metform_form__form_setting' );
		update_post_meta( $form_id, 'metform_form__form_setting', $s );
	}
	return $s['admin_email_to'];
}

function evo_step_forms( Evo_Migration $m ) {
	// Modèles de champs pris dans le formulaire ENZY (le plus complet).
	$enzy  = $m->store->els( Evo_Migration::FORM_ENZY );
	$model = [];
	$sid   = evo_mf_find( $enzy, 'mf-select' ) ?? evo_mf_find( $enzy, 'mf-secteur' );
	$mid   = evo_mf_find( $enzy, 'mf-multi-select' ) ?? evo_mf_find( $enzy, 'mf-produits' );
	if ( $sid ) {
		$model['select'] = Evo_Tree::get( $enzy, $sid );
	}
	if ( $mid ) {
		$model['multi'] = Evo_Tree::get( $enzy, $mid );
	}
	if ( empty( $model['select'] ) ) {
		$m->warn( 'Champ « Votre secteur d\'activité » introuvable dans le formulaire ENZY (#373) : il ne sera pas ajouté aux pages secteurs.' );
	}

	foreach ( evo_devis_forms() as $fid => $sector ) {
		if ( ! $m->has_post( $fid ) ) {
			$m->warn( "Formulaire #$fid introuvable." );
			continue;
		}
		$els  = &$m->store->els( $fid );
		$done = evo_fix_form_tree( $m, $els, $sector, $model );
		unset( $els );
		$m->store->mark( $fid );
		$to = evo_fix_form_settings( $m, $fid );
		$m->log( '« ' . get_the_title( $fid ) . ' » (#' . $fid . ') : ' . implode( ', ', $done ) . ' ; après envoi → /merci/ ; notification → ' . $to . ' + accusé de réception.' );
	}
	$m->log( 'Délivrabilité : GoSMTP est installé — vérifier l\'expéditeur info@evoindustries.ma et les enregistrements SPF / DKIM / DMARC du domaine (hors WordPress).', 'info' );
}

/* ======================================================================
 * Nouveaux formulaires et pages
 * ==================================================================== */

/** Duplique un formulaire MetForm (post + réglages) avec un nouvel arbre. */
function evo_clone_form( Evo_Migration $m, $source_id, $title, array $els, $key ) {
	$existing = Evo_Migration::id( $key );
	if ( $existing && get_post( $existing ) ) {
		$m->log( 'Formulaire « ' . $title . ' » déjà créé (#' . $existing . ').', 'info' );
		return $existing;
	}
	$meta = [];
	foreach ( get_post_meta( $source_id ) as $k => $values ) {
		if ( in_array( $k, [ '_edit_lock', '_edit_last', '_elementor_data', '_elementor_css', '_elementor_element_cache', Evo_Store::META_BACKUP_DATA, Evo_Store::META_BACKUP_META, Evo_Store::META_BACKUP_POST ], true ) ) {
			continue;
		}
		$meta[ $k ] = maybe_unserialize( $values[0] );
	}
	$id = $m->create_post( [ 'post_title' => $title, 'post_type' => 'metform-form', 'post_status' => 'publish' ], $meta, $els );
	if ( $id > 0 ) {
		$m->remember_id( $key, $id );
	}
	return $id;
}

/** Retire un champ (par nom) et, si sa ligne devient vide, la ligne. */
function evo_mf_remove_field( array &$els, $name ) {
	$id = evo_mf_find( $els, $name );
	if ( ! $id ) {
		return;
	}
	$parent = Evo_Tree::parent_id( $els, $id );
	Evo_Tree::remove( $els, $id );
	if ( $parent ) {
		$p = Evo_Tree::get( $els, $parent );
		if ( $p && empty( $p['elements'] ) ) {
			Evo_Tree::remove( $els, $parent );
		}
	}
}

function evo_mf_set( array &$els, $name, array $settings ) {
	$id = evo_mf_find( $els, $name );
	if ( ! $id ) {
		return false;
	}
	$w             = &Evo_Tree::get( $els, $id );
	$w['settings'] = array_merge( $w['settings'], $settings );
	return true;
}

/** Remplace le widget $name par le widget $moved (déplacé), en gardant la largeur de la place occupée. */
function evo_mf_take_place( array &$els, $name, $moved_name ) {
	$target = evo_mf_find( $els, $name );
	$moved  = evo_mf_find( $els, $moved_name );
	if ( ! $target || ! $moved ) {
		return;
	}
	$old_parent = Evo_Tree::parent_id( $els, $moved );
	$width      = evo_width_keys( Evo_Tree::get( $els, $target )['settings'] );
	$el         = Evo_Tree::remove( $els, $moved );
	$el['settings'] = array_merge( evo_strip_width( $el['settings'] ), $width );
	Evo_Tree::replace( $els, $target, $el );
	if ( $old_parent ) {
		$p = Evo_Tree::get( $els, $old_parent );
		if ( $p && empty( $p['elements'] ) ) {
			Evo_Tree::remove( $els, $old_parent );
		}
	}
}

function evo_step_new_forms( Evo_Migration $m ) {
	if ( ! $m->has_post( Evo_Migration::FORM_ENZY ) ) {
		$m->warn( 'Formulaire ENZY (#373) introuvable : nouveaux formulaires non créés.' );
		return;
	}
	$base = $m->store->els( Evo_Migration::FORM_ENZY );
	$wa   = Evo_Settings::whatsapp_url( 'Bonjour, je souhaite avoir plus d\'informations sur vos produits.' );

	// 1. Formulaire de la page devis (message obligatoire, FOR1).
	$devis = array_map( [ 'Evo_Tree', 'clone_el' ], $base );
	evo_mf_set( $devis, 'mf-message', [ 'mf_input_required' => 'yes', 'mf_input_label' => 'Précisions sur votre besoin' ] );
	$form_devis = evo_clone_form( $m, Evo_Migration::FORM_ENZY, 'Demande de devis — page dédiée', $devis, 'form_devis' );
	if ( $form_devis > 0 ) {
		evo_fix_form_settings( $m, $form_devis, 'Demande de devis — page dédiée' );
	}

	// 2. Formulaire court (Accueil + Contact) : secteur, ville, coordonnées, message.
	$quick = array_map( [ 'Evo_Tree', 'clone_el' ], $base );
	evo_mf_take_place( $quick, 'mf-produits', 'mf-ville' );
	evo_mf_remove_field( $quick, 'mf-surface' );
	evo_mf_remove_field( $quick, 'mf-number-site' );
	evo_mf_remove_field( $quick, 'mf-entretien' );
	$form_quick = evo_clone_form( $m, Evo_Migration::FORM_ENZY, 'Demande de devis — formulaire court (Accueil, Contact)', $quick, 'form_quick' );
	if ( $form_quick > 0 ) {
		evo_fix_form_settings( $m, $form_quick, 'Demande de devis — formulaire court' );
	}

	// 3. Formulaire revendeur (BEA3).
	$rev = array_map( [ 'Evo_Tree', 'clone_el' ], $base );
	evo_mf_set(
		$rev,
		'mf-secteur',
		[
			'mf_input_label'       => 'Type de point de vente',
			'mf_input_name'        => 'mf-type-point-de-vente',
			'mf_input_placeholder' => 'Choisissez',
			'mf_input_required'    => 'yes',
			'mf_input_list'        => evo_mf_select_list( [ 'Pharmacie', 'Parapharmacie', 'Salon', 'Concept store', 'E-commerce' ] ),
		]
	);
	evo_mf_set(
		$rev,
		'mf-produits',
		[
			'mf_input_label'       => 'Marques d\'intérêt',
			'mf_input_name'        => 'mf-marques',
			'mf_input_placeholder' => 'Sélection multiple',
			'mf_input_list'        => evo_mf_multi_list( array_values( Evo_Migration::BRANDS ) ),
		]
	);
	evo_mf_take_place( $rev, 'mf-surface', 'mf-ville' );
	evo_mf_set( $rev, 'mf-number-site', [ 'mf_input_label' => 'Nombre de points de vente', 'mf_input_name' => 'mf-nombre-points-de-vente', 'mf_input_placeholder' => 'ex : 3' ] );
	evo_mf_remove_field( $rev, 'mf-entretien' );
	evo_mf_set( $rev, 'mf-first-societe', [ 'mf_input_label' => 'Point de vente / société', 'mf_input_placeholder' => 'Nom de votre établissement' ] );
	evo_mf_set( $rev, 'mf-message', [ 'mf_input_label' => 'Votre message (facultatif)', 'mf_input_placeholder' => 'Votre projet, vos questions…' ] );
	Evo_Tree::walk(
		$rev,
		function ( &$el ) {
			if ( 'mf-button' === ( $el['widgetType'] ?? '' ) ) {
				$el['settings']['mf_btn_text'] = 'Envoyer ma demande';
			}
		}
	);
	$form_rev = evo_clone_form( $m, Evo_Migration::FORM_ENZY, 'Devenir revendeur', $rev, 'form_revendeur' );
	if ( $form_rev > 0 ) {
		evo_fix_form_settings( $m, $form_rev, 'Devenir revendeur', 'revendeur' );
	}

	// Widget MetForm modèle (celui de la page ENZY).
	$widget = $m->copy( Evo_Migration::ENZY, 'b134a1e' );
	if ( ! $widget ) {
		$widget = Evo_Tree::widget( 'metform', [] );
	}
	$form_widget = function ( $form_id ) use ( $widget ) {
		$w                           = Evo_Tree::clone_el( $widget );
		$w['settings']['mf_form_id'] = (string) $form_id;
		return $w;
	};

	// 4. Accueil + Contact : formulaire court à la place du formulaire Fluent Forms.
	foreach ( [ Evo_Migration::HOME => '24a883e', Evo_Migration::CONTACT => '5b884db' ] as $pid => $sc ) {
		$els = &$m->store->els( $pid );
		$ref = &Evo_Tree::get( $els, $sc );
		if ( null !== $ref && 'shortcode' === ( $ref['widgetType'] ?? '' ) && $form_quick ) {
			$new = $form_widget( $form_quick );
			Evo_Tree::add_class( $new, 'evo-quick-form' );
			unset( $ref );
			Evo_Tree::replace( $els, $sc, $new );
			$m->store->mark( $pid );
			$m->log( '« ' . get_the_title( $pid ) . ' » : formulaire Fluent Forms remplacé par le formulaire court MetForm (consentement, anti-spam, /merci/).' );
		}
		unset( $ref, $els );
	}

	// 5. Page /devis/ (FOR1) : section devis reprise de la page Écoles.
	$section = $m->copy( Evo_Migration::ECOLES, '0f91f55' );
	if ( $section && ! Evo_Migration::id( 'page_devis' ) ) {
		$section['settings']['_element_id'] = 'devis';
		Evo_Tree::add_class( $section, 'evo-devis-page' );
		$n = 0;
		Evo_Tree::walk(
			$section['elements'],
			function ( &$w ) use ( &$n, $form_devis ) {
				$t = $w['widgetType'] ?? '';
				if ( 'heading' === $t ) {
					if ( 0 === $n ) {
						$w['settings']['title']       = 'Devis gratuit';
						$w['settings']['header_size'] = 'p';
					} else {
						$w['settings']['title']       = 'Demandez votre devis — réponse sous 24 h ouvrées';
						$w['settings']['header_size'] = 'h1';
					}
					$n++;
				} elseif ( 'metform' === $t ) {
					$w['settings']['mf_form_id'] = (string) $form_devis;
				}
			}
		);
		$pid = $m->create_elementor_page( 'Demande de devis', 'devis', [ $section ] );
		$m->remember_id( 'page_devis', $pid );
	}

	// 6. Page /merci/ (FOR4) : bandeau repris de la page À propos.
	$cta = $m->copy( Evo_Migration::APROPOS, 'c174e41' );
	if ( $cta && ! Evo_Migration::id( 'page_merci' ) ) {
		Evo_Tree::add_class( $cta, 'evo-merci' );
		$i = 0;
		Evo_Tree::walk(
			$cta['elements'],
			function ( &$w ) use ( &$i, $wa ) {
				$t = $w['widgetType'] ?? '';
				if ( 'heading' === $t ) {
					$w['settings']['title']       = 'Merci, votre demande a bien été envoyée';
					$w['settings']['header_size'] = 'h1';
				} elseif ( 'text-editor' === $t ) {
					$w['settings']['editor'] = '<p>Nous revenons vers vous <strong>sous 24 h ouvrées</strong> (du lundi au vendredi, 9h–18h) avec un devis et la fiche technique correspondante.</p><p>Une question urgente ? Écrivez-nous directement sur WhatsApp.</p>';
				} elseif ( 'button' === $t ) {
					if ( 0 === $i ) {
						$w['settings']['text'] = 'Écrire sur WhatsApp';
						$w['settings']['link'] = Evo_Tree::link( $wa, true );
					} else {
						$w['settings']['text'] = 'Télécharger le catalogue ENZY';
						$w['settings']['link'] = Evo_Tree::link( '/telechargement/catalogue-enzy/', true );
						Evo_Tree::add_class( $w, 'evo-download-btn' );
					}
					$i++;
				}
			}
		);
		$pid = $m->create_elementor_page( 'Merci', 'merci', [ $cta ] );
		$m->remember_id( 'page_merci', $pid );
	}

	// 7. Beauté : section « Devenir revendeur » avec son formulaire (BEA3).
	if ( $form_rev && $m->has_post( Evo_Migration::BEAUTE ) && ! $m->has_marker( Evo_Migration::BEAUTE, 'evo-revendeur-form' ) ) {
		$sec = $m->copy( Evo_Migration::ECOLES, '0f91f55' );
		if ( $sec ) {
			$sec['settings']['_element_id'] = 'devenir-revendeur';
			Evo_Tree::add_class( $sec, 'evo-revendeur-form' );
			$n = 0;
			Evo_Tree::walk(
				$sec['elements'],
				function ( &$w ) use ( &$n, $form_rev ) {
					$t = $w['widgetType'] ?? '';
					if ( 'heading' === $t ) {
						$w['settings']['title'] = 0 === $n ? 'Formulaire revendeur' : 'Devenir revendeur de nos marques au Maroc';
						$n++;
					} elseif ( 'metform' === $t ) {
						$w['settings']['mf_form_id'] = (string) $form_rev;
					}
				}
			);
			$m->insert( Evo_Migration::BEAUTE, 'afdd715', $sec, 'after' );
			$m->log( 'Beauté : section « Devenir revendeur » (#devenir-revendeur) avec formulaire dédié : type de point de vente, ville, marques d\'intérêt, nombre de points de vente, contact.' );
		}
	}
	$m->log( 'Fluent Forms n\'est plus utilisé sur les pages : il pourra être désactivé après vérification (PER3).', 'info' );
}
