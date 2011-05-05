/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Handles serialization of style property form input fields,
	 * and selection of the correct tab on load.
	 *
	 * @param jQuery form#PropertyForm
	 */
	XenForo.StylePropertyForm = function($form)
	{
		$form.bind('submit', function(e)
		{
			var tabId = $('#propertyTabs').data('XenForo.Tabs').getCurrentTab(),
				inputQueryString;

			$form.find('input[name=tab_id]').val(tabId);

			inputQueryString = $form.serialize();

			$form.find('input:not(input[type=hidden]), select, textarea').removeAttr('name');

			$('<input type="hidden" name="_xfStylePropertiesData" />').val(inputQueryString).appendTo($form);
		});

		if (location.hash.indexOf('#tab-') == 0)
		{
			$('#propertyTabs').data('XenForo.Tabs').click(parseInt(location.hash.substr(5), 10));
		}
	};

	// *********************************************************************

	/**
	 * Activates style property editor for the specified unit
	 *
	 * @param $jQuery .StylePropertyEditor
	 */
	XenForo.StylePropertyEditor = function($unit)
	{
		$unit.find('.TextDecoration input:checkbox').click(function(e)
		{
			var $target = $(e.target);

			console.log('Text-decoration checkbox - Value=%s, Checked=%s', $target.attr('value'), $target.is(':checked'));

			if (!$target.is(':checkbox'))
			{
				$target.attr('checked', !$target.is(':checked'));
			}

			if ($target.is(':checked'))
			{
				if ($target.attr('value') == 'none')
				{
					// uncheck all the other checkboxes
					$(this).not('[value="none"]').attr('checked', false);
				}
				else
				{
					// uncheck the 'none' checkbox
					$(this).filter('[value="none"]').attr('checked', false);
				}
			}
		});
	};

	// *********************************************************************

	XenForo.StylePropertyTooltip = function($item)
	{
		var $descriptionTip = $item.find('div.DescriptionTip')
			.addClass('xenTooltip propertyDescriptionTip')
			.appendTo('body')
			.append('<span class="arrow" />');

		if ($descriptionTip.length)
		{
			$item.tooltip(
			{
				/*effect: 'fade',
				fadeInSpeed: XenForo.speed.normal,
				fadeOutSpeed: 0,*/

				position: 'bottom left',
				offset: [ -24, -3 ],
				tip: $descriptionTip,
				delay: 0
			});
		}
	};

	// *********************************************************************

	XenForo.register('#PropertyForm', 'XenForo.StylePropertyForm');

	XenForo.register('.StylePropertyEditor', 'XenForo.StylePropertyEditor');

	XenForo.register('#propertyTabs > li', 'XenForo.StylePropertyTooltip');
}
(jQuery, this, document);