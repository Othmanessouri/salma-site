<?php
/**
 * Outils > Corrections EVO : réglages (coordonnées, fichiers à fournir), application / simulation / restauration,
 * liste des actions à faire hors WordPress. Commandes WP-CLI équivalentes.
 */

defined( 'ABSPATH' ) || exit;

class Evo_Admin {

	const SLUG = 'evo-corrections';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_post_evo_corr_settings', [ __CLASS__, 'save_settings' ] );
		add_action( 'wp_ajax_evo_corr_step', [ __CLASS__, 'ajax_step' ] );
		add_action( 'wp_ajax_evo_corr_finish', [ __CLASS__, 'ajax_finish' ] );
		add_action( 'wp_ajax_evo_corr_restore', [ __CLASS__, 'ajax_restore' ] );
		add_action( 'admin_notices', [ __CLASS__, 'notices' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( EVO_CORR_FILE ), [ __CLASS__, 'action_links' ] );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'evo-corrections', 'Evo_Cli' );
		}
	}

	public static function url() {
		return admin_url( 'tools.php?page=' . self::SLUG );
	}

	public static function menu() {
		add_management_page( 'Corrections EVO', 'Corrections EVO', 'manage_options', self::SLUG, [ __CLASS__, 'page' ] );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Ouvrir</a>' );
		return $links;
	}

	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'tools_page_' . self::SLUG === $screen->id ) {
			return;
		}
		if ( ! Evo_Migration::applied() ) {
			echo '<div class="notice notice-info"><p><strong>EVO Corrections</strong> est actif. <a href="' . esc_url( self::url() ) . '">Appliquer les corrections de la liste de Salma</a>.</p></div>';
		}
		if ( get_user_by( 'login', 'adminadmin' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Sécurité (SECU1)</strong> : le compte « adminadmin » existe encore. Créez un nouvel administrateur au nom non devinable, reconnectez-vous avec, puis supprimez « adminadmin » en attribuant ses contenus au nouveau compte (Comptes > Tous les comptes).</p></div>';
		}
	}

	/* ---------- réglages ---------- */

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( 'evo_corr_settings' );
		Evo_Settings::save( $_POST['evo'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		Evo_Store::clear_caches();
		wp_safe_redirect( add_query_arg( 'saved', '1', self::url() ) );
		exit;
	}

	/* ---------- AJAX ---------- */

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Accès refusé.', 403 );
		}
		check_ajax_referer( 'evo_corr_run' );
	}

	public static function ajax_step() {
		self::guard();
		$step  = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );
		$dry   = ! empty( $_POST['dry'] );
		$steps = Evo_Migration::steps();
		$ids   = array_keys( $steps );
		$id    = null;
		foreach ( $ids as $candidate ) {
			if ( strtolower( $candidate ) === $step ) {
				$id = $candidate;
			}
		}
		if ( ! $id ) {
			wp_send_json_error( 'Étape inconnue.' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_raise_memory_limit( 'admin' );
		$m = new Evo_Migration( $dry );
		// Pas de vidage de cache à chaque étape (fait à la fin).
		$m->current = $id;
		$m->log( '— ' . $steps[ $id ][0], 'title' );
		try {
			call_user_func( $steps[ $id ][1], $m );
			$m->store->flush();
			if ( ! $dry ) {
				$applied        = Evo_Migration::applied();
				$applied[ $id ] = current_time( 'mysql' );
				update_option( Evo_Migration::OPT_APPLIED, $applied, false );
			}
		} catch ( \Throwable $e ) {
			$m->log( 'Erreur : ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')', 'error' );
		}
		wp_send_json_success( [ 'log' => $m->log ] );
	}

	public static function ajax_finish() {
		self::guard();
		if ( empty( $_POST['dry'] ) ) {
			Evo_Store::clear_caches();
		}
		wp_send_json_success( [ 'log' => [ [ 'level' => 'info', 'msg' => empty( $_POST['dry'] ) ? 'Terminé. CSS Elementor régénérés et cache SpeedyCache vidé.' : 'Simulation terminée : rien n\'a été modifié.' ] ] ] );
	}

	public static function ajax_restore() {
		self::guard();
		$log = Evo_Store::restore_all();
		delete_option( Evo_Migration::OPT_IDS );
		delete_option( 'evo_corrections_redirects' );
		wp_send_json_success( [ 'log' => array_map( fn( $l ) => [ 'level' => 'ok', 'msg' => $l ], $log ) ] );
	}

	/* ---------- page ---------- */

	public static function page() {
		$applied = Evo_Migration::applied();
		$steps   = Evo_Migration::steps();
		$s       = Evo_Settings::all();
		$nonce   = wp_create_nonce( 'evo_corr_run' );
		?>
<div class="wrap evo-admin">
	<h1>Corrections EVO <small style="font-weight:400;color:#646970">— liste de Salma du 30/09/2026</small></h1>
	<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
		<div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>
	<?php endif; ?>

	<style>
		.evo-admin .card{max-width:1100px;padding:16px 20px}
		.evo-admin table.evo-steps td{padding:6px 8px;vertical-align:top}
		.evo-admin .evo-done{color:#1a7f37;font-weight:600}
		.evo-admin #evo-log{background:#0d1b12;color:#e7f5ec;font:12px/1.55 ui-monospace,Menlo,Consolas,monospace;padding:12px 14px;max-height:460px;overflow:auto;border-radius:6px;white-space:pre-wrap;display:none}
		.evo-admin #evo-log .title{color:#95d5b2;font-weight:700;margin-top:6px;display:block}
		.evo-admin #evo-log .warn{color:#ffd166}.evo-admin #evo-log .error{color:#ff6b6b}.evo-admin #evo-log .info{color:#9ecbff}
		.evo-admin .form-table th{width:260px}
		.evo-admin .evo-todo li{margin:0 0 6px}
	</style>

	<div class="card">
		<h2>1. Appliquer les corrections</h2>
		<p>Chaque correction est enregistrée avec une <strong>sauvegarde automatique</strong> du contenu d'origine. « Simuler » affiche ce qui serait fait sans rien modifier ; « Tout restaurer » remet le site dans l'état d'avant (contenus, menu, options) et supprime ce que le plugin a créé.</p>
		<p><strong>Avant de commencer :</strong> faites une sauvegarde Backuply (base + fichiers).</p>
		<table class="evo-steps widefat striped">
			<thead><tr><td style="width:28px"><input type="checkbox" id="evo-all" checked></td><td>Correction</td><td style="width:170px">État</td></tr></thead>
			<tbody>
			<?php foreach ( $steps as $id => $step ) : ?>
				<tr>
					<td><input type="checkbox" class="evo-step" value="<?php echo esc_attr( strtolower( $id ) ); ?>" <?php checked( ! isset( $applied[ $id ] ) ); ?>></td>
					<td><?php echo esc_html( $step[0] ); ?></td>
					<td><?php echo isset( $applied[ $id ] ) ? '<span class="evo-done">Appliquée</span> <small>' . esc_html( $applied[ $id ] ) . '</small>' : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button class="button" id="evo-dry">Simuler</button>
			<button class="button button-primary" id="evo-run">Appliquer la sélection</button>
			<?php if ( Evo_Store::has_backup() ) : ?>
				<button class="button button-link-delete" id="evo-restore" style="margin-left:16px">Tout restaurer</button>
			<?php endif; ?>
		</p>
		<div id="evo-log" aria-live="polite"></div>
	</div>

	<div class="card">
		<h2>2. Coordonnées et fichiers « À fournir : Salma »</h2>
		<p>Les boutons « Télécharger le catalogue ENZY » et « Certification », ainsi que RC / ICE dans le footer, restent masqués tant que le champ correspondant est vide. Collez l'URL du PDF après l'avoir ajouté dans Médias.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="evo_corr_settings">
			<?php wp_nonce_field( 'evo_corr_settings' ); ?>
			<table class="form-table" role="presentation">
				<?php
				$fields = [
					'phone_display'     => 'Téléphone (affiché)',
					'phone_intl'        => 'Téléphone (format international, lien tel:)',
					'whatsapp'          => 'WhatsApp (chiffres, ex. 212705944036)',
					'email'             => 'Email affiché',
					'sales_email'       => 'Email commercial (notifications des formulaires)',
					'address'           => 'Adresse (footer)',
					'hours'             => 'Horaires',
					'linkedin'          => 'LinkedIn',
					'instagram'         => 'Instagram',
					'company_rc'        => 'RC (registre du commerce)',
					'company_ice'       => 'ICE',
					'catalogue_enzy'    => 'Catalogue ENZY (URL du PDF) — ENZY1',
					'cert_enzy-floor'   => 'Certification ENZY-FLOOR (PDF) — ENZY6',
					'cert_enzy-carpet'  => 'Certification ENZY-CARPET (PDF)',
					'cert_enzy-degrese' => 'Certification ENZY-DEGRESE (PDF)',
					'cert_enzy-power'   => 'Certification ENZY-POWER (PDF)',
					'cert_enzy-glass'   => 'Certification ENZY-GLASS (PDF)',
					'ga4_id'            => 'Google Analytics 4 (ID G-XXXXXXX) — SUI1',
				];
				foreach ( $fields as $key => $label ) :
					?>
					<tr>
						<th scope="row"><label for="evo-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input class="regular-text" type="text" id="evo-<?php echo esc_attr( $key ); ?>" name="evo[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row">Bandeau du haut</th>
					<td><label><input type="checkbox" name="evo[topbar_enabled]" value="1" <?php checked( $s['topbar_enabled'], '1' ); ?>> Afficher « Filiale du groupe canadien EvoRoyal · Devis sous 24 h · téléphone · WhatsApp »</label></td>
				</tr>
			</table>
			<?php submit_button( 'Enregistrer les réglages' ); ?>
		</form>
	</div>

	<div class="card">
		<h2>3. À faire hors de ce plugin</h2>
		<ul class="evo-todo">
			<li><strong>G1</strong> — Protéger la préprod <code>evo-industries.lf-signature-immobilier.com</code> par mot de passe + noindex (autre hébergement).</li>
			<li><strong>LEG1 · G9</strong> — Compléter puis publier <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&post_status=draft' ) ); ?>">Mentions légales et Politique de confidentialité</a> (brouillons) ; renseigner RC / ICE ci-dessus.</li>
			<li><strong>SECU1</strong> — Remplacer le compte « adminadmin » (voir l'avertissement en haut de l'administration).</li>
			<li><strong>SECU2</strong> — Loginizer (déjà installé) : limitation des tentatives, 2FA pour les admins, URL de connexion personnalisée.</li>
			<li><strong>SECU3</strong> — Backuply : sauvegarde quotidienne hors serveur (Google Drive) + test de restauration ; mettre à jour WordPress, thème et plugins ; supprimer les thèmes Twenty* inutilisés.</li>
			<li><strong>FOR5</strong> — GoSMTP : expéditeur info@evoindustries.ma ; DNS : SPF, DKIM, DMARC. Faire un envoi test de chaque formulaire.</li>
			<li><strong>PER1 · PER2 · PER6</strong> — SpeedyCache Pro : minification/combinaison CSS-JS, conversion WebP ; Cloudflare gratuit ; PHP ≥ 8.2 (cPanel : actuellement ea-php81) ; mémoire WordPress ≥ 256 Mo.</li>
			<li><strong>PER3</strong> — Désactiver Gum Elementor Addon (le blog est en brouillon) et Fluent Forms (plus utilisé sur les pages) après vérification.</li>
			<li><strong>SEO1</strong> — Search Console + Bing Webmaster Tools : soumettre <a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>" target="_blank">/wp-sitemap.xml</a> (nettoyé : sans modèles, formulaires ni auteurs). Si Rank Math est installé plus tard, les titles/meta de ce plugin se désactivent d'eux-mêmes.</li>
			<li><strong>SEO7</strong> — Créer la fiche Google Business Profile « EVO Industries Morocco » (Casablanca).</li>
			<li><strong>SUI1</strong> — Renseigner l'ID GA4 ci-dessus : envois de formulaire (/merci/), clics WhatsApp, téléphone et téléchargements sont suivis automatiquement.</li>
			<li><strong>G17</strong> — Tester chaque page en 375 px, 768 px et 1280 px ; lancer PageSpeed Insights mobile.</li>
		</ul>
	</div>
</div>
<script>
(function(){
	var nonce = <?php echo wp_json_encode( $nonce ); ?>, ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var logEl = document.getElementById('evo-log');
	function print(lines){ logEl.style.display='block'; lines.forEach(function(l){ var s=document.createElement('span'); s.className=l.level||''; s.textContent=(l.level==='title'?'':'  ')+l.msg+'\n'; logEl.appendChild(s); }); logEl.scrollTop=logEl.scrollHeight; }
	function post(data){ var fd=new FormData(); fd.append('_ajax_nonce',nonce); Object.keys(data).forEach(function(k){fd.append(k,data[k]);}); return fetch(ajax,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}); }
	function selected(){ return Array.prototype.map.call(document.querySelectorAll('.evo-step:checked'),function(c){return c.value;}); }
	function run(dry){
		var steps=selected(); if(!steps.length){ alert('Aucune correction sélectionnée.'); return; }
		if(!dry && !confirm('Appliquer '+steps.length+' correction(s) sur le site ? Une sauvegarde du contenu est faite automatiquement.')) return;
		document.querySelectorAll('.evo-admin button').forEach(function(b){b.disabled=true;});
		logEl.textContent=''; print([{level:'info',msg:(dry?'Simulation':'Application')+' de '+steps.length+' correction(s)…'}]);
		var i=0;
		(function next(){
			if(i>=steps.length){ post({action:'evo_corr_finish',dry:dry?1:''}).then(function(r){ print(r.data.log); if(!dry){ print([{level:'info',msg:'Rechargement de la page…'}]); setTimeout(function(){location.reload();},2500);} else { document.querySelectorAll('.evo-admin button').forEach(function(b){b.disabled=false;}); } }); return; }
			post({action:'evo_corr_step',step:steps[i],dry:dry?1:''}).then(function(r){ print(r.success?r.data.log:[{level:'error',msg:String(r.data)}]); i++; next(); }).catch(function(e){ print([{level:'error',msg:'Erreur réseau sur '+steps[i]+' : '+e}]); i++; next(); });
		})();
	}
	document.getElementById('evo-dry').addEventListener('click',function(e){e.preventDefault();run(true);});
	document.getElementById('evo-run').addEventListener('click',function(e){e.preventDefault();run(false);});
	var all=document.getElementById('evo-all'); all.addEventListener('change',function(){document.querySelectorAll('.evo-step').forEach(function(c){c.checked=all.checked;});});
	var rs=document.getElementById('evo-restore');
	if(rs){ rs.addEventListener('click',function(e){ e.preventDefault(); if(!confirm('Tout restaurer ? Les contenus, le menu et les options reviennent à leur état d\'avant le plugin, et les pages/formulaires créés sont supprimés.')) return; logEl.textContent=''; post({action:'evo_corr_restore'}).then(function(r){ print(r.data.log||[]); setTimeout(function(){location.reload();},2500); }); }); }
})();
</script>
		<?php
	}
}

/**
 * wp evo-corrections apply [--dry-run] [--steps=G1,G2]
 * wp evo-corrections restore
 * wp evo-corrections status
 */
class Evo_Cli {

	/**
	 * Applique les corrections.
	 *
	 * [--dry-run]
	 * : Simule sans rien modifier.
	 *
	 * [--steps=<ids>]
	 * : Liste d'étapes séparées par des virgules (par défaut : toutes celles pas encore appliquées).
	 *
	 * [--force]
	 * : Rejoue aussi les étapes déjà appliquées.
	 */
	public function apply( $args, $assoc ) {
		$only = null;
		if ( ! empty( $assoc['steps'] ) ) {
			$only = array_map( 'strtoupper', array_map( 'trim', explode( ',', $assoc['steps'] ) ) );
		} elseif ( ! empty( $assoc['force'] ) ) {
			$only = array_keys( Evo_Migration::steps() );
		}
		$m = new Evo_Migration( ! empty( $assoc['dry-run'] ) );
		foreach ( $m->run( $only ) as $line ) {
			$prefix = [ 'title' => '', 'ok' => '  ✓ ', 'info' => '  · ', 'warn' => '  ! ', 'error' => '  ✗ ' ][ $line['level'] ] ?? '  ';
			WP_CLI::line( $prefix . $line['msg'] );
		}
	}

	/** Restaure l'état d'avant le plugin. */
	public function restore() {
		foreach ( Evo_Store::restore_all() as $line ) {
			WP_CLI::line( '  ' . $line );
		}
		delete_option( Evo_Migration::OPT_IDS );
		delete_option( 'evo_corrections_redirects' );
		WP_CLI::success( 'Restauration terminée.' );
	}

	/** Affiche les étapes appliquées. */
	public function status() {
		$applied = Evo_Migration::applied();
		foreach ( Evo_Migration::steps() as $id => $step ) {
			WP_CLI::line( sprintf( '%-7s %-11s %s', $id, isset( $applied[ $id ] ) ? 'appliquée' : '—', $step[0] ) );
		}
	}
}
