/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.CommentLoader = function($element) { this.__construct($element); };
	XenForo.CommentLoader.prototype =
	{
		__construct: function($link)
		{
			this.$link = $link;

			$link.click($.context(this, 'click'));
		},

		click: function(e)
		{
			var params = this.$link.data('loadParams');

			if (typeof params != 'object')
			{
				params = {};
			}

			e.preventDefault();

			XenForo.ajax(
				this.$link.attr('href'),
				params,
				$.context(this, 'loadSuccess'),
				{ type: 'GET' }
			);
		},

		loadSuccess: function(ajaxData)
		{
			var $replace,
				replaceSelector = this.$link.data('replace'),
				els = [], $els, i;

			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			if (replaceSelector)
			{
				$replace = $(replaceSelector);
			}
			else
			{
				$replace = this.$link.parent();
			}

			if (ajaxData.comments && ajaxData.comments.length)
			{
				/*for (i = 0; i < ajaxData.comments.length; i++)
				{
					$.merge(els, $(ajaxData.comments[i]));
				}

				// xfInsert didn't like this
				$els = $(els).hide();
				$replace.xfFadeUp().replaceWith($els);
				$els.xfActivate().xfFadeDown();*/

				for (i = 0; i < ajaxData.comments.length; i++)
				{
					$(ajaxData.comments[i]).xfInsert('insertBefore', $replace);
				}
				$replace.xfHide();
			}
			else
			{
				$replace.xfRemove();
			}
		}
	};

	XenForo.CommentPoster = function($element) { this.__construct($element); };
	XenForo.CommentPoster.prototype =
	{
		__construct: function($link)
		{
			this.$link = $link;
			this.$commentArea = $($link.data('commentArea'));

			if (this.$commentArea.data('submitUrl'))
			{
				this.submitUrl = this.$commentArea.data('submitUrl');
			}
			else
			{
				this.submitUrl = $link.attr('href');
			}

			$link.click($.context(this, 'click'));

			this.$commentArea.find('input:submit, button').click($.context(this, 'submit'));
		},

		click: function(e)
		{
			e.preventDefault();

			this.$commentArea.xfFadeDown(XenForo.speed.fast, function()
			{
				$(this).find('textarea[name="message"]').focus();
			});
		},

		submit: function(e)
		{
			e.preventDefault();

			XenForo.ajax(
				this.submitUrl,
				{ message: this.$commentArea.find('textarea[name="message"]').val() },
				$.context(this, 'submitSuccess')
			);
		},

		submitSuccess: function(ajaxData)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			if (ajaxData.comment)
			{
				$(ajaxData.comment).xfInsert('insertBefore', this.$commentArea);
			}

			this.$commentArea.find('textarea[name="message"]').val('');
		}
	};

	XenForo.register('a.CommentLoader', 'XenForo.CommentLoader');
	XenForo.register('a.CommentPoster', 'XenForo.CommentPoster');

}
(jQuery, this, document);