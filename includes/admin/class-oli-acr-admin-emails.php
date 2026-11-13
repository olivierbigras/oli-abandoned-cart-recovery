<?php
/**
 * Écran Courriels (A-4, A-5) : séquence, éditeur avec aperçu, test et journal.
 *
 * @package OliAbandonedCartRecovery
 * @author  Olivier Bigras (bigrat95)
 * @link    https://olivierbigras.com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Courriels.
 */
class OLI_ACR_Admin_Emails {

	/**
	 * Plafond raisonnable de relances panier dans la séquence.
	 */
	const MAX_CART_EMAILS = 5;

	/**
	 * Accroches.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_oli_acr_save_template', array( __CLASS__, 'save_template' ) );
		add_action( 'admin_post_oli_acr_delete_template', array( __CLASS__, 'delete_template' ) );
		add_action( 'admin_post_oli_acr_toggle_template', array( __CLASS__, 'toggle_template' ) );
		add_action( 'admin_post_oli_acr_test_email', array( __CLASS__, 'test_email' ) );
	}

	/**
	 * Modèles d'un type, triés par délai (actifs et inactifs).
	 *
	 * @param string $type cart | order.
	 * @return array<string, array<mixed>>
	 */
	public static function sequence( $type = 'cart' ) {
		$list = array();
		foreach ( OLI_ACR_Templates::all() as $id => $tpl ) {
			if ( $type === $tpl['type'] ) {
				$list[ (string) $id ] = $tpl;
			}
		}
		uasort(
			$list,
			static function ( $a, $b ) {
				return oli_acr_duration_to_seconds( $a['delay'] ) <=> oli_acr_duration_to_seconds( $b['delay'] );
			}
		);
		return $list;
	}

	/**
	 * Titre d'une relance dans la séquence (« Relance 1 · Rappel, sans rabais » pour les modèles par défaut).
	 *
	 * @param string       $id    ID.
	 * @param array<mixed> $tpl   Modèle résolu dans la langue de l'admin.
	 * @param int          $index Position (1, 2…).
	 * @return string
	 */
	public static function card_title( $id, $tpl, $index ) {
		$name = (string) $tpl['name'];
		if ( 'tpl_cart_1' === $id && OLI_ACR_Templates::is_default_field( $id, 'name', $name ) ) {
			return __( 'Email 1 · Reminder, no discount', 'oli-abandoned-cart-recovery' );
		}
		if ( 'tpl_cart_2' === $id && OLI_ACR_Templates::is_default_field( $id, 'name', $name ) ) {
			return __( 'Email 2 · Optional, with coupon', 'oli-abandoned-cart-recovery' );
		}
		if ( 'order' === $tpl['type'] ) {
			return $name;
		}
		/* translators: 1: position in the sequence, 2: email name. */
		return sprintf( __( 'Email %1$d · %2$s', 'oli-abandoned-cart-recovery' ), $index, $name );
	}

	/**
	 * Ligne d'état d'une relance : « Active · 1 heure après l'abandon · coupon 10 % disponible ».
	 *
	 * @param array<mixed> $tpl Modèle.
	 * @return string
	 */
	public static function card_meta( $tpl ) {
		$parts   = array();
		$parts[] = 'yes' === $tpl['active'] ? __( 'Active', 'oli-abandoned-cart-recovery' ) : __( 'Inactive', 'oli-abandoned-cart-recovery' );
		if ( 'order' === $tpl['type'] ) {
			/* translators: %s: delay, e.g. 1 hour. */
			$parts[] = sprintf( __( '%s after the pending order delay', 'oli-abandoned-cart-recovery' ), oli_acr_duration_label( $tpl['delay'] ) );
		} else {
			/* translators: %s: delay, e.g. 1 hour. */
			$parts[] = sprintf( __( '%s after the cart is abandoned', 'oli-abandoned-cart-recovery' ), oli_acr_duration_label( $tpl['delay'] ) );
		}
		if ( 'yes' === $tpl['coupon_enabled'] ) {
			$amount = wc_format_localized_decimal( $tpl['coupon_amount'] ) . ( 'percent' === $tpl['coupon_type'] ? ' %' : ' ' . html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) );
			/* translators: %s: coupon amount, e.g. 10 %. */
			$parts[] = sprintf( __( 'coupon %s available', 'oli-abandoned-cart-recovery' ), $amount );
		} else {
			$parts[] = __( 'no coupon', 'oli-abandoned-cart-recovery' );
		}
		return implode( ' · ', $parts );
	}

	/**
	 * Titre de l'écran (« Modifier la relance » dans l'éditeur).
	 *
	 * @param string $default Titre par défaut.
	 * @return string
	 */
	public static function title( $default ) {
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation.
		if ( '' === $edit ) {
			return $default;
		}
		return 'new' === $edit ? __( 'New email', 'oli-abandoned-cart-recovery' ) : __( 'Edit email', 'oli-abandoned-cart-recovery' );
	}

	/**
	 * Langue des textes affichés : paramètre lang s'il fait partie des langues de l'interface, sinon langue de repli.
	 *
	 * @return string
	 */
	public static function tab_language() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture seule d'un onglet.
		$lang = isset( $_GET['lang'] ) ? OLI_ACR_Lang::normalize( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) ) : '';
		$ui   = OLI_ACR_Lang::ui_languages();
		if ( '' !== $lang && in_array( $lang, $ui, true ) ) {
			return $lang;
		}
		$admin = OLI_ACR_Lang::admin_language();
		return in_array( $admin, $ui, true ) ? $admin : OLI_ACR_Lang::fallback_language();
	}

	/**
	 * Affiche l'écran.
	 *
	 * @return void
	 */
	public static function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navigation.
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		$view = isset( $_GET['view'] ) && 'log' === sanitize_key( wp_unslash( $_GET['view'] ) ) ? 'log' : 'sequence';
		// phpcs:enable
		if ( '' !== $edit ) {
			$tpl = 'new' === $edit ? null : OLI_ACR_Templates::get( $edit );
			self::render_editor( $tpl );
			return;
		}
		global $wpdb;
		$log = oli_acr_table( 'log' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nom de table fixe (oli_acr_table()).
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log}" );
		printf(
			'<ul class="subsubsub"><li><a href="%1$s"%2$s>%3$s</a> | </li><li><a href="%4$s"%5$s>%6$s <span class="count">(%7$s)</span></a></li></ul><br class="clear">',
			esc_url( OLI_ACR_Admin::url( 'emails' ) ),
			'sequence' === $view ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Sequence', 'oli-abandoned-cart-recovery' ),
			esc_url( OLI_ACR_Admin::url( 'emails', array( 'view' => 'log' ) ) ),
			'log' === $view ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Log', 'oli-abandoned-cart-recovery' ),
			esc_html( number_format_i18n( $count ) )
		);
		if ( 'log' === $view ) {
			$table = new OLI_ACR_Log_Table();
			$table->prepare_items();
			echo '<div class="oli-acr-scroll">';
			$table->display();
			echo '</div>';
			return;
		}
		self::render_sequence();
	}

	/**
	 * Séquence : frise verticale des relances panier, puis bloc séparé « Commandes en attente » si activé.
	 *
	 * @return void
	 */
	private static function render_sequence() {
		$lang = OLI_ACR_Lang::admin_language();
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: delay, e.g. 1 hour. */
				__( 'Delays are counted from the moment the cart is considered abandoned (%s without activity).', 'oli-abandoned-cart-recovery' ),
				oli_acr_duration_label( oli_acr_get_setting( 'abandon_after' ) )
			)
		) . '</p>';
		$cart = self::sequence( 'cart' );
		echo '<ol class="oli-acr-sequence">';
		$i = 0;
		foreach ( $cart as $id => $tpl ) {
			++$i;
			self::render_card( (string) $id, OLI_ACR_Templates::for_locale( (string) $id, $tpl, $lang ), $i );
		}
		echo '</ol>';
		if ( count( $cart ) < self::MAX_CART_EMAILS ) {
			echo '<p><a class="button" href="' . esc_url( OLI_ACR_Admin::url( 'emails', array( 'edit' => 'new' ) ) ) . '">' . esc_html__( 'Add an email', 'oli-abandoned-cart-recovery' ) . '</a></p>';
		}
		if ( 'yes' === oli_acr_get_setting( 'pending_enabled' ) ) {
			echo '<h2 class="oli-acr-section-title">' . esc_html__( 'Pending orders', 'oli-abandoned-cart-recovery' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Sent to unpaid orders, counted from the pending order delay set in Settings.', 'oli-abandoned-cart-recovery' ) . '</p>';
			echo '<ol class="oli-acr-sequence oli-acr-sequence-orders">';
			$i = 0;
			foreach ( self::sequence( 'order' ) as $id => $tpl ) {
				++$i;
				self::render_card( (string) $id, OLI_ACR_Templates::for_locale( (string) $id, $tpl, $lang ), $i );
			}
			echo '</ol><p><a class="button" href="' . esc_url(
				OLI_ACR_Admin::url(
					'emails',
					array(
						'edit' => 'new',
						'type' => 'order',
					)
				)
			) . '">' . esc_html__( 'Add a pending order email', 'oli-abandoned-cart-recovery' ) . '</a></p>';
		}
	}

	/**
	 * Carte d'une relance : interrupteur, délai, objet, pastille, Modifier, Aperçu.
	 *
	 * @param string       $id    ID.
	 * @param array<mixed> $tpl   Modèle résolu.
	 * @param int          $index Position.
	 * @return void
	 */
	private static function render_card( $id, $tpl, $index ) {
		$active = 'yes' === $tpl['active'];
		$edit   = OLI_ACR_Admin::url( 'emails', array( 'edit' => $id ) );
		echo '<li class="oli-acr-step' . ( $active ? ' is-active' : '' ) . '">';
		echo '<span class="oli-acr-step-num" aria-hidden="true">' . esc_html( (string) $index ) . '</span>';
		echo '<div class="oli-acr-step-body"><h3>' . esc_html( self::card_title( $id, $tpl, $index ) ) . '</h3>';
		echo '<p>' . esc_html( self::card_meta( $tpl ) ) . '</p>';
		/* translators: %s: email subject. */
		echo '<p>' . esc_html( sprintf( __( 'Subject: %s', 'oli-abandoned-cart-recovery' ), (string) $tpl['subject'] ) ) . '</p>';
		echo wp_kses_post( self::coupon_missing_warning( $id, OLI_ACR_Templates::get( $id ) ) );
		echo '</div><div class="oli-acr-step-actions">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oli_acr_toggle_template_' . $id );
		echo '<input type="hidden" name="action" value="oli_acr_toggle_template"><input type="hidden" name="template" value="' . esc_attr( $id ) . '"><input type="hidden" name="active" value="' . ( $active ? 'no' : 'yes' ) . '">';
		printf(
			'<button type="submit" class="button oli-acr-switch" role="switch" aria-checked="%1$s" aria-label="%2$s"><span class="oli-acr-switch-track" aria-hidden="true"></span>%3$s</button>',
			$active ? 'true' : 'false',
			esc_attr( sprintf( /* translators: %s: email name. */ __( 'Send automatically: %s', 'oli-abandoned-cart-recovery' ), self::card_title( $id, $tpl, $index ) ) ),
			$active ? esc_html__( 'On', 'oli-abandoned-cart-recovery' ) : esc_html__( 'Off', 'oli-abandoned-cart-recovery' )
		);
		echo '</form>';
		echo '<a class="button" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'oli-abandoned-cart-recovery' ) . '</a>';
		echo '<a class="button" href="' . esc_url( $edit . '#oli-acr-preview' ) . '">' . esc_html__( 'Preview', 'oli-abandoned-cart-recovery' ) . '</a>';
		echo '</div></li>';
	}

	/**
	 * Onglets de langue (site vraiment multilingue seulement, D4) : nom de la langue, sans code visible.
	 *
	 * @param string $base    URL de base.
	 * @param string $current Langue affichée.
	 * @return void
	 */
	public static function language_tabs( $base, $current ) {
		$languages = OLI_ACR_Lang::ui_languages();
		if ( count( $languages ) < 2 ) {
			return;
		}
		echo '<nav class="nav-tab-wrapper oli-acr-lang-tabs" aria-label="' . esc_attr__( 'Language', 'oli-abandoned-cart-recovery' ) . '">';
		foreach ( $languages as $locale ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s" hreflang="%3$s"%4$s>%5$s</a>',
				esc_url( add_query_arg( 'lang', $locale, $base ) ),
				$locale === $current ? ' nav-tab-active' : '',
				esc_attr( str_replace( '_', '-', $locale ) ),
				$locale === $current ? ' aria-current="page"' : '',
				esc_html( OLI_ACR_Lang::label( $locale ) )
			);
		}
		echo '</nav>';
	}

	/**
	 * Éditeur : champs à gauche, aperçu rendu à droite, test d'envoi (A-5).
	 *
	 * @param array<string, mixed>|null $tpl Modèle (null = nouveau).
	 * @return void
	 */
	private static function render_editor( $tpl ) {
		$is_new = ! $tpl;
		if ( $is_new ) {
			$tpl = OLI_ACR_Templates::blank();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Type du nouveau modèle (affichage).
			if ( isset( $_GET['type'] ) && 'order' === sanitize_key( wp_unslash( $_GET['type'] ) ) && 'yes' === oli_acr_get_setting( 'pending_enabled' ) ) {
				$tpl['type'] = 'order';
			}
		}
		$lang     = self::tab_language();
		$own      = isset( $tpl['texts'][ $lang ] ) && is_array( $tpl['texts'][ $lang ] ) ? $tpl['texts'][ $lang ] : array();
		$resolved = $tpl['id'] ? OLI_ACR_Templates::for_locale( (string) $tpl['id'], $tpl, $lang ) : $tpl;
		$value    = static function ( $field ) use ( $own, $resolved ) {
			return isset( $own[ $field ] ) && '' !== (string) $own[ $field ] ? (string) $own[ $field ] : (string) $resolved[ $field ];
		};
		$field    = 't[texts][' . $lang . ']';
		$html_lang = str_replace( '_', '-', $lang );

		echo '<p><a href="' . esc_url( OLI_ACR_Admin::url( 'emails' ) ) . '">&larr; ' . esc_html__( 'Back to the sequence', 'oli-abandoned-cart-recovery' ) . '</a></p>';
		self::language_tabs( OLI_ACR_Admin::url( 'emails', array( 'edit' => $tpl['id'] ? $tpl['id'] : 'new' ) ), $lang );
		echo '<div class="oli-acr-editor">';
		echo '<div class="oli-acr-editor-fields">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'oli_acr_save_template' );
		echo '<input type="hidden" name="action" value="oli_acr_save_template"><input type="hidden" name="t[id]" value="' . esc_attr( $tpl['id'] ) . '"><input type="hidden" name="t[lang]" value="' . esc_attr( $lang ) . '">';
		if ( $tpl['id'] && count( OLI_ACR_Lang::ui_languages() ) > 1 && array() === array_filter( array_map( 'strval', $own ) ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html(
				sprintf(
					/* translators: %s: language name. */
					__( 'No text saved yet for %s. The fields show what is sent in this language now (string translation, translated default text or fallback language). Save to keep your own version.', 'oli-abandoned-cart-recovery' ),
					OLI_ACR_Lang::label( $lang )
				)
			) . '</p></div>';
		}
		echo '<table class="form-table" role="presentation" lang="' . esc_attr( $html_lang ) . '">';
		printf( '<tr><th scope="row"><label for="oli-acr-t-subject">%s</label></th><td><input type="text" required class="large-text" id="oli-acr-t-subject" name="%s[subject]" value="%s"></td></tr>', esc_html__( 'Subject', 'oli-abandoned-cart-recovery' ), esc_attr( $field ), esc_attr( $value( 'subject' ) ) );
		printf( '<tr><th scope="row"><label for="oli-acr-t-heading">%s</label></th><td><input type="text" class="large-text" id="oli-acr-t-heading" name="%s[heading]" value="%s"></td></tr>', esc_html__( 'Heading', 'oli-abandoned-cart-recovery' ), esc_attr( $field ), esc_attr( $value( 'heading' ) ) );
		printf( '<tr><th scope="row"><label for="oli-acr-t-button">%s</label></th><td><input type="text" class="regular-text" id="oli-acr-t-button" name="%s[button_label]" value="%s"></td></tr>', esc_html__( 'Button', 'oli-abandoned-cart-recovery' ), esc_attr( $field ), esc_attr( $value( 'button_label' ) ) );
		echo '<tr><th scope="row"><label for="oli_acr_content">' . esc_html__( 'Content', 'oli-abandoned-cart-recovery' ) . '</label></th><td>';
		wp_editor(
			$value( 'content' ),
			'oli_acr_content',
			array(
				'textarea_name' => $field . '[content]',
				'textarea_rows' => 12,
				'media_buttons' => false,
			)
		);
		$insert = array(
			'{first_name}'       => __( 'First name', 'oli-abandoned-cart-recovery' ),
			'{cart_items}'       => __( 'Items', 'oli-abandoned-cart-recovery' ),
			'{recovery_button}'  => __( 'Button', 'oli-abandoned-cart-recovery' ),
			'{coupon}'           => __( 'Coupon', 'oli-abandoned-cart-recovery' ),
			'{unsubscribe_link}' => __( 'Unsubscribe link', 'oli-abandoned-cart-recovery' ),
		);
		echo '<p class="oli-acr-insert"><span>' . esc_html__( 'Insert:', 'oli-abandoned-cart-recovery' ) . '</span> ';
		foreach ( $insert as $tag => $label ) {
			printf( '<button type="button" class="button-link oli-acr-insert-tag" data-tag="%1$s">%2$s</button> ', esc_attr( $tag ), esc_html( $label ) );
		}
		echo '</p><details class="oli-acr-placeholders"><summary>' . esc_html__( 'All placeholders', 'oli-abandoned-cart-recovery' ) . '</summary><ul>';
		foreach ( OLI_ACR_Mailer::placeholders() as $tag => $label ) {
			printf( '<li><code>%s</code> %s</li>', esc_html( $tag ), esc_html( $label ) );
		}
		echo '</ul></details></td></tr>';
		printf( '<tr><th scope="row"><label for="oli-acr-t-name">%s</label></th><td><input type="text" required class="regular-text" id="oli-acr-t-name" name="%s[name]" value="%s"><p class="description">%s</p></td></tr>', esc_html__( 'Internal name', 'oli-abandoned-cart-recovery' ), esc_attr( $field ), esc_attr( $value( 'name' ) ), esc_html__( 'Shown in the sequence and the log, never to customers.', 'oli-abandoned-cart-recovery' ) );
		echo '</table>';

		echo '<h2>' . esc_html__( 'Delay and coupon', 'oli-abandoned-cart-recovery' ) . '</h2><table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Status', 'oli-abandoned-cart-recovery' ) . '</th><td><label><input type="checkbox" name="t[active]" value="1"' . checked( 'yes', $tpl['active'], false ) . '> ' . esc_html__( 'Send this email automatically', 'oli-abandoned-cart-recovery' ) . '</label></td></tr>';
		if ( 'yes' === oli_acr_get_setting( 'pending_enabled' ) || 'order' === $tpl['type'] ) {
			echo '<tr><th scope="row"><label for="oli-acr-t-type">' . esc_html__( 'Type', 'oli-abandoned-cart-recovery' ) . '</label></th><td><select id="oli-acr-t-type" name="t[type]"><option value="cart"' . selected( 'cart', $tpl['type'], false ) . '>' . esc_html__( 'Abandoned cart', 'oli-abandoned-cart-recovery' ) . '</option><option value="order"' . selected( 'order', $tpl['type'], false ) . '>' . esc_html__( 'Pending order', 'oli-abandoned-cart-recovery' ) . '</option></select></td></tr>';
		} else {
			echo '<input type="hidden" name="t[type]" value="cart">';
		}
		echo '<tr><th scope="row"><label for="oli-acr-t-delay">' . esc_html__( 'Send after', 'oli-abandoned-cart-recovery' ) . '</label></th><td>';
		OLI_ACR_Admin::duration_field( 't[delay]', $tpl['delay'], 'oli-acr-t-delay' );
		echo '</td></tr>';
		printf( '<tr><th scope="row"><label for="oli-acr-t-reply">%s</label></th><td><input type="email" class="regular-text" id="oli-acr-t-reply" name="t[reply_to]" value="%s" placeholder="%s"></td></tr>', esc_html__( 'Reply-to address', 'oli-abandoned-cart-recovery' ), esc_attr( $tpl['reply_to'] ), esc_attr( (string) oli_acr_get_setting( 'reply_to' ) ) );
		echo '<tr><th scope="row">' . esc_html__( 'Coupon', 'oli-abandoned-cart-recovery' ) . '</th><td><label><input type="checkbox" name="t[coupon_enabled]" value="1"' . checked( 'yes', $tpl['coupon_enabled'], false ) . '> ' . esc_html__( 'Create a unique single-use coupon for each email ({coupon})', 'oli-abandoned-cart-recovery' ) . '</label><br>';
		printf( '<input type="number" step="0.01" min="0" class="small-text" name="t[coupon_amount]" value="%s" aria-label="%s"> ', esc_attr( $tpl['coupon_amount'] ), esc_attr__( 'Coupon amount', 'oli-abandoned-cart-recovery' ) );
		echo '<select name="t[coupon_type]" aria-label="' . esc_attr__( 'Coupon type', 'oli-abandoned-cart-recovery' ) . '"><option value="percent"' . selected( 'percent', $tpl['coupon_type'], false ) . '>' . esc_html__( 'Percentage discount', 'oli-abandoned-cart-recovery' ) . '</option><option value="fixed_cart"' . selected( 'fixed_cart', $tpl['coupon_type'], false ) . '>' . esc_html__( 'Fixed cart discount', 'oli-abandoned-cart-recovery' ) . '</option></select> ';
		printf( '<label>%s <input type="number" min="0" class="small-text" name="t[coupon_validity]" value="%d"> %s</label>', esc_html__( 'valid for', 'oli-abandoned-cart-recovery' ), (int) $tpl['coupon_validity'], esc_html__( 'days (0 = no expiry)', 'oli-abandoned-cart-recovery' ) );
		echo wp_kses_post( self::coupon_missing_warning( (string) $tpl['id'], $tpl ) );
		echo '</td></tr></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save email', 'oli-abandoned-cart-recovery' ) . '</button>';
		if ( $tpl['id'] ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=oli_acr_delete_template&template=' . $tpl['id'] ), 'oli_acr_delete_template' );
			echo ' <a class="button-link oli-acr-delete oli-acr-confirm" href="' . esc_url( $delete ) . '">' . esc_html__( 'Delete this email', 'oli-abandoned-cart-recovery' ) . '</a>';
		}
		echo '</p></form>';
		if ( $tpl['id'] ) {
			self::render_test_form( (string) $tpl['id'], $lang, 'editor' );
		}
		echo '</div>';

		echo '<div class="oli-acr-editor-preview" id="oli-acr-preview">';
		if ( $is_new ) {
			echo '<div class="oli-acr-card"><div class="oli-acr-card-body"><p>' . esc_html__( 'Save the email to see its preview.', 'oli-abandoned-cart-recovery' ) . '</p></div></div>';
		} else {
			$preview = OLI_ACR_Mailer::preview( $tpl, $lang );
			echo '<div class="oli-acr-card"><div class="oli-acr-card-head"><h2>' . esc_html(
				count( OLI_ACR_Lang::ui_languages() ) > 1
					/* translators: %s: language name. */
					? sprintf( __( 'Preview · %s', 'oli-abandoned-cart-recovery' ), OLI_ACR_Lang::label( $lang ) )
					: __( 'Preview', 'oli-abandoned-cart-recovery' )
			) . '</h2></div>';
			/* translators: %s: email subject. */
			echo '<p class="oli-acr-preview-subject">' . esc_html( sprintf( __( 'Subject: %s', 'oli-abandoned-cart-recovery' ), $preview['subject'] ) ) . '</p>';
			echo '<iframe class="oli-acr-preview-frame" sandbox="" title="' . esc_attr__( 'Email preview', 'oli-abandoned-cart-recovery' ) . '" srcdoc="' . esc_attr( $preview['html'] ) . '"></iframe>';
			echo '<p class="description oli-acr-preview-note">' . esc_html__( 'Sample data: no email is sent. Save to refresh the preview.', 'oli-abandoned-cart-recovery' ) . '</p></div>';
		}
		echo '</div></div>';
	}

	/**
	 * Formulaire « Envoyer un test » (adresse + bouton).
	 *
	 * @param string $template_id Modèle (vide = première relance active).
	 * @param string $lang        Langue.
	 * @param string $back        editor | settings.
	 * @return void
	 */
	public static function render_test_form( $template_id, $lang, $back ) {
		echo '<h2>' . esc_html__( 'Send a test', 'oli-abandoned-cart-recovery' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="oli-acr-test">';
		wp_nonce_field( 'oli_acr_test_email' );
		echo '<input type="hidden" name="action" value="oli_acr_test_email"><input type="hidden" name="template" value="' . esc_attr( $template_id ) . '"><input type="hidden" name="lang" value="' . esc_attr( $lang ) . '"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
		echo '<label class="screen-reader-text" for="oli-acr-test-to-' . esc_attr( $back ) . '">' . esc_html__( 'Test email address', 'oli-abandoned-cart-recovery' ) . '</label>';
		echo '<input type="email" id="oli-acr-test-to-' . esc_attr( $back ) . '" name="to" required class="regular-text" value="' . esc_attr( wp_get_current_user()->user_email ) . '"> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Send a test', 'oli-abandoned-cart-recovery' ) . '</button>';
		echo '</form>';
	}

	/**
	 * B4 : avertissement quand le coupon est activé mais que le courriel ne montre pas son code.
	 *
	 * @param string            $id  ID du modèle.
	 * @param array<mixed>|null $tpl Modèle (non résolu).
	 * @return string HTML (vide si tout va bien).
	 */
	public static function coupon_missing_warning( $id, $tpl ) {
		if ( ! is_array( $tpl ) ) {
			return '';
		}
		$missing = array_values( array_intersect( OLI_ACR_Templates::coupon_missing_locales( $id, $tpl ), OLI_ACR_Lang::ui_languages() ) );
		if ( empty( $missing ) ) {
			return '';
		}
		$text = count( OLI_ACR_Lang::ui_languages() ) > 1
			/* translators: %s: list of languages. */
			? sprintf( __( 'the email does not contain {coupon} or {coupon_code} (%s). Add one of these tags to the content to send the coupon.', 'oli-abandoned-cart-recovery' ), implode( ', ', array_map( array( 'OLI_ACR_Lang', 'label' ), $missing ) ) )
			: __( 'the email does not contain {coupon} or {coupon_code}. Add one of these tags to the content to send the coupon.', 'oli-abandoned-cart-recovery' );
		return '<p class="oli-acr-coupon-missing"><strong>' . esc_html__( 'No coupon will be created:', 'oli-abandoned-cart-recovery' ) . '</strong> ' . esc_html( $text ) . '</p>';
	}

	/**
	 * Enregistre un modèle.
	 *
	 * @return void
	 */
	public static function save_template() {
		OLI_ACR_Admin::check_cap();
		check_admin_referer( 'oli_acr_save_template' );
		$raw      = isset( $_POST['t'] ) && is_array( $_POST['t'] ) ? wp_unslash( $_POST['t'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé par OLI_ACR_Templates::sanitize().
		$existing = ! empty( $raw['id'] ) ? OLI_ACR_Templates::get( sanitize_key( $raw['id'] ) ) : null;
		$lang     = isset( $raw['lang'] ) ? OLI_ACR_Lang::normalize( sanitize_text_field( $raw['lang'] ) ) : '';
		$lang     = '' !== $lang ? $lang : OLI_ACR_Lang::fallback_language();
		if ( ! $existing && ( ! isset( $raw['type'] ) || 'order' !== $raw['type'] ) && count( self::sequence( 'cart' ) ) >= self::MAX_CART_EMAILS ) {
			OLI_ACR_Admin::redirect( 'emails', 'tpl_max' );
		}
		$tpl = OLI_ACR_Templates::sanitize( $raw, $existing );
		if ( $existing && ( ! isset( $existing['texts'][ $lang ] ) || array() === array_filter( array_map( 'strval', (array) $existing['texts'][ $lang ] ) ) ) && isset( $tpl['texts'][ $lang ] ) ) {
			// Langue sans texte propre : les champs affichaient le texte envoyé actuellement ; s'ils n'ont pas
			// été modifiés, on ne les fige pas (la langue continue de suivre traductions et repli).
			$resolved = OLI_ACR_Templates::for_locale( (string) $existing['id'], $existing, $lang );
			$same     = true;
			foreach ( OLI_ACR_Templates::TEXT_FIELDS as $field ) {
				if ( oli_acr_normalize_text( wpautop( (string) $tpl['texts'][ $lang ][ $field ] ) ) !== oli_acr_normalize_text( wpautop( (string) $resolved[ $field ] ) ) ) {
					$same = false;
					break;
				}
			}
			if ( $same ) {
				unset( $tpl['texts'][ $lang ] );
				$tpl = OLI_ACR_Templates::mirror_fallback( $tpl );
			}
		}
		$id = OLI_ACR_Templates::save( $tpl );
		OLI_ACR_Lang::register_strings();
		$args = array( 'edit' => $id );
		if ( count( OLI_ACR_Lang::ui_languages() ) > 1 ) {
			$args['lang'] = $lang;
		}
		OLI_ACR_Admin::redirect( 'emails', 'tpl_saved', $args );
	}

	/**
	 * Interrupteur d'une relance (carte de la séquence).
	 *
	 * @return void
	 */
	public static function toggle_template() {
		OLI_ACR_Admin::check_cap();
		$id = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
		check_admin_referer( 'oli_acr_toggle_template_' . $id );
		$tpl = OLI_ACR_Templates::get( $id );
		if ( ! $tpl ) {
			OLI_ACR_Admin::redirect( 'emails', '' );
		}
		$tpl['active'] = isset( $_POST['active'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['active'] ) ) ? 'yes' : 'no';
		OLI_ACR_Templates::save( $tpl );
		OLI_ACR_Admin::redirect( 'emails', 'yes' === $tpl['active'] ? 'tpl_on' : 'tpl_off' );
	}

	/**
	 * Supprime un modèle.
	 *
	 * @return void
	 */
	public static function delete_template() {
		OLI_ACR_Admin::check_cap();
		check_admin_referer( 'oli_acr_delete_template' );
		$id = isset( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : '';
		OLI_ACR_Templates::delete( $id );
		OLI_ACR_Admin::redirect( 'emails', 'tpl_deleted' );
	}

	/**
	 * Premier modèle panier actif (sinon le premier modèle panier) pour un test sans modèle choisi.
	 *
	 * @return array<mixed>|null
	 */
	public static function default_test_template() {
		$active = OLI_ACR_Templates::active( 'cart' );
		if ( $active ) {
			return reset( $active );
		}
		$all = self::sequence( 'cart' );
		return $all ? reset( $all ) : null;
	}

	/**
	 * Envoie un courriel test (le message ne sort que si le site sait envoyer).
	 *
	 * @param string $to          Adresse.
	 * @param string $template_id Modèle (vide = première relance).
	 * @param string $lang        Langue.
	 * @return string Code du message : test_sent | test_failed | test_bad_to.
	 */
	public static function send_test( $to, $template_id = '', $lang = '' ) {
		if ( ! is_email( $to ) ) {
			return 'test_bad_to';
		}
		$tpl = '' !== $template_id ? OLI_ACR_Templates::get( $template_id ) : self::default_test_template();
		if ( ! $tpl ) {
			return 'test_failed';
		}
		$lang = '' !== $lang ? $lang : OLI_ACR_Lang::admin_language();
		return OLI_ACR_Mailer::send_test( $to, $tpl, $lang ) ? 'test_sent' : 'test_failed';
	}

	/**
	 * Bouton « Envoyer un test » (éditeur ou Réglages) : message de succès ou d'échec lisible (CA-ACR-14).
	 *
	 * @return void
	 */
	public static function test_email() {
		OLI_ACR_Admin::check_cap();
		check_admin_referer( 'oli_acr_test_email' );
		$to   = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$id   = isset( $_POST['template'] ) ? sanitize_key( $_POST['template'] ) : '';
		$lang = isset( $_POST['lang'] ) ? OLI_ACR_Lang::normalize( sanitize_text_field( wp_unslash( $_POST['lang'] ) ) ) : '';
		$back = isset( $_POST['back'] ) ? sanitize_key( wp_unslash( $_POST['back'] ) ) : 'editor';
		$code = self::send_test( $to, $id, $lang );
		if ( 'settings' === $back && OLI_ACR_Admin::can_settings() ) {
			OLI_ACR_Admin::redirect( 'settings', $code, array( 'advanced' => 1 ), 'oli-acr-sec-sender' );
		}
		$args = '' !== $id ? array( 'edit' => $id ) : array();
		if ( '' !== $lang && count( OLI_ACR_Lang::ui_languages() ) > 1 ) {
			$args['lang'] = $lang;
		}
		OLI_ACR_Admin::redirect( 'emails', $code, $args );
	}
}
