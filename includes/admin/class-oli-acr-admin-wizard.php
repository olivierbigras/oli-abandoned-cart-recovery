<?php
/**
 * Assistant de configuration (D2, A-7) : 4 étapes, relances à l'arrêt jusqu'à « Activer les relances ».
 *
 * Affiché au premier lancement d'une NOUVELLE installation (oli_acr_setup = pending), ou sur demande
 * (« Relancer l'assistant de configuration »). Une mise à jour depuis une 1.1.x ne le repasse pas (§8.1).
 *
 * @package OliAbandonedCartRecovery
 * @author  Olivier Bigras (bigrat95)
 * @link    https://olivierbigras.com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Assistant.
 */
class OLI_ACR_Admin_Wizard {

	/**
	 * Accroches.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_oli_acr_wizard', array( __CLASS__, 'save' ) );
	}

	/**
	 * Étapes.
	 *
	 * @return array<int, string>
	 */
	public static function steps() {
		return array(
			1 => __( 'Consent', 'oli-abandoned-cart-recovery' ),
			2 => __( 'Sequence', 'oli-abandoned-cart-recovery' ),
			3 => __( 'Sender and test', 'oli-abandoned-cart-recovery' ),
			4 => __( 'Turn on', 'oli-abandoned-cart-recovery' ),
		);
	}

	/**
	 * Étape courante.
	 *
	 * @return int
	 */
	public static function current_step() {
		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation.
		return max( 1, min( 4, $step ) );
	}

	/**
	 * Affiche l'assistant (sans bandeau de statut, CA-ACR-17).
	 *
	 * @return void
	 */
	public static function render() {
		OLI_ACR_Admin::check_cap( true );
		$step = self::current_step();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Code de message affiché seulement.
		$msg = isset( $_GET['oli_acr_msg'] ) ? sanitize_key( wp_unslash( $_GET['oli_acr_msg'] ) ) : '';
		echo '<div class="wrap oli-acr-wrap oli-acr-wizard">';
		echo '<h1>' . esc_html__( 'Set up cart recovery', 'oli-abandoned-cart-recovery' ) . '</h1><hr class="wp-header-end">';
		echo '<ol class="oli-acr-steps">';
		foreach ( self::steps() as $n => $label ) {
			printf(
				'<li%1$s>%2$s</li>',
				$n === $step ? ' class="is-current" aria-current="step"' : '',
				esc_html( $n . '. ' . $label )
			);
		}
		echo '</ol>';
		OLI_ACR_Admin::notice( $msg );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="oli-acr-card oli-acr-wizard-card">';
		wp_nonce_field( 'oli_acr_wizard' );
		echo '<input type="hidden" name="action" value="oli_acr_wizard"><input type="hidden" name="step" value="' . esc_attr( (string) $step ) . '">';
		call_user_func( array( __CLASS__, 'step_' . $step ) );
		echo '</form>';
		if ( oli_acr_setup_done() ) {
			echo '<p><a href="' . esc_url( OLI_ACR_Admin::url( 'settings' ) ) . '">&larr; ' . esc_html__( 'Back to settings', 'oli-abandoned-cart-recovery' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Boutons : action primaire, « Retour », « Passer cette étape » (pas sur Activer).
	 *
	 * @param int $step Étape.
	 * @return void
	 */
	private static function buttons( $step ) {
		echo '<div class="oli-acr-card-foot">';
		echo '<button type="submit" name="do" value="continue" class="button button-primary">' . esc_html__( 'Save and continue', 'oli-abandoned-cart-recovery' ) . '</button> ';
		if ( $step > 1 ) {
			echo '<a class="button" href="' . esc_url( OLI_ACR_Admin::setup_url( $step - 1 ) ) . '">' . esc_html__( 'Back', 'oli-abandoned-cart-recovery' ) . '</a> ';
		}
		echo '<a class="button-link oli-acr-skip" href="' . esc_url( OLI_ACR_Admin::setup_url( $step + 1 ) ) . '">' . esc_html__( 'Skip this step', 'oli-abandoned-cart-recovery' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Étape 1 : consentement (non négociable dans l'assistant), texte de la case, conservation.
	 *
	 * @return void
	 */
	private static function step_1() {
		$locale = OLI_ACR_Lang::fallback_language();
		$texts  = (array) oli_acr_get_setting( 'consent_texts' );
		$value  = isset( $texts[ $locale ] ) ? (string) $texts[ $locale ] : oli_acr_consent_base_text( $locale );
		$value  = '' !== $value && ! oli_acr_is_default_consent_text( $value ) ? $value : wp_strip_all_tags( oli_acr_consent_html( $locale ) );
		$days   = absint( oli_acr_get_setting( 'retention_days' ) );
		$delete = oli_acr_duration_label( oli_acr_get_setting( 'delete_carts_after' ) );
		echo '<div class="oli-acr-card-head"><h2>' . esc_html__( 'Consent first', 'oli-abandoned-cart-recovery' ) . '</h2></div><div class="oli-acr-card-body">';
		echo '<p>' . esc_html__( 'We only save an email and cart if the shopper agrees at checkout. This is the default, and it is what privacy laws such as Quebec’s Law 25 and the GDPR require.', 'oli-abandoned-cart-recovery' ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Checkbox at checkout', 'oli-abandoned-cart-recovery' ) . '</th><td><label><input type="checkbox" checked disabled aria-describedby="oli-acr-wz-consent-help"> ' . esc_html__( 'Require consent (recommended, always required in the assistant)', 'oli-abandoned-cart-recovery' ) . '</label>';
		echo '<p class="description" id="oli-acr-wz-consent-help">' . esc_html__( 'Shoppers can still order without ticking it. Without a ticked box, nothing is saved.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="oli-acr-wz-consent">' . esc_html__( 'Checkbox text', 'oli-abandoned-cart-recovery' ) . '</label></th><td><textarea id="oli-acr-wz-consent" name="consent_text" rows="3" class="large-text" lang="' . esc_attr( str_replace( '_', '-', $locale ) ) . '">' . esc_textarea( $value ) . '</textarea></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Retention', 'oli-abandoned-cart-recovery' ) . '</th><td>' . esc_html(
			$days > 0
				/* translators: 1: number of days, 2: delay for unrecovered carts (e.g. 30 days). */
				? sprintf( _n( '%1$d day for all data · %2$s for unrecovered carts', '%1$d days for all data · %2$s for unrecovered carts', $days, 'oli-abandoned-cart-recovery' ), $days, $delete )
				/* translators: %s: delay for unrecovered carts (e.g. 30 days). */
				: sprintf( __( 'No time limit for all data · %s for unrecovered carts', 'oli-abandoned-cart-recovery' ), $delete )
		) . '</td></tr>';
		echo '</table></div>';
		self::buttons( 1 );
	}

	/**
	 * Étape 2 : séquence (la relance n° 2 reste éteinte, D5).
	 *
	 * @return void
	 */
	private static function step_2() {
		$lang = OLI_ACR_Lang::admin_language();
		echo '<div class="oli-acr-card-head"><h2>' . esc_html__( 'Your reminder sequence', 'oli-abandoned-cart-recovery' ) . '</h2></div><div class="oli-acr-card-body">';
		echo '<p>' . esc_html__( 'Two emails are enough to start. The second one, with a coupon, stays off until you turn it on.', 'oli-abandoned-cart-recovery' ) . '</p>';
		echo '<ol class="oli-acr-sequence oli-acr-sequence-compact">';
		$i     = 0;
		$first = null;
		foreach ( OLI_ACR_Admin_Emails::sequence( 'cart' ) as $id => $tpl ) {
			++$i;
			$tpl = OLI_ACR_Templates::for_locale( (string) $id, $tpl, $lang );
			if ( null === $first && 'yes' === $tpl['active'] ) {
				$first = $tpl;
			}
			$title = OLI_ACR_Admin_Emails::card_title( (string) $id, $tpl, $i );
			$cb_id = 'oli-acr-wz-active-' . sanitize_html_class( (string) $id );
			echo '<li class="oli-acr-step' . ( 'yes' === $tpl['active'] ? ' is-active' : '' ) . '"><span class="oli-acr-step-num" aria-hidden="true">' . esc_html( (string) $i ) . '</span><div class="oli-acr-step-body">';
			printf( '<h3><label for="%1$s">%2$s</label></h3>', esc_attr( $cb_id ), esc_html( $title ) );
			echo '<p>' . esc_html( OLI_ACR_Admin_Emails::card_meta( $tpl ) ) . '</p>';
			echo '<p class="oli-acr-inline-field"><label for="' . esc_attr( $cb_id . '-delay' ) . '">' . esc_html__( 'Send after', 'oli-abandoned-cart-recovery' ) . '</label> ';
			OLI_ACR_Admin::duration_field( 'delay[' . $id . ']', $tpl['delay'], $cb_id . '-delay' );
			echo '</p></div><div class="oli-acr-step-actions">';
			printf( '<input type="hidden" name="active[%1$s]" value="no"><input type="checkbox" id="%2$s" name="active[%1$s]" value="yes"%3$s>', esc_attr( (string) $id ), esc_attr( $cb_id ), checked( 'yes', $tpl['active'], false ) );
			echo '</div></li>';
			if ( $i >= OLI_ACR_Admin_Emails::MAX_CART_EMAILS ) {
				break;
			}
		}
		echo '</ol>';
		if ( $first ) {
			/* translators: %s: email subject. */
			echo '<p>' . esc_html( sprintf( __( 'Subject preview: “%s”.', 'oli-abandoned-cart-recovery' ), (string) $first['subject'] ) ) . '</p>';
		}
		echo '</div>';
		self::buttons( 2 );
	}

	/**
	 * Étape 3 : expéditeur et courriel test à l'admin.
	 *
	 * @return void
	 */
	private static function step_3() {
		$s = oli_acr_settings();
		echo '<div class="oli-acr-card-head"><h2>' . esc_html__( 'Sender and test', 'oli-abandoned-cart-recovery' ) . '</h2></div><div class="oli-acr-card-body">';
		echo '<p>' . esc_html__( 'Leave empty to use the WooCommerce sender. Then send yourself a test to check that the site can send email.', 'oli-abandoned-cart-recovery' ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		printf( '<tr><th scope="row"><label for="oli-acr-wz-name">%s</label></th><td><input type="text" class="regular-text" id="oli-acr-wz-name" name="sender_name" value="%s" placeholder="%s"></td></tr>', esc_html__( 'Sender name', 'oli-abandoned-cart-recovery' ), esc_attr( $s['sender_name'] ), esc_attr( (string) get_option( 'woocommerce_email_from_name' ) ) );
		printf( '<tr><th scope="row"><label for="oli-acr-wz-email">%s</label></th><td><input type="email" class="regular-text" id="oli-acr-wz-email" name="sender_email" value="%s" placeholder="%s"></td></tr>', esc_html__( 'Sender email', 'oli-abandoned-cart-recovery' ), esc_attr( $s['sender_email'] ), esc_attr( (string) get_option( 'woocommerce_email_from_address' ) ) );
		printf( '<tr><th scope="row"><label for="oli-acr-wz-to">%s</label></th><td><input type="email" class="regular-text" id="oli-acr-wz-to" name="test_to" value="%s"> <button type="submit" name="do" value="test" class="button">%s</button></td></tr>', esc_html__( 'Test email to', 'oli-abandoned-cart-recovery' ), esc_attr( wp_get_current_user()->user_email ), esc_html__( 'Send a test email', 'oli-abandoned-cart-recovery' ) );
		echo '</table></div>';
		self::buttons( 3 );
	}

	/**
	 * Étape 4 : résumé en 4 puces, « Activer les relances » ou « Plus tard ».
	 *
	 * @return void
	 */
	private static function step_4() {
		$active = OLI_ACR_Templates::active( 'cart' );
		$first  = $active ? reset( $active ) : null;
		$days   = absint( oli_acr_get_setting( 'retention_days' ) );
		echo '<div class="oli-acr-card-head"><h2>' . esc_html__( 'Ready to turn on', 'oli-abandoned-cart-recovery' ) . '</h2></div><div class="oli-acr-card-body">';
		if ( oli_acr_reminders_on() ) {
			$lead = __( 'Recovery emails are on. Review the summary, then keep them on or turn them off.', 'oli-abandoned-cart-recovery' );
		} else {
			$lead = oli_acr_capture_on()
				? __( 'Until you turn them on, carts may be saved if the shopper ticks the box at checkout, but no email is sent.', 'oli-abandoned-cart-recovery' )
				: __( 'Nothing has been sent so far.', 'oli-abandoned-cart-recovery' );
			$lead .= ' ' . ( $first
				/* translators: %s: delay of the first email, e.g. 1 hour. */
				? sprintf( __( 'Once on, email 1 can go out %s after a cart is abandoned, only if the shopper ticked the box.', 'oli-abandoned-cart-recovery' ), oli_acr_duration_label( $first['delay'] ) )
				: __( 'No email is active yet: turn one on in step 2 or under Emails.', 'oli-abandoned-cart-recovery' ) );
		}
		echo '<p>' . esc_html( $lead ) . '</p>';
		echo OLI_ACR_Admin::parked_hint( 'p' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans parked_hint().
		echo '<ul class="oli-acr-bullets">';
		echo '<li>' . esc_html( OLI_ACR_Admin_Settings::privacy_summary() ) . '</li>';
		echo '<li>' . esc_html( OLI_ACR_Admin_Settings::sequence_summary() ) . '</li>';
		/* translators: %s: sender name and email. */
		echo '<li>' . esc_html( sprintf( __( 'Sender: %s', 'oli-abandoned-cart-recovery' ), OLI_ACR_Admin_Settings::sender_summary() ) ) . '</li>';
		/* translators: %d: number of days. */
		echo '<li>' . esc_html( $days > 0 ? sprintf( _n( 'Retention: %d day', 'Retention: %d days', $days, 'oli-abandoned-cart-recovery' ), $days ) : __( 'Retention: no time limit', 'oli-abandoned-cart-recovery' ) ) . '</li>';
		echo '</ul></div><div class="oli-acr-card-foot">';
		if ( oli_acr_reminders_on() ) {
			// Assistant relancé sur un site où les relances tournent déjà : rien n'est coupé sans un choix explicite.
			echo '<button type="submit" name="do" value="activate" class="button button-primary">' . esc_html__( 'Keep recovery emails on', 'oli-abandoned-cart-recovery' ) . '</button> ';
			echo '<button type="submit" name="do" value="off" class="button">' . esc_html__( 'Turn off recovery emails', 'oli-abandoned-cart-recovery' ) . '</button> ';
		} else {
			echo '<button type="submit" name="do" value="activate" class="button button-primary">' . esc_html__( 'Turn on recovery emails', 'oli-abandoned-cart-recovery' ) . '</button> ';
			echo '<button type="submit" name="do" value="later" class="button">' . esc_html__( 'Not now — keep emails off', 'oli-abandoned-cart-recovery' ) . '</button> ';
		}
		echo '<a class="button-link" href="' . esc_url( OLI_ACR_Admin::setup_url( 3 ) ) . '">' . esc_html__( 'Back', 'oli-abandoned-cart-recovery' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Enregistre une étape (POST + nonce + capacité des Réglages).
	 *
	 * @return void
	 */
	public static function save() {
		OLI_ACR_Admin::check_cap( true );
		check_admin_referer( 'oli_acr_wizard' );
		$step     = isset( $_POST['step'] ) ? max( 1, min( 4, absint( $_POST['step'] ) ) ) : 1;
		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'continue';
		$settings = get_option( 'oli_acr_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( 1 === $step ) {
			// Consentement exigé dans l'assistant : le mode « sans case » repasse au consentement ; « jamais les invités » reste.
			if ( isset( $settings['guest_tracking'] ) && 'always' === $settings['guest_tracking'] ) {
				$settings['guest_tracking'] = 'consent';
			}
			$locale = OLI_ACR_Lang::fallback_language();
			$text   = isset( $_POST['consent_text'] ) ? oli_acr_sanitize_consent_html( wp_unslash( $_POST['consent_text'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé par oli_acr_sanitize_consent_html().
			$texts  = isset( $settings['consent_texts'] ) && is_array( $settings['consent_texts'] ) ? $settings['consent_texts'] : array();
			if ( '' !== trim( $text ) && ! oli_acr_is_default_consent_text( $text ) ) {
				$texts[ $locale ] = $text;
			} else {
				unset( $texts[ $locale ] );
			}
			$settings['consent_texts'] = $texts;
			$settings['consent_text']  = isset( $texts[ $locale ] ) ? $texts[ $locale ] : '';
			update_option( 'oli_acr_settings', $settings, true );
			OLI_ACR_Lang::register_strings();
		} elseif ( 2 === $step ) {
			$active = isset( $_POST['active'] ) && is_array( $_POST['active'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['active'] ) ) : array();
			$delays = isset( $_POST['delay'] ) && is_array( $_POST['delay'] ) ? wp_unslash( $_POST['delay'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé par oli_acr_sanitize_duration().
			foreach ( OLI_ACR_Admin_Emails::sequence( 'cart' ) as $id => $tpl ) {
				if ( ! isset( $active[ $id ] ) ) {
					continue;
				}
				$tpl['active'] = 'yes' === $active[ $id ] ? 'yes' : 'no';
				if ( isset( $delays[ $id ] ) && is_array( $delays[ $id ] ) ) {
					$tpl['delay'] = oli_acr_sanitize_duration( $delays[ $id ] );
				}
				OLI_ACR_Templates::save( $tpl );
			}
		} elseif ( 3 === $step ) {
			$settings['sender_name']  = isset( $_POST['sender_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sender_name'] ) ) : '';
			$settings['sender_email'] = isset( $_POST['sender_email'] ) ? sanitize_email( wp_unslash( $_POST['sender_email'] ) ) : '';
			update_option( 'oli_acr_settings', $settings, true );
			if ( 'test' === $do ) {
				$to   = isset( $_POST['test_to'] ) ? sanitize_email( wp_unslash( $_POST['test_to'] ) ) : '';
				$code = OLI_ACR_Admin_Emails::send_test( $to );
				wp_safe_redirect( add_query_arg( 'oli_acr_msg', $code, OLI_ACR_Admin::setup_url( 3 ) ) );
				exit;
			}
		} elseif ( 4 === $step ) {
			// Pas de redirection hors de l'admin du plugin : retour au Tableau de bord avec une notice.
			if ( 'activate' === $do ) {
				$settings['enabled'] = 'yes';
			} elseif ( 'off' === $do || 'later' === $do ) {
				$settings['enabled'] = 'no';
			}
			update_option( 'oli_acr_settings', $settings, true );
			update_option( 'oli_acr_setup', 'done', true );
			delete_option( 'oli_acr_schedule_signature' );
			OLI_ACR_Admin::redirect( 'dashboard', 'activate' === $do ? 'activated' : ( 'off' === $do ? 'disabled' : 'later' ) );
		}
		wp_safe_redirect( OLI_ACR_Admin::setup_url( $step + 1 ) );
		exit;
	}
}
