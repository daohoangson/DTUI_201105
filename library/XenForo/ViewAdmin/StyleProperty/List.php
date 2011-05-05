<?php

class XenForo_ViewAdmin_StyleProperty_List extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$subGroups = array();
		$customized = false;

		foreach ($this->_params['scalars'] AS $propertyId => &$property)
		{
			if (!isset($subGroups[$property['sub_group']]))
			{
				$subGroups[$property['sub_group']] = array(
					'properties' => array(),
					'customized' => false
				);
			}

			if ($property['scalar_type'] == 'template')
			{
				$property['template'] = $this->createTemplateObject($property['scalar_parameters'], array(
					'property' => $property
				));
			}

			if ($property['canReset'])
			{
				$subGroups[$property['sub_group']]['customized'] = true;
				$customized = true;
			}

			$subGroups[$property['sub_group']]['properties'][$propertyId] = $property;
		}

		$this->_params['scalars'] = $subGroups;
		$this->_params['group']['customized'] = $customized;
	}
}