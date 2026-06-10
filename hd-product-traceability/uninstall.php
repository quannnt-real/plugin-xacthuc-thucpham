<?php
/**
 * Dọn dẹp khi gỡ plugin.
 *
 * Chỉ xóa dữ liệu khi admin đã bật cờ "Xóa toàn bộ dữ liệu cài đặt khi gỡ plugin"
 * trong trang cài đặt; mặc định giữ nguyên toàn bộ dữ liệu.
 *
 * @package HD_Product_Traceability
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$hdpt_settings = get_option( 'hdpt_settings', array() );

if ( empty( $hdpt_settings['delete_data'] ) ) {
	return; // Admin không bật cờ xóa -> giữ nguyên dữ liệu.
}

// Xóa option cài đặt.
delete_option( 'hdpt_settings' );

// Xóa toàn bộ product meta của plugin (dùng API, không SQL trực tiếp).
delete_post_meta_by_key( '_hdpt_enabled' );
delete_post_meta_by_key( '_hdpt_documents' );
delete_post_meta_by_key( '_hdpt_business' );
