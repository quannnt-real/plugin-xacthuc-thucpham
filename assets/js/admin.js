/**
 * Admin JS: repeater tài liệu PDF (Media Library, sortable) + trang cài đặt.
 *
 * @package HD_Product_Traceability
 */
( function ( $ ) {
	'use strict';

	var i18n = window.hdptAdmin || {};

	/* -----------------------------------------------------------------
	 * 1) Product Data tab: repeater tài liệu PDF.
	 * ----------------------------------------------------------------- */
	var $panel = $( '#hdpt_product_data_panel' );

	if ( $panel.length ) {
		var $rows = $panel.find( '.hdpt-doc-rows' );
		var mediaFrame = null;
		var $currentRow = null;

		// Đánh lại index name="hdpt_documents[i][...]" sau khi thêm/xóa/sắp xếp.
		function reindexRows() {
			$rows.children( '.hdpt-doc-row' ).each( function ( index ) {
				$( this )
					.find( 'input' )
					.each( function () {
						var name = $( this ).attr( 'name' );
						if ( name ) {
							$( this ).attr(
								'name',
								name.replace( /hdpt_documents\[[^\]]*\]/, 'hdpt_documents[' + index + ']' )
							);
						}
					} );
			} );
		}

		// Thêm dòng mới từ template.
		$panel.on( 'click', '.hdpt-add-doc', function ( e ) {
			e.preventDefault();
			var template = $( '#tmpl-hdpt-doc-row' ).html();
			$rows.append( template.replace( /__INDEX__/g, $rows.children().length ) );
			reindexRows();
		} );

		// Xóa dòng.
		$panel.on( 'click', '.hdpt-remove-doc', function ( e ) {
			e.preventDefault();
			if ( window.confirm( i18n.confirmRow || 'Remove this row?' ) ) {
				$( this ).closest( '.hdpt-doc-row' ).remove();
				reindexRows();
			}
		} );

		// Chọn file PDF từ Media Library (lọc application/pdf).
		$panel.on( 'click', '.hdpt-select-pdf', function ( e ) {
			e.preventDefault();
			$currentRow = $( this ).closest( '.hdpt-doc-row' );

			if ( ! mediaFrame ) {
				mediaFrame = wp.media( {
					title: i18n.mediaTitle || 'Select PDF',
					button: { text: i18n.mediaButton || 'Use this file' },
					library: { type: 'application/pdf' },
					multiple: false
				} );

				mediaFrame.on( 'select', function () {
					var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();

					if ( 'application/pdf' !== attachment.mime ) {
						return; // Chỉ nhận PDF.
					}

					$currentRow.find( '.hdpt-doc-attachment-id' ).val( attachment.id );
					$currentRow
						.find( '.hdpt-doc-filename' )
						.removeClass( 'hdpt-doc-filename--empty' )
						.text( attachment.filename );

					// Tiêu đề trống -> điền sẵn tiêu đề attachment.
					var $title = $currentRow.find( '.hdpt-doc-title' );
					if ( ! $title.val() ) {
						$title.val( attachment.title || attachment.filename );
					}
				} );
			}

			mediaFrame.open();
		} );

		// Kéo thả sắp xếp bằng jQuery UI sortable.
		$rows.sortable( {
			handle: '.hdpt-doc-handle',
			axis: 'y',
			placeholder: 'hdpt-doc-row-placeholder',
			update: reindexRows
		} );
	}

	/* -----------------------------------------------------------------
	 * 2) Trang cài đặt: color picker + ẩn/hiện ô Google Font.
	 * ----------------------------------------------------------------- */
	var $settings = $( '.hdpt-settings-page' );

	if ( $settings.length ) {
		if ( $.fn.wpColorPicker ) {
			$settings.find( '.hdpt-color-field' ).wpColorPicker();
		}

		// Chỉ hiện ô nhập tên Google Font khi chọn nguồn font = google.
		var $fontSource = $settings.find( 'select[name="hdpt_settings[font_source]"]' );
		var $googleRow = $settings.find( '.hdpt-google-font-row' );

		function toggleGoogleFont() {
			$googleRow.toggle( 'google' === $fontSource.val() );
		}

		if ( $fontSource.length ) {
			$fontSource.on( 'change', toggleGoogleFont );
			toggleGoogleFont();
		}

		// Xác nhận trước khi khôi phục mặc định.
		$settings.find( '.hdpt-reset-defaults' ).on( 'click', function ( e ) {
			if ( ! window.confirm( i18n.confirmReset || 'Reset all settings to defaults?' ) ) {
				e.preventDefault();
			}
		} );
	}
} )( jQuery );
