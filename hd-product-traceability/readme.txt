=== HD Product Traceability ===
Contributors: linxhq
Tags: woocommerce, traceability, truy xuat nguon goc, pdf, product
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thêm nút "Truy xuất nguồn gốc" vào trang sản phẩm WooCommerce: xem tài liệu PDF truy xuất trực tiếp và thông tin doanh nghiệp trong modal.

== Description ==

HD Product Traceability bổ sung nút "Truy xuất nguồn gốc" cho trang sản phẩm WooCommerce. Khi khách hàng bấm nút, một modal responsive mở ra với 2 mục dạng accordion:

* **Thông tin truy xuất** — danh sách tài liệu PDF do admin tải lên theo từng sản phẩm, xem trực tiếp trong modal bằng PDF.js (bundle sẵn trong plugin, không tải từ CDN): cuộn liên tục, zoom +/−, fit-width, đếm trang, không sidebar, lazy-load khi mở toggle.
* **Thông tin doanh nghiệp** — tên doanh nghiệp, mã số thuế, địa chỉ đăng ký kinh doanh (bắt buộc) và SĐT, email, website (tùy chọn — bỏ trống thì không hiển thị).

= Ba cách hiển thị nút =

1. Tự động chèn vào trang sản phẩm (chọn vị trí trong cài đặt: sau nút thêm vào giỏ / sau tóm tắt / sau tabs mô tả).
2. Shortcode: `[hd_traceability_button product_id="" text=""]`
3. Elementor widget "Nút truy xuất nguồn gốc" (chỉ hiện khi Elementor active — Elementor là tùy chọn, không bắt buộc).

= Tùy biến giao diện =

Trang cài đặt nằm trong menu **Sản phẩm → Truy xuất nguồn gốc**: màu sắc nút/modal/accordion, bo góc, padding, font chữ (kế thừa theme / system / Google Font), cỡ chữ, toàn bộ nhãn hiển thị, hiệu ứng mở modal, vị trí auto-chèn. Mọi thay đổi áp dụng ngay qua CSS variables, không cần build.

= Yêu cầu =

* WordPress 6.0+, PHP 7.4+
* WooCommerce 7.0+ (bắt buộc, có khai báo tương thích HPOS)
* Elementor (tùy chọn)

== Installation ==

1. Tải thư mục `hd-product-traceability` lên `/wp-content/plugins/` (hoặc cài qua file zip).
2. Kích hoạt plugin trong menu Plugins. Cần WooCommerce đã active.
3. Vào **Sản phẩm → Truy xuất nguồn gốc** để tùy biến giao diện.
4. Mở một sản phẩm, vào tab **Truy xuất nguồn gốc** trong khung Product data để thêm file PDF và thông tin doanh nghiệp.

== Frequently Asked Questions ==

= Nút không hiển thị trên trang sản phẩm? =

Nút chỉ hiển thị khi sản phẩm có ít nhất một nhóm dữ liệu (tài liệu PDF hoặc đủ 3 trường thông tin doanh nghiệp bắt buộc) VÀ checkbox "Bật nút truy xuất" đang bật trong tab Truy xuất nguồn gốc của sản phẩm. Kiểm tra thêm mục "Vị trí tự động chèn" trong trang cài đặt.

= Gỡ plugin có mất dữ liệu không? =

Mặc định không. Plugin chỉ xóa dữ liệu khi bạn bật "Xóa toàn bộ dữ liệu cài đặt khi gỡ plugin" trong mục Nâng cao của trang cài đặt.

= PDF không xem được trực tiếp? =

Viewer tự fallback sang link "Mở file PDF" ở tab mới khi trình duyệt không render được.

= Plugin có gọi dịch vụ bên ngoài không? =

Mặc định: không. PDF.js được bundle sẵn trong plugin, không tải từ CDN. Duy nhất khi bạn chủ động chọn nguồn font "Google Font" trong cài đặt Typography, trình duyệt của khách sẽ tải font từ fonts.googleapis.com (cân nhắc về quyền riêng tư/GDPR nếu cần).

== Changelog ==

= 1.0.0 =
* Phát hành lần đầu: Product Data tab (repeater PDF + thông tin doanh nghiệp), trang cài đặt đầy đủ, nút auto-insert/shortcode/Elementor widget, modal accordion 2 cấp, PDF.js viewer (zoom, fit-width, lazy-load, page counter, fallback), i18n tiếng Việt.

== Upgrade Notice ==

= 1.0.0 =
Phiên bản đầu tiên.
