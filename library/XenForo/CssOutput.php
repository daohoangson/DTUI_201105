<?php

/**
 * Class to output CSS data quickly for public facing pages. This class
 * is not designed to be used with the MVC structure; this allows us to
 * significantly reduce the amount of overhead in a request.
 *
 * This class is entirely self sufficient. It handles parsing the input,
 * getting the data, rendering it, and manipulating HTTP headers.
 *
 * @package XenForo_CssOutput
 */
class XenForo_CssOutput
{
	/**
	 * Style ID the CSS will be retrieved from.
	 *
	 * @var integer
	 */
	protected $_styleId = 0;

	/**
	 * Array of CSS templates that have been requested. These will have ".css" appended
	 * to them and requested as templates.
	 *
	 * @var array
	 */
	protected $_cssRequested = array();

	/**
	 * The timestamp of the last modification, according to the input. (Used to compare
	 * to If-Modified-Since header.)
	 *
	 * @var integer
	 */
	protected $_inputModifiedDate = 0;

	/**
	 * Date of the last modification to the style. Used to output Last-Modified header.
	 *
	 * @var integer
	 */
	protected $_styleModifiedDate = 0;

	/**
	 * List of user display styles to write out username CSS.
	 *
	 * @var array
	 */
	protected $_displayStyles = array();

	/**
	 * Constructor.
	 *
	 * @param array $input Array of input. Style and CSS will be pulled from this.
	 */
	public function __construct(array $input)
	{
		$this->parseInput($input);
	}

	/**
	 * Parses the style ID and the list of CSS out of the specified array of input.
	 * The style ID will be found in "style" and CSS list in "css". The CSS should be
	 * comma-delimited.
	 *
	 * @param array $input
	 */
	public function parseInput(array $input)
	{
		$this->_styleId = isset($input['style']) ? intval($input['style']) : 0;

		if (!empty($input['css']))
		{
			$this->_cssRequested = explode(',', strval($input['css']));
		}

		if (!empty($input['d']))
		{
			$this->_inputModifiedDate = intval($input['d']);
		}
	}

	public function handleIfModifiedSinceHeader(array $server)
	{
		$outputCss = true;
		if (isset($server['HTTP_IF_MODIFIED_SINCE']))
		{
			$modDate = strtotime($server['HTTP_IF_MODIFIED_SINCE']);
			if ($modDate !== false && $this->_inputModifiedDate <= $modDate)
			{
				header('HTTP/1.1 304 Not Modified', true, 304);
				$outputCss = false;
			}
		}

		return $outputCss;
	}

	/**
	 * Does any preperations necessary for outputting to be done.
	 */
	protected function _prepareForOutput()
	{
		$cacheData = XenForo_Model::create('XenForo_Model_DataRegistry')->getMulti(array('styles', 'displayStyles', 'options'));

		if (is_array($cacheData['displayStyles']))
		{
			$this->_displayStyles = $cacheData['displayStyles'];
		}

		$styles = $cacheData['styles'];
		if (!is_array($styles))
		{
			$styles = XenForo_Model::create('XenForo_Model_Style')->rebuildStyleCache();
		}

		if (!is_array($cacheData['options']))
		{
			$cacheData['options'] = XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}
		$options = new XenForo_Options($cacheData['options']);
		XenForo_Application::setDefaultsFromOptions($options);
		XenForo_Application::set('options', $options);

		if ($this->_styleId && isset($styles[$this->_styleId]))
		{
			$style = $styles[$this->_styleId];
		}
		else
		{
			$style = reset($styles);
		}

		if ($style)
		{
			$properties = unserialize($style['properties']);

			$this->_styleId = $style['style_id'];
			$this->_styleModifiedDate = $style['last_modified_date'];
		}
		else
		{
			$properties = array();

			$this->_styleId = 0;
		}

		XenForo_Template_Helper_Core::setStyleProperties($properties, false);
		XenForo_Template_Public::setStyleId($this->_styleId);
		XenForo_Template_Abstract::setLanguageId(0);
	}

	/**
	 * Renders the CSS and returns it.
	 *
	 * @return string
	 */
	public function renderCss()
	{
		$this->_prepareForOutput();

		$params = array(
			'displayStyles' => $this->_displayStyles,
			'xenOptions' => XenForo_Application::get('options')->getOptions()
		);

		$templates = array();
		foreach ($this->_cssRequested AS $cssName)
		{
			$cssName = trim($cssName);
			if (!$cssName)
			{
				continue;
			}

			$templateName = $cssName . '.css';
			if (!isset($templates[$templateName]))
			{
				$templates[$templateName] = new XenForo_Template_Public($templateName, $params);
			}
		}

		return self::renderCssFromObjects($templates, XenForo_Application::debugMode());
	}

	/**
	 * Renders the CSS from a collection of Template objects.
	 *
	 * @param array $templates Array of XenForo_Template_Abstract objects
	 * @param boolean $withDebug If true, output debug CSS when invalid properties are accessed
	 *
	 * @return string
	 */
	public static function renderCssFromObjects(array $templates, $withDebug = false)
	{
		$errors = array();
		$output = '@CHARSET "UTF-8";' . "\n";

		foreach ($templates AS $templateName => $template)
		{
			if ($withDebug)
			{
				XenForo_Template_Helper_Core::resetInvalidStylePropertyAccessList();
			}

			$rendered = $template->render();
			if ($rendered !== '')
			{
				$output .= "\n/* --- " . str_replace('*/', '', $templateName) . " --- */\n\n$rendered\n";
			}

			if ($withDebug)
			{
				$propertyError = self::createDebugErrorString(
					XenForo_Template_Helper_Core::getInvalidStylePropertyAccessList()
				);
				if ($propertyError)
				{
					$errors["$templateName"] = $propertyError;
				}
			}
		}

		if ($withDebug && $errors)
		{
			$output .= self::getDebugErrorsAsCss($errors);
		}

		return self::translateCssRules($output);
	}

	/**
	 * Translates CSS rules for use by current browsers.
	 *
	 * @param string $output
	 *
	 * @return string
	 */
	public static function translateCssRules($output)
	{
		/**
		 * CSS3 temporary attributes translation.
		 * Some browsers implement custom attributes that refer to a future spec.
		 * This takes the (assumed) future attribute and translates it into
		 * browser-specific tags, so the CSS can be up to date with browser changes.
		 *
		 * @var array CSS translators: key = pattern to find, value = replacement pattern
		 */
		$cssTranslate = array(
			// border/outline-radius
			'/(?<=[^a-zA-Z0-9-])(border|outline)((-)(top|bottom)(-)(right|left))?-radius\s*:(\s*)([^ ;][^;]*)\s*;/siU'
				=> '\0'
					. ' -webkit-\1\3\4\5\6-radius: \7\8;'
					. ' -moz-\1-radius\3\4\6: \7\8;'
					. ' -khtml-\1\3\4\5\6-radius: \7\8;'
					//. ' -pie-\1\3\4\5\6-radius: \7\8;'
					//. " behavior: url(js/PIE/PIE.htc);"
					,

			//TODO: this is not the most clever regex - need to compare it to the w3c spec for box-shadow
			// box-shadow: left bottom size (spread - Moz) color
			'/(?<=[^a-zA-Z0-9-])box-shadow\s*:(\s*)(([^\s;]+)(\s+[^\s;]+)(\s+[^\s;]+)(\s+[^;]+)|(none))\s*;/siU'
				=> '\0'
					. ' -webkit-box-shadow: \3\4\5\6\7;'
					. ' -moz-box-shadow: \3\4\5\6\7;'
					. ' -khtml-box-shadow: \3\4\5\6\7;'
					//. ' -pie-box-shadow: \3\4\5\6\7;'
					//. " behavior: url(js/PIE/PIE.htc)"
					,

			// text-shadow - to fix the Chrome rendering bug, see http://jsbin.com/acalu4
			self::getTextShadowRegex()
				=> 'text-shadow: 0 0 0 transparent, \1;'
					,

			// box-sizing
			'/(?<=[^a-zA-Z0-9-])box-sizing\s*:\s*([^\s;]+)\s*;/siU'
				=> '\0'
					. ' -webkit-box-sizing: \1;'
					. ' -moz-box-sizing: \1;'
					. ' -ms-box-sizing: \1;'
					,

			// transform
			'/(?<=[^a-zA-Z0-9-])transform\s*:\s*([^;]+);/siU'
				=> '\0'
					. ' -webkit-transform: \1;'
					. ' -moz-transform: \1;'
					. ' -o-transform: \1;'
					,

			// rgba borders
			'/(?<=[^a-zA-Z0-9-])border([a-z-]*)\s*:([^;]*)rgba\(\s*(\d+\s*,\s*\d+\s*,\s*\d+)\s*,\s*([\d.]+)\s*\)([^;]*);/siU'
				=> 'border\1: \2rgb(\3)\5; border\1: \2rgba(\3, \4)\5; _border\1: \2rgb(\3)\5;'
					,
		);
		$output = preg_replace(
			array_keys($cssTranslate),
			$cssTranslate,
			$output
		);

		//rgba translation - only for IE
		$output = preg_replace_callback('/
				(?<=[^a-zA-Z0-9-])
				(background\s*:\s*)
				([^;]*
					(
						rgba\(
							(\s*\d+%?\s*,\s*\d+%?\s*,\s*\d+%?\s*,\s*[0-9.]+\s*)
						\)
					)
				[^;]*)
				(\s*;)
			/siUx', array('self', '_handleRgbaReplacement'), $output
		);

		return $output;
	}

	/**
	 * Returns a regular expression that matches SINGLE SHADOW text-shadow rules.
	 * Used to fix a Chrome rendering 'feature'.
	 *
	 * @link http://code.google.com/p/chromium/issues/detail?id=23440
	 *
	 * @return string
	 */
	public static function getTextShadowRegex()
	{
		$dimension = '(-?\d+[a-z%]*)';
		$namedColor = '([a-z0-9]+)';
		$hexColor = '(#[a-f0-9]{3,6})';
		$rgbColor = '(rgb\s*\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)\s*\))';
		$rgbaColor = '(rgba\s*\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d(\.\d+)?)\s*\))';

		return "/(?<=[^a-zA-Z0-9-])text-shadow\s*:\s*("
			. "{$dimension}\s+{$dimension}\s+{$dimension}\s+"
			. "({$namedColor}|{$hexColor}|{$rgbColor}|{$rgbaColor})"
			. ")\s*;/siU";
	}

	/**
	 * Handles replacement of an rgba() color with a link to the rgba.php image file
	 * that will generate a 10x10 PNG to show the image.
	 *
	 * @param array $match Match from regex
	 *
	 * @return string
	 */
	protected static function _handleRgbaReplacement(array $match)
	{
		$components = preg_split('#\s*,\s*#', trim($match[4]));
		$value = $match[2];
		if (strpos($value, 'url(') !== false)
		{
			// image and url, write rgb
			$value = str_replace(
				$match[3],
				"rgb($components[0], $components[1], $components[2])",
				$value
			);

			$filter = '';
		}
		else
		{
			$a = intval(255 * $components[3]);
			unset($components[3]);

			foreach ($components AS &$component)
			{
				if (substr($component, -1) == '%')
				{
					$component = intval(255 * intval($component) / 100);
				}
			}

			$value = str_replace(
				$match[3],
				"url(rgba.php?r=$components[0]&g=$components[1]&b=$components[2]&a=$a)",
				$value
			);

			$argb = sprintf('#%02X%02X%02X%02X', $a, $components[0], $components[1], $components[2]);

			$filter = " _filter: progid:DXImageTransform.Microsoft.gradient(startColorstr=$argb,endColorstr=$argb);";
		}

		$newRule = $match[1] . $value . $match[5] . ' ';

		return "$newRule$match[0]$filter";
	}

	/**
	 * Creates the CSS property access debug string from a list of invalid style
	 * propery accesses.
	 *
	 * @param array $invalidPropertyAccess Format: [group] => true ..OR.. [group][value] => true
	 *
	 * @return string
	 */
	public static function createDebugErrorString(array $invalidPropertyAccess)
	{
		if (!$invalidPropertyAccess)
		{
			return '';
		}

		$invalidPropertyErrors = array();
		foreach ($invalidPropertyAccess AS $invalidGroup => $value)
		{
			if ($value === true)
			{
				$invalidPropertyErrors[] = "group: $invalidGroup";
			}
			else
			{
				foreach ($value AS $invalidProperty => $subValue)
				{
					$invalidPropertyErrors[] = "property: $invalidGroup.$invalidProperty";
				}
			}
		}

		if ($invalidPropertyErrors)
		{
			return "Invalid Property Access: " . implode(', ', $invalidPropertyErrors);
		}
		else
		{
			return '';
		}
	}

	/**
	 * Gets debug output for errors as CSS rules that will change the display
	 * of the page to make it clear errors occurred.
	 *
	 * @param array $errors Collection of errors: [template name] => error text
	 *
	 * @return string
	 */
	public static function getDebugErrorsAsCss(array $errors)
	{
		if (!$errors)
		{
			return '';
		}

		$errorOutput = array();
		foreach ($errors AS $errorFile => $errorText)
		{
			$errorOutput[] = "$errorFile: " . addslashes(str_replace(array("\n", "\r", "'", '"'), '', $errorText));
		}

		return "
			/** Error output **/
			html
			{
				background-color: orange;
			}

			body:before
			{
				background-color: yellow;
				color: black;
				font-weight: bold;
				display: block;
				padding: 10px;
				margin: 10px;
				border: solid 1px red;
				border-radius: 5px;
				content: 'CSS Error: " . implode('; ', $errorOutput) . "';
			}
		";
	}

	/**
	 * Outputs the specified CSS. Also outputs the necessary HTTP headers.
	 *
	 * @param string $css
	 */
	public function displayCss($css)
	{
		if (XenForo_Application::get('options')->minifyCss)
		{
			$css = Minify_CSS_Compressor::process($css);
		}

		header('Content-type: text/css; charset=utf-8');
		header('Expires: Wed, 01 Jan 2020 00:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $this->_styleModifiedDate) . ' GMT');
		header('Cache-Control: public');

		$extraHeaders = XenForo_Application::gzipContentIfSupported($css);
		foreach ($extraHeaders AS $extraHeader)
		{
			header("$extraHeader[0]: $extraHeader[1]", $extraHeader[2]);
		}

		if (is_string($css) && $css && !ob_get_level() && XenForo_Application::get('config')->enableContentLength)
		{
			header('Content-Length: ' . strlen($css));
		}

		echo $css;
	}

	/**
	 * Static helper to execute a full request for CSS output. This will
	 * instantiate the object, pull the data from $_REQUEST, and then output
	 * the CSS.
	 */
	public static function run()
	{
		$cssOutput = new self($_REQUEST);
		if ($cssOutput->handleIfModifiedSinceHeader($_SERVER))
		{
			$cssOutput->displayCss($cssOutput->renderCss());
		}
	}
}