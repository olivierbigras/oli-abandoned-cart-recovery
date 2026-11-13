<?php
/**
 * Écran Réglages (A-6) : Résumé en 4 lignes, puis Réglages avancés repliés, un enregistrement par section.
 *
 * Les 24 clés 1.1.7 de oli_acr_settings restent lues et enregistrées ; aucune n'est effacée.
 *
 * @package OliAbandonedCartRecovery
 * @author  Olivier Bigras (bigrat95)
 * @link    https://olivierbigras.com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Réglages.
 */
class OLI_ACR_Admin_Settings {

	/**
	 * Accroches.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_oli_acr_save_section', array( __CLASS__, 'save_section' ) );
	}

	/**
	 * Sections avancées, dans l'ordre de la spec.
	 *
	 * @return array<string, string>
	 */
	public static function sections() {
		$sections = array(
			'capture'   => __( 'Capture and consent', 'oli-abandoned-cart-recovery' ),
			'timing'    => __( 'Delay and task', 'oli-abandoned-cart-recovery' ),
			'pending'   => __( 'Pending orders', 'oli-abandoned-cart-recovery' ),
			'sender'    => __( 'Sender', 'oli-abandoned-cart-recovery' ),
			'coupons'   => __( 'Coupons', 'oli-abandoned-cart-recovery' ),
			'retention' => __( 'Retention', 'oli-abandoned-cart-recovery' ),
		);
		if ( current_user_can( 'manage_options' ) ) {
			$sections['access'] = __( 'Access', 'oli-abandoned-cart-recovery' );
		}
		return $sections;
	}

	/**
	 * Ligne « Séquence » du résumé, toujours à jour : « 1 courriel actif, 1 h après l'abandon ».
	 *
	 * @return string
	 */
	public static function sequence_summary() {
		$active = OLI_ACR_Templates::active( 'cart' );
		if ( empty( $active ) ) {
			return __( 'No active email', 'oli-abandoned-cart-recovery' );
		}
		$first = reset( $active );
		return sprintf(
			/* translators: 1: number of active emails, 2: delay of the first email. */
			_n( '%1$d active email, %2$s after the cart is abandoned', '%1$d active emails, the first %2$s after the cart is abandoned', count( $active ), 'oli-abandoned-cart-recovery' ),
			count( $active ),
			oli_acr_duration_label( $first['delay'] )
		);
	}

	/**
	 * Ligne « Expéditeur » : nom et adresse, repli WooCommerce si vide.
	 *
	 * @return string
	 */
	public static function sender_summary() {
		$name  = (string) oli_acr_get_setting( 'sender_name' );
		$email = (string) oli_acr_get_setting( 'sender_email' );
		$name  = '' !== $name ? $name : (string) get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) );
		$email = '' !== $email ? $email : (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );
		return wp_specialchars_decode( $name, ENT_QUOTES ) . ' · ' . $email;
	}

	/**
	 * Ligne « Confidentialité » : consentement et durée réelle de conservation (90 j pour une nouvelle install, D6).
	 *
	 * @return string
	 */
	public static function privacy_summary() {
		$mode  = (string) oli_acr_get_setting( 'guest_tracking' );
		$parts = array();
		if ( 'always' === $mode ) {
			$parts[] = __( 'Carts recorded without a checkbox', 'oli-abandoned-cart-recovery' );
		} elseif ( 'never' === $mode ) {
			$parts[] = __( 'Guests never recorded; consent required for customers', 'oli-abandoned-cart-recovery' );
		} else {
			$parts[] = __( 'Consent required at checkout', 'oli-abandoned-cart-recovery' );
		}
		$days = absint( oli_acr_get_setting( 'retention_days' ) );
		/* translators: %d: number of days. */
		$parts[] = $days > 0 ? sprintf( _n( 'kept %d day', 'kept %d days', $days, 'oli-abandoned-cart-recovery' ), $days ) : __( 'kept with no time limit', 'oli-abandoned-cart-recovery' );
		return implode( ' · ', $parts );
	}

	/**
	 * Affiche l'écran.
	 *
	 * @return void
	 */
	public static function render() {
		OLI_ACR_Admin::donate_notice();
		$settings_url = OLI_ACR_Admin::url( 'settings', array( 'advanced' => 1 ) );
		$rows         = array(
			array( __( 'Recovery emails', 'oli-abandoned-cart-recovery' ), oli_acr_reminders_on() ? _x( 'On', 'recovery emails status', 'oli-abandoned-cart-recovery' ) : _x( 'Off', 'recovery emails status', 'oli-abandoned-cart-recovery' ), '#oli-acr-status' ),
			array( __( 'Sequence', 'oli-abandoned-cart-recovery' ), self::sequence_summary(), OLI_ACR_Admin::url( 'emails' ) ),
			array( __( 'Sender', 'oli-abandoned-cart-recovery' ), self::sender_summary(), $settings_url . '#oli-acr-sec-sender' ),
			array( __( 'Privacy', 'oli-abandoned-cart-recovery' ), self::privacy_summary(), $settings_url . '#oli-acr-sec-capture' ),
		);
		echo '<table class="widefat striped oli-acr-summary" role="presentation"><tbody>';
		foreach ( $rows as $row ) {
			printf(
				'<tr><th scope="row">%1$s</th><td>%2$s</td><td class="oli-acr-summary-edit"><a href="%3$s">%4$s<span class="screen-reader-text"> %1$s</span></a></td></tr>',
				esc_html( $row[0] ),
				esc_html( $row[1] ),
				esc_url( $row[2] ),
				esc_html__( 'Edit', 'oli-abandoned-cart-recovery' )
			);
		}
		echo '</tbody></table>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affichage (section ouverte après un enregistrement).
		$open = ! empty( $_GET['advanced'] );
		echo '<details id="oli-acr-advanced" class="oli-acr-card oli-acr-advanced"' . ( $open ? ' open' : '' ) . '>';
		echo '<summary><h2>' . esc_html__( 'Advanced settings', 'oli-abandoned-cart-recovery' ) . '</h2></summary>';
		echo '<nav class="oli-acr-anchors" aria-label="' . esc_attr__( 'Advanced settings sections', 'oli-abandoned-cart-recovery' ) . '"><ul>';
		foreach ( self::sections() as $key => $label ) {
			printf( '<li><a href="#oli-acr-sec-%1$s">%2$s</a></li>', esc_attr( $key ), esc_html( $label ) );
		}
		echo '</ul></nav>';
		$s = oli_acr_settings();
		foreach ( self::sections() as $key => $label ) {
			echo '<section id="oli-acr-sec-' . esc_attr( $key ) . '" class="oli-acr-section" aria-labelledby="oli-acr-sec-' . esc_attr( $key ) . '-title">';
			echo '<h3 id="oli-acr-sec-' . esc_attr( $key ) . '-title">' . esc_html( $label ) . '</h3>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'oli_acr_save_section_' . $key );
			echo '<input type="hidden" name="action" value="oli_acr_save_section"><input type="hidden" name="section" value="' . esc_attr( $key ) . '">';
			echo '<table class="form-table" role="presentation">';
			call_user_func( array( __CLASS__, 'section_' . $key ), $s );
			echo '</table>';
			echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save this section', 'oli-abandoned-cart-recovery' ) . '</button></p>';
			echo '</form>';
			if ( 'sender' === $key ) {
				OLI_ACR_Admin_Emails::render_test_form( '', OLI_ACR_Lang::admin_language(), 'settings' );
			}
			echo '</section>';
		}
		echo '</details>';
	}

	/**
	 * Section 1 : capture et consentement (un seul choix, texte de la case, rôles).
	 *
	 * @param array<mixed> $s Réglages.
	 * @return void
	 */
	public static function section_capture( $s ) {
		$mode = in_array( $s['guest_tracking'], array( 'consent', 'never', 'always' ), true ) ? $s['guest_tracking'] : 'consent';
		echo '<tr><th scope="row">' . esc_html__( 'At checkout', 'oli-abandoned-cart-recovery' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'At checkout', 'oli-abandoned-cart-recovery' ) . '</legend>';
		printf( '<label><input type="radio" name="s[guest_tracking]" value="consent"%s> %s</label><br>', checked( 'consent', $mode, false ), esc_html__( 'Save the email and cart only if the shopper ticks the box at checkout (recommended).', 'oli-abandoned-cart-recovery' ) );
		printf( '<label><input type="radio" name="s[guest_tracking]" value="never"%s> %s</label><br>', checked( 'never', $mode, false ), esc_html__( 'Never save guest carts (registered customers who tick the box only).', 'oli-abandoned-cart-recovery' ) );
		printf( '<label><input type="radio" name="s[guest_tracking]" value="always" class="oli-acr-always"%s aria-describedby="oli-acr-always-help"> %s</label>', checked( 'always', $mode, false ), esc_html__( 'Save without a checkbox (not recommended)', 'oli-abandoned-cart-recovery' ) );
		echo '<p class="description" id="oli-acr-always-help">' . esc_html__( 'This records personal information without a checkbox. You become responsible for compliance with applicable privacy law. Confirm only if you have another legal basis.', 'oli-abandoned-cart-recovery' ) . '</p>';
		if ( 'always' === $mode ) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Carts are recorded without a checkbox.', 'oli-abandoned-cart-recovery' ) . '</strong> ' . esc_html__( 'Quebec Law 25 and the GDPR generally require explicit consent before saving this data for reminders. You are responsible for having another legal basis.', 'oli-abandoned-cart-recovery' ) . '</p></div>';
		} else {
			echo '<p><label><input type="checkbox" name="s[always_confirm]" value="yes"> ' . esc_html__( 'I confirm that I have another legal basis to record carts without a checkbox.', 'oli-abandoned-cart-recovery' ) . '</label></p>';
		}
		echo '</fieldset></td></tr>';

		$texts = (array) $s['consent_texts'];
		echo '<tr><th scope="row">' . esc_html__( 'Checkbox text', 'oli-abandoned-cart-recovery' ) . '</th><td>';
		$ui = OLI_ACR_Lang::ui_languages();
		foreach ( $ui as $locale ) {
			$value = isset( $texts[ $locale ] ) ? (string) $texts[ $locale ] : '';
			if ( '' === $value && OLI_ACR_Lang::fallback_language() === $locale ) {
				$value = oli_acr_consent_base_text( $locale );
			}
			$id = 'oli-acr-consent-' . strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '-', $locale ) );
			echo '<div class="oli-acr-lang-field" lang="' . esc_attr( str_replace( '_', '-', $locale ) ) . '">';
			if ( count( $ui ) > 1 ) {
				echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( OLI_ACR_Lang::label( $locale ) ) . '</strong></label></p>';
			} else {
				echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Checkbox text', 'oli-abandoned-cart-recovery' ) . '</label>';
			}
			printf(
				'<textarea id="%1$s" name="s[consent_texts][%2$s]" rows="3" class="large-text" placeholder="%3$s">%4$s</textarea>',
				esc_attr( $id ),
				esc_attr( $locale ),
				esc_attr( wp_strip_all_tags( oli_acr_consent_html( $locale ) ) ),
				esc_textarea( oli_acr_is_default_consent_text( $value ) ? '' : $value )
			);
			echo '</div>';
		}
		echo '<p class="description">' . esc_html__( 'Leave empty to use the default text. Links (for example to your privacy policy), bold and italic are allowed.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';

		if ( OLI_ACR_Lang::is_multilingual() ) {
			echo '<tr><th scope="row"><label for="oli-acr-fallback">' . esc_html__( 'Fallback language', 'oli-abandoned-cart-recovery' ) . '</label></th><td><select id="oli-acr-fallback" name="s[fallback_language]">';
			printf( '<option value="">%s</option>', esc_html( sprintf( /* translators: %s: language name. */ __( 'Site default language (%s)', 'oli-abandoned-cart-recovery' ), OLI_ACR_Lang::label( OLI_ACR_Lang::adapter()->default_language() ) ) ) );
			foreach ( OLI_ACR_Lang::adapter()->languages() as $locale ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $locale ), selected( (string) $s['fallback_language'], $locale, false ), esc_html( OLI_ACR_Lang::label( $locale ) ) );
			}
			echo '</select><p class="description">' . esc_html__( 'Language used for a cart whose language is unknown, and for texts missing in a language.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
		}

		echo '<tr><th scope="row">' . esc_html__( 'Registered customers tracked', 'oli-abandoned-cart-recovery' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'Registered customers tracked', 'oli-abandoned-cart-recovery' ) . '</legend>';
		printf( '<label><input type="radio" name="s[roles_mode]" value="all"%s> %s</label><br>', checked( 'all', $s['roles_mode'], false ), esc_html__( 'All roles', 'oli-abandoned-cart-recovery' ) );
		printf( '<label><input type="radio" name="s[roles_mode]" value="selected"%s> %s</label><br>', checked( 'selected', $s['roles_mode'], false ), esc_html__( 'Only these roles:', 'oli-abandoned-cart-recovery' ) );
		echo '<span class="oli-acr-roles">';
		foreach ( wp_roles()->get_names() as $role => $name ) {
			printf( '<label class="oli-acr-role"><input type="checkbox" name="s[roles][]" value="%s"%s> %s</label> ', esc_attr( $role ), checked( in_array( $role, (array) $s['roles'], true ), true, false ), esc_html( translate_user_role( $name ) ) );
		}
		echo '</span></fieldset></td></tr>';
	}

	/**
	 * Section 2 : délai d'abandon et tâche planifiée.
	 *
	 * @param array<mixed> $s Réglages.
	 * @return void
	 */
	public static function section_timing( $s ) {
		echo '<tr><th scope="row"><label for="oli-acr-abandon">' . esc_html__( 'Consider a cart abandoned after', 'oli-abandoned-cart-recovery' ) . '</label></th><td>';
		OLI_ACR_Admin::duration_field( 's[abandon_after]', $s['abandon_after'], 'oli-acr-abandon' );
		echo '<p class="description">' . esc_html__( 'Time without activity before an active cart becomes abandoned. Email delays are counted from that moment.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="oli-acr-cron">' . esc_html__( 'How often the automatic task runs', 'oli-abandoned-cart-recovery' ) . '</label></th><td>';
		OLI_ACR_Admin::duration_field( 's[cron_interval]', $s['cron_interval'], 'oli-acr-cron' );
		echo '<p class="description">' . esc_html( function_exists( 'as_schedule_recurring_action' ) ? __( 'Runs with Action Scheduler (WooCommerce).', 'oli-abandoned-cart-recovery' ) : __( 'Runs with WP-Cron.', 'oli-abandoned-cart-recovery' ) ) . '</p></td></tr>';
	}

	/**
	 * Section 3 : commandes en attente (off par défaut, D3).
	 *
	 * @param array<mixed> $s Réglages.
	 * @return void
	 */
	public static function section_pending( $s ) {
		echo '<tr><th scope="row">' . esc_html__( 'Pending order reminders', 'oli-abandoned-cart-recovery' ) . '</th><td>';
		OLI_ACR_Admin::checkbox( 's[pending_enabled]', $s['pending_enabled'], __( 'Send the "pending order" emails to unpaid orders', 'oli-abandoned-cart-recovery' ) );
		echo '<p class="description">' . esc_html__( 'When on, a "Pending orders" view appears under Carts and a separate block appears under Emails.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="oli-acr-pending-after">' . esc_html__( 'Start reminding pending orders after', 'oli-abandoned-cart-recovery' ) . '</label></th><td>';
		OLI_ACR_Admin::duration_field( 's[pending_after]', $s['pending_after'], 'oli-acr-pending-after' );
		echo '</td></tr><tr><th scope="row"><label for="oli-acr-pending-cancel">' . esc_html__( 'Cancel reminded pending orders after', 'oli-abandoned-cart-recovery' ) . '</label></th><td>';
		OLI_ACR_Admin::duration_field( 's[pending_cancel_after]', $s['pending_cancel_after'], 'oli-acr-pending-cancel' );
		echo '<p class="description">' . esc_html__( '0 = never. Only pending orders that received a reminder are cancelled.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
	}

	/**
	 * Section 4 : expéditeur et avis admin.
	 *
	 * @param array<mixed> $s Réglages.
	 * @return void
	 */
	public static function section_sender( $s ) {
		printf( '<tr><th scope="row"><label for="oli-acr-sender-name">%s</label></th><td><input type="text" class="regular-text" id="oli-acr-sender-name" name="s[sender_name]" value="%s" placeholder="%s"></td></tr>', esc_html__( 'Sender name', 'oli-abandoned-cart-recovery' ), esc_attr( $s['sender_name'] ), esc_attr( (string) get_option( 'woocommerce_email_from_name' ) ) );
		printf( '<tr><th scope="row"><label for="oli-acr-sender-email">%s</label></th><td><input type="email" class="regular-text" id="oli-acr-sender-email" name="s[sender_email]" value="%s" placeholder="%s"></td></tr>', esc_html__( 'Sender email', 'oli-abandoned-cart-recovery' ), esc_attr( $s['sender_email'] ), esc_attr( (string) get_option( 'woocommerce_email_from_address' ) ) );
		printf( '<tr><th scope="row"><label for="oli-acr-reply-to">%s</label></th><td><input type="email" class="regular-text" id="oli-acr-reply-to" name="s[reply_to]" value="%s"><p class="description">%s</p></td></tr>', esc_html__( 'Default reply-to', 'oli-abandoned-cart-recovery' ), esc_attr( $s['reply_to'] ), esc_html__( 'Each email can override it.', 'oli-abandoned-cart-recovery' ) );
		echo '<tr><th scope="row">' . esc_html__( 'Notify on recovery', 'oli-abandoned-cart-recovery' ) . '</th><td>';
		OLI_ACR_Admin::checkbox( 's[admin_notify]', $s['admin_notify'], __( 'Email the admin when a cart or pending order is recovered', 'oli-abandoned-cart-recovery' ) );
		echo '</td></tr>';
		printf( '<tr><th scope="row"><label for="oli-acr-admin-recipient">%s</label></th><td><input type="text" class="regular-text" id="oli-acr-admin-recipient" name="s[admin_recipient]" value="%s" placeholder="%s"><p class="description"><a href="%s">%s</a></p></td></tr>', esc_html__( 'Recipient(s)', 'oli-abandoned-cart-recovery' ), esc_attr( $s['admin_recipient'] ), esc_attr( (string) get_option( 'admin_email' ) ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=oli_acr_email_admin_recovered' ) ), esc_html__( 'Subject, heading and format are in WooCommerce > Settings > Emails.', 'oli-abandoned-cart-recovery' ) );
	}

	/**
	 * Section 5 : coupons (préfixe, nettoyage).
	 *
	 * @param array<mixed> $s Réglages.
	 * @return void
	 */
	public static function section_coupons( $s ) {
		printf( '<tr><th scope="row"><label for="oli-acr-prefix">%s</label></th><td><input type="text" class="regular-text" id="oli-acr-prefix" name="s[coupon_prefix]" value="%s"></td></tr>', esc_html__( 'Coupon prefix', 'oli-abandoned-cart-recovery' ), esc_attr( $s['coupon_prefix'] ) );
		echo '<tr><th scope="row">' . esc_html__( 'Clean up coupons', 'oli-abandoned-cart-recovery' ) . '</th><td>';
		OLI_ACR_Admin::checkbox( 's[coupon_delete_used]', $s['coupon_delete_used'], __( 'Trash generated coupons once used', 'oli-abandoned-cart-recovery' ) );
		echo '<br>';
		OLI_ACR_Admin::checkbox( 's[coupon_delete_expired]', $s['coupon_delete_expired'], __( 'Trash generated coupons once expired', 'oli-abandoned-cart-recovery' ) );
		echo '</td></tr>';
	}

	/**
	 * Section 6 : conservation et désinstallation.
	 *
	 * @param array<mixed> $s Réglages.
	 * @return void
	 */
	public static function section_retention( $s ) {
		echo '<tr><th scope="row"><label for="oli-acr-delete-after">' . esc_html__( 'Delete unrecovered carts after', 'oli-abandoned-cart-recovery' ) . '</label></th><td>';
		OLI_ACR_Admin::duration_field( 's[delete_carts_after]', $s['delete_carts_after'], 'oli-acr-delete-after' );
		echo '<p class="description">' . esc_html__( '0 = never. Active, abandoned and reminded carts older than this are deleted daily.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
		printf( '<tr><th scope="row"><label for="oli-acr-retention">%s</label></th><td><input type="number" min="0" class="small-text" id="oli-acr-retention" name="s[retention_days]" value="%d"> %s<p class="description">%s</p></td></tr>', esc_html__( 'Keep all data (including recovered carts and email log) for', 'oli-abandoned-cart-recovery' ), (int) $s['retention_days'], esc_html__( 'days', 'oli-abandoned-cart-recovery' ), esc_html__( '0 = forever. This duration is shown to shoppers under the checkbox.', 'oli-abandoned-cart-recovery' ) );
		echo '<tr><th scope="row">' . esc_html__( 'Uninstall', 'oli-abandoned-cart-recovery' ) . '</th><td>';
		OLI_ACR_Admin::checkbox( 's[keep_data]', $s['keep_data'], __( 'Keep the data when the plugin is deleted (settings, emails, carts, log)', 'oli-abandoned-cart-recovery' ) );
		echo '<p class="description">' . esc_html__( 'Off by default: deleting the plugin removes all its data.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="oli-acr-blocklist">' . esc_html__( 'Unsubscribed / excluded emails', 'oli-abandoned-cart-recovery' ) . '</label></th><td><textarea id="oli-acr-blocklist" name="blocklist" rows="5" class="large-text code">' . esc_textarea( implode( "\n", oli_acr_get_blocklist() ) ) . '</textarea><p class="description">' . esc_html__( 'One email per line. These addresses are never tracked or emailed.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
	}

	/**
	 * Section 7 : accès du rôle shop_manager (manage_options seulement, D7).
	 *
	 * @param array<mixed> $s Réglages.
	 * @return void
	 */
	public static function section_access( $s ) {
		echo '<tr><th scope="row">' . esc_html__( 'Shop managers', 'oli-abandoned-cart-recovery' ) . '</th><td>';
		OLI_ACR_Admin::checkbox( 's[shop_manager_access]', $s['shop_manager_access'], __( 'Let shop managers see the dashboard, carts and emails', 'oli-abandoned-cart-recovery' ) );
		echo '<p class="description">' . esc_html__( 'Off by default. Settings stay reserved to administrators.', 'oli-abandoned-cart-recovery' ) . '</p></td></tr>';
	}

	/**
	 * Enregistre une section : seules les clés de cette section changent (CA-ACR-16).
	 *
	 * @return void
	 */
	public static function save_section() {
		OLI_ACR_Admin::check_cap( true );
		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		if ( ! array_key_exists( $section, self::sections() ) ) {
			wp_die( esc_html__( 'Unknown section.', 'oli-abandoned-cart-recovery' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( 'oli_acr_save_section_' . $section );
		$raw    = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nettoyé champ par champ ci-dessous.
		$stored = get_option( 'oli_acr_settings', array() );
		$stored = is_array( $stored ) ? $stored : array();
		$old    = oli_acr_settings();
		$new    = $stored;
		$msg    = 'saved';

		switch ( $section ) {
			case 'capture':
				$mode = isset( $raw['guest_tracking'] ) && in_array( $raw['guest_tracking'], array( 'consent', 'never', 'always' ), true ) ? (string) $raw['guest_tracking'] : 'consent';
				if ( 'always' === $mode && 'always' !== $old['guest_tracking'] && empty( $raw['always_confirm'] ) ) {
					// CA-ACR-19 : passer sans case exige une confirmation explicite.
					$mode = $old['guest_tracking'];
					$msg  = 'confirm';
				}
				$new['guest_tracking'] = $mode;
				if ( 'always' === $mode ) {
					// B3 : l'avis « le consentement est maintenant exigé » n'a plus lieu d'être.
					delete_option( 'oli_acr_notice_consent_migrated' );
				}
				$texts     = isset( $old['consent_texts'] ) && is_array( $old['consent_texts'] ) ? $old['consent_texts'] : array();
				$submitted = isset( $raw['consent_texts'] ) && is_array( $raw['consent_texts'] ) ? $raw['consent_texts'] : array();
				foreach ( OLI_ACR_Lang::ui_languages() as $locale ) {
					if ( ! array_key_exists( $locale, $submitted ) ) {
						continue;
					}
					$text = oli_acr_sanitize_consent_html( (string) $submitted[ $locale ] );
					// Texte par défaut : rien n'est enregistré pour qu'il reste traduit dans la langue du visiteur.
					if ( '' !== trim( $text ) && ! oli_acr_is_default_consent_text( $text ) ) {
						$texts[ $locale ] = $text;
					} else {
						unset( $texts[ $locale ] );
					}
				}
				$new['consent_texts'] = $texts;
				if ( OLI_ACR_Lang::is_multilingual() && isset( $raw['fallback_language'] ) ) {
					$new['fallback_language'] = in_array( $raw['fallback_language'], OLI_ACR_Lang::adapter()->languages(), true ) ? (string) $raw['fallback_language'] : '';
				}
				$fallback_new        = ! empty( $new['fallback_language'] ) ? $new['fallback_language'] : OLI_ACR_Lang::adapter()->default_language();
				$new['consent_text'] = isset( $texts[ $fallback_new ] ) ? $texts[ $fallback_new ] : '';
				$new['roles_mode']   = isset( $raw['roles_mode'] ) && 'selected' === $raw['roles_mode'] ? 'selected' : 'all';
				$new['roles']        = isset( $raw['roles'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $raw['roles'] ), array_keys( wp_roles()->get_names() ) ) ) : array();
				break;
			case 'timing':
				$new['abandon_after'] = oli_acr_sanitize_duration( isset( $raw['abandon_after'] ) ? $raw['abandon_after'] : array(), 1 );
				$new['cron_interval'] = oli_acr_sanitize_duration( isset( $raw['cron_interval'] ) ? $raw['cron_interval'] : array(), 1 );
				break;
			case 'pending':
				$new['pending_enabled']      = ! empty( $raw['pending_enabled'] ) ? 'yes' : 'no';
				$new['pending_after']        = oli_acr_sanitize_duration( isset( $raw['pending_after'] ) ? $raw['pending_after'] : array() );
				$new['pending_cancel_after'] = oli_acr_sanitize_duration( isset( $raw['pending_cancel_after'] ) ? $raw['pending_cancel_after'] : array() );
				break;
			case 'sender':
				$new['sender_name']     = isset( $raw['sender_name'] ) ? sanitize_text_field( $raw['sender_name'] ) : '';
				$new['sender_email']    = isset( $raw['sender_email'] ) ? sanitize_email( $raw['sender_email'] ) : '';
				$new['reply_to']        = isset( $raw['reply_to'] ) ? sanitize_email( $raw['reply_to'] ) : '';
				$new['admin_notify']    = ! empty( $raw['admin_notify'] ) ? 'yes' : 'no';
				$new['admin_recipient'] = isset( $raw['admin_recipient'] ) ? implode( ',', array_filter( array_map( 'sanitize_email', explode( ',', (string) $raw['admin_recipient'] ) ) ) ) : '';
				break;
			case 'coupons':
				$new['coupon_prefix']         = isset( $raw['coupon_prefix'] ) ? strtoupper( (string) preg_replace( '/[^A-Za-z0-9\-_]/', '', (string) $raw['coupon_prefix'] ) ) : '';
				$new['coupon_delete_used']    = ! empty( $raw['coupon_delete_used'] ) ? 'yes' : 'no';
				$new['coupon_delete_expired'] = ! empty( $raw['coupon_delete_expired'] ) ? 'yes' : 'no';
				break;
			case 'retention':
				$new['delete_carts_after'] = oli_acr_sanitize_duration( isset( $raw['delete_carts_after'] ) ? $raw['delete_carts_after'] : array() );
				$new['retention_days']     = isset( $raw['retention_days'] ) ? absint( $raw['retention_days'] ) : 0;
				$new['keep_data']          = ! empty( $raw['keep_data'] ) ? 'yes' : 'no';
				self::save_blocklist();
				break;
			case 'access':
				$new['shop_manager_access'] = ! empty( $raw['shop_manager_access'] ) ? 'yes' : 'no';
				break;
		}
		update_option( 'oli_acr_settings', $new, true );
		if ( 'access' === $section ) {
			OLI_ACR_Install::sync_shop_manager_cap();
		}
		if ( 'timing' === $section ) {
			delete_option( 'oli_acr_schedule_signature' );
		}
		if ( 'capture' === $section ) {
			if ( ( isset( $new['fallback_language'] ) ? $new['fallback_language'] : '' ) !== $old['fallback_language'] ) {
				OLI_ACR_Templates::sync_languages();
			}
			OLI_ACR_Lang::register_strings();
		}
		OLI_ACR_Admin::redirect( 'settings', $msg, array( 'advanced' => 1 ), 'oli-acr-sec-' . $section );
	}

	/**
	 * Liste d'exclusion (section Conservation).
	 *
	 * @return void
	 */
	private static function save_blocklist() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié dans save_section().
		$lines = isset( $_POST['blocklist'] ) ? explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['blocklist'] ) ) ) : array();
		$list  = array();
		foreach ( $lines as $line ) {
			$email = strtolower( sanitize_email( trim( $line ) ) );
			if ( is_email( $email ) ) {
				$list[] = $email;
			}
		}
		$list = array_values( array_unique( $list ) );
		foreach ( array_diff( $list, oli_acr_get_blocklist() ) as $added ) {
			OLI_ACR_Carts::mark_unsubscribed( $added );
		}
		update_option( 'oli_acr_blocklist', $list, false );
	}
}
