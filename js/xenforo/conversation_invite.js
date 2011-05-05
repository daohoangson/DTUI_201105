/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Form that handles adding more participants to a conversation
	 *
	 * @param jQuery #ConversationInvitationForm
	 */
	XenForo.ConversationInvitationForm = function($form)
	{
		var $placeholder = $('#ConversationRecipientsPlaceholder');

		if (!$placeholder.length)
		{
			$form.bind('AutoValidationBeforeSubmit', function(e)
			{
				e.preventDefault();
				e.target.submit();
			});

			return;
		}

		$form.bind('AutoValidationComplete', function(e)
		{
			e.preventDefault();
			
			$form.get(0).reset();
			if ($form.parents('.xenOverlay').length)
			{
				$form.parents('.xenOverlay').data('overlay').close();
			}

			if (XenForo.hasTemplateHtml(e.ajaxData))
			{
				$('#ConversationRecipients').xfRemove('xfFadeOut', function()
				{
					$(e.ajaxData.templateHtml).xfInsert('appendTo', $placeholder, 'xfFadeIn');
				});
			}
		});
	};

	// *********************************************************************

	XenForo.register('#ConversationInvitationForm', 'XenForo.ConversationInvitationForm');

}
(jQuery, this, document);