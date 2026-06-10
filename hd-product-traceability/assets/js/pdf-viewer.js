/**
 * HDPT PDF Viewer — viewer tối giản trên nền PDF.js (bundle trong plugin).
 *
 * - Render các trang thành canvas xếp dọc, cuộn liên tục, KHÔNG sidebar.
 * - Toolbar: Zoom out (−) / % zoom / Zoom in (+) / Fit width / "trang x / y".
 * - Chỉ render trang gần viewport (IntersectionObserver) để file dài không treo.
 * - Fit-width mặc định (hợp mobile), hỗ trợ pinch-zoom cơ bản trên cảm ứng.
 *
 * Public API: window.HDPTPdfViewer.create( containerEl, url, pdfjsLib, i18n ) -> Promise
 *
 * @package HD_Product_Traceability
 */
( function () {
	'use strict';

	var MIN_SCALE = 0.4;
	var MAX_SCALE = 4;
	var ZOOM_STEP = 1.2;
	var RENDER_MARGIN = '600px 0px'; // Render trước khi trang lọt vào vùng nhìn.

	/**
	 * Một instance viewer cho 1 file PDF.
	 *
	 * @param {HTMLElement} container Phần tử .hdpt-pdf.
	 * @param {string}      url       URL file PDF.
	 * @param {Object}      pdfjsLib  Thư viện PDF.js đã nạp.
	 * @param {Object}      i18n      Chuỗi giao diện đã dịch (từ PHP).
	 */
	function Viewer( container, url, pdfjsLib, i18n ) {
		this.container = container;
		this.url = url;
		this.pdfjsLib = pdfjsLib;
		this.i18n = i18n || {};

		this.doc = null;
		this.scale = 1;          // Scale CSS hiện tại.
		this.fitWidthScale = 1;  // Scale tương ứng "vừa chiều rộng".
		this.baseViewports = []; // Viewport scale=1 của từng trang.
		this.pageEls = [];       // Wrapper từng trang.
		this.renderTasks = {};   // pageNumber -> RenderTask đang chạy.
		this.rendered = {};      // pageNumber -> scale đã render.
		this.currentPage = 1;
		this.observer = null;
		this.relayoutTimer = null;
	}

	Viewer.prototype.load = function () {
		var self = this;

		return self.pdfjsLib
			.getDocument( { url: self.url } )
			.promise.then( function ( doc ) {
				self.doc = doc;

				// Lấy kích thước từng trang (metadata nhẹ) để dựng placeholder.
				var promises = [];
				for ( var n = 1; n <= doc.numPages; n++ ) {
					promises.push(
						doc.getPage( n ).then( function ( page ) {
							self.baseViewports[ page.pageNumber - 1 ] = page.getViewport( { scale: 1 } );
						} )
					);
				}
				return Promise.all( promises );
			} )
			.then( function () {
				self.buildUi();
				self.computeFitWidth();
				self.scale = self.fitWidthScale; // Fit-width mặc định.
				self.layoutPages();
				self.observePages();
				self.bindEvents();
				self.updateToolbar();
			} );
	};

	/* ------------------------- UI ------------------------- */

	Viewer.prototype.buildUi = function () {
		var d = document;

		this.toolbar = d.createElement( 'div' );
		this.toolbar.className = 'hdpt-pdf__toolbar';

		this.btnZoomOut = this.toolbarButton( '−', this.i18n.zoomOut || 'Zoom out' );
		this.zoomLevel = d.createElement( 'span' );
		this.zoomLevel.className = 'hdpt-pdf__zoom-level';
		this.btnZoomIn = this.toolbarButton( '+', this.i18n.zoomIn || 'Zoom in' );
		this.btnFitWidth = this.toolbarButton( '↔', this.i18n.fitWidth || 'Fit width' );

		this.pageInfo = d.createElement( 'span' );
		this.pageInfo.className = 'hdpt-pdf__page-info';

		this.toolbar.appendChild( this.btnZoomOut );
		this.toolbar.appendChild( this.zoomLevel );
		this.toolbar.appendChild( this.btnZoomIn );
		this.toolbar.appendChild( this.btnFitWidth );
		this.toolbar.appendChild( this.pageInfo );

		this.scroller = d.createElement( 'div' );
		this.scroller.className = 'hdpt-pdf__scroll';

		for ( var n = 1; n <= this.doc.numPages; n++ ) {
			var pageEl = d.createElement( 'div' );
			pageEl.className = 'hdpt-pdf__page';
			pageEl.setAttribute( 'data-page', String( n ) );
			this.scroller.appendChild( pageEl );
			this.pageEls.push( pageEl );
		}

		this.container.insertBefore( this.scroller, this.container.firstChild );
		this.container.insertBefore( this.toolbar, this.scroller );
	};

	Viewer.prototype.toolbarButton = function ( label, title ) {
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.textContent = label;
		btn.title = title;
		btn.setAttribute( 'aria-label', title );
		return btn;
	};

	/* ----------------------- Layout ----------------------- */

	Viewer.prototype.computeFitWidth = function () {
		var available = this.scroller.clientWidth - 20; // Trừ margin trang.
		var firstViewport = this.baseViewports[ 0 ];
		if ( available > 0 && firstViewport ) {
			this.fitWidthScale = Math.min( Math.max( available / firstViewport.width, MIN_SCALE ), MAX_SCALE );
		}
	};

	/**
	 * Cập nhật kích thước placeholder mọi trang theo scale hiện tại
	 * và hủy các canvas đã render ở scale cũ.
	 */
	Viewer.prototype.layoutPages = function () {
		for ( var idx = 0; idx < this.pageEls.length; idx++ ) {
			var viewport = this.baseViewports[ idx ];
			var el = this.pageEls[ idx ];
			el.style.width = Math.floor( viewport.width * this.scale ) + 'px';
			el.style.height = Math.floor( viewport.height * this.scale ) + 'px';
		}
		// Scale đổi -> canvas cũ không còn đúng, đánh dấu render lại khi lọt viewport.
		this.rendered = {};
	};

	/* -------------------- Render trang -------------------- */

	Viewer.prototype.observePages = function () {
		var self = this;

		if ( 'undefined' === typeof IntersectionObserver ) {
			// Trình duyệt quá cũ: render tuần tự toàn bộ (vẫn hoạt động).
			for ( var n = 1; n <= self.doc.numPages; n++ ) {
				self.renderPage( n );
			}
			return;
		}

		self.observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						self.renderPage( parseInt( entry.target.getAttribute( 'data-page' ), 10 ) );
					}
				} );
			},
			{ root: self.scroller, rootMargin: RENDER_MARGIN }
		);

		self.pageEls.forEach( function ( el ) {
			self.observer.observe( el );
		} );
	};

	Viewer.prototype.renderPage = function ( pageNumber ) {
		var self = this;
		var targetScale = self.scale;

		// Đã render đúng scale này rồi -> bỏ qua.
		if ( self.rendered[ pageNumber ] === targetScale ) {
			return;
		}

		// Hủy task đang render dở của trang (nếu có) trước khi render lại.
		if ( self.renderTasks[ pageNumber ] ) {
			self.renderTasks[ pageNumber ].cancel();
			delete self.renderTasks[ pageNumber ];
		}

		self.doc.getPage( pageNumber ).then( function ( page ) {
			if ( self.scale !== targetScale ) {
				return; // Scale đã đổi trong lúc chờ -> sẽ có lượt render khác.
			}

			var pageEl = self.pageEls[ pageNumber - 1 ];
			var outputScale = window.devicePixelRatio || 1;
			var viewport = page.getViewport( { scale: targetScale } );

			var canvas = document.createElement( 'canvas' );
			canvas.width = Math.floor( viewport.width * outputScale );
			canvas.height = Math.floor( viewport.height * outputScale );

			var task = page.render( {
				canvasContext: canvas.getContext( '2d' ),
				viewport: viewport,
				transform: 1 !== outputScale ? [ outputScale, 0, 0, outputScale, 0, 0 ] : null
			} );
			self.renderTasks[ pageNumber ] = task;

			task.promise
				.then( function () {
					delete self.renderTasks[ pageNumber ];
					if ( self.scale !== targetScale ) {
						return;
					}
					pageEl.innerHTML = '';
					pageEl.appendChild( canvas );
					self.rendered[ pageNumber ] = targetScale;
				} )
				.catch( function () {
					// RenderingCancelledException khi đổi zoom nhanh — bỏ qua.
					delete self.renderTasks[ pageNumber ];
				} );
		} );
	};

	/** Render lại các trang đang nằm trong (hoặc gần) vùng nhìn thấy. */
	Viewer.prototype.renderVisible = function () {
		var scrollTop = this.scroller.scrollTop;
		var viewBottom = scrollTop + this.scroller.clientHeight + 600;
		var viewTop = scrollTop - 600;

		for ( var idx = 0; idx < this.pageEls.length; idx++ ) {
			var el = this.pageEls[ idx ];
			var top = el.offsetTop;
			var bottom = top + el.offsetHeight;
			if ( bottom >= viewTop && top <= viewBottom ) {
				this.renderPage( idx + 1 );
			}
		}
	};

	/* ----------------------- Zoom ----------------------- */

	Viewer.prototype.setScale = function ( newScale ) {
		newScale = Math.min( Math.max( newScale, MIN_SCALE ), MAX_SCALE );
		if ( newScale === this.scale ) {
			return;
		}

		// Giữ tỉ lệ vị trí cuộn để không "nhảy" trang khi zoom.
		var ratio = newScale / this.scale;
		var anchor = this.scroller.scrollTop + this.scroller.clientHeight / 2;

		this.scale = newScale;
		this.layoutPages();
		this.scroller.scrollTop = anchor * ratio - this.scroller.clientHeight / 2;

		this.updateToolbar();
		this.scheduleRenderVisible();
	};

	Viewer.prototype.scheduleRenderVisible = function () {
		var self = this;
		if ( self.relayoutTimer ) {
			window.clearTimeout( self.relayoutTimer );
		}
		// Debounce nhẹ để pinch-zoom/click liên tiếp không render thừa.
		self.relayoutTimer = window.setTimeout( function () {
			self.renderVisible();
		}, 150 );
	};

	Viewer.prototype.updateToolbar = function () {
		this.zoomLevel.textContent = Math.round( ( this.scale / this.fitWidthScale ) * 100 ) + '%';
		this.updatePageInfo();
	};

	Viewer.prototype.updatePageInfo = function () {
		var template = this.i18n.pageOf || 'Page %1$s / %2$s';
		this.pageInfo.textContent = template
			.replace( '%1$s', String( this.currentPage ) )
			.replace( '%2$s', String( this.doc.numPages ) );
	};

	/** Xác định trang hiện tại theo vị trí cuộn (điểm giữa khung nhìn). */
	Viewer.prototype.trackCurrentPage = function () {
		var middle = this.scroller.scrollTop + this.scroller.clientHeight / 2;

		for ( var idx = 0; idx < this.pageEls.length; idx++ ) {
			var el = this.pageEls[ idx ];
			if ( middle >= el.offsetTop && middle < el.offsetTop + el.offsetHeight + 10 ) {
				if ( this.currentPage !== idx + 1 ) {
					this.currentPage = idx + 1;
					this.updatePageInfo();
				}
				return;
			}
		}
	};

	/* ----------------------- Events ----------------------- */

	Viewer.prototype.bindEvents = function () {
		var self = this;

		self.btnZoomIn.addEventListener( 'click', function () {
			self.setScale( self.scale * ZOOM_STEP );
		} );
		self.btnZoomOut.addEventListener( 'click', function () {
			self.setScale( self.scale / ZOOM_STEP );
		} );
		self.btnFitWidth.addEventListener( 'click', function () {
			self.computeFitWidth();
			self.setScale( self.fitWidthScale );
			self.updateToolbar();
		} );

		self.scroller.addEventListener(
			'scroll',
			function () {
				self.trackCurrentPage();
			},
			{ passive: true }
		);

		// Pinch-zoom cơ bản trên cảm ứng (2 ngón).
		var pinchStartDistance = 0;
		var pinchStartScale = 1;

		self.scroller.addEventListener(
			'touchstart',
			function ( event ) {
				if ( 2 === event.touches.length ) {
					pinchStartDistance = touchDistance( event.touches );
					pinchStartScale = self.scale;
				}
			},
			{ passive: true }
		);

		self.scroller.addEventListener(
			'touchmove',
			function ( event ) {
				if ( 2 === event.touches.length && pinchStartDistance > 0 ) {
					event.preventDefault(); // Chặn zoom cả trang của trình duyệt.
					var ratio = touchDistance( event.touches ) / pinchStartDistance;
					self.setScale( pinchStartScale * ratio );
				}
			},
			{ passive: false }
		);

		self.scroller.addEventListener(
			'touchend',
			function () {
				pinchStartDistance = 0;
			},
			{ passive: true }
		);

		// Resize (xoay máy) khi đang ở fit-width -> tính lại.
		window.addEventListener( 'resize', function () {
			var wasFit = Math.abs( self.scale - self.fitWidthScale ) < 0.01;
			self.computeFitWidth();
			if ( wasFit ) {
				self.setScale( self.fitWidthScale );
				self.updateToolbar();
			}
		} );
	};

	function touchDistance( touches ) {
		var dx = touches[ 0 ].clientX - touches[ 1 ].clientX;
		var dy = touches[ 0 ].clientY - touches[ 1 ].clientY;
		return Math.sqrt( dx * dx + dy * dy );
	}

	/* ----------------------- Public API ----------------------- */

	window.HDPTPdfViewer = {
		/**
		 * Khởi tạo viewer trong container.
		 *
		 * @param {HTMLElement} container Phần tử .hdpt-pdf.
		 * @param {string}      url       URL file PDF.
		 * @param {Object}      pdfjsLib  Thư viện PDF.js đã nạp.
		 * @param {Object}      i18n      Chuỗi giao diện.
		 * @return {Promise}
		 */
		create: function ( container, url, pdfjsLib, i18n ) {
			return new Viewer( container, url, pdfjsLib, i18n ).load();
		}
	};
} )();
