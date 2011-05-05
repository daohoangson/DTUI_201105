/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.AcpLoginForm = function($form)
	{
		var $loginControls = $('#loginControls'),
			$loginLogo = $('#loginLogo'),
			$errorMessage = $('#errorMessage'),
			$nameInput = $form.find('input[name="login"]');

		if ($nameInput.length && $nameInput.val() == '')
		{
			$nameInput.focus();
		}
		else
		{
			$form.find('input[name="password"]').focus();
		}

		$form.submit(function(e)
		{
			e.preventDefault();

			if (!$loginLogo.data('width'))
			{
				$loginLogo.data('width', $loginLogo.width());
				$loginLogo.data('marginTop', $loginLogo.css('marginTop'));
			}

			$loginControls.xfFadeOut(XenForo.speed.normal);

			XenForo.ajax
			(
				$form.attr('action'),
				$form.serializeArray(),
				function(ajaxData, textStatus)
				{
					$errorMessage.hide();

					if (ajaxData._redirectStatus && ajaxData._redirectStatus == 'ok')
					{
						$loginLogo.animate(
						{
							width: 100,
							marginTop: 0

						}, XenForo.speed.normal, function()
						{
							// allow a form to be re-POST'd if you get logged out in the middle
							if (ajaxData.repost)
							{
								var $form = $('<form />').attr({
									action: ajaxData._redirectTarget,
									method: 'POST'
								}).appendTo(document.body);
								
								var serializer = function(obj, $target, prefix)
								{
									var subPrefix;
									
									for (var k in obj)
									{
										subPrefix = (prefix ? prefix + "[" + k + "]" : k);
										
										switch (typeof(obj[k]))
										{
											case 'array':
											case 'object':
												serializer(obj[k], $target, subPrefix);
												break;
											
											default:
												$target.append($('<input />').attr({
													type: 'hidden',
													name: subPrefix,
													value: obj[k].toString()
												}));
										}
									}
								};
								
								if (ajaxData.postVars)
								{
									serializer(ajaxData.postVars, $form, '');
								}
																
								$form.submit();
							}
							else
							{
								window.location = ajaxData._redirectTarget;
							}
						});
					}
					else // something went wrong with the login
					{
						$errorMessage.html(ajaxData.error[0]).xfFadeIn(XenForo.speed.fast);

						$loginControls.xfFadeIn(XenForo.speed.fast);
					}
				}
			);
		});
	};

	// *********************************************************************

	XenForo.register('form.AcpLoginForm', 'XenForo.AcpLoginForm');

}
(jQuery, this, document);