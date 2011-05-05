/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.FeedForm = function($form)
	{
		$form.bind(
		{
			AutoValidationDataReceived: function(e)
			{
				if (XenForo.hasResponseError(e.ajaxData))
				{
					return false;
				}

				if (e.ajaxData._redirectStatus)
				{
					return true;
				}

				new XenForo.ExtLoader(e.ajaxData, function()
				{
					XenForo.createOverlay($('<span />'), e.ajaxData.templateHtml, e.ajaxData).load();
				});

				return false;
			}
		});
	};

	XenForo.register('#FeedForm', 'XenForo.FeedForm');
}
(jQuery, this, document);