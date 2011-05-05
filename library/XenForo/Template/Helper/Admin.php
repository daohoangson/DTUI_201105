<?php

//TODO: Document control options for each control

/**
 * Helper methods for the admin template functions/tags.
 *
 * @package XenForo_Template
 */
class XenForo_Template_Helper_Admin
{
	/**
	 * Internal counter used for uniquely ID'ing controls.
	 *
	 * @var integer
	 */
	protected static $_controlCounter = 1;

	protected static $_controlIdLog = array();

	/**
	 * Stores a single use ID for output. Useful for generating it in one function
	 * and defering to another (possibly recursive function) to use it.
	 *
	 * Null means it has not been set.
	 *
	 * @var null|string
	 */
	protected static $_oneUseId = null;

	/**
	 * Internal counter for uniquely ID'ing list row popup groups.
	 *
	 * @var integer
	 */
	protected static $_listItemGroupCounter = 1;

	/**
	 * Private constructor. Don't instantiate this object. Use it statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Gets a unique ID for the control
	 *
	 * @param string $name Name of the control. Will be used to generate the ID if possible and not overridden.
	 * @param array $rowOptions Collection of control options. Uses the id from this, if set.
	 *
	 * @return string
	 */
	protected static function _getControlId($name, array $controlOptions = null)
	{
		if (is_array($controlOptions) && isset($controlOptions['id']))
		{
			return $controlOptions['id'];
		}

		if ($name != '')
		{
			$name = preg_replace('/[^a-z0-9_-]/i', '', $name);

			if (!isset(self::$_controlIdLog[$name]))
			{
				self::$_controlIdLog[$name] = 0;
			}

			$counter = self::$_controlIdLog[$name]++;

			return 'ctrl_' . $name . ($counter ? "_$counter" : '');
		}

		return 'ctrl_' . self::$_controlCounter++;
	}

	/**
	 * Resets the control counter. This may cause duplicate IDs. Primarily used for testing.
	 */
	public static function resetControlCounter()
	{
		self::$_controlCounter = 1;
		self::$_controlIdLog = array();
		self::$_listItemGroupCounter = 1;
	}

	/**
	 * Array merging function to merge option and options tags. "Simple" form (value => label)
	 * data will be translated into a simple array format to be unambiguous.
	 *
	 * @param array $start The base array
	 * @param array $additional The data to add to the base array. Ignored if not an array.
	 * @param boolean $raw If true, escaping is not done on printable components
	 *
	 * @return array
	 */
	public static function mergeOptionArrays($start, $additional, $raw = false)
	{
		if (!is_array($start))
		{
			$start = array();
		}

		if (!is_array($additional))
		{
			return $start;
		}

		foreach ($additional AS  $value => $label)
		{
			if (!is_array($label))
			{
				// turn value => label into basic array
				$start[] = array('value' => $value, 'label' => $raw ? $label : htmlspecialchars($label));
			}
			else if (is_string($value))
			{
				// array format with string key -- opt group
				$start[$raw ? $value : htmlspecialchars($value)] = $label;
			}
			else
			{
				// already array format
				if (!$raw && !empty($label['label']))
				{
					$label['label'] = htmlspecialchars($label['label']);
				}
				$start[] = $label;
			}
		}

		return $start;
	}

	/**
	 * Helper to quickly append classes to a list that may or not may exist.
	 * List must be within an array.
	 *
	 * @param array $array Array that contains the list
	 * @param string $key Key of the list
	 * @param string $append Classes to appended. Separate with spaces.
	 *
	 * @return array Original array, updated
	 */
	protected static function _appendClasses(array $array, $key, $append)
	{
		if (!empty($array[$key]))
		{
			$array[$key] .= ' ' . $append;
		}
		else
		{
			$array[$key] = $append;
		}

		return $array;
	}

	/**
	 * Gets the data-* attributes for a tag AS  a string, from the list of options.
	 * The given array should be all options, not just the data array.
	 *
	 * @param array $options All options, possibly including a _data key.
	 *
	 * @return string Attributes AS  string, with a leading space if there are attributes
	 */
	protected static function _getDataAttributesAsString(array $options)
	{
		if (empty($options['_data']) || !is_array($options['_data']))
		{
			return '';
		}

		$output = '';
		foreach ($options['_data'] AS  $key => $value)
		{
			$output .= ' data-' . $key . '="' . $value . '"';
		}

		return $output;
	}

	/**
	 * Outputs an HTML form.
	 *
	 * @param string $childElements Child elements
	 * @param array $options
	 *
	 * @return string
	 */
	public static function form($childElements, array $options)
	{
		$enctype = (!empty($options['upload']) ? ' enctype="multipart/form-data"' : '');
		unset($options['upload']);

		if (!isset($options['method']))
		{
			$options['method'] = 'post';
		}

		if (!isset($options['class']))
		{
			$options['class'] = 'xenForm formOverlay';
		}
		else if (strpos(" $options[class] ", ' xenForm ') === false)
		{
			$options['class'] = 'xenForm formOverlay ' . $options['class'];
		}

		if (strtolower($options['method']) == 'get' && ($questPos = strpos($options['action'], '?')) !== false)
		{
			$converted = XenForo_Template_Helper_Core::convertUrlToActionAndNamedParams($options['action']);
			$options['action'] = $converted['action'];

			$formHiddenElements = XenForo_Template_Helper_Core::getHiddenInputs($converted['params']);
		}
		else
		{
			$formHiddenElements = '';
		}

		if (strtolower($options['method']) == 'get')
		{
			$hiddenToken = '';
		}
		else
		{
			$visitor = XenForo_Visitor::getInstance();
			$hiddenToken = '<input type="hidden" name="_xfToken" value="' . $visitor['csrf_token_page'] . '" />' . "\n";
		}

		$attributes = '';
		foreach ($options AS  $name => $value)
		{
			$attributes .= " $name=\"$value\"";
		}

		return '
			<form' . $attributes . $enctype . '>' . $childElements . $formHiddenElements . $hiddenToken . '</form>
		';
	}

	/**
	 * Wraps the specified control/inner text in a full unit.
	 *
	 * @param string Row label
	 * @param string Text of the control
	 * @param string Unique ID for the control
	 * @param array  Standard row options:
	 * * hint - hint text shown under leabel
	 * * class - class to apply to whole unit
	 * * explain - text to show under control
	 * * labelHidden - boolean, if true label is not shown
	 * * html - arbitrary html to add between control and explain text
	 *
	 * @return string Control wrapped in label/row markup
	 */
	protected static function _wrapControlUnit($label, $controlText, $id, array $rowOptions)
	{
		$hint = (!empty($rowOptions['hint']) ? ' <dfn>' . $rowOptions['hint'] . '</dfn>' : '');
		$class = (!empty($rowOptions['class']) ? " $rowOptions[class]" : '');
		$explain = (!empty($rowOptions['explain']) ? "\n" . '<p class="explain">' . $rowOptions['explain'] . '</p>' : '');
		$labelClass = (!empty($rowOptions['labelHidden']) ? ' surplusLabel' : '');
		$dataAttributes = self::_getDataAttributesAsString($rowOptions);

		if ($id)
		{
			$label = '<label for="' . $id . '">' . $label . '</label>';
		}

		return '
			<dl class="ctrlUnit' . $labelClass . $class . '"' . $dataAttributes . '>
				<dt>' . $label . $hint . '</dt>
				<dd>
					' . $controlText . (isset($rowOptions['html']) ? "\n$rowOptions[html]" : '') . $explain . '
				</dd>
			</dl>
		';
	}

	/**
	 * Outputs a control unit row. This does not contain any input field unless you manually
	 * include one. Note that the label will not be "for" any input.
	 *
	 * @param string $label Label text
	 * @param array $rowOptions Collection of options that relate to the row
	 *
	 * @return string
	 */
	public static function controlUnit($label, array $rowOptions = array())
	{
		return self::_wrapControlUnit($label, '', '', $rowOptions);
	}

	/**
	 * Outputs a submit unit row containing submit or reset buttons (and any additional HTML).
	 *
	 * @param string $childText Output from text that was within the submitunit tag
	 * @param array $controlOptions Options for this control
	 *
	 * @return string
	 */
	public static function submitUnit($childText, array $controlOptions = array())
	{
		if (!empty($controlOptions['save']))
		{
			$saveKey = (!empty($controlOptions['savekey']) ? $controlOptions['savekey'] : 's');
			$name = (!empty($controlOptions['name']) ? ' name="' . $controlOptions['name'] . '"' : '');
			$saveClass = (!empty($controlOptions['saveclass']) ? " $controlOptions[saveclass]" : '');

			$prepend = "\n" . '<input type="submit"' . $name . ' value="' . $controlOptions['save'] . '" class="button primary' . $saveClass . '" accesskey="' . $saveKey . '" />';
		}
		else
		{
			$prepend = '';
		}

		if (!empty($controlOptions['reset']))
		{
			$resetKey = (!empty($controlOptions['resetkey']) ? $controlOptions['resetkey'] : 'r');
			$resetClass = (!empty($controlOptions['resetclass']) ? " $controlOptions[resetclass]" : '');

			$append = '<input type="reset" value="' . $controlOptions['reset'] . '" class="button' . $resetClass . '" accesskey="' . $resetKey . '" />' . "\n";
		}
		else
		{
			$append = '';
		}

		if ($childText === '')
		{
			$childText = "\n";
		}

		return '<dl class="ctrlUnit submitUnit"><dt></dt><dd>' . $prepend . $childText . $append . '</dd></dl>';
	}

	/**
	 * Gets a text, password, search, number (etc) text-type input.
	 * Standard text inputs may span multiple rows, turning into a textarea.
	 *
	 * @param string $type Type of input requested; either text or password
	 * @param string $name Name of the input field. Already HTML escaped.
	 * @param string $value Default value for the input field. Already HTML escaped.
	 * @param array $controlOptions Array of options for the control
	 * @param string $id Optional ID for the input. If not specified, one will be generated.
	 *
	 * @return string
	 */
	protected static function _getTextInput($type, $name, $value, array $controlOptions, $id = '')
	{
		if ($id === '')
		{
			$id = self::_getControlId($name, $controlOptions);
		}

		$type = htmlspecialchars($type);

		$title = (!empty($controlOptions['title']) ? ' title="' . $controlOptions['title'] . '"' : '');
		$placeholder = (!empty($controlOptions['placeholder']) ? ' placeholder="' . $controlOptions['placeholder'] . '"' : '');
		$autofocus = (!empty($controlOptions['autofocus']) ? ' autofocus="autofocus"' : '');
		$autocomplete = (!empty($controlOptions['autocomplete']) ? ' autocomplete="' . $controlOptions['autocomplete'] . '"' : '');
		$size = (!empty($controlOptions['size']) ? ' size="' . $controlOptions['size'] . '"' : '');
		$maxlength = (!empty($controlOptions['maxlength']) ? ' maxlength="' . $controlOptions['maxlength'] . '"' : '');

		// input:number spinbox type
		$step = (($type == 'number' && isset($controlOptions['step'])) ? ' step="' . $controlOptions['step'] . '"' : '');
		$min  = (($type == 'number' && isset($controlOptions['min']))  ? ' min="'  . $controlOptions['min']  . '"' : '');
		$max  = (($type == 'number' && isset($controlOptions['max']))  ? ' max="'  . $controlOptions['max']  . '"' : '');

		// input:search type
		if ($type == 'search')
		{
			$results = ' results="' . (!empty($controlOptions['results']) ? intval($controlOptions['results']) : 0) . '"';
		}
		else
		{
			$results = '';
		}

		if (!empty($controlOptions['code']))
		{
			// add code class for this, add wrap for text areas below
			$controlOptions = self::_appendClasses($controlOptions, 'inputclass', 'code');

			if (empty($controlOptions['dir']))
			{
				$controlOptions['dir'] = 'ltr';
			}
		}

		$inputClass = (!empty($controlOptions['inputclass']) ? ' ' . $controlOptions['inputclass'] : '');
		$dir = (!empty($controlOptions['dir']) ? ' dir="' . $controlOptions['dir'] . '"' : '');
		$dataAttributes = self::_getDataAttributesAsString($controlOptions);
		$after = (!empty($controlOptions['after']) ? $controlOptions['after'] : '');

		if (!empty($controlOptions['rows']) && $type == 'text')
		{
			$cols = (!empty($controlOptions['size']) ? $controlOptions['size'] : 60);
			$wrap = (!empty($controlOptions['code']) ? ' wrap="off"' : '');

			return "<textarea name=\"{$name}\" rows=\"{$controlOptions['rows']}\" cols=\"{$cols}\"{$title}{$placeholder}{$autofocus}{$wrap}{$dir} class=\"textCtrl{$inputClass}\"{$dataAttributes} id=\"{$id}\">{$value}</textarea>$after";
		}
		else
		{
			return "<input type=\"{$type}\" name=\"{$name}\" value=\"{$value}\"{$size}{$maxlength}{$title}{$placeholder}{$autofocus}{$autocomplete}{$dir}{$step}{$min}{$max}{$results} class=\"textCtrl{$inputClass}\"{$dataAttributes} id=\"{$id}\" />$after";
		}
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a single text box.
	 *
	 * @param string Label for the row
	 * @param string Name of the text box
	 * @param string Default value of the text box
	 * @param array  Options that relate to the row
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function textBoxUnit($label, $name, $value, array $rowOptions = array(), array $controlOptions = array())
	{
		$id = self::_getControlId($name, $controlOptions);
		$type = (!empty($controlOptions['type']) ? $controlOptions['type'] : 'text');

		$input = self::_getTextInput($type, $name, $value, $controlOptions, $id);

		return self::_wrapControlUnit($label, $input, $id, $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a single text box.
	 *
	 * @param string Name of the text box
	 * @param string Default value of the text box
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function textBox($name, $value, array $controlOptions = array())
	{
		$type = (!empty($controlOptions['type']) ? $controlOptions['type'] : 'text');

		return self::_getTextInput($type, $name, $value, $controlOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a single password input.
	 *
	 * @param string Label for the row
	 * @param string Name of the text box
	 * @param string Default value of the text box
	 * @param array  Options that relate to the row
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function passwordUnit($label, $name, $value, array $rowOptions = array(), array $controlOptions = array())
	{
		$id = self::_getControlId($name, $controlOptions);
		$input = self::_getTextInput('password', $name, $value, $controlOptions, $id);

		return self::_wrapControlUnit($label, $input, $id, $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a single password input.
	 *
	 * @param string Name of the text box
	 * @param string Default value of the text box
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function password($name, $value, array $controlOptions = array())
	{
		return self::_getTextInput('password', $name, $value, $controlOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a single file upload input.
	 *
	 * @param string Label for the row
	 * @param string Name of the input
	 * @param string Default value of the upload box (likely ignored by browsers)
	 * @param array  Options that relate to the row
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function uploadUnit($label, $name, $value, array $rowOptions = array(), array $controlOptions = array())
	{
		$id = self::_getControlId($name, $controlOptions);
		$input = self::_getUploadInput($name, $value, $controlOptions, $id);

		return self::_wrapControlUnit($label, $input, $id, $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a single file upload input.
	 *
	 * @param string Name of the input
	 * @param string Default value of the text box (likely ignored)
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function upload($name, $value, array $controlOptions = array())
	{
		return self::_getUploadInput($name, $value, $controlOptions);
	}

	/**
	 * Gets a upload input.
	 *
	 * @param string $name Name of the input
	 * @param string $value Default selected option
	 * @param array $controlOptions Control options
	 * @param string $id Optional ID for the input. If not specified, one will be generated.
	 *
	 * @return string
	 */
	protected static function _getUploadInput($name, $value, array $controlOptions, $id = '')
	{
		$inputClass = (!empty($controlOptions['inputclass']) ? ' class="' . $controlOptions['inputclass'] . '"' : '');
		$dataAttributes = self::_getDataAttributesAsString($controlOptions);

		return "<input type=\"file\" name=\"{$name}\" value=\"{$value}\"{$inputClass}{$dataAttributes} id=\"{$id}\" />";
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a single spin box input.
	 *
	 * @param string Label for the row
	 * @param string Name of the text box
	 * @param string Default value of the text box
	 * @param array  Options that relate to the row
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function spinBoxUnit($label, $name, $value, array $rowOptions = array(), array $controlOptions = array())
	{
		$id = self::_getControlId($name, $controlOptions);
		$input = self::_getSpinBoxInput($name, $value, $controlOptions, $id);

		return self::_wrapControlUnit($label, $input, $id, $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a single spin box input.
	 *
	 * @param string Name of the text box
	 * @param string Default value of the text box
	 * @param array  Options that relate to the control
	 *
	 * @return string
	 */
	public static function spinBox($name, $value, array $controlOptions = array())
	{
		return self::_getSpinBoxInput($name, $value, $controlOptions);
	}

	/**
	 * Gets a spin box input.
	 *
	 * @param string $name Name of the input
	 * @param string $value Default selected option
	 * @param array $controlOptions Control options
	 * @param string $id Optional ID for the input. If not specified, one will be generated.
	 *
	 * @return string
	 */
	protected static function _getSpinBoxInput($name, $value, array $controlOptions, $id = '')
	{
		$value = floatval($value);

		if (!empty($controlOptions['step']))
		{
			$controlOptions['step'] = floatval($controlOptions['step']);
		}
		else
		{
			$controlOptions['step'] = 1;
		}

		if (isset($controlOptions['min']) && $controlOptions['min'] !== '')
		{
			$controlOptions['min'] = floatval($controlOptions['min']);
		}

		if (isset($controlOptions['max']) && $controlOptions['max'] !== '')
		{
			$controlOptions['max'] = floatval($controlOptions['max']);
		}

		$controlOptions = self::_appendClasses($controlOptions, 'inputclass', 'number SpinBox');

		return self::_getTextInput('number', $name, $value, $controlOptions, $id);
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a combo box input.
	 *
	 * @param string $label Label for the unit
	 * @param string $name  Name of the text box
	 * @param string $value Default value for the text box
	 * @param array $choices Choices for the select
	 * @param array $rowOptions Options that relate to the whole row
	 * @param array $controlOptions Options that relate to the text/select controls
	 *
	 * @return string
	 */
	public static function comboBoxUnit($label, $name, $value, array $choices, array $rowOptions = array(), array $controlOptions = array())
	{
		$id = self::_getControlId($name, $controlOptions);
		$input = self::_getComboBoxInput($name, $value, $choices, $controlOptions, $id);

		return self::_wrapControlUnit($label, $input, $id, $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a combo box input.
	 *
	 * @param string $name  Name of the text box
	 * @param string $value Default value for the text box
	 * @param array $choices Choices for the select
	 * @param array $controlOptions Options that relate to the text/select controls
	 *
	 * @return string
	 */
	public static function comboBox($name, $value, array $choices, array $controlOptions = array())
	{
		return self::_getComboBoxInput($name, $value, $choices, $controlOptions);
	}

	/**
	 * Gets a combo box input.
	 *
	 * @param string $name Name of the select input. If multiple selections are allow, [] will be appended.
	 * @param string $value Default selected option
	 * @param array $choices Choices for the select
	 * @param array $controlOptions Control options
	 * @param string $id Optional ID for the input. If not specified, one will be generated.
	 *
	 * @return string
	 */
	protected static function _getComboBoxInput($name, $value, array $choices, array $controlOptions, $id = '')
	{
		if ($id === '')
		{
			$id = self::_getControlId($name, $controlOptions);
		}

		$controlOptions = self::_appendClasses($controlOptions, 'inputclass', 'ComboBox');

		$textInput = self::_getTextInput('text', $name, $value, $controlOptions, $id);
		$choiceHtml = self::_processControlChoices(
			$choices,
			$value,
			array(
				'group' => array('self', '_getComboBoxGroupHtml'),
				'option' => array('self', '_getComboBoxOptionHtml')
			),
			array('allowNestedGroups' => 1)
		);

		return $textInput . "\n<select class=\"ComboBox\">\n$choiceHtml</select>";
	}

	/**
	 * Callback for generating combo box optgroup HTML.
	 *
	 * @param $label Label for the group
	 * @param $optionHtml HTML for the options within the group
	 *
	 * @return string
	 */
	protected static function _getComboBoxGroupHtml($label, $optionHtml)
	{
		return "<optgroup label=\"$label\">\n$optionHtml</optgroup>\n";
	}

	/**
	 * Callback for generating combo box option HTML
	 *
	 * @param string $value Value for the option (what it submitted to the server)
	 * @param string $label Label for the option (printable)
	 *
	 * @return string
	 */
	protected static function _getComboBoxOptionHtml($value, $label)
	{
		return "<option>$label</option>\n";
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a single select input.
	 *
	 * @param string $label Label for the unit
	 * @param string $name  Name of the select
	 * @param string $value Selected value for the select
	 * @param array $choices Choices for the select
	 * @param array $rowOptions Options that relate to the whole row
	 * @param array $controlOptions Options that relate to the select control
	 *
	 * @return string
	 */
	public static function selectUnit($label, $name, $value, array $choices, array $rowOptions = array(), array $controlOptions = array())
	{
		$id = self::_getControlId($name, $controlOptions);
		$input = self::_getSelectInput($name, $value, $choices, $controlOptions, $id);

		return self::_wrapControlUnit($label, $input, $id, $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a single select input.
	 *
	 * @param string $name  Name of the select
	 * @param string $value Selected value for the select
	 * @param array $choices Choices for the select
	 * @param array $controlOptions Options that relate to the select control
	 *
	 * @return string
	 */
	public static function select($name, $value, array $choices, array $controlOptions = array())
	{
		return self::_getSelectInput($name, $value, $choices, $controlOptions);
	}

	/**
	 * Gets a select input.
	 *
	 * @param string $name Name of the select input. If multiple selections are allow, [] will be appended.
	 * @param string $value Default selected option
	 * @param array $choices Choices for the select
	 * @param array $controlOptions Control options
	 * @param string $id Optional ID for the input. If not specified, one will be generated.
	 *
	 * @return string
	 */
	protected static function _getSelectInput($name, $value, array $choices, array $controlOptions, $id = '')
	{
		if ($id === '')
		{
			$id = self::_getControlId($name, $controlOptions);
		}

		$choiceHtml = self::_processControlChoices(
			$choices,
			$value,
			array(
				'group' => array('self', '_getSelectGroupHtml'),
				'option' => array('self', '_getSelectOptionHtml')
			),
			array('allowNestedGroups' => 1)
		);

		$multiple = (!empty($controlOptions['multiple']) ? ' multiple="multiple"' : '');
		if ($multiple)
		{
			$name .= "[]";
		}
		if ($multiple && empty($controlOptions['size']))
		{
			$controlOptions['size'] = 5;
		}

		$title = (!empty($controlOptions['title']) ? ' title="' . $controlOptions['title'] . '"' : '');
		$size = (!empty($controlOptions['size']) ? ' size="' . $controlOptions['size'] . '"' : '');
		$inputClass = (!empty($controlOptions['inputclass']) ? " $controlOptions[inputclass]" : '');
		$dataAttributes = self::_getDataAttributesAsString($controlOptions);

		return "<select name=\"$name\"$title$size$multiple class=\"textCtrl$inputClass\"$dataAttributes id=\"$id\">\n$choiceHtml</select>";
	}

	/**
	 * Callback for generating select optgroup HTML.
	 *
	 * @param $label Label for the group
	 * @param $optionHtml HTML for the options within the group
	 *
	 * @return string
	 */
	protected static function _getSelectGroupHtml($label, $optionHtml)
	{
		return "<optgroup label=\"$label\">\n$optionHtml</optgroup>\n";
	}

	/**
	 * Callback for generating select option HTML
	 *
	 * @param string $value Value for the option (what it submitted to the server)
	 * @param string $label Label for the option (printable)
	 * @param boolean $selected Whether this option is selected
	 * @param array $extra Extra data about the option
	 *
	 * @return string
	 */
	protected static function _getSelectOptionHtml($value, $label, $selected, array $extra = array())
	{
		$selectedHtml = ($selected ? ' selected="selected"' : '');
		$class = (isset($extra['class']) ? " class=\"$extra[class]\"" : '');

		if (!empty($extra['depth']))
		{
			$prefix = str_repeat('&nbsp; &nbsp; ', $extra['depth']);
		}
		else
		{
			$prefix = '';
		}

		if (!empty($extra['disabled']))
		{
			$disabledHtml = ' disabled="disabled"';
		}
		else
		{
			$disabledHtml = '';
		}

		return "<option value=\"$value\"$class$selectedHtml$disabledHtml>$prefix$label</option>\n";
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a collection of radio inputs.
	 *
	 * @param string $label Label for the unit
	 * @param string $name  Name of the radios
	 * @param string $value Selected value for the radio
	 * @param array $choices Choices for the radios
	 * @param array $rowOptions Options that relate to the whole row
	 * @param array $controlOptions Options that relate to the radio controls
	 *
	 * @return string
	 */
	public static function radioUnit($label, $name, $value, array $choices, array $rowOptions = array(), array $controlOptions = array())
	{
		$input = self::_getRadioInput($name, $value, $choices, $controlOptions);
		return self::_wrapControlUnit($label, $input, '', $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a collection of radio inputs.
	 *
	 * @param string $name  Name of the radios
	 * @param string $value Selected value for the radio
	 * @param array $choices Choices for the radios
	 * @param array $controlOptions Options that relate to the radio controls
	 *
	 * @return string
	 */
	public static function radio($name, $value, array $choices, array $controlOptions = array())
	{
		return self::_getRadioInput($name, $value, $choices, $controlOptions);
	}

	/**
	 * Gets a collection of radio inputs.
	 *
	 * @param string $name Name of the radop inputs.
	 * @param string $value Default selected option
	 * @param array $choices Choices for the radios
	 * @param array $controlOptions Control options
	 *
	 * @return string
	 */
	protected static function _getRadioInput($name, $value, array $choices, array $controlOptions)
	{
		$options = array('defaultName' => $name);
		if (!empty($controlOptions['title']))
		{
			$options['labelTitle'] = $controlOptions['title'];
		}

		$choiceHtml = self::_processControlChoices(
			$choices,
			$value,
			array(
				'group' => array('self', '_getRadioGroupHtml'),
				'option' => array('self', '_getRadioOptionHtml')
			),
			$options
		);

		$dataAttributes = self::_getDataAttributesAsString($controlOptions);

		$id = (!empty($controlOptions['id']) ? ' id="' . $controlOptions['id'] . '"' : '');
		$listClass = (!empty($controlOptions['listclass']) ? ' class="' . $controlOptions['listclass'] . '"' : '');

		return "<ul{$listClass}{$dataAttributes}{$id}>\n$choiceHtml</ul>";
	}

	/**
	 * Callback for generating radio optgroup HTML. At this time, optgroups are ignored.
	 *
	 * @param $label Label for the group
	 * @param $optionHtml HTML for the options within the group
	 *
	 * @return string
	 */
	protected static function _getRadioGroupHtml($label, $optionHtml)
	{
		return $optionHtml;
	}

	/**
	 * Callback for generating radio option HTML.
	 *
	 * @param string $value Value for the option (what it submitted to the server)
	 * @param string $label Label for the option (printable)
	 * @param boolean $selected Whether this option is selected
	 * @param array $extra Extra data about the radio
	 * @param array $options Parsing options
	 *
	 * @return string
	 */
	protected static function _getRadioOptionHtml($value, $label, $selected, array $extra = array(), array $options = array())
	{
		$selectedHtml = ($selected ? ' checked="checked"' : '');
		$name = (isset($options['defaultName']) ? $options['defaultName'] : '');
		$hint = (isset($extra['hint']) ? ' <p class="hint">' . $extra['hint'] . '</p>' : '');

		$id = self::_getControlId($name . '_' . $value);

		$disablerHtml = self::_getDisablerHtml($extra, $id);

		if (!empty($extra['inputclass']))
		{
			$inputClass = ' class="' . $extra['inputclass'] . ($disablerHtml ? ' Disabler' : '') . '"';
		}
		else
		{
			$inputClass = ($disablerHtml ? ' class="Disabler"' : '');
		}

		if (!empty($extra['depth']))
		{
			$prefix = str_repeat('&nbsp; &nbsp; ', $extra['depth']);
		}
		else
		{
			$prefix = '';
		}

		$class = (isset($extra['class']) ? " class=\"$extra[class]\"" : '');
		$title = (isset($extra['title']) ? " title=\"$extra[title]\"" : '');

		return "<li><label for=\"$id\"$class$title><input type=\"radio\" name=\"$name\" value=\"$value\"$inputClass id=\"$id\"$selectedHtml />"
			. "$prefix $label</label>$hint$disablerHtml</li>\n";
	}

	/**
	 * Helper to prepare and return the HTML for a control unit with a collection of check box inputs.
	 *
	 * @param string $label Label for the unit
	 * @param string $name Default name of the chec kbox. If used, [] will be appended.
	 * @param array $choices Choices for the check box
	 * @param array $rowOptions Options that relate to the whole row
	 * @param array $controlOptions Options that relate to the check box controls
	 *
	 * @return string
	 */
	public static function checkBoxUnit($label, $name, array $choices, array $rowOptions = array(), array $controlOptions = array())
	{
		$input = self::_getCheckBoxInput($name, $choices, $controlOptions);
		return self::_wrapControlUnit($label, $input, '', $rowOptions);
	}

	/**
	 * Helper to prepare and return the HTML for a collection of check box inputs.
	 *
	 * @param string $name Default name of the chec kbox. If used, [] will be appended.
	 * @param array $choices Choices for the check box
	 * @param array $controlOptions Options that relate to the check box controls
	 *
	 * @return string
	 */
	public static function checkBox($name, array $choices, array $controlOptions = array())
	{
		return self::_getCheckBoxInput($name, $choices, $controlOptions);
	}

	/**
	 * Gets a collection of check box inputs.
	 *
	 * @param string $name Default name of the check box. If used, [] will be appended.
	 * @param array $choices Choices for the check box
	 * @param array $controlOptions Control options
	 *
	 * @return string
	 */
	protected static function _getCheckBoxInput($name, array $choices, array $controlOptions)
	{
		$options = array('defaultName' => $name);
		if (!empty($controlOptions['title']))
		{
			$options['labelTitle'] = $controlOptions['title'];
		}

		$choiceHtml = self::_processControlChoices(
			$choices,
			false,
			array(
				'group' => array('self', '_getCheckBoxGroupHtml'),
				'option' => array('self', '_getCheckBoxOptionHtml')
			),
			$options
		);

		$dataAttributes = self::_getDataAttributesAsString($controlOptions);
		$id = (!empty($controlOptions['id']) ? ' id="' . $controlOptions['id'] . '"' : '');
		$listClass = (!empty($controlOptions['listclass']) ? ' class="' . $controlOptions['listclass'] . '"' : '');

		return "<ul{$listClass}{$dataAttributes}{$id}>\n$choiceHtml</ul>";
	}

	/**
	 * Callback for generating check box optgroup HTML. At this time, optgroups are ignored.
	 *
	 * @param $label Label for the group
	 * @param $optionHtml HTML for the options within the group
	 *
	 * @return string
	 */
	protected static function _getCheckBoxGroupHtml($label, $optionHtml)
	{
		return $optionHtml;
	}

	/**
	 * Callback for generating check box option HTML.
	 *
	 * @param string $value Value for the option (what it submitted to the server)
	 * @param string $label Label for the option (printable)
	 * @param boolean $selected Whether this option is selected
	 * @param array $extra Extra data about the check box
	 * @param array $options Parsing options
	 *
	 * @return string
	 */
	protected static function _getCheckBoxOptionHtml($value, $label, $selected, array $extra = array(), array $options = array())
	{
		$selectedHtml = ($selected ? ' checked="checked"' : '');
		$hint = (isset($extra['hint']) ? ' <p class="hint">' . $extra['hint'] . '</p>' : '');

		if (!empty($extra['name']))
		{
			if ($extra['name'] == '__NO_NAME__')
			{
				$name = '';
			}
			else if ($extra['name'][0] == '[' && isset($options['defaultName']))
			{
				$name = $options['defaultName'] . $extra['name'];
			}
			else
			{
				$name = $extra['name'];
			}
		}
		else if (isset($options['defaultName']))
		{
			if (substr($options['defaultName'], -2) == '[]')
			{
				$name = $options['defaultName'];
			}
			else
			{
				$name = $options['defaultName'] . '[]';
			}
		}
		else
		{
			$name = '';
		}

		if ($value === '')
		{
			$value = '1';
		}

		$id = (!empty($extra['id']) ? $extra['id'] : self::_getControlId($name . '_' . $value));

		$disablerHtml = self::_getDisablerHtml($extra, $id);

		if (!empty($extra['inputclass']))
		{
			$inputClass = ' class="' . $extra['inputclass'] . ($disablerHtml ? ' Disabler' : '') . '"';
		}
		else
		{
			$inputClass = ($disablerHtml ? ' class="Disabler"' : '');
		}

		$name = ($name !== '' ? " name=\"$name\"" : '');
		$class = (isset ($extra['class']) ? " class=\"$extra[class]\"" : '');
		$title = (isset ($extra['title']) ? " title=\"$extra[title]\"" : '');

		return "<li><label for=\"$id\"$class$title><input type=\"checkbox\"$name value=\"$value\"$inputClass id=\"$id\"$selectedHtml />"
			. " $label</label>$hint$disablerHtml</li>\n";
	}

	/**
	 * Gets the necessary HTML to make disabler controls (if dependent controls
	 * are specified).
	 *
	 * @param array $optionInfo Information about the option. Disablers searched for in "disabled" key.
	 * @param string $optionId ID that is being used for the parent of the disabler
	 *
	 * @return string HTML for the disabled controls, if applicable
	 */
	protected static function _getDisablerHtml(array $optionInfo, $optionId)
	{
		$disablerHtml = '';
		if (!empty($optionInfo['disabled']))
		{
			$disablerHtml = "\n<ul id=\"{$optionId}_Disabler\">";
			foreach ($optionInfo['disabled'] AS  $disabled)
			{
				$disablerHtml .= "\n<li>$disabled</li>";
			}
			$disablerHtml .= "\n</ul>\n";
		}

		return $disablerHtml;
	}

	/**
	 * Processes the list of choices for a control into the appropriate HTML output.
	 *
	 * @param array $choices Array of choices
	 * @param string $selectedValue Selected value
	 * @param array $callbacks Callbacks. Define 2 keys: group and option
	 * @param array $options Extra options to manipulate the choices.
	 *
	 * @return string
	 */
	protected static function _processControlChoices(array $choices, $selectedValue, array $callbacks, array $options = array())
	{
		if (empty($choices))
		{
			return '';
		}

		$output = '';
		$options = array_merge(array(
			'allowNestedGroups' => 1000,
			'defaultName' => ''
		), $options);

		$childOptions = $options;
		$childOptions['allowNestedGroups']--;

		foreach ($choices AS  $label => $choice)
		{
			if (is_string($label) || !is_array($choice) || !isset($choice['label']))
			{
				if (is_array($choice))
				{
					// opt group
					$groupOptionHtml = self::_processControlChoices($choice, $selectedValue, $callbacks, $childOptions);
					if ($groupOptionHtml)
					{
						if ($options['allowNestedGroups'] > 0)
						{
							$output .= call_user_func($callbacks['group'], $label, $groupOptionHtml, $options);
						}
						else
						{
							$output .= $groupOptionHtml;
						}
					}
				}
				else
				{
					// simple value => label construct
					$value = $label;
					$label = $choice;

					if ($selectedValue !== null && $selectedValue !== false)
					{
						$selected = (strval($selectedValue) == strval($value));
					}
					else
					{
						$selected = false;
					}

					$output .= call_user_func($callbacks['option'], $value, $label, $selected, array(), $options);
				}
			}
			else
			{
				// advanced construct
				if (!empty($choice['selected']))
				{
					$selected = (boolean)$choice['selected'];
				}
				else if ($selectedValue !== null && $selectedValue !== false && isset($choice['value']))
				{
					if (is_array($selectedValue))
					{
						$selected = in_array($choice['value'], $selectedValue);
					}
					else
					{
						$selected = (strval($selectedValue) == strval($choice['value']));
					}
				}
				else
				{
					$selected = false;
				}

				$choice['value'] = (isset($choice['value']) ? $choice['value'] : '');

				$output .= call_user_func($callbacks['option'], $choice['value'], $choice['label'], $selected, $choice, $options);
			}
		}

		return $output;
	}

	/**
	 * Returns a list item element.
	 *
	 * @param array $controlOptions Options relating to the item
	 * @param array $popups A list of popups (HTML) that belong to the item
	 *
	 * @return string List row HTML
	 */
	public static function listItem(array $controlOptions, array $popups)
	{
		$extraClasses = (!empty($controlOptions['class']) ? ' ' . $controlOptions['class'] : '');
		$labelClass = (!empty($controlOptions['labelclass']) ? ' class="' . $controlOptions['labelclass'] . '"' : '');
		$href = (!empty($controlOptions['href']) ? $controlOptions['href'] : '');
		$linkStyle = (!empty($controlOptions['linkstyle']) ? ' style="' . $controlOptions['linkstyle'] . '"' : '');
		$beforeLabel = (!empty($controlOptions['beforelabel']) ? $controlOptions['beforelabel'] : '');

		if (empty($controlOptions['delete']))
		{
			$delete = '';
		}
		else
		{
			$deletePhrase = new XenForo_Phrase('delete') . '...';

			$deleteHint = (!empty($controlOptions['deletehint']) ? $controlOptions['deletehint'] : $deletePhrase);

			if ($controlOptions['delete'] === '#')
			{
				$delete = '<a class="delete secondaryContent"></a>' . "\n";
			}
			else
			{
				$delete = '<a href="' . $controlOptions['delete'] . '" class="delete OverlayTrigger secondaryContent" title="' . $deleteHint . '"><span>' . $deletePhrase . '</span></a>' . "\n";
			}
		}

		$href = ($href ? ' href="' . $href . '"' : '');

		$output = '
<li class="listItem primaryContent' . $extraClasses . '" id="' . self::getListItemId($controlOptions['id']) . '">
	' . $delete . '
	' . (!empty($controlOptions['html']) ? $controlOptions['html'] : '') . '
';

		$popupOutput = '';
		foreach ($popups AS  $popup)
		{
			if ($popup !== '')
			{
				$popupOutput = $popup . "\n" . $popupOutput;
			}
		}
		$output .= $popupOutput;

		$output .= '
	<h4' . $labelClass . '>' . $beforeLabel . '
		<a' . $href . $linkStyle . '>
		<em>' . $controlOptions['label'] . '</em>' . (!empty($controlOptions['snippet']) ? '
		<dfn>' . $controlOptions['snippet'] . '</dfn>' : '') . '
	</a></h4>
</li>
';

		return $output;
	}

	/**
	 * Returns HTML for a quick and simple popup control.
	 *
	 * @param array $controlOptions Options for the control
	 * @param array $choices Choices within the popup; each will be an li
	 * @param string $wrapTag Tag to wrap menu in (div or ul, for example)
	 * @param string $extraMenuClass An extra class (or multiple) set as the menu class
	 *
	 * @return string Popup HTML
	 */
	public static function popup(array $controlOptions, array $choices, $wrapTag = 'div', $extraMenuClass = '')
	{
		// attached to .Popup
		$class = (!empty($controlOptions['class']) ? ' ' . $controlOptions['class'] : '');

		// attached to control
		$ctrlHref = (!empty($controlOptions['href']) ? ' href="' . $controlOptions['href'] . '"' : '');
		$ctrlClass = (!empty($controlOptions['ctrlclass']) ? ' class="' . $controlOptions['ctrlclass'] . '"' : '');

		// attached to .Menu
		$menuClass = (!empty($controlOptions['menuclass']) ? ' ' . $controlOptions['menuclass'] : '');
		if ($extraMenuClass)
		{
			$menuClass .= " $extraMenuClass";
		}

		$choiceOutput = '';
		foreach ($choices AS  $choice)
		{
			if (isset($choice['displayif']) && empty($choice['displayif']))
			{
				continue;
			}

			if (isset($choice['html']))
			{
				$choiceOutput .= "\t\t<li>$choice[html]</li>\n";
			}
			else
			{
				$choiceOutput .= "\t\t<li><a href=\"$choice[href]\">$choice[text]</a></li>\n";
			}
		}

		if ($choiceOutput === '')
		{
			return '';
		}

		return '
<' . $wrapTag . ' class="Popup' . $class . '">
	<a' . $ctrlHref . $ctrlClass . ' rel="Menu">' . $controlOptions['title'] . '</a>
	<div class="Menu">
		<div class="primaryContent menuHeader"><h3>' . $controlOptions['title'] . '</h3></div>
		<ul class="secondaryContent blockLinksList ' . $menuClass . '">
	' . $choiceOutput . '
		</ul>
	</div>
</' . $wrapTag . '>
';
	}

	/**
	 * Returns the ID used for filter list items and lastHash
	 *
	 * @param mixed $id
	 *
	 * @return string
	 */
	public static function getListItemId($id)
	{
		return ($id !== '' ? '_' . str_replace('.', '-', $id) : '');
	}
}