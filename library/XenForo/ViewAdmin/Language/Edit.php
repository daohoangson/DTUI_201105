<?php

class XenForo_ViewAdmin_Language_Edit extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$this->_params['languageParents'] = $this->_getLanguagesAsSelectList($this->_params['languages'], 1);
		return null;
	}

	protected function _getLanguagesAsSelectList(array $languageTree, $depthModifier = 0, $repeatText = '--')
	{
		$output = array();
		foreach ($languageTree AS $language)
		{
			$prefix = str_repeat($repeatText, $language['depth'] + $depthModifier);
			if ($prefix)
			{
				$prefix .= ' ';
			}
			$output[$language['language_id']] = $prefix . htmlspecialchars($language['title']);
		}

		return $output;
	}
}