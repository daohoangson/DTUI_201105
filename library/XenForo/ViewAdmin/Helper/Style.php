<?php

class XenForo_ViewAdmin_Helper_Style
{
	private function __construct()
	{
	}

	public static function getStylesAsSelectList(array $styleTree, $depthModifier = 0, $repeatText = '--')
	{
		$output = array();
		foreach ($styleTree AS $style)
		{
			$prefix = str_repeat($repeatText, $style['depth'] + $depthModifier);
			if ($prefix)
			{
				$prefix .= ' ';
			}
			$output[$style['style_id']] = $prefix . htmlspecialchars($style['title']);
		}

		return $output;
	}
}