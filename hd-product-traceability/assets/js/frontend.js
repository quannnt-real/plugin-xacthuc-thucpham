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
		if ( ! event.target || 'function' !== typeof event.target.closest ) {
			return;
		}

		// 1) Nút mở modal. preventDefault + stopPropagation: nút có thể nằm
		// gần/trong vùng bị theme bọc thẻ <a> (link "Liên hệ" của giá, link
		// product card) hoặc có JS điều hướng riêng — click vào nút truy xuất
		// tuyệt đối không được điều hướng/bubble sang handler của theme.
		var trigger = event.target.closest( '[data-hdpt-modal]' );
		if ( trigger ) {
			var modal = document.getElementById( trigger.getAttribute( 'data-hdpt-modal' ) );
			if ( modal ) {
				event.preventDefault();
				event.stopPropagation();
				openModal( modal );
			}
			return;
		}

		// 2) Đóng modal (nút X hoặc click overlay).
		var closer = event.target.closest( '[data-hdpt-close]' );
		if ( closer ) {
			var openedByCloser = closer.closest( '.hdpt-modal' );
			if ( openedByCloser ) {
				event.preventDefault();
				event.stopPropagation();
				closeModal( openedByCloser );
			}
			return;
		}

		// 3) Toggle accordion.
		var header = event.target.closest( '.hdpt-acc-header' );
		if ( header && header.closest( '.hdpt-modal' ) ) {
			event.preventDefault();
			event.stopPropagation();
			togglePanel( header );
		}
	} );

	/* -----------------------------------------------------------------
	 * Fallback auto-insert: với theme/Elementor template không chạy hook
	 * summary của WooCommerce, PHP in nút (ẩn) ở footer kèm [data-hdpt-fallback];
	 * JS di chuyển nút vào vị trí hợp lý trong layout rồi hiển thị.
	 * ----------------------------------------------------------------- */
	/**
	 * Tìm thẻ <a> tổ tiên NGOÀI CÙNG của một phần tử (nếu có).
	 *
	 * Theme có thể thay HTML giá bằng thẻ <a> (filter woocommerce_get_price_html)
	 * hoặc bọc cả card sản phẩm trong <a>; <a> lồng <a> còn bị trình duyệt
	 * tự tách lại khi parse. Nút truy xuất tuyệt đối không được nằm trong
	 * bất kỳ <a> nào — click sẽ bị anchor nuốt/điều hướng.
	 *
	 * @param {Element} el Phần tử cần kiểm tra.
	 * @return {Element|null} Thẻ <a> ngoài cùng, hoặc null nếu không nằm trong <a>.
	 */
	function outermostAnchor( el ) {
		var found = null;
		var node = el && el.closest ? el.closest( 'a' ) : null;
		while ( node ) {
			found = node;
			node = node.parentElement ? node.parentElement.closest( 'a' ) : null;
		}
		return found;
	}

	/**
	 * Chèn nút theo anchor, tự thoát ra ngoài nếu điểm chèn nằm trong thẻ <a>.
	 *
	 * @param {Element} anchor Phần tử mốc.
	 * @param {string}  mode   'append' (chèn vào cuối) hoặc 'after' (chèn ngay sau).
	 * @param {Element} btn    Nút cần chèn.
	 */
	function insertOutsideAnchors( anchor, mode, btn ) {
		var wrapperA = outermostAnchor( anchor );
		if ( wrapperA ) {
			// Điểm chèn nằm trong link -> chèn ra NGOÀI, ngay sau thẻ <a> ngoài cùng.
			wrapperA.insertAdjacentElement( 'afterend', btn );
			return;
		}
		if ( 'append' === mode ) {
			anchor.appendChild( btn );
		} else {
			anchor.insertAdjacentElement( 'afterend', btn );
		}
	}

	function placeFallbackButtons() {
		var fallbacks = document.querySelectorAll( '[data-hdpt-fallback]' );
		if ( ! fallbacks.length ) {
			return;
		}

		// Chỉ dùng container ỔN ĐỊNH cấp cao, KHÔNG dựa vào phần tử giá
		// (.price/.amount/.woocommerce-Price-amount) — vùng giá có thể đã bị
		// theme thay bằng thẻ <a> "Liên hệ" và DOM quanh nó không còn tin cậy.
		// Thứ tự: summary của single product -> container Elementor single
		// product -> container sản phẩm -> vùng nội dung chính.
		var anchors = [
			{ selector: '.single-product div.product .summary', mode: 'append' },
			{ selector: '[data-elementor-type="product"]', mode: 'append' },
			{ selector: '.elementor-location-single', mode: 'append' },
			{ selector: '.single-product div.product', mode: 'append' },
			{ selector: 'main, #main, .site-main, #content, #primary', mode: 'append' }
		];

		Array.prototype.forEach.call( fallbacks, function ( wrap ) {
			var btn = wrap.firstElementChild;
			if ( ! btn ) {
				if ( wrap.parentNode ) {
					wrap.parentNode.removeChild( wrap );
				}
				return;
			}

			var placed = false;
			for ( var i = 0; i < anchors.length && ! placed; i++ ) {
				// Mỗi bước thử đều được bọc try/catch: một selector lỗi/không khớp
				// không bao giờ được phép dừng cả chuỗi chèn.
				try {
					var anchor = document.querySelector( anchors[ i ].selector );
					if ( ! anchor ) {
						continue;
					}
					insertOutsideAnchors( anchor, anchors[ i ].mode, btn );
					placed = true;
				} catch ( e ) {
					// Bỏ qua, thử anchor kế tiếp.
				}
			}

			if ( ! placed ) {
				// Không tìm được anchor nào: vẫn hiển thị nút tại chỗ
				// (cuối trang) thay vì ẩn mất chức năng.
				wrap.insertAdjacentElement( 'beforebegin', btn );
			}

			// Hậu kiểm: nếu vì lý do nào đó nút vẫn nằm trong <a>, kéo ra ngoài.
			try {
				var trapped = outermostAnchor( btn );
				if ( trapped ) {
					trapped.insertAdjacentElement( 'afterend', btn );
				}
			} catch ( e ) {
				// Không chặn luồng.
			}

			if ( wrap.parentNode ) {
				wrap.parentNode.removeChild( wrap );
			}
		} );
	}

	/**
	 * Đảm bảo mọi modal nằm ở CUỐI <body>: không kẹt trong cây DOM của
	 * summary/card (vùng có thể bị vỡ bởi <a> lồng nhau) và không nằm trong
	 * wrapper bị theme ẩn/đổi vị trí.
	 */
	function relocateModals() {
		var modals = document.querySelectorAll( '.hdpt-modal' );
		Array.prototype.forEach.call( modals, function ( modal ) {
			if ( modal.parentElement !== document.body ) {
				document.body.appendChild( modal );
			}
		} );
	}

	function onReady() {
		try {
			placeFallbackButtons();
		} catch ( e ) {
			// Fallback lỗi không được phép làm chết phần modal/accordion.
		}
		try {
			relocateModals();
		} catch ( e ) {
			// Như trên.
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', onReady );
	} else {
		onReady();
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
