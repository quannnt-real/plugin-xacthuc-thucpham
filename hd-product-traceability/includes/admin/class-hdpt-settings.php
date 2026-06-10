<?php
/**
 * Trang cài đặt plugin (Settings API) — submenu dưới menu Sản phẩm.
 *
 * @package HD_Product_Traceability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trang cài đặt tùy biến giao diện, lưu vào 1 option duy nhất hdpt_settings.
 */
class HDPT_Settings {

	/**
	 * Option group dùng cho Settings API.
	 */
	const OPTION_GROUP = 'hdpt_settings_group';

	/**
	 * Slug trang cài đặt.
	 */
	const PAGE_SLUG = 'hdpt-settings';

	/**
	 * Gắn hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Settings API lưu qua options.php (mặc định đòi manage_options)
		// -> map capability của option group này sang manage_woocommerce.
		add_filter( 'option_page_capability_' . self::OPTION_GROUP, array( $this, 'option_page_capability' ) );
	}

	/**
	 * Capability cho phép lưu option group này.
	 *
	 * @return string
	 */
	public function option_page_capability() {
		return 'manage_woocommerce';
	}

	/**
	 * Thêm submenu "Truy xuất nguồn gốc" dưới menu Sản phẩm.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Cài đặt Truy xuất nguồn gốc', 'hd-product-traceability' ),
			__( 'Truy xuất nguồn gốc', 'hd-product-traceability' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue assets cho trang cài đặt.
	 *
	 * @param string $hook_suffix Hook suffix màn hình hiện tại.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'product_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'hdpt-admin', HDPT_PLUGIN_URL . 'assets/css/admin.css', array(), HDPT_VERSION );
		wp_enqueue_script(
			'hdpt-admin',
			HDPT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			HDPT_VERSION,
			true
		);
		wp_localize_script(
			'hdpt-admin',
			'hdptAdmin',
			array(
				'confirmReset' => __( 'Khôi phục toàn bộ cài đặt về mặc định?', 'hd-product-traceability' ),
			)
		);
	}

	/**
	 * Đăng ký setting + sections + fields.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			HDPT_Plugin::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		$sections = array(
			'hdpt_section_button'     => __( '1. Nút truy xuất', 'hd-product-traceability' ),
			'hdpt_section_modal'      => __( '2. Modal', 'hd-product-traceability' ),
			'hdpt_section_accordion'  => __( '3. Toggle / Accordion', 'hd-product-traceability' ),
			'hdpt_section_typography' => __( '4. Typography', 'hd-product-traceability' ),
			'hdpt_section_labels'     => __( '5. Nhãn hiển thị', 'hd-product-traceability' ),
			'hdpt_section_advanced'   => __( '6. Nâng cao', 'hd-product-traceability' ),
		);
		foreach ( $sections as $id => $title ) {
			add_settings_section( $id, $title, '__return_false', self::PAGE_SLUG );
		}

		// ----- 1. Nút truy xuất -----
		$this->add_field( 'btn_text', __( 'Text nút', 'hd-product-traceability' ), 'hdpt_section_button', 'text' );
		$this->add_field( 'btn_icon', __( 'Hiện icon (kính lúp)', 'hd-product-traceability' ), 'hdpt_section_button', 'checkbox' );
		$this->add_field( 'btn_bg', __( 'Màu nền nút', 'hd-product-traceability' ), 'hdpt_section_button', 'color' );
		$this->add_field( 'btn_color', __( 'Màu chữ nút', 'hd-product-traceability' ), 'hdpt_section_button', 'color' );
		$this->add_field( 'btn_bg_hover', __( 'Màu nền hover', 'hd-product-traceability' ), 'hdpt_section_button', 'color' );
		$this->add_field( 'btn_color_hover', __( 'Màu chữ hover', 'hd-product-traceability' ), 'hdpt_section_button', 'color' );
		$this->add_field( 'btn_radius', __( 'Bo góc nút (px)', 'hd-product-traceability' ), 'hdpt_section_button', 'number', array( 'min' => 0, 'max' => 60 ) );
		$this->add_field(
			'btn_padding',
			__( 'Padding nút', 'hd-product-traceability' ),
			'hdpt_section_button',
			'select',
			array(
				'options' => array(
					'small'  => __( 'Nhỏ', 'hd-product-traceability' ),
					'medium' => __( 'Vừa', 'hd-product-traceability' ),
					'large'  => __( 'Lớn', 'hd-product-traceability' ),
				),
			)
		);
		$this->add_field(
			'btn_width',
			__( 'Độ rộng nút', 'hd-product-traceability' ),
			'hdpt_section_button',
			'select',
			array(
				'options' => array(
					'auto' => __( 'Tự động', 'hd-product-traceability' ),
					'full' => __( 'Toàn bộ chiều ngang', 'hd-product-traceability' ),
				),
			)
		);
		$this->add_field(
			'btn_position',
			__( 'Vị trí tự động chèn', 'hd-product-traceability' ),
			'hdpt_section_button',
			'select',
			array(
				'options'     => array(
					'after_add_to_cart' => __( 'Sau nút thêm vào giỏ', 'hd-product-traceability' ),
					'after_summary'     => __( 'Sau phần tóm tắt sản phẩm', 'hd-product-traceability' ),
					'after_tabs'        => __( 'Sau tabs mô tả', 'hd-product-traceability' ),
					'none'              => __( 'Không tự động chèn', 'hd-product-traceability' ),
				),
				'description' => __( 'Vị trí nút trên trang sản phẩm. Chọn "Không tự động chèn" nếu chỉ dùng shortcode/widget.', 'hd-product-traceability' ),
			)
		);

		// ----- 2. Modal -----
		$this->add_field( 'overlay_color', __( 'Màu overlay', 'hd-product-traceability' ), 'hdpt_section_modal', 'color' );
		$this->add_field( 'overlay_opacity', __( 'Độ mờ overlay (%)', 'hd-product-traceability' ), 'hdpt_section_modal', 'number', array( 'min' => 0, 'max' => 100 ) );
		$this->add_field( 'modal_bg', __( 'Màu nền modal', 'hd-product-traceability' ), 'hdpt_section_modal', 'color' );
		$this->add_field( 'modal_title_color', __( 'Màu tiêu đề modal', 'hd-product-traceability' ), 'hdpt_section_modal', 'color' );
		$this->add_field( 'modal_radius', __( 'Bo góc modal (px)', 'hd-product-traceability' ), 'hdpt_section_modal', 'number', array( 'min' => 0, 'max' => 60 ) );
		$this->add_field( 'modal_max_width', __( 'Độ rộng tối đa (px)', 'hd-product-traceability' ), 'hdpt_section_modal', 'number', array( 'min' => 320, 'max' => 1600 ) );
		$this->add_field(
			'modal_effect',
			__( 'Hiệu ứng mở', 'hd-product-traceability' ),
			'hdpt_section_modal',
			'select',
			array(
				'options' => array(
					'fade'  => __( 'Fade (mờ dần)', 'hd-product-traceability' ),
					'slide' => __( 'Slide (trượt lên)', 'hd-product-traceability' ),
				),
			)
		);

		// ----- 3. Accordion -----
		$this->add_field( 'acc_header_bg', __( 'Màu nền header toggle', 'hd-product-traceability' ), 'hdpt_section_accordion', 'color' );
		$this->add_field( 'acc_header_color', __( 'Màu chữ header', 'hd-product-traceability' ), 'hdpt_section_accordion', 'color' );
		$this->add_field( 'acc_icon_color', __( 'Màu icon mũi tên', 'hd-product-traceability' ), 'hdpt_section_accordion', 'color' );
		$this->add_field( 'acc_content_bg', __( 'Màu nền nội dung', 'hd-product-traceability' ), 'hdpt_section_accordion', 'color' );
		$this->add_field(
			'acc_default',
			__( 'Trạng thái mặc định', 'hd-product-traceability' ),
			'hdpt_section_accordion',
			'select',
			array(
				'options' => array(
					'first_open' => __( 'Toggle đầu tiên mở sẵn', 'hd-product-traceability' ),
					'all_closed' => __( 'Tất cả đóng', 'hd-product-traceability' ),
				),
			)
		);

		// ----- 4. Typography -----
		$this->add_field(
			'font_source',
			__( 'Font chữ', 'hd-product-traceability' ),
			'hdpt_section_typography',
			'select',
			array(
				'options' => array(
					'inherit' => __( 'Kế thừa theme', 'hd-product-traceability' ),
					'system'  => __( 'System font', 'hd-product-traceability' ),
					'google'  => __( 'Google Font', 'hd-product-traceability' ),
				),
			)
		);
		$this->add_field(
			'google_font',
			__( 'Tên Google Font', 'hd-product-traceability' ),
			'hdpt_section_typography',
			'text',
			array(
				'row_class'   => 'hdpt-google-font-row',
				'description' => __( 'Ví dụ: Be Vietnam Pro. Plugin sẽ tự enqueue font từ Google Fonts.', 'hd-product-traceability' ),
			)
		);
		$this->add_field( 'font_size', __( 'Cỡ chữ cơ bản (px)', 'hd-product-traceability' ), 'hdpt_section_typography', 'number', array( 'min' => 10, 'max' => 28 ) );

		// ----- 5. Nhãn hiển thị -----
		$labels = array(
			'label_modal_title' => __( 'Tiêu đề modal', 'hd-product-traceability' ),
			'label_trace'       => __( 'Nhãn "Thông tin truy xuất"', 'hd-product-traceability' ),
			'label_business'    => __( 'Nhãn "Thông tin doanh nghiệp"', 'hd-product-traceability' ),
			'label_biz_name'    => __( 'Nhãn: Tên doanh nghiệp', 'hd-product-traceability' ),
			'label_biz_tax'     => __( 'Nhãn: Mã số thuế', 'hd-product-traceability' ),
			'label_biz_address' => __( 'Nhãn: Địa chỉ đăng ký kinh doanh', 'hd-product-traceability' ),
			'label_biz_phone'   => __( 'Nhãn: Điện thoại', 'hd-product-traceability' ),
			'label_biz_email'   => __( 'Nhãn: Email', 'hd-product-traceability' ),
			'label_biz_website' => __( 'Nhãn: Website', 'hd-product-traceability' ),
		);
		foreach ( $labels as $key => $label ) {
			$this->add_field( $key, $label, 'hdpt_section_labels', 'text' );
		}

		// ----- 6. Nâng cao -----
		$this->add_field(
			'delete_data',
			__( 'Xóa dữ liệu khi gỡ plugin', 'hd-product-traceability' ),
			'hdpt_section_advanced',
			'checkbox',
			array(
				'description' => __( 'Xóa toàn bộ dữ liệu cài đặt (option + meta sản phẩm) khi gỡ plugin. Mặc định giữ nguyên.', 'hd-product-traceability' ),
			)
		);
	}

	/**
	 * Helper đăng ký 1 field.
	 *
	 * @param string $key     Khóa trong mảng settings.
	 * @param string $label   Nhãn field.
	 * @param string $section ID section.
	 * @param string $type    Loại field: text|number|color|select|checkbox.
	 * @param array  $args    Tham số thêm (options, min, max, description, row_class).
	 */
	private function add_field( $key, $label, $section, $type, $args = array() ) {
		add_settings_field(
			'hdpt_field_' . $key,
			$label,
			array( $this, 'render_field' ),
			self::PAGE_SLUG,
			$section,
			array_merge(
				$args,
				array(
					'key'       => $key,
					'type'      => $type,
					'label_for' => 'hdpt-field-' . $key,
					'class'     => isset( $args['row_class'] ) ? $args['row_class'] : '',
				)
			)
		);
	}

	/**
	 * Render 1 field theo loại.
	 *
	 * @param array $args Tham số field từ add_settings_field.
	 */
	public function render_field( $args ) {
		$settings = HDPT_Plugin::get_settings();
		$key      = $args['key'];
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$id       = 'hdpt-field-' . $key;
		$name     = HDPT_Plugin::OPTION_KEY . '[' . $key . ']';

		switch ( $args['type'] ) {
			case 'color':
				printf(
					'<input type="text" class="hdpt-color-field" id="%s" name="%s" value="%s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'number':
				printf(
					'<input type="number" class="small-text" id="%s" name="%s" value="%s" min="%s" max="%s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( isset( $args['min'] ) ? $args['min'] : 0 ),
					esc_attr( isset( $args['max'] ) ? $args['max'] : 9999 )
				);
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $option_value ),
						selected( $value, $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Bật', 'hd-product-traceability' )
				);
				break;

			case 'text':
			default:
				printf(
					'<input type="text" class="regular-text" id="%s" name="%s" value="%s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;
		}

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Sanitize toàn bộ mảng settings.
	 *
	 * @param array $input Dữ liệu thô từ form.
	 * @return array Dữ liệu sạch.
	 */
	public function sanitize_settings( $input ) {
		$defaults = HDPT_Plugin::get_default_settings();

		// Nút "Khôi phục mặc định" -> trả về mảng mặc định.
		// Nonce + capability đã được options.php xác thực trước khi gọi callback này.
		if ( isset( $_POST['hdpt_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php đã verify nonce của option group.
			add_settings_error(
				HDPT_Plugin::OPTION_KEY,
				'hdpt_reset_done',
				__( 'Đã khôi phục toàn bộ cài đặt về mặc định.', 'hd-product-traceability' ),
				'updated'
			);
			return $defaults;
		}

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$clean = array();

		// Màu hex.
		$color_keys = array( 'btn_bg', 'btn_color', 'btn_bg_hover', 'btn_color_hover', 'overlay_color', 'modal_bg', 'modal_title_color', 'acc_header_bg', 'acc_header_color', 'acc_icon_color', 'acc_content_bg' );
		foreach ( $color_keys as $key ) {
			$color         = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$clean[ $key ] = $color ? $color : $defaults[ $key ];
		}

		// Số nguyên (kèm giới hạn).
		$number_keys = array(
			'btn_radius'      => array( 0, 60 ),
			'overlay_opacity' => array( 0, 100 ),
			'modal_radius'    => array( 0, 60 ),
			'modal_max_width' => array( 320, 1600 ),
			'font_size'       => array( 10, 28 ),
		);
		foreach ( $number_keys as $key => $range ) {
			$number        = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $defaults[ $key ];
			$clean[ $key ] = min( max( $number, $range[0] ), $range[1] );
		}

		// Select (whitelist giá trị hợp lệ).
		$select_keys = array(
			'btn_padding'  => array( 'small', 'medium', 'large' ),
			'btn_width'    => array( 'auto', 'full' ),
			'btn_position' => array( 'after_add_to_cart', 'after_summary', 'after_tabs', 'none' ),
			'modal_effect' => array( 'fade', 'slide' ),
			'acc_default'  => array( 'first_open', 'all_closed' ),
			'font_source'  => array( 'inherit', 'system', 'google' ),
		);
		foreach ( $select_keys as $key => $allowed ) {
			$value         = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : '';
			$clean[ $key ] = in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
		}

		// Text (nhãn + text nút + tên Google Font). Nhãn rỗng -> dùng mặc định.
		$text_keys = array( 'btn_text', 'google_font', 'label_modal_title', 'label_trace', 'label_business', 'label_biz_name', 'label_biz_tax', 'label_biz_address', 'label_biz_phone', 'label_biz_email', 'label_biz_website' );
		foreach ( $text_keys as $key ) {
			$text          = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
			$clean[ $key ] = ( '' === $text && 'google_font' !== $key ) ? $defaults[ $key ] : $text;
		}

		// Checkbox.
		$clean['btn_icon']    = empty( $input['btn_icon'] ) ? 0 : 1;
		$clean['delete_data'] = empty( $input['delete_data'] ) ? 0 : 1;

		return $clean;
	}

	/**
	 * Render trang cài đặt.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'hd-product-traceability' ) );
		}
		?>
		<div class="wrap hdpt-settings-page">
			<h1><?php esc_html_e( 'Cài đặt Truy xuất nguồn gốc', 'hd-product-traceability' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Tùy biến giao diện nút truy xuất, modal, accordion và nhãn hiển thị. Dữ liệu truy xuất của từng sản phẩm nhập trong tab "Truy xuất nguồn gốc" khi sửa sản phẩm.', 'hd-product-traceability' ); ?></p>

			<?php settings_errors( HDPT_Plugin::OPTION_KEY ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				?>
				<p class="hdpt-submit-row">
					<?php submit_button( __( 'Lưu cài đặt', 'hd-product-traceability' ), 'primary', 'submit', false ); ?>
					<?php submit_button( __( 'Khôi phục mặc định', 'hd-product-traceability' ), 'secondary hdpt-reset-defaults', 'hdpt_reset', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}
}
