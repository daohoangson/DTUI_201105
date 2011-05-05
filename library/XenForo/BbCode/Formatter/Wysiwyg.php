<?php

class XenForo_BbCode_Formatter_Wysiwyg extends XenForo_BbCode_Formatter_Base
{
	protected $_undisplayableTags = array('quote', 'code', 'php', 'html', 'plain', 'media', 'attach');

	protected $_stateMap = array(
		'b' => array('bold', true),
		'i' => array('italic', true),
		'u' => array('underline', true),
		's' => array('strikethrough', true),
		'color' => array('color', '$option'),
		'font' => array('font', '$option'),
		'size' => array('size', '$option'),
		'left' => array('align', 'left'),
		'center' => array('align', 'center'),
		'right' => array('align', 'right'),
		'indent' => array('indent', 'indent')
	);

	protected $_imageTemplate = '<img src="%1$s" class="bbCodeImage wysiwygImage" alt="[IMG]" />';

	public function getTags()
	{
		if ($this->_tags !== null)
		{
			return $this->_tags;
		}

		$tags = parent::getTags();

		foreach ($tags AS $tagName => &$tag)
		{
			$tag['isBlock'] = !empty($tag['trimLeadingLinesAfter']);

			if (in_array($tagName, $this->_undisplayableTags))
			{
				$tag['callback'] = array($this, 'renderTagUndisplayable');
				unset($tag['trimLeadingLinesAfter'], $tag['stopLineBreakConversion']);
			}
			else if (isset($this->_stateMap[$tagName]))
			{
				$tag['state'] = $this->_stateMap[$tagName];
				$tag['callback'] = array($this, 'renderTagWithState');
				unset($tag['stopLineBreakConversion']);
			}
		}

		return $tags;
	}

	public function renderTree(array $tree, array $extraStates = array())
	{
		if (empty($extraStates['states']))
		{
			$extraStates['states'] = array();
		}

		return parent::renderTree($tree, $extraStates);
	}

	public function filterFinalOutput($output)
	{
		$output = trim($output);
		if (substr($output, 0, 2) != '<p')
		{
			$output = '<p>' . $output;
		}
		if (substr($output, -4) != '</p>')
		{
			$output .= '</p>';
		}

		$output = strtr($output, array(
			'<postblock><p' => '<p',
			'<postblock>' => '<p>'
		));
		$output = preg_replace('#<p[^>]*><(p|ul|ol)#', '<\\1', $output);
		$output = preg_replace('#</(ul|ol)></p>#', '</\\1>', $output);
		$output = preg_replace('#<br />\s*<(ul|ol)#', '<\\1', $output);
		//$output = preg_replace('#(<p[^>]*><([a-z]+)[^>]*>)(</\\2></p>)#', '\\1<br />\\3', $output);
		$output = str_replace('<p></p>', '<p><br /></p>', $output);

		//echo '<pre>' . htmlspecialchars($output) . '</pre>';

		return $output;
	}

	public function filterString($string, array $rendererStates)
	{
		if (empty($rendererStates['stopSmilies']))
		{
			$string = $this->replaceSmiliesInText($string, 'htmlspecialchars');
		}
		else
		{
			$string = htmlspecialchars($string);
		}

		if (empty($rendererStates['stopLineBreakConversion']))
		{
			if (!empty($rendererStates['states']['list']))
			{
				$string = nl2br($string);
			}
			else
			{
				list($inlineStyles, $blockStyles) = $this->_getStateStyling($rendererStates['states']);
				list($inlineStartHtml, $inlineEndHtml) = $this->_getInlineStateHtml($inlineStyles);
				list($blockStartHtml, $blockEndHtml) = $this->_getBlockStateHtml($blockStyles);

				$string = preg_replace('/\r\n|\n|\r/', "$inlineEndHtml$blockEndHtml\n$blockStartHtml$inlineStartHtml", $string);
			}
		}

		return $string;
	}

	protected function _getStateStyling(array $states)
	{
		$blockStyles = array();
		$inlineStyles = array();

		if (!empty($states['bold']))
		{
			$inlineStyles[] = 'font-weight: bold';
		}
		if (!empty($states['italic']))
		{
			$inlineStyles[] = 'font-style: italic';
		}

		$textDec = array();
		if (!empty($states['underline']))
		{
			$textDec[] = 'underline';
		}
		if (!empty($states['strikethrough']))
		{
			$textDec[] = 'line-through';
		}
		if ($textDec)
		{
			$inlineStyles[] = 'text-decoration: ' . implode(', ', $textDec);
		}

		if (!empty($states['font']))
		{
			$inlineStyles[] = 'font-family: \'' . $states['font'] . '\'';
		}
		if (!empty($states['size']))
		{
			$size = $this->getTextSize($states['size']);
			if ($size)
			{
				$inlineStyles[] = 'font-size: ' . $size;
			}
		}
		if (!empty($states['color']))
		{
			$inlineStyles[] = 'color: ' . $states['color'];
		}

		if (!empty($states['align']))
		{
			$blockStyles[] = 'text-align: ' . $states['align'];
		}
		if (!empty($states['indent']))
		{
			$blockStyles[] = 'padding-left: ' . ($states['indent'] * 30) . 'px';
		}

		return array($inlineStyles, $blockStyles);
	}

	protected function _getInlineStateHtml(array $inlineStyles)
	{
		if ($inlineStyles)
		{
			$inlineStartHtml = '<span style="' . htmlspecialchars(implode('; ', $inlineStyles)) . '">';
			$inlineEndHtml = '</span>';
		}
		else
		{
			$inlineStartHtml = '';
			$inlineEndHtml = '';
		}

		return array($inlineStartHtml, $inlineEndHtml);
	}

	protected function _getBlockStateHtml(array $blockStyles)
	{
		if ($blockStyles)
		{
			$blockStartHtml = '<p style="' . htmlspecialchars(implode('; ', $blockStyles)) . '">';
		}
		else
		{
			$blockStartHtml = '<p>';
		}

		return array($blockStartHtml, '</p>');
	}

	public function renderTagUrl(array $tag, array $rendererStates)
	{
		$rendererStates['shortenUrl'] = false;
		return parent::renderTagUrl($tag, $rendererStates);
	}

	public function renderTagList(array $tag, array $rendererStates)
	{
		$rendererStates['states']['list'] = true;
		return parent::renderTagList($tag, $rendererStates);
	}

	protected function _renderListOutput($listType, array $elements)
	{
		$output = "<$listType>"; //. implode("</li>\n<li>", $elements) .
		foreach ($elements AS $element)
		{
			$output .= "\n<li>$element</li>";
		}
		$output .= "\n</$listType>";

		return $output;
	}

	public function renderTagWithState(array $tag, array $rendererStates)
	{
		$tagInfo = $this->_getTagRule($tag['tag']);

		$rendererStates['states']['block'] = $tagInfo['isBlock'];

		if (!empty($tagInfo['state']))
		{
			list($state, $type) = $tagInfo['state'];
			if ($type === true)
			{
				$rendererStates['states'][$state] = true;
			}
			else if ($type === 1)
			{
				if (isset($rendererStates['states'][$state]))
				{
					$rendererStates['states'][$state]++;
				}
				else
				{
					$rendererStates['states'][$state] = 1;
				}
			}
			else if ($type == 'indent')
			{
				$amount = isset($tag['option']) ? intval($tag['option']) : 1;
				if (isset($rendererStates['states'][$state]))
				{
					$rendererStates['states'][$state] += $amount;
				}
				else
				{
					$rendererStates['states'][$state] = $amount;
				}
			}
			else if ($type == '$option')
			{
				$rendererStates['states'][$state] = $tag['option'];
			}
			else
			{
				$rendererStates['states'][$state] = $type;
			}
		}

		list($inlineStyles, $blockStyles) = $this->_getStateStyling($rendererStates['states']);

		$children = $this->renderSubTree($tag['children'], $rendererStates);

		list($inlineStyles, $blockStyles) = $this->_getStateStyling($rendererStates['states']);
		list($inlineStartHtml, $inlineEndHtml) = $this->_getInlineStateHtml($inlineStyles);
		list($blockStartHtml, $blockEndHtml) = $this->_getBlockStateHtml($blockStyles);

		if ($children === '')
		{
			return ($tagInfo['isBlock'] ? "$blockStartHtml<br />$blockEndHtml\n" : '');
		}
		else if ($tagInfo['isBlock'])
		{
			$return = "$blockStartHtml$inlineStartHtml$children$inlineEndHtml$blockEndHtml\n";
			if (empty($rendererStates['states']['list']))
			{
				$return .= "<postblock>";
			}
			return $return;
		}
		else
		{
			if (!empty($tagInfo['state']))
			{
				list($state, $type) = $tagInfo['state'];
				if (!empty($rendererStates['states'][$state]))
				{
					$thisState = array($state => $rendererStates['states'][$state]);
					list($inlineStyles) = $this->_getStateStyling($thisState);
					list($inlineStartHtml, $inlineEndHtml) = $this->_getInlineStateHtml($inlineStyles);

					return "$inlineStartHtml$children$inlineEndHtml";
				}
			}

			return $children;
		}
	}

	public function renderTagUndisplayable(array $tag, array $rendererStates)
	{
		switch ($tag['tag'])
		{
			case 'code':
			case 'php':
			case 'html':
				if (!empty($tag['original']) && is_array($tag['original']))
				{
					list($prepend, $append) = $tag['original'];
				}
				else
				{
					$prepend = '';
					$append = '';
				}

				$children = $this->renderSubTree($tag['children'], $rendererStates);
				$children = str_replace('  ', '&nbsp; ', $children);

				$output = $this->filterString($prepend, $rendererStates)
					. $children
					. $this->filterString($append, $rendererStates);

				return $output;

			default:
				return $this->renderInvalidTag($tag, $rendererStates);
		}
	}
}