# HD Product Traceability

Plugin WordPress thêm nút **"Truy xuất nguồn gốc"** vào trang sản phẩm WooCommerce. Khách hàng bấm nút sẽ mở modal responsive gồm 2 mục dạng accordion:

- **Thông tin truy xuất** — các tài liệu PDF do admin tải lên theo từng sản phẩm, **xem trực tiếp trong modal** bằng PDF.js (bundle sẵn trong plugin, không tải từ CDN): cuộn liên tục, zoom +/−, fit-width, đếm trang, không sidebar, lazy-load khi mở toggle.
- **Thông tin doanh nghiệp** — tên doanh nghiệp, mã số thuế, địa chỉ đăng ký kinh doanh (bắt buộc) và SĐT, email, website (tùy chọn — bỏ trống thì không hiển thị).

Hoạt động tốt trên site **chế độ catalog/liên hệ báo giá** (sản phẩm không mua được, không có giá) và trang sản phẩm dựng bằng **Elementor template**.

## Yêu cầu

| Thành phần | Phiên bản |
|---|---|
| WordPress | >= 6.0 |
| PHP | >= 7.4 |
| WooCommerce | >= 7.0 (bắt buộc, có khai báo tương thích HPOS) |
| Elementor | Tùy chọn — không bắt buộc |

## Cài đặt

**Cách 1 — Upload zip:** Vào **Plugins → Add New → Upload Plugin**, chọn file `hd-product-traceability-x.y.z.zip`, cài và kích hoạt.

**Cách 2 — Copy thủ công / clone từ git:** Đặt toàn bộ mã nguồn vào thư mục `wp-content/plugins/hd-product-traceability/` (lưu ý: tên thư mục phải là `hd-product-traceability`, nếu clone repo thì đổi tên thư mục), sau đó kích hoạt trong menu Plugins.

> Cần WooCommerce đã kích hoạt trước. Nếu thiếu, plugin sẽ hiện thông báo lỗi và không chạy.

## Hướng dẫn sử dụng

### Bước 1 — Nhập dữ liệu truy xuất cho sản phẩm

Mở sản phẩm cần cấu hình (**Sản phẩm → sửa sản phẩm**), trong khung **Product data** chọn tab **"Truy xuất nguồn gốc"**:

1. **Bật nút truy xuất** — checkbox bật/tắt nút cho riêng sản phẩm này (mặc định bật).
2. **Tài liệu truy xuất (PDF)** — bấm **"+ Thêm tài liệu"**, nhập tiêu đề, bấm **"Chọn file PDF"** để chọn từ Media Library (chỉ nhận file PDF). Thêm bao nhiêu dòng tùy ý, **kéo thả biểu tượng ≡** để sắp xếp thứ tự, bấm **×** để xóa dòng.
   - 1 file → viewer mở trực tiếp trong mục "Thông tin truy xuất".
   - Nhiều file → mỗi file thành một toggle con theo tiêu đề.
3. **Thông tin doanh nghiệp** — 3 trường **bắt buộc**: Tên doanh nghiệp, Mã số thuế, Địa chỉ đăng ký kinh doanh (thiếu 1 trong 3 thì mục này không hiển thị ngoài frontend). 3 trường tùy chọn: SĐT (render dạng `tel:`), Email (`mailto:`), Website (link mở tab mới) — bỏ trống thì không hiển thị dòng đó.

Bấm **Cập nhật** sản phẩm để lưu.

> **Nút chỉ hiển thị khi**: sản phẩm có ít nhất 1 nhóm dữ liệu (PDF hoặc đủ thông tin doanh nghiệp) **và** checkbox đang bật. Không phụ thuộc giá, tồn kho hay trạng thái mua được.

### Bước 2 — Tùy biến giao diện (trang cài đặt)

Vào **Sản phẩm → Truy xuất nguồn gốc** (quyền `manage_woocommerce`):

| Mục | Tùy chọn |
|---|---|
| **1. Nút truy xuất** | Text nút, bật/tắt icon kính lúp, màu nền/chữ + hover, bo góc, padding (nhỏ/vừa/lớn), độ rộng (auto/full), **vị trí tự động chèn** và **priority** |
| **2. Modal** | Màu + độ mờ overlay, màu nền, màu tiêu đề, bo góc, độ rộng tối đa, hiệu ứng mở (fade/slide) |
| **3. Toggle/Accordion** | Màu nền/chữ header, màu icon mũi tên, màu nền nội dung, trạng thái mặc định (toggle đầu mở sẵn / tất cả đóng) |
| **4. Typography** | Font (kế thừa theme / system / Google Font tự enqueue), cỡ chữ cơ bản |
| **5. Nhãn hiển thị** | Đổi mọi nhãn: tiêu đề modal, tên 2 accordion, nhãn từng trường doanh nghiệp |
| **6. Nâng cao** | Cờ "Xóa toàn bộ dữ liệu khi gỡ plugin" |

Mọi thay đổi áp dụng **ngay lập tức** qua CSS variables, không cần xóa cache build. Nút **"Khôi phục mặc định"** đưa toàn bộ cài đặt về ban đầu.

**Vị trí tự động chèn** (đều hoạt động kể cả khi sản phẩm không mua được/không có giá):

- *Trong phần tóm tắt sản phẩm* — hook `woocommerce_single_product_summary`, priority cấu hình được (mặc định **35**; nếu theme chèn nút "Liên hệ báo giá" ở priority 31 thì nút truy xuất nằm ngay phía sau).
- *Sau phần tóm tắt sản phẩm* — hook `woocommerce_after_single_product_summary`.
- *Cuối phần thông tin sản phẩm* — hook `woocommerce_product_meta_end` (sau SKU/danh mục).
- *Không tự động chèn* — chỉ dùng shortcode/widget.

> **Trang dựng bằng Elementor template không gọi hook WooCommerce?** Plugin có cơ chế dự phòng: tự chèn nút vào container ổn định của trang (summary → container Elementor → nội dung chính) bằng JS, đảm bảo nút luôn xuất hiện và không bao giờ nằm trong thẻ `<a>` (không bị link "Liên hệ" nuốt click).

### Bước 3 — Các cách hiển thị nút khác

**Shortcode** — đặt ở bất kỳ đâu (page builder, widget text, template):

```
[hd_traceability_button]
[hd_traceability_button product_id="123" text="Xem truy xuất"]
```

- Không truyền `product_id`: tự nhận sản phẩm hiện tại (chỉ chạy trên trang sản phẩm).
- `text`: ghi đè text nút (mặc định lấy từ cài đặt).

**Elementor widget** — khi Elementor active, tìm widget **"Nút truy xuất nguồn gốc"** trong panel (category General), kéo vào template Single Product. Controls: text nút, căn lề; style lấy từ trang cài đặt plugin. Widget hiển thị preview ngay trong editor.

## Gỡ cài đặt & dữ liệu

Mặc định gỡ plugin **không xóa dữ liệu** (cài đặt + dữ liệu sản phẩm giữ nguyên, cài lại là dùng tiếp). Chỉ khi bật **"Xóa toàn bộ dữ liệu cài đặt khi gỡ plugin"** trong mục Nâng cao thì uninstall mới xóa option `hdpt_settings` và toàn bộ meta `_hdpt_*` của sản phẩm.

## Câu hỏi thường gặp

**Nút không hiển thị?** Kiểm tra: (1) sản phẩm có dữ liệu PDF hoặc đủ 3 trường doanh nghiệp bắt buộc chưa; (2) checkbox "Bật nút truy xuất" của sản phẩm; (3) "Vị trí tự động chèn" trong trang cài đặt không phải "Không tự động chèn"; (4) hard-refresh trình duyệt (Ctrl+Shift+R) sau khi update plugin.

**PDF không xem được trực tiếp?** Viewer tự fallback sang link "Mở file PDF" ở tab mới khi trình duyệt không render được. Kiểm tra file trong Media Library đúng định dạng `application/pdf`.

**Plugin có gọi dịch vụ bên ngoài không?** Mặc định: không (PDF.js bundle sẵn). Duy nhất khi chọn nguồn font "Google Font", trình duyệt khách sẽ tải font từ fonts.googleapis.com (cân nhắc GDPR nếu cần).

**Site bán hàng bình thường có dùng được không?** Có — plugin trung lập với giá/trạng thái mua hàng, hoạt động y hệt trên site bán hàng lẫn site catalog.

## Changelog

### 1.1.1
- Chuyển mã nguồn plugin ra thư mục gốc repo; chuyển hướng dẫn sang README.md.
- Tinh chỉnh CSS nút/modal (thêm `!important` cho các thuộc tính cốt lõi) tăng tương thích với theme can thiệp mạnh.

### 1.1.0
- Tương thích chế độ catalog/liên hệ báo giá: nút hiển thị cả khi sản phẩm không mua được (`woocommerce_is_purchasable` = false) hoặc không có giá; plugin không đọc/phụ thuộc giá ở bất kỳ logic nào.
- Vị trí auto-insert mới chỉ dùng hook luôn chạy: trong phần tóm tắt (priority cấu hình được, mặc định 35), sau phần tóm tắt, cuối phần thông tin sản phẩm; tự migrate cài đặt cũ.
- Fallback JS chèn nút trên theme/Elementor template không gọi hook WooCommerce; chống chèn vào trong thẻ `<a>` (link "Liên hệ" của giá, link product card), click không bị anchor nuốt.
- Header accordion hiển thị dạng tiêu đề (h3/h4 + reset CSS chống style button của theme).
- Fix PDF viewer không khởi tạo; fallback "Mở file PDF" chỉ hiện khi viewer thực sự lỗi.
- Modal luôn được đưa về cuối `<body>`.

### 1.0.0
- Phát hành lần đầu: Product Data tab (repeater PDF + thông tin doanh nghiệp), trang cài đặt đầy đủ, nút auto-insert/shortcode/Elementor widget, modal accordion 2 cấp, PDF.js viewer (zoom, fit-width, lazy-load, page counter, fallback), i18n tiếng Việt.

## Giấy phép

GPL-2.0-or-later. PDF.js © Mozilla Foundation (Apache-2.0), bundle trong `assets/vendor/pdfjs/`.
