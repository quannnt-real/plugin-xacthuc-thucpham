<?php
/**
 * Frontend: nút truy xuất, shortcode, modal, enqueue assets, CSS variables.
 *
 * @package HD_Product_Traceability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hiển thị nút + modal truy xuất nguồn gốc ngoài frontend.
 */
class HDPT_Frontend {

	/**
	 * Danh sách product ID đã render nút trên trang (để in modal ở footer).
	 *
	 * @var int[]
	 */
	private $rendered_products = array();

	/**
	 * Danh sách product ID đã auto-insert nút (chống render trùng
	 * khi hook chính và hook dự phòng cùng chạy).
	 *
	 * @var int[]
	 */
	private $auto_inserted = array();

	/**
	 * Assets đã được enqueue hay chưa.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Gắn hooks.
	 */
	public function __construct() {
		add_shortcode( 'hd_traceability_button', array( $this, 'shortcode_button' ) );

		add_action( 'wp', array( $this, 'setup_auto_insert' ) );
		// Priority 999: enqueue SAU theme/Elementor/WooCommerce để stylesheet
		// của plugin in ra sau cùng -> thắng các rule cùng specificity.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 999 );
		// Priority 4: fallback phải chạy TRƯỚC render_modals (5) để modal
		// của sản phẩm fallback kịp được ghi nhận và render.
		add_action( 'wp_footer', array( $this, 'render_fallback_button' ), 4 );
		add_action( 'wp_footer', array( $this, 'render_modals' ), 5 );
	}

	/**
	 * Gắn hook auto-insert nút vào trang sản phẩm theo vị trí đã chọn.
	 *
	 * Chỉ dùng các hook LUÔN chạy kể cả khi sản phẩm không mua được
	 * (woocommerce_is_purchasable = false) hoặc không có giá — tuyệt đối
	 * không dùng hook nằm bên trong form add-to-cart (form không render
	 * với sản phẩm không purchasable, hoặc bị theme remove_action).
	 */
	public function setup_auto_insert() {
		if ( is_admin() ) {
			return;
		}

		$settings = HDPT_Plugin::get_settings();
		$position = $settings['btn_position'];

		// Vị trí summary: priority cấu hình được (mặc định 35 — sau nút
		// "Liên hệ báo giá" nếu theme chèn ở priority 31).
		$position_hooks = array(
			'summary'       => array( 'woocommerce_single_product_summary', min( max( absint( $settings['btn_position_priority'] ), 1 ), 100 ) ),
			'after_summary' => array( 'woocommerce_after_single_product_summary', 5 ),
			'meta_end'      => array( 'woocommerce_product_meta_end', 10 ),
		);

		if ( ! isset( $position_hooks[ $position ] ) ) {
			return; // 'none' hoặc giá trị không hợp lệ.
		}

		list( $hook, $priority ) = $position_hooks[ $position ];
		add_action( $hook, array( $this, 'auto_insert_button' ), $priority );
	}

	/**
	 * Callback auto-insert: in nút cho sản phẩm hiện tại (mỗi sản phẩm chỉ 1 lần).
	 */
	public function auto_insert_button() {
		if ( ! is_product() ) {
			return;
		}

		$product_id = absint( get_the_ID() );
		if ( in_array( $product_id, $this->auto_inserted, true ) ) {
			return;
		}

		$html = $this->get_button_html( $product_id );
		if ( '' === $html ) {
			return;
		}

		$this->auto_inserted[] = $product_id;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML đã được escape từng phần khi build.
	}

	/**
	 * Shortcode [hd_traceability_button product_id="" text=""].
	 *
	 * @param array $atts Thuộc tính shortcode.
	 * @return string
	 */
	public function shortcode_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'text'       => '',
			),
			$atts,
			'hd_traceability_button'
		);

		$product_id = absint( $atts['product_id'] );

		// Không truyền product_id -> tự nhận diện sản phẩm hiện tại.
		if ( ! $product_id ) {
			if ( ! is_singular( 'product' ) ) {
				return ''; // Ngoài trang sản phẩm mà không có product_id -> không render.
			}
			$product_id = get_the_ID();
		}

		return $this->get_button_html( $product_id, sanitize_text_field( $atts['text'] ) );
	}

	/**
	 * Build HTML nút truy xuất (đồng thời ghi nhận để render modal + enqueue assets).
	 *
	 * @param int    $product_id ID sản phẩm.
	 * @param string $text       Text nút tùy chọn (rỗng -> lấy từ settings).
	 * @return string
	 */
	public function get_button_html( $product_id, $text = '' ) {
		$product_id = absint( $product_id );

		if ( ! HDPT_Plugin::product_has_traceability( $product_id ) ) {
			return '';
		}

		$settings = HDPT_Plugin::get_settings();
		$text     = ( '' !== $text ) ? $text : $settings['btn_text'];

		if ( ! in_array( $product_id, $this->rendered_products, true ) ) {
			$this->rendered_products[] = $product_id;
		}
		$this->ensure_assets();

		$icon = '';
		if ( ! empty( $settings['btn_icon'] ) ) {
			// Icon kính lúp SVG inline (trang trí, ẩn với screen reader).
			$icon = '<svg class="hdpt-btn__icon" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
		}

		return sprintf(
			'<div class="hdpt-btn-wrap"><button type="button" class="hdpt-btn hdpt-btn--%1$s hdpt-btn--pad-%2$s" data-hdpt-modal="hdpt-modal-%3$d" aria-haspopup="dialog">%4$s<span class="hdpt-btn__text">%5$s</span></button></div>',
			esc_attr( $settings['btn_width'] ),
			esc_attr( $settings['btn_padding'] ),
			$product_id,
			$icon,
			esc_html( $text )
		);
	}

	/**
	 * Enqueue assets trên các trang chắc chắn có nút
	 * (single product có auto-insert, hoặc nội dung chứa shortcode).
	 */
	public function maybe_enqueue_assets() {
		$settings = HDPT_Plugin::get_settings();

		$needs = false;

		if ( is_product() && 'none' !== $settings['btn_position'] && HDPT_Plugin::product_has_traceability( get_the_ID() ) ) {
			$needs = true;
		}

		if ( ! $needs && is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( (string) $post->post_content, 'hd_traceability_button' ) ) {
				$needs = true;
			}
		}

		if ( $needs ) {
			$this->ensure_assets();
		}
	}

	/**
	 * Enqueue CSS/JS frontend + CSS variables (idempotent — gọi được cả lúc render).
	 */
	public function ensure_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->assets_enqueued = true;

		$settings = HDPT_Plugin::get_settings();

		// Google Font (tùy chọn).
		if ( 'google' === $settings['font_source'] && '' !== $settings['google_font'] ) {
			wp_enqueue_style(
				'hdpt-google-font',
				'https://fonts.googleapis.com/css2?family=' . rawurlencode( $settings['google_font'] ) . ':wght@400;600;700&display=swap',
				array(),
				HDPT_VERSION
			);
		}

		wp_enqueue_style( 'hdpt-frontend', HDPT_PLUGIN_URL . 'assets/css/frontend.css', array(), HDPT_VERSION );
		wp_add_inline_style( 'hdpt-frontend', $this->build_css_variables( $settings ) );

		wp_enqueue_script( 'hdpt-frontend', HDPT_PLUGIN_URL . 'assets/js/frontend.js', array(), HDPT_VERSION, true );
		wp_enqueue_script( 'hdpt-pdf-viewer', HDPT_PLUGIN_URL . 'assets/js/pdf-viewer.js', array(), HDPT_VERSION, true );

		wp_localize_script(
			'hdpt-frontend',
			'hdptFrontend',
			array(
				// PDF.js bundle trong plugin — lazy-load khi mở toggle lần đầu.
				'pdfjsSrc'   => HDPT_PLUGIN_URL . 'assets/vendor/pdfjs/pdf.min.js',
				'workerSrc'  => HDPT_PLUGIN_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
				'accDefault' => $settings['acc_default'],
				'effect'     => $settings['modal_effect'],
				'i18n'       => array(
					'loading'  => __( 'Đang tải tài liệu…', 'hd-product-traceability' ),
					'error'    => __( 'Không thể hiển thị tài liệu trực tiếp.', 'hd-product-traceability' ),
					'openPdf'  => __( 'Mở file PDF', 'hd-product-traceability' ),
					'zoomIn'   => __( 'Phóng to', 'hd-product-traceability' ),
					'zoomOut'  => __( 'Thu nhỏ', 'hd-product-traceability' ),
					'fitWidth' => __( 'Vừa chiều rộng', 'hd-product-traceability' ),
					/* translators: %1$s: trang hiện tại, %2$s: tổng số trang. */
					'pageOf'   => __( 'Trang %1$s / %2$s', 'hd-product-traceability' ),
					'close'    => __( 'Đóng', 'hd-product-traceability' ),
				),
			)
		);
	}

	/**
	 * Build block CSS variables từ settings.
	 *
	 * @param array $settings Cài đặt đã merge mặc định.
	 * @return string CSS.
	 */
	private function build_css_variables( $settings ) {
		// Overlay: hex + opacity -> rgba.
		$overlay_rgba = $this->hex_to_rgba( $settings['overlay_color'], absint( $settings['overlay_opacity'] ) / 100 );

		// Font-family theo nguồn.
		switch ( $settings['font_source'] ) {
			case 'system':
				$font_family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
				break;
			case 'google':
				$font_family = '' !== $settings['google_font']
					? '"' . str_replace( array( '"', "'" ), '', $settings['google_font'] ) . '", sans-serif'
					: 'inherit';
				break;
			default:
				$font_family = 'inherit';
		}

		$padding_map = array(
			'small'  => '8px 16px',
			'medium' => '12px 24px',
			'large'  => '16px 32px',
		);
		$btn_padding = isset( $padding_map[ $settings['btn_padding'] ] ) ? $padding_map[ $settings['btn_padding'] ] : $padding_map['medium'];

		$vars = array(
			'--hdpt-btn-bg'            => sanitize_hex_color( $settings['btn_bg'] ),
			'--hdpt-btn-color'         => sanitize_hex_color( $settings['btn_color'] ),
			'--hdpt-btn-bg-hover'      => sanitize_hex_color( $settings['btn_bg_hover'] ),
			'--hdpt-btn-color-hover'   => sanitize_hex_color( $settings['btn_color_hover'] ),
			'--hdpt-btn-radius'        => absint( $settings['btn_radius'] ) . 'px',
			'--hdpt-btn-padding'       => $btn_padding,
			'--hdpt-overlay'           => $overlay_rgba,
			'--hdpt-modal-bg'          => sanitize_hex_color( $settings['modal_bg'] ),
			'--hdpt-modal-title-color' => sanitize_hex_color( $settings['modal_title_color'] ),
			'--hdpt-modal-radius'      => absint( $settings['modal_radius'] ) . 'px',
			'--hdpt-modal-max-width'   => absint( $settings['modal_max_width'] ) . 'px',
			'--hdpt-acc-header-bg'     => sanitize_hex_color( $settings['acc_header_bg'] ),
			'--hdpt-acc-header-color'  => sanitize_hex_color( $settings['acc_header_color'] ),
			'--hdpt-acc-icon-color'    => sanitize_hex_color( $settings['acc_icon_color'] ),
			'--hdpt-acc-content-bg'    => sanitize_hex_color( $settings['acc_content_bg'] ),
			'--hdpt-font-family'       => $font_family,
			'--hdpt-font-size'         => absint( $settings['font_size'] ) . 'px',
		);

		$css = ':root{';
		foreach ( $vars as $name => $value ) {
			if ( '' !== (string) $value && null !== $value ) {
				$css .= $name . ':' . $value . ';';
			}
		}
		$css .= '}';

		return $css;
	}

	/**
	 * Chuyển hex (#rrggbb / #rgb) + alpha thành chuỗi rgba().
	 *
	 * @param string $hex   Màu hex.
	 * @param float  $alpha Độ mờ 0–1.
	 * @return string
	 */
	private function hex_to_rgba( $hex, $alpha ) {
		$hex = sanitize_hex_color( $hex );
		if ( ! $hex ) {
			$hex = '#000000';
		}
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, (string) max( 0, min( 1, $alpha ) ) );
	}

	/**
	 * Fallback auto-insert ở wp_footer cho theme/template KHÔNG chạy các hook
	 * summary chuẩn của WooCommerce (vd: trang sản phẩm dựng bằng Elementor
	 * template — woocommerce_single_product_summary không bao giờ fire, hoặc
	 * site đã remove_action toàn bộ khu vực add-to-cart).
	 *
	 * Nếu đến wp_footer mà nút vẫn chưa được render (qua hook/shortcode/widget),
	 * in nút trong wrapper ẩn; JS frontend sẽ di chuyển nút vào vị trí hợp lý
	 * trong layout (sau khối add-to-cart/giá/summary) rồi hiển thị.
	 */
	public function render_fallback_button() {
		if ( ! is_product() ) {
			return;
		}

		$settings = HDPT_Plugin::get_settings();
		if ( 'none' === $settings['btn_position'] ) {
			return;
		}

		$product_id = absint( get_queried_object_id() );

		// Nút đã hiển thị trên trang (hook/shortcode/widget) -> không cần fallback.
		if ( in_array( $product_id, $this->rendered_products, true ) ) {
			return;
		}

		$html = $this->get_button_html( $product_id );
		if ( '' === $html ) {
			return;
		}

		echo '<div class="hdpt-btn-fallback" data-hdpt-fallback hidden>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML đã được escape từng phần khi build.
	}

	/**
	 * Render modal cho từng sản phẩm đã có nút trên trang (wp_footer).
	 */
	public function render_modals() {
		if ( empty( $this->rendered_products ) ) {
			return;
		}

		$settings = HDPT_Plugin::get_settings();

		foreach ( $this->rendered_products as $product_id ) {
			$this->render_modal( $product_id, $settings );
		}
	}

	/**
	 * Render markup modal của một sản phẩm.
	 *
	 * @param int   $product_id ID sản phẩm.
	 * @param array $settings   Cài đặt.
	 */
	private function render_modal( $product_id, $settings ) {
		$documents = HDPT_Plugin::get_documents( $product_id );
		$business  = HDPT_Plugin::get_business_info( $product_id );

		if ( empty( $documents ) && null === $business ) {
			return;
		}

		$modal_id = 'hdpt-modal-' . $product_id;
		$title_id = $modal_id . '-title';
		?>
		<div id="<?php echo esc_attr( $modal_id ); ?>" class="hdpt-modal hdpt-modal--<?php echo esc_attr( $settings['modal_effect'] ); ?>" aria-hidden="true">
			<div class="hdpt-modal__overlay" data-hdpt-close tabindex="-1"></div>
			<div class="hdpt-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
				<button type="button" class="hdpt-modal__close" data-hdpt-close aria-label="<?php esc_attr_e( 'Đóng', 'hd-product-traceability' ); ?>">&times;</button>
				<h2 class="hdpt-modal__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $settings['label_modal_title'] ); ?></h2>

				<div class="hdpt-modal__body">
					<div class="hdpt-accordion">

						<?php if ( ! empty( $documents ) ) : ?>
							<section class="hdpt-acc-item">
								<h3 class="hdpt-acc-heading">
									<button type="button" class="hdpt-acc-header" aria-expanded="false" aria-controls="<?php echo esc_attr( $modal_id ); ?>-trace">
										<span><?php echo esc_html( $settings['label_trace'] ); ?></span>
										<?php $this->arrow_icon(); ?>
									</button>
								</h3>
								<div class="hdpt-acc-content" id="<?php echo esc_attr( $modal_id ); ?>-trace" hidden>
									<?php if ( 1 === count( $documents ) ) : ?>
										<?php $this->render_pdf_container( $documents[0] ); ?>
									<?php else : ?>
										<div class="hdpt-accordion hdpt-accordion--sub">
											<?php foreach ( $documents as $doc_index => $doc ) : ?>
												<section class="hdpt-acc-item">
													<h4 class="hdpt-acc-heading">
														<button type="button" class="hdpt-acc-header" aria-expanded="false" aria-controls="<?php echo esc_attr( $modal_id . '-doc-' . $doc_index ); ?>">
															<span><?php echo esc_html( $doc['title'] ); ?></span>
															<?php $this->arrow_icon(); ?>
														</button>
													</h4>
													<div class="hdpt-acc-content" id="<?php echo esc_attr( $modal_id . '-doc-' . $doc_index ); ?>" hidden>
														<?php $this->render_pdf_container( $doc ); ?>
													</div>
												</section>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							</section>
						<?php endif; ?>

						<?php if ( null !== $business ) : ?>
							<section class="hdpt-acc-item">
								<h3 class="hdpt-acc-heading">
									<button type="button" class="hdpt-acc-header" aria-expanded="false" aria-controls="<?php echo esc_attr( $modal_id ); ?>-business">
										<span><?php echo esc_html( $settings['label_business'] ); ?></span>
										<?php $this->arrow_icon(); ?>
									</button>
								</h3>
								<div class="hdpt-acc-content" id="<?php echo esc_attr( $modal_id ); ?>-business" hidden>
									<dl class="hdpt-business">
										<dt><?php echo esc_html( $settings['label_biz_name'] ); ?></dt>
										<dd><?php echo esc_html( $business['name'] ); ?></dd>

										<dt><?php echo esc_html( $settings['label_biz_tax'] ); ?></dt>
										<dd><?php echo esc_html( $business['tax_code'] ); ?></dd>

										<dt><?php echo esc_html( $settings['label_biz_address'] ); ?></dt>
										<dd><?php echo wp_kses_post( nl2br( esc_html( $business['address'] ) ) ); ?></dd>

										<?php if ( '' !== $business['phone'] ) : ?>
											<dt><?php echo esc_html( $settings['label_biz_phone'] ); ?></dt>
											<dd><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $business['phone'] ) ); ?>"><?php echo esc_html( $business['phone'] ); ?></a></dd>
										<?php endif; ?>

										<?php if ( '' !== $business['email'] && is_email( $business['email'] ) ) : ?>
											<dt><?php echo esc_html( $settings['label_biz_email'] ); ?></dt>
											<dd><a href="mailto:<?php echo esc_attr( antispambot( $business['email'] ) ); ?>"><?php echo esc_html( antispambot( $business['email'] ) ); ?></a></dd>
										<?php endif; ?>

										<?php if ( '' !== $business['website'] ) : ?>
											<dt><?php echo esc_html( $settings['label_biz_website'] ); ?></dt>
											<dd><a href="<?php echo esc_url( $business['website'] ); ?>" target="_blank" rel="nofollow noopener"><?php echo esc_html( $business['website'] ); ?></a></dd>
										<?php endif; ?>
									</dl>
								</div>
							</section>
						<?php endif; ?>

					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render container PDF viewer (PDF.js khởi tạo lazy khi mở toggle lần đầu).
	 *
	 * @param array $doc Tài liệu { title, attachment_id, url } (đã verify mime PDF).
	 */
	private function render_pdf_container( $doc ) {
		?>
		<div class="hdpt-pdf" data-hdpt-pdf="<?php echo esc_url( $doc['url'] ); ?>">
			<?php // Fallback luôn có sẵn trong markup: JS ẩn đi khi viewer chạy được. ?>
			<p class="hdpt-pdf__fallback">
				<a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Mở file PDF', 'hd-product-traceability' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Icon mũi tên accordion (SVG inline).
	 */
	private function arrow_icon() {
		?>
		<svg class="hdpt-acc-arrow" aria-hidden="true" focusable="false" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
		<?php
	}
}
