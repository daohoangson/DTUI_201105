<?php

abstract class XenForo_ViewPublic_Helper_Editor
{
	/**
	 * Array of editor IDs already used, prevents duplicate IDs.
	 *
	 * @var array
	 */
	protected static $_editorIds = array();

	/**
	 * Gets the editor template. The WYSIWYG editor will be used if supported by
	 * the browser.
	 *
	 * @param XenForo_View $view
	 * @param string $formCtrlName Name of the textarea. If using the WYSIWYG editor, this will have _html appended to it.
	 * @param string $message Default message to put in editor. This should contain BB code
	 * @param array $editorOptions Array of options for the editor. Defaults are provided for any unspecified
	 * 	Currently supported:
	 * 		editorId - (string) override normal {formCtrlName}_html id
	 * 		templateName - (string) override normal 'editor' name
	 * 		disable - (boolean) true to prevent WYSIWYG from activating
	 *
	 * @return XenForo_Template_Abstract
	 */
	public static function getEditorTemplate(XenForo_View $view, $formCtrlName, $message = '', array $editorOptions = array())
	{
		$messageHtml = '';

		if (!empty($editorOptions['disable']))
		{
			$showWysiwyg = false;
		}
		else if (!XenForo_Visitor::getInstance()->enable_rte)
		{
			$showWysiwyg = false;
		}
		else
		{
			$showWysiwyg = !XenForo_Visitor::isBrowsingWith('mobile');
		}

		if ($showWysiwyg)
		{
			if (substr($formCtrlName, -1) == ']')
			{
				$formCtrlNameHtml = substr($formCtrlName, 0, -1) . '_html]';
			}
			else
			{
				$formCtrlNameHtml = $formCtrlName . '_html';
			}

			if ($message !== '')
			{
				$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Wysiwyg', array('view' => $view)));
				$messageHtml = $bbCodeParser->render($message, array('lightBox' => false));
			}
		}
		else
		{
			$formCtrlNameHtml = $formCtrlName;
		}

		// get editor id
		if (isset($editorOptions['editorId']))
		{
			$editorId = $editorOptions['editorId'];
		}
		else
		{
			$ctrlInc = 0;

			do
			{
				$editorId = 'ctrl_' . $formCtrlName . ($ctrlInc ? "_$ctrlInc" : '');
				$ctrlInc++;
			}
			while (isset(self::$_editorIds[$editorId]) && $ctrlInc < 100);

			self::$_editorIds[$editorId] = true;
		}

		$templateName = (isset($editorOptions['templateName']) ? $editorOptions['templateName'] : 'editor');
		$height = (isset($editorOptions['height']) ? $editorOptions['height'] : '260px');

		return $view->createTemplateObject($templateName, array(
			'showWysiwyg' => $showWysiwyg,
			'height' => $height,
			'formCtrlNameHtml' => $formCtrlNameHtml,
			'formCtrlName' => $formCtrlName,
			'editorId' => $editorId,

			'message' => $message,
			'messageHtml' => $messageHtml,

			'smilies' => ($showWysiwyg ? self::getEditorSmilies() : array())
		));
	}

	public static function getQuickReplyEditor(XenForo_View $view, $formCtrlName, $message = '', array $editorOptions = array())
	{
		// prevent Opera < 10.5 from using WYSIWYG Quick Reply, due to bugs with mceInsertContent and block level tags
		if (preg_match('/Opera\/.*Version\/([\d\.]+)$/', $_SERVER['HTTP_USER_AGENT'], $match))
		{
			if ($match[1] < 10.5)
			{
				$editorOptions['disable'] = true;
			}
		}

		$editorOptions['height'] = false;

		return self::getEditorTemplate($view, $formCtrlName, $message, $editorOptions);
	}

	/**
	 * Gets the list of smilies in the correct format for the editor.
	 *
	 * @param array|null $smilies If null, pulls from default list.
	 *
	 * @return array [smilie text] => array(0 => title, 1 => url)
	 */
	public static function getEditorSmilies(array $smilies = null)
	{
		if (!is_array($smilies))
		{
			if (XenForo_Application::isRegistered('smilies'))
			{
				$smilies = XenForo_Application::get('smilies');
			}
			else
			{
				$smilies = XenForo_Model::create('XenForo_Model_Smilie')->getAllSmiliesForCache();
				XenForo_Application::set('smilies', $smilies);
			}
		}

		$output = array();
		foreach ($smilies AS $smilie)
		{
			$output[reset($smilie['smilieText'])] = array($smilie['title'], $smilie['image_url']);
		}

		return $output;
	}
}