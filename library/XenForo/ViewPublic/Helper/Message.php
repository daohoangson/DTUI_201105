<?php

class XenForo_ViewPublic_Helper_Message
{
	public static function getBbCodeWrapper(array &$message, XenForo_BbCode_Parser $parser, array $options = array())
	{
		$options = array_merge(array(
			'states' => array(),

			'messageKey' => 'message',

			'showSignature' => true,
			'signatureKey' => 'signature',
			'signatureHtmlKey' => 'signatureHtml',

			'noFollow' => null
		), $options);

		$text = $message[$options['messageKey']];

		if ($options['noFollow'] === null)
		{
			$options['noFollow'] = empty($message['isTrusted']) ? true : false;
		}

		$options['states'] += array(
			'noFollowDefault' => $options['noFollow']
		);

		if (empty($options['states']['attachments']) && !empty($message['attachments']))
		{
			$options['states']['attachments'] = $message['attachments'];

			if (stripos($text, '[/attach]') !== false)
			{
				if (preg_match_all('#\[attach(=[^\]]*)?\](?P<id>\d+)(\D.*)?\[/attach\]#iU', $text, $matches))
				{
					foreach ($matches['id'] AS $attachId)
					{
						unset($message['attachments'][$attachId]);
					}
				}
			}
		}

		if ($options['signatureKey'] && isset($message[$options['signatureKey']]))
		{
			if ($options['showSignature'])
			{
				// note: signatures are always nofollow'd by default
				$message[$options['signatureHtmlKey']] = new XenForo_BbCode_TextWrapper(
					$message[$options['signatureKey']], $parser, array('lightBox' => false)
				);
			}
			else
			{
				$message[$options['signatureHtmlKey']] = '';
			}
		}

		return new XenForo_BbCode_TextWrapper($text, $parser, $options['states']);
	}

	public static function bbCodeWrapMessages(array &$messages, XenForo_BbCode_Parser $parser, array $options = array())
	{
		$options += array(
			'showSignature' => XenForo_Visitor::getInstance()->get('content_show_signature'),
			'states' => array()
		);

		foreach ($messages AS &$message)
		{
			$message['messageHtml'] = XenForo_ViewPublic_Helper_Message::getBbCodeWrapper($message, $parser, $options);
		}
	}
}