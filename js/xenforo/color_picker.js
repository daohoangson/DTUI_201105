/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Shared input color picker
	 *
	 * @param jQuery input.ColorPicker - also supports class .DisablePalette to show picker only
	 */
	XenForo.ColorPicker = function($target) { this.__construct($target); };
	XenForo.ColorPicker.prototype =
	{
		__construct: function($input)
		{
			var $form = $input.closest('form');
			if (!$form.data('XenForo.ColorPickerForm'))
			{
				$form.bind('reset', function(e)
				{
					setTimeout(function() // set timeout because the values aren't back to the default until AFTER the reset event.
					{
						$form.find('input.ColorPicker').each(function(i, input)
						{
							$(input).data('XenForo.ColorPicker').updateValue(input.value);
						});
					}, 100);
				});

				$form.data('XenForo.ColorPickerForm', true);
			}
			this.$form = $form;

			this.$input = $input;

			this.$placeholder = $('<span />')
				.addClass('colorPickerPlaceholder textCtrl')
				.attr('unselectable', true)
				.insertAfter($input);

			this.$placeholder.append('<span>&nbsp;</span>');

			$input.hide();
			this.$placeholder.click($.context(this, 'openColorPicker'));

			this.setPlaceholderColor();
		},

		openColorPicker: function()
		{
			this.$placeholder.blur();

			if (!_pickerInstance)
			{
				_pickerInstance = new ColorPickerInstance();
			}

			_pickerInstance.load($.context(this, 'updateValue'),
				this.$input.val(),
				(this.$input.hasClass('DisablePalette') ? false : true)
			);
		},

		updateValue: function(value)
		{
			this.$input.val(value);
			this.setPlaceholderColor();
		},

		updateRgbaValue: function(r, g, b, a)
		{
			if (a === undefined || a == 1)
			{
				this.updateValue('rgb(' + r + ',' + g + ',' + b + ')');
			}
			else
			{
				this.updateValue('rgba(' + r + ',' + g + ',' + b + ',' + a + ')');
			}
		},

		setPlaceholderColor: function()
		{
			var val = this.$input.val(),
				color = _matchColorsFromString(val);

			if (color)
			{
				this.$placeholder.css('border-style', '');
				if (color.unknown)
				{
					this.$placeholder.find('span')
						.css('background-color', '')
						.addClass('undefined');
				}
				else
				{
					this.setPlaceholderRgb(color.r, color.g, color.b)
						.removeClass('undefined');
				}

				this.currentHsva = _rgbToHsv(color.r, color.g, color.b);
				this.currentHsva.a = color.a;
			}
			else
			{
				this.$placeholder.css('border-style', 'dashed');
				this.$placeholder.find('span')
					.css('background-color', 'transparent')
					.removeClass('undefined');

				this.currentHsva = null;
			}

			this.$placeholder.attr('title', val);
		},

		setPlaceholderRgb: function(r, g, b)
		{
			return this.$placeholder.find('span')
				.css('background-color', 'rgb(' + r + ', ' + g + ', ' + b + ')')
		}
	};

	// *********************************************************************

	XenForo.ColorInvert = function($input)
	{
		$input.click(function(e)
		{
			$($input.data('target')).find('input.ColorPicker').each(function(i, input)
			{
				var color = _matchColorsFromString(input.value);

				$(input).data('XenForo.ColorPicker').updateRgbaValue(
					255 - color.r,
					255 - color.g,
					255 - color.b,
					color.a
				);
			});
		});
	};

	// *********************************************************************

	XenForo.HueShift = function($input)
	{
		var _updateSwatches = function(e, hueShift)
		{
			var shiftValue = hueShift - $input.data('hueShift');

			$input.data('hueShift', hueShift);

			$($input.data('target')).find('input.ColorPicker').each(function(i, input)
			{
				var ColorPicker = $(input).data('XenForo.ColorPicker'),
					hsv,
					rgb;

				if (hsv = ColorPicker.currentHsva)
				{
					hsv.h = (hsv.h + shiftValue) % 360;
					if (hsv.h < 0)
					{
						hsv.h += 360;
					}

					rgb = _hsvToRgb(hsv.h, hsv.s, hsv.v);

					if (e.type == 'change')
					{
						ColorPicker.updateRgbaValue(rgb.r, rgb.g, rgb.b, hsv.a);
					}
					else
					{
						ColorPicker.setPlaceholderRgb(rgb.r, rgb.g, rgb.b);
					}
				}
			});
		};

		$input.data('hueShift', $input.val());

		$input.rangeinput(
		{
			//progress: true,
			change: _updateSwatches,
			onSlide: _updateSwatches,
			css:
			{
				input: 'range textCtrl',
				handle: 'handle'
			}
		});

		var $form = $input.closest('form');
		if (!$form.data('XenForo.HueShiftForm'))
		{
			$form.bind('reset', function(e)
			{
				setTimeout(function() // set timeout because the values aren't back to the default until AFTER the reset event.
				{
					$form.find('input.HueShift').each(function(i, input)
					{
						$(input).data('rangeinput').setValue(input.value);
					});
				}, 50);
			});

			$form.data('XenForo.HueShiftForm', true);
		}
		this.$form = $form;

		$input.data('rangeinput').getHandle().attr('title', $input.attr('title'));
	};

	// *********************************************************************

	XenForo.ColorPicker.namedColors =
	{
		aliceblue: 'f0f8ff',
		antiquewhite: 'faebd7',
		aqua: '00ffff',
		aquamarine: '7fffd4',
		azure: 'f0ffff',
		beige: 'f5f5dc',
		bisque: 'ffe4c4',
		black: '000000',
		blanchedalmond: 'ffebcd',
		blue: '0000ff',
		blueviolet: '8a2be2',
		brown: 'a52a2a',
		burlywood: 'deb887',
		cadetblue: '5f9ea0',
		chartreuse: '7fff00',
		chocolate: 'd2691e',
		coral: 'ff7f50',
		cornflowerblue: '6495ed',
		cornsilk: 'fff8dc',
		crimson: 'dc143c',
		cyan: '00ffff',
		darkblue: '00008b',
		darkcyan: '008b8b',
		darkgoldenrod: 'b8860b',
		darkgray: 'a9a9a9',
		darkgreen: '006400',
		darkkhaki: 'bdb76b',
		darkmagenta: '8b008b',
		darkolivegreen: '556b2f',
		darkorange: 'ff8c00',
		darkorchid: '9932cc',
		darkred: '8b0000',
		darksalmon: 'e9967a',
		darkseagreen: '8fbc8f',
		darkslateblue: '483d8b',
		darkslategray: '2f4f4f',
		darkturquoise: '00ced1',
		darkviolet: '9400d3',
		deeppink: 'ff1493',
		deepskyblue: '00bfff',
		dimgray: '696969',
		dodgerblue: '1e90ff',
		firebrick: 'b22222',
		floralwhite: 'fffaf0',
		forestgreen: '228b22',
		fuchsia: 'ff00ff',
		gainsboro: 'dcdcdc',
		ghostwhite: 'f8f8ff',
		gold: 'ffd700',
		goldenrod: 'daa520',
		gray: '808080',
		green: '008000',
		greenyellow: 'adff2f',
		honeydew: 'f0fff0',
		hotpink: 'ff69b4',
		indianred: 'cd5c5c',
		indigo: '4b0082',
		ivory: 'fffff0',
		khaki: 'f0e68c',
		lavender: 'e6e6fa',
		lavenderblush: 'fff0f5',
		lawngreen: '7cfc00',
		lemonchiffon: 'fffacd',
		lightblue: 'add8e6',
		lightcoral: 'f08080',
		lightcyan: 'e0ffff',
		lightgoldenrodyellow: 'fafad2',
		lightgrey: 'd3d3d3',
		lightgreen: '90ee90',
		lightpink: 'ffb6c1',
		lightsalmon: 'ffa07a',
		lightseagreen: '20b2aa',
		lightskyblue: '87cefa',
		lightslategray: '778899',
		lightsteelblue: 'b0c4de',
		lightyellow: 'ffffe0',
		lime: '00ff00',
		limegreen: '32cd32',
		linen: 'faf0e6',
		magenta: 'ff00ff',
		maroon: '800000',
		mediumaquamarine: '66cdaa',
		mediumblue: '0000cd',
		mediumorchid: 'ba55d3',
		mediumpurple: '9370d8',
		mediumseagreen: '3cb371',
		mediumslateblue: '7b68ee',
		mediumspringgreen: '00fa9a',
		mediumturquoise: '48d1cc',
		mediumvioletred: 'c71585',
		midnightblue: '191970',
		mintcream: 'f5fffa',
		mistyrose: 'ffe4e1',
		moccasin: 'ffe4b5',
		navajowhite: 'ffdead',
		navy: '000080',
		oldlace: 'fdf5e6',
		olive: '808000',
		olivedrab: '6b8e23',
		orange: 'ffa500',
		orangered: 'ff4500',
		orchid: 'da70d6',
		palegoldenrod: 'eee8aa',
		palegreen: '98fb98',
		paleturquoise: 'afeeee',
		palevioletred: 'd87093',
		papayawhip: 'ffefd5',
		peachpuff: 'ffdab9',
		peru: 'cd853f',
		pink: 'ffc0cb',
		plum: 'dda0dd',
		powderblue: 'b0e0e6',
		purple: '800080',
		red: 'ff0000',
		rosybrown: 'bc8f8f',
		royalblue: '4169e1',
		saddlebrown: '8b4513',
		salmon: 'fa8072',
		sandybrown: 'f4a460',
		seagreen: '2e8b57',
		seashell: 'fff5ee',
		sienna: 'a0522d',
		silver: 'c0c0c0',
		skyblue: '87ceeb',
		slateblue: '6a5acd',
		slategray: '708090',
		snow: 'fffafa',
		springgreen: '00ff7f',
		steelblue: '4682b4',
		tan: 'd2b48c',
		teal: '008080',
		thistle: 'd8bfd8',
		tomato: 'ff6347',
		turquoise: '40e0d0',
		violet: 'ee82ee',
		wheat: 'f5deb3',
		white: 'ffffff',
		whitesmoke: 'f5f5f5',
		yellow: 'ffff00',
		yellowgreen: '9acd32'
	};

	// *********************************************************************

	var _pickerInstance,

		_matchColorsFromString = function(str)
		{
			var r = 0, g = 0, b = 0, a = 1, value = '', unknown = false, match;

			str = $.trim(str);
			if (str == '')
			{
				return false;
			}

			if (match = str.match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i))
			{
				r = parseInt(match[1], 16);
				g = parseInt(match[2], 16);
				b = parseInt(match[3], 16);
				value = match[0];
			}
			else if (match = str.match(/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i))
			{
				r = parseInt(match[1] + match[1], 16);
				g = parseInt(match[2] + match[2], 16);
				b = parseInt(match[3] + match[3], 16);
				value = match[0];
			}
			else if (match = str.match(/^rgb\(\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*\)$/i))
			{
				r = match[1];
				g = match[2];
				b = match[3];
				value = match[0];
			}
			else if (match = str.match(/^rgba\(\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9.]+)\s*\)$/i))
			{
				r = match[1];
				g = match[2];
				b = match[3];
				a = match[4];
				value = match[0];
			}
			else if (match = str.match(/^@(([a-z0-9_-]+)(\.[a-z0-9_-]+)?)$/i))
			{
				var color = _getPalette()[match[1]];
				if (color)
				{
					r = color.r;
					g = color.g;
					b = color.b;
					if (color.a !== undefined)
					{
						a = color.a;
					}
				}
				else
				{
					unknown = true;
				}
				value = match[0];
			}
			else if (XenForo.ColorPicker.namedColors[str.toLowerCase()])
			{
				match = _matchColorsFromString('#' + XenForo.ColorPicker.namedColors[str.toLowerCase()]);
				match.value = str;
				return match;
			}
			else
			{
				return false;
			}

			return {
				r: r,
				g: g,
				b: b,
				a: a,
				value: value,
				unknown: unknown
			};
		},

		_palette = false,

		_getPalette = function()
		{
			if (_palette !== false)
			{
				return _palette;
			}

			_palette = {};

			$('#ColorPickerInstance').find('.PaletteTab li').each(function() {
				var $this = $(this),
					colors = _matchColorsFromString($this.data('colorCss')),
					name = $this.data('colorName'),
					$swatch = $('<div />').addClass('swatch');

				if (colors)
				{
					$swatch.css('background-color', 'rgb(' + colors.r + ', ' + colors.g + ', ' + colors.b + ')')
						.prependTo($this);

					_palette[name] = colors;
				}
			});

			return _palette;
		},

		_rgbToHsv = function(r, g, b)
		{
			var max, min, c, h, s, v;

			if (typeof r == 'object')
			{
				g = r.g;
				b = r.b;
				r = r.r;
			}

			r /= 255;
			g /= 255;
			b /= 255;

			max = Math.max(r, g, b);
			min = Math.min(r, g, b);
			c = max - min;

			v = max;

			if (c == 0)
			{
				h = 0;
				s = 0;
			}
			else
			{
				switch (max)
				{
					case r: h = ((g - b) / c) % 6; break;
					case g: h = (b - r) / c + 2; break;
					case b: h = (r - g) / c + 4; break;
				}

				h = Math.round(60 * h);
				if (h < 0)
				{
					h += 360;
				}

				s = c / v;
			}

			return {
				h: h,
				s: s,
				v: v
			};
		},

		_hsvToRgb = function(h, s, v)
		{
			var c, hAlt, x, modifier, r, g, b;

			if (typeof h == 'object')
			{
				s = h.s;
				v = h.v;
				h = h.h;
			}

			c = v * s;
			hAlt = h / 60;
			x = c * (1 - Math.abs(hAlt % 2 - 1));

			if (hAlt < 1)      { r = c; g = x; b = 0; }
			else if (hAlt < 2) { r = x; g = c; b = 0; }
			else if (hAlt < 3) { r = 0; g = c; b = x; }
			else if (hAlt < 4) { r = 0; g = x; b = c; }
			else if (hAlt < 5) { r = x; g = 0; b = c; }
			else if (hAlt < 6) { r = c; g = 0; b = x; }

			modifier = v - c;

			return {
				r: Math.round(255 * (r + modifier)),
				g: Math.round(255 * (g + modifier)),
				b: Math.round(255 * (b + modifier))
			};
		};

	// *********************************************************************

	var ColorPickerInstance = function() { this.__construct(); };
	ColorPickerInstance.prototype =
	{
		__construct: function()
		{
			this.$picker = $('#ColorPickerInstance').appendTo(document.body);
			this.$picker.overlay(
			{
				close: '.OverlayCloser',
				speed: 0,
				closeSpeed: 0,
				mask:
				{
					color: 'white',
					opacity: 0.6,
					loadSpeed: 0,
					closeSpeed: 0
				}
			});

			this.api = this.$picker.data('overlay');

			this.hsv = {h: 0, s: 0, v: 0};
			this.tempEvents = {};

			_getPalette();
			this.$paletteItems = this.$picker.find('.PaletteTab li');
			this.$paletteItems.each($.context(this, 'initPaletteItem'));

			this.$gradient = this.$picker.find('.gradient');
			this.$gradientCircle = this.$gradient.find('.circle');

			this.$bar = this.$picker.find('.bar');
			this.$barArrow = this.$bar.find('.arrow');

			this.$preview = this.$picker.find('.preview');

			this.$finalValue = this.$picker.find('input.textCtrl.finalValue');
			this.$remove = this.$picker.find('.remove input');

			var $inputs = this.$picker.find('.inputs');
			this.inputs = {
				r: $inputs.find('input[name="r"]'),
				g: $inputs.find('input[name="g"]'),
				b: $inputs.find('input[name="b"]'),
				a: $inputs.find('input[name="a"]')
			};

			this.$hex = $inputs.find('input[name="hex"]');

			this.$gradient.mousedown($.context(this, 'eGradientMouseDown'));
			this.$bar.mousedown($.context(this, 'eBarMouseDown'));

			this.inputs.a.change($.context(this, 'normalizeAlpha'));
			$inputs.find('input[name="r"], input[name="g"], input[name="b"], input[name="a"]').change(
				$.context(this, 'eChangeIndividualInput')
			);
			this.$hex.change($.context(this, 'eChangeHexInput'));
			this.$finalValue.change($.context(this, 'eChangeFinalInput'));
			this.$remove.click($.context(this, 'eRemoveClick'));

			this.$picker.find('.save').click($.context(this, 'save'));

			// trap enter/return on text boxes
			this.$picker.find('input[type=text]').keydown($.context(function(e)
			{
				if (e.keyCode == 13) // enter/return
				{
					e.preventDefault();
					e.target.blur();
					this.save();
				}
			}, this));

			this.tabs = new XenForo.Tabs(this.$picker.find('.ColorPickerTabs .tabs'));
		},

		initPaletteItem: function(x, item)
		{
			$(item).click($.context(this, 'ePaletteItemClick'));
		},

		load: function(saveCallback, currentColor, withPalette)
		{
			console.log('Picker with palette: %b', withPalette);

			this.saveCallback = saveCallback;

			this.updateFromString(currentColor);
			this.updateCurrentColor(currentColor);
			this.updatePreview(true);

			if (!withPalette || !this.$paletteItems.length)
			{
				this.tabs.click(1); // picker
				this.$picker.find('.ColorPickerTabs li:eq(0)').hide();
			}
			else
			{
				this.$picker.find('.ColorPickerTabs li:eq(0)').show();
				if (currentColor && currentColor.substr(0, 1) !== '@')
				{
					this.tabs.click(1); // show picker if non-palette color chosen
				}
				else
				{
					this.tabs.click(0);
				}
			}

			this.api.load();
		},

		updateFromString: function(str)
		{
			var colors;

			str = $.trim(str);
			if (str == '')
			{
				this.$remove.attr('checked', true);
				this.updateInputs('', '', '', '');
				return;
			}

			colors = _matchColorsFromString(str);

			if (colors)
			{
				if (colors.unknown)
				{
					this.updateInputs('', '', '', '');
				}
				else
				{
					this.updateInputs(colors);
					this.normalizeAlpha();
				}
				this.$finalValue.val(colors.value);

				if (colors.value.substr(0, 1) == '@')
				{
					this.selectPaletteItem(colors.value.substr(1));
				}
			}
		},

		selectPaletteItem: function(name)
		{
			this.$paletteItems.each(function() {
				var $this = $(this);
				if ($this.data('colorName') == name)
				{
					$this.addClass('selected');
					selected = name;
				}
				else
				{
					$this.removeClass('selected');
				}
			});
		},

		matchColorsFromString: _matchColorsFromString,

		getRgbFromInputs: function()
		{
			var r = parseInt(this.inputs.r.val(), 10),
				g = parseInt(this.inputs.g.val(), 10),
				b = parseInt(this.inputs.b.val(), 10),
				invalid = false;

			if (isNaN(r) && isNaN(g) && isNaN(b))
			{
				invalid = true;
			}

			r = isNaN(r) ? 0 : r;
			g = isNaN(g) ? 0 : g;
			b = isNaN(b) ? 0 : b;

			return {
				r: r,
				g: g,
				b: b,
				invalid: invalid
			};
		},

		getCssColorFromInputs: function(allowAlpha)
		{
			var color = this.getRgbFromInputs(),
				a = this.inputs.a.val();

			if (color.invalid)
			{
				return '';
			}

			if (allowAlpha && parseFloat(a) < 1)
			{
				return 'rgba(' + color.r + ', ' + color.g + ', ' + color.b + ', ' + a + ')';
			}
			else
			{
				return 'rgb(' + color.r + ', ' + color.g + ', ' + color.b + ')';
			}
		},

		normalizeAlpha: function()
		{
			alpha = parseFloat(this.inputs.a.val());

			if (alpha > 1)
			{
				alpha = String(alpha / 255).substr(0, 4);
			}
			else if (alpha < 0)
			{
				alpha = 0;
			}
			else
			{
				return false;
			}

			this.inputs.a.val(alpha);
			return true;
		},

		ePaletteItemClick: function(e)
		{
			var colorName = $(e.currentTarget).data('colorName'), color;

			if (colorName)
			{
				color = _getPalette()[colorName];
				if (color)
				{
					this.updateInputs(color);
					this.$finalValue.val('@' + colorName);
					this.selectPaletteItem(colorName);
					this.updatePreview(true);
				}
			}
		},

		eGradientMouseDown: function(e)
		{
			e.preventDefault();

			this.handleGradientEventUpdate(e);

			this.tempEvents = {
				mousemove: $.context(this, 'eGradientMouseMove'),
				mouseup: $.context(this, 'eGradientMouseUp')
			};
			$(document).bind(this.tempEvents);
		},

		eGradientMouseMove: function(e)
		{
			this.handleGradientEventUpdate(e);
		},

		eGradientMouseUp: function(e)
		{
			this.unbindTempEvents();
		},

		handleGradientEventUpdate: function(e)
		{
			var offset = this.$gradient.offset(), x, y;

			// -1 adjusts for border
			x = e.pageX - offset.left - 1;
			y = e.pageY - offset.top - 1;

			this.hsv.s = Math.max(0, Math.min(1, x / 255));
			this.hsv.v = Math.max(0, Math.min(1, (255 - y) / 255));

			this.updateInputs(_hsvToRgb(this.hsv), false);
			this.updatePreview(true);
		},

		unbindTempEvents: function()
		{
			for (var key in this.tempEvents)
			{
				$(document).unbind(key, this.tempEvents[key]);
			}

			this.tempEvents = {};
		},

		eBarMouseDown: function(e)
		{
			e.preventDefault();

			this.handleBarEventUpdate(e);

			this.tempEvents = {
				mousemove: $.context(this, 'eBarMouseMove'),
				mouseup: $.context(this, 'eBarMouseUp')
			};
			$(document).bind(this.tempEvents);
		},

		eBarMouseMove: function(e)
		{
			this.handleBarEventUpdate(e);
		},

		eBarMouseUp: function(e)
		{
			this.unbindTempEvents();
		},

		handleBarEventUpdate: function(e)
		{
			var offset = this.$bar.offset(), y;

			y = e.pageY - offset.top - 1; // -1 adjusts for border
			this.hsv.h = Math.max(0, Math.min(359,
				Math.round((255 - y) / 255 * 360)
			));

			this.updateInputs(_hsvToRgb(this.hsv), false);
			this.updatePreview(true);
		},

		eChangeFinalInput: function(e)
		{
			this.$remove.attr('checked', false);
			this.updateFromString(this.$finalValue.val());
			this.updatePreview(true);
		},

		eChangeIndividualInput: function(e)
		{
			var $target = $(e.currentTarget);

			if ($target.val() > 255)
			{
				$target.val(255);
			}
			else if ($target.val() < 0)
			{
				$target.val(0);
			}

			this.updateHexFromInputs();
			this.updateFinalValueFromInputs();
			this.updateHsv();
			this.updatePreview(true);
		},

		eChangeHexInput: function(e)
		{
			this.updateInputsFromHex();
			this.updateFinalValueFromInputs();
			this.updateHsv();
			this.updatePreview(true);
		},

		eRemoveClick: function(e)
		{
			if (this.$remove.attr('checked'))
			{
				this.$finalValue.val('');
				this.selectPaletteItem(false);
			}
			else
			{
				this.updateFinalValueFromInputs();
				this.updateHsv();
			}
			this.updatePreview(true);
		},

		updateCurrentColor: function(currentColor)
		{
			var colors = _matchColorsFromString(currentColor),
				$current = this.$picker.find('.currentPreview');

			if (!colors || colors.unknown)
			{
				$current.css('background', 'transparent');
			}
			else
			{
				$current.css('background', 'rgb(' + colors.r + ', ' + colors.g + ', ' + colors.b + ')');
			}
		},

		updatePreview: function(updatePicker)
		{
			var inputColor = this.getCssColorFromInputs(false);

			if (inputColor == '' || this.$remove.attr('checked'))
			{
				this.$preview.css('background', 'transparent');
			}
			else
			{
				this.$preview.css('background', inputColor);
			}

			if (updatePicker)
			{
				this.updatePicker();
			}
		},

		updatePicker: function()
		{
			var x, y, gradRgb, hueY;

			// + 1 accounts for border
			x = Math.round(this.hsv.s * 255 + 1);
			y = Math.round(255 - (this.hsv.v * 255) + 1);
			hueY = Math.round((359 - this.hsv.h) / 359 * 255 + 1);

			this.$gradientCircle.css({top: y, left: x});
			this.$barArrow.css('top', hueY);

			gradRgb = _hsvToRgb(
			{
				h: this.hsv.h,
				s: 1,
				v: 1
			});
			this.$gradient.css('background-color', 'rgb(' + gradRgb.r + ', ' + gradRgb.g + ', ' + gradRgb.b + ')');
		},

		updateInputs: function(r, g, b, a, updateHsv)
		{
			if (updateHsv === undefined)
			{
				updateHsv = true;
			}

			if (typeof r == 'object')
			{
				if (g !== undefined)
				{
					updateHsv = g;
				}

				g = r.g;
				b = r.b;
				if (r.a !== undefined)
				{
					a = r.a;
				}
				r = r.r;
			}

			this.inputs.r.val(r);
			this.inputs.g.val(g);
			this.inputs.b.val(b);
			if (r !== '' && g !== '' && b !== '')
			{
				this.$hex.val(this._toHexComponent(r) + this._toHexComponent(g) + this._toHexComponent(b));
			}
			else
			{
				this.$hex.val('');
			}

			if (a !== undefined)
			{
				this.inputs.a.val(a);
				this.normalizeAlpha();
			}

			this.updateFinalValueFromInputs();

			if (updateHsv)
			{
				this.updateHsv();
			}
		},

		_toHexComponent: function(v)
		{
			var i = parseInt(v, 10), s;

			if (isNaN(i))
			{
				return '';
			}
			else
			{
				s = i.toString(16);
				if (s.length == 1)
				{
					s = '0' + s;
				}
				return s;
			}
		},

		updateFinalValueFromInputs: function()
		{
			this.$finalValue.val(this.getCssColorFromInputs(true));
			if (this.$finalValue.val())
			{
				this.$remove.attr('checked', false);
			}
			this.selectPaletteItem(false);
		},

		updateInputsFromHex: function()
		{
			var val = this.$hex.val(), colors;

			if (val === '')
			{
				this.updateInputs('', '', '', '');
			}
			else
			{
				colors = _matchColorsFromString(val.substr(0, 1) == '#' ?  val : ('#' + val));
				if (colors)
				{
					this.updateInputs(colors);
				}
				else
				{
					this.$hex.val('');
					this.updateInputs('', '', '', '');
				}
			}
		},

		updateHexFromInputs: function()
		{
			var r = this.inputs.r.val(),
				g = this.inputs.g.val(),
				b = this.inputs.b.val();

			if (r !== '' && g !== '' && b !== '')
			{
				this.$hex.val(this._toHexComponent(r) + this._toHexComponent(g) + this._toHexComponent(b));
			}
			else
			{
				this.$hex.val('');
			}
		},

		updateHsv: function()
		{
			this.hsv = _rgbToHsv(this.getRgbFromInputs());
		},

		save: function()
		{
			var val = $.trim(this.$finalValue.val());
			if (this.$remove.attr('checked'))
			{
				val = '';
			}

			if (this.saveCallback)
			{
				this.saveCallback(val);
			}

			this.api.close();
		}
	};

	// *********************************************************************

	XenForo.register('input.ColorPicker', 'XenForo.ColorPicker');

	XenForo.register('input.ColorInvert', 'XenForo.ColorInvert');

	XenForo.register('input.HueShift', 'XenForo.HueShift');
}
(jQuery, this, document);
