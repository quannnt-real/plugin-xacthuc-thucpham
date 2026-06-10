<?php
/**
 * Class chính: khởi tạo và nạp các module của plugin.
 *
 * @package HD_Product_Traceability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lớp khởi tạo plugin (singleton).
 */
final class HDPT_Plugin {

	/**
	 * Instance duy nhất.
	 *
	 * @var HDPT_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Tên option lưu toàn bộ cài đặt giao diện.
	 */
	const OPTION_KEY = 'hdpt_settings';

	/**
	 * Module frontend (dùng chung cho shortcode/hook/Elementor widget).
	 *
	 * @var HDPT_Frontend
	 */
	private $frontend;

	/**
	 * Lấy instance duy nhất.
	 *
	 * @return HDPT_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Khởi tạo: nạp i18n và các module admin/frontend/Elementor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			new HDPT_Product_Data();
			new HDPT_Settings();
		}

		$this->frontend = new HDPT_Frontend();

		// Elementor là tùy chọn: chỉ đăng ký widget khi Elementor đã nạp.
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
	}

	/**
	 * Lấy module frontend.
	 *
	 * @return HDPT_Frontend
	 */
	public function frontend() {
		return $this->frontend;
	}

	/**
	 * Nạp text domain cho i18n.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'hd-product-traceability',
			false,
			dirname( HDPT_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Đăng ký Elementor widget (chỉ chạy khi Elementor active).
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager của Elementor.
	 */
	public function register_elementor_widget( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}
		$widgets_manager->register( new HDPT_Widget_Button() );
	}

	/**
	 * Giá trị mặc định cho toàn bộ cài đặt.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			// 1. Nút truy xuất.
			'btn_text'          => __( 'Truy xuất nguồn gốc', 'hd-product-traceability' ),
			'btn_icon'          => 1,
			'btn_bg'            => '#2e7d32',
			'btn_color'         => '#ffffff',
			'btn_bg_hover'      => '#1b5e20',
			'btn_color_hover'   => '#ffffff',
			'btn_radius'        => 4,
			'btn_padding'       => 'medium', // small | medium | large.
			'btn_width'         => 'auto',   // auto | full.
			'btn_position'      => 'after_add_to_cart', // after_add_to_cart | after_summary | after_tabs | none.

			// 2. Modal.
			'overlay_color'     => '#000000',
			'overlay_opacity'   => 70, // %.
			'modal_bg'          => '#ffffff',
			'modal_title_color' => '#1f2937',
			'modal_radius'      => 8,
			'modal_max_width'   => 860,
			'modal_effect'      => 'fade', // fade | slide.

			// 3. Toggle/Accordion.
			'acc_header_bg'     => '#f3f4f6',
			'acc_header_color'  => '#1f2937',
			'acc_icon_color'    => '#6b7280',
			'acc_content_bg'    => '#ffffff',
			'acc_default'       => 'first_open', // first_open | all_closed.

			// 4. Typography.
			'font_source'       => 'inherit', // inherit | system | google.
			'google_font'       => '',
			'font_size'         => 15,

			// 5. Nhãn hiển thị.
			'label_modal_title' => __( 'Truy xuất nguồn gốc sản phẩm', 'hd-product-traceability' ),
			'label_trace'       => __( 'Thông tin truy xuất', 'hd-product-traceability' ),
			'label_business'    => __( 'Thông tin doanh nghiệp', 'hd-product-traceability' ),
			'label_biz_name'    => __( 'Tên doanh nghiệp', 'hd-product-traceability' ),
			'label_biz_tax'     => __( 'Mã số thuế', 'hd-product-traceability' ),
			'label_biz_address' => __( 'Địa chỉ đăng ký kinh doanh', 'hd-product-traceability' ),
			'label_biz_phone'   => __( 'Điện thoại', 'hd-product-traceability' ),
			'label_biz_email'   => __( 'Email', 'hd-product-traceability' ),
			'label_biz_website' => __( 'Website', 'hd-product-traceability' ),

			// 6. Nâng cao.
			'delete_data'       => 0,
		);
	}

	/**
	 * Lấy cài đặt đã merge với mặc định.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::get_default_settings() );
	}

	/**
	 * Lấy danh sách tài liệu PDF hợp lệ của một sản phẩm.
	 *
	 * Mỗi item: { title, attachment_id }. Chỉ trả về item có attachment
	 * tồn tại và đúng mime application/pdf (verify cả lúc render).
	 *
	 * @param int $product_id ID sản phẩm.
	 * @return array[]
	 */
	public static function get_documents( $product_id ) {
		$raw = get_post_meta( $product_id, '_hdpt_documents', true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$documents = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) || empty( $item['attachment_id'] ) ) {
				continue;
			}
			$attachment_id = absint( $item['attachment_id'] );
			if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
				continue;
			}
			$url = wp_get_attachment_url( $attachment_id );
			if ( ! $url ) {
				continue;
			}
			$documents[] = array(
				'title'         => isset( $item['title'] ) ? (string) $item['title'] : '',
				'attachment_id' => $attachment_id,
				'url'           => $url,
			);
		}

		return $documents;
	}

	/**
	 * Lấy thông tin doanh nghiệp của một sản phẩm.
	 *
	 * Chỉ coi là "có dữ liệu" khi đủ 3 trường bắt buộc: tên DN, MST, địa chỉ ĐKKD.
	 *
	 * @param int $product_id ID sản phẩm.
	 * @return array|null Mảng các trường, hoặc null nếu thiếu trường bắt buộc.
	 */
	public static function get_business_info( $product_id ) {
		$raw = get_post_meta( $product_id, '_hdpt_business', true );
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$business = wp_parse_args(
			$raw,
			array(
				'name'     => '',
				'tax_code' => '',
				'address'  => '',
				'phone'    => '',
				'email'    => '',
				'website'  => '',
			)
		);

		if ( '' === trim( $business['name'] ) || '' === trim( $business['tax_code'] ) || '' === trim( $business['address'] ) ) {
			return null;
		}

		return $business;
	}

	/**
	 * Sản phẩm có hiển thị nút truy xuất hay không.
	 *
	 * Điều kiện: checkbox bật (mặc định bật khi chưa lưu) VÀ có ít nhất
	 * một nhóm dữ liệu (PDF hoặc thông tin doanh nghiệp).
	 *
	 * @param int $product_id ID sản phẩm.
	 * @return bool
	 */
	public static function product_has_traceability( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return false;
		}

		$enabled = get_post_meta( $product_id, '_hdpt_enabled', true );
		if ( 'no' === $enabled ) {
			return false;
		}

		return ( count( self::get_documents( $product_id ) ) > 0 ) || ( null !== self::get_business_info( $product_id ) );
	}
}
