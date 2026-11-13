<?php
/**
 * Écran Paniers (A-3) : vues, liste, panneau de lecture et actions sur un panier.
 *
 * @package OliAbandonedCartRecovery
 * @author  Olivier Bigras (bigrat95)
 * @link    https://olivierbigras.com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paniers.
 */
class OLI_ACR_Admin_Carts {

	/**
	 * Accroches.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_oli_acr_cart_action', array( __CLASS__, 'cart_action' ) );
		add_action( 'admin_post_oli_acr_order_action', array( __CLASS__, 'order_action' ) );
	}

	/**
	 * Vues (subsubsub) : clé => [libellé, statuts]. « Commandes en attente » seulement si la fonction est active (D3).
	 *
	 * @return array<string, array{0: string, 1: string[]}>
	 */
	public static function views() {
		$views = array(
			'all'          => array( __( 'All', 'oli-abandoned-cart-recovery' ), array() ),
			'todo'         => array( __( 'To follow up', 'oli-abandoned-cart-recovery' ), array( 'abandoned', 'failed' ) ),
			'reminded'     => array( _x( 'Reminded', 'carts view (plural)', 'oli-abandoned-cart-recovery' ), array( 'reminded' ) ),
			'recovered'    => array( _x( 'Recovered', 'carts view (plural)', 'oli-abandoned-cart-recovery' ), array( 'recovered' ) ),
			'unsubscribed' => array( _x( 'Unsubscribed', 'carts view (plural)', 'oli-abandoned-cart-recovery' ), array( 'unsubscribed' ) ),
		);
		if ( 'yes' === oli_acr_get_setting( 'pending_enabled' ) ) {
			$views['pending'] = array( __( 'Pending orders', 'oli-abandoned-cart-recovery' ), array() );
		}
		return $views;
	}

	/**
	 * Vue courante.
	 *
	 * @return string
	 */
	public static function current_view() {
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtre d'affichage.
		return array_key_exists( $view, self::views() ) ? $view : 'all';
	}

	/**
	 * Pastille de statut (courte ; l'erreur d'envoi est dans le panneau).
	 *
	 * @param string $status Statut.
	 * @return string HTML échappé.
	 */
	public static function status_pill( $status ) {
		$labels = oli_acr_statuses();
		$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		return '<span class="oli-acr-pill oli-acr-pill-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Résumé des articles : premier titre × quantité, et « +N » s'il y en a d'autres.
	 *
	 * @param object $cart Panier.
	 * @return string Texte brut.
	 */
	public static function items_summary( $cart ) {
		$items = OLI_ACR_Carts::items( $cart );
		if ( empty( $items ) ) {
			return '—';
		}
		$first = reset( $items );
		$text  = (string) $first['name'] . ' × ' . (int) $first['quantity'];
		$more  = count( $items ) - 1;
		if ( $more > 0 ) {
			/* translators: %d: number of other items. */
			$text .= ' ' . sprintf( _n( '+ %d other item', '+ %d other items', $more, 'oli-abandoned-cart-recovery' ), $more );
		}
		return $text;
	}

	/**
	 * Suppression groupée (accroche load-{écran}, avant tout affichage).
	 *
	 * @return void
	 */
	public static function maybe_bulk_delete() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Lecture du nom de l'action seulement ; nonce vérifié avant toute modification.
		$screen = isset( $_GET['screen'] ) ? sanitize_key( wp_unslash( $_GET['screen'] ) ) : '';
		$action = '';
		if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( $_GET['action'] ) ) {
			$action = 'delete';
		} elseif ( isset( $_GET['action2'] ) && 'delete' === sanitize_key( $_GET['action2'] ) ) {
			$action = 'delete';
		}
		$has_ids = ! empty( $_GET['cart_ids'] );
		// phpcs:enable
		if ( 'carts' !== $screen || 'delete' !== $action || ! $has_ids ) {
			return;
		}
		OLI_ACR_Admin::check_cap();
		check_admin_referer( 'bulk-oli_acr_carts' );
		$ids = isset( $_GET['cart_ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_GET['cart_ids'] ) ) ) : array();
		foreach ( $ids as $id ) {
			OLI_ACR_Carts::delete( $id );
		}
		OLI_ACR_Admin::redirect( 'carts', 'deleted' );
	}

	/**
	 * Affiche l'écran.
	 *
	 * @return void
	 */
	public static function render() {
		$view    = self::current_view();
		$cart_id = isset( $_GET['cart'] ) ? absint( $_GET['cart'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Affichage d'un panier.
		$cart    = $cart_id ? OLI_ACR_Carts::get( $cart_id ) : null;

		echo '<div class="oli-acr-carts' . ( $cart ? ' has-panel' : '' ) . '">';
		echo '<div class="oli-acr-carts-list">';
		if ( 'pending' === $view ) {
			self::render_views( $view );
			echo '<br class="clear"><p class="description">' . esc_html__( 'Unpaid orders that can receive the "pending order" emails.', 'oli-abandoned-cart-recovery' ) . '</p>';
			$table = new OLI_ACR_Pending_Table();
			$table->prepare_items();
			echo '<div class="oli-acr-scroll">';
			$table->display();
			echo '</div>';
		} else {
			$table = new OLI_ACR_Carts_Table( self::views()[ $view ][1] );
			$table->prepare_items();
			echo '<form method="get">';
			echo '<input type="hidden" name="page" value="' . esc_attr( OLI_ACR_Admin::SLUG ) . '"><input type="hidden" name="screen" value="carts"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
			self::render_views( $view );
			$table->search_box( __( 'Search carts', 'oli-abandoned-cart-recovery' ), 'oli-acr-search' );
			if ( 'todo' === $view ) {
				echo '<p class="description oli-acr-legend">' . esc_html__( '"To follow up" lists the carts that are abandoned or whose last email failed, right now. The "Carts left behind" tile counts the carts marked abandoned during the period, whatever their status today.', 'oli-abandoned-cart-recovery' ) . '</p>';
			}
			echo '<div class="oli-acr-scroll">';
			$table->display();
			echo '</div></form>';
		}
		echo '</div>';
		if ( $cart ) {
			self::render_panel( $cart );
		}
		echo '</div>';
	}

	/**
	 * Liens des vues avec leur nombre.
	 *
	 * @param string $current Vue courante.
	 * @return void
	 */
	private static function render_views( $current ) {
		$counts = OLI_ACR_Carts::count_by_status();
		$links  = array();
		foreach ( self::views() as $key => $view ) {
			if ( 'pending' === $key ) {
				$count = '';
			} else {
				$n     = empty( $view[1] ) ? array_sum( $counts ) : array_sum( array_intersect_key( $counts, array_flip( $view[1] ) ) );
				$count = ' <span class="count">(' . number_format_i18n( $n ) . ')</span>';
			}
			$links[] = sprintf(
				'<li><a href="%1$s"%2$s>%3$s%4$s</a>',
				esc_url( OLI_ACR_Admin::url( 'carts', 'all' === $key ? array() : array( 'view' => $key ) ) ),
				$key === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $view[0] ),
				$count // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nombre formaté.
			);
		}
		echo '<ul class="subsubsub">' . implode( ' | </li>', $links ) . '</li></ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé ci-dessus.
	}

	/**
	 * Panneau de lecture d'un panier : identité, articles, frise, actions, langue et conservation.
	 *
	 * @param object $cart Panier.
	 * @return void
	 */
	public static function render_panel( $cart ) {
		global $wpdb;
		$log = oli_acr_table( 'log' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nom de table fixe (oli_acr_table()), valeur passée par prepare().
		$sent = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$log} WHERE object_type = 'cart' AND object_id = %d ORDER BY sent_at ASC", (int) $cart->id ) );
		$name = trim( $cart->first_name . ' ' . $cart->last_name );

		echo '<aside class="oli-acr-panel oli-acr-card" aria-labelledby="oli-acr-panel-title">';
		echo '<div class="oli-acr-card-head"><h2 id="oli-acr-panel-title">' . esc_html( '' !== $name ? $name : $cart->email ) . '</h2>';
		echo '<a class="oli-acr-panel-close" href="' . esc_url( OLI_ACR_Admin::url( 'carts', 'all' !== self::current_view() ? array( 'view' => self::current_view() ) : array() ) ) . '">' . esc_html__( 'Close', 'oli-abandoned-cart-recovery' ) . '</a></div>';
		echo '<div class="oli-acr-card-body">';
		echo '<p><strong>' . esc_html( $cart->email ) . '</strong>';
		if ( $cart->phone ) {
			echo '<br>' . esc_html( $cart->phone );
		}
		if ( $cart->user_id ) {
			echo '<br><em>' . esc_html__( 'Registered customer', 'oli-abandoned-cart-recovery' ) . '</em>';
		}
		echo '</p><ul class="oli-acr-panel-items">';
		foreach ( OLI_ACR_Carts::items( $cart ) as $line ) {
			printf( '<li>%s × %d</li>', esc_html( (string) $line['name'] ), (int) $line['quantity'] );
		}
		echo '</ul><p><strong>' . wp_kses_post( wc_price( (float) $cart->cart_total, array( 'currency' => $cart->currency ) ) ) . '</strong></p>';

		// Frise : enregistré → abandonné → courriels (cliqués ou non) → récupéré / désabonné / échec.
		$steps   = array();
		$steps[] = array( __( 'Saved at checkout', 'oli-abandoned-cart-recovery' ), (string) $cart->created_at, $cart->consent ? __( 'box ticked', 'oli-abandoned-cart-recovery' ) : '' );
		if ( ! empty( $cart->abandoned_at ) ) {
			$steps[] = array( __( 'Marked abandoned', 'oli-abandoned-cart-recovery' ), (string) $cart->abandoned_at, '' );
		}
		foreach ( (array) $sent as $row ) {
			/* translators: %s: email name. */
			$steps[] = array( sprintf( __( 'Sent: %s', 'oli-abandoned-cart-recovery' ), OLI_ACR_Admin::template_name( (string) $row->template_id ) ), (string) $row->sent_at, $row->clicked_at ? __( 'clicked', 'oli-abandoned-cart-recovery' ) : __( 'not clicked', 'oli-abandoned-cart-recovery' ) );
		}
		if ( 'recovered' === $cart->status ) {
			$steps[] = array( 'direct' === $cart->recovered_via ? __( 'Ordered without a reminder', 'oli-abandoned-cart-recovery' ) : __( 'Recovered', 'oli-abandoned-cart-recovery' ), (string) $cart->recovered_at, '' );
		} elseif ( 'unsubscribed' === $cart->status ) {
			$steps[] = array( __( 'Unsubscribed', 'oli-abandoned-cart-recovery' ), (string) $cart->updated_at, '' );
		} elseif ( 'failed' === $cart->status ) {
			$steps[] = array( __( 'Send failed', 'oli-abandoned-cart-recovery' ), (string) $cart->updated_at, (string) $cart->last_error );
		}
		if ( in_array( $cart->status, OLI_ACR_Carts::LIVE_STATUSES, true ) && ! empty( $cart->next_send_at ) ) {
			$steps[] = array( __( 'Next email planned', 'oli-abandoned-cart-recovery' ), (string) $cart->next_send_at, '', true );
		}
		echo '<ol class="oli-acr-timeline">';
		foreach ( $steps as $step ) {
			printf(
				'<li class="%1$s"><strong>%2$s</strong><br>%3$s%4$s</li>',
				empty( $step[3] ) ? 'is-done' : 'is-next',
				esc_html( $step[0] ),
				OLI_ACR_Admin::relative_date( $step[1] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Échappé dans relative_date().
				'' !== $step[2] ? ' · ' . esc_html( $step[2] ) : ''
			);
		}
		echo '</ol>';
		if ( 'failed' === $cart->status && $cart->fail_count ) {
			/* translators: %d: number of failed attempts. */
			echo '<p class="description">' . esc_html( sprintf( _n( '%d failed attempt.', '%d failed attempts.', (int) $cart->fail_count, 'oli-abandoned-cart-recovery' ), (int) $cart->fail_count ) ) . '</p>';
		}

		echo '<p class="oli-acr-panel-actions">';
		if ( self::can_send( $cart ) ) {
			echo '<a class="button button-primary" href="' . esc_url( self::action_url( $cart, 'send' ) ) . '">' . esc_html__( 'Send the next reminder', 'oli-abandoned-cart-recovery' ) . '</a> ';
		}
		echo '<a class="button oli-acr-delete" href="' . esc_url( self::action_url( $cart, 'delete' ) ) . '">' . esc_html__( 'Delete', 'oli-abandoned-cart-recovery' ) . '</a></p>';

		// Ligne méta : langue · conservation.
		$meta = array();
		if ( '' !== (string) $cart->language ) {
			$meta[] = OLI_ACR_Lang::label( OLI_ACR_Lang::resolve( (string) $cart->language ) );
		}
		$days = self::days_left( $cart );
		if ( null !== $days ) {
			/* translators: %d: number of days. */
			$meta[] = sprintf( _n( 'will be removed in %d day', 'will be removed in %d days', $days, 'oli-abandoned-cart-recovery' ), $days );
		}
		if ( $meta ) {
			echo '<p class="description">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}
		echo '</div></aside>';
	}

	/**
	 * Jours avant le retrait automatique (delete_carts_after pour un panier non récupéré, retention_days sinon).
	 *
	 * @param object $cart Panier.
	 * @return int|null Null = jamais.
	 */
	public static function days_left( $cart ) {
		if ( in_array( $cart->status, array( 'open', 'abandoned', 'reminded', 'failed' ), true ) ) {
			$seconds = oli_acr_duration_to_seconds( oli_acr_get_setting( 'delete_carts_after' ) );
			$from    = (int) strtotime( $cart->updated_at . ' UTC' );
		} else {
			$seconds = absint( oli_acr_get_setting( 'retention_days' ) ) * DAY_IN_SECONDS;
			$from    = (int) strtotime( $cart->created_at . ' UTC' );
		}
		if ( $seconds <= 0 || $from <= 0 ) {
			return null;
		}
		return max( 0, (int) ceil( ( $from + $seconds - time() ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Le panier peut-il recevoir la prochaine relance maintenant ?
	 *
	 * @param object $cart Panier.
	 * @return bool
	 */
	public static function can_send( $cart ) {
		return in_array( $cart->status, OLI_ACR_Carts::LIVE_STATUSES, true ) && ! (int) $cart->order_id && ! oli_acr_is_unsubscribed( $cart->email );
	}

	/**
	 * URL d'une action sur un panier (nonce lié à l'action et au panier).
	 *
	 * @param object $cart Panier.
	 * @param string $do   send | delete.
	 * @return string
	 */
	public static function action_url( $cart, $do ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=oli_acr_cart_action&do=' . $do . '&cart=' . (int) $cart->id ), 'oli_acr_cart_action_' . $do . '_' . (int) $cart->id );
	}

	/**
	 * Actions sur un panier : envoi immédiat ou suppression.
	 *
	 * @return void
	 */
	public static function cart_action() {
		OLI_ACR_Admin::check_cap();
		$id = isset( $_GET['cart'] ) ? absint( $_GET['cart'] ) : 0;
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		check_admin_referer( 'oli_acr_cart_action_' . $do . '_' . $id );
		$cart = OLI_ACR_Carts::get( $id );
		if ( ! $cart ) {
			OLI_ACR_Admin::redirect( 'carts', 'not_sent' );
		}
		if ( 'delete' === $do ) {
			OLI_ACR_Carts::delete( $id );
			OLI_ACR_Admin::redirect( 'carts', 'deleted' );
		}
		$back = array( 'cart' => $id );
		// Seuls les paniers encore actifs peuvent être relancés (pas récupérés, désabonnés ni terminés).
		if ( 'send' !== $do || ! in_array( $cart->status, OLI_ACR_Carts::LIVE_STATUSES, true ) || oli_acr_is_unsubscribed( $cart->email ) || (int) $cart->order_id > 0 || ! OLI_ACR_Scheduler::cart_owner_tracked( $cart ) ) {
			OLI_ACR_Admin::redirect( 'carts', 'not_sent', $back );
		}
		$templates = OLI_ACR_Templates::active( 'cart' );
		$done      = array_filter( explode( ',', (string) $cart->sent_templates ) );
		$next      = OLI_ACR_Scheduler::next_template( $templates, $done );
		if ( ! $next ) {
			OLI_ACR_Admin::redirect( 'carts', 'not_sent', $back );
		}
		if ( empty( $cart->abandoned_at ) ) {
			$cart->abandoned_at = oli_acr_now();
			OLI_ACR_Carts::update( $id, array( 'abandoned_at' => $cart->abandoned_at ) );
		}
		// Envoi manuel : le compteur d'échecs repart de zéro.
		$cart->fail_count = 0;
		$ok               = OLI_ACR_Mailer::send_cart_email( $cart, $next['id'], $next );
		if ( ! $ok ) {
			OLI_ACR_Scheduler::cart_failed( $cart, $next['id'] );
		}
		if ( $ok ) {
			// La suite de la séquence continue automatiquement (si les relances sont actives).
			$done[]    = $next['id'];
			$following = OLI_ACR_Scheduler::next_template( $templates, $done );
			$base      = (int) strtotime( $cart->abandoned_at . ' UTC' );
			OLI_ACR_Carts::update( $id, array( 'next_send_at' => $following ? gmdate( 'Y-m-d H:i:s', max( time() + 60, $base + oli_acr_duration_to_seconds( $following['delay'] ) ) ) : null ) );
		}
		OLI_ACR_Admin::redirect( 'carts', $ok ? 'sent' : 'not_sent', $back );
	}

	/**
	 * Envoi immédiat d'une relance de commande en attente.
	 *
	 * @return void
	 */
	public static function order_action() {
		OLI_ACR_Admin::check_cap();
		$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		check_admin_referer( 'oli_acr_order_action_' . $order_id );
		$back  = array( 'view' => 'pending' );
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order && $order->has_status( 'pending' ) && ! $order->get_meta( '_oli_acr_done' ) ) {
			$done = array_filter( (array) $order->get_meta( '_oli_acr_sent' ) );
			foreach ( OLI_ACR_Templates::active( 'order' ) as $tpl_id => $tpl ) {
				if ( ! in_array( $tpl_id, $done, true ) ) {
					$order->delete_meta_data( '_oli_acr_fail_count' );
					$order->delete_meta_data( '_oli_acr_retry_at' );
					$ok = OLI_ACR_Mailer::send_order_email( $order, $tpl_id, $tpl );
					if ( ! $ok ) {
						OLI_ACR_Scheduler::order_failed( $order, $tpl_id );
					} else {
						$order->save();
					}
					OLI_ACR_Admin::redirect( 'carts', $ok ? 'sent' : 'not_sent', $back );
				}
			}
		}
		OLI_ACR_Admin::redirect( 'carts', 'not_sent', $back );
	}
}
