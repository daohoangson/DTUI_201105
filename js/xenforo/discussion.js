/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	//TODO: Enable jQuery plugin and compressor in editor template.

	/**
	 * Enables quick reply for a message form
	 * @param $form
	 */
	XenForo.QuickReply = function($form)
	{
		if ($('#messageList').length == 0)
		{
			return console.error('Quick Reply not possible for %o, no #messageList found.', $form);
		}

		var submitEnableCallback = XenForo.MultiSubmitFix($form);

		/**
		 * Scrolls QuickReply into view and focuses the editor
		 */
		this.scrollAndFocus = function()
		{
			$(document).scrollTop($form.offset().top);

			if (window.tinyMCE)
			{
				window.tinyMCE.editors['ctrl_message_html'].focus();
			}
			else
			{
				$('#QuickReply').find('textarea:first').get(0).focus();
			}

			return this;
		};

		$form.data('QuickReply', this).bind(
		{
			/**
			 * Fires just before the form would be AJAX submitted,
			 * to detect whether or not the 'more options' button was clicked,
			 * and to abort AJAX submission if it was.
			 *
			 * @param event e
			 * @return
			 */
			AutoValidationBeforeSubmit: function(e)
			{
				if ($(e.clickedSubmitButton).is('input[name="more_options"]'))
				{
					e.preventDefault();
					e.returnValue = true;
				}
			},

			/**
			 * Fires after the AutoValidator form has successfully validated the AJAX submission
			 *
			 * @param event e
			 */
			AutoValidationComplete: function(e)
			{
				if (e.ajaxData._redirectTarget)
				{
					window.location = e.ajaxData._redirectTarget;
				}

				$('input[name="last_position"]', $form).val(e.ajaxData.lastPosition);
				$('input[name="last_date"]', $form).val(e.ajaxData.lastDate);

				if (submitEnableCallback)
				{
					submitEnableCallback();
				}

				$form.find('input:submit').blur();

				new XenForo.ExtLoader(e.ajaxData, function()
				{
					$(e.ajaxData.templateHtml).each(function()
					{
						if (this.tagName)
						{
							$(this).xfInsert('appendTo', $('#messageList'));
						}
					});
				});

				$('#QuickReply').find('textarea').val('');
				if (window.tinyMCE)
				{
					window.tinyMCE.editors['ctrl_message_html'].setContent('');
				}

				if (window.sessionStorage)
				{
					window.sessionStorage.quickReplyText = null;
				}

				$form.trigger('QuickReplyComplete');

				return false;
			}
		});
	};

	// *********************************************************************

	/**
	 * Controls to initialise Quick Reply with a quote
	 *
	 * @param jQuery a.ReplyQuote, a.MultiQuote
	 */
	XenForo.QuickReplyTrigger = function($trigger)
	{
		if ($trigger.is('.MultiQuote'))
		{
			// not yet implemented
			return false;
		}

		/**
		 * Activates quick reply and quotes the post to which the trigger belongs
		 *
		 * @param e event
		 *
		 * @return boolean false
		 */
		$trigger.click(function(e)
		{
			var $form = $('#QuickReply'),
				xhr = null;

			$form.data('QuickReply').scrollAndFocus();

			if (!xhr)
			{
				xhr = XenForo.ajax
				(
					$trigger.data('postUrl') || $trigger.attr('href'),
					'',
					function(ajaxData, textStatus)
					{
						if (XenForo.hasResponseError(ajaxData))
						{
							return false;
						}

						delete(xhr);

						var ed = XenForo.getEditorInForm($form);
						if (!ed)
						{
							return false;
						}

						if (ed.execCommand)
						{
							if (tinyMCE.isIE)
							{
								ed.execCommand('mceInsertContent', false, ajaxData.quoteHtml);
							}
							else
							{
								ed.execCommand('insertHtml', false, ajaxData.quoteHtml);
							}

							if (window.sessionStorage)
							{
								window.sessionStorage.quickReplyText = ajaxData.quoteHtml;
							}

							//TODO: keep an eye on when Webkit fixes this
							if (tinyMCE.isWebKit)
							{
								// fixes Webkit's magic disappearing cursor after execCommand()
								ed.selection.select(ed.dom.select('body')[0].lastChild);
								ed.selection.collapse(false);
							}
						}
						else
						{
							ed.val(ed.val() + ajaxData.quote);

							if (window.sessionStorage)
							{
								window.sessionStorage.quickReplyText = ajaxData.quote;
							}
						}
					}
				);
			}

			return false;
		});
	};

	// *********************************************************************

	XenForo.InlineMessageEditor = function($form)
	{
		$form.bind(
		{
			AutoValidationBeforeSubmit: function(e)
			{
				if ($(e.clickedSubmitButton).is('input[name="more_options"]'))
				{
					e.preventDefault();
					e.returnValue = true;
				}
			},
			AutoValidationComplete: function(e)
			{
				var overlay = $form.closest('div.xenOverlay').data('overlay'),
					target = overlay.getTrigger().data('target');

				if (XenForo.hasTemplateHtml(e.ajaxData, 'messagesTemplateHtml') || XenForo.hasTemplateHtml(e.ajaxData))
				{
					e.preventDefault();
					overlay.close().getTrigger().data('XenForo.OverlayTrigger').deCache();

					XenForo.showMessages(e.ajaxData, overlay.getTrigger(), 'instant');
				}
				else
				{
					console.warn('No template HTML!');
				}
			}
		});
	};

	// *********************************************************************

	XenForo.MessageLoader = function($ctrl)
	{
		$ctrl.click(function(e)
		{
			e.preventDefault();

			var messageIds = [];

			$($ctrl.data('messageSelector')).each(function(i, msg)
			{
				messageIds.push(msg.id);
			});

			if (messageIds.length)
			{
				XenForo.ajax
				(
					$ctrl.attr('href'),
					{
						messageIds: messageIds
					},
					function(ajaxData, textStatus)
					{
						XenForo.showMessages(ajaxData, $ctrl, 'fadeDown');
					}
				);
			}
			else
			{
				console.warn('No messages found to load.'); // debug message, no phrasing
			}
		});
	};

	// *********************************************************************

	XenForo.showMessages = function(ajaxData, $ctrl, method)
	{
		var showMessage = function(selector, templateHtml)
		{
			switch (method)
			{
				case 'instant':
				{
					method =
					{
						show: 'xfShow',
						hide: 'xfHide',
						speed: 0
					};
					break;
				}

				case 'fadeIn':
				{
					method =
					{
						show: 'xfFadeIn',
						hide: 'xfFadeOut',
						speed: XenForo.speed.fast
					};
					break;
				}

				case 'fadeDown':
				default:
				{
					method =
					{
						show: 'xfFadeDown',
						hide: 'xfFadeUp',
						speed: XenForo.speed.normal
					};
				}
			}

			$(selector)[method.hide](method.speed / 2, function()
			{
				$(templateHtml).xfInsert('replaceAll', selector, method.show, method.speed);
			});
		};

		if (XenForo.hasResponseError(ajaxData))
		{
			return false;
		}

		if (XenForo.hasTemplateHtml(ajaxData, 'messagesTemplateHtml'))
		{
			new XenForo.ExtLoader(ajaxData, function()
			{
				$.each(ajaxData.messagesTemplateHtml, showMessage);
			});
		}
		else if (XenForo.hasTemplateHtml(ajaxData))
		{
			// single message
			new XenForo.ExtLoader(ajaxData, function()
			{
				showMessage($ctrl.data('messageSelector'), ajaxData.templateHtml);
			});
		}
	};

	// *********************************************************************

	XenForo.PollVoteForm = function($form)
	{
		$form.bind('AutoValidationComplete', function(e)
		{
			e.preventDefault();

			if (XenForo.hasTemplateHtml(e.ajaxData))
			{
				var $container = $($form.data('container'));

				$form.xfFadeUp(XenForo.speed.normal, function()
				{
					$form.empty().remove();

					$(e.ajaxData.templateHtml).xfInsert('appendTo', $container);
				}, XenForo.speed.normal, 'swing');
			}
		});
	};

	// *********************************************************************

	XenForo.register('#QuickReply', 'XenForo.QuickReply');

	XenForo.register('a.ReplyQuote, a.MultiQuote', 'XenForo.QuickReplyTrigger');

	XenForo.register('form.InlineMessageEditor', 'XenForo.InlineMessageEditor');

	XenForo.register('a.MessageLoader', 'XenForo.MessageLoader');

	XenForo.register('form.PollVoteForm', 'XenForo.PollVoteForm');

}
(jQuery, this, document);
