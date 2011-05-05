/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.SpamCleaner = function($form)
	{
		var $overlay = $form.closest('.xenOverlay');

		if ($overlay.length)
		{
			$form.submit(function(e)
			{
				e.preventDefault();

				 XenForo.ajax(
					$form.attr('action'),
					$form.serializeArray(),
					function(ajaxData, textStatus)
					{
						if (XenForo.hasResponseError(ajaxData))
						{
							return false;
						}

						if (XenForo.hasTemplateHtml(ajaxData))
						{
							new XenForo.ExtLoader(ajaxData, function()
							{
								$form.slideUp(XenForo.speed.fast, function()
								{
									$form.remove();

									$template = $(ajaxData.templateHtml).prepend('<h2 class="heading">' + ajaxData.title + '</h2>');

									$template.xfInsert('appendTo', $overlay, 'slideDown', XenForo.speed.fast);

									// decache the overlay on close
									$overlay.data('overlay').getTrigger().bind('onClose', function()
									{
										$(this).data('XenForo.OverlayTrigger').deCache();
									});
								});
							});
						}
						else if (ajaxData._redirectTarget)
						{
							window.location = ajaxData._redirectTarget;
						}
						else
						{
							$overlay.data('overlay').close();
						}
					}
				);
			});
		}
	};

	// *********************************************************************

	XenForo.register('.SpamCleaner', 'XenForo.SpamCleaner');
}
(jQuery, this, document);