<?php
/**
 * Plugin Name:       HD Product Traceability
 * Plugin URI:        https://linxhq.sg/
 * Description:       Thêm nút "Truy xuất nguồn gốc" vào trang sản phẩm WooCommerce, mở modal chứa tài liệu PDF truy xuất (xem trực tiếp) và thông tin doanh nghiệp.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            LinxHQ
 * Author URI:        https://linxhq.sg/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hd-product-traceability
 * Domain Path:       /languages
 * WC requires at least: 7.0
 *
 * @package HD_Product_Traceability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HDPT_VERSION', '1.0.0' );
define( 'HDPT_PLUGIN_FILE', __FILE__ );
define( 'HDPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HDPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HDPT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader thủ công kiểu PSR-4 cho các class HDPT_*.
 *
 * Map tên class -> file trong thư mục includes/.
 *
 * @param string $class_name Tên class cần nạp.
 */
function hdpt_autoload( $class_name ) {
	if ( 0 !== strpos( $class_name, 'HDPT_' ) ) {
		return;
	}

	$map = array(
		'HDPT_Plugin'        => 'includes/class-hdpt-plugin.php',
		'HDPT_Product_Data'  => 'includes/admin/class-hdpt-product-data.php',
		'HDPT_Settings'      => 'includes/admin/class-hdpt-settings.php',
		'HDPT_Frontend'      => 'includes/frontend/class-hdpt-frontend.php',
		'HDPT_Widget_Button' => 'includes/elementor/class-hdpt-widget-button.php',
	);

	if ( isset( $map[ $class_name ] ) ) {
		$file = HDPT_PLUGIN_DIR . $map[ $class_name ];
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
spl_autoload_register( 'hdpt_autoload' );

/**
 * Kiểm tra WooCommerce đã active hay chưa.
 *
 * @return bool
 */
function hdpt_is_woocommerce_active() {
	return class_exists( 'WooCommerce', false ) || did_action( 'woocommerce_loaded' );
}

/**
 * Admin notice khi thiếu WooCommerce.
 */
function hdpt_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Plugin "HD Product Traceability" yêu cầu WooCommerce (phiên bản 7.0 trở lên) được cài đặt và kích hoạt. Vui lòng kích hoạt WooCommerce trước.', 'hd-product-traceability' )
	);
}

/**
 * Khởi động plugin sau khi mọi plugin đã nạp (đảm bảo WooCommerce có mặt).
 */
function hdpt_bootstrap() {
	if ( ! hdpt_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'hdpt_missing_woocommerce_notice' );
		return;
	}

	HDPT_Plugin::instance();
}
add_action( 'plugins_loaded', 'hdpt_bootstrap' );

/**
 * Khai báo tương thích WooCommerce HPOS (plugin chỉ dùng product meta, không đụng order).
 */
function hdpt_declare_hpos_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'hdpt_declare_hpos_compatibility' );

/**
 * Activation hook: kiểm tra môi trường tối thiểu.
 *
 * Không dừng kích hoạt khi thiếu WooCommerce (chỉ hiện notice qua bootstrap),
 * nhưng chặn hẳn nếu PHP/WP quá cũ để tránh lỗi fatal.
 */
function hdpt_activate() {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( HDPT_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Plugin "HD Product Traceability" yêu cầu PHP 7.4 trở lên.', 'hd-product-traceability' ),
			'',
			array( 'back_link' => true )
		);
	}

	global $wp_version;
	if ( version_compare( $wp_version, '6.0', '<' ) ) {
		deactivate_plugins( HDPT_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Plugin "HD Product Traceability" yêu cầu WordPress 6.0 trở lên.', 'hd-product-traceability' ),
			'',
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'hdpt_activate' );
