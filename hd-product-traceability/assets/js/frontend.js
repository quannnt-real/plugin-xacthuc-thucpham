/**
 * Frontend JS: modal (mở/đóng, ESC, focus trap, khóa scroll),
 * accordion 2 cấp, lazy-load PDF.js khi mở toggle chứa viewer lần đầu.
 *
 * Vanilla JS, không phụ thuộc jQuery.
 *
 * @package HD_Product_Traceability
 */
( function () {
	'use strict';

	var config = window.hdptFrontend || {};
	var i18n = config.i18n || {};

	var pdfjsPromise = null; // Promise nạp thư viện PDF.js (nạp đúng 1 lần).
	var lastFocused = null;

	/* -----------------------------------------------------------------
	 * Lazy-load thư viện PDF.js (bundle trong plugin, không CDN).
	 * ----------------------------------------------------------------- */
	function loadPdfJs() {
		if ( pdfjsPromise ) {
			return pdfjsPromise;
		}

		pdfjsPromise = new Promise( function ( resolve, reject ) {
			if ( window.pdfjsLib ) {
				resolve( window.pdfjsLib );
				return;
			}

			var script = document.createElement( 'script' );
			script.src = config.pdfjsSrc;
			script.async = true;
			script.onload = function () {
				if ( window.pdfjsLib ) {
					window.pdfjsLib.GlobalWorkerOptions.workerSrc = config.workerSrc;
					resolve( window.pdfjsLib );
				} else {
					reject( new Error( 'pdfjsLib unavailable' ) );
				}
			};
			script.onerror = function () {
				reject( new Error( 'Failed to load PDF.js' ) );
			};
			document.head.appendChild( script );
		} );

		return pdfjsPromise;
	}

	/* -----------------------------------------------------------------
	 * PDF viewer: khởi tạo lazy khi panel chứa viewer được mở lần đầu.
	 * ----------------------------------------------------------------- */
	function initViewersIn( container ) {
		var viewers = container.querySelectorAll( '.hdpt-pdf:not([data-hdpt-init])' );

		Array.prototype.forEach.call( viewers, function ( el ) {
			el.setAttribute( 'data-hdpt-init', '1' );

			var url = el.getAttribute( 'data-hdpt-pdf' );
			if ( ! url || ! window.HDPTPdfViewer || 'function' !== typeof window.HDPTPdfViewer.create ) {
				return;
			}

			// Spinner trong lúc tải thư viện + tài liệu.
			var status = document.createElement( 'div' );
			status.className = 'hdpt-pdf__status';
			var spinner = document.createElement( 'span' );
			spinner.className = 'hdpt-pdf__spinner';
			spinner.setAttribute( 'aria-hidden', 'true' );
			var loadingText = document.createElement( 'span' );
			loadingText.textContent = i18n.loading || 'Loading…';
			status.appendChild( spinner );
			status.appendChild( loadingText );
			el.insertBefore( status, el.firstChild );

			loadPdfJs()
				.then( function ( pdfjsLib ) {
					return window.HDPTPdfViewer.create( el, url, pdfjsLib, i18n );
				} )
				.then( function () {
					el.classList.add( 'hdpt-pdf--active' );
					if ( status.parentNode ) {
						status.parentNode.removeChild( status );
					}
				} )
				.catch( function () {
					// Fallback: hiện thông báo + hiện lại link "Mở file PDF" trong markup.
					el.classList.add( 'hdpt-pdf--error' );
					status.innerHTML = '';
					var message = document.createElement( 'span' );
					message.textContent = i18n.error || 'Cannot display this document.';
					status.appendChild( message );
				} );
		} );
	}

	/* -----------------------------------------------------------------
	 * Accordion (2 cấp — mỗi .hdpt-acc-header điều khiển panel kế tiếp).
	 * ----------------------------------------------------------------- */
	function togglePanel( header, expand ) {
		var panelId = header.getAttribute( 'aria-controls' );
		var panel = panelId ? document.getElementById( panelId ) : null;
		if ( ! panel ) {
			return;
		}

		var willExpand = 'boolean' === typeof expand ? expand : 'true' !== header.getAttribute( 'aria-expanded' );

		header.setAttribute( 'aria-expanded', willExpand ? 'true' : 'false' );
		panel.hidden = ! willExpand;

		if ( willExpand ) {
			initViewersIn( panel );
		}
	}

	/* -----------------------------------------------------------------
	 * Modal.
	 * ----------------------------------------------------------------- */
	function getFocusable( modal ) {
		return modal.querySelectorAll(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
	}

	function openModal( modal ) {
		lastFocused = document.activeElement;

		modal.classList.add( 'hdpt-modal--open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'hdpt-modal-locked' );

		// Trạng thái accordion mặc định (chỉ áp dụng lần mở đầu tiên).
		if ( ! modal.hasAttribute( 'data-hdpt-ready' ) ) {
			modal.setAttribute( 'data-hdpt-ready', '1' );
			if ( 'first_open' === config.accDefault ) {
				var first = modal.querySelector( '.hdpt-modal__body > .hdpt-accordion > .hdpt-acc-item > .hdpt-acc-header' );
				if ( first ) {
					togglePanel( first, true );
				}
			}
		}

		var closeBtn = modal.querySelector( '.hdpt-modal__close' );
		if ( closeBtn ) {
			closeBtn.focus();
		}
	}

	function closeModal( modal ) {
		modal.classList.remove( 'hdpt-modal--open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'hdpt-modal-locked' );

		if ( lastFocused && 'function' === typeof lastFocused.focus ) {
			lastFocused.focus();
		}
	}

	function getOpenModal() {
		return document.querySelector( '.hdpt-modal.hdpt-modal--open' );
	}

	/* -----------------------------------------------------------------
	 * Gắn sự kiện (event delegation).
	 * ----------------------------------------------------------------- */
	document.addEventListener( 'click', function ( event ) {
		// 1) Nút mở modal.
		var trigger = event.target.closest( '[data-hdpt-modal]' );
		if ( trigger ) {
			var modal = document.getElementById( trigger.getAttribute( 'data-hdpt-modal' ) );
			if ( modal ) {
				event.preventDefault();
				openModal( modal );
			}
			return;
		}

		// 2) Đóng modal (nút X hoặc click overlay).
		var closer = event.target.closest( '[data-hdpt-close]' );
		if ( closer ) {
			var openedByCloser = closer.closest( '.hdpt-modal' );
			if ( openedByCloser ) {
				closeModal( openedByCloser );
			}
			return;
		}

		// 3) Toggle accordion.
		var header = event.target.closest( '.hdpt-acc-header' );
		if ( header && header.closest( '.hdpt-modal' ) ) {
			togglePanel( header );
		}
	} );

	/* -----------------------------------------------------------------
	 * Fallback auto-insert: với theme/Elementor template không chạy hook
	 * summary của WooCommerce, PHP in nút (ẩn) ở footer kèm [data-hdpt-fallback];
	 * JS di chuyển nút vào vị trí hợp lý trong layout rồi hiển thị.
	 * ----------------------------------------------------------------- */
	function placeFallbackButtons() {
		var fallbacks = document.querySelectorAll( '[data-hdpt-fallback]' );
		if ( ! fallbacks.length ) {
			return;
		}

		// Thứ tự ưu tiên anchor:
		// (1) phần summary của product (append -> nằm SAU mọi nội dung trong
		//     summary, kể cả nút "Liên hệ báo giá" theme chèn ở priority 31);
		// (2) các widget Elementor của khối mua hàng/giá (chèn ngay sau);
		// (3) container Elementor single product template;
		// (4) container sản phẩm / cuối nội dung chính.
		var anchors = [
			{ selector: '.single-product div.product .summary', mode: 'append' },
			{ selector: '.single-product div.product a[href^="tel:"]', mode: 'after' },
			{ selector: '.elementor-widget-woocommerce-product-add-to-cart', mode: 'after' },
			{ selector: '.elementor-widget-woocommerce-product-price', mode: 'after' },
			{ selector: '[data-elementor-type="product"]', mode: 'append' },
			{ selector: '.single-product div.product', mode: 'append' },
			{ selector: 'main, #main, .site-main, #content', mode: 'append' }
		];

		Array.prototype.forEach.call( fallbacks, function ( wrap ) {
			var btn = wrap.firstElementChild;
			if ( ! btn ) {
				wrap.remove();
				return;
			}

			var placed = false;
			for ( var i = 0; i < anchors.length && ! placed; i++ ) {
				var anchor = document.querySelector( anchors[ i ].selector );
				if ( ! anchor ) {
					continue;
				}
				if ( 'append' === anchors[ i ].mode ) {
					anchor.appendChild( btn );
				} else {
					anchor.insertAdjacentElement( 'afterend', btn );
				}
				placed = true;
			}

			if ( ! placed ) {
				// Không tìm được anchor nào: vẫn hiển thị nút tại chỗ
				// (cuối trang) thay vì ẩn mất chức năng.
				wrap.insertAdjacentElement( 'beforebegin', btn );
			}

			wrap.remove();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', placeFallbackButtons );
	} else {
		placeFallbackButtons();
	}

	document.addEventListener( 'keydown', function ( event ) {
		var modal = getOpenModal();
		if ( ! modal ) {
			return;
		}

		// ESC để đóng.
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			closeModal( modal );
			return;
		}

		// Focus trap cơ bản trong modal.
		if ( 'Tab' === event.key ) {
			var focusable = getFocusable( modal );
			if ( ! focusable.length ) {
				return;
			}
			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}
	} );
} )();
