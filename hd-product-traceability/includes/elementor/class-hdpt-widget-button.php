<?php
/**
 * Elementor widget "Nút truy xuất nguồn gốc" (Elementor v3 API, 3.x–4.x).
 *
 * Chỉ được nạp khi Elementor active (đăng ký qua elementor/widgets/register).
 *
 * @package HD_Product_Traceability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget nút truy xuất nguồn gốc.
 */
class HDPT_Widget_Button extends \Elementor\Widget_Base {

	/**
	 * Tên widget (unique, có prefix).
	 *
	 * @return string
	 */
	public function get_name() {
		return 'hdpt_traceability_button';
	}

	/**
	 * Tiêu đề widget trong panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Nút truy xuất nguồn gốc', 'hd-product-traceability' );
	}

	/**
	 * Icon widget.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-search';
	}

	/**
	 * Category trong panel.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Từ khóa tìm kiếm trong panel.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'truy xuất', 'nguồn gốc', 'traceability', 'pdf', 'woocommerce' );
	}

	/**
	 * Dùng DOM tối ưu (single wrapper).
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Đăng ký controls.
	 */
	protected function register_controls() {
		$settings = HDPT_Plugin::get_settings();

		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Nút truy xuất', 'hd-product-traceability' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'btn_text',
			array(
				'label'       => esc_html__( 'Text nút', 'hd-product-traceability' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => $settings['btn_text'],
				'placeholder' => $settings['btn_text'],
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label'     => esc_html__( 'Căn lề', 'hd-product-traceability' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Trái', 'hd-product-traceability' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Giữa', 'hd-product-traceability' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Phải', 'hd-product-traceability' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}} .hdpt-btn-wrap' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'style_note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Màu sắc, bo góc, padding, icon… của nút được lấy từ trang cài đặt plugin: Sản phẩm → Truy xuất nguồn gốc.', 'hd-product-traceability' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render frontend (PHP).
	 */
	protected function render() {
		$widget_settings = $this->get_settings_for_display();
		$text            = isset( $widget_settings['btn_text'] ) ? sanitize_text_field( $widget_settings['btn_text'] ) : '';

		$frontend = HDPT_Plugin::instance()->frontend();
		if ( ! $frontend ) {
			return;
		}

		$product_id = $this->get_current_product_id();
		$html       = $product_id ? $frontend->get_button_html( $product_id, $text ) : '';

		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML đã được escape từng phần khi build.
			return;
		}

		// Trong editor preview: luôn hiện nút mẫu (kèm CSS) để kéo thả/căn chỉnh được,
		// kể cả khi preview không phải sản phẩm có dữ liệu truy xuất.
		if ( $this->is_editor_mode() ) {
			$frontend->ensure_assets();
			$plugin_settings = HDPT_Plugin::get_settings();
			$preview_text    = ( '' !== $text ) ? $text : $plugin_settings['btn_text'];
			printf(
				'<div class="hdpt-btn-wrap"><button type="button" class="hdpt-btn hdpt-btn--%1$s hdpt-btn--pad-%2$s">%3$s<span class="hdpt-btn__text">%4$s</span></button></div>',
				esc_attr( $plugin_settings['btn_width'] ),
				esc_attr( $plugin_settings['btn_padding'] ),
				! empty( $plugin_settings['btn_icon'] ) ? $this->icon_svg() : '',
				esc_html( $preview_text )
			);
		}
	}

	/**
	 * JS template cho editor preview (đồng bộ logic với render()).
	 */
	protected function content_template() {
		$plugin_settings = HDPT_Plugin::get_settings();
		?>
		<#
		var btnText = settings.btn_text || '<?php echo esc_js( $plugin_settings['btn_text'] ); ?>';
		#>
		<div class="hdpt-btn-wrap">
			<button type="button" class="hdpt-btn hdpt-btn--<?php echo esc_attr( $plugin_settings['btn_width'] ); ?> hdpt-btn--pad-<?php echo esc_attr( $plugin_settings['btn_padding'] ); ?>">
				<?php
				if ( ! empty( $plugin_settings['btn_icon'] ) ) {
					echo $this->icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG tĩnh do plugin định nghĩa.
				}
				?>
				<span class="hdpt-btn__text">{{ btnText }}</span>
			</button>
		</div>
		<?php
	}

	/**
	 * Xác định product ID hiện tại (single product hoặc preview template).
	 *
	 * @return int
	 */
	private function get_current_product_id() {
		global $product;

		if ( $product instanceof WC_Product ) {
			return $product->get_id();
		}

		$post_id = get_the_ID();
		if ( $post_id && 'product' === get_post_type( $post_id ) ) {
			return $post_id;
		}

		return 0;
	}

	/**
	 * Đang ở chế độ editor/preview của Elementor?
	 *
	 * @return bool
	 */
	private function is_editor_mode() {
		return \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();
	}

	/**
	 * SVG icon kính lúp (giống nút frontend).
	 *
	 * @return string
	 */
	private function icon_svg() {
		return '<svg class="hdpt-btn__icon" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
	}
}
