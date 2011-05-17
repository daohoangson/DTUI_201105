<?php

class XenForo_ControllerHelper_Editor extends XenForo_ControllerHelper_Abstract
{
	/**
	 * Gets the message text from an input that can be a plain text editor or
	 * a WYSIWYG editor.
	 *
	 * @param string $inputName
	 * @param XenForo_Input $input
	 * @param integer $htmlCharacterLimit Max length of HTML before processing; defaults to 4 * message length option
	 *
	 * @return string BB code input
	 */
	public function getMessageText($inputName, XenForo_Input $input, $htmlCharacterLimit = -1)
	{
		if ($input->inRequest($inputName))
		{
			return $input->filterSingle($inputName, XenForo_Input::STRING);
		}
		else if ($input->inRequest($inputName . '_html'))
		{
			$messageTextHtml = $input->filterSingle($inputName . '_html', XenForo_Input::STRING);

			if ($input->filterSingle('_xfRteFailed', XenForo_Input::UINT))
			{
				// actually, the RTE failed to load, so just treat this as BB code
				return $messageTextHtml;
			}

			if ($messageTextHtml !== '')
			{
				if ($htmlCharacterLimit < 0)
				{
					$htmlCharacterLimit = 4 * XenForo_Application::get('options')->messageMaxLength;
					// quadruple the limit as HTML can be a lot more verbose
				}

				if ($htmlCharacterLimit && utf8_strlen($messageTextHtml) > $htmlCharacterLimit)
				{
					throw new XenForo_Exception(new XenForo_Phrase('submitted_message_is_too_long_to_be_processed'), true);
				}

				$options = array();
				$requestPaths = XenForo_Application::get('requestPaths');
				$options['baseUrl'] = $requestPaths['fullBasePath'];

				$relativeResolver = $input->filterSingle('_xfRelativeResolver', XenForo_Input::STRING);
				if ($relativeResolver && isset($_SERVER['HTTP_USER_AGENT']))
				{
					if (preg_match('#Firefox/([0-9]+)\.([0-9]+)\.([0-9]+)#i', $_SERVER['HTTP_USER_AGENT'], $match))
					{
						// FF versions sometime before 3.6.12 have an issue with respecting the base tag of the editor,
						// 3.6.8 is a known version that has problems
						$useResolver = ($match[1] <= 3 && $match[2] <= 6 && $match[3] <= 8);
					}
					else
					{
						$useResolver = false;
					}

					if ($useResolver)
					{
						// take off query string and then up to the last directory
						$relativeResolver = preg_replace('/\?.*$/', '', $relativeResolver);
						$relativeResolver = preg_replace('#/[^/]+$#', '', $relativeResolver);

						$options['baseUrl'] = $relativeResolver;
					}
				}

				$rendered = XenForo_Html_Renderer_BbCode::renderFromHtml($messageTextHtml, $options);
				return trim(XenForo_Input::cleanString($rendered));
			}
		}
		else
		{
			return '';
		}
	}
}