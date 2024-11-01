<?php
/*
Plugin Name: WPC Customer Care for WooCommerce
Description: Customer care system for WooCommerce.
Version: 1.0.5
Author: WPClever.net
Author URI: https://wpclever.net
Text Domain: woocc
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 5.3.2
WC requires at least: 3.0
WC tested up to: 3.8.1
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WOOCC_VERSION', '1.0.5' );
define( 'WOOCC_URI', plugin_dir_url( __FILE__ ) );
define( 'WOOCC_REVIEWS', 'https://wordpress.org/support/plugin/woo-customer-care/reviews/?filter=5' );
define( 'WOOCC_CHANGELOG', 'https://wordpress.org/plugins/woo-customer-care/#developers' );
define( 'WOOCC_DISCUSSION', 'https://wordpress.org/support/plugin/woo-customer-care' );
if ( ! defined( 'WPC_URI' ) ) {
	define( 'WPC_URI', WOOCC_URI );
}

include 'includes/wpc-menu.php';
include 'includes/wpc-dashboard.php';

if ( ! class_exists( 'WPCleverWoocc' ) ) {
	class WPCleverWoocc {
		private static $current_user_id = 0;
		private static $current_user_name = '';
		private static $current_user_email = '';
		private static $current_user_roles = array();

		private static $assign_auto = 'no';
		private static $assign_list = array( 'shop_manager' );
		private static $assign_self = array( 'administrator', 'shop_manager' );
		private static $remove_self = array( 'administrator', 'shop_manager' );
		private static $assign_another = array( 'administrator' );
		private static $remove_another = array( 'administrator' );
		private static $delete_note = array( 'administrator' );
		private static $assign_auto_note = 'This order was auto-assigned to $1';
		private static $assign_self_note = '$1 start caring this order';
		private static $assign_another_note = '$1 assigned this order to $2';
		private static $remove_note = '$1 released this order';

		function __construct() {
			// init
			add_action( 'init', array( $this, 'woocc_init' ) );

			// menu
			add_action( 'admin_menu', array( $this, 'woocc_admin_menu' ) );

			// enqueue
			add_action( 'admin_enqueue_scripts', array( $this, 'woocc_admin_enqueue_scripts' ) );

			// order column
			add_filter( 'manage_shop_order_posts_columns', array( $this, 'woocc_shop_order_columns' ), 99 );
			add_action( 'manage_shop_order_posts_custom_column', array(
				$this,
				'woocc_shop_order_columns_content'
			), 99, 2 );

			// metabox
			add_action( 'add_meta_boxes', array( $this, 'woocc_add_meta_boxes' ) );

			// ajax assign
			add_action( 'wp_ajax_woocc_assign', array( $this, 'woocc_assign' ) );

			// ajax remove
			add_action( 'wp_ajax_woocc_remove', array( $this, 'woocc_remove' ) );

			// ajax delete note
			add_action( 'wp_ajax_woocc_delete_note', array( $this, 'woocc_delete_note' ) );

			// admin footer
			add_action( 'admin_footer', array( $this, 'woocc_admin_footer' ) );

			// secure order care
			add_filter( 'comments_clauses', array( $this, 'woocc_comments_clauses' ), 10, 1 );
			add_action( 'comment_feed_where', array( $this, 'woocc_comment_feed_where' ) );

			// order filter
			add_action( 'restrict_manage_posts', array( $this, 'woocc_restrict_manage_posts' ) );
			add_filter( 'parse_query', array( $this, 'woocc_parse_query' ) );

			// auto assign
			add_action( 'woocommerce_thankyou', array( $this, 'woocc_thankyou' ), 10, 1 );

			// add plugin links
			add_filter( 'plugin_action_links', array( $this, 'woocc_plugin_action_links' ), 10, 2 );
		}

		function woocc_init() {
			$current_user             = wp_get_current_user();
			self::$current_user_id    = $current_user->ID;
			self::$current_user_name  = $current_user->display_name;
			self::$current_user_email = $current_user->user_email;
			self::$current_user_roles = $current_user->roles;

			self::$assign_auto         = get_option( '_woocc_assign_auto', self::$assign_auto );
			self::$assign_list         = get_option( '_woocc_assign_list', self::$assign_list );
			self::$assign_self         = get_option( '_woocc_assign_self', self::$assign_self );
			self::$remove_self         = get_option( '_woocc_remove_self', self::$remove_self );
			self::$assign_another      = get_option( '_woocc_assign_another', self::$assign_another );
			self::$remove_another      = get_option( '_woocc_remove_another', self::$remove_another );
			self::$delete_note         = get_option( '_woocc_delete_note', self::$delete_note );
			self::$assign_auto_note    = get_option( '_woocc_assign_auto_note', self::$assign_auto_note );
			self::$assign_self_note    = get_option( '_woocc_assign_self_note', self::$assign_self_note );
			self::$assign_another_note = get_option( '_woocc_assign_another_note', self::$assign_another_note );
			self::$remove_note         = get_option( '_woocc_remove_note', self::$remove_note );
		}

		function woocc_admin_menu() {
			add_submenu_page( 'wpclever', esc_html__( 'WPC Customer Care', 'woocc' ), esc_html__( 'Customer Care', 'woocc' ), 'manage_options', 'wpclever-woocc', array(
				&$this,
				'woocc_admin_menu_content'
			) );
		}

		function woocc_admin_menu_content() {
			$page_slug  = 'wpclever-woocc';
			$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'how';
			?>
            <div class="wpclever_settings_page wrap">
                <h1 class="wpclever_settings_page_title">WPC Customer Care <?php echo WOOCC_VERSION; ?></h1>
                <div class="wpclever_settings_page_desc about-text">
                    <p>
						<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woofc' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                        <br/>
                        <a href="<?php echo esc_url( WOOCC_REVIEWS ); ?>"
                           target="_blank"><?php esc_html_e( 'Reviews', 'woofc' ); ?></a> | <a
                                href="<?php echo esc_url( WOOCC_CHANGELOG ); ?>"
                                target="_blank"><?php esc_html_e( 'Changelog', 'woofc' ); ?></a>
                        | <a href="<?php echo esc_url( WOOCC_DISCUSSION ); ?>"
                             target="_blank"><?php esc_html_e( 'Discussion', 'woofc' ); ?></a>
                    </p>
                </div>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="?page=<?php echo $page_slug; ?>&amp;tab=how"
                           class="nav-tab <?php echo $active_tab == 'how' ? 'nav-tab-active' : ''; ?>">How to use?</a>
                        <a href="?page=<?php echo $page_slug; ?>&amp;tab=settings"
                           class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                        <a href="https://wpclever.net/support?utm_source=support&utm_medium=woocc&utm_campaign=wporg"
                           class="nav-tab" target="_blank">Premium Support</a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
					<?php if ( $active_tab === 'how' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                WooCommerce Customer Care is a complete customer care system for WooCommerce. You can
                                self-assign or assign an order to another one. When the order was assigned to one
                                manager,
                                another one can't access and make any change for this order. All actions will be logged
                                and we
                                can easily in track who is caring the order.
                            </p>
                            <p>1. The order detail screen</p>
                            <p><img src="<?php echo WOOCC_URI; ?>assets/images/how-01.jpg"/></p>
                            <p>2. All orders of the site</p>
                            <p><img src="<?php echo WOOCC_URI; ?>assets/images/how-02.jpg"/></p>
                            <p>3. Prevent access when another one is caring</p>
                            <p><img src="<?php echo WOOCC_URI; ?>assets/images/how-03.jpg"/></p>
                        </div>
					<?php } elseif ( $active_tab === 'settings' ) { ?>
                        <form method="post" action="options.php">
							<?php wp_nonce_field( 'update-options' ) ?>
                            <table class="form-table">
                                <tr>
                                    <th>Auto assign?</th>
                                    <td>
                                        <select name="_woocc_assign_auto">
                                            <option
                                                    value="yes" <?php echo( get_option( '_woocc_assign_auto', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
                                                Yes
                                            </option>
                                            <option
                                                    value="no" <?php echo( get_option( '_woocc_assign_auto', 'no' ) == 'no' ? 'selected' : '' ); ?>>
                                                No
                                            </option>
                                        </select>
                                        <span class="description">
											When enable this feature, a new order will be auto-assigned to random user in below list.
										</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Who in assign list?</th>
                                    <td>
                                        <select name="_woocc_assign_list[]" multiple="multiple">
                                            <option
                                                    value="administrator" <?php echo( in_array( 'administrator', (Array) get_option( '_woocc_assign_list', array( 'shop_manager' ) ) ) ? 'selected' : '' ); ?>>
                                                Administrator
                                            </option>
                                            <option
                                                    value="shop_manager" <?php echo( in_array( 'shop_manager', (Array) get_option( '_woocc_assign_list', array( 'shop_manager' ) ) ) ? 'selected' : '' ); ?>>
                                                Shop Manager
                                            </option>
                                        </select>
                                        <span class="description">
											Choose the user role of who will appear in "auto-assign" and "assign to another one" list
										</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Who can self-assign?</th>
                                    <td>
                                        <select name="_woocc_assign_self[]" multiple="multiple">
                                            <option
                                                    value="administrator" <?php echo( in_array( 'administrator', (Array) get_option( '_woocc_assign_self', array(
												'administrator',
												'shop_manager'
											) ) ) ? 'selected' : '' ); ?>>
                                                Administrator
                                            </option>
                                            <option
                                                    value="shop_manager" <?php echo( in_array( 'shop_manager', (Array) get_option( '_woocc_assign_self', array(
												'administrator',
												'shop_manager'
											) ) ) ? 'selected' : '' ); ?>>
                                                Shop Manager
                                            </option>
                                        </select>
                                        <span class="description">
											Just work when the order is not caring by anyone
										</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Who can assign to another one?</th>
                                    <td>
                                        <select name="_woocc_assign_another[]" multiple="multiple">
                                            <option
                                                    value="administrator" <?php echo( in_array( 'administrator', (Array) get_option( '_woocc_assign_another', array( 'administrator' ) ) ) ? 'selected' : '' ); ?>>
                                                Administrator
                                            </option>
                                            <option
                                                    value="shop_manager" <?php echo( in_array( 'shop_manager', (Array) get_option( '_woocc_assign_another', array( 'administrator' ) ) ) ? 'selected' : '' ); ?>>
                                                Shop Manager
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Who can self-remove?</th>
                                    <td>
                                        <select name="_woocc_remove_self[]" multiple="multiple">
                                            <option
                                                    value="administrator" <?php echo( in_array( 'administrator', (Array) get_option( '_woocc_remove_self', array(
												'administrator',
												'shop_manager'
											) ) ) ? 'selected' : '' ); ?>>
                                                Administrator
                                            </option>
                                            <option
                                                    value="shop_manager" <?php echo( in_array( 'shop_manager', (Array) get_option( '_woocc_remove_self', array(
												'administrator',
												'shop_manager'
											) ) ) ? 'selected' : '' ); ?>>
                                                Shop Manager
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Who can remove another one?</th>
                                    <td>
                                        <select name="_woocc_remove_another[]" multiple="multiple">
                                            <option
                                                    value="administrator" <?php echo( in_array( 'administrator', (Array) get_option( '_woocc_remove_another', array( 'administrator' ) ) ) ? 'selected' : '' ); ?>>
                                                Administrator
                                            </option>
                                            <option
                                                    value="shop_manager" <?php echo( in_array( 'shop_manager', (Array) get_option( '_woocc_remove_another', array( 'administrator' ) ) ) ? 'selected' : '' ); ?>>
                                                Shop Manager
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Auto assign note</th>
                                    <td>
                                        <input type="text" class="regular-text" name="_woocc_assign_auto_note"
                                               value="<?php echo get_option( '_woocc_assign_auto_note', 'This order was auto-assigned to $1' ); ?>"/>
                                        <span class="description">Use $1 for assignee name.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Self-assign note</th>
                                    <td>
                                        <input type="text" class="regular-text" name="_woocc_assign_self_note"
                                               value="<?php echo get_option( '_woocc_assign_self_note', '$1 start caring this order' ); ?>"/>
                                        <span class="description">Use $1 for assignee name.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Assign to another one note</th>
                                    <td>
                                        <input type="text" class="regular-text" name="_woocc_assign_another_note"
                                               value="<?php echo get_option( '_woocc_assign_another_note', '$1 assigned this order to $2' ); ?>"/>
                                        <span class="description">Use $1 and $2 for assignees name.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Remove note</th>
                                    <td>
                                        <input type="text" class="regular-text" name="_woocc_remove_note"
                                               value="<?php echo get_option( '_woocc_remove_note', '$1 released this order' ); ?>"/>
                                        <span class="description">Use $1 for assignee name.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Who can delete note?</th>
                                    <td>
                                        <select name="_woocc_delete_note[]" multiple="multiple">
                                            <option
                                                    value="administrator" <?php echo( in_array( 'administrator', (Array) get_option( '_woocc_delete_note', array( 'administrator' ) ) ) ? 'selected' : '' ); ?>>
                                                Administrator
                                            </option>
                                            <option
                                                    value="shop_manager" <?php echo( in_array( 'shop_manager', (Array) get_option( '_woocc_delete_note', array( 'administrator' ) ) ) ? 'selected' : '' ); ?>>
                                                Shop Manager
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <input type="submit" name="submit" class="button button-primary"
                                               value="Update Options"/>
                                        <input type="hidden" name="action" value="update"/>
                                        <input type="hidden" name="page_options"
                                               value="_woocc_assign_auto,_woocc_assign_list,_woocc_assign_self,_woocc_assign_another,_woocc_remove_self,_woocc_assign_auto_note,_woocc_assign_self_note,_woocc_assign_another_note,_woocc_remove_note,_woocc_delete_note"/>
                                    </th>
                                </tr>
                            </table>
                        </form>
					<?php } ?>
                </div>
            </div>
			<?php
		}

		function woocc_admin_enqueue_scripts() {
			wp_enqueue_style( 'woocc-backend', WOOCC_URI . 'assets/css/backend.css' );
			wp_enqueue_script( 'woocc-backend', WOOCC_URI . 'assets/js/backend.js', array( 'jquery' ), WOOCC_VERSION, true );
			wp_localize_script( 'woocc-backend', 'woocc_vars', array(
					'url'   => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'woocc_nonce' )
				)
			);
		}

		function woocc_plugin_action_links( $links, $file ) {
			static $plugin;
			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( __FILE__ );
			}
			if ( $plugin == $file ) {
				$settings_link = '<a href="' . admin_url( 'admin.php?page=wpclever-woocc&tab=settings' ) . '">' . esc_html__( 'Settings', 'woocc' ) . '</a>';
				$links[]       = '<a href="https://wpclever.net/support?utm_source=support&utm_medium=woocc&utm_campaign=wporg" target="_blank">' . esc_html__( 'Premium Support', 'woocc' ) . '</a>';
				array_unshift( $links, $settings_link );
			}

			return $links;
		}

		function woocc_assign() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woocc_nonce' ) ) {
				die( esc_html__( 'Permissions check failed', 'woocc' ) );
			}
			if ( isset( $_POST['order'] ) ) {
				$order_id = (int) $_POST['order'];
				if ( isset( $_POST['user'] ) ) {
					// assign to another one
					if ( self::woocc_has_role( self::$assign_another ) ) {
						$assignee           = (int) $_POST['user'];
						$assignee_user      = get_user_by( 'id', $assignee );
						$assignee_user_name = $assignee_user->display_name;
						update_post_meta( $order_id, '_woocc_assignee', $assignee );
						update_post_meta( $order_id, '_woocc_time', current_time( 'timestamp' ) );
						if ( $assignee == self::$current_user_id ) {
							$comment_content = str_replace( '$1', '<strong>' . self::$current_user_name . '</strong>', self::$assign_self_note );
						} else {
							$comment_content = str_replace( '$1', '<strong>' . self::$current_user_name . '</strong>', self::$assign_another_note );
							$comment_content = str_replace( '$2', '<strong>' . $assignee_user_name . '</strong>', $comment_content );
						}
						self::woocc_insert_comment( $order_id, $comment_content );
					}
				} else {
					// assign to me
					if ( self::woocc_has_role( self::$assign_self ) ) {
						update_post_meta( $order_id, '_woocc_assignee', self::$current_user_id );
						update_post_meta( $order_id, '_woocc_time', current_time( 'timestamp' ) );
						$comment_content = str_replace( '$1', '<strong>' . self::$current_user_name . '</strong>', self::$assign_self_note );
						self::woocc_insert_comment( $order_id, $comment_content );
					}
				}
				self::woocc_metabox_content( $order_id );
			}
			die();
		}

		function woocc_remove() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woocc_nonce' ) ) {
				die( esc_html__( 'Permissions check failed', 'woocc' ) );
			}
			if ( isset( $_POST['order'] ) ) {
				$order_id = (int) $_POST['order'];
				if ( isset( $_POST['user'] ) ) {
					// remove another one
					if ( self::woocc_has_role( self::$remove_another ) ) {
						delete_post_meta( $order_id, '_woocc_assignee' );
						update_post_meta( $order_id, '_woocc_time', current_time( 'timestamp' ) );
						$comment_content = '<strong>' . self::$current_user_name . '</strong> release this order';
						self::woocc_insert_comment( $order_id, $comment_content );
					}
				} else {
					// remove me
					if ( self::woocc_has_role( self::$remove_self ) && get_post_meta( $order_id, '_woocc_assignee', true ) && ( get_post_meta( $order_id, '_woocc_assignee', true ) == self::$current_user_id ) ) {
						delete_post_meta( $order_id, '_woocc_assignee' );
						update_post_meta( $order_id, '_woocc_time', current_time( 'timestamp' ) );
						$comment_content = str_replace( '$1', '<strong>' . self::$current_user_name . '</strong>', self::$remove_note );
						self::woocc_insert_comment( $order_id, $comment_content );
					}
				}
				self::woocc_metabox_content( $order_id );
			}
			die();
		}

		function woocc_delete_note() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woocc_nonce' ) ) {
				die( esc_html__( 'Permissions check failed', 'woocc' ) );
			}
			if ( isset( $_POST['order'] ) && isset( $_POST['note'] ) ) {
				wp_delete_comment( (int) $_POST['note'], true );
				self::woocc_metabox_content( (int) $_POST['order'] );
			}
			die();
		}

		function woocc_insert_comment( $order_id, $comment_content ) {
			$comment_data = array(
				'comment_post_ID'      => $order_id,
				'comment_author'       => self::$current_user_name,
				'comment_author_email' => self::$current_user_email,
				'comment_author_url'   => '',
				'comment_content'      => $comment_content,
				'comment_agent'        => 'woocc',
				'comment_type'         => 'order_care',
				'comment_parent'       => 0,
				'comment_approved'     => 1,
			);
			wp_insert_comment( $comment_data );
		}

		function woocc_shop_order_columns( $columns ) {
			$columns['woocc'] = esc_html__( 'Care', 'woocc' );

			return $columns;
		}

		function woocc_shop_order_columns_content( $column, $order_id ) {
			if ( $column == 'woocc' ) {
				$assignee = get_post_meta( $order_id, '_woocc_assignee', true );
				if ( $assignee ) {
					$assignee_user      = get_user_by( 'id', $assignee );
					$assignee_user_name = $assignee_user->display_name;
					echo '<a href="#">' . $assignee_user_name . '</a>';
					if ( get_post_meta( $order_id, '_woocc_time', true ) ) {
						echo '<small class="meta">' . date_i18n( wc_date_format(), get_post_meta( $order_id, '_woocc_time', true ) ) . ' ' . date_i18n( wc_time_format(), get_post_meta( $order_id, '_woocc_time', true ) ) . '</span>';
					}
				}
			}
		}

		function woocc_add_meta_boxes() {
			add_meta_box( 'woocc', esc_html__( 'Order care', 'woocc' ), array(
				&$this,
				'woocc_add_meta_boxes_callback'
			), 'shop_order', 'side', 'high' );
		}

		function woocc_add_meta_boxes_callback( $order ) {
			$order_id = $order->ID;
			echo '<div class="woocc-metabox" id="woocc_metabox">';
			if ( isset( $_GET['post'] ) ) {
				self::woocc_metabox_content( $order_id );
			} else {
				esc_html_e( 'Customer Care just work after you created the order.', 'woocc' );
			}
			echo '</div>';
		}

		function woocc_metabox_content( $order_id ) {
			$assignee = get_post_meta( $order_id, '_woocc_assignee', true );
			echo '<div class="woocc-actions">';
			if ( $assignee ) {
				$assignee_user      = get_user_by( 'id', $assignee );
				$assignee_user_name = $assignee_user->display_name;
				if ( $assignee == self::$current_user_id ) {
					echo '<div class="woocc-notice">' . esc_html__( 'You are caring this order', 'woocc' ) . '</div>';
					if ( self::woocc_has_role( self::$remove_self ) ) {
						echo '<a class="button" id="woocc_remove_me" href="#" data-order="' . $order_id . '">Remove Me</a>';
					}
				} else {
					echo '<div class="woocc-notice">' . sprintf( esc_html__( '%s is caring this order', 'woocc' ), $assignee_user_name ) . '</div>';
					if ( self::woocc_has_role( self::$remove_another ) ) {
						echo '<a class="button" id="woocc_remove_user" href="#" data-order="' . $order_id . '" data-user="' . $assignee . '">Remove</a>';
					}
				}
			} else {
				echo '<div class="woocc-notice">' . esc_html__( 'Nobody is caring this order', 'woocc' ) . '</div>';
				if ( self::woocc_has_role( self::$assign_self ) ) {
					echo '<a class="button" id="woocc_assign_me" href="#" data-order="' . $order_id . '">' . esc_html__( 'Assign to Me', 'woocc' ) . '</a>';
				}
			}
			if ( self::woocc_has_role( self::$assign_another ) ) {
				echo '<span class="woocc-text"> ' . esc_html__( 'or Assign to...', 'woocc' ) . '</span>';
				echo '<div class="woocc-user-choose">';
				$args  = array(
					'role__in' => self::$assign_list,
					'fields'   => array( 'ID', 'display_name', 'user_email' ),
				);
				$users = get_users( $args );
				if ( is_array( $users ) && ( count( $users ) > 0 ) ) {
					echo '<select id="woocc_assign_user">';
					foreach ( $users as $user ) {
						echo '<option value="' . $user->ID . '" ' . ( $assignee == $user->ID ? 'selected' : '' ) . '>' . $user->display_name . ' (#' . $user->ID . ' - ' . $user->user_email . ')</option>';
					}
					echo '</select><a class="button" id="woocc_assign_to" href="#" data-order="' . $order_id . '"><i class="dashicons dashicons-yes"></i></a>';
				}
				echo '</div>';
			}
			echo '</div>';
			$args = array(
				'post_id' => $order_id,
				'orderby' => 'comment_ID',
				'order'   => 'DESC',
				'approve' => 'approve',
				'type'    => 'order_care',
			);
			remove_filter( 'comments_clauses', array( $this, 'woocc_comments_clauses' ), 10, 1 );
			$notes = get_comments( $args );
			add_filter( 'comments_clauses', array( $this, 'woocc_comments_clauses' ), 10, 1 );
			if ( $notes ) {
				echo '<ul class="order_notes woocc-notes">';
				$note_count = 1;
				foreach ( $notes as $note ) { ?>
                    <li rel="<?php echo absint( $note->comment_ID ); ?>" class="note">
                        <div class="note_content">
							<?php echo wpautop( wptexturize( wp_kses_post( $note->comment_content ) ) ); ?>
                        </div>
                        <p class="meta">
                            <abbr class="exact-date"
                                  title="<?php echo $note->comment_date; ?>"><?php printf( esc_html__( 'added on %1$s at %2$s', 'woocc' ), date_i18n( wc_date_format(), strtotime( $note->comment_date ) ), date_i18n( wc_time_format(), strtotime( $note->comment_date ) ) ); ?></abbr>
							<?php
							if ( esc_html__( 'woocc', 'woocc' ) !== $note->comment_author ) :
								printf( ' ' . esc_html__( 'by %s', 'woocc' ), $note->comment_author );
							endif;
							?> <?php if ( ( $note_count > 1 ) && self::woocc_has_role( self::$delete_note ) ) { ?>
                                <a href="#" class="delete_note woocc-delete-note" role="button"
                                   data-note="<?php echo absint( $note->comment_ID ); ?>"
                                   data-order="<?php echo $order_id; ?>">Delete note</a>
							<?php } ?>
                        </p>
                    </li>
					<?php
					$note_count ++;
				}
				echo '</ul>';
			}
		}

		function woocc_admin_footer() {
			$current_screen = get_current_screen();
			if ( ( $current_screen->id == 'shop_order' ) && isset( $_GET['post'] ) ) {
				$order_id           = (int) $_GET['post'];
				$assignee           = get_post_meta( $order_id, '_woocc_assignee', true );
				$assignee_user      = get_user_by( 'id', $assignee );
				$assignee_user_name = $assignee_user->display_name;
				if ( $assignee && ( $assignee != self::$current_user_id ) ) {
					?>
                    <div id="post-lock-dialog" class="notification-dialog-wrap">
                        <div class="notification-dialog-background"></div>
                        <div class="notification-dialog">
                            <div class="post-locked-message">
                                <div class="post-locked-avatar">
									<?php echo get_avatar( $assignee, 64 ); ?>
                                </div>
                                <p class="currently-editing wp-tab-first" tabindex="0">
                                    <strong><?php echo $assignee_user_name; ?></strong> is caring this order.
                                </p>
                                <p>
                                    <a class="button" onclick="window.history.go(-1); return false;">
										<?php esc_html_e( 'Go back', 'woocc' ); ?>
                                    </a>
									<?php if ( self::woocc_has_role( self::$remove_another ) ) {
										echo '<a class="button button-primary" id="woocc_take_over">' . esc_html__( 'Take over', 'woocc' ) . '</a>';
									} ?>
                                </p>
                            </div>
                        </div>
                    </div>
					<?php if ( ! self::woocc_has_role( self::$remove_another ) ) {
						echo '<script>jQuery("#woocommerce-order-actions").remove();jQuery("#woocommerce-order-items").remove();</script>';
					}
				}
			}
		}

		function woocc_comments_clauses( $clauses ) {
			$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != 'order_care' ";

			return $clauses;
		}

		function woocc_comment_feed_where( $where ) {
			return $where . ( $where ? ' AND ' : '' ) . " comment_type != 'order_care' ";
		}

		function woocc_restrict_manage_posts() {
			$type = 'post';
			if ( isset( $_GET['post_type'] ) ) {
				$type = esc_attr( $_GET['post_type'] );
			}
			if ( 'shop_order' == $type ) {
				$args  = array(
					'role__in' => array( 'administrator', 'shop_manager' ),
					'fields'   => array( 'ID', 'display_name', 'user_email' ),
				);
				$users = get_users( $args );
				if ( is_array( $users ) && ( count( $users ) > 0 ) ) {
					echo '<select name="woocc"><option value="">' . esc_html__( 'Care by...', 'woocc' ) . '</option>';
					foreach ( $users as $user ) {
						echo '<option value="' . $user->ID . '" ' . ( isset( $_GET['woocc'] ) && ( $_GET['woocc'] == $user->ID ) ? 'selected' : '' ) . '>' . $user->display_name . ' (#' . $user->ID . ' - ' . $user->user_email . ')</option>';
					}
					echo '</select>';
				}
			}
		}

		function woocc_parse_query( $query ) {
			global $pagenow;
			$type = 'post';
			if ( isset( $_GET['post_type'] ) ) {
				$type = esc_attr( $_GET['post_type'] );
			}
			if ( 'shop_order' == $type && is_admin() && $pagenow == 'edit.php' && isset( $_GET['woocc'] ) && $_GET['woocc'] != '' ) {
				$query->query_vars['meta_key']   = '_woocc_assignee';
				$query->query_vars['meta_value'] = (int) $_GET['woocc'];
			}
		}

		function woocc_has_role( $setting_roles ) {
			$user_roles = self::$current_user_roles;
			foreach ( $user_roles as $user_role ) {
				if ( in_array( $user_role, $setting_roles ) ) {
					return true;
				}
			}

			return false;
		}

		function woocc_get_random_user() {
			$args  = array(
				'role__in' => self::$assign_list,
				'fields'   => array( 'ID', 'display_name', 'user_email' ),
			);
			$users = get_users( $args );
			if ( is_array( $users ) && ( count( $users ) > 0 ) ) {
				$random_key = array_rand( $users, 1 );

				return $users[ $random_key ];
			} else {
				return false;
			}
		}

		function woocc_thankyou( $order_id ) {
			// auto assign
			if ( ( self::$assign_auto == 'yes' ) && ( $assignee = self::woocc_get_random_user() ) ) {
				update_post_meta( $order_id, '_woocc_assignee', $assignee->ID );
				update_post_meta( $order_id, '_woocc_time', current_time( 'timestamp' ) );
				$comment_content = str_replace( '$1', '<strong>' . $assignee->display_name . '</strong>', self::$assign_auto_note );
				self::woocc_insert_comment( $order_id, $comment_content );
			}
		}
	}

	new WPCleverWoocc();
}