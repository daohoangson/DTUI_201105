/*
 * The following functions are bespoke and thus covered by a proprietary license.
 */
(function() {
	var wrapBbCode = function(editor, tag)
	{
		var selection = editor.selection,
			content, range, caret;

		content = '[' + tag + ']'
			+ selection.getContent().replace(/^<p>/, '').replace(/<\/p>$/, '')
			+ '<span id="__xfBbCaret">_</span>'
			+ '[/' + tag + ']';

		content = '<p>' + content + '</p>';
		selection.setContent(content);

		if (editor.getDoc().createRange)
		{
			caret = editor.dom.get('__xfBbCaret');
			range = editor.getDoc().createRange();
			range.setStartBefore(caret);
			range.setEndBefore(caret);
			selection.setRng(range);
		}
		editor.dom.remove('__xfBbCaret');
	};

	tinymce.create('tinymce.plugins.XenForoCustomBbCode', {
		init : function (ed, url)
		{
			ed.addCommand('xenForoWrapBbCode', function(ui, val)
			{
				wrapBbCode(ed, val);
			});
		},

		addButtons : function (theme, tb)
		{
			var tags = theme.settings.xenforo_custom_bbcode_tags,
				controlManager = theme.editor.controlManager;

			//tb.add(controlManager.createControl('|'));

			tb.add(controlManager.createButton('xenforo_quote',
				{ title: 'xenforo.quote', cmd: 'xenForoWrapBbCode', ui: false, value: 'QUOTE' }
			));

			if (typeof tags === 'undefined' || tags.length === 0 || tags === {})
			{
				return;
			}

			tinymce.each(tags, function(tag, tagName) {
				tb.add(controlManager.createButton('xenforo_custom_bbcode_' + tag,
					{title : tag[0], image : tag[1], cmd : 'xenForoWrapBbCode', ui : false, value : tagName}
				));
			});
		},

		getInfo : function()
		{
			return {
				longname : 'XenForo Custom BB Code',
				author : '',
				version : '1.0'
			};
		}
	});

	tinymce.create('tinymce.plugins.XenForoSmilies', {
		addButtons : function (theme, tb)
		{
			var smilies = theme.settings.xenforo_smilies,
				controlManager = theme.editor.controlManager,
				editor = theme.editor,
				button, DOM = tinymce.DOM;

			if (typeof smilies === 'undefined' || smilies.length === 0 || smilies === {})
			{
				return;
			}

			button = controlManager.createColorSplitButton('xenforo_smilies', {
				title : 'xenforo.smilies_desc',
				image : theme.settings.xenforo_smilies_menu_image,
				icons : false,
				onclick : function() { button.showMenu(); },
				onselect: function(s, txt) {
					console.log(this);
					editor.execCommand('mceInsertContent', false,
						'<img src="' + DOM.encode(s) + '" alt="' + DOM.encode(txt) + '" class="smilie" data-smilie="yes" />'
					);
				},
				smilies: smilies
			}, tinymce.ui.XenForoSmiliesButton);
			tb.add(button);
		},

		getInfo : function()
		{
			return {
				longname : 'XenForo Smilies',
				author : '',
				version : '1.0'
			};
		}
	});

	tinymce.create('tinymce.plugins.XenForoMedia', {
		addButtons : function (theme, tb)
		{
			var controlManager = theme.editor.controlManager,
				ed = theme.editor,
				button, DOM = tinymce.DOM;

			button = controlManager.createButton('xenforo_media', {
				title : 'xenforo.media_desc',
				onclick : function(ui, val)
				{
					ed.windowManager.open({
						url : theme._getFullDialogUrl('media'),
						width : 310,
						height : 300,
						inline : true
					});
				}
			});
			tb.add(button);
		},

		getInfo : function()
		{
			return {
				longname : 'XenForo Media',
				author : '',
				version : '1.0'
			};
		}
	});

	tinymce.create('tinymce.plugins.XenForoCode', {
		addButtons : function (theme, tb)
		{
			var controlManager = theme.editor.controlManager,
				ed = theme.editor,
				button, DOM = tinymce.DOM;

			button = controlManager.createButton('xenforo_code', {
				title : 'xenforo.code',
				onclick : function(ui, val)
				{
					ed.windowManager.open({
						url : theme._getFullDialogUrl('code'),
						width : 400,
						height : 300,
						inline : true
					});
				}
			});
			tb.add(button);
		},

		getInfo : function()
		{
			return {
				longname : 'XenForo Code',
				author : '',
				version : '1.0'
			};
		}
	});

	tinymce.create('tinymce.plugins.XenForoElastic', {
		init: function(ed)
		{
			if ($.browser.msie && $.browser.version <= 6)
			{
				return;
			}

			var maxHeight,
				minHeight, curHeight = 0,
				$iframe, eventInit, eventResize;

			eventInit = function()
			{
				$iframe = $(ed.getContentAreaContainer()).find('iframe');

				minHeight = $(ed.getWin()).height();

				maxHeight = $(window).height() - 200;
				if ($iframe.closest('.xenOverlay').length)
				{
					maxHeight -= (maxHeight + 200) / 10;
				}
				maxHeight = Math.max(maxHeight, minHeight);

				$(ed.getBody()).css('overflow-y', 'hidden');
				eventResize();
				setTimeout(eventResize, 250);
			};
			eventResize = function()
			{
				if (!$iframe)
				{
					return;
				}

				var height = ed.getDoc().documentElement.scrollHeight,
					diff, $container,
					docHeightAsScroll = ($.browser.webkit || ($.browser.msie && $.browser.version >= 9));

				if (!docHeightAsScroll)
				{
					height += 22; // gives some space and stops scroll bar in IE
				}

				if (height < minHeight)
				{
					height = minHeight;
				}
				else if (height > maxHeight)
				{
					height = maxHeight;
				}

				if (height != curHeight)
				{
					if (!$.browser.msie || $.browser.version >= 9) // IE doesn't need this ?!?! (full size images cause problems with this)
					{
						if (curHeight < height && height == maxHeight)
						{
							$(ed.getBody()).css('overflow-y', 'auto');
						}
						else if (curHeight == maxHeight && height < maxHeight)
						{
							$(ed.getBody()).css('overflow-y', 'hidden');
						}
					}

					$iframe.height(height);
					curHeight = height;
				}
			};

			ed.onInit.add(eventInit);

			ed.onSetContent.add(eventResize);
			ed.onPaste.add(eventResize);
			ed.onChange.add(eventResize);
			ed.onKeyDown.add(eventResize);

			ed.addCommand('xenForoElastic', function(ui, val) { eventResize(); });
		},

		getInfo : function()
		{
			return {
				longname : 'XenForo Elastic',
				author : '',
				version : '1.0'
			};
		}
	});

	tinymce.create('tinymce.plugins.XenForoBbCodeSwitch', {
		init: function(ed)
		{
			var t = this;
			t.editor = ed;

			ed.onPostRender.add(function() {
				$('<a />')
					.attr('class', 'mceButton mceButtonEnabled bbCodeEditorButton')
					.append(
						$('<span />')
							.attr({
								title: ed.settings.xenforo_bbcode_switch_text[0],
								'class': 'mceIcon'
							})
							.click($.context(t, 'wysiwygToBbCode'))
					)
					.prependTo('#' + ed.id + '_tbl td.mceToolbar');
			});
		},

		wysiwygToBbCode: function()
		{
			XenForo.ajax(
				'index.php?editor/to-bb-code',
				{ html: this.editor.getContent() },
				$.context(this, 'wysiwygToBbCodeSuccess')
			);
		},

		wysiwygToBbCodeSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData) || typeof(ajaxData.bbCode) == 'undefined')
			{
				return;
			}

			var editor = this.editor;
				$container = $(editor.getContainer()),
				$existingTextArea = $(editor.getElement()),
				$textContainer = $('<div class="bbCodeEditorContainer" />'),
				$newTextArea = $('<textarea class="textCtrl Elastic" rows="5" />');

			if ($existingTextArea.attr('disabled'))
			{
				return; // already using this
			}

			$newTextArea
				.attr('name', $existingTextArea.attr('name').replace(/_html(]|$)/, ''))
				.val(ajaxData.bbCode)
				.appendTo($textContainer);

			$('<a />')
				.attr('href', 'javascript:')
				.text(editor.settings.xenforo_bbcode_switch_text[1])
				.click($.context(this, 'bbCodeToWysiwyg'))
				.appendTo(
					$('<div />').appendTo($textContainer)
				);

			$existingTextArea
				.attr('disabled', true)
				.after($textContainer);

			if ($.browser.mozilla)
			{
				// reloading the page needs to remove this as it will start in wysiwyg mode
				$(window)
					.unbind('unload.rte')
					.bind('unload.rte', function() {
						$existingTextArea.removeAttr('disabled');
					});
			}

			$container.hide();

			$textContainer.xfActivate();

			$newTextArea.focus();

			this.$bbCodeTextContainer = $textContainer;
			this.$bbCodeTextArea = $newTextArea;
		},

		bbCodeToWysiwyg: function()
		{
			XenForo.ajax(
				'index.php?editor/to-html',
				{ bbCode: this.$bbCodeTextArea.val() },
				$.context(this, 'bbCodeToWysiwygSuccess')
			);
		},

		bbCodeToWysiwygSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData) || typeof(ajaxData.html) == 'undefined')
			{
				return;
			}

			var editor = this.editor;
				$container = $(editor.getContainer()),
				$existingTextArea = $(editor.getElement());

			if (!$existingTextArea.attr('disabled'))
			{
				return; // already using
			}

			$existingTextArea.attr('disabled', false);

			$container.show();

			editor.setContent(ajaxData.html);
			editor.focus();

			this.$bbCodeTextContainer.remove();
		},

		getInfo : function()
		{
			return {
				longname : 'XenForo BB Code Switch',
				author : '',
				version : '1.0'
			};
		}
	});

	tinymce.PluginManager.add('xenforo_custom_bbcode', tinymce.plugins.XenForoCustomBbCode);
	tinymce.PluginManager.add('xenforo_smilies', tinymce.plugins.XenForoSmilies);
	tinymce.PluginManager.add('xenforo_media', tinymce.plugins.XenForoMedia);
	tinymce.PluginManager.add('xenforo_code', tinymce.plugins.XenForoCode);
	tinymce.PluginManager.add('xenforo_elastic', tinymce.plugins.XenForoElastic);
	tinymce.PluginManager.add('xenforo_bbcode_switch', tinymce.plugins.XenForoBbCodeSwitch);
})();

/**
 * Derived from tinymce.ui.ColorSplitButton plugin. Modifications for XenForo.
 *
 * Original code copyright 2009, Moxiecode Systems AB
 * Released under LGPL License.
 *
 * License: http://tinymce.moxiecode.com/license
 * Contributing: http://tinymce.moxiecode.com/contributing
 */
(function(tinymce) {
	var DOM = tinymce.DOM, Event = tinymce.dom.Event, is = tinymce.is, each = tinymce.each;

	tinymce.create('tinymce.ui.XenForoSmiliesButton:tinymce.ui.SplitButton', {
		XenForoSmiliesButton : function(id, s) {
			var t = this;

			t.parent(id, s);

			t.settings = s = tinymce.extend({
				smilies: {}
			}, t.settings);

			t.onShowMenu = new tinymce.util.Dispatcher(t);
			t.onHideMenu = new tinymce.util.Dispatcher(t);
		},

		showMenu : function() {
			var t = this, r, p, e, p2;

			if (t.isDisabled())
				return;

			if (!t.isMenuRendered) {
				t.renderMenu();
				t.isMenuRendered = true;
			}

			if (t.isMenuVisible)
				return t.hideMenu();

			e = DOM.get(t.id);
			DOM.show(t.id + '_menu');
			DOM.addClass(e, 'mceSplitButtonSelected');
			p2 = DOM.getPos(e);
			DOM.setStyles(t.id + '_menu', {
				left : p2.x,
				top : p2.y + e.clientHeight,
				zIndex : 200000
			});
			e = 0;

			Event.add(DOM.doc, 'mousedown', t.hideMenu, t);

			if (t._focused) {
				t._keyHandler = Event.add(t.id + '_menu', 'keydown', function(e) {
					if (e.keyCode == 27)
						t.hideMenu();
				});

				DOM.select('a', t.id + '_menu')[0].focus(); // Select first link
			}

			t.isMenuVisible = 1;
		},

		hideMenu : function(e) {
			var t = this;

			// Prevent double toogles by canceling the mouse click event to the button
			if (e && e.type == "mousedown" && DOM.getParent(e.target, function(e) {return e.id === t.id + '_open';}))
				return;

			if (!e || !DOM.getParent(e.target, '.mceSplitButtonMenu')) {
				DOM.removeClass(t.id, 'mceSplitButtonSelected');
				Event.remove(DOM.doc, 'mousedown', t.hideMenu, t);
				Event.remove(t.id + '_menu', 'keydown', t._keyHandler);
				DOM.hide(t.id + '_menu');
			}

			t.isMenuVisible = 0;
		},

		renderMenu : function() {
			var t = this, m, i = 0, s = t.settings, n, tb, tr, w;

			w = DOM.add(s.menu_container, 'div', {id : t.id + '_menu', 'class' : s['menu_class'] + ' ' + s['class'], style : 'position:absolute;left:0;top:-1000px;'});
			m = DOM.add(w, 'div', {'class' : s['class'] + ' mceSplitButtonMenu mceSmiliesMenu'});
			DOM.add(m, 'span', {'class' : 'mceMenuLine'});

			n = DOM.add(m, 'div');

			i = 0;
			each(s.smilies, function(smilie, smilieName)
			{
				DOM.add(n, 'a', {
					href : 'javascript:;',
					'data-smilieUrl' : smilie[1],
					'data-smilieText' : smilieName
				}, '<img src="' + DOM.encode(smilie[1]) + '" alt="" title="' + DOM.encode(smilie[0] + '    ' + smilieName) + '" />');
			});

			Event.add(t.id + '_menu', 'click', function(e) {
				var c, $t;

				e = e.target;
				$t = $(e).closest('[data-smilieUrl]');

				if ($t.length)
				{
					t.settings.onselect($t.data('smilieUrl'), $t.data('smilieText'));
					t.hideMenu();
				}

				return Event.cancel(e); // Prevent IE auto save warning
			});

			return w;
		},

		destroy : function() {
			this.parent();

			Event.clear(this.id + '_menu');
			DOM.remove(this.id + '_menu');
		}
	});
})(tinymce);

/**
 * Derived from inlinepopups plugin. Modifications for XenForo.
 *
 * Original code copyright 2009, Moxiecode Systems AB
 * Released under LGPL License.
 *
 * License: http://tinymce.moxiecode.com/license
 * Contributing: http://tinymce.moxiecode.com/contributing
 */
(function() {
	var DOM = tinymce.DOM, Element = tinymce.dom.Element, Event = tinymce.dom.Event, each = tinymce.each, is = tinymce.is;

	tinymce.create('tinymce.plugins.XenForoInlinePopups', {
		init : function(ed, url) {
			// Replace window manager
			ed.onBeforeRenderUI.add(function() {
				ed.windowManager = new tinymce.XenForoInlineWindowManager(ed);
			});
		},

		getInfo : function() {
			return {
				longname : 'XenForoInlinePopups',
				author : 'Moxiecode Systems AB, modifications for XenForo',
				version : '1.0'
			};
		}
	});

	tinymce.create('tinymce.XenForoInlineWindowManager:tinymce.WindowManager', {
		XenForoInlineWindowManager : function(ed) {
			var t = this;

			t.parent(ed);
			t.zIndex = 300000;
			t.count = 0;
			t.windows = {};
		},

		open : function(f, p) {
			var t = this, id, opt = '', ed = t.editor, dw = 0, dh = 0, vp, po, mdf, clf, we, w, u;

			f = f || {};
			p = p || {};

			// Run native windows
			if (!f.inline)
				return t.parent(f, p);

			// Only store selection if the type is a normal window
			if (!f.type)
				t.bookmark = ed.selection.getBookmark(1);

			id = DOM.uniqueId();
			vp = DOM.getViewPort();
			f.width = parseInt(f.width || 320);
			f.height = parseInt(f.height || 240) + (tinymce.isIE ? 8 : 0);
			f.min_width = parseInt(f.min_width || 150);
			f.min_height = parseInt(f.min_height || 100);
			f.max_width = parseInt(f.max_width || 2000);
			f.max_height = parseInt(f.max_height || 2000);
			f.left = f.left || Math.round(Math.max(vp.x, vp.x + (vp.w / 2.0) - (f.width / 2.0)));
			f.top = f.top || Math.round(Math.max(vp.y, vp.y + (vp.h / 2.0) - (f.height / 2.0)));
			f.movable = f.resizable = true;
			p.mce_width = f.width;
			p.mce_height = f.height;
			p.mce_inline = true;
			p.mce_window_id = id;
			p.mce_auto_focus = f.auto_focus;

			// Transpose
//			po = DOM.getPos(ed.getContainer());
//			f.left -= po.x;
//			f.top -= po.y;

			t.features = f;
			t.params = p;
			t.onOpen.dispatch(t, f, p);

			if (f.type) {
				opt += ' mceModal';

				if (f.type)
					opt += ' mce' + f.type.substring(0, 1).toUpperCase() + f.type.substring(1);

				f.resizable = false;
			}

			t._addAll(DOM.doc.body,
				['div', {id: id, 'class': 'editorInlinePopup', style: 'width:100px; height:100px'},
					['div', {id: id + '_content', 'class': 'popupContent', style: 'height:100%'}]
				]
			);

			DOM.setStyles(id, {top : -10000, left : -10000});
			DOM.setStyles(id, {top : f.top, left : f.left, width : f.width + dw});

			u = f.url || f.file;
			if (u) {
				if (tinymce.relaxedDomain)
					u += (u.indexOf('?') == -1 ? '?' : '&') + 'mce_rdomain=' + tinymce.relaxedDomain;

				u = tinymce._addVer(u);
			}

			if (!f.type) {
				DOM.add(id + '_content', 'iframe', {id : id + '_ifr', src : 'javascript:;', frameBorder : 0, scrolling: 'no', style : 'border:0; width:100%; height: 100%;'});
				Event.add(id + '_ifr', 'load', function(e) {
					DOM.setStyles(id, { height: e.target.contentWindow.document.documentElement.scrollHeight });
					DOM.setStyles(id + '_ifr', { height: e.target.contentWindow.document.documentElement.scrollHeight });
				});
				DOM.setAttrib(id + '_ifr', 'src', u);
				DOM.get(id + '_ifr').focus();
			} else {
				DOM.add(id + '_wrapper', 'a', {id : id + '_ok', 'class' : 'mceButton mceOk', href : 'javascript:;', onmousedown : 'return false;'}, 'Ok');

				if (f.type == 'confirm')
					DOM.add(id + '_wrapper', 'a', {'class' : 'mceButton mceCancel', href : 'javascript:;', onmousedown : 'return false;'}, 'Cancel');

				DOM.add(id + '_middle', 'div', {'class' : 'mceIcon'});
				DOM.setHTML(id + '_content', f.content.replace('\n', '<br />'));
			}

			// Add window
			w = t.windows[id] = {
				id : id,
				mousedown_func : mdf,
				click_func : clf,
				element : new Element(id, {blocker : 1, container : ed.getContainer()}),
				iframeElement : new Element(id + '_ifr'),
				features : f,
				deltaWidth : dw,
				deltaHeight : dh
			};

			w.iframeElement.on('focus', function() {
				t.focus(id);
			});

			// Setup blocker
			if (t.count == 0 && t.editor.getParam('dialog_type', 'modal') == 'modal') {
				DOM.add(DOM.doc.body, 'div', {
					id : 'mceModalBlocker',
					'class' : 'editorInlinePopup_modalBlocker',
					style : {zIndex : t.zIndex - 1}
				});
				Event.add('mceModalBlocker', 'click', function()
				{
					t.close(null, id);
				});

				DOM.show('mceModalBlocker'); // Reduces flicker in IE
			} else
				DOM.setStyle('mceModalBlocker', 'z-index', t.zIndex - 1);

			if (tinymce.isIE6 || /Firefox\/2\./.test(navigator.userAgent) || (tinymce.isIE && !DOM.boxModel))
				DOM.setStyles('mceModalBlocker', {position : 'absolute', left : vp.x, top : vp.y, width : vp.w - 2, height : vp.h - 2});

			t.focus(id);
			t._fixIELayout(id, 1);

			// Focus ok button
			if (DOM.get(id + '_ok'))
				DOM.get(id + '_ok').focus();

			t.count++;

			return w;
		},

		focus : function(id) {
			var t = this, w;

			if (w = t.windows[id]) {
				w.zIndex = this.zIndex++;
				w.element.setStyle('zIndex', w.zIndex);
				w.element.update();

				id = id + '_wrapper';
				DOM.removeClass(t.lastId, 'mceFocus');
				DOM.addClass(id, 'mceFocus');
				t.lastId = id;
			}
		},

		_addAll : function(te, ne) {
			var i, n, t = this, dom = tinymce.DOM;

			if (is(ne, 'string'))
				te.appendChild(dom.doc.createTextNode(ne));
			else if (ne.length) {
				te = te.appendChild(dom.create(ne[0], ne[1]));

				for (i=2; i<ne.length; i++)
					t._addAll(te, ne[i]);
			}
		},

		resizeBy : function(dw, dh, id) {
			return;

			var w = this.windows[id];

			if (w) {
				w.element.resizeBy(dw, dh);
				w.iframeElement.resizeBy(dw, dh);
			}
		},

		close : function(win, id) {
			var t = this, w, d = DOM.doc, ix = 0, fw, id;

			id = t._findId(id || win);

			// Probably not inline
			if (!t.windows[id]) {
				t.parent(win);
				return;
			}

			t.count--;

			if (t.count == 0)
				DOM.remove('mceModalBlocker');

			if (w = t.windows[id]) {
				t.onClose.dispatch(t);
				Event.remove(d, 'mousedown', w.mousedownFunc);
				Event.remove(d, 'click', w.clickFunc);
				Event.clear(id);
				Event.clear(id + '_ifr');

				DOM.setAttrib(id + '_ifr', 'src', 'javascript:""'); // Prevent leak
				w.element.remove();
				delete t.windows[id];

				// Find front most window and focus that
				each (t.windows, function(w) {
					if (w.zIndex > ix) {
						fw = w;
						ix = w.zIndex;
					}
				});

				if (fw)
					t.focus(fw.id);
			}
		},

		setTitle : function(w, ti) {
			var e;

			w = this._findId(w);

			if (e = DOM.get(w + '_title'))
				e.innerHTML = DOM.encode(ti);
		},

		alert : function(txt, cb, s) {
			var t = this, w;

			w = t.open({
				title : t,
				type : 'alert',
				button_func : function(s) {
					if (cb)
						cb.call(s || t, s);

					t.close(null, w.id);
				},
				content : DOM.encode(t.editor.getLang(txt, txt)),
				inline : 1,
				width : 400,
				height : 130
			});
		},

		confirm : function(txt, cb, s) {
			var t = this, w;

			w = t.open({
				title : t,
				type : 'confirm',
				button_func : function(s) {
					if (cb)
						cb.call(s || t, s);

					t.close(null, w.id);
				},
				content : DOM.encode(t.editor.getLang(txt, txt)),
				inline : 1,
				width : 400,
				height : 130
			});
		},

		// Internal functions

		_findId : function(w) {
			var t = this;

			if (typeof(w) == 'string')
				return w;

			each(t.windows, function(wo) {
				var ifr = DOM.get(wo.id + '_ifr');

				if (ifr && w == ifr.contentWindow) {
					w = wo.id;
					return false;
				}
			});

			return w;
		},

		_fixIELayout : function(id, s) {
			var w, img;

			if (!tinymce.isIE6)
				return;

			// Fixes graphics glitch
			if (w = this.windows[id]) {
				// Fixes rendering bug after resize
				w.element.hide();
				w.element.show();

				// Forced a repaint of the window
				//DOM.get(id).style.filter = '';

				// IE has a bug where images used in CSS won't get loaded
				// sometimes when the cache in the browser is disabled
				// This fix tries to solve it by loading the images using the image object
				each(DOM.select('div,a', id), function(e, i) {
					if (e.currentStyle.backgroundImage != 'none') {
						img = new Image();
						img.src = e.currentStyle.backgroundImage.replace(/url\(\"(.+)\"\)/, '$1');
					}
				});

				DOM.get(id).style.filter = '';
			}
		}
	});

	// Register plugin
	tinymce.PluginManager.add('xenforo_inline_popups', tinymce.plugins.XenForoInlinePopups);
})();

/*
 * Below this point is derived from TinyMCE's advanced theme and thus a derivative
 * of LGPL-licensed code.
 */
(function(tinymce) {
	var DOM = tinymce.DOM, Event = tinymce.dom.Event, extend = tinymce.extend, each = tinymce.each, Cookie = tinymce.util.Cookie, lastExtID, explode = tinymce.explode;

	tinymce.create('tinymce.themes.XenForoTheme', {
		sizes : [8, 10, 12, 14, 18, 24, 36],

		// Control name lookup, format: title, command
		controls : {
			bold : ['bold_desc', 'Bold'],
			italic : ['italic_desc', 'Italic'],
			underline : ['underline_desc', 'Underline'],
			strikethrough : ['strikethrough_desc', 'Strikethrough'],
			justifyleft : ['justifyleft_desc', 'JustifyLeft'],
			justifycenter : ['justifycenter_desc', 'JustifyCenter'],
			justifyright : ['justifyright_desc', 'JustifyRight'],
			justifyfull : ['justifyfull_desc', 'JustifyFull'],
			bullist : ['bullist_desc', 'InsertUnorderedList'],
			numlist : ['numlist_desc', 'InsertOrderedList'],
			outdent : ['outdent_desc', 'Outdent'],
			indent : ['indent_desc', 'Indent'],
			cut : ['cut_desc', 'Cut'],
			copy : ['copy_desc', 'Copy'],
			paste : ['paste_desc', 'Paste'],
			undo : ['undo_desc', 'Undo'],
			redo : ['redo_desc', 'Redo'],
			link : ['link_desc', 'mceLink'],
			unlink : ['unlink_desc', 'unlink'],
			image : ['image_desc', 'mceImage'],
			cleanup : ['cleanup_desc', 'mceCleanup'],
			help : ['help_desc', 'mceHelp'],
			code : ['code_desc', 'mceCodeEditor'],
			hr : ['hr_desc', 'InsertHorizontalRule'],
			removeformat : ['removeformat_desc', 'RemoveFormat'],
			sub : ['sub_desc', 'subscript'],
			sup : ['sup_desc', 'superscript'],
			forecolor : ['forecolor_desc', 'ForeColor'],
			forecolorpicker : ['forecolor_desc', 'mceForeColor'],
			backcolor : ['backcolor_desc', 'HiliteColor'],
			backcolorpicker : ['backcolor_desc', 'mceBackColor'],
			charmap : ['charmap_desc', 'mceCharMap'],
			visualaid : ['visualaid_desc', 'mceToggleVisualAid'],
			anchor : ['anchor_desc', 'mceInsertAnchor'],
			blockquote : ['blockquote_desc', 'mceBlockQuote']
		},

		stateControls : ['bold', 'italic', 'underline', 'strikethrough', 'bullist', 'numlist', 'justifyleft', 'justifycenter', 'justifyright', 'justifyfull', 'sub', 'sup', 'blockquote'],

		init : function(ed, url) {
			var t = this, s, v, o;

			t.editor = ed;
			t.url = url;
			t.onResolveName = new tinymce.util.Dispatcher(this);

			if (!ed.settings.skin || ed.settings.skin == 'default')
			{
				ed.settings.skin = 'xenForo';
			}

			ed.settings.popup_css = false;
			ed.settings.language = 'xenforo';
			ed.settings.paste_remove_styles_if_webkit = false;
			ed.settings.convert_urls = false;
			ed.settings.gecko_spellcheck = true;
			ed.settings.entities = '160,nbsp,38,amp,34,quot,60,lt,62,gt';

			ed.onPostProcess.add(function(ed, o) {
				o.content = o.content.replace(/\t/g, '&nbsp;&nbsp;&nbsp; ');
			});

			// Default settings
			t.settings = s = extend({
				theme_xenforo_path : true,
				theme_xenforo_buttons1 : 'removeformat,|,fontselect,fontsizeselect,forecolor,xenforo_smilies,|,undo,redo',
				theme_xenforo_buttons2 : 'bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,|,bullist,numlist,outdent,indent,|,link,unlink,image,xenforo_media,|,xenforo_code,xenforo_custom_bbcode',
				theme_xenforo_buttons3 : '',
				theme_xenforo_toolbar_location : 'top',
				theme_xenforo_toolbar_align : 'left',
				theme_xenforo_blockformats : "p,address,pre,h1,h2,h3,h4,h5,h6",
				theme_xenforo_fonts : "Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Tahoma=tahoma,arial,helvetica,sans-serif;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva",
				theme_xenforo_more_colors : 1,
				theme_xenforo_row_height : 23,
				theme_xenforo_resize_horizontal : 1,
				theme_xenforo_resizing_use_cookie : 1,
				theme_xenforo_font_sizes : "1,2,3,4,5,6,7",
				theme_xenforo_font_selector : "span",
				theme_xenforo_dialog_url : '',
				readonly : ed.settings.readonly
			}, ed.settings);

			ed.settings.formats = {
				xenForoRemoveFormat: [
					{selector : 'h1,h2,h3,h4,h5,h6', remove : 'all', split : true, expand : false, block_expand : true, deep : true}
				]
			};

			if (tinymce.is(s.theme_xenforo_font_sizes, 'string')) {
				s.font_size_style_values = tinymce.explode(s.font_size_style_values);
				s.font_size_classes = tinymce.explode(s.font_size_classes || '');

				// Parse string value
				o = {};
				ed.settings.theme_xenforo_font_sizes = s.theme_xenforo_font_sizes;
				each(ed.getParam('theme_xenforo_font_sizes', '', 'hash'), function(v, k) {
					var cl;

					if (k == v && v >= 1 && v <= 7) {
						//k = v + ' (' + t.sizes[v - 1] + 'pt)';

						if (ed.settings.convert_fonts_to_spans) {
							cl = s.font_size_classes[v - 1];
							v = s.font_size_style_values[v - 1] || (t.sizes[v - 1] + 'pt');
						}
					}

					if (/^\s*\./.test(v))
						cl = v.replace(/\./g, '');

					o[k] = cl ? {'class' : cl} : {fontSize : v};
				});

				s.theme_xenforo_font_sizes = o;
			}

			if ((v = s.theme_xenforo_path_location) && v != 'none')
				s.theme_xenforo_statusbar_location = s.theme_xenforo_path_location;

			if (s.theme_xenforo_statusbar_location == 'none')
				s.theme_xenforo_statusbar_location = 0;

			// Init editor
			ed.onInit.add(function() {
				ed.onNodeChange.add(t._nodeChanged, t);

				ed.dom.loadCSS(s.theme_xenforo_contents_css_url);
			});

			ed.onSetProgressState.add(function(ed, b, ti) {
				var co, id = ed.id, tb;

				if (b) {
					t.progressTimer = setTimeout(function() {
						co = ed.getContainer();
						co = co.insertBefore(DOM.create('DIV', {style : 'position:relative'}), co.firstChild);
						tb = DOM.get(ed.id + '_tbl');

						DOM.add(co, 'div', {id : id + '_blocker', 'class' : 'mceBlocker', style : {width : tb.clientWidth + 2, height : tb.clientHeight + 2}});
						DOM.add(co, 'div', {id : id + '_progress', 'class' : 'mceProgress', style : {left : tb.clientWidth / 2, top : tb.clientHeight / 2}});
					}, ti || 0);
				} else {
					DOM.remove(id + '_blocker');
					DOM.remove(id + '_progress');
					clearTimeout(t.progressTimer);
				}
			});
		},

		createControl : function(n, cf) {
			var cd, c;

			if (c = cf.createControl(n))
				return c;

			switch (n) {
				case "styleselect":
					return this._createStyleSelect();

				case "formatselect":
					return this._createBlockFormats();

				case "fontselect":
					return this._createFontSelect();

				case "fontsizeselect":
					return this._createFontSizeSelect();

				case "forecolor":
					return this._createForeColorMenu();

				case "backcolor":
					return this._createBackColorMenu();
			}

			if ((cd = this.controls[n]))
				return cf.createButton(n, {title : "xenforo." + cd[0], cmd : cd[1], ui : cd[2], value : cd[3]});
		},

		execCommand : function(cmd, ui, val) {
			var f = this['_' + cmd];

			if (f) {
				f.call(this, ui, val);
				return true;
			}

			return false;
		},

		_importClasses : function(e) {
			var ed = this.editor, c = ed.controlManager.get('styleselect');

			if (c.getLength() == 0) {
				each(ed.dom.getClasses(), function(o) {
					c.add(o['class'], o['class']);
				});
			}
		},

		_createStyleSelect : function(n) {
			var t = this, ed = t.editor, cf = ed.controlManager, c = cf.createListBox('styleselect', {
				title : 'xenforo.style_select',
				onselect : function(v) {
					if (c.selectedValue === v) {
						ed.execCommand('mceSetStyleInfo', 0, {command : 'removeformat'});
						c.select();
						return false;
					} else
						ed.execCommand('mceSetCSSClass', 0, v);
				}
			});

			if (c) {
				each(ed.getParam('theme_xenforo_styles', '', 'hash'), function(v, k) {
					if (v)
						c.add(t.editor.translate(k), v);
				});

				c.onPostRender.add(function(ed, n) {
					if (!c.NativeListBox) {
						Event.add(n.id + '_text', 'focus', t._importClasses, t);
						Event.add(n.id + '_text', 'mousedown', t._importClasses, t);
						Event.add(n.id + '_open', 'focus', t._importClasses, t);
						Event.add(n.id + '_open', 'mousedown', t._importClasses, t);
					} else
						Event.add(n.id, 'focus', t._importClasses, t);
				});
			}

			return c;
		},

		_createFontSelect : function() {
			var c, t = this, ed = t.editor;

			c = ed.controlManager.createListBox('fontselect', {title : 'xenforo.fontdefault', cmd : 'FontName'});
			if (c) {
				each(ed.getParam('theme_xenforo_fonts', t.settings.theme_xenforo_fonts, 'hash'), function(v, k) {
					c.add(ed.translate(k), v, {style : v.indexOf('dings') == -1 ? 'font-family:' + v : ''});
				});
			}

			return c;
		},

		_createFontSizeSelect : function() {
			var t = this, ed = t.editor, c, i = 0, cl = [];

			c = ed.controlManager.createListBox('fontsizeselect', {title : 'xenforo.font_size', onselect : function(v) {
				if (v.fontSize)
					ed.execCommand('FontSize', false, v.fontSize);
				else {
					each(t.settings.theme_xenforo_font_sizes, function(v, k) {
						if (v['class'])
							cl.push(v['class']);
					});

					ed.editorCommands._applyInlineStyle('span', {'class' : v['class']}, {check_classes : cl});
				}
			}});

			if (c) {
				each(t.settings.theme_xenforo_font_sizes, function(v, k) {
					var fz = v.fontSize;

					if (fz >= 1 && fz <= 7)
						fz = t.sizes[parseInt(fz) - 1] + 'pt';

					c.add(k, v, {'style' : 'font-size:' + fz, 'class' : 'mceFontSize' + (i++) + (' ' + (v['class'] || ''))});
				});
			}

			return c;
		},

		_createBlockFormats : function() {
			var c, fmts = {
				p : 'xenforo.paragraph',
				address : 'xenforo.address',
				pre : 'xenforo.pre',
				h1 : 'xenforo.h1',
				h2 : 'xenforo.h2',
				h3 : 'xenforo.h3',
				h4 : 'xenforo.h4',
				h5 : 'xenforo.h5',
				h6 : 'xenforo.h6',
				div : 'xenforo.div',
				blockquote : 'xenforo.blockquote',
				code : 'xenforo.code',
				dt : 'xenforo.dt',
				dd : 'xenforo.dd',
				samp : 'xenforo.samp'
			}, t = this;

			c = t.editor.controlManager.createListBox('formatselect', {title : 'xenforo.block', cmd : 'FormatBlock'});
			if (c) {
				each(t.editor.getParam('theme_xenforo_blockformats', t.settings.theme_xenforo_blockformats, 'hash'), function(v, k) {
					c.add(t.editor.translate(k != v ? k : fmts[v]), v, {'class' : 'mce_formatPreview mce_' + v});
				});
			}

			return c;
		},

		_createForeColorMenu : function() {
			var c, t = this, s = t.settings, o = {}, v;

			if (s.theme_xenforo_more_colors) {
				o.more_colors_func = function() {
					t._mceColorPicker(0, {
						color : c.value,
						func : function(co) {
							c.setColor(co);
						}
					});
				};
			}

			if (v = s.theme_xenforo_text_colors)
				o.colors = v;

			if (s.theme_xenforo_default_foreground_color)
				o.default_color = s.theme_xenforo_default_foreground_color;

			o.title = 'xenforo.forecolor_desc';
			o.cmd = 'ForeColor';
			o.scope = this;

			c = t.editor.controlManager.createColorSplitButton('forecolor', o);

			return c;
		},

		_createBackColorMenu : function() {
			var c, t = this, s = t.settings, o = {}, v;

			if (s.theme_xenforo_more_colors) {
				o.more_colors_func = function() {
					t._mceColorPicker(0, {
						color : c.value,
						func : function(co) {
							c.setColor(co);
						}
					});
				};
			}

			if (v = s.theme_xenforo_background_colors)
				o.colors = v;

			if (s.theme_xenforo_default_background_color)
				o.default_color = s.theme_xenforo_default_background_color;

			o.title = 'xenforo.backcolor_desc';
			o.cmd = 'HiliteColor';
			o.scope = this;

			c = t.editor.controlManager.createColorSplitButton('backcolor', o);

			return c;
		},

		renderUI : function(o) {
			var n, ic, tb, t = this, ed = t.editor, s = t.settings, sc, p, nl;

			if (ed.settings) {
				ed.settings.aria_label = s.aria_label + ed.getLang('advanced.help_shortcut');
			}

			// TODO: ACC Should have an aria-describedby attribute which is user-configurable to describe what this field is actually for.
			// Maybe actually inherit it from the original textara?
			n = p = DOM.create('span', {role : 'application', 'aria-labelledby' : ed.id + '_voice', id : ed.id + '_parent', 'class' : 'mceEditor ' + ed.settings.skin + 'Skin' + (s.skin_variant ? ' ' + ed.settings.skin + 'Skin' + t._ufirst(s.skin_variant) : '')});
			DOM.add(n, 'span', {'class': 'mceVoiceLabel', 'style': 'display:none;', id: ed.id + '_voice'}, s.aria_label);

			if (!DOM.boxModel)
				n = DOM.add(n, 'div', {'class' : 'mceOldBoxModel'});

			n = sc = DOM.add(n, 'table', {role : "presentation", id : ed.id + '_tbl', 'class' : 'mceLayout', cellSpacing : 0, cellPadding : 0});
			n = tb = DOM.add(n, 'tbody');

			switch ((s.theme_xenforo_layout_manager || '').toLowerCase()) {
				case "rowlayout":
					ic = t._rowLayout(s, tb, o);
					break;

				case "customlayout":
					ic = ed.execCallback("theme_xenforo_custom_layout", s, tb, o, p);
					break;

				default:
					ic = t._simpleLayout(s, tb, o, p);
			}

			n = o.targetNode;

			// Add classes to first and last TRs
			nl = sc.rows;
			DOM.addClass(nl[0], 'mceFirst');
			DOM.addClass(nl[nl.length - 1], 'mceLast');

			// Add classes to first and last TDs
			each(DOM.select('tr', tb), function(n) {
				DOM.addClass(n.firstChild, 'mceFirst');
				DOM.addClass(n.childNodes[n.childNodes.length - 1], 'mceLast');
			});

			if (DOM.get(s.theme_xenforo_toolbar_container))
				DOM.get(s.theme_xenforo_toolbar_container).appendChild(p);
			else
				DOM.insertAfter(p, n);

			Event.add(ed.id + '_path_row', 'click', function(e) {
				e = e.target;

				if (e.nodeName == 'A') {
					t._sel(e.className.replace(/^.*mcePath_([0-9]+).*$/, '$1'));

					return Event.cancel(e);
				}
			});
/*
			if (DOM.get(ed.id + '_path_row')) {
				Event.add(ed.id + '_tbl', 'mouseover', function(e) {
					var re;

					e = e.target;

					if (e.nodeName == 'SPAN' && DOM.hasClass(e.parentNode, 'mceButton')) {
						re = DOM.get(ed.id + '_path_row');
						t.lastPath = re.innerHTML;
						DOM.setHTML(re, e.parentNode.title);
					}
				});

				Event.add(ed.id + '_tbl', 'mouseout', function(e) {
					if (t.lastPath) {
						DOM.setHTML(ed.id + '_path_row', t.lastPath);
						t.lastPath = 0;
					}
				});
			}
*/

			if (!ed.getParam('accessibility_focus'))
				Event.add(DOM.add(p, 'a', {href : '#'}, '<!-- IE -->'), 'focus', function() {tinyMCE.get(ed.id).focus();});

			if (s.theme_xenforo_toolbar_location == 'external')
				o.deltaHeight = 0;

			t.deltaHeight = o.deltaHeight;
			o.targetNode = null;

			ed.onKeyDown.add(function(ed, evt) {
				var DOM_VK_F10 = 121, DOM_VK_F11 = 122;

				if (evt.altKey) {
		 			if (evt.keyCode === DOM_VK_F10) {
						t.toolbarGroup.focus();
						return Event.cancel(evt);
					} else if (evt.keyCode === DOM_VK_F11) {
						DOM.get(ed.id + '_path_row').focus();
						return Event.cancel(evt);
					}
				}
			});

			// alt+0 is the UK recommended shortcut for accessing the list of access controls.
			ed.addShortcut('alt+0', '', 'mceShortcuts', t);

			return {
				iframeContainer : ic,
				editorContainer : ed.id + '_parent',
				sizeContainer : sc,
				deltaHeight : o.deltaHeight
			};
		},

		getInfo : function() {
			return {
				longname : 'XenForo theme',
				author : '',
				authorurl : '',
				version : '1.0'
			};
		},

		resizeBy : function(dw, dh) {
			var e = DOM.get(this.editor.id + '_ifr');

			this.resizeTo(e.clientWidth + dw, e.clientHeight + dh);
		},

		resizeTo : function(w, h) {
			var ed = this.editor, s = ed.settings, e = DOM.get(ed.id + '_tbl'), ifr = DOM.get(ed.id + '_ifr'), dh;

			// Boundery fix box
			w = Math.max(s.theme_xenforo_resizing_min_width || 100, w);
			h = Math.max(s.theme_xenforo_resizing_min_height || 100, h);
			w = Math.min(s.theme_xenforo_resizing_max_width || 0xFFFF, w);
			h = Math.min(s.theme_xenforo_resizing_max_height || 0xFFFF, h);

			// Calc difference between iframe and container
			dh = e.clientHeight - ifr.clientHeight;

			// Resize iframe and container
			DOM.setStyle(ifr, 'height', h - dh);
			DOM.setStyles(e, {width : w, height : h});
		},

		destroy : function() {
			var id = this.editor.id;

			Event.clear(id + '_resize');
			Event.clear(id + '_path_row');
			Event.clear(id + '_external_close');
		},

		// Internal functions

		_simpleLayout : function(s, tb, o, p) {
			var t = this, ed = t.editor, lo = s.theme_xenforo_toolbar_location, sl = s.theme_xenforo_statusbar_location, n, ic, etb, c;

			if (s.readonly) {
				n = DOM.add(tb, 'tr');
				n = ic = DOM.add(n, 'td', {'class' : 'mceIframeContainer'});
				return ic;
			}

			// Create toolbar container at top
			if (lo == 'top')
				t._addToolbars(tb, o);

			// Create external toolbar
			if (lo == 'external') {
				n = c = DOM.create('div', {style : 'position:relative'});
				n = DOM.add(n, 'div', {id : ed.id + '_external', 'class' : 'mceExternalToolbar'});
				DOM.add(n, 'a', {id : ed.id + '_external_close', href : 'javascript:;', 'class' : 'mceExternalClose'});
				n = DOM.add(n, 'table', {id : ed.id + '_tblext', cellSpacing : 0, cellPadding : 0});
				etb = DOM.add(n, 'tbody');

				if (p.firstChild.className == 'mceOldBoxModel')
					p.firstChild.appendChild(c);
				else
					p.insertBefore(c, p.firstChild);

				t._addToolbars(etb, o);

				ed.onMouseUp.add(function() {
					var e = DOM.get(ed.id + '_external');
					DOM.show(e);

					DOM.hide(lastExtID);

					var f = Event.add(ed.id + '_external_close', 'click', function() {
						DOM.hide(ed.id + '_external');
						Event.remove(ed.id + '_external_close', 'click', f);
					});

					DOM.show(e);
					DOM.setStyle(e, 'top', 0 - DOM.getRect(ed.id + '_tblext').h - 1);

					// Fixes IE rendering bug
					DOM.hide(e);
					DOM.show(e);
					e.style.filter = '';

					lastExtID = ed.id + '_external';

					e = null;
				});
			}

			if (sl == 'top')
				t._addStatusBar(tb, o);

			// Create iframe container
			if (!s.theme_xenforo_toolbar_container) {
				n = DOM.add(tb, 'tr');
				n = ic = DOM.add(n, 'td', {'class' : 'mceIframeContainer'});
			}

			// Create toolbar container at bottom
			if (lo == 'bottom')
				t._addToolbars(tb, o);

			if (sl == 'bottom')
				t._addStatusBar(tb, o);

			return ic;
		},

		_rowLayout : function(s, tb, o) {
			var t = this, ed = t.editor, dc, da, cf = ed.controlManager, n, ic, to, a;

			dc = s.theme_xenforo_containers_default_class || '';
			da = s.theme_xenforo_containers_default_align || 'center';

			each(explode(s.theme_xenforo_containers || ''), function(c, i) {
				var v = s['theme_xenforo_container_' + c] || '';

				switch (v.toLowerCase()) {
					case 'mceeditor':
						n = DOM.add(tb, 'tr');
						n = ic = DOM.add(n, 'td', {'class' : 'mceIframeContainer'});
						break;

					case 'mceelementpath':
						t._addStatusBar(tb, o);
						break;

					default:
						a = (s['theme_xenforo_container_' + c + '_align'] || da).toLowerCase();
						a = 'mce' + t._ufirst(a);

						n = DOM.add(DOM.add(tb, 'tr'), 'td', {
							'class' : 'mceToolbar ' + (s['theme_xenforo_container_' + c + '_class'] || dc) + ' ' + a || da
						});

						to = cf.createToolbar("toolbar" + i);
						t._addControls(v, to);
						DOM.setHTML(n, to.renderHTML());
						o.deltaHeight -= s.theme_xenforo_row_height;
				}
			});

			return ic;
		},

		_addControls : function(v, tb) {
			var t = this, s = t.settings, di, cf = t.editor.controlManager;

			if (s.theme_xenforo_disable && !t._disabled) {
				di = {};

				each(explode(s.theme_xenforo_disable), function(v) {
					di[v] = 1;
				});

				t._disabled = di;
			} else
				di = t._disabled;

			each(explode(v), function(n) {
				var c;

				if (di && di[n])
					return;

				// Compatiblity with 2.x
				if (n == 'tablecontrols') {
					each(["table","|","row_props","cell_props","|","row_before","row_after","delete_row","|","col_before","col_after","delete_col","|","split_cells","merge_cells"], function(n) {
						n = t.createControl(n, cf);

						if (n)
							tb.add(n);
					});

					return;
				}
				else if (n.indexOf('xenforo_') == 0 && t.editor.plugins[n] && t.editor.plugins[n].addButtons)
				{
					t.editor.plugins[n].addButtons(t, tb);
					return;
				}

				c = t.createControl(n, cf);

				if (c)
					tb.add(c);
			});
		},

		_addToolbars : function(c, o) {
			var t = this, i, tb, ed = t.editor, s = t.settings, v, cf = ed.controlManager, di, n, h = [], a, toolbarGroup;

			toolbarGroup = cf.createToolbarGroup('toolbargroup', {
				'name': ed.getLang('advanced.toolbar'),
				'tab_focus_toolbar':ed.getParam('theme_advanced_tab_focus_toolbar')
			});

			t.toolbarGroup = toolbarGroup;

			a = s.theme_xenforo_toolbar_align.toLowerCase();
			a = 'mce' + t._ufirst(a);

			n = DOM.add(DOM.add(c, 'tr', {role: 'presentation'}), 'td', {'class' : 'mceToolbar ' + a, "role":"presentation"});

			// Create toolbar and add the controls
			for (i=1; (v = s['theme_xenforo_buttons' + i]); i++) {
				tb = cf.createToolbar("toolbar" + i, {'class' : 'mceToolbarRow' + i});

				if (s['theme_xenforo_buttons' + i + '_add'])
					v += ',' + s['theme_xenforo_buttons' + i + '_add'];

				if (s['theme_xenforo_buttons' + i + '_add_before'])
					v = s['theme_xenforo_buttons' + i + '_add_before'] + ',' + v;

				t._addControls(v, tb);
				toolbarGroup.add(tb);

				o.deltaHeight -= s.theme_xenforo_row_height;
			}

			h.push(toolbarGroup.renderHTML());
			h.push(DOM.createHTML('a', {href : '#', accesskey : 'z', title : '', onfocus : 'tinyMCE.getInstanceById(\'' + ed.id + '\').focus();'}, '<!-- IE -->'));
			DOM.setHTML(n, h.join(''));

			$(n).find('a[id$=removeformat]').focus(function() { tinyMCE.getInstanceById(ed.id).focus(); });
		},

		_addStatusBar : function(tb, o) {
			var n, t = this, ed = t.editor, s = t.settings, r, mf, me, td;

			n = DOM.add(tb, 'tr');
			n = td = DOM.add(n, 'td', {'class' : 'mceStatusbar'});
			n = DOM.add(n, 'div', {id : ed.id + '_path_row', 'role': 'group', 'aria-labelledby': ed.id + '_path_voice'});
			if (s.theme_advanced_path) {
				DOM.add(n, 'span', {id: ed.id + '_path_voice'}, ed.translate('advanced.path'));
				DOM.add(n, 'span', {}, ': ');
			} else {
				DOM.add(n, 'span', {}, '&#160;');
			}

			if (s.theme_xenforo_resizing) {
				DOM.add(td, 'a', {id : ed.id + '_resize', href : 'javascript:;', onclick : "return false;", 'class' : 'mceResize'});

				if (s.theme_xenforo_resizing_use_cookie) {
					ed.onPostRender.add(function() {
						var o = Cookie.getHash("TinyMCE_" + ed.id + "_size"), c = DOM.get(ed.id + '_tbl');

						if (!o)
							return;

						if (s.theme_xenforo_resize_horizontal)
							c.style.width = Math.max(10, o.cw) + 'px';

						c.style.height = Math.max(10, o.ch) + 'px';
						DOM.get(ed.id + '_ifr').style.height = Math.max(10, parseInt(o.ch) + t.deltaHeight) + 'px';
					});
				}

				ed.onPostRender.add(function() {
					Event.add(ed.id + '_resize', 'mousedown', function(e) {
						var c, p, w, h, n, pa;

						// Measure container
						c = DOM.get(ed.id + '_tbl');
						w = c.clientWidth;
						h = c.clientHeight;

						miw = s.theme_xenforo_resizing_min_width || 100;
						mih = s.theme_xenforo_resizing_min_height || 100;
						maw = s.theme_xenforo_resizing_max_width || 0xFFFF;
						mah = s.theme_xenforo_resizing_max_height || 0xFFFF;

						// Setup placeholder
						p = DOM.add(DOM.get(ed.id + '_parent'), 'div', {'class' : 'mcePlaceHolder'});
						DOM.setStyles(p, {width : w, height : h});

						// Replace with placeholder
						DOM.hide(c);
						DOM.show(p);

						// Create internal resize obj
						r = {
							x : e.screenX,
							y : e.screenY,
							w : w,
							h : h,
							dx : null,
							dy : null
						};

						// Start listening
						mf = Event.add(DOM.doc, 'mousemove', function(e) {
							var w, h;

							// Calc delta values
							r.dx = e.screenX - r.x;
							r.dy = e.screenY - r.y;

							// Boundery fix box
							w = Math.max(miw, r.w + r.dx);
							h = Math.max(mih, r.h + r.dy);
							w = Math.min(maw, w);
							h = Math.min(mah, h);

							// Resize placeholder
							if (s.theme_xenforo_resize_horizontal)
								p.style.width = w + 'px';

							p.style.height = h + 'px';

							return Event.cancel(e);
						});

						me = Event.add(DOM.doc, 'mouseup', function(e) {
							var ifr;

							// Stop listening
							Event.remove(DOM.doc, 'mousemove', mf);
							Event.remove(DOM.doc, 'mouseup', me);

							c.style.display = '';
							DOM.remove(p);

							if (r.dx === null)
								return;

							ifr = DOM.get(ed.id + '_ifr');

							if (s.theme_xenforo_resize_horizontal)
								c.style.width = Math.max(10, r.w + r.dx) + 'px';

							c.style.height = Math.max(10, r.h + r.dy) + 'px';
							ifr.style.height = Math.max(10, ifr.clientHeight + r.dy) + 'px';

							if (s.theme_xenforo_resizing_use_cookie) {
								Cookie.setHash("TinyMCE_" + ed.id + "_size", {
									cw : r.w + r.dx,
									ch : r.h + r.dy
								});
							}
						});

						return Event.cancel(e);
					});
				});
			}

			o.deltaHeight -= 21;
			n = tb = null;
		},

		_updateUndoStatus : function(ed) {
			var cm = ed.controlManager;

			cm.setDisabled('undo', !ed.undoManager.hasUndo() && !ed.typing);
			cm.setDisabled('redo', !ed.undoManager.hasRedo());
		},

		_nodeChanged : function(ed, cm, n, co) {
			var t = this, p, de = 0, v, c, s = t.settings, cl, fz, fn;

			if (s.readonly)
				return;

			tinymce.each(t.stateControls, function(c) {
				cm.setActive(c, ed.queryCommandState(t.controls[c][1]));
			});

			cm.setActive('visualaid', ed.hasVisual);
			cm.setDisabled('undo', !ed.undoManager.hasUndo() && !ed.typing);
			cm.setDisabled('redo', !ed.undoManager.hasRedo());
			cm.setDisabled('outdent', !ed.queryCommandState('Outdent'));

			p = DOM.getParent(n, 'A');
			if (c = cm.get('link')) {
				if (!p || !p.name) {
					c.setDisabled(!p && co);
					c.setActive(!!p);
				}
			}

			if (c = cm.get('unlink')) {
				c.setDisabled(!p && co);
				c.setActive(!!p && !p.name);
			}

			if (c = cm.get('anchor')) {
				c.setActive(!co && !!p && p.name);

				if (tinymce.isWebKit) {
					p = DOM.getParent(n, 'IMG');
					c.setActive(!!p && DOM.getAttrib(p, 'mce_name') == 'a');
				}
			}

			p = DOM.getParent(n, 'IMG');
			if (c = cm.get('image'))
				c.setActive(!co && !!p && n.className.indexOf('mceItem') == -1);

			if (c = cm.get('styleselect')) {
				if (n.className) {
					t._importClasses();
					c.select(n.className);
				} else
					c.select();
			}

			if (c = cm.get('formatselect')) {
				p = DOM.getParent(n, DOM.isBlock);

				if (p)
					c.select(p.nodeName.toLowerCase());
			}

			if (ed.settings.convert_fonts_to_spans) {
				ed.dom.getParent(n, function(n) {
					if (n.nodeName === 'SPAN') {
						if (!cl && n.className)
							cl = n.className;

						if (!fz && n.style.fontSize)
							fz = n.style.fontSize;

						if (!fn && n.style.fontFamily)
							fn = n.style.fontFamily.replace(/[\"\']+/g, '').replace(/^([^,]+).*/, '$1').toLowerCase();
					}

					return false;
				});

				if (c = cm.get('fontselect')) {
					c.select(function(v) {
						return v.replace(/^([^,]+).*/, '$1').toLowerCase() == fn;
					});
				}

				if (c = cm.get('fontsizeselect')) {
					c.select(function(v) {
						if (v.fontSize && v.fontSize === fz)
							return true;

						if (v['class'] && v['class'] === cl)
							return true;
					});
				}
			} else {
				if (c = cm.get('fontselect'))
					c.select(ed.queryCommandValue('FontName'));

				if (c = cm.get('fontsizeselect')) {
					v = ed.queryCommandValue('FontSize');
					c.select(function(iv) {
						return iv.fontSize == v;
					});
				}
			}

			if (s.theme_xenforo_path && s.theme_xenforo_statusbar_location) {
				p = DOM.get(ed.id + '_path') || DOM.add(ed.id + '_path_row', 'span', {id : ed.id + '_path'});
				DOM.setHTML(p, '');

				ed.dom.getParent(n, function(n) {
					var na = n.nodeName.toLowerCase(), u, pi, ti = '';

					// Ignore non element and hidden elements
					if (n.nodeType != 1 || n.nodeName === 'BR' || (DOM.hasClass(n, 'mceItemHidden') || DOM.hasClass(n, 'mceItemRemoved')))
						return;

					// Fake name
					if (v = DOM.getAttrib(n, 'mce_name'))
						na = v;

					// Handle prefix
					if (tinymce.isIE && n.scopeName !== 'HTML')
						na = n.scopeName + ':' + na;

					// Remove internal prefix
					na = na.replace(/mce\:/g, '');

					// Handle node name
					switch (na) {
						case 'b':
							na = 'strong';
							break;

						case 'i':
							na = 'em';
							break;

						case 'img':
							if (v = DOM.getAttrib(n, 'src'))
								ti += 'src: ' + v + ' ';

							break;

						case 'a':
							if (v = DOM.getAttrib(n, 'name')) {
								ti += 'name: ' + v + ' ';
								na += '#' + v;
							}

							if (v = DOM.getAttrib(n, 'href'))
								ti += 'href: ' + v + ' ';

							break;

						case 'font':
							if (s.convert_fonts_to_spans)
								na = 'span';

							if (v = DOM.getAttrib(n, 'face'))
								ti += 'font: ' + v + ' ';

							if (v = DOM.getAttrib(n, 'size'))
								ti += 'size: ' + v + ' ';

							if (v = DOM.getAttrib(n, 'color'))
								ti += 'color: ' + v + ' ';

							break;

						case 'span':
							if (v = DOM.getAttrib(n, 'style'))
								ti += 'style: ' + v + ' ';

							break;
					}

					if (v = DOM.getAttrib(n, 'id'))
						ti += 'id: ' + v + ' ';

					if (v = n.className) {
						v = v.replace(/(webkit-[\w\-]+|Apple-[\w\-]+|mceItem\w+|mceVisualAid)/g, '');

						if (v && v.indexOf('mceItem') == -1) {
							ti += 'class: ' + v + ' ';

							if (DOM.isBlock(n) || na == 'img' || na == 'span')
								na += '.' + v;
						}
					}

					na = na.replace(/(html:)/g, '');
					na = {name : na, node : n, title : ti};
					t.onResolveName.dispatch(t, na);
					ti = na.title;
					na = na.name;

					//u = "javascript:tinymce.EditorManager.get('" + ed.id + "').theme._sel('" + (de++) + "');";
					pi = DOM.create('a', {'href' : "javascript:;", onmousedown : "return false;", title : ti, 'class' : 'mcePath_' + (de++)}, na);

					if (p.hasChildNodes()) {
						p.insertBefore(DOM.doc.createTextNode(' \u00bb '), p.firstChild);
						p.insertBefore(pi, p.firstChild);
					} else
						p.appendChild(pi);
				}, ed.getBody());
			}
		},

		// Commands gets called by execCommand

		_sel : function(v) {
			this.editor.execCommand('mceSelectNodeDepth', false, v);
		},

		_mceHelp : function() {
			var ed = this.editor;

			ed.windowManager.open({
				url : this._getFullDialogUrl('about'),
				width : 480,
				height : 380,
				inline : true
			}, {
				theme_url : this.url
			});
		},

		_mceColorPicker : function(u, v) {
			var ed = this.editor;

			v = v || {};

			ed.windowManager.open({
				url : this._getFullDialogUrl('color_picker'),
				width : 375 + parseInt(ed.getLang('xenforo.colorpicker_delta_width', 0)),
				height : 250 + parseInt(ed.getLang('xenforo.colorpicker_delta_height', 0)),
				close_previous : false,
				inline : true,
				translate_i18n : false
			}, {
				input_color : v.color,
				func : v.func,
				theme_url : this.url
			});
		},

		_mceImage : function(ui, val) {
			var ed = this.editor;

			// Internal image object like a flash placeholder
			if (ed.dom.getAttrib(ed.selection.getNode(), 'class').indexOf('mceItem') != -1)
				return;

			ed.windowManager.open({
				url : this._getFullDialogUrl('image'),
				width : 355 + parseInt(ed.getLang('xenforo.image_delta_width', 0)),
				height : 275 + parseInt(ed.getLang('xenforo.image_delta_height', 0)),
				inline : true,
				translate_i18n : false
			}, {
				theme_url : this.url
			});
		},

		_mceLink : function(ui, val) {
			var ed = this.editor;

			ed.windowManager.open({
				url : this._getFullDialogUrl('link'),
				width : 310 + parseInt(ed.getLang('xenforo.link_delta_width', 0)),
				height : 200 + parseInt(ed.getLang('xenforo.link_delta_height', 0)),
				inline : true,
				translate_i18n : false
			}, {
				theme_url : this.url
			});
		},

		_mceForeColor : function() {
			var t = this;

			this._mceColorPicker(0, {
				color: t.fgColor,
				func : function(co) {
					t.fgColor = co;
					t.editor.execCommand('ForeColor', false, co);
				}
			});
		},

		_mceBackColor : function() {
			var t = this;

			this._mceColorPicker(0, {
				color: t.bgColor,
				func : function(co) {
					t.bgColor = co;
					t.editor.execCommand('HiliteColor', false, co);
				}
			});
		},

		_ufirst : function(s) {
			return s.substring(0, 1).toUpperCase() + s.substring(1);
		},

		_getFullDialogUrl : function(dialogName)
		{
			var dialogUrl = this.settings.theme_xenforo_dialog_url;

			if (dialogUrl.indexOf('?') == -1)
			{
				return dialogUrl + '?dialog=' + encodeURIComponent(dialogName);
			}
			else
			{
				return dialogUrl + '&dialog=' + encodeURIComponent(dialogName);
			}
		},

		_RemoveFormat : function() {
			if (this._removeFormatExecuting)
			{
				return;
			}
			this._removeFormatExecuting = true;

			var ed = this.editor;

			ed.execCommand('removeFormat', false);
			ed.formatter.remove('xenForoRemoveFormat');
			ed.execCommand('unlink', false);

			this._removeFormatExecuting = false;
		},

		_removeFormatExecuting: false
	});

	tinymce.ThemeManager.add('xenforo', tinymce.themes.XenForoTheme);
}(tinymce));