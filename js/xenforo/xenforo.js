/**
 * Create the XenForo namespace
 * @package XenForo
 */
var XenForo = {};

if (jQuery === undefined)
{
	// global vars to prevent tinyMCE alert (editor doesn't appear though)
	jQuery = $ = {};
}

/**
 * Deal with Firebug not being present
 */
if (!window.console || !console.firebug)
{
	!function(w) { var fn, i = 0;
		if (!w.console) w.console = {};
		fn = ['assert', 'clear', 'count', 'debug', 'dir', 'dirxml', 'error', 'getFirebugElement', 'group', 'groupCollapsed', 'groupEnd', 'info', 'log', 'notifyFirebug', 'profile', 'profileEnd', 'time', 'timeEnd', 'trace', 'warn'];
		for (i = 0; i < fn.length; ++i) if (!w.console[fn[i]]) w.console[fn[i]] = function() {};
	}(window);
}

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Fix IE abbr handling
	 */
	document.createElement('abbr');

	/**
	 * Detect mobile webkit
	 */
	if (/webkit.*mobile/i.test(navigator.userAgent))
	{
		XenForo._isWebkitMobile = true;

		/**
		 * Temporary bugfix for http://dev.jquery.com/ticket/6446
		 */
		if ('getBoundingClientRect' in document.documentElement && /; CPU.*OS (?:3_2|4_0)/i.test(navigator.userAgent))
		{
			// TODO: remove this when 6446 reports fixed
			$.fn.__offset = $.fn.offset;
			$.fn.offset = function ()
			{
				var result = this.__offset();
				result.top -= window.scrollY;
				result.left -= window.scrollX;
				return result;
			};
		}
	}

	// preserve original jQuery Tools .overlay()
	jQuery.fn._jQueryToolsOverlay = jQuery.fn.overlay;

	/**
	 * Extends jQuery core
	 */
	jQuery.extend(true,
	{
		/**
		 * Sets the context of 'this' within a called function.
		 * Takes identical parameters to $.proxy, but does not
		 * enforce the one-elment-one-method merging that $.proxy
		 * does, allowing multiple objects of the same type to
		 * bind to a single element's events (for example).
		 *
		 * @param function|object Function to be called | Context for 'this', method is a property of fn
		 * @param function|string Context for 'this' | Name of method within fn to be called
		 *
		 * @return function
		 */
		context: function(fn, context)
		{
			if (typeof context == 'string')
			{
				var _context = fn;
				fn = fn[context];
				context = _context;
			}

			return function() { return fn.apply(context, arguments); };
		},

		/**
		 * Sets a cookie.
		 *
		 * @param string cookie name (escaped)
		 * @param mixed cookie value
		 * @param string cookie expiry date
		 *
		 * @return mixed cookie value
		 */
		setCookie: function(name, value, expires)
		{
			console.log('Set cookie %s=%s', name, value);

			document.cookie = XenForo._cookieConfig.prefix + name + '=' + encodeURIComponent(value)
				+ (expires === undefined ? '' : ';expires=' + expires.toGMTString())
				+ (XenForo._cookieConfig.path  ? ';path=' + XenForo._cookieConfig.path : '')
				+ (XenForo._cookieConfig.domain ? ';domain=' + XenForo._cookieConfig.domain : '');

			return value;
		},

		/**
		 * Fetches the value of a named cookie.
		 *
		 * @param string Cookie name (escaped)
		 *
		 * @return string Cookie value
		 */
		getCookie: function(name)
		{
			var expr, cookie;

			expr = new RegExp('(^| )' + XenForo._cookieConfig.prefix + name + '=([^;]+)(;|$)');
			cookie = expr.exec(document.cookie);

			if (cookie)
			{
				return decodeURIComponent(cookie[2]);
			}
			else
			{
				return null;
			}
		},

		/**
		 * Deletes a cookie.
		 *
		 * @param string Cookie name (escaped)
		 *
		 * @return null
		 */
		deleteCookie: function(name)
		{
			console.info('Delete cookie %s', name);

			document.cookie = XenForo._cookieConfig.prefix + name + '='
				+ (XenForo._cookieConfig.path  ? '; path=' + XenForo._cookieConfig.path : '')
				+ (XenForo._cookieConfig.domain ? '; domain=' + XenForo._cookieConfig.domain : '')
				+ '; expires=Thu, 01-Jan-70 00:00:01 GMT';

			return null;
		}
	});

	/**
	 * Extends jQuery functions
	 */
	jQuery.fn.extend(
	{
		/**
		 * Wrapper for XenForo.activate, for 'this' element
		 *
		 * @return jQuery
		 */
		xfActivate: function()
		{
			return XenForo.activate(this);
		},

		/**
		 * Like .val() but also trims trailing whitespace
		 */
		strval: function()
		{
			return String(this.val()).replace(/\s+$/g, '');
		},

		/**
		 * Get the 'name' attribute of an element, or if it exists, the value of 'data-fieldName'
		 *
		 * @return string
		 */
		fieldName: function()
		{
			return this.data('fieldName') || this.attr('name');
		},

		/**
		 * Get the value that would be submitted with 'this' element's name on form submit
		 *
		 * @return string
		 */
		fieldValue: function()
		{
			switch (this.attr('type'))
			{
				case 'checkbox':
				{
					return $('input:checkbox[name="' + this.fieldName() + '"]:checked', this.context.form).val();
				}

				case 'radio':
				{
					return $('input:radio[name="' + this.fieldName() + '"]:checked', this.context.form).val();
				}

				default:
				{
					return this.val();
				}
			}
		},

		_jqSerialize : $.fn.serialize,

		/**
		 * Overriden jQuery serialize method to ensure that RTE areas are serialized properly.
		 */
		serialize: function()
		{
			if (window.tinyMCE)
			{
				try { window.tinyMCE.triggerSave(); } catch (e) {}
			}

			return this._jqSerialize();
		},

		_jqSerializeArray : $.fn.serializeArray,

		/**
		 * Overriden jQuery serializeArray method to ensure that RTE areas are serialized properly.
		 */
		serializeArray: function()
		{
			if (window.tinyMCE)
			{
				try { window.tinyMCE.triggerSave(); } catch (e) {}
			}

			return this._jqSerializeArray();
		},

		/**
		 * Returns the position and size of an element, including hidden elements.
		 *
		 * If the element is hidden, it will very quickly un-hides a display:none item,
		 * gets its offset and size, restore the element to its hidden state and returns values.
		 *
		 * @param string inner/outer/{none} Defines the jQuery size function to use
		 * @param string offset/position/{none} Defines the jQuery position function to use (default: offset)
		 *
		 * @return object Offset { left: float, top: float }
		 */
		coords: function(sizeFn, offsetFn)
		{
			var coords,
				visibility,
				display,
				widthFn,
				heightFn,
				hidden = this.is(':hidden');

			if (hidden)
			{
				visibility = this.css('visibility'),
				display = this.css('display');

				this.css(
				{
					visibility: 'hidden',
					display: 'block'
				});
			}

			switch (sizeFn)
			{
				case 'inner':
				{
					widthFn = 'innerWidth';
					heightFn = 'innerHeight';
					break;
				}
				case 'outer':
				{
					widthFn = 'outerWidth';
					heightFn = 'outerHeight';
					break;
				}
				default:
				{
					widthFn = 'width';
					heightFn = 'height';
				}
			}

			switch (offsetFn)
			{
				case 'position':
				{
					offsetFn = 'position';
					break;
				}

				default:
				{
					offsetFn = 'offset';
					break;
				}
			}

			coords = this[offsetFn]();
				coords.width = this[widthFn]();
				coords.height = this[heightFn]();

			if (hidden)
			{
				this.css(
				{
					display: display,
					visibility: visibility
				});
			}

			return coords;
		},

		/**
		 * Sets a unique id for an element, if one is not already present
		 */
		uniqueId: function()
		{
			if (this.attr('id') === '')
			{
				this.attr('id', 'XenForoUniq' + XenForo._uniqueIdCounter++);
			}

			return this;
		},

		/**
		 * Wrapper functions for commonly-used animation effects, so we can customize their behaviour as required
		 */
		xfFadeIn: function(speed, callback)
		{
			return this.fadeIn(speed, function() { $(this).ieOpacityFix(callback); });
		},
		xfFadeOut: function(speed, callback)
		{
			return this.fadeOut(speed, callback);
		},
		xfShow: function(speed, callback)
		{
			return this.show(speed, function() { $(this).ieOpacityFix(callback); });
		},
		xfHide: function(speed, callback)
		{
			return this.hide(speed, callback);
		},
		xfSlideDown: function(speed, callback)
		{
			return this.slideDown(speed, function() { $(this).ieOpacityFix(callback); });
		},
		xfSlideUp: function(speed, callback)
		{
			return this.slideUp(speed, callback);
		},

		/**
		 * Animates an element opening a space for itself, then fading into that space
		 *
		 * @param integer|string Speed of fade-in
		 * @param function Callback function on completion
		 *
		 * @return jQuery
		 */
		xfFadeDown: function(fadeSpeed, callback)
		{
			this.filter(':hidden').xfHide().css('opacity', 0);

			fadeSpeed = fadeSpeed || XenForo.speed.normal;

			return this
				.xfSlideDown(XenForo.speed.fast)
				.animate({ opacity: 1 }, fadeSpeed, function()
				{
					$(this).ieOpacityFix(callback);
				});
		},

		/**
		 * Animates an element fading out then closing the gap left behind
		 *
		 * @param integer|string Speed of fade-out - if this is zero, there will be no animation at all
		 * @param function Callback function on completion
		 * @param integer|string Slide speed - ignored if fadeSpeed is zero
		 * @param string Easing method
		 *
		 * @return jQuery
		 */
		xfFadeUp: function(fadeSpeed, callback, slideSpeed, easingMethod)
		{
			return this
				.animate({ opacity: 0 }, fadeSpeed)
				.slideUp(
				{
					duration: (fadeSpeed ? slideSpeed || XenForo.speed.slow : 0),
					easing: easingMethod || 'easeOutBounce',
					complete: callback
				});
		},

		/**
		 * Inserts and activates content into the DOM, using xfFadeDown to animate the insertion
		 *
		 * @param string jQuery method with which to insert the content
		 * @param string Selector for the previous parameter
		 * @param string jQuery method with which to animate the showing of the content
		 * @param string|integer Speed at which to run the animation
		 * @param function Callback for when the animation is complete
		 *
		 * @return jQuery
		 */
		xfInsert: function(insertMethod, insertReference, animateMethod, animateSpeed, callback)
		{
			if (insertMethod == 'replaceAll')
			{
				$(insertReference).xfFadeUp(animateSpeed);
			}

			this
				.addClass('__XenForoActivator')
				.hide()
				[insertMethod || 'appendTo'](insertReference)
				.xfActivate()
				[animateMethod || 'xfFadeDown'](animateSpeed, callback);

			return this;
		},

		/**
		 * Removes an element from the DOM, animating its removal with xfFadeUp
		 * All parameters are optional.
		 *
		 *  @param string animation method
		 *  @param function callback function
		 *  @param integer Sliding speed
		 *  @param string Easing method
		 *
		 * @return jQuery
		 */
		xfRemove: function(animateMethod, callback, slideSpeed, easingMethod)
		{
			return this[animateMethod || 'xfFadeUp'](XenForo.speed.normal, function()
			{
				$(this).empty().remove();

				if ($.isFunction(callback))
				{
					callback();
				}
			}, slideSpeed, easingMethod);
		},

		/**
		 * Prepares an element for xfSlideIn() / xfSlideOut()
		 *
		 * @param boolean If true, return the height of the wrapper
		 *
		 * @return jQuery|integer
		 */
		_xfSlideWrapper: function(getHeight)
		{
			if (!this.data('slideWrapper'))
			{
				this.data('slideWrapper', this.wrap('<div class="_swOuter"><div class="_swInner" /></div>')
					.closest('div._swOuter').css('overflow', 'hidden'));
			}

			if (getHeight)
			{
				return this.data('slideWrapper').height();
			}

			return this.data('slideWrapper');
		},

		/**
		 * Slides content in (down), with content glued to lower edge, drawer-like
		 *
		 * @param duration
		 * @param easing
		 * @param callback
		 *
		 * @return jQuery
		 */
		xfSlideIn: function(duration, easing, callback)
		{
			var $wrap = this._xfSlideWrapper().css('height', 'auto'),
				height = 0;

			$wrap.find('div._swInner').css('margin', 'auto');
			height = this.show(0).outerHeight();

			$wrap
				.css('height', 0)
				.animate({ height: height }, duration, easing)
			.find('div._swInner')
				.css('marginTop', height * -1)
				.animate({ marginTop: 0 }, duration, easing, callback);

			return this;
		},

		/**
		 * Slides content out (up), reversing xfSlideIn()
		 *
		 * @param duration
		 * @param easing
		 * @param callback
		 *
		 * @return jQuery
		 */
		xfSlideOut: function(duration, easing, callback)
		{
			var height = this.outerHeight();

			this._xfSlideWrapper()
				.animate({ height: 0 }, duration, easing)
			.find('div._swInner')
				.animate({ marginTop: height * -1 }, duration, easing, callback);

			return this;
		},

		/**
		 * Workaround for IE's font-antialiasing bug when dealing with opacity
		 *
		 * @param function Callback
		 */
		ieOpacityFix: function(callback)
		{
			//ClearType Fix
			if (!$.support.opacity)
			{
				this.css('filter', '');
				this.attr('style', this.attr('style').replace(/filter:\s*;/i, ''));
			}

			if ($.isFunction(callback))
			{
				callback.apply(this);
			}

			return this;
		},

		/**
		 * Wraps around jQuery Tools .overlay().
		 *
		 * Prepares overlay options before firing overlay() for best possible experience.
		 * For example, removes fancy (slow) stuff from options for touch browsers.
		 *
		 * @param options
		 *
		 * @returns jQuery
		 */
		overlay: function(options)
		{
			if (XenForo.isTouchBrowser())
			{
				return this._jQueryToolsOverlay($.extend(true, options,
				{
					//mask: false,
					speed: 0,
					loadSpeed: 0
				}));
			}
			else
			{
				return this._jQueryToolsOverlay(options);
			}
		}
	});

	/* jQuery Tools Extensions */

	/**
	 * Effect method for jQuery.tools overlay.
	 * Slides down a container, then fades up the content.
	 * Closes by reversing the animation.
	 */
	$.tools.overlay.addEffect('slideDownContentFade',
		function(position, callback)
		{
			var $overlay = this.getOverlay(),
				conf = this.getConf();

			$overlay.find('.content').css('opacity', 0);

			if (this.getConf().fixed)
			{
				position.position = 'fixed';
			}
			else
			{
				position.position = 'absolute';
				position.top += $(window).scrollTop();
				position.left += $(window).scrollLeft();
			}

			$overlay.css(position).xfSlideDown(XenForo.speed.fast, function()
			{
				$overlay.find('.content').animate({ opacity: 1 }, conf.speed, function() { $(this).ieOpacityFix(callback); });
			});
		},
		function(callback)
		{
			var $overlay = this.getOverlay();

			$overlay.find('.content').animate({ opacity: 0 }, this.getConf().speed, function()
			{
				$overlay.xfSlideUp(XenForo.speed.fast, callback);
			});
		}
	);

	$.tools.overlay.addEffect('slideDown',
		function(position, callback)
		{
			if (this.getConf().fixed)
			{
				position.position = 'fixed';
			}
			else
			{
				position.position = 'absolute';
				position.top += $(window).scrollTop();
				position.left += $(window).scrollLeft();
			}

			this.getOverlay()
				.css(position)
				.xfSlideDown(this.getConf().speed, callback);
		},
		function(callback)
		{
			this.getOverlay().hide(0, callback);
		}
	);

	// *********************************************************************

	$.extend(XenForo,
	{
		/**
		 * Cache for overlays
		 *
		 * @var object
		 */
		_OverlayCache: {},

		/**
		 * Defines whether or not an AJAX request is known to be in progress
		 *
		 *  @var boolean
		 */
		_AjaxProgress: false,

		/**
		 * Counter for unique ID generation
		 *
		 * @var integer
		 */
		_uniqueIdCounter: 0,

		/**
		 * Configuration for overlays, should be redefined in the PAGE_CONTAINER template HTML
		 *
		 * @var object
		 */
		_overlayConfig: {},

		/**
		 * Contains the URLs of all externally loaded resources from scriptLoader
		 *
		 * @var object
		 */
		_loadedScripts: {},

		/**
		 * Configuration for cookies
		 *
		 * @var object
		 */
		_cookieConfig: { path: '/', domain: '', 'prefix': 'xf_'},

		/**
		 * Flag showing whether or not the browser window has focus. On load, assume true.
		 *
		 * @var boolean
		 */
		_hasFocus: true,

		/**
		 * @var object List of server-related time info (now, today, todayDow)
		 */
		serverTimeInfo: {},

		/**
		 * @var object Information about the XenForo visitor. Usually contains user_id.
		 */
		visitor: {},

		/**
		 * @var integer Time the page was loaded.
		 */
		_pageLoadTime: (new Date()).getTime() / 1000,

		/**
		 * JS version key, to force refreshes when needed
		 *
		 * @var string
		 */
		_jsVersion: '',

		/**
		 * CSRF Token
		 *
		 * @var string
		 */
		_csrfToken: '',

		/**
		 * URL to CSRF token refresh.
		 *
		 * @var string
		 */
		_csrfRefreshUrl: '',

		/**
		 * Speeds for animation
		 *
		 * @var object
		 */
		speed:
		{
			xxfast: 50,
			xfast: 100,
			fast: 200,
			normal: 400,
			slow: 600
		},

		/**
		 * Multiplier for animation speeds
		 *
		 * @var float
		 */
		_animationSpeedMultiplier: 1,

		/**
		 * Enable overlays or use regular pages
		 *
		 * @var boolean
		 */
		_enableOverlays: true,

		/**
		 * Enables AJAX submission via AutoValidator. Doesn't change things other than
		 * that. Useful to disable for debugging.
		 *
		 * @var boolean
		 */
		_enableAjaxSubmit: true,

		/**
		 * Determines whether the lightbox shows all images from the current page,
		 * or just from an individual message
		 *
		 * @var boolean
		 */
		_lightBoxUniversal: false,

		/**
		 * @var object Phrases
		 */
		phrases: {},

		/**
		 * Binds all registered functions to elements within the DOM
		 */
		init: function()
		{
			var dStart = new Date(),

			xfFocus = function()
			{
				XenForo._hasFocus = true;
				$(document).triggerHandler('XenForoWindowFocus');
			},

			xfBlur = function()
			{
				XenForo._hasFocus = false;
				$(document).triggerHandler('XenForoWindowBlur');
			};

			if ($.browser.msie)
			{
				$(document).bind(
				{
					focusin:  xfFocus,
					focusout: xfBlur
				});
			}
			else
			{
				$(window).bind(
				{
					focus: xfFocus,
					blur:  xfBlur,
					load: XenForo.chromeAutoFillFix
				});
			}

			// Identify the browser type to CSS
			if (XenForo.isTouchBrowser())
			{
				$('html').addClass('Touch');
			}

			// Set the animation speed based around the style property speed multiplier
			XenForo.setAnimationSpeed(XenForo._animationSpeedMultiplier);

			// Periodical timestamp refresh
			XenForo._TimestampRefresh = new XenForo.TimestampRefresh();

			// Activate all registered controls
			XenForo.activate(document);

			// Periodical CSRF token refresh
			XenForo._CsrfRefresh = new XenForo.CsrfRefresh();

			// Autofocus for non-supporting browsers
			if (!('autofocus' in document.createElement('input')))
			{
				//TODO: work out a way to prevent focusing if something else already has focus http://www.w3.org/TR/html5/forms.html#attr-fe-autofocus
				$('input[autofocus], textarea[autofocus], select[autofocus]').first().focus();
			}

			// init ajax progress indicators
			XenForo.AjaxProgress();

			// init Tweet buttons
			XenForo.tweetButtonInit();

			console.info('XenForo.init() time: %d ms', new Date() - dStart);
		},

		/**
		 * Asynchronously load the specified JavaScript, with an optional callback on completion.
		 *
		 * @param string Script source
		 * @param object Callback function
		 */
		loadJs: function(src, callback)
		{
			try
			{
				var script = document.createElement('script');
					script.async = true;
				  $(script).load(callback);
					script.src = src;
				document.getElementsByTagName('head')[0].appendChild(script);
			}
			catch(e) {}
		},

		/**
		 * Asynchronously load the Twitter button JavaScript.
		 */
		tweetButtonInit: function()
		{
			if ($('a.twitter-share-button').length)
			{
				XenForo.loadJs('http://platform.twitter.com/widgets.js');
			}
		},

		/**
		 * Prevents Google Chrome's AutoFill from turning inputs yellow.
		 * Adapted from http://www.benjaminmiles.com/2010/11/22/fixing-google-chromes-yellow-autocomplete-styles-with-jquery/
		 */
		chromeAutoFillFix: function()
		{
			if ($.browser.webkit && navigator.userAgent.toLowerCase().indexOf('chrome') >= 0)
			{
				var $inputs = $('input:-webkit-autofill');

				if ($inputs.length)
				{
					console.group('Chrome AutoFill Fix');

					$inputs.each(function(i)
					{
						console.debug('%d	%o', i+1, this);

						var text = $(this).val(),
							name = $(this).attr('name');

						$(this).after(this.outerHTML).remove();
						$('input[name=' + name + ']').val(text);
					});

					console.groupEnd();
				}
			}
		},

		/**
		 * Binds a function to elements to fire on a custom event
		 *
		 * @param string jQuery selector - to get the elements to be bound
		 * @param function Function to fire
		 * @param string Custom event name (if empty, assume 'XenForoActivateHtml')
		 */
		register: function(selector, fn, event)
		{
			if (typeof fn == 'string')
			{
				var className = fn;
				fn = function(i)
				{
					XenForo.create(className, this);
				};
			}

			$(document).bind(event || 'XenForoActivateHtml', function(e)
			{
				$(e.element).find(selector).each(fn);
			});
		},

		/**
		 * Creates a new object of class XenForo.{functionName} using
		 * the specified element, unless one has already been created.
		 *
		 * @param string Function name (property of XenForo)
		 * @param object HTML element
		 *
		 * @return object XenForo[functionName]($(element))
		 */
		create: function(className, element)
		{
			var $element = $(element),
				xfObj = window,
				parts = className.split('.'), i;

			for (i = 0; i < parts.length; i++) { xfObj = xfObj[parts[i]]; }

			if (typeof xfObj != 'function')
			{
				return console.error('%s is not a function.', className);
			}

			if (!$element.data(className))
			{
				$element.data(className, new xfObj($element));
			}

			return $element.data(className);
		},

		/**
		 * Fire the initialization events and activate functions for the specified element
		 *
		 * @param object Usually jQuery
		 *
		 * @return object
		 */
		activate: function(element)
		{
			console.group('XenForo.activate(%o)', element);

			$(element).trigger('XenForoActivate').removeClass('__XenForoActivator');

			$(element).find('noscript').empty().remove();

			XenForo._TimestampRefresh.refresh(element, true);

			$(document)
				.trigger({ element: element, type: 'XenForoActivateHtml' })
				.trigger({ element: element, type: 'XenForoActivatePopups' })
				.trigger({ element: element, type: 'XenForoActivationComplete' });

			console.groupEnd();

			return element;
		},

		/**
		 * Pushes an additional parameter onto the data to be submitted via AJAX
		 *
		 * @param array|string Data parameters - either from .serializeArray() or .serialize()
		 * @param string Name of parameter
		 * @param mixed Value of parameter
		 *
		 * @return array|string Data including new parameter
		 */
		ajaxDataPush: function(data, name, value)
		{
			if (!data || typeof data == 'string')
			{
				// data is empty, or a url string - &name=value
				data = String(data);
				data += '&' + name + '=' + value;
			}
			else if (data[0] !== undefined)
			{
				// data is a numerically-keyed array of name/value pairs
				data.push({ name: name, value: value });
			}
			else
			{
				// data is an object with a single set of name & value properties
				data[name] = value;
			}

			return data;
		},

		/**
		 * Wraps around jQuery's own $.ajax function, with our own defaults provided.
		 * Will submit via POST and expect JSON back by default.
		 * Server errors will be handled using XenForo.handleServerError
		 *
		 * @param string URL to load
		 * @param object Data to pass
		 * @param function Success callback function
		 * @param object Additional options to override or extend defaults
		 *
		 * @return XMLHttpRequest
		 */
		ajax: function(url, data, success, options)
		{
			if (!url)
			{
				return console.error('No URL specified for XenForo.ajax()');
			}

			data = XenForo.ajaxDataPush(data, '_xfRequestUri', window.location.pathname + window.location.search);
			data = XenForo.ajaxDataPush(data, '_xfNoRedirect', 1);
			if (XenForo._csrfToken)
			{
				data = XenForo.ajaxDataPush(data, '_xfToken', XenForo._csrfToken);
			}

			var ajaxOptions = $.extend(true,
			{
				data: data,
				url: url,
				success: success,
				type: 'POST',
				dataType: 'json',
				error: function(xhr, textStatus, errorThrown)
				{
					try
					{
						// attempt to pass off to success, if we can decode JSON from the response
						success.call(null, $.parseJSON(xhr.responseText), textStatus);
					}
					catch (e)
					{
						// not valid JSON, trigger server error handler
						XenForo.handleServerError(xhr, textStatus, errorThrown);
					}
				},
				timeout: 30000 // 30s
			}, options);

			// override standard extension, depending on dataType
			if (!ajaxOptions.data._xfResponseType)
			{
				switch (ajaxOptions.dataType)
				{
					case 'html':
					case 'json':
					case 'xml':
					{
						// pass _xfResponseType parameter to override default extension
						ajaxOptions.data = XenForo.ajaxDataPush(ajaxOptions.data, '_xfResponseType', ajaxOptions.dataType);
						break;
					}
				}
			}

			return $.ajax(ajaxOptions);
		},

		/**
		 * Generic handler for server-level errors received from XenForo.ajax
		 * Attempts to provide a useful error message.
		 *
		 * @param object XMLHttpRequest
		 * @param string Response text
		 * @param string Error thrown
		 *
		 * @return boolean False
		 */
		handleServerError: function(xhr, responseText, errorThrown)
		{
			// handle timeout and parse error before attempting to decode an error
			switch (responseText)
			{
				case 'timeout':
				{
					return XenForo.alert(
						XenForo.phrases.server_did_not_respond_in_time_try_again,
						XenForo.phrases.following_error_occurred + ':'
					);
				}
				case 'parsererror':
				{
					console.error('PHP ' + xhr.responseText);
					XenForo.alert('The server responded with an error. The error message is in the JavaScript console.');
					return false;
				}
				case 'notmodified':
				case 'error':
				{
					if (!xhr || !xhr.responseText)
					{
						// this is likely a user cancellation, so just return
						return false;
					}
					break;
				}
			}

			var contentTypeHeader = xhr.getResponseHeader('Content-Type'),
				contentType = false,
				data;

			if (contentTypeHeader)
			{
				switch (contentTypeHeader.split(';')[0])
				{
					case 'application/json':
					{
						contentType = 'json';
						break;
					}
					case 'text/html':
					{
						contentType = 'html';
						break;
					}
					default:
					{
						if (xhr.responseText.substr(0, 1) == '{')
						{
							contentType = 'json';
						}
						else if (xhr.responseText.substr(0, 9) == '<!DOCTYPE')
						{
							contentType = 'html';
						}
					}
				}
			}

			if (contentType == 'json' && xhr.responseText.substr(0, 1) == '{')
			{
				// XMLHttpRequest response is probably JSON
				try
				{
					data = $.parseJSON(xhr.responseText);
				}
				catch (e) {}

				if (data)
				{
					XenForo.hasResponseError(data, xhr.status);
				}
				else
				{
					XenForo.alert(xhr.responseText, XenForo.phrases.following_error_occurred + ':');
				}
			}
			else
			{
				// XMLHttpRequest is some other type...
				XenForo.alert(xhr.responseText, XenForo.phrases.following_error_occurred + ':');
			}

			return false;
		},

		/**
		 * Checks for the presence of an 'error' key in the provided data
		 * and displays its contents if found, using an alert.
		 *
		 * @param object ajaxData
		 * @param integer HTTP error code (optional)
		 *
		 * @return boolean|string Returns the error string if found, or false if not found.
		 */
		hasResponseError: function(ajaxData, httpErrorCode)
		{
			if (typeof ajaxData != 'object')
			{
				XenForo.alert('Response not JSON!'); // debug info, no phrasing
				return true;
			}

			if (ajaxData.error !== undefined)
			{
				// TODO: ideally, handle an array of errors
				if (typeof ajaxData.error === 'object')
				{
					var key;
					for (key in ajaxData.error)
					{
						break;
					}
					ajaxData.error = ajaxData.error[key];
				}

				XenForo.alert(
					ajaxData.error + '\n'
						+ (ajaxData.traceHtml !== undefined ? '<ol class="traceHtml">\n' + ajaxData.traceHtml + '</ol>' : ''),
					XenForo.phrases.following_error_occurred + ':'
				);

				return ajaxData.error;
			}
			else if (ajaxData.status == 'ok' && ajaxData.message)
			{
				XenForo.alert(ajaxData.message);
				return true;
			}
			else
			{
				return false;
			}
		},

		/**
		 * Checks that the supplied ajaxData has a key that can be used to create a jQuery object
		 *
		 *  @param object ajaxData
		 *  @param string key to look for (defaults to 'templateHtml')
		 *
		 *  @return boolean
		 */
		hasTemplateHtml: function(ajaxData, templateKey)
		{
			templateKey = templateKey || 'templateHtml';

			if (!ajaxData[templateKey])
			{
				return false;
			}
			if (typeof(ajaxData[templateKey].search) == 'function')
			{
				return (ajaxData[templateKey].search(/\S+/) !== -1);
			}
			else
			{
				return true;
			}
		},

		/**
		 * Creates an overlay using the given HTML
		 *
		 * @param jQuery Trigger element
		 * @param string|jQuery HTML
		 * @param object Extra options for overlay, will override defaults if specified
		 *
		 * @return jQuery Overlay API
		 */
		createOverlay: function($trigger, templateHtml, extraOptions)
		{
			var $overlay = null,
				$templateHtml = null,
				api = null,
				overlayOptions = null,
				noFixed = false;

			if (templateHtml instanceof jQuery && templateHtml.is('.xenOverlay'))
			{
				// this is an object that has already been initialised
				$overlay = templateHtml.appendTo('body');
			}
			else
			{
				$templateHtml = $(templateHtml);

				// add a header to the overlay, unless instructed otherwise
				if (!$templateHtml.is('.NoAutoHeader'))
				{
					// jQuery and IE <= 8 falls over on append() when the HTML to be appended contains <script>
					if ((($.browser.msie && $.browser.version < 9) || $.browser.opera) && templateHtml.indexOf && templateHtml.indexOf('<script') != -1)
					{
						if ($trigger.attr('href'))
						{
							window.location = XenForo.canonicalizeUrl($trigger.attr('href'));
							return false;
						}
					}

					if (extraOptions && extraOptions.title)
					{
						$('<h2 class="heading h1" />')
							.html(extraOptions.title)
							.prependTo($templateHtml);
					}
				}

				// add a cancel button to the overlay, if the overlay is a .formOverlay, has a .submitUnit but has no :reset button
				if ($templateHtml.is('.formOverlay'))
				{
					if ($templateHtml.find('.submitUnit').length)
					{
						if (!$templateHtml.find('.submitUnit :reset').length)
						{
							$templateHtml.find('.submitUnit .button:last')
								.after($('<input type="reset" class="button OverlayCloser" />').val(XenForo.phrases.cancel))
								.after(' ');
						}
					}
				}

				// create an overlay container, add the activated template to it and append it to the body.
				$overlay = $('<div class="xenOverlay __XenForoActivator" />')
					.appendTo('body')
					.addClass($(templateHtml).data('overlayClass')) // if content defines data-overlayClass, apply the value to the overlay as a class.
					.append($templateHtml)
					.xfActivate();
			}

			if (extraOptions)
			{
				// add {effect}Effect class to overlay container if necessary
				if (extraOptions.effect)
				{
					$overlay.addClass(extraOptions.effect + 'Effect');
				}

				// add any extra class name defined in extraOptions
				if (extraOptions.className)
				{
					$overlay.addClass(extraOptions.className);
					delete(extraOptions.className);
				}
			}

			// add an overlay closer if one does not already exist
			if ($overlay.find('.OverlayCloser').length == 0)
			{
				$overlay.prepend('<a class="close OverlayCloser"></a>');
			}

			$overlay.find('.OverlayCloser').click(function(e) { e.stopPropagation(); });

			// if no trigger was specified (automatic popup), then activate the overlay instead of the trigger
			$trigger = $trigger || $overlay;

			// activate the overlay
			$trigger.overlay($.extend(true,
			{
				target: $overlay,
				oneInstance: true,
				close: '.OverlayCloser',
				speed: XenForo.speed.normal,
				closeSpeed: XenForo.speed.fast,
				mask:
				{
					color: 'white',
					opacity: 0.6,
					loadSpeed: XenForo.speed.normal,
					closeSpeed: XenForo.speed.fast
				},
				// IE6 doesn't support position: fixed; webkit mobile attaches to the body not the viewport
				fixed: !(($.browser.msie && $.browser.version <= 6) || XenForo._isWebkitMobile)

			}, XenForo._overlayConfig, extraOptions));

			$trigger.bind(
			{
				onBeforeLoad: function(e)
				{
					$(document).triggerHandler('OverlayOpening');
				},
				onLoad: function(e)
				{
					var api = $(this).data('overlay'),
						$overlay = api.getOverlay(),
						scroller = $overlay.find('.OverlayScroller').get(0),
						resizeClose = null;


					// timeout prevents flicker in FF
					if (scroller)
					{
						setTimeout(function()
						{
							scroller.scrollIntoView(true);
						}, 0);
					}

					// autofocus the first form element in a .formOverlay
					$overlay.find('.formOverlay').find('input, textarea, select, button').first().focus();

					// hide on window resize
					if (api.getConf().closeOnResize)
					{
						resizeClose = function()
						{
							console.info('Window resize, close overlay!');
							api.close();
						};

						$(window).one('resize', resizeClose);

						// remove event when closing the overlay
						$trigger.one('onClose', function()
						{
							$(window).unbind('resize', resizeClose);
						});
					}

					$(document).triggerHandler('OverlayOpened');
				}
			});

			api = $trigger.data('overlay');
				  $overlay.data('overlay', api);

			return api;
		},

		/**
		 * Present the user with a pop-up, modal message that they must confirm
		 *
		 * @param string Message
		 * @param string Message type (error, info, redirect)
		 * @param integer Timeout (auto-close after this period)
		 * @param function Callback onClose
		 */
		alert: function(message, messageType, timeOut, onClose)
		{
			message = String(message || 'Unspecified error');

			var key = message.replace(/[^a-z0-9_]/gi, '_') + parseInt(timeOut),
				$overlayHtml;

			if (XenForo._OverlayCache[key] === undefined)
			{
				if (timeOut)
				{
					XenForo._OverlayCache[key] = $(''
						+ '<div class="xenOverlay timedMessage">'
						+	'<div class="content baseHtml">'
						+		message
						+		'<span class="close"></span>'
						+	'</div>'
						+ '</div>'
					).appendTo('body').overlay(
					{
						top: 0,
						effect: 'slideDownContentFade',
						speed: XenForo.speed.normal,
						oneInstance: false,
						onBeforeClose: (onClose ? onClose : null)
					}).data('overlay');
				}
				else
				{
					$overlayHtml = $(''
						+ '<div class="errorOverlay">'
						+ 	'<a class="close OverlayCloser"></a>'
						+ 	'<h2 class="heading">' + (messageType || XenForo.phrases.following_error_occurred) + '</h2>'
						+ 	'<div class="baseHtml"></div>'
						+ '</div>'
					);
					$overlayHtml.find('div.baseHtml').html(message);
					XenForo._OverlayCache[key] = XenForo.createOverlay(null, $overlayHtml, {
						onLoad: function() { var el = $('input:button.close, button.close', document.getElementById(key)).get(0); if (el) { el.focus(); } },
						onClose: (onClose ? onClose : null)
					});
				}
			}

			XenForo._OverlayCache[key].load();

			if (timeOut)
			{
				setTimeout('XenForo._OverlayCache["' + key + '"].close()', timeOut);
			}

			return false;
		},

		/**
		 * Adjusts all animation speeds used by XenForo
		 *
		 * @param integer multiplier - set to 0 to disable all animation
		 */
		setAnimationSpeed: function(multiplier)
		{
			var ieSpeedAdjust, s, index;

			for (index in XenForo.speed)
			{
				s = XenForo.speed[index];

				if ($.browser.msie)
				{
					// if we are using IE, change the animation lengths for a smoother appearance
					if (s <= 100)
					{
						ieSpeedAdjust = 2;
					}
					else if (s > 800)
					{
						ieSpeedAdjust = 1;
					}
					else
					{
						ieSpeedAdjust = 1 + 100/s;
					}
					XenForo.speed[index] = s * multiplier * ieSpeedAdjust;
				}
				else
				{
					XenForo.speed[index] = s * multiplier;
				}
			}
		},

		/**
		 * Generates a unique ID for an element, if required
		 *
		 * @param object HTML element (optional)
		 *
		 * @return string Unique ID
		 */
		uniqueId: function(element)
		{
			if (!element)
			{
				return 'XenForoUniq' + XenForo._uniqueIdCounter++;
			}
			else
			{
				return $(element).uniqueId().attr('id');
			}
		},

		canonicalizeUrl: function(url)
		{
			if (url.indexOf('/') == 0)
			{
				return url;
			}
			else if (url.match(/^https?:/i))
			{
				return url;
			}
			else
			{
				return $('base').attr('href') + url;
			}
		},

		/**
		 * Adds a trailing slash to a string if one is not already present
		 *
		 * @param string
		 */
		trailingSlash: function(string)
		{
			if (string.substr(-1) != '/')
			{
				string += '/';
			}

			return string;
		},

		/**
		 * Escapes a string so it can be inserted into a RegExp without altering special characters
		 *
		 * @param string
		 *
		 * @return string
		 */
		regexQuote: function(string)
		{
			return (string + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!<>\|\:])/g, "\\$1");
		},

		/**
		 * Escapes HTML into plain text
		 *
		 * @param string
		 *
		 * @return string
		 */
		htmlspecialchars: function(string)
		{
			return string
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		},

		/**
		 * Checks whether or not a tag is a list container
		 *
		 * @param jQuery Tag
		 *
		 * @return boolean
		 */
		isListTag: function($tag)
		{
			return ($tag.tagName == 'ul' || $tag.tagName == 'ol');
		},

		/**
		 * Checks that the value passed is a numeric value, even if its actual type is a string
		 *
		 * @param mixed Value to be checked
		 *
		 * @return boolean
		 */
		isNumeric: function(value)
		{
			return (!isNaN(value) && (value - 0) == value && value.length > 0);
		},

		/**
		 * Helper to check that an attribute value is 'positive'
		 *
		 * @param scalar Value to check
		 *
		 * @return boolean
		 */
		isPositive: function(value)
		{
			switch (String(value).toLowerCase())
			{
				case 'on':
				case 'yes':
				case 'true':
				case '1':
					return true;

				default:
					return false;
			}
		},

		/**
		 * Converts the first character of a string to uppercase.
		 *
		 * @param string
		 *
		 * @return string
		 */
		ucfirst: function(string)
		{
			return string.charAt(0).toUpperCase() + string.substr(1);
		},

		/**
		 * Replaces any existing avatars for the given user on the page
		 *
		 * @param integer user ID
		 * @param array List of avatar urls for the user, keyed with size code
		 * @param boolean Include crop editor image
		 */
		updateUserAvatars: function(userId, avatarUrls, andEditor)
		{
			console.log('Replacing visitor avatars on page: %o', avatarUrls);

			$.each(avatarUrls, function(sizeCode, avatarUrl)
			{
				var sizeClass = '.Av' + userId + sizeCode + (andEditor ? '' : ':not(.AvatarCropControl)');

				// .avatar > img
				$(sizeClass).find('img').attr('src', avatarUrl);

				// .avatar > span.img
				$(sizeClass).find('span.img').css('background-image', 'url(' + avatarUrl + ')');
			});
		},

		getEditorInForm: function(form)
		{
			var $form = $(form),
				$textarea = $form.find('textarea.MessageEditor');

			if ($textarea.length)
			{
				if ($textarea.attr('disabled'))
				{
					return $form.find('.bbCodeEditorContainer textarea');
				}
				else if (window.tinyMCE && $textarea.attr('id') && tinyMCE.editors[$textarea.attr('id')])
				{
					return tinyMCE.editors[$textarea.attr('id')];
				}
				else
				{
					return $textarea;
				}
			}

			return false;
		},

		/**
		 * Returns the name of the tag that should be animated for page scrolling
		 *
		 * @return string
		 */
		getPageScrollTagName: function()
		{
			//TODO: watch for webkit support for scrolling 'html'
			return ($.browser.webkit ? 'body' : 'html');
		},

		/**
		 * Determines whether or not we are working with a touch-based browser
		 *
		 * @return boolean
		 */
		isTouchBrowser: function()
		{
			if (XenForo._isTouchBrowser === undefined)
			{
				try
				{
					document.createEvent('TouchEvent');
					XenForo._isTouchBrowser = true;
				}
				catch(e)
				{
					XenForo._isTouchBrowser = (navigator.userAgent.indexOf('webOS') != -1 ? true : false);
				}
			}

			return XenForo._isTouchBrowser;
		},

		/**
		 * Lazy-loads Javascript files
		 *
		 * @param string File to load
		 * @param
		 */
		scriptLoader:
		{
			loadScript: function(url, success, failure)
			{
				if (XenForo._loadedScripts[url] === undefined)
				{
					if (/tiny_mce[a-zA-Z0-9_-]*\.js/.test(url))
					{
						var preInit = {suffix: '', base: '', query: ''},
							baseHref = $('base').attr('href');

						if (/_(src|dev)\.js/g.test(url))
						{
							preInit.suffix = '_src';
						}

						if ((p = url.indexOf('?')) != -1)
						{
							preInit.query = url.substring(p + 1);
						}

						preInit.base = url.substring(0, url.lastIndexOf('/'));

						if (baseHref && preInit.base.indexOf('://') == -1 && preInit.base.indexOf('/') !== 0)
							preInit.base = baseHref + preInit.base;

						window.tinyMCEPreInit = preInit;
					}

					$.ajax(
					{
						type: 'GET',
						url: url,
						cache: true,
						dataType: 'text',
						error: failure,
						success: function(javascript, textStatus)
						{
							XenForo._loadedScripts[url] = true;

							$.globalEval(javascript);

							success.call();
						}
					});
				}
				else
				{
					success.call();
				}
			},

			loadCss: function(css, urlTemplate, success, failure)
			{
				var stylesheets = [],
					url;

				// build a list of stylesheets we have not already loaded
				$.each(css, function(i, stylesheet)
				{
					if (!XenForo._loadedScripts[stylesheet])
					{
						stylesheets.push(stylesheet);
					}
				});

				// if there are any left, construct the URL and load them
				if (stylesheets.length)
				{
					url = urlTemplate.replace('__sentinel__', stylesheets.join(','));

					$.ajax(
					{
						type: 'GET',
						url: url,
						cache: true,
						dataType: 'text',
						error: failure,
						success: function(cssText, textStatus)
						{
							$.each(stylesheets, function(i, stylesheet)
							{
								console.log('Loaded script %d, %s', i, stylesheet);
								XenForo._loadedScripts[stylesheet] = true;
							});

							var baseHref = $('base').first().attr('href');
							if (baseHref)
							{
								cssText = cssText.replace(
									/(url\(("|')?)([^"')]+)(("|')?\))/gi,
									function(all, front, null1, url, back, null2)
									{
										if (!url.match(/^(https?:|\/)/i))
										{
											url = baseHref + url;
										}
										return front + url + back;
									}
								);
							}

							$('<style type="text/css">' + cssText + '</style>').appendTo('head');

							success.call();
						}
					});
				}
				else
				{
					success.call();
				}
			}
		}
	});

	// *********************************************************************

	/**
	 * Loads the requested list of javascript and css files
	 * Before firing the specified callback.
	 *
	 * @param array Javascript URLs
	 * @param array CSS URLs
	 * @param function Success callback
	 * @param function Error callback
	 */
	XenForo.ExtLoader = function(data, success, failure) { this.__construct(data, success, failure); };
	XenForo.ExtLoader.prototype =
	{
		__construct: function(data, success, failure)
		{
			this.success = success;
			this.failure = failure;
			this.totalFetched = 0;
			this.data = data;

			var numJs = 0,
				hasCss = 0,
				i = 0;

			// check if css is required, and make sure the format is good
			if (data.css && !$.isEmptyObject(data.css.stylesheets))
			{
				if (!data.css.urlTemplate)
				{
					return console.warn('Unable to load CSS without a urlTemplate being provided.');
				}

				hasCss = 1;
			}

			// check if javascript is required, and make sure the format is good
			if (data.js)
			{
				numJs = data.js.length;
			}

			this.totalExt = hasCss + numJs;

			// nothing to do
			if (!this.totalExt)
			{
				return this.callSuccess();
			}

			// fetch required javascript
			if (numJs)
			{
				for (i = 0; i < numJs; i++)
				{
					XenForo.scriptLoader.loadScript(data.js[i], $.context(this, 'successCount'), $.context(this, 'callFailure'));
				}
			}

			// fetch required css
			if (hasCss)
			{
				XenForo.scriptLoader.loadCss(data.css.stylesheets, data.css.urlTemplate, $.context(this, 'successCount'), $.context(this, 'callFailure'));
			}
		},

		/**
		 * Fires the success callback
		 */
		callSuccess: function()
		{
			if (typeof this.success == 'function')
			{
				this.success(this.data);
			}
		},

		/**
		 * Fires the error callback
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 * @param boolean errorThrown
		 */
		callFailure: function(ajaxData, textStatus, errorThrown)
		{
			if (!this.failed)
			{
				if (typeof this.failure == 'function')
				{
					this.failure(this.data);
				}
				else
				{
					console.warn('ExtLoader Failure %s %s', textStatus, ajaxData.status);
				}

				this.failed = true;
			}
		},

		/**
		 * Increment the totalFetched variable, and
		 * fire callSuccess() when this.totalFetched
		 * equals this.totalExt
		 *
		 * @param event e
		 */
		successCount: function(e)
		{
			this.totalFetched++;

			if (this.totalFetched >= this.totalExt)
			{
				this.callSuccess();
			}
		}
	};

	// *********************************************************************

	/**
	 * Instance of XenForo.TimestampRefresh
	 *
	 * @var XenForo.TimestampRefresh
	 */
	XenForo._TimestampRefresh = null;

	/**
	 * Allows date/time stamps on the page to be displayed as relative to now, and auto-refreshes periodically
	 */
	XenForo.TimestampRefresh = function() { this.__construct(); };
	XenForo.TimestampRefresh.prototype =
	{
		__construct: function()
		{
			this.active = this.activate();

			$(document).bind('XenForoWindowFocus', $.context(this, 'focus'));
		},

		/**
		 * Runs on window.focus, activates the system if deactivated
		 *
		 * @param event e
		 */
		focus: function(e)
		{
			if (!this.active)
			{
				this.activate(true);
			}
		},

		/**
		 * Runs a refresh, then refreshes again every 60 seconds
		 *
		 * @param boolean Refresh instantly
		 *
		 * @return integer Refresh interval or something...
		 */
		activate: function(instant)
		{
			if (instant)
			{
				this.refresh();
			}

			return this.active = window.setInterval($.context(this, 'refresh'), 60 * 1000); // one minute
		},

		/**
		 * Halts timestamp refreshes
		 *
		 * @return boolean false
		 */
		deactivate: function()
		{
			window.clearInterval(this.active);
			return this.active = false;
		},

		/**
		 * Date/Time output updates
		 */
		refresh: function(element, force)
		{
			if (!XenForo._hasFocus && !force)
			{
				return this.deactivate();
			}

			if ($.browser.msie && $.browser.version <= 6)
			{
				return;
			}

			var $elements = $('abbr.DateTime[data-time]', element),
				pageOpenTime = (new Date().getTime() / 1000),
				pageOpenLength = pageOpenTime - XenForo._pageLoadTime,
				serverTime = XenForo.serverTimeInfo.now,
				today = XenForo.serverTimeInfo.today,
				todayDow = XenForo.serverTimeInfo.todayDow,
				yesterday, week, dayOffset,
				i, $element, thisTime, thisDiff, thisServerTime, interval, calcDow;

			if (serverTime + pageOpenLength > today + 86400)
			{
				// day has changed, need to adjust
				dayOffset = Math.floor((serverTime + pageOpenLength - today) / 86400);

				today += dayOffset * 86400;
				todayDow = (todayDow + dayOffset) % 7;
			}

			yesterday = today - 86400;
			week = today - 6 * 86400;

			console.count('XenForo.TimestampRefresh');

			for (i = 0; i < $elements.length; i++)
			{
				$element = $($elements[i]);

				// set the original value of the tag as its title
				if (!$element.attr('title'))
				{
					$element.attr('title', $element.text());
				}

				thisDiff = parseInt($element.data('diff'), 10);
				thisTime = parseInt($element.data('time'), 10);

				thisServerTime = thisTime + thisDiff;
				if (thisServerTime > serverTime + pageOpenLength)
				{
					thisServerTime = Math.floor(serverTime + pageOpenLength);
				}
				interval = serverTime - thisServerTime + thisDiff + pageOpenLength;

				if (interval < 0)
				{
					// date in the future
				}
				else if (interval <= 60)
				{
					$element.text(XenForo.phrases.a_moment_ago);
				}
				else if (interval <= 120)
				{
					$element.text(XenForo.phrases.one_minute_ago);
				}
				else if (interval < 3600)
				{
					$element.text(XenForo.phrases.x_minutes_ago
						.replace(/%minutes%/, Math.floor(interval / 60)));
				}
				else if (thisTime > today)
				{
					$element.text(XenForo.phrases.today_at_x
						.replace(/%time%/, $element.data('timestring')));
				}
				else if (thisTime > yesterday)
				{
					$element.text(XenForo.phrases.yesterday_at_x
						.replace(/%time%/, $element.data('timestring')));
				}
				else if (thisTime > week)
				{
					calcDow = todayDow - Math.ceil((today - thisTime) / 86400);
					if (calcDow < 0)
					{
						calcDow += 7;
					}

					$element.text(XenForo.phrases.day_x_at_time_y
						.replace('%day%', XenForo.phrases['day' + calcDow])
						.replace(/%time%/, $element.data('timestring'))
					);
				}
				else
				{
					$element.text($element.data('datestring'));
				}
			}
		}
	};

	// *********************************************************************

	/**
	 * Periodically refreshes all CSRF tokens on the page
	 */
	XenForo.CsrfRefresh = function() { this.__construct(); };
	XenForo.CsrfRefresh.prototype =
	{
		__construct: function()
		{
			this.activate();

			$(document).bind('XenForoWindowFocus', $.context(this, 'focus'));
		},

		/**
		 * Runs on window focus, activates the system if deactivated
		 *
		 * @param event e
		 */
		focus: function(e)
		{
			if (!this.active)
			{
				this.activate(true);
			}
		},

		/**
		 * Runs a refresh, then refreshes again every hour
		 *
		 * @param boolean Refresh instantly
		 *
		 * @return integer Refresh interval or something...
		 */
		activate: function(instant)
		{
			if (instant)
			{
				this.refresh();
			}

			this.active = window.setInterval($.context(this, 'refresh'), 50 * 60 * 1000); // 50 minutes
			return this.active;
		},

		/**
		 * Halts csrf refreshes
		 */
		deactivate: function()
		{
			window.clearInterval(this.active);
			this.active = false;
		},

		/**
		 * Updates all CSRF tokens
		 */
		refresh: function()
		{
			if (!XenForo._csrfRefreshUrl)
			{
				return;
			}

			if (!XenForo._hasFocus)
			{
				this.deactivate();
				return;
			}

			XenForo.ajax(
				XenForo._csrfRefreshUrl,
				'',
				function(ajaxData, textStatus)
				{
					if (!ajaxData || ajaxData.csrfToken === undefined)
					{
						return false;
					}

					var tokenInputs = $('input[name=_xfToken]').val(ajaxData.csrfToken);

					XenForo._csrfToken = ajaxData.csrfToken;

					if (tokenInputs.length)
					{
						console.log('XenForo CSRF token updated in %d places (%s)', tokenInputs.length, ajaxData.csrfToken);
					}

					$(document).trigger(
					{
						type: 'CSRFRefresh',
						ajaxData: ajaxData
					});
				},
				{ error: false, global: false }
			);
		}
	};

	// *********************************************************************

	/**
	 * Stores the id of the currently active popup menu group
	 *
	 * @var string
	 */
	XenForo._PopupMenuActiveGroup = null;

	/**
	 * Popup menu system.
	 *
	 * Requires:
	 * <el class="Popup">
	 * 		<a rel="Menu">control</a>
	 * 		<el class="Menu {Left} {Hider}">menu content</el>
	 * </el>
	 *
	 * * .Menu.Left causes orientation of menu to reverse, away from scrollbar
	 * * .Menu.Hider causes menu to appear over control instead of below
	 *
	 * @param jQuery *.Popup container element
	 */
	XenForo.PopupMenu = function($container) { this.__construct($container); };
	XenForo.PopupMenu.prototype =
	{
		__construct: function($container)
		{
			// the container holds the control and the menu
			this.$container = $container;

			// take the menu, which will be a sibling of the control, and append/move it to the end of the body
			this.$menu = this.$container.find('.Menu').appendTo('body');
			this.menuVisible = false;

			// check that we have the necessary elements
			if (!this.$menu.length)
			{
				console.warn('Unable to find menu for Popup %o', this.$container);

				return false;
			}

			// add a unique id to the menu
			this.$menu.id = XenForo.uniqueId(this.$menu);

			// variables related to dynamic content loading
			this.contentSrc = this.$menu.data('contentSrc');
			this.contentDest = this.$menu.data('contentDest');
			this.loading = null;
			this.unreadDisplayTimeout = null;

			// bind events to the menu control
			this.$clicker = $container.find('[rel="Menu"]').first()
				.click($.context(this, 'controlClick'))
				.mouseover($.context(this, 'controlHover'))
				.hoverIntent(
				{
					sensitivity: 1,
					interval: 100,
					timeout: 0,
					over: $.context(this, 'controlHoverIntent'),
					out: function(){}
				});

			this.$control = this.addPopupGadget(this.$clicker);

			// the popup group for this menu, if specified
			this.popupGroup = this.$control.closest('[data-popupGroup]').data('popupGroup');

			//console.log('Finished popup menu for %o', this.$control);
		},

		addPopupGadget: function($control)
		{
			if (!$control.hasClass('NoPopupGadget') && !$control.hasClass('SplitCtrl'))
			{
				$control.append('<span class="arrowWidget" />');
			}

			var $popupControl = $control.closest('.PopupControl');
			if ($popupControl.length)
			{
				$control = $popupControl.addClass('PopupContainerControl');
			}

			$control.addClass('PopupControl');

			return $control;
		},

		/**
		 * Opens or closes a menu, or navigates to another page, depending on menu status and control attributes.
		 *
		 * Clicking a control while the menu is hidden will open and show the menu.
		 * If the control has an href attribute, clicking on it when the menu is open will navigate to the specified URL.
		 * If the control does not have an href, a click will close the menu.
		 *
		 * @param event
		 *
		 * @return mixed
		 */
		controlClick: function(e)
		{
			console.debug('%o control clicked. NewlyOpened: %s, Animated: %s', this.$control, this.newlyOpened, this.$menu.is(':animated'));

			if (!this.newlyOpened && !this.$menu.is(':animated'))
			{
				console.info('control: %o', this.$control);

				if (this.$menu.is(':hidden'))
				{
					this.showMenu(e, false);
				}
				else if (this.$clicker.attr('href') && !XenForo.isPositive(this.$clicker.data('closeMenu')))
				{
					console.warn('Following hyperlink from %o', this.$clicker);
					return true;
				}
				else
				{
					this.hideMenu(e, false);
				}
			}
			else
			{
				console.debug('Click on control of newly-opened or animating menu, ignored');
			}

			e.preventDefault();
			e.target.blur();
			return false;
		},

		/**
		 * Handles hover events on menu controls. Will normally do nothing,
		 * unless there is a menu open and the control being hovered belongs
		 * to the same popupGroup, in which case this menu will open instantly.
		 *
		 * @param event
		 *
		 * @return mixed
		 */
		controlHover: function(e)
		{
			if (this.popupGroup != null && this.popupGroup == this.getActiveGroup())
			{
				this.showMenu(e, true);

				return false;
			}
		},

		/**
		 * Handles hover-intent events on menu controls. Menu will show
		 * if the cursor is hovered over a control at low speed and for a duration
		 *
		 * @param event
		 */
		controlHoverIntent: function(e)
		{
			var instant = false;//(this.popupGroup != null && this.popupGroup == this.getActiveGroup());

			if (this.$clicker.hasClass('SplitCtrl'))
			{
				instant = true;
			}

			this.showMenu(e, instant);
		},

		/**
		 * Opens and shows a popup menu.
		 *
		 * If the menu requires dynamic content to be loaded, this will load the content.
		 * To define dynamic content, the .Menu element should have:
		 * * data-contentSrc = URL to JSON that contains templateHtml to be inserted
		 * * data-contentDest = jQuery selector specifying the element to which the templateHtml will be appended. Defaults to this.$menu.
		 *
		 * @param event
		 * @param boolean Show instantly (true) or fade in (false)
		 */
		showMenu: function(e, instant)
		{
			if (this.$menu.is(':visible'))
			{
				return false;
			}

			//console.log('Show menu event type = %s', e.type);

			var $eShow = new $.Event('PopupMenuShow');
			$eShow.$menu = this.$menu;
			$eShow.instant = instant;
			$(document).trigger($eShow);

			if ($eShow.isDefaultPrevented())
			{
				return false;
			}

			this.menuVisible = true;

			this.setMenuPosition('showMenu');

			if (this.$menu.hasClass('BottomControl'))
			{
				instant = true;
			}

			if (this.contentSrc && !this.loading)
			{
				this.loading = XenForo.ajax(
					this.contentSrc, '',
					$.context(this, 'loadSuccess'),
					{ type: 'GET' }
				);

				this.$menu.find('.Progress').addClass('InProgress');

				instant = true;
			}

			this.setActiveGroup();

			this.$control.addClass('PopupOpen').removeClass('PopupClosed');

			this.$menu.stop().xfSlideDown((instant ? 0 : XenForo.speed.xfast), $.context(this, 'menuShown'));

			if (!this.menuEventsInitialized)
			{
				// TODO: make this global?
				// TODO: touch interfaces don't like this
				$(document).bind(
				{
					click:         $.context(this, 'hideMenu'),
					PopupMenuShow: $.context(this, 'hideIfOther')
				});

				// Webkit mobile kinda does not support document.click, bind to other elements
				if (XenForo._isWebkitMobile)
				{
					$('#header, #content, .footer').click($.context(this, 'hideMenu'));
				}

				$(window).bind(
				{
					resize: $.context(this, '_hideMenu')
				});

				this.$menu.delegate('a', 'click', $.context(this, 'menuLinkClick'));
				this.$menu.delegate('.MenuCloser', 'click', $.context(this, 'hideMenu'));

				this.menuEventsInitialized = true;
			}
		},

		/**
		 * Hides an open popup menu (conditionally)
		 *
		 * @param event
		 * @param boolean Hide instantly (true) or fade out (false)
		 */
		hideMenu: function(e, instant)
		{
			if (this.$menu.is(':visible') && this.triggersMenuHide(e))
			{
				this._hideMenu(e, !instant);
			}
		},

		/**
		 * Hides an open popup menu, without checking context or environment
		 *
		 * @param event
		 * @param boolean Fade out the menu (true) or hide instantly out (false)
		 */
		_hideMenu: function(e, fade)
		{
			//console.log('Hide menu \'%s\' %o TYPE = %s', this.$control.text(), this.$control, e.type);
			this.menuVisible = false;

			this.setActiveGroup(null);

			if (this.$menu.hasClass('BottomControl'))
			{
				fade = false;
			}

			// stop any unread content fading into its read state
			clearTimeout(this.unreadDisplayTimeout);
			this.$menu.find('.Unread').stop();

			this.$menu.xfSlideUp((fade ? XenForo.speed.xfast : 0), $.context(this, 'menuHidden'));
		},

		/**
		 * Fires when the menu showing animation is completed and the menu is displayed
		 */
		menuShown: function()
		{
			// if the menu has a data-contentSrc attribute, we can assume that it requires dynamic content, which has not yet loaded
			var contentLoaded = (this.$menu.data('contentSrc') ? false : true);

			this.$control.addClass('PopupOpen').removeClass('PopupClosed');

			this.newlyOpened = true;
			setTimeout($.context(function()
			{
				this.newlyOpened = false;
			}, this), 250);

			this.$menu.trigger('ShowComplete', [contentLoaded]);

			this.setMenuPosition('menuShown');

			this.highlightUnreadContent();
		},

		/**
		 * Fires when the menu hiding animations is completed and the menu is hidden
		 */
		menuHidden: function()
		{
			this.$control.removeClass('PopupOpen').addClass('PopupClosed');

			this.$menu.trigger('MenuHidden');
		},

		/**
		 * Fires in response to the document triggering 'PopupMenuShow' and hides the current menu
		 * if the menu that fired the event is not itself.
		 *
		 * @param event
		 */
		hideIfOther: function(e)
		{
			if (e.$menu.attr($.expando) != this.$menu.attr($.expando))
			{
				this.hideMenu(e, e.instant);
			}
		},

		/**
		 * Checks to see if an event should hide the menu.
		 *
		 * Returns false if:
		 * * Event target is a child of the menu, or is the menu itself
		 *
		 * @param event
		 *
		 * @return boolean
		 */
		triggersMenuHide: function(e)
		{
			var $target = $(e.target);

			if (e.ctrlKey || e.shiftKey || e.altKey)
			{
				return false;
			}

			if (e.which > 1)
			{
				// right or middle click, don't close
				return false;
			}

			if ($target.is('.MenuCloser'))
			{
				return true;
			}

			// is the control a hyperlink that has not had its default action prevented?
			if ($target.is('a[href]') && !e.isDefaultPrevented())
			{
				return true;
			}

			// is the control a child of the menu, or the menu itself?
			if (!$target.parents().andSelf().is('#' + this.$menu.id))
			{
				return true;
			}

			return false;
		},

		/**
		 * Sets the position of the popup menu, based on the position of the control
		 */
		setMenuPosition: function(caller)
		{
			//console.info('setMenuPosition(%s)', caller);

			var controlLayout, // control coordinates
				menuLayout, // menu coordinates
				contentLayout, // #content coordinates
				$content,
				$window,
				proposedLeft,
				proposedTop;

			controlLayout = this.$control.coords('outer');

			this.$control.removeClass('BottomControl');

			// set the menu to sit flush with the left of the control, immediately below it
			this.$menu.removeClass('BottomControl').css(
			{
				left: controlLayout.left,
				top: controlLayout.top + controlLayout.height
			});

			menuLayout = this.$menu.coords('outer');

			$content = $('#content .pageContent');
			if ($content.length)
			{
				contentLayout = $content.coords('outer');
			}
			else
			{
				contentLayout = $('body').coords('outer');
			}

			$window = $(window);
			$window.sT = $window.scrollTop();
			$window.sL = $window.scrollLeft();

			/*
			 * if the menu's right edge is off the screen, check to see if
			 * it would be better to position it flush with the right edge of the control
			 */
			if (menuLayout.left + menuLayout.width > contentLayout.left + contentLayout.width)
			{
				proposedLeft = controlLayout.left + controlLayout.width - menuLayout.width;
				// must always position to left with mobile webkit as the menu seems to close if it goes off the screen
				if (proposedLeft > $window.sL || XenForo._isWebkitMobile)
				{
					this.$menu.css('left', proposedLeft);
				}
			}

			/*
			 * if the menu's bottom edge is off the screen, check to see if
			 * it would be better to position it above the control
			 */
			if (menuLayout.top + menuLayout.height > $window.height() + $window.sT)
			{
				proposedTop = controlLayout.top - menuLayout.height;
				if (proposedTop > $window.sT)
				{
					this.$control.addClass('BottomControl');
					this.$menu.addClass('BottomControl');
					this.$menu.css('top', proposedTop);
				}
			}
		},

		/**
		 * Fires when dynamic content for a popup menu has been loaded.
		 *
		 * Checks for errors and if there are none, appends the new HTML to the element selected by this.contentDest.
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		loadSuccess: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData) || !XenForo.hasTemplateHtml(ajaxData))
			{
				return false;
			}

			this.$menu.trigger('LoadComplete');

			var $templateHtml = $(ajaxData.templateHtml);

			// removing this attribute prevents content being re-loaded
			this.$menu.removeData('contentSrc');

			// check for content destination
			if (!this.contentDest)
			{
				console.warn('Menu content destination not specified, using this.$menu.');

				this.contentDest = this.$menu;
			}

			console.info('Content destination: %o', this.contentDest);

			// append the loaded content to the destination
			$templateHtml.xfInsert(
				this.$menu.data('insertFn') || 'appendTo',
				this.contentDest,
				'slideDown', 0,
				$.context(this, 'menuShown')
			);

			this.$menu.find('.Progress').removeClass('InProgress');
		},

		menuLinkClick: function(e)
		{
			this.hideMenu(e, true);
		},

		/**
		 * Sets the name of the globally active popup group
		 *
		 * @param mixed If specified, active group will be set to this value.
		 *
		 * @return string Active group name
		 */
		setActiveGroup: function(value)
		{
			var activeGroup = (value === undefined ? this.popupGroup : value);

			return XenForo._PopupMenuActiveGroup = activeGroup;
		},

		/**
		 * Returns the name of the globally active popup group
		 *
		 * @return string Active group name
		 */
		getActiveGroup: function()
		{
			return XenForo._PopupMenuActiveGroup;
		},

		/**
		 * Fade return the background color of unread items to the normal background
		 */
		highlightUnreadContent: function()
		{
			var $unreadContent = this.$menu.find('.Unread'),
				defaultBackground = null,
				counterSelector = null;

			if ($unreadContent.length)
			{
				defaultBackground = $unreadContent.data('defaultBackground');

				if (defaultBackground)
				{
					$unreadContent.css('backgroundColor', null);

					this.unreadDisplayTimeout = setTimeout($.context(function()
					{
						// removes an item specified by data-removeCounter on the menu element
						if (counterSelector = this.$menu.data('removeCounter'))
						{
							$(counterSelector).xfFadeOut(XenForo.speed.fast, function()
							{
								$(this).empty().remove();
							});
						}

						$unreadContent.animate({ backgroundColor: defaultBackground }, 2000, $.context(function()
						{
							$unreadContent.removeClass('Unread');
							this.$menu.trigger('UnreadDisplayComplete');
						}, this));
					}, this), 1000);
				}
			}
		}
	};

	// *********************************************************************

	/**
	 * Shows and hides global request pending progress indicators for AJAX calls.
	 *
	 * Binds to the global ajaxStart and ajaxStop jQuery events.
	 * Also binds to the PseudoAjaxStart and PseudoAjaxStop events,
	 * see XenForo.AutoInlineUploader
	 *
	 * Initialized by XenForo.init()
	 */
	XenForo.AjaxProgress = function()
	{
		var overlay = null,

		showOverlay = function()
		{
			// mini indicators
			$('.Progress, .xenForm .ctrlUnit.submitUnit dt').addClass('InProgress');

			// the overlay
			if (!overlay)
			{
				overlay = $('<div id="AjaxProgress" class="xenOverlay"><div class="content"><span class="close" /></div></div>')
					.appendTo('body')
					.overlay(
					{
						top: 0,
						speed: XenForo.speed.fast,
						oneInstance: false,
						closeOnClick: false,
						closeOnEsc: false
					}).data('overlay');
			}

			overlay.load();
		},

		hideOverlay = function()
		{
			// mini indicators
			$('.Progress, .xenForm .ctrlUnit.submitUnit dt')
				.removeClass('InProgress');

			// the overlay
			if (overlay && overlay.isOpened())
			{
				overlay.close();
			}
		};

		$(document).bind(
		{
			ajaxStart: function(e)
			{
				XenForo._AjaxProgress = true;
				showOverlay();
			},

			ajaxStop: function(e)
			{
				XenForo._AjaxProgress = false;
				hideOverlay();
			},

			PseudoAjaxStart: function(e)
			{
				showOverlay();
			},

			PseudoAjaxStop: function(e)
			{
				hideOverlay();
			}
		});

		if ($.browser.msie && $.browser.version < 7)
		{
			$(document).bind('scroll', function(e)
			{
				if (overlay && overlay.isOpened() && !overlay.getConf().fixed)
				{
					overlay.getOverlay().css('top', overlay.getConf().top + $(window).scrollTop());
				}
			});
		}
	};

	// *********************************************************************

	/**
	 * Handles the scrollable pagenav gadget, allowing selection of any page between 1 and (end)
	 * while showing only {range*2+1} pages plus first and last at once.
	 *
	 * @param jQuery .pageNav
	 */
	XenForo.PageNav = function($pageNav) { this.__construct($pageNav); };
	XenForo.PageNav.prototype =
	{
		__construct: function($pageNav)
		{
			var $scroller = $pageNav.find('.scrollable');
			if (!$scroller.length)
			{
				return false;
			}

			console.info('PageNav %o', $pageNav);

			this.start = parseInt($pageNav.data('start'));
			this.page  = parseInt($pageNav.data('page'));
			this.end   = parseInt($pageNav.data('end'));
			this.last  = parseInt($pageNav.data('last'));
			this.range = parseInt($pageNav.data('range'));
			this.size  = (this.range * 2 + 1);

			this.baseurl = $pageNav.data('baseurl');
			this.sentinel = $pageNav.data('sentinel');

			$scroller.scrollable(
			{
				speed: XenForo.speed.slow,
				easing: 'easeOutBounce',
				keyboard: false
			});

			this.api = $scroller.data('scrollable').onBeforeSeek($.context(this, 'beforeSeek'));

			this.$prevButton = $pageNav.find('.PageNavPrev').click($.context(this, 'prevPage'));
			this.$nextButton = $pageNav.find('.PageNavNext').click($.context(this, 'nextPage'));

			this.setControlVisibility(this.api.getIndex(), 0);
		},

		/**
		 * Scrolls to the previous 'page' of page links, creating them if necessary
		 *
		 * @param Event e
		 */
		prevPage: function(e)
		{
			if (this.api.getIndex() == 0 && this.start > 2)
			{
				var i = 0,
					minPage = Math.max(2, (this.start - this.size));

				for (i = this.start - 1; i >= minPage; i--)
				{
					this.prepend(i);
				}

				this.start = minPage;
			}

			this.api.seekTo(Math.max(this.api.getIndex() - this.size, 0));
		},

		/**
		 * Scrolls to the next 'page' of page links, creating them if necessary
		 *
		 * @param Event e
		 */
		nextPage: function(e)
		{
			if ((this.api.getIndex() + 1 + 2 * this.size) > this.api.getSize() && this.end < this.last -1)
			{
				var i = 0,
					maxPage = Math.min(this.last - 1, this.end + this.size);

				for (i = this.end + 1; i <= maxPage; i++)
				{
					this.append(i);
				}

				this.end = maxPage;
			}

			this.api.seekTo(Math.min(this.api.getSize() - this.size, this.api.getIndex() + this.size));
		},

		/**
		 * Adds an additional page link to the beginning of the scrollable section, out of sight
		 *
		 * @param integer page
		 */
		prepend: function(page)
		{
			this.buildPageLink(page).prependTo(this.api.getItemWrap());

			this.api.next(0);
		},

		/**
		 * Adds an additional page link to the end of the scrollable section, out of sight
		 *
		 * @param integer page
		 */
		append: function(page)
		{
			this.buildPageLink(page).appendTo(this.api.getItemWrap());
		},

		/**
		 * Buids a single page link
		 *
		 * @param integer page
		 *
		 * @return jQuery page link html
		 */
		buildPageLink: function(page)
		{
			return $('<a />',
			{
				href:  this.buildPageUrl(page),
				text: page,
				className: (page > 999 ? 'gt999' : '')
			});
		},

		/**
		 * Converts the baseUrl into a page url by replacing the sentinel value
		 *
		 * @param integer page
		 *
		 * @return string page URL
		 */
		buildPageUrl: function(page)
		{
			return this.baseurl
				.replace(this.sentinel, page)
				.replace(escape(this.sentinel), page);
		},

		/**
		 * Runs immediately before the pagenav seeks to a new index,
		 * Toggles visibility of the next/prev controls based on whether they are needed or not
		 *
		 * @param jQuery Event e
		 * @param integer index
		 */
		beforeSeek: function(e, index)
		{
			this.setControlVisibility(index, XenForo.speed.fast);
		},

		/**
		 * Sets the visibility of the scroll controls, based on whether using them would do anything
		 * (hide the prev-page control if on the first page, etc.)
		 *
		 * @param integer Target index of the current scroll
		 *
		 * @param mixed Speed of animation
		 */
		setControlVisibility: function(index, speed)
		{
			if (index == 0 && this.start <= 2)
			{
				this.$prevButton.hide(speed);
			}
			else
			{
				this.$prevButton.show(speed);
			}

			if (this.api.getSize() - this.size <= index && this.end >= this.last - 1)
			{
				this.$nextButton.hide(speed);
			}
			else
			{
				this.$nextButton.show(speed);
			}
		}
	};

	// *********************************************************************

	/**
	 * Triggers an overlay from a regular link or button
	 * Triggers can provide an optional data-cacheOverlay attribute
	 * to allow multiple trigers to access the same overlay.
	 *
	 * @param jQuery .OverlayTrigger
	 */
	XenForo.OverlayTrigger = function($trigger, options) { this.__construct($trigger, options); };
	XenForo.OverlayTrigger.prototype =
	{
		__construct: function($trigger, options)
		{
			this.$trigger = $trigger.click($.context(this, 'show'));
			this.options = options;
		},

		/**
		 * Begins the process of loading and showing an overlay
		 *
		 * @param event e
		 */
		show: function(e)
		{
			var parentOverlay = this.$trigger.closest('.xenOverlay').data('overlay'),
				cache,
				options,
				isUserLink = (this.$trigger.is('.username, .avatar')),
				cardHref;

			if (!parseInt(XenForo._enableOverlays))
			{
				// if no overlays, use <a href /> by preference
				if (this.$trigger.attr('href'))
				{
					return true;
				}
				else if (this.$trigger.data('href'))
				{
					if (this.$trigger.closest('.AttachmentUploader, #AttachmentUploader').length == 0)
					{
						// open the overlay target as a regular link, unless it's the attachment uploader
						window.location = XenForo.canonicalizeUrl(this.$trigger.data('href'));
						return false;
					}
				}
				else
				{
					// can't do anything - should not happen
					console.warn('No alternative action found for OverlayTrigger %o', this.$trigger);
					return false;
				}
			}

			// abort if this is a username / avatar overlay with NoOverlay specified
			if (isUserLink && this.$trigger.hasClass('NoOverlay'))
			{
				return true;
			}

			// abort if the event has a modifier key
			if (e.ctrlKey || e.shiftKey || e.altKey)
			{
				return true;
			}

			// abort if the event is a middle or right-button click
			if (e.which > 1)
			{
				return true;
			}

			e.preventDefault();

			// TODO: this is a workaround for jQuery.Tools bug relating to opening a second overlay.
			if (parentOverlay && parentOverlay.isOpened())
			{
				parentOverlay.getTrigger().one('onClose', $.context(this, 'show'));
				parentOverlay.getConf().mask.closeSpeed = 0;
				parentOverlay.close();
				return;
			}

			if (!this.OverlayLoader)
			{
				options = (typeof this.options == 'object' ? this.options : {});
				options = $.extend(options, this.$trigger.data('overlayOptions'));

				cache = this.$trigger.data('cacheOverlay');
				if (cache !== undefined)
				{
					if (XenForo.isPositive(cache))
					{
						cache = true;
					}
					else
					{
						cache = false;
						options.onClose = $.context(this, 'deCache');
					}
				}

				if (isUserLink && !this.$trigger.hasClass('OverlayTrigger'))
				{
					if (!this.$trigger.data('cardUrl') && this.$trigger.attr('href'))
					{
						cardHref = this.$trigger.attr('href').replace(/#.*$/, '');
						if (cardHref.indexOf('?') >= 0)
						{
							cardHref += '&card=1';
						}
						else
						{
							cardHref += '?card=1';
						}

						this.$trigger.data('cardUrl', cardHref);
					}

					cache = true;
					options.speed = XenForo.speed.fast;
					options.effect = 'apple';
				}

				this.OverlayLoader = new XenForo.OverlayLoader(this.$trigger, cache, options);
				this.OverlayLoader.load();

				e.preventDefault();
				return true;
			}

			this.OverlayLoader.show();
		},

		deCache: function()
		{
			console.info('DeCache %o', this.OverlayLoader.overlay.getOverlay());
			this.OverlayLoader.overlay.getTrigger().removeData('overlay');
			this.OverlayLoader.overlay.getOverlay().empty().remove();
			delete(this.OverlayLoader);
		}
	};

	// *********************************************************************

	XenForo.LightBoxTrigger = function($link)
	{
		var containerSelector = '*[data-author]';

		new XenForo.OverlayTrigger($link.data('cacheOverlay', 1),
		{
			top: 15,
			speed: 1, // prevents the onLoad event being fired prematurely
			closeSpeed: 0,
			closeOnResize: true,
			mask:
			{
				color: 'rgb(0,0,0)',
				opacity: 0.6,
				loadSpeed: 0,
				closeSpeed: 0
			},
			onBeforeLoad: function(e)
			{
				if (typeof XenForo.LightBox == 'function')
				{
					if (XenForo._LightBoxObj === undefined)
					{
						XenForo._LightBoxObj = new XenForo.LightBox(this, containerSelector);
					}

					var $imageContainer = (parseInt(XenForo._lightBoxUniversal) ? $(document) : $link.closest(containerSelector));

					console.info('Opening LightBox for %o', $imageContainer);

					XenForo._LightBoxObj.setThumbStrip($imageContainer);
					XenForo._LightBoxObj.setImage(this.getTrigger().find('img:first'));

					$(document).triggerHandler('LightBoxOpening');
				}

				return true;
			},
			onLoad: function(e)
			{
				XenForo._LightBoxObj.setImageMaxHeight();

				return true;
			}
		});
	};

	// *********************************************************************

	XenForo.OverlayLoaderCache = {};

	/**
	 * Loads HTML and related external resources for an overlay
	 *
	 * @param jQuery Overlay trigger object
	 * @param boolean If true, cache the overlay HTML for this URL
	 * @param object Object of options for the overlay
	 */
	XenForo.OverlayLoader = function($trigger, cache, options)
	{
		this.__construct($trigger, options, cache);
	};
	XenForo.OverlayLoader.prototype =
	{
		__construct: function($trigger, options, cache)
		{
			this.$trigger = $trigger;
			this.cache = cache;
			this.options = options;
		},

		/**
		 * Initiates the loading of the overlay, or returns it from cache
		 *
		 * @param function Callback to run on successful load
		 */
		load: function(callback)
		{
			//TODO: ability to point to extant overlay HTML, rather than loading via AJAX
			this.href = this.$trigger.data('cardUrl') || this.$trigger.data('href') || this.$trigger.attr('href');

			if (!this.href)
			{
				console.warn('No overlay href found for control %o', this.$trigger);
				return false;
			}

			console.info('OverlayLoader for %s', this.href);

			this.callback = callback;

			if (this.cache && XenForo.OverlayLoaderCache[this.href])
			{
				this.createOverlay(XenForo.OverlayLoaderCache[this.href]);
			}
			else if (!this.xhr)
			{
				this.xhr = XenForo.ajax(
					this.href, '',
					$.context(this, 'loadSuccess'), { type: 'GET' }
				);
			}
		},

		/**
		 * Handles the returned ajaxdata from an overlay xhr load,
		 * Stores the template HTML then inits externals (js, css) loading
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		loadSuccess: function(ajaxData, textStatus)
		{
			delete(this.xhr);

			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			this.options.title = ajaxData.h1 || ajaxData.title;

			new XenForo.ExtLoader(ajaxData, $.context(this, 'createOverlay'));
		},

		/**
		 * Creates an overlay containing the appropriate template HTML,
		 * runs the callback specified in .load() and then shows the overlay.
		 *
		 * @param jQuery Cached $overlay object
		 */
		createOverlay: function($overlay)
		{
			var contents = ($overlay && $overlay.templateHtml) ? $overlay.templateHtml : $overlay;
			this.overlay = XenForo.createOverlay(this.$trigger, contents, this.options);

			if (this.cache)
			{
				XenForo.OverlayLoaderCache[this.href] = this.overlay.getOverlay();
			}

			if (typeof this.callback == 'function')
			{
				this.callback();
			}

			this.show();
		},

		/**
		 * Shows a finished overlay
		 */
		show: function()
		{
			if (!this.overlay)
			{
				console.warn('Attempted to call XenForo.OverlayLoader.show() for %s before overlay is created', this.href);
				this.load(this.callback);
				return;
			}

			this.overlay.load();
		}
	};

	// *********************************************************************

	XenForo.LoginBar = function($loginBar)
	{
		var $form = $('#login').appendTo($loginBar.find('.pageContent')),

		/**
		 * Opens the login form
		 *
		 * @param event
		 */
		openForm = function(e)
		{
			e.preventDefault();

			XenForo.chromeAutoFillFix($form);

			$form.xfSlideIn(XenForo.speed.slow, 'easeOutBack', function()
			{
				$('#LoginControl').select();

				$loginBar.expose($.extend(XenForo._overlayConfig.mask,
				{
					loadSpeed: XenForo.speed.slow,
					onBeforeClose: function(e)
					{
						closeForm(false, true);
						return true;
					}
				}));
			});
		},

		/**
		 * Closes the login form
		 *
		 * @param event
		 * @param boolean
		 */
		closeForm = function(e, isMaskClosing)
		{
			if (e) e.target.blur();

			$form.xfSlideOut(XenForo.speed.fast);

			if (!isMaskClosing && $.mask)
			{
				$.mask.close();
			}
		};

		/**
		 * Toggles the login form
		 */
		$('label[for="LoginControl"]').click(function(e)
		{
			e.preventDefault();

			if ($form._xfSlideWrapper(true) && $(e.target).closest('#login').length == 0)
			{
				closeForm(e);
			}
			else
			{
				$(XenForo.getPageScrollTagName()).scrollTop(0);

				openForm(e);
			}
		});

		/**
		 * Changes the text of the Log in / Sign up submit button depending on state
		 */
		$loginBar.delegate('input[name="register"]', 'click', function(e)
		{
			var $button = $form.find('input.button.primary'),
				register = $form.find('input[name="register"]:checked').val();

			$form.find('input.button.primary').val(register == '1'
				? $button.data('signupPhrase')
				: $button.data('loginPhrase'));
		});

		// close form if any .click elements within it are clicked
		$loginBar.delegate('.close', 'click', closeForm);
	};

	// *********************************************************************

	XenForo.QuickSearch = function($form)
	{
		$form.find('#QuickSearchQuery').focus(function(focusEvent)
		{
			console.log('Show quick search menu');

			$form.addClass('active');
			$form.find('.secondaryControls').slideDown(0);

			$(document).bind('click', function(clickEvent)
			{
				if (!$(clickEvent.target).parents('#QuickSearch').length)
				{
					console.log('Hide quick search menu');

					$(this).unbind(clickEvent);

					$form.find('.secondaryControls').slideUp(XenForo.speed.xfast, function()
					{
						$form.removeClass('active');
					});
				}
			});
		});
	};

	// *********************************************************************

	/**
	 * Wrapper for jQuery Tools Tooltip
	 *
	 * @param jQuery .Tooltip
	 */
	XenForo.Tooltip = function($element)
	{
		var offsetY = parseInt($element.data('offsetY')) || -6,
			offsetX = parseInt($element.data('offsetX')) + $element.innerWidth() * -1;

		if (!offsetX)
		{
			setTimeout(function()
			{
				offsetX = $element.innerWidth() * -1;

				$element.data('tooltip').getConf().offset = [ offsetY, offsetX ];
			}, 500);
		}

		$element.attr('title', XenForo.htmlspecialchars($element.attr('title'))).tooltip(
		{
			delay: 0,
			position: $element.data('position') || 'top right',
			offset: [ offsetY, offsetX ],
			tipClass: 'xenTooltip ' + String($element.data('tipClass')),
			layout: '<div><span class="arrow" /></div>'
		});
	};

	// *********************************************************************

	XenForo.StatusTooltip = function($element)
	{
		if ($element.attr('title'))
		{
			$element.attr('title', XenForo.htmlspecialchars($element.attr('title'))).tooltip(
			{
				effect: 'slide',
				slideOffset: 30,
				position: 'bottom right',
				offset: [ 10, 10 ],
				tipClass: 'xenTooltip statusTip',
				layout: '<div><span class="arrow" /></div>'
			});
		}
	};

	// *********************************************************************

	XenForo.NodeDescriptionTooltip = function($title)
	{
		var description = $title.data('description');

		if (description && $(description).length)
		{
			$(description)
				.addClass('xenTooltip nodeDescriptionTip')
				.appendTo('body')
				.append('<span class="arrow" />');

			$title.tooltip(
			{
				effect: 'slide',
				slideOffset: 30,
				offset: [ 30, 0 ],
				slideInSpeed: XenForo.speed.xfast,
				slideOutSpeed: 50 * XenForo._animationSpeedMultiplier,

				/*effect: 'fade',
				fadeInSpeed: XenForo.speed.xfast,
				fadeOutSpeed: XenForo.speed.xfast,*/

				predelay: 250,
				position: 'bottom right',
				tip: description
			});
			$title.click(function() { $(this).data('tooltip').hide(); });
		}
	};

	// *********************************************************************

	XenForo.AccountMenu = function($menu)
	{
		$menu.find('.submitUnit').hide();

		$menu.find('.StatusEditor').focus(function(e)
		{
			if ($menu.is(':visible'))
			{
				$menu.find('.submitUnit').show();
			}
		});
	};

	// *********************************************************************

	XenForo.FollowLink = function($link)
	{
		$link.click(function(e)
		{
			e.preventDefault();

			$link.get(0).blur();

			XenForo.ajax(
				$link.attr('href'),
				{ _xfConfirm: 1 },
				function (ajaxData, textStatus)
				{
					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					$link.xfFadeOut(XenForo.speed.fast, function()
					{
						$link
							.attr('href', ajaxData.linkUrl)
							.text(ajaxData.linkPhrase)
							.xfFadeIn(XenForo.speed.fast);
					});
				}
			);
		});
	};

	// *********************************************************************

	/**
	 * Allows relative hash links to smoothly scroll into place,
	 * Primarily used for 'x posted...' messages on bb code quote.
	 *
	 * @param jQuery a.AttributionLink
	 */
	XenForo.AttributionLink = function($link)
	{
		$link.click(function(e)
		{
			if ($(this.hash).length)
			{
				try
				{
					var hash = this.hash,
						top = $(this.hash).offset().top,
						scroller = XenForo.getPageScrollTagName();

					$(scroller).animate({ scrollTop: top }, XenForo.speed.normal, 'easeOutBack', function()
					{
						window.location.hash = hash;
					});
				}
				catch(e)
				{
					window.location.hash = this.hash;
				}

				e.preventDefault();
			}
		});
	};

	// *********************************************************************

	/**
	 * Allows clicks on one element to trigger the click event of another
	 *
	 * @param jQuery .ClickProxy[rel="{selectorForTarget}"]
	 *
	 * @return boolean false - prevents any direct action for the proxy element on click
	 */
	XenForo.ClickProxy = function($element)
	{
		$element.click(function(e)
		{
			$($element.attr('rel')).click();

			if (!$element.data('allowDefault'))
			{
				return false;
			}
		});
	};

	// *********************************************************************

	/**
	 * ReCaptcha wrapper
	 */
	XenForo.ReCaptcha = function($captcha) { this.__construct($captcha); };
	XenForo.ReCaptcha.prototype =
	{
		__construct: function($captcha)
		{
			if (XenForo.ReCaptcha.instance)
			{
				XenForo.ReCaptcha.instance.remove();
			}
			XenForo.ReCaptcha.instance = this;

			this.publicKey = $captcha.data('publicKey');
			if (!this.publicKey)
			{
				return;
			}

			$captcha.siblings('noscript').remove();

			$captcha.uniqueId();
			this.$captcha = $captcha;
			this.type = 'image';

			$captcha.find('.ReCaptchaReload').click($.context(this, 'reload'));
			$captcha.find('.ReCaptchaSwitch').click($.context(this, 'switchType'));

			this.load();
			$(window).unload($.context(this, 'remove'));

			$captcha.closest('form.AutoValidator').bind(
			{
				AutoValidationDataReceived: $.context(this, 'reload')
			});
		},

		load: function()
		{
			if (window.Recaptcha)
			{
				this.create();
			}
			else
			{
				var f = $.context(this, 'create'),
					delay = ($.browser.msie && $.browser.version <= 6 ? 250 : 0); // helps IE6 loading

				$.getScript('//www.google.com/recaptcha/api/js/recaptcha_ajax.js',
					function() { setTimeout(f, delay); }
				);
			}
		},

		create: function()
		{
			var $c = this.$captcha;

			window.Recaptcha.create(this.publicKey, $c.attr('id'),
			{
				theme: 'custom',
				callback: function() {
					$c.show();
					$('#ReCaptchaLoading').remove();
					// webkit seems to overwrite this value using the back button
					$('#recaptcha_challenge_field').val(window.Recaptcha.get_challenge());
				}
			});
		},

		reload: function(e)
		{
			if (!window.Recaptcha)
			{
				return;
			}

			if (!$(e.target).is('form'))
			{
				e.preventDefault();
			}
			window.Recaptcha.reload();
		},

		switchType: function(e)
		{
			e.preventDefault();
			this.type = (this.type == 'image' ? 'audio' : 'image');
			window.Recaptcha.switch_type(this.type);
		},

		remove: function()
		{
			this.$captcha.empty().remove();
			if (window.Recaptcha)
			{
				window.Recaptcha.destroy();
			}
		}
	};
	XenForo.ReCaptcha.instance = null;

	// *********************************************************************

	/**
	 * Loads a new (non-ReCaptcha) CAPTCHA upon verification failure
	 *
	 * @param jQuery #Captcha
	 */
	XenForo.Captcha = function($container)
	{
		$container.closest('form').one('AutoValidationError', function(e)
		{
			$container.fadeTo(XenForo.speed.fast, 0.5);

			XenForo.ajax($container.data('source'), {}, function(ajaxData, textStatus)
			{
				if (XenForo.hasResponseError(ajaxData))
				{
					return false;
				}

				if (XenForo.hasTemplateHtml(ajaxData))
				{
					$container.xfFadeOut(XenForo.speed.xfast, function()
					{
						$(ajaxData.templateHtml).xfInsert('replaceAll', $container, 'xfFadeIn', XenForo.speed.xfast);
					});
				}
			});
		});
	};

	// *********************************************************************

	/**
	 * Handles resizing of BB code [img] tags that would overflow the page
	 *
	 * @param jQuery img.bbCodeImage
	 */
	XenForo.BbCodeImage = function($image) { this.__construct($image); };
	XenForo.BbCodeImage.prototype =
	{
		__construct: function($image)
		{
			this.$image = $image;
			this.actualWidth = 0;

			if ($image.closest('a').length)
			{
				return;
			}

			$image
				.attr('title', XenForo.phrases.click_image_show_full_size_version || 'Show full size')
				.click($.context(this, 'toggleFullSize'));

			this.$image.tooltip(
			{
				effect: 'slide',
				slideOffset: 30,
				position: 'top center',
				offset: [ 45, 0 ],
				tipClass: 'xenTooltip bbCodeImageTip',
				onBeforeShow: $.context(this, 'isResized'),
				onShow: $.context(this, 'addTipClick')
			});

			if (!this.getImageWidth())
			{
				var src = $image.attr('src');

				$image.bind({
					load: $.context(this, 'getImageWidth')
				});
				$image.attr('src', '');
				$image.attr('src', src);
			}
		},

		/**
		 * Attempts to store the un-resized width of the image
		 *
		 * @return integer
		 */
		getImageWidth: function()
		{
			this.$image.css('max-width', 'none');
			this.actualWidth = this.$image.width();
			this.$image.css('max-width', '');

			//console.log('BB Code Image %o has width %s', this.$image, this.actualWidth);

			return this.actualWidth;
		},

		/**
		 * Shows and hides a full-size version of the image
		 *
		 * @param event
		 */
		toggleFullSize: function(e)
		{
			var currentWidth = this.$image.width(),
				offset, scale,
				scrollLeft, scrollTop,
				layerX, layerY,
				$fullSizeImage,
				speed = XenForo.speed.normal,
				easing = 'easeOutBack';

			//TODO: speed up response in slow browsers

			if (this.actualWidth > currentWidth)
			{
				offset = this.$image.offset();
				scale = this.actualWidth / currentWidth;
				layerX = e.pageX - offset.left;
				layerY = e.pageY - offset.top;

				$fullSizeImage = $('<img />', { src: this.$image.attr('src') })
					.addClass('bbCodeImageFullSize')
					.css('width', currentWidth)
					.css(offset)
					.click(function()
					{
						$(this).animate({ width: currentWidth }, 0, function()
						{
							$(this).remove();
						});

						$(XenForo.getPageScrollTagName()).animate(
						{
							scrollTop: offset.top,
							scrollLeft: 0
						}, 0);
					})
					.appendTo('body')
					.animate({ width: this.actualWidth }, speed, easing);

				// remove full size image if an overlay is about to open
				$(document).one('OverlayOpening', function()
				{
					$fullSizeImage.remove();
				});

				if (e.target == this.$image.get(0))
				{
					scrollLeft = offset.left + (e.pageX - offset.left) * scale - $(window).width() / 2;
					scrollTop = offset.top + (e.pageY - offset.top) * scale - $(window).height() / 2;
				}
				else
				{
					scrollLeft = offset.left + (this.actualWidth / 2) - $(window).width() / 2;
					scrollTop = offset.top + (this.$image.height() * scale / 2) - $(window).height() / 2;
				}

				$(XenForo.getPageScrollTagName()).animate(
				{
					scrollLeft: scrollLeft,
					scrollTop: scrollTop
				}, speed, easing, $.context(function()
				{
					this.$image.data('tooltip').hide();
				}, this));
			}
		},

		isResized: function(e)
		{
			var width = this.$image.width();

			if (!width)
			{
				return false;
			}

			if (this.getImageWidth() <= width)
			{
				//console.log('Image is not resized %o', this.$image);
				return false;
			}
		},

		addTipClick: function(e)
		{
			if (!this.tipClickAdded)
			{
				$(this.$image.data('tooltip').getTip()).click($.context(this, 'toggleFullSize'));
				this.tipClickAdded = true;
			}
		}
	};

	// *********************************************************************

	/**
	 * Wrapper for the jQuery Tools Tabs system
	 *
	 * @param jQuery .Tabs
	 */
	XenForo.Tabs = function($tabContainer) { this.__construct($tabContainer); };
	XenForo.Tabs.prototype =
	{
		__construct: function($tabContainer)
		{
			// var useHistory = XenForo.isPositive($tabContainer.data('history'));
			// TODO: disabled until base tag issues are resolved
			var useHistory = false;

			this.$tabContainer = $tabContainer;
			this.$panes = $($tabContainer.data('panes'));

			/*if (useHistory)
			{
				$tabContainer.find('a[href]').each(function()
				{
					var $this = $(this), hrefParts = $this.attr('href').split('#');
					if (hrefParts[1] && location.pathname == hrefParts[0])
					{
						$this.attr('href', '#' + hrefParts[1]);
					}
				});
			}*/

			$tabContainer.tabs(this.$panes, {
				current: 'active',
				history: useHistory,
				onBeforeClick: $.context(this, 'onBeforeClick')
			});
			this.api = $tabContainer.data('tabs');
		},

		getCurrentTab: function()
		{
			return this.api.getIndex();
		},

		click: function(index)
		{
			this.api.click(index);
		},

		onBeforeClick: function(e, index)
		{
			this.$tabContainer.children().each(function(i)
			{
				if (index == i)
				{
					$(this).addClass('active');
				}
				else
				{
					$(this).removeClass('active');
				}
			});

			var $pane = $(this.$panes.get(index)),
				loadUrl = $pane.data('loadUrl');

			if (loadUrl)
			{
				$pane.data('loadUrl', '');

				XenForo.ajax(loadUrl, {}, function(ajaxData)
				{
					if (XenForo.hasTemplateHtml(ajaxData) || XenForo.hasTemplateHtml(ajaxData, 'message'))
					{
						new XenForo.ExtLoader(ajaxData, function(ajaxData)
						{
							var $html;

							if (ajaxData.templateHtml)
							{
								$html = $(ajaxData.templateHtml);
							}
							else if (ajaxData.message)
							{
								$html = $('<div />').html(ajaxData.message);
							}

							$pane.html('');
							if ($html)
							{
								$html.xfInsert('appendTo', $pane, 'xfFadeIn', 0);
							}
						});
					}
					else if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}
				}, {type: 'GET'});
			}
		}
	};

	// *********************************************************************

	/**
	 * Handles a like / unlike link being clicked
	 *
	 * @param jQuery a.LikeLink
	 */
	XenForo.LikeLink = function($link)
	{
		$link.click(function(e)
		{
			e.preventDefault();

			var $link = $(this);

			XenForo.ajax(this.href, {}, function(ajaxData, textStatus)
			{
				if (XenForo.hasResponseError(ajaxData))
				{
					return false;
				}

				$link.stop(true, true);

				if (ajaxData.term) // term = Like / Unlike
				{
					$link.find('.LikeLabel').html(ajaxData.term);

					if (ajaxData.cssClasses)
					{
						$.each(ajaxData.cssClasses, function(className, action)
						{
							$link[action == '+' ? 'addClass' : 'removeClass'](className);
						});
					}
				}

				if (ajaxData.templateHtml === '')
				{
					$($link.data('container')).xfFadeUp(XenForo.speed.fast, function()
					{
						$(this).empty().xfFadeDown(0);
					});
				}
				else
				{
					var $container    = $($link.data('container')),
						$likeText     = $container.find('.LikeText'),
						$templateHtml = $(ajaxData.templateHtml);

					if ($likeText.length)
					{
						// we already have the likes_summary template in place, so just replace the text
						$likeText.xfFadeOut(50, function()
						{
							var textContainer = this.parentNode;

							$(this).remove();

							$templateHtml.find('.LikeText').xfInsert('appendTo', textContainer, 'xfFadeIn', 50);
						});
					}
					else
					{
						new XenForo.ExtLoader(ajaxData, function()
						{
							$templateHtml.xfInsert('appendTo', $container);
						});
					}
				}
			});
		});
	};

	// *********************************************************************

	XenForo.Facebook =
	{
		initialized: false,
		appId: '',
		fbUid: 0,
		fbSession: {},
		locale: 'en-US',

		init: function()
		{
			XenForo.Facebook.initialized = true;

			$(document.body).append($('<div id="fb-root" />'));
			FB.init(
			{
				appId: XenForo.Facebook.appId,
				xfbml: true,
				channelUrl: XenForo.canonicalizeUrl('fb_channel.php?l=' + XenForo.Facebook.locale)
			});
			FB.Event.subscribe('auth.sessionChange', XenForo.Facebook.sessionChange);
			FB.getLoginStatus(XenForo.Facebook.sessionChange);

			if (XenForo.visitor.user_id)
			{
				$('a.LogOut').click(XenForo.Facebook.eLogOutClick);
			}
		},

		start: function()
		{
			var cookieUid = $.getCookie('fbUid');
			if (cookieUid && cookieUid.length)
			{
				XenForo.Facebook.fbUid = parseInt(cookieUid, 10);
			}

			if (!XenForo.Facebook.forceInit && (!XenForo.Facebook.appId || !XenForo.Facebook.fbUid))
			{
				return;
			}

			XenForo.Facebook.load();
		},

		load: function()
		{
			XenForo.Facebook.locale = $('html').attr('lang').replace('-', '_');
			if (!XenForo.Facebook.locale)
			{
				XenForo.Facebook.locale = 'en_US';
			}

			var e = document.createElement('script'),
				locale = XenForo.Facebook.locale.replace('-', '_'); // TODO: which locale are we using?
			e.src = document.location.protocol + '//connect.facebook.net/' + XenForo.Facebook.locale + '/all.js';
			e.async = true;

			window.fbAsyncInit = XenForo.Facebook.init;
			document.getElementsByTagName('head')[0].appendChild(e);
		},

		sessionChange: function(response)
		{
			if (!XenForo.Facebook.fbUid)
			{
				return;
			}

			var session = response.session, visitor = XenForo.visitor;

			if (session && !visitor.user_id)
			{
				// facebook user, connect!
				XenForo.alert(XenForo.phrases.logging_in + '...', '', 8000);
				setTimeout(function() {
					window.location = XenForo.canonicalizeUrl(
						'index.php?register/facebook&t=' + escape(session.access_token)
						+ '&redirect=' + escape(window.location)
					);
				}, 250);
			}
			else if (!session && visitor.user_id)
			{
				// facebook user that is no longer logged in - log out
				XenForo.Facebook.logout();
			}

			XenForo.Facebook.fbSession = session;
		},

		logout: function()
		{
			var location = $('a.LogOut').attr('href');
			location += (location.indexOf('?') >= 0 ? '&' : '?') + 'redirect=' + escape(window.location);
			window.location = XenForo.canonicalizeUrl(location);
		},

		eLogOutClick: function(e)
		{
			FB.logout(XenForo.Facebook.logout);
		}
	};

	// *********************************************************************
	/**
	 * Turns an :input into a Prompt
	 *
	 * @param {Object} :input[placeholder]
	 */
	XenForo.Prompt = function($input)
	{
		this.__construct($input);
	};
	if ('placeholder' in document.createElement('input'))
	{
		// native placeholder support
		XenForo.Prompt.prototype =
		{
			__construct: function($input)
			{
				this.$input = $input;
			},

			isEmpty: function()
			{
				return (this.$input.strval() === '');
			},

			val: function(value, focus)
			{
				if (value === undefined)
				{
					return this.$input.val();
				}
				else
				{
					if (focus)
					{
						this.$input.focus();
					}

					return this.$input.val(value);
				}
			}
		};
	}
	else
	{
		// emulate placeholder support
		XenForo.Prompt.prototype =
		{
			__construct: function($input)
			{
				console.log('Emulating placeholder behaviour for %o', $input);

				this.placeholder = $input.attr('placeholder');

				this.$input = $input.bind(
				{
					focus: $.context(this, 'setValueMode'),
					blur:  $.context(this, 'setPromptMode')
				});

				this.$input.closest('form').bind(
				{
					submit: $.context(this, 'eFormSubmit'),
					AutoValidationBeforeSubmit: $.context(this, 'eFormSubmit'),
					AutoValidationComplete: $.context(this, 'eFormSubmitted')
				});

				this.setPromptMode();
			},

			/**
			 * If the prompt box contains no text, or contains the prompt text (only) it is 'empty'
			 *
			 * @return boolean
			 */
			isEmpty: function()
			{
				var val = this.$input.val();

				return (val === '' || val == this.placeholder);
			},

			/**
			 * When exiting the prompt box, update its contents if necessary
			 */
			setPromptMode: function()
			{
				if (this.isEmpty())
				{
					this.$input.val(this.placeholder).addClass('prompt');
				}
			},

			/**
			 * When entering the prompt box, clear its contents if it is 'empty'
			 */
			setValueMode: function()
			{
				if (this.isEmpty())
				{
					this.$input.val('').removeClass('prompt').select();
				}
			},

			/**
			 * Gets or sets the value of the prompt and puts it into the correct mode for its contents
			 *
			 * @param string value
			 */
			val: function(value, focus)
			{
				// get value
				if (value === undefined)
				{
					if (this.isEmpty())
					{
						return '';
					}
					else
					{
						return this.$input.val();
					}
				}

				// clear value
				else if (value === '')
				{
					this.$input.val('');

					if (focus === undefined)
					{
						this.setPromptMode();
					}
				}

				// set value
				else
				{
					this.setValueMode();
					this.$input.val(value);
				}
			},

			/**
			 * When the form is submitted, empty the prompt box if it is 'empty'
			 *
			 * @return boolean true;
			 */
			eFormSubmit: function()
			{
				if (this.isEmpty())
				{
					this.$input.val('');
				}

				return true;
			},

			/**
			 * Fires immediately after the form has sent its AJAX submission
			 */
			eFormSubmitted: function()
			{
				this.setPromptMode();
			}
		};
	}

	// *********************************************************************

	/**
	 * Turn an input[type="text"] into a Combo Box
	 *
	 * @param {Object} $input
	 */
	XenForo.ComboBox = function($input) { this.__construct($input); };
	XenForo.ComboBox.prototype =
	{
		__construct: function($input)
		{
			this.maxSelectSize = 7;

			var inputWidth = $input.width(),
				wrapperId = XenForo.uniqueId();

			// register input:text
			this.$input = $input;

			console.info('Value: %s', this.$input.val());

			// register the select menu
			this.$menu = this.fetchMenu();

			console.log('Menu items: %o', this.$menu);

			// set up the input:text field
			this.$input
				//.attr('autocomplete', 'off')
				.width(inputWidth - 17 - 3)
				.css('paddingRight', 3)
				.wrap('<div class="inputWrapper" id="' + wrapperId + '">'
					+ '<table><tr><td></td><td>'
					+ '<input type="button" class="down" value="v" tabindex="-1" />'
					+ '</td></tr></table></div>');

			// input events
			this.$input
				.focus($.context(this, 'eFocusInput'))
				.blur($.context(this, 'eBlurInput'))
				.keyup($.context(this, 'eKeyupInput'));

			// cache the wrapper
			this.$wrapper = $('#' + wrapperId).width(inputWidth);

			// set up the button
			$('input:button', this.$wrapper)
				.focus($.context(this, 'eFocusButton'))
				.click($.context(this, 'eClickButton')); // TODO: Multiple fast clicks can leave the menu open and stranded. Resolve?

			// set up the menu
			this.$menu
				.addClass('combobox')
				.attr('size', Math.min(this.maxSelectSize, $('option, optgroup', this.$menu).length));

			// menu events
			this.$menu
				.click($.context(this, 'eClickMenu'))
				.keydown($.context(this, 'eKeydownMenu'))
				.blur($.context(this, 'eBlurMenu'));

			this.$menu.open = false;
		},

		/**
		 * Fetches a menu that already exists and is a sibling of the input:text
		 *
		 * @return object Any ComboBox siblings
		 */
		fetchMenu: function()
		{
			return this.$input.siblings('select.ComboBox');
		},

		/**
		 * Shows the menu attached to the ComboBox
		 */
		showMenu: function()
		{
			this.$menu
				.xfFadeIn(XenForo.speed.fast, $.context(function()
				{
					this.$menu.focus();
					this.$wrapper.addClass('pseudoFocus');
				}, this))
				.open = true;
		},

		/**
		 * Hides the menu attached to the ComboBox
		 *
		 * @param boolean selectInput Select the input box
		 */
		hideMenu: function(selectInput)
		{
			this.$menu.xfFadeOut(XenForo.speed.normal, $.context(function()
			{
				this.$menu.open = false;
			}, this));

			if (selectInput)
			{
				this.$input.select();
			}
			else
			{
				this.$wrapper.removeClass('pseudoFocus');
			}
		},

		/**
		 * Handles the input gaining focus.
		 *
		 * Attaches the 'pseudofocus' class to the input wrapper
		 * in order to make it appear like a selected input.
		 *
		 * @param Event e
		 */
		eFocusInput: function(e)
		{
			this.$wrapper.addClass('pseudoFocus');
		},

		/**
		 * Event handler for the input losing focus.
		 *
		 * Removes the 'pseudofocus' class from the input wrapper
		 * in order to make it appear like a normal input.
		 *
		 * @param Event e
		 */
		eBlurInput: function(e)
		{
			this.$wrapper.removeClass('pseudoFocus');
		},

		/**
		 * Handles key presses on the input.
		 *
		 * If an up or down arrow is detected, show the menu.
		 *
		 * @param Event e
		 */
		eKeyupInput: function(e)
		{
			switch (e.which)
			{
				case 38: // up
				case 40: // down
				{
					this.showMenu();
					break;
				}
			}
		},

		/**
		 * Handles focus events for the show/hide menu button.
		 *
		 * Don't allow the button to be focused - return focus to the input.
		 *
		 * @param Event e
		 *
		 * @return boolean false
		 */
		eFocusButton: function(e)
		{
			this.eFocusInput(e);
			return false;
		},

		/**
		 * Handles click events on the show/hide menu button.
		 *
		 * Either opens the menu, or else focuses and selects the input.
		 *
		 * @param Event e
		 *
		 * @return boolean false
		 */
		eClickButton: function(e)
		{
			if (!this.$menu.open)
			{
				this.showMenu();
			}
			else
			{
				this.$input.focus();
				this.$input.select();
			}

			return false;
		},

		/**
		 * Handles click events on the menu.
		 *
		 * Sets the value of the input to the currently selected item in the menu,
		 * then hides the menu.
		 *
		 * @param Event e
		 */
		eClickMenu: function(e)
		{
			this.$input.val(this.$menu.val());
			this.hideMenu(true);
		},

		/**
		 * Handles the menu losing focus, which hides the menu.
		 *
		 * @param Event e
		 */
		eBlurMenu: function(e)
		{
			this.hideMenu(false);
		},

		/**
		 * Handles key events on the menu.
		 *
		 * [Return], [Enter] or [Space] will select/activate the
		 * current menu item, while [Escape] will hide the menu.
		 *
		 * @param Event e
		 */
		eKeydownMenu: function(e)
		{
			switch (e.which)
			{
				case 27: // escape
				{
					this.hideMenu(true);
					break;
				}

				case 13: // return / enter
				case 32: // space
				{
					e.preventDefault();
					this.$input.val(this.$menu.val());
					this.hideMenu(true);
					break;
				}
			}
		}
	};

	// *********************************************************************

	/**
	 * Turn in input:text.SpinBox into a Spin Box
	 * Requires a parameter class of 'SpinBox' and an attribute of 'data-step' with a numeric step value.
	 * data-max and data-min parameters are optional.
	 *
	 * @param {Object} $input
	 */
	XenForo.SpinBox = function($input) { this.__construct($input); };
	XenForo.SpinBox.prototype =
	{
		__construct: function($input)
		{
			var param,
				inputWidth,
				$plusButton,
				$minusButton;

			if ($input.attr('step') === undefined)
			{
				console.warn('ERROR: No data-step attribute specified for spinbox.');
				return;
			}

			this.parameters = { step: null, min:  null, max:  null };

			for (param in this.parameters)
			{
				if ($input.attr(param) === undefined)
				{
					delete this.parameters[param];
				}
				else
				{
					this.parameters[param] = parseFloat($input.attr(param));
				}
			}

			inputWidth = $input.width();

			$plusButton  = $('<input type="button" class="button spinBoxButton up" value="+" data-plusMinus="+" tabindex="-1" />')
				.insertAfter($input)
				.focus($.context(this, 'eFocusButton'))
				.click($.context(this, 'eClickButton'))
				.mousedown($.context(this, 'eMousedownButton'))
				.mouseup($.context(this, 'eMouseupButton'));
			$minusButton = $('<input type="button" class="button spinBoxButton down" value="-" data-plusMinus="-" tabindex="-1" />')
				.insertAfter($plusButton)
				.focus($.context(this, 'eFocusButton'))
				.click($.context(this, 'eClickButton'))
				.mousedown($.context(this, 'eMousedownButton'))
				.mouseup($.context(this, 'eMouseupButton'));

			// set up the input
			this.$input = $input
				.attr('autocomplete', 'off')
				.blur($.context(this, 'eBlurInput'))
				.keyup($.context(this, 'eKeyupInput'));

			// force validation to occur on form submit
			this.$input.closest('form').bind('submit', $.context(this, 'eBlurInput'));
		},

		/**
		 * Returns the (numeric) value of the spinbox
		 *
		 * @return float
		 */
		getValue: function()
		{
			var value = parseFloat(this.$input.val());

			value = (isNaN(value)) ? parseFloat(this.$input.val().replace(/[^0-9.]/g, '')) : value;

			return (isNaN(value) ? 0 : value);
		},

		/**
		 * Asserts that the value of the spinbox is within defined min and max parameters.
		 *
		 * @param float Spinbox value
		 *
		 * @return float
		 */
		constrain: function(value)
		{
			if (this.parameters.min !== undefined && value < this.parameters.min)
			{
				console.warn('Minimum value for SpinBox = %s\n %o', this.parameters.min, this.$input);
				return this.parameters.min;
			}
			else if (this.parameters.max !== undefined && value > this.parameters.max)
			{
				console.warn('Maximum value for SpinBox = %s\n %o', this.parameters.max, this.$input);
				return this.parameters.max;
			}
			else
			{
				return value;
			}
		},

		/**
		 * Takes the value of the SpinBox input to the nearest step.
		 *
		 * @param string +/- Take the value up or down
		 */
		stepValue: function(plusMinus)
		{
			var val = this.getValue(),
				mod = val % this.parameters.step,
				posStep = (plusMinus == '+'),
				newVal = val - mod;

			if (!mod || (posStep && mod > 0) || (!posStep && mod < 0))
			{
				newVal = newVal + this.parameters.step * (posStep ? 1 : -1);
			}

			this.$input.val(this.constrain(newVal));
		},

		/**
		 * Handles the input being blurred. Removes the 'pseudofocus' class and constrains the spinbox value.
		 *
		 * @param Event e
		 */
		eBlurInput: function(e)
		{
			this.$input.val(this.constrain(this.getValue()));
		},

		/**
		 * Handles key events on the spinbox input. Up and down arrows perform a value step.
		 *
		 * @param Event e
		 *
		 * @return false|undefined
		 */
		eKeyupInput: function(e)
		{
			switch (e.which)
			{
				case 38: // up
				{
					this.stepValue('+');
					this.$input.select();
					return false;
				}

				case 40: // down
				{
					this.stepValue('-');
					this.$input.select();
					return false;
				}
			}
		},

		/**
		 * Handles focus events on spinbox buttons.
		 *
		 * Does not allow buttons to keep focus, returns focus to the input.
		 *
		 * @param Event e
		 *
		 * @return boolean false
		 */
		eFocusButton: function(e)
		{
			return false;
		},

		/**
		 * Handles click events on spinbox buttons.
		 *
		 * The buttons are assumed to have data-plusMinus attributes of + or -
		 *
		 * @param Event e
		 */
		eClickButton: function(e)
		{
			this.stepValue($(e.target).data('plusMinus'));
			this.$input.focus();
			this.$input.select();
		},

		/**
		 * Handles a mouse-down event on a spinbox button in order to allow rapid repeats.
		 *
		 * @param Event e
		 */
		eMousedownButton: function(e)
		{
			this.eMouseupButton(e); // don't orphan

			this.holdTimeout = setTimeout(
				$.context(function()
				{
					this.holdInterval = setInterval($.context(function() { this.stepValue(e.target.value); }, this), 75);
				}, this
			), 500);
		},

		/**
		 * Handles a mouse-up event on a spinbox button in order to halt rapid repeats.
		 *
		 * @param Event e
		 */
		eMouseupButton: function(e)
		{
			clearTimeout(this.holdTimeout);
			clearInterval(this.holdInterval);
		}
	};

	// *********************************************************************

	/**
	 * Allows an input:checkbox or input:radio to disable subsidiary controls
	 * based on its own state
	 *
	 * @param {Object} $input
	 */
	XenForo.Disabler = function($input)
	{
		/**
		 * Sets the disabled state of form elements being controlled by this disabler.
		 *
		 * @param Event e
		 * @param boolean If true, this is the initialization call
		 */
		var setStatus = function(e, init)
		{
			console.info('Disabler %o for child container: %o', $input, $childContainer);

			var $childControls = $childContainer.find('input, select, textarea, button, .inputWrapper'),
				speed = init ? 0 : XenForo.speed.fast,
				select = function(e)
				{
					$childContainer.find('input, select, textarea').first().focus().select();
				};

			if ($input.is(':checked:enabled'))
			{
				$childContainer
					.removeAttr('disabled')
					.removeClass('disabled')
					.trigger('DisablerDisabled');

				$childControls
					.removeAttr('disabled')
					.removeClass('disabled');

				if ($input.hasClass('Hider'))
				{
					$childContainer.xfFadeDown(speed, init ? null : select);
				}
				else if (!init)
				{
					select.call();
				}
			}
			else
			{
				if ($input.hasClass('Hider'))
				{
					$childContainer.xfFadeUp(speed, null, XenForo.speed.fast, 'easeInBack');
				}

				$childContainer
					.attr('disabled', true)
					.addClass('disabled')
					.trigger('DisablerEnabled');

				$childControls
					.attr('disabled', true)
					.addClass('disabled')
					.each(function(i, ctrl)
					{
						var $ctrl = $(ctrl),
							disabledVal = $ctrl.data('disabled');

						if (disabledVal !== null && typeof(disabledVal) != 'undefined')
						{
							$ctrl.val(disabledVal);
						}
					});
			}
		},

		$childContainer = $('#' + $input.attr('id') + '_Disabler'),

		$form = $input.closest('form');

		if ($input.is(':radio'))
		{
			$form.find('input:radio[name="' + $input.fieldName() + '"]').click(setStatus);
		}
		else
		{
			$input.click(setStatus);
		}

		$form.bind('reset', setStatus);

		setStatus(null, true);
	};

	// *********************************************************************

	/**
	 * Quick way to check or toggle all specified items. Works in one of two ways:
	 * 1) If the control is a checkbox, a data-target attribute specified a jQuery
	 * 	selector for a container within which all checkboxes will be toggled
	 * 2) If the control is something else, the data-target attribute specifies a
	 * 	jQuery selector for the elements themselves that will be selected.
	 *
	 *  @param jQuery .CheckAll
	 */
	XenForo.CheckAll = function($control)
	{
		if ($control.is(':checkbox'))
		{
			$control.click(function(e)
			{
				var target = $control.data('target');

				if (!target)
				{
					target = $control.closest('form');
				}

				$(target).find('input:checkbox').attr('checked', e.target.checked);
			});
		}
		else
		{
			$control.click(function(e)
			{
				var target = $control.data('target');

				if (target)
				{
					$(target).attr('checked', true);
				}
			});
		}
	};

	// *********************************************************************

	/**
	 * Converts a checkbox/radio plus label into a toggle button.
	 *
	 * @param jQuery label.ToggleButton
	 */
	XenForo.ToggleButton = function($label)
	{
		var $button,

		setCheckedClasses = function()
		{
			$button[($input.is(':checked') ? 'addClass' : 'removeClass')]('checked');
		},

		$input = $label.hide().find('input:checkbox, input:radio').first(),

		$list = $label.closest('ul, ol').bind('toggleButtonClick', setCheckedClasses);

		if (!$input.length && $label.attr('for'))
		{
			$input = $('#' + $label.attr('for'));
		}

		$button = $('<a />')
			.text($label.attr('title') || $label.text())
			.insertBefore($label)
			.attr(
			{
				'class': 'button ' + $label.attr('class'),
				'title': $label.text()
			})
			.click(function(e)
			{
				$input.click();

				if ($list.length)
				{
					$list.triggerHandler('toggleButtonClick');
				}
				else
				{
					setCheckedClasses();
				}

				return false;
			});

		$label.closest('form').bind('reset', function(e)
		{
			setTimeout(setCheckedClasses, 100);
		});

		setCheckedClasses();
	};

	// *********************************************************************

	/**
	 * Allows files to be uploaded in-place without a page refresh
	 *
	 * @param jQuery form.AutoInlineUploader
	 */
	XenForo.AutoInlineUploader = function($form)
	{
		/**
		 * Fires when the contents of an input:file change.
		 * Submits the form into a temporary iframe.
		 *
		 * @param event e
		 */
		var $uploader = $form.find('input:file').change(function(e)
		{
			if ($(e.target).val() != '')
			{
				var $iframe,
					$hiddenInput;

				$iframe = $('<iframe src="about:blank" style="display:none; background-color: white" name="AutoInlineUploader"></iframe>')
					.insertAfter($(e.target))
					.load(function(e)
					{
						var $iframe = $(e.target),
							ajaxData = $iframe.contents().text(),
							eComplete = null;

						// Opera fires this function when it's not done with no data
						if (!ajaxData)
						{
							return false;
						}

						// alert the global progress indicator that the transfer is complete
						$(document).trigger('PseudoAjaxStop');

						$uploader = $uploaderOrig.clone(true).replaceAll($uploader);

						// removing the iframe after a delay to prevent Firefox' progress indicator staying active
						setTimeout(function() { $iframe.remove(); }, 500);

						try
						{
							ajaxData = $.parseJSON(ajaxData);
							console.info('Inline file upload completed successfully. Data: %o', ajaxData);
						}
						catch(e)
						{
							console.error(ajaxData);
							return false;
						}

						if (XenForo.hasResponseError(ajaxData))
						{
							return false;
						}

						$('input:submit', this.$form).removeAttr('disabled');

						eComplete = new $.Event('AutoInlineUploadComplete');
						eComplete.$form = $form;
						eComplete.ajaxData = ajaxData;

						$form.trigger(eComplete);

						console.log(ajaxData);

						if (!eComplete.isDefaultPrevented() && ajaxData.message)
						{
							XenForo.alert(ajaxData.message, '', 2500);
						}
					});

				$hiddenInput = $('<span>'
					+ '<input type="hidden" name="_xfNoRedirect" value="1" />'
					+ '<input type="hidden" name="_xfResponseType" value="json-text" />'
					+ '<input type="hidden" name="_xfUploader" value="1" />'
					+ '</span>')
					.appendTo($form);

				$form.attr('target', 'AutoInlineUploader')
					.submit()
					.trigger('AutoInlineUploadStart');

				$hiddenInput.remove();

				// fire the event that will be caught by the global progress indicator
				$(document).trigger('PseudoAjaxStart');

				$form.find('input:submit').attr('disabled', 'disabled');
			}
		}),

		$uploaderOrig = $uploader.clone(true);
	};

	// *********************************************************************

	XenForo.MultiSubmitFix = function($form)
	{
		var selector = 'input:submit, input.PreviewButton, input.DisableOnSubmit',

		enable = function()
		{
			$form.trigger('EnableSubmitButtons').find(selector)
				.removeClass('disabled')
				.removeAttr('disabled');
		};

		/**
		 * Workaround for a Firefox issue that prevents resubmission after back button,
		 * however the workaround triggers a webkit rendering bug.
		 */
		if (!$.browser.webkit)
		{
			$(window).unload(enable);
		}

		$form.data('MultiSubmitEnable', enable).submit(function(e)
		{
			setTimeout(function()
			{
				$form.trigger('DisableSubmitButtons').find(selector)
					.attr('disabled', true)
					.addClass('disabled');
			}, 0);

			setTimeout(enable, 4000);
		});

		return enable;
	};

	// *********************************************************************

	/**
	 * Handler for radio/checkbox controls that cause the form to submit when they are altered
	 *
	 * @param jQuery input:radio.SubmitOnChange, input:checkbox.SubmitOnChange
	 */
	XenForo.SubmitOnChange = function($input)
	{
		$input.click(function(e)
		{
			clearTimeout(e.target.form.submitTimeout);

			e.target.form.submitTimeout = setTimeout(function()
			{
				$(e.target).closest('form').submit();
			}, 500);
		});
	};

	// *********************************************************************

	/**
	 * Handler for automatic AJAX form validation and error management
	 *
	 * Forms to be auto-validated require the following attributes:
	 *
	 * * data-fieldValidatorUrl: URL of a JSON-returning validator for a single field, using _POST keys of 'name' and 'value'
	 * * data-optInOut: (Optional - default = OptOut) Either OptIn or OptOut, depending on the validation mode. Fields with a class of OptIn are included in opt-in mode, while those with OptOut are excluded in opt-out mode.
	 * * data-exitUrl: (Optional - no default) If defined, any form reset event will redirect to this URL.
	 * * data-existingDataKey: (Optional) Specifies the primary key of the data being manipulated. If this is not present, a hidden input with class="ExistingDataKey" is searched for.
	 * * data-redirect: (Optional) If set, the browser will redirect to the returned _redirectTarget from the ajaxData response after validation
	 *
	 * @param jQuery form.AutoValidator
	 */
	XenForo.AutoValidator = function($form) { this.__construct($form); };
	XenForo.AutoValidator.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form.bind(
			{
				submit: $.context(this, 'ajaxSave'),
				reset:  $.context(this, 'formReset')
			});

			this.$form.find('input[type="submit"]').click($.context(this, 'setClickedSubmit'));

			this.fieldValidatorUrl = this.$form.data('fieldValidatorUrl');
			this.optInMode = this.$form.data('optInOut') || 'optOut';
			this.ajaxSubmit = (XenForo.isPositive(this.$form.data('normalSubmit')) ? false : true);

			this.fieldValidationTimeouts = {};
			this.fieldValidationRequests = {};
		},

		/**
		 * Fetches the value of the form's existing data key.
		 *
		 * This could either be a data-existingDataKey attribute on the form itself,
		 * or a hidden input with class 'ExistingDataKey'
		 *
		 * @return string
		 */
		getExistingDataKey: function()
		{
			var val = this.$form.find('input.ExistingDataKey, select.ExistingDataKey, textarea.ExistingDataKey, button.ExistingDataKey').val();
			if (val === undefined)
			{
				val = this.$form.data('existingDataKey');
				if (val === undefined)
				{
					val = '';
				}
			}

			return val;
		},

		/**
		 * Intercepts form reset events.
		 * If the form specifies a data-exitUrl, the browser will navigate there before resetting the form.
		 *
		 * @param event e
		 */
		formReset: function(e)
		{
			var exitUrl = this.$form.data('exitUrl');

			if (exitUrl)
			{
				window.location = XenForo.canonicalizeUrl(exitUrl);
			}
		},

		/**
		 * Fires whenever a submit button is clicked, in order to store the clicked control
		 *
		 * @param event e
		 */
		setClickedSubmit: function(e)
		{
			this.$form.data('clickedSubmitButton', e.target);
		},

		/**
		 * Intercepts form submit events.
		 * Attempts to save the form with AJAX, after cancelling any pending validation tasks.
		 *
		 * @param event e
		 *
		 * @return boolean false
		 */
		ajaxSave: function(e)
		{
			if (!this.ajaxSubmit || !XenForo._enableAjaxSubmit)
			{
				// do normal validation
				return true;
			}

			this.abortPendingFieldValidation();

			var clickedSubmitButton = this.$form.data('clickedSubmitButton'),
				serialized,
				$clickedSubmitButton,

			/**
			 * Event listeners for this event can:
			 * 	e.preventSubmit = true; to prevent any submission
			 * 	e.preventDefault(); to disable ajax sending
			 */
			eDataSend = $.Event('AutoValidationBeforeSubmit');
				eDataSend.formAction = this.$form.attr('action');
				eDataSend.clickedSubmitButton = clickedSubmitButton;
				eDataSend.preventSubmit = false;
				eDataSend.ajaxOptions = {};

			this.$form.trigger(eDataSend);

			this.$form.removeData('clickedSubmitButton');

			if (eDataSend.preventSubmit)
			{
				return false;
			}
			else if (!eDataSend.isDefaultPrevented())
			{
				serialized = this.$form.serializeArray();
				if (clickedSubmitButton)
				{
					$clickedSubmitButton = $(clickedSubmitButton);
					if ($clickedSubmitButton.attr('name'))
					{
						serialized.push({
							name: $clickedSubmitButton.attr('name'),
							value: $clickedSubmitButton.attr('value')
						});
					}
				}

				XenForo.ajax(
					eDataSend.formAction,
					serialized,
					$.context(this, 'ajaxSaveResponse'),
					eDataSend.ajaxOptions
				);

				e.preventDefault();
			}
		},

		/**
		 * Handles the AJAX response from ajaxSave().
		 *
		 * @param ajaxData
		 * @param textStatus
		 * @return
		 */
		ajaxSaveResponse: function(ajaxData, textStatus)
		{
			if (!ajaxData)
			{
				console.warn('No ajax data returned.');
				return false;
			}

			var eDataRecv,
				eError,
				eComplete,
				$trigger;

			eDataRecv = $.Event('AutoValidationDataReceived');
			eDataRecv.ajaxData = ajaxData;
			eDataRecv.textStatus = textStatus;
			eDataRecv.validationError = [];
			console.group('Event: %s', eDataRecv.type);
			this.$form.trigger(eDataRecv);
			console.groupEnd();
			if (eDataRecv.isDefaultPrevented())
			{
				return false;
			}

			// if the submission has failed validation, show the error overlay
			if (!this.validates(eDataRecv))
			{
				eError = $.Event('AutoValidationError');
				eError.ajaxData = ajaxData;
				eError.textStatus = textStatus;
				eError.validationError = eDataRecv.validationError;
				console.group('Event: %s', eError.type);
				this.$form.trigger(eError);
				console.groupEnd();
				if (eError.isDefaultPrevented())
				{
					return false;
				}

				if (ajaxData.templateHtml)
				{
					this.$error = XenForo.createOverlay(null, this.prepareError(ajaxData.templateHtml)).load();
				}
				else if (ajaxData.error !== undefined)
				{
					if (typeof ajaxData.error === 'object')
					{
						var key;
						for (key in ajaxData.error)
						{
							break;
						}
						ajaxData.error = ajaxData.error[key];
					}

					XenForo.alert(
						ajaxData.error + '\n'
							+ (ajaxData.traceHtml !== undefined ? '<ol class="traceHtml">\n' + ajaxData.traceHtml + '</ol>' : ''),
						XenForo.phrases.following_error_occurred + ':'
					);
				}

				return false;
			}

			eComplete = $.Event('AutoValidationComplete'),
			eComplete.ajaxData = ajaxData;
			eComplete.textStatus = textStatus;
			eComplete.$form = this.$form;
			console.group('Event: %s', eComplete.type);
			this.$form.trigger(eComplete);
			console.groupEnd();
			if (eComplete.isDefaultPrevented())
			{
				return false;
			}

			// if the form is in an overlay, close it
			if (this.$form.parents('.xenOverlay').length)
			{
				this.$form.parents('.xenOverlay').data('overlay').close();

				if (ajaxData.linkPhrase)
				{
					$trigger = this.$form.parents('.xenOverlay').data('overlay').getTrigger();
					$trigger.xfFadeOut(XenForo.speed.fast, function()
					{
						if (ajaxData.linkUrl && $trigger.is('a'))
						{
							$trigger.attr('href', ajaxData.linkUrl);
						}

						$trigger
							.text(ajaxData.linkPhrase)
							.xfFadeIn(XenForo.speed.fast);
					});
				}
			}

			if (ajaxData.message)
			{
				XenForo.alert(ajaxData.message, '', 4000);
				return;
			}

			// if a redirect message was not specified, redirect immediately
			if (ajaxData._redirectMessage == '')
			{
				return this.redirect(ajaxData._redirectTarget);
			}

			// show the redirect message, then redirect if a redirect target was specified
			XenForo.alert(ajaxData._redirectMessage, '', 1000, $.context(function()
			{
				this.redirect(ajaxData._redirectTarget);
			}, this));
		},

		/**
		 * Checks for the presence of validation errors in the given event
		 *
		 * @param event e
		 *
		 * @return boolean
		 */
		validates: function(e)
		{
			return ($.isEmptyObject(e.validationErrors) && !e.ajaxData.error);
		},

		/**
		 * Attempts to match labels to errors for the error overlay
		 *
		 * @param string html
		 *
		 * @return jQuery
		 */
		prepareError: function(html)
		{
			$html = $(html);

			// extract labels that correspond to the error fields and insert their text next to the error message
			$html.find('label').each(function(i, label)
			{
				var $ctrlLabel = $('#' + $(label).attr('for'))
					.closest('.ctrlUnit')
					.find('dt > label');

				if ($ctrlLabel.length)
				{
					$(label).prepend($ctrlLabel.text() + '<br />');
				}
			});

			return $html;
		},

		/**
		 * Redirect the browser to redirectTarget if it is specified
		 *
		 * @param string redirectTarget
		 *
		 * @return boolean
		 */
		redirect: function(redirectTarget)
		{
			if (XenForo.isPositive(this.$form.data('redirect')) || !parseInt(XenForo._enableOverlays))
			{
				var $AutoValidationRedirect = new $.Event('AutoValidationRedirect');
					$AutoValidationRedirect.redirectTarget = redirectTarget;

				this.$form.trigger($AutoValidationRedirect);

				if (!$AutoValidationRedirect.isDefaultPrevented() && $AutoValidationRedirect.redirectTarget)
				{
					window.location = XenForo.canonicalizeUrl($AutoValidationRedirect.redirectTarget);
					return true;
				}
			}

			return false;
		},

		// ---------------------------------------------------
		// Field validation methods...

		/**
		 * Sets a timeout before an AJAX field validation request will be fired
		 * (Prevents AJAX floods)
		 *
		 * @param string Name of field to be validated
		 * @param function Callback to fire when the timeout elapses
		 */
		setFieldValidationTimeout: function(name, callback)
		{
			if (!this.hasFieldValidator(name)) { return false; }

			console.log('setTimeout %s', name);

			this.clearFieldValidationTimeout(name);

			this.fieldValidationTimeouts[name] = setTimeout(callback, 250);
		},

		/**
		 * Cancels a timeout set with setFieldValidationTimeout()
		 *
		 * @param string name
		 */
		clearFieldValidationTimeout: function(name)
		{
			if (this.fieldValidationTimeouts[name])
			{
				console.log('Clear field validation timeout: %s', name);

				clearTimeout(this.fieldValidationTimeouts[name]);
				delete(this.fieldValidationTimeouts[name]);
			}
		},

		/**
		 * Fires an AJAX field validation request
		 *
		 * @param string Name of variable to be verified
		 * @param jQuery Input field to be validated
		 * @param function Callback function to fire on success
		 */
		startFieldValidationRequest: function(name, $input, callback)
		{
			if (!this.hasFieldValidator(name)) { return false; }

			// abort any existing AJAX validation requests from this $input
			this.abortFieldValidationRequest(name);

			// fire the AJAX request and register it in the fieldValidationRequests
			// object so it can be cancelled by subsequent requests
			this.fieldValidationRequests[name] = XenForo.ajax(this.fieldValidatorUrl,
			{
				name: $input.attr('name'),
				value: $input.fieldValue(),
				existingDataKey: this.getExistingDataKey()
			}, callback,
			{
				global: false // don't show AJAX progress indicators for inline validation
			});
		},

		/**
		 * Aborts an AJAX field validation request set up by startFieldValidationRequest()
		 *
		 * @param string name
		 */
		abortFieldValidationRequest: function(name)
		{
			if (this.fieldValidationRequests[name])
			{
				console.log('Abort field validation request: %s', name);

				this.fieldValidationRequests[name].abort();
				delete(this.fieldValidationRequests[name]);
			}
		},

		/**
		 * Cancels any pending timeouts or ajax field validation requests
		 */
		abortPendingFieldValidation: function()
		{
			console.group('Abort pending field validation...');
			$.each(this.fieldValidationTimeouts, $.context(this, 'clearFieldValidationTimeout'));

			$.each(this.fieldValidationRequests, $.context(this, 'abortFieldValidationRequest'));
			console.groupEnd();
		},

		/**
		 * Throws a warning if this.fieldValidatorUrl is not valid
		 *
		 * @param string Name of field to be validated
		 *
		 * @return boolean
		 */
		hasFieldValidator: function(name)
		{
			if (this.fieldValidatorUrl)
			{
				return true;
			}

			//console.warn('Unable to request validation for field "%s" due to lack of fieldValidatorUrl in form tag.', name);
			return false;
		}
	};

	// *********************************************************************

	/**
	 * Handler for individual fields in an AutoValidator form.
	 * Manages individual field validation and inline error display.
	 *
	 * @param jQuery input [text-type]
	 */
	XenForo.AutoValidatorControl = function($input) { this.__construct($input); };
	XenForo.AutoValidatorControl.prototype =
	{
		__construct: function($input)
		{
			this.$form = $input.closest('form.AutoValidator').bind(
			{
				AutoValidationDataReceived: $.context(this, 'handleFormValidation')
			});

			this.$input = $input.bind(
			{
				change:              $.context(this, 'change'),
				AutoValidationError: $.context(this, 'showError'),
				AutoValidationPass:  $.context(this, 'hideError')
			});

			this.name = $input.attr('name');
		},

		/**
		 * When the value of a field changes, initiate validation
		 *
		 * @param event e
		 */
		change: function(e)
		{
			this.$form.data('XenForo.AutoValidator')
				.setFieldValidationTimeout(this.name, $.context(this, 'validate'));
		},

		/**
		 * Fire a validation AJAX request
		 */
		validate: function()
		{
			this.$form.data('XenForo.AutoValidator')
				.startFieldValidationRequest(this.name, this.$input, $.context(this, 'handleValidation'));
		},

		/**
		 * Handle the data returned from an AJAX validation request fired in validate().
		 * Fires 'AutoValidationPass' or 'AutoValidationError' for the $input according to the validation state.
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 *
		 * @return boolean
		 */
		handleValidation: function(ajaxData, textStatus)
		{
			if (ajaxData && ajaxData.error && ajaxData.error[this.name])
			{
				this.$input.trigger({
					type: 'AutoValidationError',
					errorMessage: ajaxData.error[this.name]
				});
				return false;
			}
			else
			{
				this.$input.trigger('AutoValidationPass');
				return true;
			}
		},

		/**
		 * Shows an inline error message, text contained within a .errorMessage property of the event passed
		 *
		 * @param event e
		 */
		showError: function(e)
		{
			console.warn('%s: %s', this.name, e.errorMessage);

			this.fetchError(e.errorMessage)
				.css(this.positionError())
				.show();
		},

		/**
		 * Hides any inline error message shown with this input
		 */
		hideError: function()
		{
			console.info('%s: Okay', this.name);

			if (this.$error)
			{
				this.fetchError()
					.hide();
			}
		},

		/**
		 * Fetches or creates (as necessary) the error HTML object for this field
		 *
		 * @param string Error message
		 *
		 * @return jQuery this.$error
		 */
		fetchError: function(message)
		{
			if (!this.$error)
			{
				this.$error = $('<label for="' + this.$input.attr('id') + '" class="formValidationInlineError">WHoops</label>').insertAfter(this.$input);
			}

			if (message)
			{
				this.$error.html(message);
			}

			return this.$error;
		},

		/**
		 * Returns an object containing top and left properties, used to position the inline error message
		 *
		 * @return object {left: x, top:y}
		 */
		positionError: function()
		{
			var coords = this.$input.coords('outer', 'position');

			return {
				top: coords.top,
				left: coords.left + coords.width + 10
			};
		},

		/**
		 * Handles validation for this field passed down from a submission of the whole AutoValidator
		 * form, and passes the relevant data into the handler for this field specifically.
		 *
		 * @param event e
		 */
		handleFormValidation: function(e)
		{
			if (!this.handleValidation(e.ajaxData, e.textStatus))
			{
				e.validationError.push(this.name);
			}
		}
	};

	// *********************************************************************

	/**
	 * Checks a form field to see if it is part of an AutoValidator form,
	 * and if so, whether or not it is subject to autovalidation.
	 *
	 * @param object Form control to be tested
	 *
	 * @return boolean
	 */
	XenForo.isAutoValidatorField = function(ctrl)
	{
		var AutoValidator, $ctrl;

		if (AutoValidator = $(ctrl.form).data('XenForo.AutoValidator'))
		{
			$ctrl = $(ctrl);

			switch (AutoValidator.optInMode)
			{
				case 'OptIn':
				{
					return ($ctrl.hasClass('OptIn') || $ctrl.closest('.ctrlUnit').hasClass('OptIn'));
				}
				default:
				{
					return (!$ctrl.hasClass('OptOut') && !$ctrl.closest('.ctrlUnit').hasClass('OptOut'));
				}
			}
		}

		return false;
	};

	// *********************************************************************

	XenForo.PreviewForm = function($form)
	{
		var previewUrl = $form.data('previewUrl');
		if (!previewUrl)
		{
			console.warn('PreviewForm has no data-previewUrl: %o', $form);
			return;
		}

		$form.find('.PreviewButton').click(function(e)
		{
			XenForo.ajax(previewUrl, $form.serialize(), function(ajaxData)
			{
				if (XenForo.hasResponseError(ajaxData) || !XenForo.hasTemplateHtml(ajaxData))
				{
					return false;
				}

				new XenForo.ExtLoader(ajaxData, function(ajaxData)
				{
					var $preview = $form.find('.PreviewContainer').first();
					if ($preview.length)
					{
						$preview.xfFadeOut(XenForo.speed.fast, function() {
							$preview.html(ajaxData.templateHtml).xfActivate();
						});
					}
					else
					{
						$preview = $('<div />', { 'class': 'PreviewContainer'})
							.hide()
							.html(ajaxData.templateHtml)
							.xfActivate()
							.prependTo($form);
					}

					$preview.xfFadeIn(XenForo.speed.fast);
					$preview.get(0).scrollIntoView(true);
				});
			});
		});
	};

	// *********************************************************************

	/**
	 * Allows a text input field to rewrite the H1 (or equivalent) tag's contents
	 *
	 * @param jQuery input[data-liveTitleTemplate]
	 */
	XenForo.LiveTitle = function($input)
	{
		var $title = $input.closest('.formOverlay').find('h2.h1'), setTitle;

		if (!$title.length)
		{
			$title = $('h1').first();
		}
		console.info('Title Element: %o', this.$title);
		$title.data('originalHtml', $title.html());

		setTitle = function(value)
		{
			$title.html(value === ''
				? $title.data('originalHtml')
				: $input.data('liveTitleTemplate').replace(/%s/, $('<div />').text(value).html())
			);
		};

		setTitle($input.strval());

		$input.keyup(function(e)
		{
			setTitle($input.strval());
		})
		.closest('form').bind('reset', function(e)
		{
			setTitle('');
		});
	};

	// *********************************************************************

	XenForo.TextareaElastic = function($input) { this.__construct($input); };
	XenForo.TextareaElastic.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input;
			this.curHeight = 0;

			$input.bind('keyup focus', $.context(this, 'recalculate'));
			$input.bind('paste', $.context(this, 'paste'));

			if ($input.val() !== '')
			{
				this.recalculate();
			}
		},

		recalculate: function()
		{
			var $input = this.$input,
				input = $input.get(0),
				clone,
				height,
				pos;

			if ($input.val() === '')
			{
				$input.css({
					'overflow': 'hidden',
					'height': ''
				});
				this.curHeight = 0;
				return;
			}

			if (!this.minHeight)
			{
				this.borderBox = ($input.css('-moz-box-sizing') == 'border-box' || $input.css('box-sizing') == 'border-box');
				this.minHeight = (this.borderBox ? $input.outerHeight() : input.clientHeight);

				if (!this.minHeight)
				{
					return;
				}

				this.maxHeight = parseInt($input.css('max-height'), 10);
				this.spacing = (this.borderBox ? $input.outerHeight() - $input.innerHeight() : 0);
			}

			if (!this.$clone)
			{
				this.$clone = $('<textarea />').css({
					position: 'absolute',
					left: '-10000px',
					visibility: 'hidden',
					width: input.clientWidth,
					height: '1px',
					'font-size': $input.css('font-size'),
					'font-family': $input.css('font-family'),
					'font-weight': $input.css('font-weight'),
					'line-height': $input.css('line-height'),
					'word-wrap': $input.css('word-wrap')
				}).attr('tabindex', -1).val(' ');

				this.$clone.appendTo(document.body);

				this.lineHeight = this.$clone.get(0).scrollHeight;
			}

			this.$clone.val($input.val());
			clone = this.$clone.get(0);

			height = Math.max(this.minHeight, clone.scrollHeight + this.lineHeight + this.spacing);

			if (height < this.maxHeight)
			{
				if (this.curHeight != height)
				{
					input = $input.get(0);
					if (this.curHeight == this.maxHeight && input.setSelectionRange)
					{
						pos = input.selectionStart;
					}

					$input.css({
						'overflow': 'hidden',
						'height': height + 'px'
					});

					if (this.curHeight == this.maxHeight && input.setSelectionRange)
					{
						input.setSelectionRange(pos, pos);
					}

					this.curHeight = height;
				}
			}
			else
			{
				if (this.curHeight != this.maxHeight)
				{
					input = $input.get(0);
					if (input.setSelectionRange)
					{
						pos = input.selectionStart;
					}

					$input.css({
						'overflow': 'auto',
						'height': this.maxHeight + 'px'
					});

					if (input.setSelectionRange)
					{
						input.setSelectionRange(pos, pos);
					}

					this.curHeight = this.maxHeight;
				}
			}
		},

		paste: function()
		{
			setTimeout($.context(this, 'recalculate'), 100);
		}
	};

	// *********************************************************************

	XenForo.AutoTimeZone = function($element)
	{
		var now = new Date(),
			jan1 = new Date(now.getFullYear(), 0, 1), // 0 = jan
			jun1 = new Date(now.getFullYear(), 5, 1), // 5 = june
			jan1offset = Math.round(jan1.getTimezoneOffset()),
			jun1offset = Math.round(jun1.getTimezoneOffset());

		// opera doesn't report TZ offset differences in jan/jun correctly
		if ($.browser.opera)
		{
			return false;
		}

		if (XenForo.AutoTimeZone.map[jan1offset + ',' + jun1offset])
		{
			$element.val(XenForo.AutoTimeZone.map[jan1offset + ',' + jun1offset]);
			return true;
		}
		else
		{
			return false;
		}
	};

	XenForo.AutoTimeZone.map =
	{
		// TODO: expand to cover as many TZs as possible
		'480,420': 'America/Los_Angeles',
		'420,360': 'America/Denver',
		'360,300': 'America/Chicago',
		'300,240': 'America/New_York',
		'0,-60': 'Europe/London'
	};

	// *********************************************************************

	XenForo.DatePicker = function($input)
	{
		if (!XenForo.DatePicker.$root)
		{
			$.tools.dateinput.localize('_f',
			{
				months: XenForo.phrases._months,
				shortMonths: '1,2,3,4,5,6,7,8,9,10,11,12',
				days: 's,m,t,w,t,f,s',
				shortDays: XenForo.phrases._daysShort
			});
		}

		var $date = $input.dateinput(
		{
			lang: '_f',
			format: 'yyyy-mm-dd', // rfc 3339 format, required by html5 date element
			speed: 0,
			onShow: function(e)
			{
				var $root = XenForo.DatePicker.$root,
					offset = $date.offset(),
					maxZIndex = 0;

				$root.css(
				{
					top: offset.top + $date.outerHeight({margins: true}),
					left: offset.left
				});

				$date.parents().each(function(i, el)
				{
					var zIndex = parseInt($(el).css('z-index'), 10);
					if (zIndex > maxZIndex)
					{
						maxZIndex = zIndex;
					}
				});

				$root.css('z-index', maxZIndex + 1000);
			}
		});

		$date.addClass($input.attr('class'));
		if ($input.attr('id'))
		{
			$date.attr('id', $input.attr('id'));
		}

		if (!XenForo.DatePicker.$root)
		{
			XenForo.DatePicker.$root = $('#calroot').appendTo(document.body);

			$('#calprev').html('&larr;').attr('unselectable', 'on');
			$('#calnext').html('&rarr;').attr('unselectable', 'on');
		}
	};

	XenForo.DatePicker.$root = null;

	// *********************************************************************

	XenForo.AutoComplete = function($element) { this.__construct($element); };
	XenForo.AutoComplete.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input;

			if (XenForo.AutoComplete.defaultUrl === null)
			{
				if ($('html').hasClass('Admin'))
				{
					XenForo.AutoComplete.defaultUrl = 'admin.php?users/search-name&_xfResponseType=json';
				}
				else
				{
					XenForo.AutoComplete.defaultUrl = 'index.php?members/find&_xfResponseType=json';
				}
			}

			this.url = $input.data('acUrl') || XenForo.AutoComplete.defaultUrl;

			var options = {
				multiple: $input.hasClass('AcSingle') ? false : ',', // mutiple value joiner
				minLength: 2, // min word length before lookup
				queryKey: 'q',
				extraParams: {},
				jsonContainer: 'results',
				autoSubmit: XenForo.isPositive($input.data('autoSubmit'))
			};
			if ($input.data('acOptions'))
			{
				options = $.extend(options, $.parseJSON($input.data('acOptions')));
			}

			if (options.autoSubmit)
			{
				options.multiple = false;
			}

			this.multiple = options.multiple;
			this.minLength = options.minLength;
			this.queryKey = options.queryKey;
			this.extraParams = options.extraParams;
			this.jsonContainer = options.jsonContainer;
			this.autoSubmit = options.autoSubmit;

			this.selectedResult = 0;
			this.loadVal = '';
			this.$results = false;
			this.resultsVisible = false;

			$input.attr('autocomplete', 'off')
				.keydown($.context(this, 'keystroke'))
				.keypress($.context(this, 'operaKeyPress'))
				.blur($.context(this, 'blur'));

			$input.closest('form').submit($.context(this, 'hideResults'));
		},

		keystroke: function(e)
		{
			var code = e.keyCode || e.charCode, prevent = true;

			switch(code)
			{
				case 40: this.selectResult(1); break; // down
				case 38: this.selectResult(-1); break; // up
				case 27: this.hideResults(); break; // esc
				case 13: // enter
					if (this.resultsVisible)
					{
						this.insertSelectedResult();
					}
					else
					{
						prevent = false;
					}
					break;

				default:
					prevent = false;
					if (this.loadTimer)
					{
						clearTimeout(this.loadTimer);
					}
					this.loadTimer = setTimeout($.context(this, 'load'), 200);

					if (this.$results)
					{
						this.$results.hide().empty();
						this.resultsVisible = false;
					}
			}

			if (prevent)
			{
				e.preventDefault();
			}
			this.preventKey = prevent;
		},

		operaKeyPress: function(e)
		{
			if ($.browser.opera && this.preventKey)
			{
				e.preventDefault();
			}
		},

		blur: function(e)
		{
			clearTimeout(this.loadTimer);

			// timeout ensures that clicks still register
			setTimeout($.context(this, 'hideResults'), 250);

			if (this.xhr)
			{
				this.xhr.abort();
				this.xhr = false;
			}
		},

		load: function()
		{
			var lastLoad = this.loadVal, params = this.extraParams;

			if (this.loadTimer)
			{
				clearTimeout(this.loadTimer);
			}

			this.loadVal = this.getPartialValue();

			if (this.loadVal == '')
			{
				this.hideResults();
				return;
			}

			if (this.loadVal == lastLoad)
			{
				return;
			}

			if (this.loadVal.length < this.minLength)
			{
				return;
			}

			params[this.queryKey] = this.loadVal;

			if (this.xhr)
			{
				this.xhr.abort();
			}

			this.xhr = XenForo.ajax(
				this.url,
				params,
				$.context(this, 'showResults'),
				{ global: false, error: false }
			);
		},

		hideResults: function()
		{
			this.resultsVisible = false;

			if (this.$results)
			{
				this.$results.hide();
			}
		},

		showResults: function(results)
		{
			var offset = this.$input.offset(),
				maxZIndex = 0,
				i;

			if (this.xhr)
			{
				this.xhr = false;
			}

			if (!results)
			{
				this.hideResults();
				return;
			}

			if (this.jsonContainer)
			{
				if (!results[this.jsonContainer])
				{
					this.hideResults();
					return;
				}
				else
				{
					results = results[this.jsonContainer];
				}
			}

			this.resultsVisible = false;

			if (!this.$results)
			{
				this.$results = $('<ul />')
					.css({position: 'absolute', display: 'none'})
					.addClass('autoCompleteList')
					.appendTo(document.body);

				this.$input.parents().each(function(i, el)
				{
					var $el = $(el),
						zIndex = parseInt($el.css('z-index'), 10);

					if (zIndex > maxZIndex)
					{
						maxZIndex = zIndex;
					}
				});

				this.$results.css('z-index', maxZIndex + 1000);
			}
			else
			{
				this.$results.hide().empty();
			}

			for (i in results)
			{
				$('<li />')
					.css('cursor', 'pointer')
					.data('autoComplete', i)
					.click($.context(this, 'resultClick'))
					.mouseenter($.context(this, 'resultMouseEnter'))
					.html(results[i])
					.appendTo(this.$results);
			}

			if (!this.$results.children().length)
			{
				return;
			}

			this.selectResult(0, true);

			this.$results.css(
			{
				top: offset.top + this.$input.outerHeight(),
				left: offset.left
			}).show();
			this.resultsVisible = true;
		},

		resultClick: function(e)
		{
			e.stopPropagation();

			this.addValue($(e.currentTarget).data('autoComplete'));
			this.hideResults();
			this.$input.focus();
		},

		resultMouseEnter: function (e)
		{
			this.selectResult($(e.currentTarget).index(), true);
		},

		selectResult: function(shift, absolute)
		{
			var sel, children;

			if (!this.$results)
			{
				return;
			}

			if (absolute)
			{
				this.selectedResult = shift;
			}
			else
			{
				this.selectedResult += shift;
			}

			sel = this.selectedResult;
			children = this.$results.children();
			children.each(function(i)
			{
				if (i == sel)
				{
					$(this).addClass('selected');
				}
				else
				{
					$(this).removeClass('selected');
				}
			});

			if (sel < 0 || sel >= children.length)
			{
				this.selectedResult = -1;
			}
		},

		insertSelectedResult: function()
		{
			var res, ret = false;

			if (!this.resultsVisible)
			{
				return false;
			}

			if (this.selectedResult >= 0)
			{
				res = this.$results.children().get(this.selectedResult);
				if (res)
				{
					this.addValue($(res).data('autoComplete'));
					ret = true;
				}
			}

			this.hideResults();

			return ret;
		},

		addValue: function(value)
		{
			if (!this.multiple)
			{
				this.$input.val(value);
			}
			else
			{
				var values = this.getFullValues();
				if (value != '')
				{
					if (values.length)
					{
						value = ' ' + value;
					}
					values.push(value + this.multiple + ' ');
				}
				this.$input.val(values.join(this.multiple));
			}

			if (this.autoSubmit)
			{
				this.$input.closest('form').submit();
			}
		},

		getFullValues: function()
		{
			var val = this.$input.val();

			if (val == '')
			{
				return [];
			}

			if (!this.multiple)
			{
				return [val];
			}
			else
			{
				splitPos = val.lastIndexOf(this.multiple);
				if (splitPos == -1)
				{
					return [];
				}
				else
				{
					val = val.substr(0, splitPos);
					return val.split(this.multiple);
				}
			}
		},

		getPartialValue: function()
		{
			var val = this.$input.val(),
				splitPos;

			if (!this.multiple)
			{
				return $.trim(val);
			}
			else
			{
				splitPos = val.lastIndexOf(this.multiple);
				if (splitPos == -1)
				{
					return $.trim(val);
				}
				else
				{
					return $.trim(val.substr(splitPos + this.multiple.length));
				}
			}
		}
	};
	XenForo.AutoComplete.defaultUrl = null;

	// *********************************************************************

	/**
	 * Status Editor
	 *
	 * @param jQuery $textarea.StatusEditor
	 */
	XenForo.StatusEditor = function($input) { this.__construct($input); };
	XenForo.StatusEditor.prototype =
	{
		__construct: function($input)
		{
			this.$input = $input
				.keyup($.context(this, 'update'))
				.keydown($.context(this, 'preventNewline'));

			this.$counter = $(this.$input.data('statusEditorCounter'));
			if (!this.$counter.length)
			{
				this.$counter = $('<span />').insertAfter(this.$input);
			}
			this.$counter
				.addClass('statusEditorCounter')
				.text('0');

			this.$form = this.$input.closest('form').bind(
			{
				AutoValidationComplete: $.context(this, 'saveStatus')
			});

			this.charLimit = 140; // Twitter max characters
			this.charCount = 0; // number of chars currently in use

			this.update();
		},

		/**
		 * Handles key events on the status editor, updates the 'characters remaining' output.
		 *
		 * @param Event e
		 */
		update: function(e)
		{
			var statusText = this.$input.val();

			if (this.$input.attr('placeholder') && this.$input.attr('placeholder') == statusText)
			{
				this.setCounterValue(this.charLimit, statusText.length);
			}
			else
			{
				this.setCounterValue(this.charLimit - statusText.length, statusText.length);
			}
		},

		/**
		 * Sets the value of the character countdown, and appropriate classes for that value.
		 *
		 * @param integer Characters remaining
		 * @param integer Current length of status text
		 */
		setCounterValue: function(remaining, length)
		{
			if (remaining < 0)
			{
				this.$counter.addClass('error');
				this.$counter.removeClass('warning');
			}
			else if (remaining <= this.charLimit - 130)
			{
				this.$counter.removeClass('error');
				this.$counter.addClass('warning');
			}
			else
			{
				this.$counter.removeClass('error');
				this.$counter.removeClass('warning');
			}

			this.$counter.text(remaining);
			this.charCount = length || this.$input.val().length;
		},

		/**
		 * Don't allow newline characters in the status message.
		 *
		 * Submit the form if [Enter] or [Return] is hit.
		 *
		 * @param Event e
		 */
		preventNewline: function(e)
		{
			if (e.which == 13) // return / enter
			{
				e.preventDefault();

				$(this.$input.get(0).form).submit();

				return false;
			}
		},

		/**
		 * Updates the status field after saving
		 *
		 * @param event e
		 */
		saveStatus: function(e)
		{
			this.$input.val('');
			this.update(e);

			if (e.ajaxData && e.ajaxData.status !== undefined)
			{
				$('.CurrentStatus').text(e.ajaxData.status);
			}
		}
	};

	// *********************************************************************

	/**
	 * Special effect that allows positioning based on bottom / left rather than top / left
	 */
	$.tools.tooltip.addEffect('PreviewTooltip',
	function(callback)
	{
		var triggerOffset = this.getTrigger().offset(),
			config = this.getConf();

		this.getTip().css(
		{
			top: 'auto',
			left: triggerOffset.left + config.offset[1],
			bottom: $(window).height() - triggerOffset.top + config.offset[0]
		}
		).xfFadeIn(XenForo.speed.normal);

	},
	function(callback)
	{
		this.getTip().xfFadeOut(XenForo.speed.fast);
	});

	/**
	 * Cache to store fetched previews
	 *
	 * @var object
	 */
	XenForo._PreviewTooltipCache = {};

	XenForo.PreviewTooltip = function($el)
	{
		var hasTooltip, previewUrl, setupTimer;

		if (!parseInt(XenForo._enableOverlays))
		{
			return;
		}

		if (!(previewUrl = $el.data('previewUrl')))
		{
			console.warn('Preview tooltip has no preview: %o', $el);
			return;
		}

		$el.find('[title]').andSelf().attr('title', '');

		$el.bind(
		{
			mouseenter: function(e)
			{
				if (hasTooltip)
				{
					return;
				}

				setupTimer = setTimeout(function()
				{
					if (hasTooltip)
					{
						return;
					}

					hasTooltip = true;

					var $tipSource = $('#PreviewTooltip'),
						$tipHtml,
						xhr;

					if (!$tipSource.length)
					{
						console.error('Unable to find #PreviewTooltip');
						return;
					}

					console.log('Setup preview tooltip for %s', previewUrl);

					$tipHtml = $tipSource.clone()
						.removeAttr('id')
						.addClass('xenPreviewTooltip')
						.appendTo(document.body);

					if (!XenForo._PreviewTooltipCache[previewUrl])
					{
						xhr = XenForo.ajax(
							previewUrl,
							{},
							function(ajaxData)
							{
								if (XenForo.hasTemplateHtml(ajaxData))
								{
									XenForo._PreviewTooltipCache[previewUrl] = ajaxData.templateHtml;

									$(ajaxData.templateHtml).xfInsert('replaceAll', $tipHtml.find('.PreviewContents'));
								}
								else
								{
									$tipHtml.remove();
								}
							},
							{
								type: 'GET',
								error: false,
								global: false
							}
						);
					}

					$el.tooltip(
					{
						predelay: 500,
						delay: 0,
						effect: 'PreviewTooltip',
						fadeInSpeed: 'normal',
						fadeOutSpeed: 'fast',
						tip: $tipHtml,
						position: 'bottom left',
						offset: [ 10, -15 ] // was 10, 25
					});

					$el.data('tooltip').show(0);

					if (XenForo._PreviewTooltipCache[previewUrl])
					{
						$(XenForo._PreviewTooltipCache[previewUrl])
							.xfInsert('replaceAll', $tipHtml.find('.PreviewContents'), 'show', 0);
					}
				}, 800);
			},

			mouseleave: function(e)
			{
				if (hasTooltip)
				{
					if ($el.data('tooltip'))
					{
						$el.data('tooltip').hide();
					}

					return;
				}

				if (setupTimer)
				{
					clearTimeout(setupTimer);
				}
			},

			mousedown: function(e)
			{
				// the click will cancel a timer or hide the tooltip
				if (setupTimer)
				{
					clearTimeout(setupTimer);
				}

				if ($el.data('tooltip'))
				{
					$el.data('tooltip').hide();
				}
			}
		});
	};

	// *********************************************************************

	/**
	 * Allows an entire block to act as a link in the navigation popups
	 *
	 * @param jQuery li.PopupItemLink
	 */
	XenForo.PopupItemLink = function($listItem)
	{
		var href = $listItem.find('.PopupItemLink').first().attr('href');

		if (href)
		{
			$listItem
				.addClass('PopupItemLinkActive')
				.click(function(e)
				{
					if ($(e.target).is('a'))
					{
						return;
					}
					window.location = XenForo.canonicalizeUrl(href);
				});
		}
	};

	// *********************************************************************

	/**
	 * Allows a link or input to load content via AJAX and insert it into the DOM.
	 * The control element to which this is applied must have href or data-href attributes
	 * and a data-target attribute describing a jQuery selector for the element relative to which
	 * the content will be inserted.
	 *
	 * You may optionally provide a data-method attribute to override the default insertion method
	 * of 'appendTo'.
	 *
	 * By default, the control will be unlinked and have its click event unbound after a single use.
	 * Specify data-unlink="false" to prevent this default behaviour.
	 *
	 * Upon successful return of AJAX data, the control element will fire a 'ContentLoaded' event,
	 * including ajaxData and textStatus data properties.
	 *
	 * After template content has been inserted, the control element will fire a 'ContentInserted' event
	 * after which the control will be deactivated.
	 *
	 * @param jQuery a.ContentLoader[href][data-target]
	 */
	XenForo.Loader = function($link)
	{
		var clickHandler = function(e)
		{
			var href = $link.attr('href') || $link.data('href'),
				target = $link.data('target');

			if (href && $(target).length)
			{
				e.preventDefault();

				XenForo.ajax(href, {}, function(ajaxData, textStatus)
				{
					if (XenForo.hasResponseError(ajaxData))
					{
						return false;
					}

					var insertEvent = new $.Event('ContentLoaded');
						insertEvent.ajaxData = ajaxData;
						insertEvent.textStatus = textStatus;

					$link.trigger(insertEvent);

					if (!insertEvent.isDefaultPrevented())
					{
						if (ajaxData.templateHtml)
						{
							new XenForo.ExtLoader(ajaxData, function()
							{
								var method = $link.data('method');

								if (typeof method != 'function')
								{
									method = 'appendTo';
								}

								$(ajaxData.templateHtml).xfInsert(method, target);

								if ($link.data('unlink') !== false)
								{
									$link.removeAttr('href').removeData('href').unbind('click', clickHandler);
								}
							});
						}
					}
				});
			}
		};

		$link.bind('click', clickHandler);
	};

	// *********************************************************************

	/**
	 * Allows a control to create a clone of an existing field, like 'add new response' for polls
	 *
	 * @param jQuery $button.FieldAdder[data-source=#selectorOfCloneSource]
	 */
	XenForo.FieldAdder = function($button)
	{
		$button.click(function(e)
		{
			var $source = $($button.data('source')),
				maxFields = $button.data('maxFields'),
				$clone = null;

			console.log('source.length %s, maxfields %s', $source.length, maxFields);

			if ($source.length && (!maxFields || ($source.length < maxFields)))
			{
				$clone = $source.last().clone();
				$clone.find('input').val('').attr('disabled', true);
				$clone.xfInsert('insertAfter', $source.last(), false, false, function()
				{
					$clone.find('input').first().attr('disabled', false).focus().select();

					if (maxFields)
					{
						if ($($button.data('source')).length >= maxFields)
						{
							$button.xfRemove();
						}
					}
				});
			}
		});
	};

	// *********************************************************************

	// Register overlay-loading controls
	// TODO: when we have a global click handler, change this to use rel="Overlay" instead of class="OverlayTrigger"
	XenForo.register(
		'a.OverlayTrigger, input.OverlayTrigger, button.OverlayTrigger, label.OverlayTrigger, a.username, a.avatar',
		'XenForo.OverlayTrigger'
	);

	if (!XenForo.isTouchBrowser())
	{
		// Register tooltip elements for desktop browsers
		XenForo.register('.Tooltip', 'XenForo.Tooltip');
		XenForo.register('a.StatusTooltip', 'XenForo.StatusTooltip');
		XenForo.register('.PreviewTooltip', 'XenForo.PreviewTooltip');
	}

	// Register lightbox triggers for desktop browsers
	XenForo.register('a.LbTrigger', 'XenForo.LightBoxTrigger');

	// Register click-proxy controls
	XenForo.register('.ClickProxy', 'XenForo.ClickProxy');

	// Register popup menu controls
	XenForo.register('.Popup', 'XenForo.PopupMenu', 'XenForoActivatePopups');

	// Register scrolly pagenav elements
	XenForo.register('.PageNav', 'XenForo.PageNav');

	// Register tabs
	XenForo.register('.Tabs', 'XenForo.Tabs');

	// Handle all xenForms
	XenForo.register('form.xenForm', 'XenForo.MultiSubmitFix');

	// Register check-all controls
	XenForo.register('input.CheckAll, a.CheckAll, label.CheckAll', 'XenForo.CheckAll');

	// Register toggle buttons
	XenForo.register('label.ToggleButton', 'XenForo.ToggleButton');

	// Register auto inline uploader controls
	XenForo.register('form.AutoInlineUploader', 'XenForo.AutoInlineUploader');

	// Register form auto validators
	XenForo.register('form.AutoValidator', 'XenForo.AutoValidator');

	// Register auto time zone selector
	XenForo.register('select.AutoTimeZone', 'XenForo.AutoTimeZone');

	// Register generic content loader
	XenForo.register('a.Loader, input.Loader', 'XenForo.Loader');

	// Register form controls
	XenForo.register('input, textarea', function(i)
	{
		var $this = $(this);

		switch ($this.attr('type'))
		{
			case 'hidden':
			case 'submit':
				return;
			case 'checkbox':
			case 'radio':
				// Register control disablers
				if ($this.is('.Disabler:checkbox, .Disabler:radio'))
				{
					XenForo.create('XenForo.Disabler', this);
				}

				// Register auto submitters
				if ($this.hasClass('SubmitOnChange'))
				{
					XenForo.create('XenForo.SubmitOnChange', this);
				}
				return;
		}

		// Combobox
		if ($this.hasClass('ComboBox'))
		{
			XenForo.create('XenForo.ComboBox', this);
		}

		// Spinbox / input[type=number]
		if ($this.attr('type') == 'number' && 'step' in document.createElement('input'))
		{
			// use the XenForo implementation instead, as browser implementations seem to be universally horrible
			this.type = 'text';
			$this.addClass('SpinBox number');
		}
		if ($this.hasClass('SpinBox'))
		{
			XenForo.create('XenForo.SpinBox', this);
		}

		// Prompt / placeholder
		if ($this.hasClass('Prompt'))
		{
			console.error('input.Prompt[title] is now deprecated. Please replace any instances with input[placeholder] and remove the Prompt class.');
			$this.attr({ placeholder: $this.attr('title'), title: '' });
		}
		if ($this.attr('placeholder'))
		{
			XenForo.create('XenForo.Prompt', this);
		}

		// LiveTitle
		if ($this.data('liveTitleTemplate'))
		{
			XenForo.create('XenForo.LiveTitle', this);
		}

		// DatePicker
		if ($this.is(':date'))
		{
			XenForo.create('XenForo.DatePicker', this);
		}

		// AutoComplete
		if ($this.hasClass('AutoComplete'))
		{
			XenForo.create('XenForo.AutoComplete', this);
		}

		// AutoValidator
		if (XenForo.isAutoValidatorField(this))
		{
			XenForo.create('XenForo.AutoValidatorControl', this);
		}

		if ($this.is('textarea.StatusEditor'))
		{
			XenForo.create('XenForo.StatusEditor', this);
		}

		// Register Elastic textareas
		if (!XenForo.isTouchBrowser() && $this.is('textarea.Elastic:not(.code)'))
		{
			XenForo.create('XenForo.TextareaElastic', this);
		}
	});

	// Register form previewer
	XenForo.register('form.Preview', 'XenForo.PreviewForm');

	// Register field adder
	XenForo.register('a.FieldAdder, input.FieldAdder', 'XenForo.FieldAdder');

	/**
	 * Public-only registrations
	 */
	if ($('html').hasClass('Public'))
	{
		// Register the login bar handle
		XenForo.register('#loginBar', 'XenForo.LoginBar');

		// Register the header search box
		XenForo.register('#QuickSearch', 'XenForo.QuickSearch');

		// Register attribution links
		XenForo.register('a.AttributionLink', 'XenForo.AttributionLink');

		// Recaptcha
		XenForo.register('#ReCaptcha', 'XenForo.ReCaptcha');

		// Other CAPTCHA
		XenForo.register('#Captcha', 'XenForo.Captcha');

		// Resize large BB code images
		XenForo.register('img.bbCodeImage', 'XenForo.BbCodeImage');

		// Handle like/unlike links
		XenForo.register('a.LikeLink', 'XenForo.LikeLink');

		// Register node description tooltips
		if (!XenForo.isTouchBrowser())
		{
			XenForo.register('h3.nodeTitle a', 'XenForo.NodeDescriptionTooltip');
		}

		// Register visitor menu
		XenForo.register('#AccountMenu', 'XenForo.AccountMenu');

		// Register follow / unfollow links
		XenForo.register('a.FollowLink', 'XenForo.FollowLink');

		XenForo.register('li.PopupItemLink', 'XenForo.PopupItemLink');
	}

	// *********************************************************************

	/**
	 * Use jQuery to initialize the system
	 */
	$(function()
	{
		XenForo.Facebook.start();
		XenForo.init();
	});

}
(jQuery, this, document);