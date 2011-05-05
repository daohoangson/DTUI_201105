/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Activates unfollow controls
	 *
	 * @param jQuery $link a.UnfollowLink
	 */
	XenForo.UnfollowLink = function($link) { this.__construct($link); };
	XenForo.UnfollowLink.prototype =
	{
		__construct: function($link)
		{
			this.$link = $link.click($.context(this, 'eClick'));

			this.userId = this.$link.data('userId');
			this.jsonUrl = this.$link.data('jsonUrl');

			if (this.userId === null || this.jsonUrl === null)
			{
				console.warn('Unfollow link found without userId or url defined. %o', this.$link);
				return false;
			}

			this.$container = $('#follow_user_' + this.userId);
		},

		/**
		 * Intercept a link on an un-follow link and ask for confirmation
		 *
		 * @param event e
		 */
		eClick: function(e)
		{
			e.preventDefault();

			this.stopFollowing();
		},

		/**
		 * Confirmation callback from link event - stop following the user via AJAX
		 */
		stopFollowing: function()
		{
			XenForo.ajax(
				this.jsonUrl,
				{ user_id: this.userId },
				$.context(this, 'stopFollowingSuccess')
			);
		},

		/**
		 * AJAX callback for stop-following. Removes the link's container from the DOM.
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		stopFollowingSuccess: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			this.$container.xfRemove();
			//xfFadeUp(XenForo.speed.normal, function() { $(this).remove(); });
		}
	};

	// *********************************************************************

	/**
	 * Controls to allow a new user to be followed.
	 *
	 * @param jQuery $form form.FollowForm
	 */
	XenForo.FollowForm = function($form) { this.__construct($form); };
	XenForo.FollowForm.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form
				.bind('AutoValidationComplete', $.context(this, 'ajaxCallback'));

			this.$userInputField = this.$form.find(this.$form.data('userInputField'));
		},

		/**
		 * Fires when triggered by the response of the form being submitted via AJAX in XenForo.AutoValidatorForm
		 *
		 * @param event jQuery event containing ajaxData.templateHtml
		 */
		ajaxCallback: function(e)
		{
			e.preventDefault();

			if (XenForo.hasResponseError(e.ajaxData))
			{
				return false;
			}

			var following = e.ajaxData.following.split(','),
				lastId = null,
				i = 0,
				templateHtml = null;

			this.$userInputField.val('').focus();

			for (i = 0; i < following.length; i++)
			{
				if (this.$form.find('#follow_user_' + following[i]).length == 0)
				{
					// this user is not already shown, so insert the template here
					$templateHtml = $(e.ajaxData.followUsers[following[i]]);

					if (lastId)
					{
						$templateHtml.xfInsert('insertAfter', lastId);
					}
					else
					{
						$templateHtml.xfInsert('prependTo', '.FollowList');
					}

				}

				lastId = '#follow_user_' + following[i];
			}
		}
	};

	// *********************************************************************

	XenForo.register('a.UnfollowLink', 'XenForo.UnfollowLink');

	XenForo.register('form.FollowForm', 'XenForo.FollowForm');

}
(jQuery, this, document);