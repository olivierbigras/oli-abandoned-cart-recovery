<?php
/**
 * Interface d'administration sous le menu WooCommerce (1.2) : un sous-menu, quatre écrans.
 *
 * Tableau de bord · Paniers · Courriels · Réglages (+ assistant de configuration des nouvelles installations).
 *
 * @package OliAbandonedCartRecovery
 * @author  Olivier Bigras (bigrat95)
 * @link    https://olivierbigras.com
 */

defined( 'ABSPATH' ) || exit;

require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-carts-table.php';
require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-pending-table.php';
require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-log-table.php';
require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-admin-dashboard.php';
require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-admin-carts.php';
require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-admin-emails.php';
require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-admin-settings.php';
require_once OLI_ACR_DIR . 'includes/admin/class-oli-acr-admin-wizard.php';

/**
 * Admin : menu, navigation, bandeau de statut, avis et actions communes.
 */
class OLI_ACR_Admin {

	const SLUG = 'oli-acr';

	/**
	 * Suffixe d'écran de la page du plugin (retour de add_submenu_page()).
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Avis fermables par utilisateur (méta utilisateur), en plus des avis fermés pour tout le site.
	 */
	const USER_NOTICES = array( 'polylang_wc', 'update_120' );

	/**
	 * Méta utilisateur des avis fermés (préfixe _oli_acr_ : supprimée par uninstall.php).
	 */
	const DISMISSED_META = '_oli_acr_dismissed_notices';

	/**
	 * Accroches.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_oli_acr_toggle', array( __CLASS__, 'toggle' ) );
		add_action( 'admin_post_oli_acr_dismiss_notice', array( __CLASS__, 'dismiss_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'global_notices' ) );
		add_action( 'wp_ajax_oli_acr_dismiss_donate', array( __CLASS__, 'ajax_dismiss_donate' ) );
		add_action( 'wp_ajax_oli_acr_dismiss', array( __CLASS__, 'ajax_dismiss' ) );
		add_filter( 'plugin_action_links_' . OLI_ACR_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
		OLI_ACR_Admin_Carts::init();
		OLI_ACR_Admin_Emails::init();
		OLI_ACR_Admin_Settings::init();
		OLI_ACR_Admin_Wizard::init();
	}

	/**
	 * Écrans, dans l'ordre fixe de la spec (D1).
	 *
	 * @return array<string, string>
	 */
	public static function screens() {
		$screens = array(
			'dashboard' => __( 'Dashboard', 'oli-abandoned-cart-recovery' ),
			'carts'     => __( 'Carts', 'oli-abandoned-cart-recovery' ),
			'emails'    => __( 'Emails', 'oli-abandoned-cart-recovery' ),
			'settings'  => __( 'Settings', 'oli-abandoned-cart-recovery' ),
		);
		if ( ! self::can_settings() ) {
			unset( $screens['settings'] );
		}
		return $screens;
	}

	/**
	 * L'utilisateur courant peut-il ouvrir Réglages et l'assistant ? (shop_manager autorisé : non, D7.)
	 *
	 * @return bool
	 */
	public static function can_settings() {
		return current_user_can( OLI_ACR_CAP ) && current_user_can( oli_acr_settings_cap() );
	}

	/**
	 * URL d'un écran.
	 *
	 * @param string       $screen Écran (dashboard, carts, emails, settings).
	 * @param array<mixed> $args   Arguments.
	 * @return string
	 */
	public static function url( $screen = 'dashboard', $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page'   => self::SLUG,
					'screen' => $screen,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * URL de l'assistant.
	 *
	 * @param int $step Étape (1 à 4).
	 * @return string
	 */
	public static function setup_url( $step = 1 ) {
		return add_query_arg(
			array(
				'page'  => self::SLUG,
				'setup' => 1,
				'step'  => max( 1, min( 4, (int) $step ) ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Liens « Réglages » et « Donate » dans la liste des extensions.
	 *
	 * @param array<mixed> $links Liens.
	 * @return array<mixed>
	 */
	public static function action_links( $links ) {
		$settings = '<a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'oli-abandoned-cart-recovery' ) . '</a>';
		$donate   = '<a href="' . esc_url( OLI_ACR_DONATE_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'oli-abandoned-cart-recovery' ) . '</a>';
		array_unshift( $links, $settings, $donate );
		return $links;
	}

	/**
	 * Lien Donate dans la méta de la ligne d'extension.
	 *
	 * @param array<mixed> $links Liens.
	 * @param string       $file  Basename.
	 * @return array<mixed>
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( OLI_ACR_BASENAME !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( OLI_ACR_DONATE_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'oli-abandoned-cart-recovery' ) . '</a>';
		return $links;
	}

	/**
	 * Sous-menu WooCommerce unique « Récupération de paniers » (D1).
	 *
	 * @return void
	 */
	public static function menu() {
		$label = __( 'Cart recovery', 'oli-abandoned-cart-recovery' );
		$hook  = add_submenu_page( 'woocommerce', $label, $label, OLI_ACR_CAP, self::SLUG, array( __CLASS__, 'render' ) );
		if ( is_string( $hook ) && '' !== $hook ) {
			self::$hook = $hook;
			// Redirections et actions groupées traitées avant tout affichage.
			add_action( 'load-' . $hook, array( __CLASS__, 'deny_settings_early' ) );
			add_action( 'load-' . $hook, array( __CLASS__, 'maybe_redirect_legacy' ) );
			add_action( 'load-' . $hook, array( 'OLI_ACR_Admin_Carts', 'maybe_bulk_delete' ) );
		}
	}

	/**
	 * Réglages refusés (403) à qui n'a pas la capacité des Réglages, avant tout affichage.
	 *
	 * @return void
	 */
	public static function deny_settings_early() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture seule : choix de l'écran.
		$screen = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : '';
		if ( 'settings' === $screen && ! self::can_settings() ) {
			// D7 : réponse 403 complète avant tout affichage (pas un message dans la page).
			self::check_cap( true );
		}
	}

	/**
	 * Anciennes URL d'onglets (1.1.x) : 302 vers l'écran ou la vue équivalente (CA-ACR-02).
	 *
	 * @return void
	 */
	public static function maybe_redirect_legacy() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navigation en lecture seule (redirection), aucune modification.
		if ( ! isset( $_GET['tab'] ) ) {
			return;
		}
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ) );
		$args = array();
		switch ( $tab ) {
			case 'carts':
				$screen = 'carts';
				$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
				$map    = array(
					'abandoned'    => 'todo',
					'failed'       => 'todo',
					'reminded'     => 'reminded',
					'recovered'    => 'recovered',
					'unsubscribed' => 'unsubscribed',
				);
				if ( isset( $map[ $status ] ) ) {
					$args['view'] = $map[ $status ];
				}
				break;
			case 'pending':
				$screen = 'carts';
				if ( 'yes' === oli_acr_get_setting( 'pending_enabled' ) ) {
					$args['view'] = 'pending';
				}
				break;
			case 'recovered':
				$screen       = 'carts';
				$args['view'] = 'recovered';
				break;
			case 'log':
				$screen       = 'emails';
				$args['view'] = 'log';
				break;
			case 'templates':
				$screen = 'emails';
				if ( isset( $_GET['edit'] ) ) {
					$args['edit'] = sanitize_key( wp_unslash( $_GET['edit'] ) );
				}
				if ( isset( $_GET['lang'] ) ) {
					$args['lang'] = sanitize_text_field( wp_unslash( $_GET['lang'] ) );
				}
				break;
			case 'settings':
				$screen = 'settings';
				break;
			default:
				$screen = 'dashboard';
				if ( isset( $_GET['period'] ) ) {
					$args['period'] = absint( $_GET['period'] );
				}
		}
		if ( isset( $_GET['oli_acr_msg'] ) ) {
			$args['oli_acr_msg'] = sanitize_key( wp_unslash( $_GET['oli_acr_msg'] ) );
		}
		// phpcs:enable
		wp_safe_redirect( self::url( $screen, $args ), 302 );
		exit;
	}

	/**
	 * Indique si l'écran courant est une page du plugin.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return ( '' !== self::$hook && self::$hook === $screen->id ) || false !== strpos( (string) $screen->id, '_page_' . self::SLUG );
	}

	/**
	 * L'assistant est-il affiché sur cette requête ?
	 *
	 * @return bool
	 */
	public static function is_wizard_request() {
		if ( ! self::can_settings() ) {
			return false;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navigation en lecture seule.
		if ( ! empty( $_GET['setup'] ) ) {
			return true;
		}
		$screen = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : '';
		// phpcs:enable
		// Premier lancement d'une nouvelle installation : l'assistant remplace l'accueil et Réglages.
		return ! oli_acr_setup_done() && in_array( $screen, array( '', 'settings' ), true );
	}

	/**
	 * Styles et script d'admin (écrans du plugin seulement ; sélecteurs limités à .oli-acr-wrap).
	 *
	 * @param string $hook Écran.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( ( '' !== self::$hook && self::$hook !== $hook ) || false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		// Feuille minifiée en production ; source lisible (admin.css, livrée aussi) avec SCRIPT_DEBUG.
		$css = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'admin.css' : 'admin.min.css';
		wp_enqueue_style( 'oli-acr-admin', OLI_ACR_URL . 'assets/css/' . $css, array(), OLI_ACR_VERSION );
		wp_enqueue_script( 'oli-acr-admin', OLI_ACR_URL . 'assets/js/admin.js', array(), OLI_ACR_VERSION, true );
		wp_localize_script(
			'oli-acr-admin',
			'oliAcrAdmin',
			array(
				'confirmDeleteTemplate' => __( 'Delete this email?', 'oli-abandoned-cart-recovery' ),
				'confirmAlways'         => __( 'This records personal information without a checkbox. You become responsible for compliance with applicable privacy law. Confirm only if you have another legal basis.', 'oli-abandoned-cart-recovery' ),
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'donateNonce'           => wp_create_nonce( 'oli_acr_donate' ),
				'dismissNonce'          => wp_create_nonce( 'oli_acr_dismiss' ),
			)
		);
	}

	/**
	 * Vérifie la capacité.
	 *
	 * @param bool $settings Exiger aussi la capacité des Réglages.
	 * @return void
	 */
	public static function check_cap( $settings = false ) {
		if ( ! current_user_can( OLI_ACR_CAP ) || ( $settings && ! self::can_settings() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'oli-abandoned-cart-recovery' ), 403 );
		}
	}

	/**
	 * Redirection avec message.
	 *
	 * @param string               $screen Écran.
	 * @param string               $msg    Code du message.
	 * @param array<string, mixed> $args   Arguments.
	 * @param string               $hash   Ancre.
	 * @return void
	 */
	public static function redirect( $screen, $msg, $args = array(), $hash = '' ) {
		$url = self::url( $screen, array_merge( $args, array( 'oli_acr_msg' => $msg ) ) );
		wp_safe_redirect( $url . ( '' !== $hash ? '#' . $hash : '' ) );
		exit;
	}

	/**
	 * Écran demandé (dashboard par défaut).
	 *
	 * @return string
	 */
	public static function current_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation en lecture seule.
		$screen = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : 'dashboard';
		$all    = array( 'dashboard', 'carts', 'emails', 'settings' );
		return in_array( $screen, $all, true ) ? $screen : 'dashboard';
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public static function render() {
		self::check_cap();
		if ( self::is_wizard_request() ) {
			OLI_ACR_Admin_Wizard::render();
			return;
		}
		$screen = self::current_screen();
		if ( 'settings' === $screen && ! self::can_settings() ) {
			// D7 : le shop_manager autorisé voit Tableau de bord, Paniers et Courriels, pas Réglages.
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'oli-abandoned-cart-recovery' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Code de message affiché seulement.
		$msg     = isset( $_GET['oli_acr_msg'] ) ? sanitize_key( wp_unslash( $_GET['oli_acr_msg'] ) ) : '';
		$screens = self::screens();
		$title   = isset( $screens[ $screen ] ) ? $screens[ $screen ] : $screens['dashboard'];
		if ( 'emails' === $screen ) {
			$title = OLI_ACR_Admin_Emails::title( $title );
		}

		echo '<div class="wrap oli-acr-wrap oli-acr-screen-' . esc_attr( $screen ) . '">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		if ( 'settings' === $screen ) {
			echo ' <a href="' . esc_url( self::setup_url( 1 ) ) . '" class="page-title-action">' . esc_html__( 'Run the setup assistant again', 'oli-abandoned-cart-recovery' ) . '</a>';
		}
		echo '<hr class="wp-header-end">';
		self::nav( $screen );
		self::notice( $msg );
		if ( in_array( $screen, array( 'dashboard', 'settings' ), true ) ) {
			self::status_banner();
		}
		switch ( $screen ) {
			case 'carts':
				OLI_ACR_Admin_Carts::render();
				break;
			case 'emails':
				OLI_ACR_Admin_Emails::render();
				break;
			case 'settings':
				OLI_ACR_Admin_Settings::render();
				break;
			default:
				OLI_ACR_Admin_Dashboard::render();
		}
		echo '</div>';
	}

	/**
	 * Navigation entre les quatre écrans (nav-tab).
	 *
	 * @param string $current Écran courant.
	 * @return void
	 */
	private static function nav( $current ) {
		echo '<nav class="nav-tab-wrapper oli-acr-nav" aria-label="' . esc_attr__( 'Cart recovery screens', 'oli-abandoned-cart-recovery' ) . '">';
		foreach ( self::screens() as $key => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( self::url( $key ) ),
				$key === $current ? ' nav-tab-active' : '',
				$key === $current ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Messages après une action.
	 *
	 * @param string $msg Code.
	 * @return void
	 */
	public static function notice( $msg ) {
		$messages = array(
			'saved'       => array( 'success', __( 'Settings saved.', 'oli-abandoned-cart-recovery' ) ),
			'tpl_saved'   => array( 'success', __( 'Email saved.', 'oli-abandoned-cart-recovery' ) ),
			'tpl_deleted' => array( 'success', __( 'Email deleted.', 'oli-abandoned-cart-recovery' ) ),
			'tpl_on'      => array( 'success', __( 'Email turned on.', 'oli-abandoned-cart-recovery' ) ),
			'tpl_off'     => array( 'success', __( 'Email turned off.', 'oli-abandoned-cart-recovery' ) ),
			'tpl_max'     => array( 'warning', __( 'The sequence already has 5 cart emails.', 'oli-abandoned-cart-recovery' ) ),
			'test_sent'   => array( 'success', __( 'Test email sent. Check the inbox of the address you entered.', 'oli-abandoned-cart-recovery' ) ),
			'test_failed' => array( 'error', __( 'The site cannot send email right now. Check outgoing mail in Settings.', 'oli-abandoned-cart-recovery' ) ),
			'test_bad_to' => array( 'error', __( 'Enter a valid email address for the test.', 'oli-abandoned-cart-recovery' ) ),
			'sent'        => array( 'success', __( 'Reminder sent.', 'oli-abandoned-cart-recovery' ) ),
			'not_sent'    => array( 'warning', __( 'No reminder was sent (no active email left, or the customer unsubscribed).', 'oli-abandoned-cart-recovery' ) ),
			'deleted'     => array( 'success', __( 'Cart(s) deleted.', 'oli-abandoned-cart-recovery' ) ),
			'enabled'     => array( 'success', __( 'Recovery emails are on.', 'oli-abandoned-cart-recovery' ) ),
			'disabled'    => array( 'success', __( 'Recovery emails are off.', 'oli-abandoned-cart-recovery' ) ),
			'activated'   => array( 'success', __( 'Setup complete: recovery emails are on. Shoppers who opted in at checkout may now receive the sequence.', 'oli-abandoned-cart-recovery' ) ),
			'later'       => array( 'info', __( 'Setup saved. Recovery emails stay off until you turn them on.', 'oli-abandoned-cart-recovery' ) ),
			'confirm'     => array( 'error', __( 'Nothing was changed: tick the confirmation to record carts without a checkbox.', 'oli-abandoned-cart-recovery' ) ),
		);
		if ( isset( $messages[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $messages[ $msg ][0] ), esc_html( $messages[ $msg ][1] ) );
		}
	}

	/**
	 * État des relances : 'mail' (envoi bloqué), 'on' ou 'off'.
	 *
	 * @return string
	 */
	public static function status() {
		$failure = get_option( 'oli_acr_mail_failure' );
		if ( is_array( $failure ) && ! empty( $failure['count'] ) ) {
			return 'mail';
		}
		return oli_acr_reminders_on() ? 'on' : 'off';
	}

	/**
	 * Indice « N paniers enregistrés pendant l'arrêt ne seront pas relancés automatiquement » (P3-2).
	 * Vide si N = 0 ou si les relances tournent déjà.
	 *
	 * @param string $tag Balise englobante (span dans le bandeau, p dans l'assistant).
	 * @return string HTML échappé.
	 */
	public static function parked_hint( $tag = 'span' ) {
		if ( oli_acr_reminders_on() ) {
			return '';
		}
		$n = OLI_ACR_Carts::count_parked();
		if ( $n < 1 ) {
			return '';
		}
		$tag = 'p' === $tag ? 'p' : 'span';
		/* translators: %d: number of carts. */
		$text = sprintf( _n( '%d cart saved while emails were off will not be reminded automatically.', '%d carts saved while emails were off will not be reminded automatically.', $n, 'oli-abandoned-cart-recovery' ), $n );
		return ( 'span' === $tag ? ' ' : '' ) . '<' . $tag . ' class="oli-acr-parked">' . esc_html( $text ) . ' <a href="' . esc_url( self::url( 'carts', array( 'view' => 'todo' ) ) ) . '">' . esc_html__( 'View carts', 'oli-abandoned-cart-recovery' ) . '</a></' . $tag . '>';
	}

	/**
	 * Bandeau de statut (A-1) : Tableau de bord et Réglages seulement, absent de l'assistant (CA-ACR-17).
	 * Remplace l'ancien avis rouge d'échec d'envoi (pas de doublon).
	 *
	 * @return void
	 */
	public static function status_banner() {
		$status  = self::status();
		$classes = array(
			'on'   => 'oli-acr-banner-on',
			'off'  => 'oli-acr-banner-off',
			'mail' => 'oli-acr-banner-mail',
		);
		echo '<div id="oli-acr-status" class="oli-acr-banner ' . esc_attr( $classes[ $status ] ) . '" role="status">';
		echo '<p class="oli-acr-banner-text">';
		if ( 'mail' === $status ) {
			echo esc_html__( 'The site could not send an email. Check outgoing mail, then try again.', 'oli-abandoned-cart-recovery' );
		} elseif ( 'on' === $status ) {
			echo '<strong>' . esc_html__( 'Recovery emails are on.', 'oli-abandoned-cart-recovery' ) . '</strong> ' . esc_html__( 'Shoppers who opted in may receive the sequence.', 'oli-abandoned-cart-recovery' );
		} else {
			echo '<strong>' . esc_html__( 'Recovery emails are off.', 'oli-abandoned-cart-recovery' ) . '</strong> ' . esc_html(
				oli_acr_capture_on()
					? __( 'Carts may be saved if the shopper ticks the box at checkout, but no email is sent.', 'oli-abandoned-cart-recovery' )
					: __( 'No messages will be sent.', 'oli-abandoned-cart-recovery' )
			);
			if ( oli_acr_setup_done() ) {
				echo self::parked_hint(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans parked_hint().
			}
		}
		echo '</p><div class="oli-acr-banner-actions">';
		if ( 'mail' === $status ) {
			if ( self::can_settings() ) {
				echo '<a class="button" href="' . esc_url( self::url( 'settings', array( 'advanced' => 1 ) ) . '#oli-acr-sec-sender' ) . '">' . esc_html__( 'Check outgoing mail', 'oli-abandoned-cart-recovery' ) . '</a> ';
			}
			echo '<a class="button" href="' . esc_url( self::url( 'emails', array( 'view' => 'log' ) ) ) . '">' . esc_html__( 'Log', 'oli-abandoned-cart-recovery' ) . '</a> ';
			echo '<a class="button-link" href="' . esc_url( self::dismiss_url( 'mail_failure' ) ) . '">' . esc_html__( 'Hide until the next failure', 'oli-abandoned-cart-recovery' ) . '</a>';
		} elseif ( self::can_settings() ) {
			if ( ! oli_acr_setup_done() ) {
				echo '<a class="button button-primary" href="' . esc_url( self::setup_url( 1 ) ) . '">' . esc_html__( 'Open the setup assistant', 'oli-abandoned-cart-recovery' ) . '</a>';
			} else {
				$on = 'on' === $status;
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'oli_acr_toggle' );
				echo '<input type="hidden" name="action" value="oli_acr_toggle"><input type="hidden" name="to" value="' . ( $on ? 'off' : 'on' ) . '">';
				echo '<input type="hidden" name="back" value="' . esc_attr( self::current_screen() ) . '">';
				echo '<button type="submit" class="button' . ( $on ? '' : ' button-primary' ) . '">' . ( $on ? esc_html__( 'Turn off', 'oli-abandoned-cart-recovery' ) : esc_html__( 'Turn on recovery emails', 'oli-abandoned-cart-recovery' ) ) . '</button>';
				echo '</form>';
			}
		}
		echo '</div></div>';
	}

	/**
	 * Bouton du bandeau : bascule « enabled » (POST + nonce, CA-ACR-17).
	 *
	 * @return void
	 */
	public static function toggle() {
		self::check_cap( true );
		check_admin_referer( 'oli_acr_toggle' );
		$to   = isset( $_POST['to'] ) && 'on' === sanitize_key( wp_unslash( $_POST['to'] ) ) ? 'yes' : 'no';
		$back = isset( $_POST['back'] ) && 'settings' === sanitize_key( wp_unslash( $_POST['back'] ) ) ? 'settings' : 'dashboard';
		if ( 'yes' === $to && ! oli_acr_setup_done() ) {
			wp_safe_redirect( self::setup_url( 1 ) );
			exit;
		}
		$settings            = get_option( 'oli_acr_settings', array() );
		$settings            = is_array( $settings ) ? $settings : array();
		$settings['enabled'] = $to;
		update_option( 'oli_acr_settings', $settings, true );
		delete_option( 'oli_acr_schedule_signature' );
		self::redirect( $back, 'yes' === $to ? 'enabled' : 'disabled' );
	}

	/**
	 * Mémorise le rejet de l'avis Donate pour l'utilisateur courant.
	 *
	 * @return void
	 */
	public static function ajax_dismiss_donate() {
		check_ajax_referer( 'oli_acr_donate', 'nonce' );
		if ( ! current_user_can( OLI_ACR_CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		update_user_meta( get_current_user_id(), 'oli_acr_donate_dismissed', 1 );
		wp_send_json_success();
	}

	/**
	 * Ferme un avis par utilisateur (croix de l'avis, sans rechargement).
	 *
	 * @return void
	 */
	public static function ajax_dismiss() {
		check_ajax_referer( 'oli_acr_dismiss', 'nonce' );
		$notice = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '';
		if ( ! current_user_can( OLI_ACR_CAP ) || ! in_array( $notice, self::USER_NOTICES, true ) ) {
			wp_send_json_error( null, 403 );
		}
		self::dismiss_for_user( $notice );
		wp_send_json_success();
	}

	/**
	 * Mémorise un avis fermé pour l'utilisateur courant.
	 *
	 * @param string $notice Clé.
	 * @return void
	 */
	private static function dismiss_for_user( $notice ) {
		$dismissed            = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		$dismissed            = is_array( $dismissed ) ? $dismissed : array();
		$dismissed[ $notice ] = time();
		update_user_meta( get_current_user_id(), self::DISMISSED_META, $dismissed );
	}

	/**
	 * Nom d'un modèle dans la langue de l'admin (journal, tableau de bord).
	 *
	 * @param string $id ID du modèle.
	 * @return string
	 */
	public static function template_name( $id ) {
		$tpl = OLI_ACR_Templates::get( $id );
		if ( ! $tpl ) {
			return $id;
		}
		$tpl = OLI_ACR_Templates::for_locale( $id, $tpl, OLI_ACR_Lang::admin_language() );
		return (string) $tpl['name'];
	}

	/**
	 * B2 : Polylang (gratuit) seul ne fait pas reconnaître les pages de paiement traduites par WooCommerce.
	 * Aucun avis si « Polylang for WooCommerce » est actif ou si un filtre relie déjà la page de paiement.
	 *
	 * @return bool
	 */
	public static function polylang_needs_wc_bridge() {
		if ( 'polylang' !== OLI_ACR_Lang::adapter()->id() || count( OLI_ACR_Lang::languages() ) < 2 ) {
			return false;
		}
		$bridged = defined( 'PLLWC_VERSION' ) || function_exists( 'PLLWC' ) || has_filter( 'woocommerce_get_checkout_page_id' );
		/**
		 * Filtre : vrai si les pages de paiement traduites par Polylang sont reconnues par WooCommerce.
		 *
		 * @param bool $bridged Détection automatique.
		 */
		return ! apply_filters( 'oli_acr_polylang_checkout_bridged', $bridged );
	}

	/**
	 * Lien « Ne plus afficher » d'un avis (nonce par avis).
	 *
	 * @param string $notice Clé de l'avis.
	 * @return string URL.
	 */
	public static function dismiss_url( $notice ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=oli_acr_dismiss_notice&notice=' . $notice ), 'oli_acr_dismiss_' . $notice );
	}

	/**
	 * Indique si l'utilisateur courant a fermé un avis (avis fermables par utilisateur).
	 *
	 * @param string $notice Clé de l'avis.
	 * @return bool
	 */
	public static function user_dismissed( $notice ) {
		$dismissed = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		return is_array( $dismissed ) && ! empty( $dismissed[ $notice ] );
	}

	/**
	 * Avis du plugin (Guideline 11 : écrans du plugin seulement, aux utilisateurs autorisés).
	 *
	 * - Mise à jour 1.2 (CA-ACR-31) : avis unique, fermable par utilisateur, sans redirection.
	 * - Consentement désactivé (mode « always ») : avertissement Loi 25 visible tant que le mode est actif (CA-ACR-19).
	 * - Polylang sans Polylang for WooCommerce : fermable par utilisateur.
	 * - Migration R2 (1.1.0) : le mode « toujours » est passé au consentement (jusqu'à fermeture).
	 * L'échec d'envoi n'est plus un avis : il passe dans le bandeau de statut (Tableau de bord, Réglages).
	 *
	 * @return void
	 */
	public static function global_notices() {
		if ( ! current_user_can( OLI_ACR_CAP ) || ! self::is_plugin_screen() || self::is_wizard_request() ) {
			return;
		}
		if ( get_option( 'oli_acr_notice_update' ) && ! self::user_dismissed( 'update_120' ) ) {
			// Le lien vers Réglages n'est montré qu'aux personnes qui peuvent l'ouvrir (D7).
			printf(
				'<div class="notice notice-info is-dismissible oli-acr-update-notice" data-oli-acr-notice="update_120"><p>%1$s%2$s</p></div>',
				esc_html__( 'New interface: your settings and recovery emails have been kept.', 'oli-abandoned-cart-recovery' ),
				self::can_settings() ? ' <a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'View settings', 'oli-abandoned-cart-recovery' ) . '</a>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lien échappé ci-contre.
			);
		}
		if ( 'always' === oli_acr_get_setting( 'guest_tracking' ) ) {
			printf(
				'<div class="notice notice-warning oli-acr-consent-off"><p><strong>%1$s</strong> %2$s%3$s</p></div>',
				esc_html__( 'Carts are recorded without a checkbox.', 'oli-abandoned-cart-recovery' ),
				esc_html__( 'Quebec Law 25 and the GDPR generally require explicit consent before saving this data for reminders. You are responsible for having another legal basis.', 'oli-abandoned-cart-recovery' ),
				self::can_settings() ? ' <a href="' . esc_url( self::url( 'settings', array( 'advanced' => 1 ) ) . '#oli-acr-sec-capture' ) . '">' . esc_html__( 'Turn consent back on', 'oli-abandoned-cart-recovery' ) . '</a>' : ''
			);
		}
		if ( self::polylang_needs_wc_bridge() && ! self::user_dismissed( 'polylang_wc' ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible oli-acr-polylang-wc"><p><strong>%1$s</strong> %2$s <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a> | <a href="%5$s">%6$s</a></p></div>',
				esc_html__( 'Oli Abandoned Cart Recovery: carts on translated checkout pages are not captured.', 'oli-abandoned-cart-recovery' ),
				esc_html__( 'Polylang is active without "Polylang for WooCommerce", so WooCommerce does not recognize the translated checkout pages: the capture script is not loaded there and no cart is saved in those languages. Install "Polylang for WooCommerce", or link the translated cart and checkout pages to WooCommerce with the woocommerce_get_checkout_page_id filter (see the plugin FAQ).', 'oli-abandoned-cart-recovery' ),
				esc_url( 'https://polylang.pro/downloads/polylang-for-woocommerce/' ),
				esc_html__( 'Polylang for WooCommerce', 'oli-abandoned-cart-recovery' ),
				esc_url( self::dismiss_url( 'polylang_wc' ) ),
				esc_html__( 'Dismiss', 'oli-abandoned-cart-recovery' )
			);
		}
		// B3 : jamais à côté de l'avertissement « consentement désactivé » (les deux messages se contrediraient).
		if ( get_option( 'oli_acr_notice_consent_migrated' ) && oli_acr_consent_required() ) {
			printf(
				'<div class="notice notice-warning is-dismissible oli-acr-consent-migrated"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a> | <a href="%5$s">%6$s</a></p></div>',
				esc_html__( 'Oli Abandoned Cart Recovery 1.1.0: consent is now required.', 'oli-abandoned-cart-recovery' ),
				esc_html__( 'Your store saved guest carts without consent ("Always" mode). To comply with Quebec Law 25 and the GDPR, the update turned consent on: guests and logged-in customers are now tracked only when they check the consent box at checkout. You can turn it off again in the settings, under your own responsibility.', 'oli-abandoned-cart-recovery' ),
				esc_url( self::url( 'settings' ) ),
				esc_html__( 'Review the settings', 'oli-abandoned-cart-recovery' ),
				esc_url( self::dismiss_url( 'consent_migrated' ) ),
				esc_html__( 'Dismiss', 'oli-abandoned-cart-recovery' )
			);
		}
	}

	/**
	 * Ferme un avis (lien sans JavaScript) : capacité + nonce par avis.
	 *
	 * @return void
	 */
	public static function dismiss_notice() {
		self::check_cap();
		$notice = isset( $_GET['notice'] ) ? sanitize_key( $_GET['notice'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce vérifié juste après, avec la clé de l'avis.
		if ( ! in_array( $notice, array_merge( array( 'consent_migrated', 'mail_failure' ), self::USER_NOTICES ), true ) ) {
			wp_die( esc_html__( 'Unknown notice.', 'oli-abandoned-cart-recovery' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( 'oli_acr_dismiss_' . $notice );
		if ( in_array( $notice, self::USER_NOTICES, true ) ) {
			self::dismiss_for_user( $notice );
		} else {
			delete_option( 'consent_migrated' === $notice ? 'oli_acr_notice_consent_migrated' : 'oli_acr_mail_failure' );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : self::url( 'dashboard' ) );
		exit;
	}

	/**
	 * Avis de soutien : au plus une fois, sur Réglages seulement, fermable (méta utilisateur, CA-ACR-18).
	 * Le rejet est mémorisé par assets/js/admin.js (pas de script inline).
	 *
	 * @return void
	 */
	public static function donate_notice() {
		if ( get_user_meta( get_current_user_id(), 'oli_acr_donate_dismissed', true ) ) {
			return;
		}
		echo '<div class="notice notice-info inline is-dismissible oli-acr-donate-notice"><p>';
		echo wp_kses(
			sprintf(
				/* translators: %s: donate URL */
				__( 'Enjoying this plugin? <a href="%s" target="_blank" rel="noopener noreferrer">Buy me a coffee</a> — your support keeps it free.', 'oli-abandoned-cart-recovery' ),
				esc_url( OLI_ACR_DONATE_URL )
			),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			)
		);
		echo '</p></div>';
	}

	/**
	 * Champ de durée (nombre + unité), avec libellé accessible.
	 *
	 * @param string               $name  Nom du champ.
	 * @param array<string, mixed> $value Valeur.
	 * @param string               $id    ID du champ nombre (pour un <label for>).
	 * @return void
	 */
	public static function duration_field( $name, $value, $id = '' ) {
		$value = wp_parse_args(
			(array) $value,
			array(
				'value' => 0,
				'unit'  => 'minutes',
			)
		);
		$id = '' !== $id ? $id : 'oli-acr-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
		printf( '<span class="oli-acr-duration"><input type="number" min="0" class="small-text" id="%1$s" name="%2$s[value]" value="%3$s"> ', esc_attr( $id ), esc_attr( $name ), esc_attr( $value['value'] ) );
		printf( '<select name="%1$s[unit]" aria-label="%2$s">', esc_attr( $name ), esc_attr__( 'Unit', 'oli-abandoned-cart-recovery' ) );
		foreach ( oli_acr_duration_units() as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value['unit'], $key, false ), esc_html( $label ) );
		}
		echo '</select></span>';
	}

	/**
	 * Case oui/non.
	 *
	 * @param string $name  Nom.
	 * @param string $value Valeur.
	 * @param string $label Libellé.
	 * @return void
	 */
	public static function checkbox( $name, $value, $label ) {
		printf( '<label><input type="checkbox" name="%s" value="yes"%s> %s</label>', esc_attr( $name ), checked( 'yes', $value, false ), esc_html( $label ) );
	}

	/**
	 * Date relative (« il y a 2 h »), date exacte en title.
	 *
	 * @param string $date Date MySQL (UTC).
	 * @return string HTML.
	 */
	public static function relative_date( $date ) {
		if ( empty( $date ) || 0 === strpos( (string) $date, '0000' ) ) {
			return '—';
		}
		$ts = (int) strtotime( $date . ' UTC' );
		if ( $ts <= 0 ) {
			return '—';
		}
		$diff = time() - $ts;
		if ( $diff >= 0 ) {
			/* translators: %s: human-readable time difference, e.g. 2 hours. */
			$text = sprintf( __( '%s ago', 'oli-abandoned-cart-recovery' ), human_time_diff( $ts ) );
		} else {
			/* translators: %s: human-readable time difference, e.g. 2 hours. */
			$text = sprintf( __( 'in %s', 'oli-abandoned-cart-recovery' ), human_time_diff( $ts ) );
		}
		return '<time datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '" title="' . esc_attr( oli_acr_format_datetime( $ts ) ) . '">' . esc_html( $text ) . '</time>';
	}
}
