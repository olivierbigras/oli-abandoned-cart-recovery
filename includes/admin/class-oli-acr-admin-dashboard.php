<?php
/**
 * Écran Tableau de bord (A-2) : 4 tuiles définies, paniers à relancer, derniers récupérés, rapports repliés.
 *
 * @package OliAbandonedCartRecovery
 * @author  Olivier Bigras (bigrat95)
 * @link    https://olivierbigras.com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tableau de bord.
 */
class OLI_ACR_Admin_Dashboard {

	/**
	 * Périodes proposées (7, 30, 90 jours).
	 *
	 * @return array<int, string>
	 */
	public static function periods() {
		return array(
			7  => __( '7 days', 'oli-abandoned-cart-recovery' ),
			30 => __( '30 days', 'oli-abandoned-cart-recovery' ),
			90 => __( '90 days', 'oli-abandoned-cart-recovery' ),
		);
	}

	/**
	 * Statistiques d'une période [maintenant - $days - $offset, maintenant - $offset].
	 *
	 * « Paniers laissés » = paniers dont abandoned_at tombe dans la période, quel que soit leur statut actuel.
	 *
	 * @param int $days   Nombre de jours.
	 * @param int $offset Décalage en jours (période précédente).
	 * @return array<string, mixed>
	 */
	public static function stats( $days, $offset = 0 ) {
		global $wpdb;
		$carts = oli_acr_table( 'carts' );
		$log   = oli_acr_table( 'log' );
		$since = oli_acr_now( -1 * ( $days + $offset ) * DAY_IN_SECONDS );
		$until = oli_acr_now( -1 * $offset * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Noms de table fixes (oli_acr_table()), valeurs passées par prepare().
		$left      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$carts} WHERE abandoned_at >= %s AND abandoned_at < %s", $since, $until ) );
		$emails    = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS sent, SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked FROM {$log} WHERE sent_at >= %s AND sent_at < %s", $since, $until ) );
		$pending   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT object_id) FROM {$log} WHERE object_type = 'order' AND sent_at >= %s AND sent_at < %s", $since, $until ) );
		$recovered = $wpdb->get_results( $wpdb->prepare( "SELECT object_type, COUNT(DISTINCT order_id) AS total, SUM(recovered_total) AS amount FROM {$log} WHERE recovered_at >= %s AND recovered_at < %s GROUP BY object_type", $since, $until ), OBJECT_K );
		// phpcs:enable

		$rec_carts  = isset( $recovered['cart'] ) ? (int) $recovered['cart']->total : 0;
		$rec_orders = isset( $recovered['order'] ) ? (int) $recovered['order']->total : 0;
		$amount     = ( isset( $recovered['cart'] ) ? (float) $recovered['cart']->amount : 0 ) + ( isset( $recovered['order'] ) ? (float) $recovered['order']->amount : 0 );
		return array(
			'left'             => $left,
			'pending'          => $pending,
			'sent'             => $emails ? (int) $emails->sent : 0,
			'clicked'          => $emails ? (int) $emails->clicked : 0,
			'recovered_carts'  => $rec_carts,
			'recovered_orders' => $rec_orders,
			'amount'           => $amount,
		);
	}

	/**
	 * Affiche l'écran.
	 *
	 * @return void
	 */
	public static function render() {
		$periods = self::periods();
		$days    = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtre d'affichage.
		if ( ! isset( $periods[ $days ] ) ) {
			$days = 30;
		}
		$s      = self::stats( $days );
		$prev   = self::stats( $days, $days );
		$counts = OLI_ACR_Carts::count_by_status();
		$total  = array_sum( $counts );

		echo '<ul class="subsubsub oli-acr-periods">';
		$links = array();
		foreach ( $periods as $value => $label ) {
			$links[] = sprintf( '<li><a href="%s"%s>%s</a>', esc_url( OLI_ACR_Admin::url( 'dashboard', array( 'period' => $value ) ) ), $value === $days ? ' class="current" aria-current="page"' : '', esc_html( $label ) );
		}
		echo implode( ' | </li>', $links ) . '</li></ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé ci-dessus.

		// Tuile 1 : variation par rapport à la période précédente, seulement s'il y a des données.
		$delta = '';
		if ( $prev['left'] > 0 || $s['left'] > 0 ) {
			$diff  = $s['left'] - $prev['left'];
			$delta = sprintf(
				/* translators: %s: signed difference, e.g. +2 or -1. */
				__( '%s vs the previous period.', 'oli-abandoned-cart-recovery' ),
				( $diff > 0 ? '+' : '' ) . number_format_i18n( $diff )
			);
		}
		$sent_line = sprintf(
			/* translators: %s: number of emails sent. */
			_n( '%s email sent', '%s emails sent', $s['sent'], 'oli-abandoned-cart-recovery' ),
			number_format_i18n( $s['sent'] )
		) . ', ' . sprintf(
			/* translators: %s: number of clicks. */
			_n( '%s click', '%s clicks', $s['clicked'], 'oli-abandoned-cart-recovery' ),
			number_format_i18n( $s['clicked'] )
		);
		$rate_line = $s['left'] > 0
			/* translators: %s: percentage of carts left behind that were recovered. */
			? sprintf( __( '%s %% of the carts left behind', 'oli-abandoned-cart-recovery' ), number_format_i18n( 100 * $s['recovered_carts'] / $s['left'], 0 ) )
			: __( 'No cart left behind in this period.', 'oli-abandoned-cart-recovery' );

		$tiles = array(
			array(
				__( 'Carts left behind', 'oli-abandoned-cart-recovery' ),
				number_format_i18n( $s['left'] ),
				trim( __( 'Carts marked abandoned in this period.', 'oli-abandoned-cart-recovery' ) . ' ' . $delta ),
			),
			array(
				__( 'Emails sent', 'oli-abandoned-cart-recovery' ),
				number_format_i18n( $s['sent'] ),
				$sent_line,
			),
			array(
				__( 'Recovered carts', 'oli-abandoned-cart-recovery' ),
				number_format_i18n( $s['recovered_carts'] ),
				$rate_line,
			),
			array(
				__( 'Recovered revenue', 'oli-abandoned-cart-recovery' ),
				wp_strip_all_tags( wc_price( $s['amount'] ) ),
				__( 'Orders linked to a reminder.', 'oli-abandoned-cart-recovery' ),
			),
		);
		// D3 : tuile « commandes en attente » seulement si la fonction est activée (zéro tuile fantôme).
		if ( 'yes' === oli_acr_get_setting( 'pending_enabled' ) ) {
			$tiles[] = array(
				__( 'Pending orders reminded', 'oli-abandoned-cart-recovery' ),
				number_format_i18n( $s['pending'] ),
				sprintf(
					/* translators: %s: number of recovered pending orders. */
					__( 'Unpaid orders that received a reminder. Recovered: %s.', 'oli-abandoned-cart-recovery' ),
					number_format_i18n( $s['recovered_orders'] )
				),
			);
		}
		echo '<div class="oli-acr-tiles">';
		foreach ( $tiles as $tile ) {
			printf(
				'<div class="oli-acr-tile"><h2 class="oli-acr-tile-title">%1$s</h2><p class="oli-acr-tile-value">%2$s</p><p class="oli-acr-tile-help">%3$s</p></div>',
				esc_html( $tile[0] ),
				esc_html( $tile[1] ),
				esc_html( $tile[2] )
			);
		}
		echo '</div>';

		echo '<div class="oli-acr-columns">';
		echo '<div class="oli-acr-main">';
		if ( 0 === $total ) {
			echo '<div class="oli-acr-card oli-acr-empty"><p>' . esc_html__( 'No carts recorded yet. Try checkout: add an item, tick the consent box, then leave without paying.', 'oli-abandoned-cart-recovery' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( wc_get_checkout_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View checkout', 'oli-abandoned-cart-recovery' ) . '</a></p></div>';
		} else {
			self::render_todo();
		}
		echo '</div><div class="oli-acr-side">';
		self::render_recent_recovered();
		echo '</div></div>';
		self::render_reports( $days );
	}

	/**
	 * Colonne principale : jusqu'à 5 paniers à relancer (abandonnés ou en échec).
	 *
	 * @return void
	 */
	private static function render_todo() {
		global $wpdb;
		$table = oli_acr_table( 'carts' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nom de table fixe (oli_acr_table()).
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status IN ('abandoned','failed') ORDER BY updated_at DESC LIMIT 5" );
		echo '<div class="oli-acr-card"><div class="oli-acr-card-head"><h2>' . esc_html__( 'To follow up', 'oli-abandoned-cart-recovery' ) . '</h2>';
		echo '<a class="button" href="' . esc_url( OLI_ACR_Admin::url( 'carts', array( 'view' => 'todo' ) ) ) . '">' . esc_html__( 'View all', 'oli-abandoned-cart-recovery' ) . '</a></div>';
		if ( empty( $rows ) ) {
			echo '<p class="oli-acr-card-body">' . esc_html__( 'No cart to follow up right now.', 'oli-abandoned-cart-recovery' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped oli-acr-mini"><thead><tr><th scope="col">' . esc_html__( 'Customer', 'oli-abandoned-cart-recovery' ) . '</th><th scope="col">' . esc_html__( 'Items', 'oli-abandoned-cart-recovery' ) . '</th><th scope="col">' . esc_html__( 'Total', 'oli-abandoned-cart-recovery' ) . '</th><th scope="col">' . esc_html__( 'Status', 'oli-abandoned-cart-recovery' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$name = trim( $row->first_name . ' ' . $row->last_name );
			printf(
				'<tr><td><a href="%1$s"><strong>%2$s</strong></a>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
				esc_url( OLI_ACR_Admin::url( 'carts', array( 'cart' => (int) $row->id ) ) ),
				esc_html( $row->email ),
				'' !== $name ? '<br>' . esc_html( $name ) : '',
				esc_html( OLI_ACR_Admin_Carts::items_summary( $row ) ),
				wp_kses_post( wc_price( (float) $row->cart_total, array( 'currency' => $row->currency ) ) ),
				OLI_ACR_Admin_Carts::status_pill( (string) $row->status ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé dans status_pill().
			);
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Colonne latérale : 3 derniers paniers récupérés.
	 *
	 * @return void
	 */
	private static function render_recent_recovered() {
		global $wpdb;
		$table = oli_acr_table( 'carts' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nom de table fixe (oli_acr_table()).
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'recovered' ORDER BY recovered_at DESC LIMIT 3" );
		echo '<div class="oli-acr-card"><div class="oli-acr-card-head"><h2>' . esc_html__( 'Latest recovered', 'oli-abandoned-cart-recovery' ) . '</h2></div><div class="oli-acr-card-body">';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No recovered cart yet.', 'oli-abandoned-cart-recovery' ) . '</p>';
		}
		foreach ( (array) $rows as $row ) {
			$amount = (float) $row->recovered_total > 0 ? (float) $row->recovered_total : (float) $row->cart_total;
			printf(
				'<p class="oli-acr-recovered"><a href="%1$s"><strong>%2$s</strong></a><br>%3$s · <strong>%4$s</strong><br><span class="description">%5$s</span></p>',
				esc_url( OLI_ACR_Admin::url( 'carts', array( 'cart' => (int) $row->id ) ) ),
				esc_html( $row->email ),
				esc_html( OLI_ACR_Admin_Carts::items_summary( $row ) ),
				wp_kses_post( wc_price( $amount, array( 'currency' => $row->currency ) ) ),
				OLI_ACR_Admin::relative_date( (string) $row->recovered_at ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé dans relative_date().
			);
		}
		echo '<p><a href="' . esc_url( OLI_ACR_Admin::url( 'carts', array( 'view' => 'recovered' ) ) ) . '">' . esc_html__( 'View all recovered carts', 'oli-abandoned-cart-recovery' ) . '</a></p>';
		echo '</div></div>';
	}

	/**
	 * Rapports par jour et par courriel (anciens tableaux), repliés.
	 *
	 * @param int $days Période.
	 * @return void
	 */
	private static function render_reports( $days ) {
		global $wpdb;
		$log    = oli_acr_table( 'log' );
		$offset = (int) wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
		$since  = oli_acr_now( -1 * $days * DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nom de table fixe (oli_acr_table()), valeurs passées par prepare().
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(DATE_ADD(sent_at, INTERVAL %d SECOND)) AS day, COUNT(*) AS sent, SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked, SUM(CASE WHEN recovered_at IS NOT NULL THEN 1 ELSE 0 END) AS recovered, SUM(recovered_total) AS amount FROM {$log} WHERE sent_at >= %s GROUP BY day ORDER BY day DESC LIMIT 400", $offset, $since ) );
		$by_tpl = $wpdb->get_results( $wpdb->prepare( "SELECT template_id, COUNT(*) AS sent, SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked, SUM(CASE WHEN recovered_at IS NOT NULL THEN 1 ELSE 0 END) AS recovered, SUM(recovered_total) AS amount FROM {$log} WHERE sent_at >= %s GROUP BY template_id", $since ) );
		// phpcs:enable
		$head = '<thead><tr><th scope="col">%s</th><th scope="col">' . esc_html__( 'Emails sent', 'oli-abandoned-cart-recovery' ) . '</th><th scope="col">' . esc_html__( 'Clicks', 'oli-abandoned-cart-recovery' ) . '</th><th scope="col">' . esc_html__( 'Recovered', 'oli-abandoned-cart-recovery' ) . '</th><th scope="col">' . esc_html__( 'Recovered revenue', 'oli-abandoned-cart-recovery' ) . '</th></tr></thead>';

		echo '<details class="oli-acr-card oli-acr-reports"><summary>' . esc_html__( 'Email report', 'oli-abandoned-cart-recovery' ) . '</summary><div class="oli-acr-card-body">';
		echo '<h3>' . esc_html__( 'By day', 'oli-abandoned-cart-recovery' ) . '</h3><div class="oli-acr-scroll"><table class="widefat striped">' . sprintf( $head, esc_html__( 'Day', 'oli-abandoned-cart-recovery' ) ) . '<tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- En-tête échappé ci-dessus.
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No data for this period.', 'oli-abandoned-cart-recovery' ) . '</td></tr>';
		}
		foreach ( (array) $rows as $row ) {
			printf( '<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td></tr>', esc_html( date_i18n( get_option( 'date_format' ), (int) strtotime( (string) $row->day ) ) ), (int) $row->sent, (int) $row->clicked, (int) $row->recovered, wp_kses_post( wc_price( (float) $row->amount ) ) );
		}
		echo '</tbody></table></div>';
		echo '<h3>' . esc_html__( 'By email', 'oli-abandoned-cart-recovery' ) . '</h3><div class="oli-acr-scroll"><table class="widefat striped">' . sprintf( $head, esc_html__( 'Email', 'oli-abandoned-cart-recovery' ) ) . '<tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- En-tête échappé ci-dessus.
		if ( empty( $by_tpl ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No data for this period.', 'oli-abandoned-cart-recovery' ) . '</td></tr>';
		}
		foreach ( (array) $by_tpl as $row ) {
			printf( '<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td></tr>', esc_html( OLI_ACR_Admin::template_name( (string) $row->template_id ) ), (int) $row->sent, (int) $row->clicked, (int) $row->recovered, wp_kses_post( wc_price( (float) $row->amount ) ) );
		}
		echo '</tbody></table></div></div></details>';
	}
}
