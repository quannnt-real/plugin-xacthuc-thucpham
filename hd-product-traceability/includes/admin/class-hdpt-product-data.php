<?php
/**
 * Tab "Truy xuất nguồn gốc" trong WooCommerce Product Data + lưu meta.
 *
 * @package HD_Product_Traceability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quản lý dữ liệu truy xuất theo từng sản phẩm (admin).
 */
class HDPT_Product_Data {

	/**
	 * Action của nonce form product data.
	 */
	const NONCE_ACTION = 'hdpt_save_product_data';

	/**
	 * Tên field nonce.
	 */
	const NONCE_NAME = 'hdpt_product_data_nonce';

	/**
	 * Gắn hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Thêm tab "Truy xuất nguồn gốc" vào Product Data tabs.
	 *
	 * @param array $tabs Danh sách tab hiện có.
	 * @return array
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['hdpt_traceability'] = array(
			'label'    => __( 'Truy xuất nguồn gốc', 'hd-product-traceability' ),
			'target'   => 'hdpt_product_data_panel',
			'class'    => array(),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Enqueue assets cho màn hình sửa sản phẩm.
	 *
	 * @param string $hook_suffix Hook suffix của màn hình admin hiện tại.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'hdpt-admin',
			HDPT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			HDPT_VERSION
		);

		wp_enqueue_script(
			'hdpt-admin',
			HDPT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			HDPT_VERSION,
			true
		);

		wp_localize_script(
			'hdpt-admin',
			'hdptAdmin',
			array(
				'mediaTitle'  => __( 'Chọn file PDF truy xuất', 'hd-product-traceability' ),
				'mediaButton' => __( 'Dùng file này', 'hd-product-traceability' ),
				'noFile'      => __( 'Chưa chọn file', 'hd-product-traceability' ),
				'confirmRow'  => __( 'Xóa dòng tài liệu này?', 'hd-product-traceability' ),
			)
		);
	}

	/**
	 * Render nội dung panel trong Product Data.
	 */
	public function render_product_data_panel() {
		global $post;

		$product_id = $post ? absint( $post->ID ) : 0;

		$enabled   = get_post_meta( $product_id, '_hdpt_enabled', true );
		$enabled   = ( '' === $enabled ) ? 'yes' : $enabled; // Mặc định: bật.
		$documents = get_post_meta( $product_id, '_hdpt_documents', true );
		$documents = is_array( $documents ) ? $documents : array();
		$business  = get_post_meta( $product_id, '_hdpt_business', true );
		$business  = wp_parse_args(
			is_array( $business ) ? $business : array(),
			array(
				'name'     => '',
				'tax_code' => '',
				'address'  => '',
				'phone'    => '',
				'email'    => '',
				'website'  => '',
			)
		);
		?>
		<div id="hdpt_product_data_panel" class="panel woocommerce_options_panel hidden">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

			<div class="options_group">
				<p class="form-field">
					<label for="hdpt_enabled"><?php esc_html_e( 'Bật nút truy xuất', 'hd-product-traceability' ); ?></label>
					<input type="checkbox" id="hdpt_enabled" name="hdpt_enabled" value="yes" <?php checked( 'yes', $enabled ); ?> />
					<span class="description"><?php esc_html_e( 'Bật nút truy xuất cho sản phẩm này (nút chỉ hiển thị khi sản phẩm có dữ liệu).', 'hd-product-traceability' ); ?></span>
				</p>
			</div>

			<div class="options_group hdpt-documents-group">
				<h4 class="hdpt-group-title"><?php esc_html_e( 'Tài liệu truy xuất (PDF)', 'hd-product-traceability' ); ?></h4>
				<p class="description hdpt-group-desc"><?php esc_html_e( 'Thêm các file PDF truy xuất nguồn gốc. Kéo thả để sắp xếp thứ tự hiển thị.', 'hd-product-traceability' ); ?></p>

				<ul class="hdpt-doc-rows">
					<?php
					foreach ( $documents as $index => $doc ) {
						$attachment_id = isset( $doc['attachment_id'] ) ? absint( $doc['attachment_id'] ) : 0;
						$title         = isset( $doc['title'] ) ? $doc['title'] : '';
						$filename      = $attachment_id ? wp_basename( get_attached_file( $attachment_id ) ) : '';
						$this->render_document_row( (int) $index, $title, $attachment_id, $filename );
					}
					?>
				</ul>

				<p class="hdpt-doc-actions">
					<button type="button" class="button hdpt-add-doc"><?php esc_html_e( '+ Thêm tài liệu', 'hd-product-traceability' ); ?></button>
				</p>

				<?php // Template dòng mới cho JS (placeholder __INDEX__ được thay bằng chỉ số). ?>
				<script type="text/html" id="tmpl-hdpt-doc-row">
					<?php $this->render_document_row( '__INDEX__', '', 0, '' ); ?>
				</script>
			</div>

			<div class="options_group hdpt-business-group">
				<h4 class="hdpt-group-title"><?php esc_html_e( 'Thông tin doanh nghiệp (ký cam kết/hợp đồng)', 'hd-product-traceability' ); ?></h4>

				<p class="form-field">
					<label for="hdpt_biz_name"><?php esc_html_e( 'Tên doanh nghiệp', 'hd-product-traceability' ); ?> <span class="hdpt-required">*</span></label>
					<input type="text" class="short" id="hdpt_biz_name" name="hdpt_business[name]" value="<?php echo esc_attr( $business['name'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="hdpt_biz_tax_code"><?php esc_html_e( 'Mã số thuế', 'hd-product-traceability' ); ?> <span class="hdpt-required">*</span></label>
					<input type="text" class="short" id="hdpt_biz_tax_code" name="hdpt_business[tax_code]" value="<?php echo esc_attr( $business['tax_code'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="hdpt_biz_address"><?php esc_html_e( 'Địa chỉ đăng ký kinh doanh', 'hd-product-traceability' ); ?> <span class="hdpt-required">*</span></label>
					<textarea class="short" id="hdpt_biz_address" name="hdpt_business[address]" rows="3"><?php echo esc_textarea( $business['address'] ); ?></textarea>
				</p>
				<p class="description hdpt-group-desc"><?php esc_html_e( 'Ba trường trên là bắt buộc — thiếu một trong ba thì mục "Thông tin doanh nghiệp" sẽ không hiển thị ngoài trang sản phẩm.', 'hd-product-traceability' ); ?></p>

				<p class="form-field">
					<label for="hdpt_biz_phone"><?php esc_html_e( 'Số điện thoại', 'hd-product-traceability' ); ?></label>
					<input type="text" class="short" id="hdpt_biz_phone" name="hdpt_business[phone]" value="<?php echo esc_attr( $business['phone'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="hdpt_biz_email"><?php esc_html_e( 'Email', 'hd-product-traceability' ); ?></label>
					<input type="email" class="short" id="hdpt_biz_email" name="hdpt_business[email]" value="<?php echo esc_attr( $business['email'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="hdpt_biz_website"><?php esc_html_e( 'Website', 'hd-product-traceability' ); ?></label>
					<input type="url" class="short" id="hdpt_biz_website" name="hdpt_business[website]" value="<?php echo esc_attr( $business['website'] ); ?>" placeholder="https://" />
				</p>
				<p class="description hdpt-group-desc"><?php esc_html_e( 'Trường tùy chọn bỏ trống sẽ không hiển thị ngoài trang sản phẩm.', 'hd-product-traceability' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render một dòng tài liệu trong repeater.
	 *
	 * @param int|string $index         Chỉ số dòng (hoặc placeholder __INDEX__ cho template JS).
	 * @param string     $title         Tiêu đề tài liệu.
	 * @param int        $attachment_id ID attachment PDF.
	 * @param string     $filename      Tên file hiển thị.
	 */
	private function render_document_row( $index, $title, $attachment_id, $filename ) {
		?>
		<li class="hdpt-doc-row">
			<span class="hdpt-doc-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Kéo để sắp xếp', 'hd-product-traceability' ); ?>"></span>
			<input
				type="text"
				class="hdpt-doc-title"
				name="hdpt_documents[<?php echo esc_attr( (string) $index ); ?>][title]"
				value="<?php echo esc_attr( $title ); ?>"
				placeholder="<?php esc_attr_e( 'Tiêu đề tài liệu (bắt buộc khi có file)', 'hd-product-traceability' ); ?>"
			/>
			<input
				type="hidden"
				class="hdpt-doc-attachment-id"
				name="hdpt_documents[<?php echo esc_attr( (string) $index ); ?>][attachment_id]"
				value="<?php echo esc_attr( (string) $attachment_id ); ?>"
			/>
			<button type="button" class="button hdpt-select-pdf"><?php esc_html_e( 'Chọn file PDF', 'hd-product-traceability' ); ?></button>
			<span class="hdpt-doc-filename<?php echo $filename ? '' : ' hdpt-doc-filename--empty'; ?>">
				<?php echo $filename ? esc_html( $filename ) : esc_html__( 'Chưa chọn file', 'hd-product-traceability' ); ?>
			</span>
			<button type="button" class="button-link-delete hdpt-remove-doc" title="<?php esc_attr_e( 'Xóa dòng', 'hd-product-traceability' ); ?>">&times;</button>
		</li>
		<?php
	}

	/**
	 * Lưu meta khi lưu sản phẩm.
	 *
	 * Chạy trên hook woocommerce_admin_process_product_object (tương thích HPOS,
	 * dùng CRUD của WooCommerce thay vì update_post_meta trực tiếp).
	 *
	 * @param WC_Product $product Đối tượng sản phẩm đang được lưu.
	 */
	public function save_product_data( $product ) {
		// Nonce: form product data có thể được lưu từ nơi khác (quick edit, REST)
		// không chứa field của plugin -> chỉ xử lý khi nonce có mặt và hợp lệ.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Capability: quyền sửa chính sản phẩm này.
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		// 1) Checkbox bật/tắt.
		$enabled = isset( $_POST['hdpt_enabled'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_hdpt_enabled', $enabled );

		// 2) Tài liệu PDF: verify attachment tồn tại + đúng mime, sanitize tiêu đề.
		$documents = array();
		if ( isset( $_POST['hdpt_documents'] ) && is_array( $_POST['hdpt_documents'] ) ) {
			$raw_documents = wp_unslash( $_POST['hdpt_documents'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Từng phần tử được sanitize bên dưới.
			foreach ( $raw_documents as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
				if ( ! $attachment_id ) {
					continue; // Dòng chưa chọn file -> bỏ qua.
				}

				// Verify attachment tồn tại và là PDF.
				if ( 'attachment' !== get_post_type( $attachment_id ) || 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
					continue;
				}

				$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
				if ( '' === $title ) {
					// Tiêu đề bắt buộc khi có file: fallback sang tiêu đề attachment.
					$title = sanitize_text_field( get_the_title( $attachment_id ) );
				}

				$documents[] = array(
					'title'         => $title,
					'attachment_id' => $attachment_id,
				);
			}
		}
		if ( ! empty( $documents ) ) {
			$product->update_meta_data( '_hdpt_documents', $documents );
		} else {
			$product->delete_meta_data( '_hdpt_documents' );
		}

		// 3) Thông tin doanh nghiệp.
		$business = array(
			'name'     => '',
			'tax_code' => '',
			'address'  => '',
			'phone'    => '',
			'email'    => '',
			'website'  => '',
		);
		if ( isset( $_POST['hdpt_business'] ) && is_array( $_POST['hdpt_business'] ) ) {
			$raw_business = wp_unslash( $_POST['hdpt_business'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Từng trường được sanitize bên dưới.

			$business['name']     = isset( $raw_business['name'] ) ? sanitize_text_field( $raw_business['name'] ) : '';
			$business['tax_code'] = isset( $raw_business['tax_code'] ) ? sanitize_text_field( $raw_business['tax_code'] ) : '';
			$business['address']  = isset( $raw_business['address'] ) ? sanitize_textarea_field( $raw_business['address'] ) : '';
			$business['phone']    = isset( $raw_business['phone'] ) ? sanitize_text_field( $raw_business['phone'] ) : '';
			$business['email']    = isset( $raw_business['email'] ) ? sanitize_email( $raw_business['email'] ) : '';
			$business['website']  = isset( $raw_business['website'] ) ? esc_url_raw( $raw_business['website'] ) : '';
		}
		if ( implode( '', $business ) !== '' ) {
			$product->update_meta_data( '_hdpt_business', $business );
		} else {
			$product->delete_meta_data( '_hdpt_business' );
		}
	}
}
