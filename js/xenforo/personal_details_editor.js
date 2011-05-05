/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	// *********************************************************************

	/**
	 * Updates any existing avatars for the visitor after they change their gender
	 *
	 * @param jQuery form.AutoValidator
	 */
	XenForo.AvatarGenderUpdater = function($form) { this.__construct($form); };
	XenForo.AvatarGenderUpdater.prototype =
	{
		__construct: function($form)
		{
			if ($form.find('input[name="gender"]').length)
			{
				$form.bind('AutoValidationComplete', $.context(this, 'updateAvatars'));
			}
		},

		/**
		 * Updates all the current user's avatars on the page,
		 * provided that the event passed contains ajaxData with userId and avatarUrls keys.
		 *
		 * @param event AutoValidationComplete from form autovalidator
		 */
		updateAvatars: function(e)
		{
			if (e.ajaxData.userId && e.ajaxData.avatarUrls)
			{
				XenForo.updateUserAvatars(e.ajaxData.userId, e.ajaxData.avatarUrls);
			}
		}
	};

	// *********************************************************************

	XenForo.register('form.AutoValidator', 'XenForo.AvatarGenderUpdater');

}
(jQuery, this, document);