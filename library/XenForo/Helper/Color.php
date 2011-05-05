<?php

class XenForo_Helper_Color
{
	/**
	 * Named HTML / CSS colors and their hex values.
	 * Matches the list provided in color_picker.js
	 *
	 * @var array
	 */
	public static $colors = array(
		'aliceblue' => 'f0f8ff',
		'antiquewhite' => 'faebd7',
		'aqua' => '00ffff',
		'aquamarine' => '7fffd4',
		'azure' => 'f0ffff',
		'beige' => 'f5f5dc',
		'bisque' => 'ffe4c4',
		'black' => '000000',
		'blanchedalmond' => 'ffebcd',
		'blue' => '0000ff',
		'blueviolet' => '8a2be2',
		'brown' => 'a52a2a',
		'burlywood' => 'deb887',
		'cadetblue' => '5f9ea0',
		'chartreuse' => '7fff00',
		'chocolate' => 'd2691e',
		'coral' => 'ff7f50',
		'cornflowerblue' => '6495ed',
		'cornsilk' => 'fff8dc',
		'crimson' => 'dc143c',
		'cyan' => '00ffff',
		'darkblue' => '00008b',
		'darkcyan' => '008b8b',
		'darkgoldenrod' => 'b8860b',
		'darkgray' => 'a9a9a9',
		'darkgreen' => '006400',
		'darkkhaki' => 'bdb76b',
		'darkmagenta' => '8b008b',
		'darkolivegreen' => '556b2f',
		'darkorange' => 'ff8c00',
		'darkorchid' => '9932cc',
		'darkred' => '8b0000',
		'darksalmon' => 'e9967a',
		'darkseagreen' => '8fbc8f',
		'darkslateblue' => '483d8b',
		'darkslategray' => '2f4f4f',
		'darkturquoise' => '00ced1',
		'darkviolet' => '9400d3',
		'deeppink' => 'ff1493',
		'deepskyblue' => '00bfff',
		'dimgray' => '696969',
		'dodgerblue' => '1e90ff',
		'firebrick' => 'b22222',
		'floralwhite' => 'fffaf0',
		'forestgreen' => '228b22',
		'fuchsia' => 'ff00ff',
		'gainsboro' => 'dcdcdc',
		'ghostwhite' => 'f8f8ff',
		'gold' => 'ffd700',
		'goldenrod' => 'daa520',
		'gray' => '808080',
		'green' => '008000',
		'greenyellow' => 'adff2f',
		'honeydew' => 'f0fff0',
		'hotpink' => 'ff69b4',
		'indianred' => 'cd5c5c',
		'indigo' => '4b0082',
		'ivory' => 'fffff0',
		'khaki' => 'f0e68c',
		'lavender' => 'e6e6fa',
		'lavenderblush' => 'fff0f5',
		'lawngreen' => '7cfc00',
		'lemonchiffon' => 'fffacd',
		'lightblue' => 'add8e6',
		'lightcoral' => 'f08080',
		'lightcyan' => 'e0ffff',
		'lightgoldenrodyellow' => 'fafad2',
		'lightgrey' => 'd3d3d3',
		'lightgreen' => '90ee90',
		'lightpink' => 'ffb6c1',
		'lightsalmon' => 'ffa07a',
		'lightseagreen' => '20b2aa',
		'lightskyblue' => '87cefa',
		'lightslategray' => '778899',
		'lightsteelblue' => 'b0c4de',
		'lightyellow' => 'ffffe0',
		'lime' => '00ff00',
		'limegreen' => '32cd32',
		'linen' => 'faf0e6',
		'magenta' => 'ff00ff',
		'maroon' => '800000',
		'mediumaquamarine' => '66cdaa',
		'mediumblue' => '0000cd',
		'mediumorchid' => 'ba55d3',
		'mediumpurple' => '9370d8',
		'mediumseagreen' => '3cb371',
		'mediumslateblue' => '7b68ee',
		'mediumspringgreen' => '00fa9a',
		'mediumturquoise' => '48d1cc',
		'mediumvioletred' => 'c71585',
		'midnightblue' => '191970',
		'mintcream' => 'f5fffa',
		'mistyrose' => 'ffe4e1',
		'moccasin' => 'ffe4b5',
		'navajowhite' => 'ffdead',
		'navy' => '000080',
		'oldlace' => 'fdf5e6',
		'olive' => '808000',
		'olivedrab' => '6b8e23',
		'orange' => 'ffa500',
		'orangered' => 'ff4500',
		'orchid' => 'da70d6',
		'palegoldenrod' => 'eee8aa',
		'palegreen' => '98fb98',
		'paleturquoise' => 'afeeee',
		'palevioletred' => 'd87093',
		'papayawhip' => 'ffefd5',
		'peachpuff' => 'ffdab9',
		'peru' => 'cd853f',
		'pink' => 'ffc0cb',
		'plum' => 'dda0dd',
		'powderblue' => 'b0e0e6',
		'purple' => '800080',
		'red' => 'ff0000',
		'rosybrown' => 'bc8f8f',
		'royalblue' => '4169e1',
		'saddlebrown' => '8b4513',
		'salmon' => 'fa8072',
		'sandybrown' => 'f4a460',
		'seagreen' => '2e8b57',
		'seashell' => 'fff5ee',
		'sienna' => 'a0522d',
		'silver' => 'c0c0c0',
		'skyblue' => '87ceeb',
		'slateblue' => '6a5acd',
		'slategray' => '708090',
		'snow' => 'fffafa',
		'springgreen' => '00ff7f',
		'steelblue' => '4682b4',
		'tan' => 'd2b48c',
		'teal' => '008080',
		'thistle' => 'd8bfd8',
		'tomato' => 'ff6347',
		'turquoise' => '40e0d0',
		'violet' => 'ee82ee',
		'wheat' => 'f5deb3',
		'white' => 'ffffff',
		'whitesmoke' => 'f5f5f5',
		'yellow' => 'ffff00',
		'yellowgreen' => '9acd32'
	);

	/**
	 * Regular expression to match an RGB color.
	 * Components captured to 1=r, 2=g, 3=b.
	 *
	 * @var string
	 */
	public static $rgbRegex = '/^rgb\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)\s*\)$/i';

	/**
	 * Regular expression to match an RGBA color.
	 * Components captured to 1=r, 2=g, 3=b, 4=a.
	 *
	 * @var string
	 */
	public static $rgbaRegex = '/^rgba\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*([0-1](\.\d+)?)\s*\)$/i';

	/**
	 * Converts a color (named, hex or rgb) into an RGBA string
	 * Supports namedcolor, #abc, #abcdef and rgb(0, 128, 255)
	 *
	 * @param string $color
	 * @param float $alpha
	 *
	 * @return string Returns the original color if an RGBA match cannot be made
	 */
	public static function rgba($color, $alpha)
	{
		if (!$color)
		{
			return $color;
		}

		$color = strtolower($color);

		if (strpos($color, 'rgb') !== 0)
		{
			if ($color[0] == '#')
			{
				$color = substr($color, 1);

				switch (strlen($color))
				{
					case 3:
						$color = "$color[0]$color[0]$color[1]$color[1]$color[2]$color[2]";
						break;

					case 6:
						// already ok
						break;

					default:
						return '#' . $color;
				}
			}
			else if (isset(self::$colors[$color]))
			{
				$color = self::$colors[$color];
			}
			else
			{
				return $color;
			}

			$rgb = array(
				1 => hexdec(substr($color, 0, 2)),
				2 => hexdec(substr($color, 2, 2)),
				3 => hexdec(substr($color, 4, 2)),
			);
		}
		else if (!preg_match(self::$rgbRegex, $color, $rgb))
		{
			return $color;
		}

		return sprintf('rgba(%s, %s, %s, %s)', $rgb[1], $rgb[2], $rgb[3], $alpha);
	}

	/**
	 * Converts a color into a non-alpha-transparent version.
	 * Supports named color, #abc, #abcdef and rgb(a)
	 *
	 * @param string $color
	 *
	 * @return string
	 */
	public static function unRgba($color)
	{
		if (substr(strtolower($color), 0, 4) == 'rgba' && preg_match(self::$rgbaRegex, $color, $rgba))
		{
			$color = sprintf('rgb(%s, %s, %s)', $rgba[1], $rgba[2], $rgba[3]);
		}

		return $color;
	}
}