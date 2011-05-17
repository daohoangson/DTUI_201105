<?php

/**
 * Renders HTML to BB code.
 *
 * @package XenForo_Html
 */
class XenForo_Html_Renderer_BbCode
{
	/**
	 * A map of tag handlers. Tag names are in lower case. Possible keys:
	 * 		* wrap - wraps tag content in some text; used %s for text (eg, [b]%s[/b])
	 * 		* filterCallback - callback to process tag; given tag content (string) and tag (XenForo_Html_Tag)
	 *
	 * @var array Key is tag name in lower case
	 */
	protected $_handlers = array(
		'br'         => array('wrap' => "%s\n"),

		'b'          => array('wrap' => '[B]%s[/B]'),
		'strong'     => array('wrap' => '[B]%s[/B]'),

		'i'          => array('wrap' => '[I]%s[/I]'),
		'em'         => array('wrap' => '[I]%s[/I]'),

		'u'          => array('wrap' => '[U]%s[/U]'),
		's'          => array('wrap' => '[S]%s[/S]'),

		'a'          => array('filterCallback' => array('$this', 'handleTagA')),
		'img'        => array('filterCallback' => array('$this', 'handleTagImg')),

		'ul'         => array('wrap' => "[LIST]%s\n[/LIST]", 'skipCss' => true),
		'ol'         => array('wrap' => "[LIST=1]%s\n[/LIST]", 'skipCss' => true),
		'li'         => array('filterCallback' => array('$this', 'handleTagLi')),

		'blockquote' => array('wrap' => '[INDENT]%s[/INDENT]'),

		'h1'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h2'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h3'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h4'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h5'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h6'         => array('filterCallback' => array('$this', 'handleTagH')),

		// remove the contents of these tags
		'script'     => array('wrap' => ''),
		'title'      => array('wrap' => ''),
		'style'      => array('wrap' => ''),
	);

	/**
	 * Handlers for specific CSS rules. Value is a callback function name.
	 *
	 * @var array Key is the CSS rule name
	 */
	protected $_cssHandlers = array(
		'color'           => array('$this', 'handleCssColor'),
		'float'           => array('$this', 'handleCssFloat'),
		'font-family'     => array('$this', 'handleCssFontFamily'),
		'font-size'       => array('$this', 'handleCssFontSize'),
		'font-style'      => array('$this', 'handleCssFontStyle'),
		'font-weight'     => array('$this', 'handleCssFontWeight'),
		'padding-left'    => array('$this', 'handleCssPaddingLeft'), // editor implements indent this way
		'text-align'      => array('$this', 'handleCssTextAlign'),
		'text-decoration' => array('$this', 'handleCssTextDecoration'),
	);

	protected $_options = array(
		'baseUrl' => '',
		'stripLinkPathTraversal' => false
	);

	/**
	 * Helper function to render a string of HTML direct to BB code.
	 *
	 * @param string $html
	 * @param array $options
	 *
	 * @return string BB code
	 */
	public static function renderFromHtml($html, array $options = array())
	{
		//echo '<pre>' . htmlspecialchars($html) . '</pre>'; exit;

		$parser = new XenForo_Html_Parser($html);
		$renderer = new self($options);
		$parsed = $parser->parse();

		//$parser->printTags($parsed);

		$rendered = $renderer->render($parsed);
		//echo '<pre>' . htmlspecialchars($rendered) . '</pre>'; exit;

		return self::filterBbCode($rendered);
	}

	public static function filterBbCode($bbCode)
	{
		$bbCode = preg_replace('#(\[img\])\[url\]([^[]*)\[/url\](\[/img\])#siU', '$1$2$3', $bbCode);

		return $bbCode;
	}

	/**
	 * Constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = array())
	{
		$requestPaths = XenForo_Application::get('requestPaths');
		$this->_options['baseUrl'] = $requestPaths['fullBasePath'];

		$this->_options = array_merge($this->_options, $options);
	}

	/**
	 * Renders the specified tag and all children.
	 *
	 * @param XenForo_Html_Tag $tag
	 *
	 * @return string
	 */
	public function render(XenForo_Html_Tag $tag)
	{
		return $this->renderTag($tag);
	}

	/**
	 * Renders the specififed tag and all children.
	 *
	 * @param XenForo_Html_Tag $tag
	 *
	 * @return string
	 */
	public function renderTag(XenForo_Html_Tag $tag)
	{
		if (isset($this->_handlers[$tag->tagName()]))
		{
			$handler = $this->_handlers[$tag->tagName()];
		}
		else
		{
			$handler = null;
		}

		$currentIsBlock = $tag->isBlock();

		$output = array();

		$children = $tag->children();
		$lastKey = count($children) - 1;
		foreach ($children AS $key => $child)
		{
			if ($child instanceof XenForo_Html_Tag)
			{
				if ($currentIsBlock && $key == $lastKey && $child->tagName() == 'br')
				{
					// ignore 1 trailing br of block tag
					continue;
				}
				else if ($child->isBlock() && $child->isEmpty())
				{
					// block with nothing worth rendering
					continue;
				}

				$text = $this->renderTag($child);
				if ($text !== '' || $child->isBlock())
				{
					$output[] = array(
						'type' => ($child->isBlock() ? 'block' : 'inline'),
						'text' => $text
					);
				}
			}
			else if ($child instanceof XenForo_Html_Text)
			{
				$output[] = array(
					'type' => 'text',
					'text' => $this->renderText($child)
				);
			}
		}

		$stringOutput = '';
		$maxCounter = count($output) - 1;

		foreach ($output AS $counter => $childOutput)
		{
			$text = $childOutput['text'];

			$hasPrevious = ($counter > 0);
			$hasNext = ($counter < $maxCounter);

			switch($childOutput['type'])
			{
				case 'text':
					if ($currentIsBlock && !$hasPrevious)
					{
						// caused problems with leading spaces
						//$text = ltrim($text);
					}
					else if ($hasPrevious && $output[$counter - 1]['type'] == 'block')
					{
						$text = "\n" . ltrim($text);
					}

					if ($hasNext && $output[$counter + 1]['type'] == 'block')
					{
						$text = rtrim($text);
					}
					else if (!$hasNext && $currentIsBlock)
					{
						$text = rtrim($text);
					}
					break;

				case 'block':
					if ($hasPrevious)
					{
						$text = "\n" . $text;
					}
					break;
			}

			$stringOutput .= $text;
		}

		$preCssOutput = $stringOutput;
		if (!$handler || empty($handler['skipCss']))
		{
			$stringOutput = $this->renderCss($tag, $stringOutput);
		}

		if (!empty($handler['filterCallback']))
		{
			$callback = $handler['filterCallback'];
			if (is_array($callback) && $callback[0] == '$this')
			{
				$callback[0] = $this;
			}
			$stringOutput = call_user_func($callback, $stringOutput, $tag, $preCssOutput);
		}
		else if (isset($handler['wrap']))
		{
			$stringOutput = sprintf($handler['wrap'], $stringOutput);
		}

		return $stringOutput;
	}

	/**
	 * Renders the CSS for a given tag.
	 *
	 * @param XenForo_Html_Tag $tag
	 * @param string $stringOutput
	 *
	 * @return string BB code output
	 */
	public function renderCss(XenForo_Html_Tag $tag, $stringOutput)
	{
		$css = $tag->attribute('style');
		if ($css)
		{
			foreach ($css AS $cssRule => $cssValue)
			{
				if (strtolower($cssRule) == 'display' && strtolower($cssValue) == 'none')
				{
					return '';
				}

				if (!empty($this->_cssHandlers[$cssRule]))
				{
					$callback = $this->_cssHandlers[$cssRule];
					if (is_array($callback) && $callback[0] == '$this')
					{
						$callback[0] = $this;
					}
					$stringOutput = call_user_func($callback, $stringOutput, $cssValue, $tag);
				}
			}

			// images centered on their own are done this way
			$centerRules = array_merge(array(
				'display' => '',
				'margin-left' => '',
				'margin-right' => ''
			), $css);
			if ($centerRules['display'] == 'block'
				&& $centerRules['margin-left'] == 'auto' && $centerRules['margin-right'] == 'auto'
			)
			{
				$stringOutput = '[CENTER]' . $stringOutput . '[/CENTER]';
			}
		}

		return $stringOutput;
	}

	/**
	 * Renders the text to the correct format.
	 *
	 * @param XenForo_Html_Text $text
	 *
	 * @return string
	 */
	public function renderText(XenForo_Html_Text $text)
	{
		return preg_replace('/(\r\n|\n|\r)/', ' ', $text);
	}

	public function convertUrlToAbsolute($url)
	{
		if (preg_match('#^(https?|ftp)://#i', $url))
		{
			return $url;
		}

		if ($this->_options['stripLinkPathTraversal'])
		{
			$url = preg_replace('#^(\.\./){1,2}#', '', $url);
		}

		if (!$this->_options['baseUrl'])
		{
			return $url;
		}

		if ($url === '')
		{
			return $this->_options['baseUrl'];
		}

		preg_match('#^(?P<protocolHost>(https?|ftp)://[^/]+)(?P<path>.*)$#i',
			$this->_options['baseUrl'], $baseParts
		);
		if (!$baseParts)
		{
			return $url;
		}

		if ($url[0] == '/')
		{
			return $baseParts['protocolHost'] . $url;
		}

		if (preg_match('#^((\.\./)+)#', $url, $upMatch))
		{
			$count = strlen($upMatch[1]) / strlen($upMatch[2]);

			for ($i = 1; $i <= $count; $i++)
			{
				$baseParts['path'] = dirname($baseParts['path']);
			}

			$url = substr($url, strlen($upMatch[0]));
		}

		$baseParts['path'] = str_replace('\\', '/', $baseParts['path']);

		if (substr($baseParts['path'], -1) != '/')
		{
			$baseParts['path'] .= '/';
		}
		if ($url[0] == '/')
		{
			// path has trailing slash
			$url = substr($url, 1);
		}

		return $baseParts['protocolHost'] . $baseParts['path'] . $url;
	}

	/**
	 * Handles A tags. Can generate URL or EMAIL tags in BB code.
	 *
	 * @param string $text Child text of the tag
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagA($text, XenForo_Html_Tag $tag)
	{
		$href = trim($tag->attribute('href'));
		if ($href)
		{
			if (preg_match('/^mailto:(.+)$/i', $href, $match))
			{
				$target = $match[1];
				$type = 'EMAIL';
			}
			else
			{
				$target = $this->convertUrlToAbsolute($href);
				$type = 'URL';
			}


			if ($target == $text)
			{
				// look for part of a BB code at the end that may have been swallowed up
				if (preg_match('#\[/?([a-z0-9_-]+)$#i', $text, $match))
				{
					$append = $match[0];
					$text = substr($text, 0, -strlen($match[0]));
				}
				else
				{
					$append = '';
				}

				return "[$type]{$text}[/$type]$append";
			}
			else
			{
				return "[$type='$target']{$text}[/$type]";
			}
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Handles IMG tags.
	 *
	 * @param string $text Child text of the tag (probably none)
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagImg($text, XenForo_Html_Tag $tag)
	{
		if (($tag->attribute('class') == 'smilie' || $tag->attribute('data-smilie')) && $tag->attribute('alt'))
		{
			$output = $tag->attribute('alt');
		}
		else if (preg_match('#attach(Thumb|Full)(\d+)#', $tag->attribute('alt'), $match))
		{
			if ($match[1] == 'Full')
			{
				$output = '[ATTACH=full]' . $match[2] . '[/ATTACH]';
			}
			else
			{
				$output = '[ATTACH]' . $match[2] . '[/ATTACH]';
			}

		}
		else
		{
			$src = $tag->attribute('src');
			$output = '';

			if ($src)
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
				foreach ($smilies AS $smilie)
				{
					if ($src == $smilie['image_url'])
					{
						$output = reset($smilie['smilieText']);
						break;
					}
				}

				if (!$output)
				{
					$output =  "[IMG]" . $this->convertUrlToAbsolute($src) . "[/IMG]";
				}
			}
		}

		return $this->renderCss($tag, $output);
	}

	/**
	 * Handles LI tags.
	 *
	 * @param string $text Child text of the tag
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagLi($text, XenForo_Html_Tag $tag)
	{
		$parent = $tag->parent();
		if ($parent && !in_array($parent->tagName(), array('ol', 'ul')))
		{
			if (trim($text) === '')
			{
				return '';
			}
			else
			{
				return '[LIST][*]' . $text . '[/LIST]';
			}
		}
		else
		{
			return '[*]' . $text;
		}
	}

	/**
	 * Handles heading tags.
	 *
	 * @param string $text Child text of the tag
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagH($text, XenForo_Html_Tag $tag)
	{
		switch ($tag->tagName())
		{
			case 'h1': $size = 6; break;
			case 'h2': $size = 5; break;
			case 'h3': $size = 4; break;
			case 'h4': $size = 3; break;
			default: $size = false;
		}

		$text = '[B]' . $text . '[/B]';

		if ($size)
		{
			$text = '[SIZE=' . $size . ']' . $text . '[/SIZE]';
		}

		return $text . "\n";
	}

	/**
	 * Handles CSS (text) color rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssColor($text, $color)
	{
		return "[COLOR=$color]{$text}[/COLOR]";
	}

	/**
	 * Handles CSS float rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFloat($text, $alignment)
	{
		switch (strtolower($alignment))
		{
			case 'left':
			case 'right':
				$alignmentUpper = strtoupper($alignment);
				return "[$alignmentUpper]{$text}[/$alignmentUpper]";

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS font-family rules. The first font is used.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontFamily($text, $cssValue)
	{
		list($fontFamily) = explode(',', $cssValue);
		if (preg_match('/^(\'|")(.*)\\1$/', $fontFamily, $match))
		{
			$fontFamily = $match[2];
		}

		if ($fontFamily)
		{
			return "[FONT=$fontFamily]{$text}[/FONT]";
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Handles CSS font-size rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontSize($text, $fontSize)
	{
		switch (strtolower($fontSize))
		{
			case 'xx-small': $fontSize = 1; break;
			case 'x-small':  $fontSize = 2; break;
			case 'small':    $fontSize = 3; break;
			case 'medium':   $fontSize = 4; break;
			case 'large':    $fontSize = 5; break;
			case 'x-large':  $fontSize = 6; break;
			case 'xx-large': $fontSize = 7; break;

			default:
				// TODO: support more units by mapping again
				if (!preg_match('/^[0-9]+(px)?$/i', $fontSize))
				{
					$fontSize = 0;
				}
		}

		if ($fontSize)
		{
			return "[SIZE=$fontSize]{$text}[/SIZE]";
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Handles CSS font-style rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontStyle($text, $fontStyle)
	{
		switch (strtolower($fontStyle))
		{
			case 'italic':
			case 'oblique':
				return '[I]' . $text . '[/I]';

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS font-weight rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontWeight($text, $fontWeight)
	{
		switch (strtolower($fontWeight))
		{
			case 'bold':
			case 'bolder':
			case '700':
			case '800':
			case '900':
				return '[B]' . $text . '[/B]';

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS padding-left rules to represent indent.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssPaddingLeft($text, $paddingAmount)
	{
		if (preg_match('/^(\d+)px$/i', $paddingAmount, $match))
		{
			$depth = floor($match[1] / 30); // editor puts in 30px
			if ($depth)
			{
				return '[INDENT=' . $depth . ']' . $text . '[/INDENT]';
			}
		}

		return $text;
	}

	/**
	 * Handles CSS text-align rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssTextAlign($text, $alignment)
	{
		switch (strtolower($alignment))
		{
			case 'left':
			case 'center':
			case 'right':
				$alignmentUpper = strtoupper($alignment);
				return "[$alignmentUpper]{$text}[/$alignmentUpper]";

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS text-decoration rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssTextDecoration($text, $decoration)
	{
		switch (strtolower($decoration))
		{
			case 'underline':
				return "[U]{$text}[/U]";

			case 'line-through':
				return "[S]{$text}[/S]";

			default:
				return $text;
		}
	}
}