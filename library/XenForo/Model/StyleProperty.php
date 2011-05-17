<?php

/**
 * Model for style properties and style property definitions.
 * Note that throughout, style ID -1 is the ACP master.
 *
 * @package XenForo_StyleProperty
 */
class XenForo_Model_StyleProperty extends XenForo_Model
{
	protected static $_tempProperties = null;

	/**
	 * Gets the specified style property by its ID. Includes
	 * definition info.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getStylePropertyById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT property_definition.*,
				style_property.*
			FROM xf_style_property AS style_property
			INNER JOIN xf_style_property_definition AS property_definition ON
				(property_definition.property_definition_id = style_property.property_definition_id)
			WHERE style_property.property_id = ?
		', $id);
	}

	/**
	 * Gets the specified style property by its definition ID
	 * and style ID. Includes definition info.
	 *
	 * @param integer $definitionId
	 * @param integer $styleId
	 *
	 * @return array|false
	 */
	public function getStylePropertyByDefinitionAndStyle($definitionId, $styleId)
	{
		return $this->_getDb()->fetchRow('
			SELECT property_definition.*,
				style_property.*
			FROM xf_style_property AS style_property
			INNER JOIN xf_style_property_definition AS property_definition ON
				(property_definition.property_definition_id = style_property.property_definition_id)
			WHERE style_property.property_definition_id = ?
				AND style_property.style_id = ?
		', array($definitionId, $styleId));
	}

	/**
	 * Gets all style properties in the specified styles. This only
	 * includes properties that have been customized or initially defined
	 * in the specified styles. Includes definition info.
	 *
	 * @param array $styleIds
	 *
	 * @return array Format: [property id] => info
	 */
	public function getStylePropertiesInStyles(array $styleIds)
	{
		if (!$styleIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT property_definition.*,
				style_property.*
			FROM xf_style_property AS style_property
			INNER JOIN xf_style_property_definition AS property_definition ON
				(property_definition.property_definition_id = style_property.property_definition_id)
			WHERE style_property.style_id IN (' . $this->_getDb()->quote($styleIds) . ')
			ORDER BY property_definition.display_order
		', 'property_id');
	}

	/**
	 * Gets all style properties in a style with the specified definition IDs
	 * that have been customized or defined directly in the style.
	 *
	 * @param integer $styleId
	 * @param array $definitionIds
	 *
	 * @return array Format: [definition id] => info
	 */
	public function getStylePropertiesInStyleByDefinitions($styleId, array $definitionIds)
	{
		if (!$definitionIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT property_definition.*,
				style_property.*
			FROM xf_style_property AS style_property
			INNER JOIN xf_style_property_definition AS property_definition ON
				(property_definition.property_definition_id = style_property.property_definition_id)
			WHERE style_property.style_id = ?
				AND style_property.definition_id IN (' . $this->_getDb()->quote($definitionIds) . ')
			ORDER BY property_definition.display_order
		', 'property_definition_id', $styleId);
	}

	/**
	 * Gets the effective style properties in a style. This includes properties
	 * that have been customized/created in a parent style.
	 *
	 * Includes effectiveState key in each property (customized, inherited, default).
	 *
	 * @param integer $styleId
	 * @param array|null $path Path from style to root (earlier positions closer to style); if null, determined automatically
	 * @param array|null $properties List of properties in this style and all parent styles; if null, determined automatically
	 *
	 * @return array Format: [definition id] => info
	 */
	public function getEffectiveStylePropertiesInStyle($styleId, array $path = null, array $properties = null)
	{
		if ($path === null)
		{
			$path = $this->getParentPathFromStyle($styleId);
		}
		if ($properties === null)
		{
			$properties = $this->getStylePropertiesInStyles($path);
		}

		$effective = array();
		$propertyPriorities = array();
		foreach ($properties AS $property)
		{
			$definitionId = $property['property_definition_id'];
			$propertyPriority = array_search($property['style_id'], $path);

			if (!isset($propertyPriorities[$definitionId]) || $propertyPriority < $propertyPriorities[$definitionId])
			{
				switch ($property['style_id'])
				{
					case $property['definition_style_id']:
						$property['effectiveState'] = 'default';
						break;

					case $styleId:
						$property['effectiveState'] = 'customized';
						break;

					default:
						$property['effectiveState'] = 'inherited';
						break;
				}

				$effective[$definitionId] = $property;
				$propertyPriorities[$definitionId] = $propertyPriority;
			}
		}

		return $effective;
	}

	/**
	 * Gets the effective properties and groups in a style. Properties are organized
	 * within the groups (in properties key).
	 *
	 * @param integer $styleId
	 * @param array|null $path Path from style to root (earlier positions closer to style); if null, determined automatically
	 * @param array|null $properties List of properties in this style and all parent styles; if null, determined automatically
	 *
	 * @return array Format: [group name] => group info, with [properties][definition id] => property info
	 */
	public function getEffectiveStylePropertiesByGroup($styleId, array $path = null, array $properties = null)
	{
		if ($path === null)
		{
			$path = $this->getParentPathFromStyle($styleId);
		}

		if ($properties === null)
		{
			$properties = $this->getEffectiveStylePropertiesInStyle($styleId, $path, $properties);
		}

		$groups = $this->getEffectiveStylePropertyGroupsInStyle($styleId, $path);

		$invalidGroupings = array();
		foreach ($properties AS $definitionId => $property)
		{
			if (isset($groups[$property['group_name']]))
			{
				$groups[$property['group_name']]['properties'][$definitionId] = $property;
			}
			else
			{
				$invalidGroupings[$definitionId] = $property;
			}
		}

		if ($invalidGroupings)
		{
			$groups[''] = array(
				'property_group_id' => 0,
				'group_name' => '',
				'group_style_id' => $styleId,
				'title' => '(ungrouped)',
				'description' => '',
				'addon_id' => '',
				'properties' => $invalidGroupings
			);
		}

		return $groups;
	}

	/**
	 * Fetches all color palette properties for the specified style.
	 *
	 * @param integer $styleId
	 * @param array|null $path Path from style to root (earlier positions closer to style); if null, determined automatically
	 * @param array|null $properties List of properties in this style and all parent styles; if null, determined automatically
	 *
	 * @return array
	 */
	public function getColorPalettePropertiesInStyle($styleId, array $path = null, array $properties = null)
	{
		$groups = $this->getEffectiveStylePropertiesByGroup($styleId, $path, $properties);

		return $groups['color']['properties'];
	}

	/**
	 * Reorganizes a property list to key properties by name. This is only safe
	 * to do when getting properties (effective or not) for a single style.
	 *
	 * @param array $properties
	 *
	 * @return array
	 */
	public function keyPropertiesByName(array $properties)
	{
		$output = array();
		foreach ($properties AS $property)
		{
			$output[$property['property_name']] = $property;
		}

		return $output;
	}

	/**
	 * Filters a list of properties into 2 groups: scalar properties and css properties.
	 *
	 * @param array [scalar props, css props]
	 */
	public function filterPropertiesByType(array $properties)
	{
		$scalar = array();
		$css = array();

		foreach ($properties AS $key => $property)
		{
			if ($property['property_type'] == 'scalar')
			{
				$scalar[$key] = $property;
			}
			else
			{
				$css[$key] = $property;
			}
		}

		return array($scalar, $css);
	}

	/**
	 * Gets the specified style property group.
	 *
	 * @param integer $groupId
	 *
	 * @return array|false
	 */
	public function getStylePropertyGroupById($groupId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_style_property_group
			WHERE property_group_id = ?
		', $groupId);
	}

	/**
	 * Gets all style property groups defined in the specified styles.
	 *
	 * @param array $styleIds
	 *
	 * @return array Format: [property group id] => info
	 */
	public function getStylePropertyGroupsInStyles(array $styleIds)
	{
		if (!$styleIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_style_property_group
			WHERE group_style_id IN (' . $this->_getDb()->quote($styleIds) . ')
			ORDER BY display_order
		', 'property_group_id');
	}

	/**
	 * Gets the effective list of groups that apply to a style.
	 *
	 * @param integer $styleId
	 * @param array|null $path Path from style to root (earlier positions closer to style); if null, determined automatically
	 *
	 * @return array Format: [group name] => info
	 */
	public function getEffectiveStylePropertyGroupsInStyle($styleId, array $path = null)
	{
		if ($path === null)
		{
			$path = $this->getParentPathFromStyle($styleId);
		}

		$groups = $this->getStylePropertyGroupsInStyles($path);
		$output = array();
		foreach ($groups AS $group)
		{
			$output[$group['group_name']] = $group;
		}

		return $output;
	}

	/**
	 * Gets name-value pairs of style property groups in a style.
	 *
	 * @param integer $styleId
	 *
	 * @return array Format: [name] => title
	 */
	public function getStylePropertyGroupOptions($styleId)
	{
		$groups = $this->prepareStylePropertyGroups($this->getEffectiveStylePropertyGroupsInStyle($styleId));
		$output = array();
		foreach ($groups AS $group)
		{
			$output[$group['group_name']] = $group['title'];
		}

		return $output;
	}

	/**
	 * Prepares a style property group for display. If properties are found
	 * within, they will be automatically prepared.
	 *
	 * @param array $property
	 * @param integer|null $displayStyleId The ID of the style the groups/properties are being edited in
	 *
	 * @return array Prepared version
	 */
	public function prepareStylePropertyGroup(array $group, $displayStyleId = null)
	{
		$group['masterTitle'] = $group['title'];
		$group['masterDescription'] = $group['description'];
		if ($group['addon_id'])
		{
			$group['title'] = new XenForo_Phrase($this->getStylePropertyGroupTitlePhraseName($group));
			$group['description'] = new XenForo_Phrase($this->getStylePropertyGroupDescriptionPhraseName($group));
		}

		if (!$group['group_name'])
		{
			$group['canEdit'] = false;
		}
		else if ($displayStyleId === null)
		{
			$group['canEdit'] = $this->canEditStylePropertyDefinition($group['group_style_id']);
		}
		else
		{
			$group['canEdit'] = (
				$this->canEditStylePropertyDefinition($group['group_style_id'])
				&& $group['group_style_id'] == $displayStyleId
			);
		}

		if (!empty($group['properties']))
		{
			$group['properties'] = $this->prepareStyleProperties($group['properties'], $displayStyleId);
		}

		return $group;
	}

	/**
	 * Prepares a list of style property groups. If properties are found within,
	 * they will be automatically prepared.
	 *
	 * @param array $groups
	 * @param integer|null $displayStyleId The ID of the style the groups/properties are being edited in
	 *
	 * @return array
	 */
	public function prepareStylePropertyGroups(array $groups, $displayStyleId = null)
	{
		foreach ($groups AS &$group)
		{
			$group = $this->prepareStylePropertyGroup($group, $displayStyleId);
		}

		return $groups;
	}

	/**
	 * Gets the default style property group record.
	 *
	 * @param integer $styleId
	 *
	 * @return array
	 */
	public function getDefaultStylePropertyGroup($styleId)
	{
		return array(
			'group_name' => '',
			'group_style_id' => $styleId,
			'title' => '',
			'display_order' => 1,
			'sub_group' => '',
			'addon_id' => null,

			'masterTitle' => '',
			'masterDescription' => '',
		);
	}

	/**
	 * Gets the name of the style property group title phrase.
	 *
	 * @param array $group
	 *
	 * @return string
	 */
	public function getStylePropertyGroupTitlePhraseName(array $group)
	{
		switch ($group['group_style_id'])
		{
			case -1: $suffix = 'admin'; break;
			case 0:  $suffix = 'master'; break;
			default: return '';
		}

		return "style_property_group_$group[group_name]_$suffix";
	}

	/**
	 * Gets the name of the style property group description phrase.
	 *
	 * @param array $group
	 *
	 * @return string
	 */
	public function getStylePropertyGroupDescriptionPhraseName(array $group)
	{
		switch ($group['group_style_id'])
		{
			case -1: $suffix = 'admin'; break;
			case 0:  $suffix = 'master'; break;
			default: return '';
		}

		return "style_property_group_$group[group_name]_{$suffix}_desc";
	}

	/**
	 * Gets the parent path from the specified style. For real styles,
	 * this is the parent list. However, this function can handle styles
	 * 0 (master) and -1 (ACP).
	 *
	 * @param $styleId
	 * @return array Parent list; earlier positions are more specific
	 */
	public function getParentPathFromStyle($styleId)
	{
		switch (intval($styleId))
		{
			case 0: return array(0);
			case -1: return array(-1, 0);

			default:
				$style = $this->_getStyleModel()->getStyleById($styleId);
				if ($style)
				{
					return explode(',', $style['parent_list']);
				}
				else
				{
					return array();
				}
		}
	}

	/**
	 * Gets style info in the style property-specific way.
	 *
	 * @param integer $styleId
	 *
	 * @return array|false
	 */
	public function getStyle($styleId)
	{
		if ($styleId >= 0)
		{
			return $this->getModelFromCache('XenForo_Model_Style')->getStyleById($styleId, true);
		}
		else
		{
			return array(
				'style_id' => -1,
				'parent_list' => '-1,0',
				'title' => new XenForo_Phrase('admin_control_panel')
			);
		}
	}

	/**
	 * Group a list of style properties by the style they belong to.
	 * This uses the customization style (not definition style) for grouping.
	 *
	 * @param array $properties
	 *
	 * @return array Format: [style id][definition id] => info
	 */
	public function groupStylePropertiesByStyle(array $properties)
	{
		$newProperties = array();
		foreach ($properties AS $property)
		{
			$newProperties[$property['style_id']][$property['property_definition_id']] = $property;
		}

		return $newProperties;
	}

	/**
	 * Rebuilds the property cache for all styles.
	 */
	public function rebuildPropertyCacheForAllStyles()
	{
		$this->rebuildPropertyCacheInStyleAndChildren(0);
	}

	/**
	 * Rebuild the style property cache in the specified style and all
	 * child/dependent styles.
	 *
	 * @param integer $styleId
	 *
	 * @return array The property cache for the requested style
	 */
	public function rebuildPropertyCacheInStyleAndChildren($styleId)
	{
		if ($styleId == -1)
		{
			$rebuildStyleIds = array(-1);
			$dataStyleIds = array(-1, 0);
			$styles = array();
		}
		else
		{
			$styleModel = $this->_getStyleModel();

			$styles = $styleModel->getAllStyles();
			$styleTree = $styleModel->getStyleTreeAssociations($styles);

			$rebuildStyleIds = $styleModel->getAllChildStyleIdsFromTree($styleId, $styleTree);
			$rebuildStyleIds[] = $styleId;

			$dataStyleIds = array_keys($styles);
			$dataStyleIds[] = 0;

			if ($styleId == 0)
			{
				$rebuildStyleIds[] = -1;
				$dataStyleIds[] = -1;
			}
		}

		$properties = $this->groupStylePropertiesByStyle(
			$this->getStylePropertiesInStyles($dataStyleIds)
		);

		$styleOutput = false;

		foreach ($rebuildStyleIds AS $rebuildStyleId)
		{
			$sourceStyle = (isset($styles[$rebuildStyleId]) ? $styles[$rebuildStyleId] : array());

			switch ($rebuildStyleId)
			{
				case 0:
					continue 2;

				case -1:
					$sourceStyleIds = array(-1, 0);
					break;

				default:
					$sourceStyleIds = explode(',', $sourceStyle['parent_list']);
			}

			$styleProperties = array();
			foreach ($sourceStyleIds AS $sourceStyleId)
			{
				if (isset($properties[$sourceStyleId]))
				{
					$styleProperties = array_merge($styleProperties, $properties[$sourceStyleId]);
				}
			}

			$effectiveProperties = $this->getEffectiveStylePropertiesInStyle(
				$rebuildStyleId, $sourceStyleIds, $styleProperties
			);

			$cacheOutput = $this->updatePropertyCacheInStyle($rebuildStyleId, $effectiveProperties, $sourceStyle);
			if ($rebuildStyleId == $styleId)
			{
				$styleOutput = $cacheOutput;
			}
		}

		return $styleOutput;
	}

	/**
	 * Updates the property cache in the specified style.
	 *
	 * @param integer $styleId
	 * @param array $effectiveProperties List of effective properties in style.
	 * @param array|null $style Style information; queried if needed
	 *
	 * @return array|false Compiled property cache
	 */
	public function updatePropertyCacheInStyle($styleId, array $effectiveProperties, array $style = null)
	{
		if ($styleId == 0)
		{
			return false;
		}

		$propertyCache = array();
		foreach ($effectiveProperties AS $property)
		{
			if ($property['property_type'] == 'scalar')
			{
				$propertyCache[$property['property_name']] = $property['property_value'];
			}
			else
			{
				$propertyCache[$property['property_name']] = unserialize($property['property_value']);
			}
		}
		foreach ($propertyCache AS &$propertyValue)
		{
			if (is_array($propertyValue))
			{
				$propertyValue = $this->compileCssPropertyForCache($propertyValue, $propertyCache);
			}
			else
			{
				$propertyValue = $this->compileScalarPropertyForCache($propertyValue, $propertyCache);
			}
		}

		if ($styleId == -1)
		{
			$this->_getDataRegistryModel()->set('adminStyleModifiedDate', XenForo_Application::$time);
			$this->_getDataRegistryModel()->set('adminStyleProperties', $propertyCache);
		}
		else if ($styleId > 0)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Style');
			if ($style)
			{
				$dw->setExistingData($style, true);
			}
			else
			{
				$dw->setExistingData($styleId);
			}
			$dw->set('properties', $propertyCache);
			$dw->save();
		}

		return $propertyCache;
	}

	/**
	 * Compiles a CSS property from it's user-input-based version to the property cache version.
	 *
	 * @param array $original Original, input-based CSS rule
	 * @param array $properties A list of all properties, for resolving variable style references
	 *
	 * @return array CSS rule for cache
	 */
	public function compileCssPropertyForCache(array $original, array $properties = array())
	{
		$output = $original;
		$output = $this->compileCssProperty_sanitize($output, $original);

		foreach ($output AS &$outputValue)
		{
			$outputValue = $this->replaceVariablesInStylePropertyValue($outputValue, $properties);
		}

		$output = $this->compileCssProperty_compileRules($output, $original);
		$output = $this->compileCssProperty_cleanUp($output, $original);

		return $output;
	}

	/**
	 * Sanitizes the values in the CSS property output array.
	 *
	 * @param array $output Output CSS property
	 * @param array $original Original format of CSS property
	 *
	 * @return array Updated output CSS property
	 */
	public function compileCssProperty_sanitize(array $output, array $original)
	{
		// remove empty properties so isset can be used (0 is a valid value in many places)
		foreach ($output AS $key => &$value)
		{
			if (is_array($value))
			{
				if (count($value) == 0)
				{
					unset($output[$key]);
				}
			}
			else if (trim($value) === '')
			{
				unset($output[$key]);
			}
			else if ($value !== '0' && strval(intval($value)) === $value)
			{
				// not 0 and looks like an int, add "px" unit
				$value = $value . 'px';
			}
		}

		// translate array-based text decoration to css style
		if (!empty($output['text-decoration']) && is_array($output['text-decoration']))
		{
			if (isset($output['text-decoration']['none']))
			{
				$output['text-decoration'] = 'none';
			}
			else
			{
				$output['text-decoration'] = implode(' ', $output['text-decoration']);
			}
		}

		return $output;
	}

	/**
	 * Compiles all the rules of a CSS property.
	 *
	 * @param array $output Output CSS property
	 * @param array $original Original format of CSS property
	 *
	 * @return array Updated output CSS property
	 */
	public function compileCssProperty_compileRules(array $output, array $original)
	{
		// handle the font short cut (includes all text-related rules)
		if (false && isset($output['font-size'], $output['font-family']))
		{
			// font shortcut now disabled on account of the line-height issue
			$output['font'] = 'font: ' . $this->_getCssValue(
				$output, array('font-style', 'font-variant', 'font-weight', 'font-size', 'font-family')
			) . ';';
		}
		else
		{
			$output['font'] = $this->_getCssValueRule(
				$output, array('font-style', 'font-variant', 'font-weight', 'font-size', 'font-family')
			);
		}

		$output['font'] .= "\n" . $this->_getCssValueRule(
			$output, array('color', 'text-decoration')
		);

		// background shortcut
		if (isset($output['background-image']) && $output['background-image'] != 'none')
		{
			$output['background-image'] = trim($output['background-image']);
			if (!preg_match('#^url\s*\(#', $output['background-image']))
			{
				$output['background-image'] = preg_replace('/^("|\')(.*)\\1$/', '\\2', $output['background-image']);
				$output['background-image'] = 'url(\'' . $output['background-image'] . '\')';
			}
		}

		if (!empty($output['background-none']))
		{
			$output['background'] = 'background: none;';
			$output['background-color'] = 'none';
			$output['background-image'] = 'none';
		}
		else if ( // force the background shortcut if a color + image is specified, OR if color = rgba
			isset($output['background-color'], $output['background-image'])
			||
			(isset($output['background-color']) && substr(strtolower($output['background-color']), 0, 4) == 'rgba')
		)
		{
			$output['background'] = 'background: ' . $this->_getCssValue(
				$output, array('background-color', 'background-image', 'background-repeat', 'background-position')
			) . ';';
		}
		else
		{
			$output['background'] = $this->_getCssValueRule($output,
				array('background-color', 'background-image', 'background-repeat', 'background-position')
			);
		}

		// padding, margin shortcuts
		$this->_getPaddingMarginShortCuts('padding', $output);
		$this->_getPaddingMarginShortCuts('margin', $output);

		// border shortcut
		if (isset($output['border-width'], $output['border-style'], $output['border-color']))
		{
			$output['border'] = 'border: ' . $this->_getCssValue(
				$output, array('border-width', 'border-style', 'border-color')
			) . ';';
		}
		else
		{
			$output['border'] = $this->_getCssValueRule(
				$output, array('border-width', 'border-style', 'border-color')
			);
		}

		foreach (array('top', 'right', 'bottom', 'left') AS $borderSide)
		{
			$borderSideName = "border-$borderSide";

			if (isset($output["$borderSideName-width"], $output["$borderSideName-style"], $output["$borderSideName-color"]))
			{
				$borderSideCss = $borderSideName . ': ' . $this->_getCssValue(
					$output, array("$borderSideName-width", "$borderSideName-style", "$borderSideName-color")
				) . ';';
			}
			else
			{
				$borderSideCss = $this->_getCssValueRule(
					$output, array("$borderSideName-width", "$borderSideName-style", "$borderSideName-color")
				);
			}

			if ($borderSideCss)
			{
				$output['border'] .= "\n" . $borderSideCss;
			}
		}

		// border radius shortcut, ties into border
		if (isset($output['border-radius']))
		{
			$output['border'] .= "\nborder-radius: " . $output['border-radius'] . ';';
		}

		foreach (array('top-left', 'top-right', 'bottom-right', 'bottom-left') AS $radiusCorner)
		{
			$radiusCornerName = "border-$radiusCorner-radius";
			if (isset($output[$radiusCornerName]))
			{
				$output['border'] .= "\n$radiusCornerName: " . $output[$radiusCornerName] . ';';
			}
		}

		return $output;
	}

	protected function _getPaddingMarginShortCuts($type, array &$output)
	{
		$test = $output;

		// push all the values into the test array for purposes of determining how to build the short cut
		if (isset($output[$type . '-all']))
		{
			foreach (array('top', 'left', 'bottom', 'right') AS $side)
			{
				if (!isset($output["{$type}-{$side}"]))
				{
					$test["{$type}-{$side}"] = $output[$type . '-all'];
				}
			}
		}

		if (isset($test[$type . '-top'], $test[$type . '-right'], $test[$type . '-bottom'], $test[$type . '-left']))
		{
			if ($test[$type . '-top'] == $test[$type . '-right']
				&& $test[$type . '-top'] == $test[$type . '-bottom']
				&& $test[$type . '-top'] == $test[$type . '-left'])
			{
				$output[$type] = $type . ': ' . $test[$type . '-top'] . ';';
			}
			else if ($test[$type . '-top'] == $test[$type . '-bottom'] && $test[$type . '-right'] == $test[$type . '-left'])
			{
				$output[$type] = $type . ': ' . $this->_getCssValue(
					$test, array($type . '-top', $type . '-right')
				) . ';';
			}
			else if ($test[$type . '-right'] == $test[$type . '-left'])
			{
				$output[$type] = $type . ': ' . $this->_getCssValue(
					$test, array($type . '-top', $type . '-right', $type . '-bottom')
				) . ';';
			}
			else
			{
				$output[$type] = $type . ': ' . $this->_getCssValue(
					$test, array($type . '-top', $type . '-right', $type . '-bottom', $type . '-left')
				) . ';';
			}
		}
		else
		{
			$output[$type] = $this->_getCssValueRule(
				$test, array($type . '-top', $type . '-right', $type . '-bottom', $type . '-left')
			);
		}
	}

	/**
	 * Cleans up the CSS property output after compilation.
	 *
	 * @param array $output Output CSS property
	 * @param array $original Original format of CSS property
	 *
	 * @return array Updated output CSS property
	 */
	public function compileCssProperty_cleanUp(array $output, array $original)
	{
		foreach ($output AS $key => &$value)
		{
			if (preg_match('/^(
				background-(none|image|position|repeat)
				|font-(variant|weight|style)
				|text-decoration
				|border-style
				|border-(left|right|top|bottom)-style
			)/x', $key))
			{
				unset($output[$key]);
				continue;
			}

			$value = trim($value);
			if ($value === '')
			{
				unset($output[$key]);
				continue;
			}
		}

		return $output;
	}

	/**
	 * Compiles a scalar property value for the cache.
	 *
	 * @param string $original Original property value
	 * @param array $properties A list of all properties, for resolving variable style references
	 *
	 * @return string
	 */
	public function compileScalarPropertyForCache($original, array $properties = array())
	{
		return $this->replaceVariablesInStylePropertyValue($original, $properties);
	}

	/**
	 * Replaces variable references in a style property value.
	 *
	 * @param string $value Property value. This is an individual string value.
	 * @param array $properties List of properites to read from.
	 *
	 * @return string
	 */
	public function replaceVariablesInStylePropertyValue($value, array $properties, array $seenProperties = array())
	{
		if (!$properties)
		{
			return $value;
		}

		$outputValue = $this->convertAtPropertiesToTemplateSyntax($value, $properties);

		$varProperties = array();

		preg_match_all('#\{xen:property\s+("|\'|)([a-z0-9_-]+)(\.([a-z0-9_-]+))?\\1\s*\}#i', $outputValue, $matches, PREG_SET_ORDER);
		foreach ($matches AS $match)
		{
			$varProperties[$match[0]] = (isset($match[4]) ? array($match[2], $match[4]) : array($match[2]));
		}

		foreach ($varProperties AS $matchSearch => $match)
		{
			$matchReplace = '';

			$matchName = implode('.', $match);
			if (!isset($seenProperties[$matchName]))
			{
				$matchProperty = $match[0];
				if (isset($match[1]))
				{
					$matchSubProperty = $match[1];
					if (isset($properties[$matchProperty][$matchSubProperty]) && is_array($properties[$matchProperty]))
					{
						$matchReplace = $properties[$matchProperty][$matchSubProperty];
					}
				}
				else if (isset($properties[$matchProperty]) && !is_array($properties[$matchProperty]))
				{
					$matchReplace = $properties[$matchProperty];
				}
			}

			if ($matchReplace)
			{
				$newSeenProperties = $seenProperties;
				$newSeenProperties[$matchName] = true;

				$matchReplace = $this->replaceVariablesInStylePropertyValue($matchReplace, $properties, $newSeenProperties);
			}

			$outputValue = str_replace($matchSearch, $matchReplace, $outputValue);
		}

		return $outputValue;
	}

	/**
	 * Helper for CSS property cache compilation. Gets the value(s) for one or more
	 * CSS rule keys. Multiple keys will be separated by a space.
	 *
	 * @param array $search Array to search for keys in
	 * @param string|array $key One or more keys to search for
	 *
	 * @return string Values for matching keys; space separated
	 */
	protected function _getCssValue(array $search, $key)
	{
		if (is_array($key))
		{
			$parts = array();
			foreach ($key AS $searchKey)
			{
				if (isset($search[$searchKey]))
				{
					$parts[] = $search[$searchKey];
				}
			}
			return implode(' ', $parts);
		}
		else
		{
			return (isset($search[$key]) ? $search[$key] : '');
		}
	}

	/**
	 * Helper for CSS property cache compilation. Gets the full rule(s) for one or more
	 * CSS rule keys.
	 *
	 * @param array $search Array to search for keys in
	 * @param string|array $key One or more keys to search for
	 *
	 * @return string Full CSS rules
	 */
	protected function _getCssValueRule(array $search, $key)
	{
		if (is_array($key))
		{
			$parts = array();
			foreach ($key AS $searchKey)
			{
				if (isset($search[$searchKey]))
				{
					$parts[] = "$searchKey: " . $search[$searchKey] . ";";
				}
			}
			return implode("\n", $parts);
		}
		else if (isset($search[$key]))
		{
			return "$key: " . $search[$key] . ";";
		}
	}

	/**
	 * Updates the specified style property value.
	 *
	 * @param array $definition Style property definition
	 * @param integer $styleId Style property is being changed in
	 * @param mixed $newValue New value (string for scalar; array for css)
	 * @param boolean $extraOptions Extra options to pass to the data writer
	 * @param mixed $existingProperty If array/false, considered to be the property to be updated; otherwise, determined automatically
	 * @param string $existingValue The existing value in the place of this property. This may
	 * 		come from the parent style (unlike $existingProperty). This prevents customization from
	 * 		occurring when a value isn't changed.
	 *
	 * @param string Returns the property value as it looks going into the DB
	 */
	public function updateStylePropertyValue(array $definition, $styleId, $newValue,
		array $extraOptions = array(), $existingProperty = null, $existingValue = null
	)
	{
		$styleId = intval($styleId);

		if ($existingProperty !== false && !is_array($existingProperty))
		{
			$existingProperty = $this->getStylePropertyByDefinitionAndStyle(
				$definition['property_definition_id'], $styleId
			);
		}

		if ($definition['property_type'] == 'scalar')
		{
			$newValue = strval($newValue);
		}
		else if (!is_array($newValue))
		{
			$newValue = array();
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_StyleProperty');
		$dw->setOption(XenForo_DataWriter_StyleProperty::OPTION_VALUE_FORMAT, $definition['property_type']);
		$dw->setOption(XenForo_DataWriter_StyleProperty::OPTION_VALUE_COMPONENTS, unserialize($definition['css_components']));
		$dw->setOption(XenForo_DataWriter_StyleProperty::OPTION_REBUILD_CACHE, true);
		foreach ($extraOptions AS $option => $optionValue)
		{
			$dw->setOption($option, $optionValue);
		}
		$dw->setExtraData(XenForo_DataWriter_StyleProperty::DATA_DEFINITION, $definition);

		if ($existingProperty)
		{
			$dw->setExistingData($existingProperty, true);
		}
		else
		{
			$dw->set('property_definition_id', $definition['property_definition_id']);
			$dw->set('style_id', $styleId);
		}

		$dw->set('property_value', $newValue);
		$dw->preSave();

		if ($dw->get('property_value') === $existingValue)
		{
			return $dw->get('property_value');
		}

		$dw->save();

		return $dw->get('property_value');
	}

	/**
	 * Saves a set of style property changes from the input format.
	 *
	 * @param integer $styleId Style to change properties in
	 * @param array $properties Properties from input; keyed by definition ID
	 * @param array $reset List of properties to reset if customized; keyed by definition ID
	 */
	public function saveStylePropertiesInStyleFromInput($styleId, array $properties, array $reset = array())
	{
		$existingProperties = $this->getEffectiveStylePropertiesInStyle($styleId);

		XenForo_Db::beginTransaction($this->_getDb());

		foreach ($properties AS $definitionId => $propertyValue)
		{
			if (!isset($existingProperties[$definitionId]))
			{
				continue;
			}

			$propertyDefinition = $existingProperties[$definitionId];
			if ($propertyDefinition['style_id'] == $styleId)
			{
				$existingProperty = $propertyDefinition;
				if (!empty($reset[$definitionId]) && $propertyDefinition['definition_style_id'] != $styleId)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_StyleProperty');
					$dw->setOption(XenForo_DataWriter_StyleProperty::OPTION_REBUILD_CACHE, false);
					$dw->setExtraData(XenForo_DataWriter_StyleProperty::DATA_DEFINITION, $propertyDefinition);
					$dw->setExistingData($existingProperty, true);
					$dw->delete();
					continue;
				}
			}
			else
			{
				$existingProperty = false;
			}

			$this->updateStylePropertyValue(
				$propertyDefinition, $styleId, $propertyValue,
				array(XenForo_DataWriter_StyleProperty::OPTION_REBUILD_CACHE => false),
				$existingProperty, $propertyDefinition['property_value'] // this is the effective value
			);
		}

		$this->rebuildPropertyCacheInStyleAndChildren($styleId);

		XenForo_Db::commit($this->_getDb());
	}

	/**
	 * Get the specified style property definition by ID. Includes default
	 * property value.
	 *
	 * @param integer $propertyDefinitionId
	 *
	 * @return array|false
	 */
	public function getStylePropertyDefinitionById($propertyDefinitionId)
	{
		return $this->_getDb()->fetchRow('
			SELECT property_definition.*,
				property.property_value
			FROM xf_style_property_definition AS property_definition
			LEFT JOIN xf_style_property AS property ON
				(property.property_definition_id = property_definition.property_definition_id
				AND property.style_id = property_definition.definition_style_id)
			WHERE property_definition.property_definition_id = ?
		', $propertyDefinitionId);
	}

	/**
	 * Gets the specified style property definition by its name and definition
	 * style ID. Includes default property value.
	 *
	 * @param string $name
	 * @param integer $styleId
	 *
	 * @return array|false
	 */
	public function getStylePropertyDefinitionByNameAndStyle($name, $styleId)
	{
		return $this->_getDb()->fetchRow('
			SELECT property_definition.*,
				property.property_value
			FROM xf_style_property_definition AS property_definition
			LEFT JOIN xf_style_property AS property ON
				(property.property_definition_id = property_definition.property_definition_id
				AND property.style_id = property_definition.definition_style_id)
			WHERE property_definition.property_name = ?
				AND property_definition.definition_style_id = ?
		', array($name, $styleId));
	}

	/**
	 * Get the specified style property definitions by their IDs.
	 * Includes default property value.
	 *
	 * @param array $propertyDefinitionIds
	 *
	 * @return array Format: [property definition id] => info
	 */
	public function getStylePropertyDefinitionsByIds(array $propertyDefinitionIds)
	{
		if (!$propertyDefinitionIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT property_definition.*,
				property.property_value
			FROM xf_style_property_definition AS property_definition
			LEFT JOIN xf_style_property AS property ON
				(property.property_definition_id = property_definition.property_definition_id
				AND property.style_id = property_definition.definition_style_id)
			WHERE property_definition.property_definition_id IN (' . $this->_getDb()->quote($propertyDefinitionIds) . ')
		', 'property_definition_id');
	}

	/**
	 * Returns an array of all style property definitions in the specified group
	 *
	 * @param string $groupId
	 * @param integer|null $styleId If specified, limits to definitions in a specified style
	 *
	 * @return array
	 */
	public function getStylePropertyDefinitionsByGroup($groupName, $styleId = null)
	{
		$properties = $this->fetchAllKeyed('
			SELECT *
			FROM xf_style_property_definition
			WHERE group_name = ?
				' . ($styleId !== null ? 'AND definition_style_id = ' . $this->_getDb()->quote($styleId) : '') . '
			ORDER BY display_order
		', 'property_name', $groupName);

		return $properties;
	}

	/**
	 * Create of update a style property definition. Input data
	 * is named after fields in the style property definition, as well as
	 * property_value_scalar and property_value_css.
	 *
	 * @param integer $definitionId Definition to update; if 0, creates a new one
	 * @param array $input List of data from input to change in definition
	 *
	 * @return array Definition info after saving
	 */
	public function createOrUpdateStylePropertyDefinition($definitionId, array $input)
	{
		XenForo_Db::beginTransaction($this->_getDb());

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_StylePropertyDefinition');
		if ($definitionId)
		{
			$dw->setExistingData($definitionId);
		}
		else
		{
			$dw->set('definition_style_id', $input['definition_style_id']);
		}
		$dw->set('group_name', $input['group_name']);
		$dw->set('property_name', $input['property_name']);
		$dw->set('title', $input['title']);
		$dw->set('description', $input['description']);
		$dw->set('property_type', $input['property_type']);
		$dw->set('css_components', $input['css_components']);
		$dw->set('scalar_type', $input['scalar_type']);
		$dw->set('scalar_parameters', $input['scalar_parameters']);
		$dw->set('display_order', $input['display_order']);
		$dw->set('sub_group', $input['sub_group']);
		$dw->set('addon_id', $input['addon_id']);
		$dw->save();

		$definition = $dw->getMergedData();
		if ($input['property_type'] == 'scalar')
		{
			$propertyValue = $input['property_value_scalar'];

			$newPropertyValue = $this->updateStylePropertyValue(
				$definition, $definition['definition_style_id'], $propertyValue
			);
		}
		else
		{
			$propertyValue = $input['property_value_css'];
			if ($definitionId && !$dw->isChanged('property_type'))
			{
				// TODO: update value when possible
			}
			else
			{
				$newPropertyValue = $this->updateStylePropertyValue(
					$definition, $definition['definition_style_id'], array()
				);
			}
		}

		XenForo_Db::commit($this->_getDb());

		return $definition;
	}

	/**
	 * Prepares a style property (or definition) for display.
	 *
	 * @param array $property
	 * @param integer|null $displayStyleId The ID of the style the properties are being edited in
	 *
	 * @return array Prepared version
	 */
	public function prepareStyleProperty(array $property, $displayStyleId = null)
	{
		$property['cssComponents'] = unserialize($property['css_components']);

		if ($property['property_type'] == 'scalar')
		{
			$property['propertyValueScalar'] = $property['property_value'];
			$property['propertyValueCss'] = array();
		}
		else
		{
			$property['propertyValueScalar'] = '';
			$property['propertyValueCss'] = unserialize($property['property_value']);
		}

		$property['masterTitle'] = $property['title'];
		if ($property['addon_id'])
		{
			$property['title'] = new XenForo_Phrase($this->getStylePropertyTitlePhraseName($property));
		}

		$property['masterDescription'] = $property['description'];
		if ($property['addon_id'])
		{
			$property['description'] = new XenForo_Phrase($this->getStylePropertyDescriptionPhraseName($property));
		}

		if ($displayStyleId === null)
		{
			$property['canEditDefinition'] = $this->canEditStylePropertyDefinition($property['definition_style_id']);
		}
		else
		{
			$property['canEditDefinition'] = (
				$this->canEditStylePropertyDefinition($property['definition_style_id'])
				&& $property['definition_style_id'] == $displayStyleId
			);
		}

		$property['canReset'] = (
			isset($property['effectiveState'])
			&& $property['effectiveState'] == 'customized'
		);

		return $property;
	}

	/**
	 * Prepares a list of style properties.
	 *
	 * @param array $properties
	 * @param integer|null $displayStyleId The ID of the style the properties are being edited in
	 *
	 * @return array
	 */
	public function prepareStyleProperties(array $properties, $displayStyleId = null)
	{
		foreach ($properties AS &$property)
		{
			$property = $this->prepareStyleProperty($property, $displayStyleId);
		}

		return $properties;
	}

	/**
	 * Gets the name of the style property title phrase.
	 *
	 * @param array $property
	 *
	 * @return string
	 */
	public function getStylePropertyTitlePhraseName(array $property)
	{
		switch ($property['definition_style_id'])
		{
			case -1: $suffix = 'admin'; break;
			case 0:  $suffix = 'master'; break;
			default: return '';
		}

		return "style_property_$property[property_name]_$suffix";
	}

	/**
	 * Gets the name of the style property description phrase.
	 *
	 * @param array $property
	 *
	 * @return string
	 */
	public function getStylePropertyDescriptionPhraseName(array $property)
	{
		switch ($property['definition_style_id'])
		{
			case -1: $suffix = 'admin'; break;
			case 0:  $suffix = 'master'; break;
			default: return '';
		}

		return "style_property_$property[property_name]_description_$suffix";
	}

	/**
	 * Gets the default style property definition.
	 *
	 * @param integer $styleId
	 * @param string $groupName
	 *
	 * @return array
	 */
	public function getDefaultStylePropertyDefinition($styleId, $groupName = '')
	{
		$components = array(
			'text' => true,
			'background' => true,
			'border' => true,
			'layout' => true,
			'extra' => true
		);

		return array(
			'definition_style_id' => $styleId,
			'group_name' => $groupName,
			'title' => '',
			'property_name' => '',
			'property_type' => 'css',
			'property_value' => '',
			'css_components' => serialize($components),
			'display_order' => 1,
			'sub_group' => '',
			'addon_id' => null,

			'cssComponents' => $components,
			'propertyValueScalar' => '',
			'propertyValueCss' => array(),
			'masterTitle' => ''
		);
	}

	/**
	 * Determines if a style property definition can be edited in
	 * the specified style.
	 *
	 * @param integer $styleId
	 *
	 * @return boolean
	 */
	public function canEditStylePropertyDefinition($styleId)
	{
		return XenForo_Application::debugMode();
	}

	/**
	 * Determines if a style property can be edited in the specified style.
	 *
	 * @param integer $styleId
	 *
	 * @return boolean
	 */
	public function canEditStyleProperty($styleId)
	{
		if ($styleId > 0)
		{
			return true;
		}
		else
		{
			return XenForo_Application::debugMode();
		}
	}

	/**
	 * Get the style property development directory based on the style a property/definition
	 * is defined in. Returns an empty string for non-master properties.
	 *
	 * @param integer $styleId
	 *
	 * @return string
	 */
	public function getStylePropertyDevelopmentDirectory($styleId)
	{
		if ($styleId > 0)
		{
			return '';
		}

		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		if ($styleId == 0)
		{
			return XenForo_Application::getInstance()->getRootDir()
				. '/' . $config->development->directory . '/file_output/style_properties';
		}
		else
		{
			return XenForo_Application::getInstance()->getRootDir()
				. '/' . $config->development->directory . '/file_output/admin_style_properties';
		}
	}

	/**
	 * Gets the full path to a specific style property development file.
	 * Ensures directory is writable.
	 *
	 * @param string $propertyName
	 * @param integer $styleId
	 *
	 * @return string
	 */
	public function getStylePropertyDevelopmentFileName($propertyName, $styleId)
	{
		$dir = $this->getStylePropertyDevelopmentDirectory($styleId);
		if (!$dir)
		{
			throw new XenForo_Exception('Tried to write non-master/admin style property value to development directory, or debug mode is not enabled');
		}
		if (!is_dir($dir) || !is_writable($dir))
		{
			throw new XenForo_Exception("Style property development directory $dir is not writable");
		}

		return ($dir . '/' . $propertyName . '.xml');
	}

	public function writeAllStylePropertyDevelopmentFiles()
	{
		$definitions = $this->getDefaultStylePropertyDefinition(0);

		Zend_Debug::dump($definitions);
	}

	/**
	 * Writes out a style property development file.
	 *
	 * @param array $definition Property definition
	 * @param array $property Property value
	 */
	public function writeStylePropertyDevelopmentFile(array $definition, array $property)
	{
		if ($definition['addon_id'] != 'XenForo' && $property['style_id'] > 0)
		{
			return;
		}

		$fileName = $this->getStylePropertyDevelopmentFileName($definition['property_name'], $property['style_id']);

		// TODO: in the future, the writing system could be split into writing out definition and values in separate functions.
		// 		it would make a clearer code path.

		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$node = $document->createElement('property');
		$document->appendChild($node);

		$includeDefinition = ($definition['definition_style_id'] == $property['style_id']);
		if ($includeDefinition)
		{
			$node->setAttribute('definition', 1);
			$node->setAttribute('group_name', $definition['group_name']);
			$node->setAttribute('property_type', $definition['property_type']);
			$node->setAttribute('scalar_type', $definition['scalar_type']);
			$node->setAttribute('scalar_parameters', $definition['scalar_parameters']);

			$components = unserialize($definition['css_components']);
			$node->setAttribute('css_components', implode(',', array_keys($components)));

			$node->setAttribute('display_order', $definition['display_order']);
			$node->setAttribute('sub_group', $definition['sub_group']);

			XenForo_Helper_DevelopmentXml::createDomElements($node, array(
				'title' => isset($definition['masterTitle']) ? $definition['masterTitle'] : strval($definition['title']),
				'description' => isset($definition['masterDescription']) ? $definition['masterDescription'] : strval($definition['description'])
			));
		}
		else
		{
			$node->setAttribute('property_type', $definition['property_type']);
		}

		$valueNode = $document->createElement('value');
		if ($definition['property_type'] == 'scalar')
		{
			$valueNode->appendChild($document->createCDATASection($property['property_value']));
		}
		else
		{
			$jsonValue = json_encode(unserialize($property['property_value']));
			// format one value per line
			$jsonValue = preg_replace('/(?<!:|\\\\)","/', '",' . "\n" . '"', $jsonValue);

			$valueNode->appendChild($document->createCDATASection($jsonValue));
		}
		$node->appendChild($valueNode);

		$document->save($fileName);
	}

	/**
	 * Moves a style property development file, for renames.
	 *
	 * @param array $oldDefinition
	 * @param array $newDefinition
	 */
	public function moveStylePropertyDevelopmentFile(array $oldDefinition, array $newDefinition)
	{
		if ($oldDefinition['addon_id'] != 'XenForo' || $oldDefinition['definition_style_id'] > 0)
		{
			return;
		}

		if ($newDefinition['definition_style_id'] > 0)
		{
			$this->deleteStylePropertyDevelopmentFile($oldDefinition['property_name'], $oldDefinition['definition_style_id']);
			return;
		}

		$oldFile = $this->getStylePropertyDevelopmentFileName($oldDefinition['property_name'], $oldDefinition['definition_style_id']);
		$newFile = $this->getStylePropertyDevelopmentFileName($newDefinition['property_name'], $newDefinition['definition_style_id']);

		if (file_exists($oldFile))
		{
			rename($oldFile, $newFile);
		}
	}

	/**
	 * Updates the definition-related elements of the style property development file
	 * without touching the property value.
	 *
	 * @param array $definition
	 */
	public function updateStylePropertyDevelopmentFile(array $definition)
	{
		if ($definition['addon_id'] != 'XenForo' || $definition['definition_style_id'] > 0)
		{
			return;
		}

		$fileName = $this->getStylePropertyDevelopmentFileName($definition['property_name'], $definition['definition_style_id']);

		if (file_exists($fileName))
		{
			$document = new SimpleXMLElement($fileName, 0, true);

			if ((string)$document['definition'])
			{
				$value = (string)$document->value;
				if ((string)$document['property_type'] == 'css')
				{
					$value = serialize(json_decode($value, true));
				}

				$property = $definition + array(
					'style_id' => $definition['definition_style_id'],
					'property_value' => $value
				);

				$this->writeStylePropertyDevelopmentFile($definition, $property);
			}
		}
	}

	/**
	 * Deletes a style property development file.
	 *
	 * @param string $name
	 * @param integer $styleId Definition style ID.
	 */
	public function deleteStylePropertyDevelopmentFile($name, $styleId)
	{
		if ($styleId > 0)
		{
			return;
		}

		$fileName = $this->getStylePropertyDevelopmentFileName($name, $styleId);
		if (file_exists($fileName))
		{
			unlink($fileName);
		}
	}

	/**
	 * Deletes the style property file if needed. This is used when reverting a
	 * customized property; the file is only deleted if we're deleting the property
	 * from a style other than the one it was created in.
	 *
	 * @param array $definition
	 * @param array $property
	 */
	public function deleteStylePropertyDevelopmentFileIfNeeded(array $definition, array $property)
	{
		if ($property['style_id'] > 0)
		{
			return;
		}
		if ($property['style_id'] == $definition['definition_style_id'])
		{
			// assume this will be deleted by the definition
			return;
		}

		$this->deleteStylePropertyDevelopmentFile($definition['property_name'], $property['style_id']);
	}

	/**
	 * Imports style properties/groups from the development location. This only imports
	 * one style's worth of properties at a time.
	 *
	 * @param integer $styleId Style to import for
	 */
	public function importStylePropertiesFromDevelopment($styleId)
	{
		if ($styleId > 0)
		{
			return;
		}

		$dir = $this->getStylePropertyDevelopmentDirectory($styleId);
		if (!$dir)
		{
			return;
		}

		if (!is_dir($dir))
		{
			throw new XenForo_Exception("Style property development directory doesn't exist");
		}

		$files = glob("$dir/*.xml");

		$newProperties = array();
		$newGroups = array();
		foreach ($files AS $fileName)
		{
			$name = basename($fileName, '.xml');
			$document = new SimpleXMLElement($fileName, 0, true);

			if (substr($name, 0, 6) == 'group.')
			{
				$newGroups[] = array(
					'group_name'    => substr($name, 6),
					'title'         => (string)$document->title,
					'description'   => (string)$document->description,
					'display_order' => (string)$document['display_order']
				);
			}
			else
			{
				if ((string)$document['definition'])
				{
					$property = array(
						'title'             => (string)$document->title,
						'description'       => (string)$document->description,
						'definition'        => (string)$document['definition'],
						'group_name'        => (string)$document['group_name'],
						'property_type'     => (string)$document['property_type'],
						'scalar_type'       => (string)$document['scalar_type'],
						'scalar_parameters' => (string)$document['scalar_parameters'],
						'display_order'     => (string)$document['display_order'],
						'sub_group'         => (string)$document['sub_group']
					);

					$components = (string)$document['css_components'];
					if ($components)
					{
						$property['css_components'] = array_fill_keys(explode(',', $components), true);
					}
					else
					{
						$property['css_components'] = array();
					}
				}
				else
				{
					$property = array(
						'property_type' => (string)$document['property_type']
					);
				}

				$property['property_name'] = $name;

				if ($property['property_type'] == 'scalar')
				{
					$property['property_value'] = (string)$document->value;
				}
				else
				{
					$property['property_value'] = json_decode((string)$document->value, true);
				}

				$newProperties[] = $property;
			}
		}

		$this->importStylePropertiesFromArray($newProperties, $newGroups, $styleId, 'XenForo');
	}

	/**
	 * Deletes the style properties and definitions in a style (and possibly limited to an add-on).
	 *
	 * @param integer $styleId Style to delete from. 0 for master, -1 for admin
	 * @param string|null $addOnId If not null, limits deletions to an ad-on
	 * @param boolean $leaveChildCustomizations If true, child customizations of a deleted definition will be left
	 */
	public function deleteStylePropertiesAndDefinitionsInStyle($styleId, $addOnId = null, $leaveChildCustomizations = false)
	{
		$properties = $this->getStylePropertiesInStyles(array($styleId));

		$delPropertyIds = array();
		$delPropertyDefinitionIds = array();
		foreach ($properties AS $property)
		{
			if ($addOnId === null || $property['addon_id'] == $addOnId)
			{
				if ($property['definition_style_id'] == $styleId)
				{
					$delPropertyDefinitionIds[] = $property['property_definition_id'];
				}
				else if ($property['style_id'] == $styleId)
				{
					$delPropertyIds[] = $property['property_id'];
				}
			}
		}

		if ($delPropertyIds)
		{
			$this->_db->delete('xf_style_property',
				'property_id IN (' . $this->_db->quote($delPropertyIds) . ')'
			);
		}

		if ($delPropertyDefinitionIds)
		{
			$this->_db->delete('xf_style_property_definition',
				'property_definition_id IN (' . $this->_db->quote($delPropertyDefinitionIds) . ')'
			);

			if ($leaveChildCustomizations)
			{
				$this->_db->delete('xf_style_property',
					'property_definition_id IN (' . $this->_db->quote($delPropertyDefinitionIds) . ')
					AND style_id = ' . $this->_db->quote($styleId)
				);
			}
			else
			{
				$this->_db->delete('xf_style_property',
					'property_definition_id IN (' . $this->_db->quote($delPropertyDefinitionIds) . ')'
				);
			}
		}
	}

	/**
	 * Delete the style property groups in the specified style, matching the add-on if provided.
	 *
	 * @param integer $styleId Style to delete from. 0 for master, -1 for admin
	 * @param string|null $addOnId If not null, limits deletions to an ad-on
	 */
	public function deleteStylePropertyGroupsInStyle($styleId, $addOnId = null)
	{
		$db = $this->_getDb();
		if ($addOnId === null)
		{
			$db->delete('xf_style_property_group', 'group_style_id = ' . $db->quote($styleId));
		}
		else
		{
			$db->delete('xf_style_property_group',
				'group_style_id = ' . $db->quote($styleId) . ' AND addon_id = ' . $db->quote($addOnId)
			);
		}
	}

	/**
	 * Appends the style property list to an XML document.
	 *
	 * @param DOMElement $rootNode Node to append to
	 * @param integer $styleId Style to read values/definitions from
	 * @param string|null $addOnId If not null, limits to values/definitions in the specified add-on
	 */
	public function appendStylePropertyXml(DOMElement $rootNode, $styleId, $addOnId = null)
	{
		$document = $rootNode->ownerDocument;

		$properties = $this->getStylePropertiesInStyles(array($styleId));
		ksort($properties);

		foreach ($properties AS $property)
		{
			if ($addOnId === null || $property['addon_id'] == $addOnId)
			{
				$node = $document->createElement('property');
				$node->setAttribute('property_name', $property['property_name']);
				$node->setAttribute('property_type', $property['property_type']);

				if ($property['definition_style_id'] == $styleId)
				{
					$node->setAttribute('definition', 1);
					$node->setAttribute('group_name', $property['group_name']);
					$node->setAttribute('title', isset($property['masterTitle']) ? $property['masterTitle'] : strval($property['title']));
					$node->setAttribute('description', isset($property['masterDescription']) ? $property['masterDescription'] : strval($property['description']));

					$components = unserialize($property['css_components']);
					$node->setAttribute('css_components', implode(',', array_keys($components)));
					$node->setAttribute('scalar_type', $property['scalar_type']);
					$node->setAttribute('scalar_parameters', $property['scalar_parameters']);
					$node->setAttribute('display_order', $property['display_order']);
					$node->setAttribute('sub_group', $property['sub_group']);
				}

				if ($property['property_type'] == 'scalar')
				{
					$node->appendChild($document->createCDATASection($property['property_value']));
				}
				else
				{
					$node->appendChild($document->createCDATASection(json_encode(unserialize($property['property_value']))));
				}

				$rootNode->appendChild($node);
			}
		}

		$groups = $this->getStylePropertyGroupsInStyles(array($styleId));
		ksort($groups);
		foreach ($groups AS $group)
		{
			if ($addOnId === null || $group['addon_id'] == $addOnId)
			{
				$node = $document->createElement('group');
				$rootNode->appendChild($node);

				$node->setAttribute('group_name', $group['group_name']);
				$node->setAttribute('display_order', $group['display_order']);
				XenForo_Helper_DevelopmentXml::createDomElements($node, array(
					'title' => $group['title'],
					'description' => $group['description']
				));
			}
		}
	}

	/**
	 * Gets the style property development XML.
	 *
	 * @param integer $styleId
	 *
	 * @return DOMDocument
	 */
	public function getStylePropertyDevelopmentXml($styleId)
	{
		$rootTag = ($styleId == -1 ? 'admin_style_properties' : 'style_properties');

		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement($rootTag);
		$document->appendChild($rootNode);

		$this->appendStylePropertyXml($rootNode, $styleId, 'XenForo');

		return $document;
	}

	/**
	 * Imports the development admin navigation XML data.
	 *
	 * @param string $fileName File to read the XML from
	 */
	public function importStylePropertyDevelopmentXml($fileName, $styleId)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importStylePropertyXml($document, $styleId, 'XenForo');
	}

	/**
	 * Imports style properties and definitions from XML.
	 *
	 * @param SimpleXMLElement $xml XML node to search within
	 * @param integer $styleId Target style ID
	 * @param string|null $addOnId If not null, target add-on for definitions; if null, add-on is ''
	 */
	public function importStylePropertyXml(SimpleXMLElement $xml, $styleId, $addOnId = null)
	{
		if ($xml->property === null)
		{
			return;
		}

		$newProperties = array();
		foreach ($xml->property AS $xmlProperty)
		{
			$property = array(
				'property_name' => (string)$xmlProperty['property_name'],
				'group_name' => (string)$xmlProperty['group_name'],
				'title' => (string)$xmlProperty['title'],
				'description' => (string)$xmlProperty['description'],
				'definition' => (string)$xmlProperty['definition'],
				'property_type' => (string)$xmlProperty['property_type'],
				'scalar_type' => (string)$xmlProperty['scalar_type'],
				'scalar_parameters' => (string)$xmlProperty['scalar_parameters'],
				'display_order' => (string)$xmlProperty['display_order'],
				'sub_group' => (string)$xmlProperty['sub_group']
			);

			$components = (string)$xmlProperty['css_components'];
			if ($components)
			{
				$components = array_fill_keys(explode(',', $components), true);
				$property['css_components'] = $components;
			}
			else
			{
				$property['css_components'] = array();
			}

			if ($property['property_type'] == 'scalar')
			{
				$property['property_value'] = (string)$xmlProperty;
			}
			else
			{
				$property['property_value'] = json_decode((string)$xmlProperty, true);
			}

			$newProperties[] = $property;
		}

		$newGroups = array();
		foreach ($xml->group AS $xmlGroup)
		{
			$newGroups[] = array(
				'group_name' => (string)$xmlGroup['group_name'],
				'title' => (string)$xmlGroup->title,
				'description' => (string)$xmlGroup->description,
				'display_order' => (string)$xmlGroup['display_order']
			);
		}

		$this->importStylePropertiesFromArray($newProperties, $newGroups, $styleId, $addOnId);
	}

	/**
	 * Imports style properties and definitions from an array.
	 *
	 * @param array $newProperties List of properties and definitions to import
	 * @param array $newGroups List of groups to import
	 * @param integer $styleId Target style ID
	 * @param string|null $addOnId If not null, only replaces properties with this add-on; otherwise, all in style
	 */
	public function importStylePropertiesFromArray(array $newProperties, array $newGroups, $styleId, $addOnId = null)
	{
		// must be run before delete to keep values accessible
		$existingProperties = $this->keyPropertiesByName($this->getEffectiveStylePropertiesInStyle($styleId));

		$addOnIdString = ($addOnId !== null ? $addOnId : '');
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);
		$this->deleteStylePropertiesAndDefinitionsInStyle($styleId, $addOnId, true);
		$this->deleteStylePropertyGroupsInStyle($styleId, $addOnId);

		// run after the delete to not include removed data
		$existingGroups = $this->getEffectiveStylePropertyGroupsInStyle($styleId);

		foreach ($newGroups AS $group)
		{
			if (isset($existingGroups[$group['group_name']]))
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_StylePropertyGroup');
			$dw->setOption(XenForo_DataWriter_StylePropertyGroup::OPTION_UPDATE_MASTER_PHRASE, false);
			$dw->setOption(XenForo_DataWriter_StylePropertyGroup::OPTION_UPDATE_DEVELOPMENT, false);
			$dw->bulkSet(array(
				'group_name' => $group['group_name'],
				'group_style_id' => $styleId,
				'title' => $group['title'],
				'description' => $group['description'],
				'display_order' => $group['display_order'],
				'addon_id' => $addOnIdString
			));
			$dw->save();
		}

		foreach ($newProperties AS $property)
		{
			$propertyName = $property['property_name'];
			$propertyValue = $property['property_value'];

			$definition = null;
			$deletedDefinitionId = 0;
			$existingProperty = null;

			if (isset($existingProperties[$propertyName]))
			{
				$definition = $existingProperties[$propertyName];
				if ($definition['definition_style_id'] == $styleId && ($addOnId === null || $definition['addon_id'] == $addOnId))
				{
					$deletedDefinitionId = $definition['property_definition_id'];
					$definition = null;
				}
			}

			if (!empty($property['definition']))
			{
				if (!$definition)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_StylePropertyDefinition');
					$dw->setOption(XenForo_DataWriter_StylePropertyDefinition::OPTION_UPDATE_MASTER_PHRASE, false);
					$dw->setOption(XenForo_DataWriter_StylePropertyDefinition::OPTION_UPDATE_DEVELOPMENT, false);
					$dw->setOption(XenForo_DataWriter_StylePropertyDefinition::OPTION_CHECK_DUPLICATE, false);
					$dw->bulkSet(array(
						'property_name' => $propertyName,
						'group_name' => $property['group_name'],
						'title' => $property['title'],
						'description' => $property['description'],
						'definition_style_id' => $styleId,
						'property_type' => $property['property_type'],
						'css_components' => $property['css_components'],
						'scalar_type' => $property['scalar_type'],
						'scalar_parameters' => $property['scalar_parameters'],
						'display_order' => $property['display_order'],
						'sub_group' => $property['sub_group'],
						'addon_id' => $addOnIdString
					));
					$dw->save();

					$definition = $dw->getMergedData();

					if ($deletedDefinitionId)
					{
						$db->update('xf_style_property', array(
							'property_definition_id' => $definition['property_definition_id']
						), 'property_definition_id = ' . $db->quote($deletedDefinitionId));
					}

					$existingProperty = false;
				}
			}
			else if ($definition)
			{
				if ($definition['style_id'] == $styleId && $addOnId !== null && $definition['addon_id'] !== $addOnId)
				{
					$existingProperty = $definition;
				}
				else
				{
					$existingProperty = false;
				}
			}
			else
			{
				// non-definition and no matching definition
				continue;
			}

			$this->updateStylePropertyValue(
				$definition, $styleId, $propertyValue,
				array(
					XenForo_DataWriter_StyleProperty::OPTION_REBUILD_CACHE => false,
					XenForo_DataWriter_StyleProperty::OPTION_UPDATE_DEVELOPMENT => false
				),
				$existingProperty
			);
		}

		$this->rebuildPropertyCacheInStyleAndChildren($styleId);

		XenForo_Db::commit($db);
	}

	/**
	 * Replaces {xen:property} references in a template with the @ property version.
	 * This allows for easier editing and viewing of properties.
	 *
	 * @param string $templateText Template with {xen:property} references
	 * @param integer $editStyleId The style the template is being edited in
	 * @param array|null $properties A list of valid style properties; if null, grabbed automatically ([name] => property)
	 *
	 * @return string Replaced template text
	 */
	public function replacePropertiesInTemplateForEditor($templateText, $editStyleId, array $properties = null)
	{
		if ($properties === null)
		{
			$properties = $this->keyPropertiesByName($this->getEffectiveStylePropertiesInStyle($editStyleId));
		}

		$validComponents = array('font', 'background', 'padding', 'margin', 'border', 'extra');

		preg_match_all('#(?P<leading_space>[ \t]*)\{xen:property\s+("|\'|)(?P<property>[a-z0-9._-]+)\\2\s*\}#si', $templateText, $matches, PREG_SET_ORDER);
		foreach ($matches AS $match)
		{
			$propertyReference = $match['property'];
			$parts = explode('.', $propertyReference, 2);

			$propertyName = $parts[0];
			$propertyComponent = (count($parts) == 2 ? $parts[1] : false);

			if (!isset($properties[$propertyName]) || $properties[$propertyName]['property_type'] == 'scalar')
			{
				continue;
			}

			if ($propertyComponent && !in_array($propertyComponent, $validComponents))
			{
				continue;
			}

			$propertyValue = unserialize($properties[$propertyName]['property_value']);
			$outputValue = $propertyValue;

			$outputValue = $this->compileCssProperty_sanitize($outputValue, $propertyValue);
			$outputValue = $this->compileCssProperty_compileRules($outputValue, $propertyValue);
			$outputValue = $this->compileCssProperty_cleanUp($outputValue, $propertyValue);

			$replacementRules = '';
			if ($propertyComponent)
			{
				if (isset($outputValue[$propertyComponent]))
				{
					$replacementRules = $outputValue[$propertyComponent];
				}
			}
			else
			{
				foreach ($validComponents AS $validComponent)
				{
					if (isset($outputValue[$validComponent]))
					{
						$replacementRules .= "\n" . $outputValue[$validComponent];
					}
				}
				if (isset($outputValue['width']))
				{
					$replacementRules .= "\nwidth: $outputValue[width];";
				}
				if (isset($outputValue['height']))
				{
					$replacementRules .= "\nheight: $outputValue[height];";
				}
			}

			$leadingSpace = $match['leading_space'];

			$replacementRules = preg_replace('#(^|;(\r?\n)+)[ ]*([*a-z0-9_/\-]+):\s*#i', "\\1{$leadingSpace}\\3: ", trim($replacementRules));

			$replacement = $leadingSpace . '@property "' . $propertyReference . '";'
				. "\n" . $replacementRules
				. "\n" . $leadingSpace . '@property "/' . $propertyReference . '";';

			$templateText = str_replace($match[0], $replacement, $templateText);
		}

		preg_match_all('/\{xen:property\s+("|\'|)(?P<propertyName>[a-z0-9_]+)(?P<propertyComponent>\.[a-z0-9._-]+)?\\1\s*\}/si', $templateText, $matches, PREG_SET_ORDER);
		foreach ($matches AS $match)
		{
			if (!in_array(strtolower($match['propertyName']), XenForo_DataWriter_StylePropertyDefinition::$reservedNames))
			{
				$replacement = '@' . $match['propertyName'] . (empty($match['propertyComponent']) ? '' : $match['propertyComponent']);

				$templateText = str_replace($match[0], $replacement, $templateText);
			}
		}

		return $templateText;
	}

	protected static function _atToPropertyCallback($property)
	{
		$name = $property[1];

		if (!isset(self::$_tempProperties[$name]))
		{
			return $property[0];
		}

		if (!in_array(strtolower($name), XenForo_DataWriter_StylePropertyDefinition::$reservedNames))
		{
			return '{xen:property ' . $name . (empty($property[2]) ? '' : $property[2]) . '}';
		}

		return $property[0];
	}

	/**
	 * Converts @propertyName to {xen:property propertyName}
	 *
	 * @param string $text
	 * @param array $properties
	 *
	 * @return string
	 */
	public function convertAtPropertiesToTemplateSyntax($text, array $properties)
	{
		self::$_tempProperties = $properties;
		$text = preg_replace_callback(
			'/(?<=[^a-z0-9_]|^)@([a-z0-9_]+)(\.[a-z0-9._-]+)?/si',
			array('self', '_atToPropertyCallback'),
			$text
		);
		self::$_tempProperties = null;

		return $text;
	}

	/**
	 * Translates @ property style references from the template editor into a structured array,
	 * and rewrites the template text to the standard {xen:property} format.
	 *
	 * @param string $templateText Template with @ property references
	 * @param string $outputText By reference, the template with {xen:property} values instead
	 * @param array $properties A list of valid style properties in the correct style; keyed by named
	 *
	 * @return array Property values from the template text. Change detection still needs to be run.
	 */
	public function translateEditorPropertiesToArray($templateText, &$outputText, array $properties)
	{
		// replace @property 'foo'; .... @property '/foo'; with {xen:property foo}
		$outputText = $templateText;
		$outputProperties = array();

		preg_match_all('/
			@property\s+("|\')(?P<name>[a-z0-9._-]+)\\1;
			(?P<rules>([^@]*?|@(?!property))*)
			@property\s+("|\')\/(?P=name)\\5;
			/siUx', $templateText, $matches, PREG_SET_ORDER
		);
		foreach ($matches AS $match)
		{
			$parts = explode('.', $match['name'], 2);
			$propertyName = $parts[0];
			$propertyComponent = (count($parts) == 2 ? $parts[1] : false);
			if ($propertyComponent == 'font')
			{
				$propertyComponent = 'text';
			}
			else if ($propertyComponent == 'margin' || $propertyComponent == 'padding')
			{
				$propertyComponent = 'layout';
			}

			if (!isset($properties[$propertyName]) || $properties[$propertyName]['property_type'] != 'css')
			{
				continue;
			}

			$validComponents = unserialize($properties[$propertyName]['css_components']);
			if ($propertyComponent && !isset($validComponents[$propertyComponent]))
			{
				continue;
			}

			$set = array(
				'name' => $propertyName,
				'component' => $propertyComponent,
				'rules' => array()
			);
			$extra = array();
			$nonPropertyRules = array();
			$paddingValues = array();
			$marginValues = array();

			$comments = array();
			preg_match_all('#/\*(.+)(\*/|$)#siU', $match['rules'], $commentMatches, PREG_SET_ORDER);
			foreach ($commentMatches AS $commentMatch)
			{
				$comments[] = $commentMatch[1];
				$match['rules'] = str_replace($commentMatch[0], '', $match['rules']);
			}

			preg_match_all('/
				(?<=^|\s|;)(?P<name>[a-z0-9-_*]+)
				\s*:\s*
				(?P<value>[^;]*)
				(;|$)
				/siUx', $match['rules'], $ruleMatches, PREG_SET_ORDER
			);
			foreach ($ruleMatches AS $ruleMatch)
			{
				$value = trim($ruleMatch['value']);
				if ($value === '')
				{
					continue;
				}

				$name = strtolower($ruleMatch['name']);

				switch ($name)
				{
					case 'color':
					case 'text-decoration':
						$group = 'text';
						break;

					case 'width':
					case 'height':
						$group = 'layout';
						break;

					default:
						$regex = '/^('
							. 'font|font-(family|size|style|variant|weight)'
							. '|background|background-(color|image|position|repeat)'
							. '|padding|padding-.*|margin|margin-.*'
							. '|border|border-.*-radius|border(-(top|right|bottom|left))?(-(color|style|width|radius))?'
							. ')$/';
						if (preg_match($regex, $name, $nameMatch))
						{
							$ruleParts = explode('-', $nameMatch[1], 2);
							$group = $ruleParts[0];
						}
						else
						{
							$group = 'extra';
						}
				}

				// css references font, but the css components list references text
				if ($group == 'font')
				{
					$group = 'text';
				}
				else if ($group == 'padding' || $group == 'margin')
				{
					$group = 'layout';
				}

				if (($propertyComponent && $group != $propertyComponent) || !isset($validComponents[$group]))
				{
					if (isset($validComponents['extra']))
					{
						$extra[$name] = $value;
					}
					else
					{
						$nonPropertyRules[] = $ruleMatch[0];
					}
				}
				else
				{
					$isValidRule = false;

					if ($group == 'extra')
					{
						$isValidRule = true;
						$extra[$name] = $value;
					}
					else if ($value == 'inherit')
					{
						$isValidRule = false; // can't put inherit rules in properties
						$nonPropertyRules[] = $ruleMatch[0];
					}
					else
					{
						$isValidRule = false;

						switch ($name)
						{
							case 'font':
								$ruleOutput = $this->parseFontCss($value);
								if (is_array($ruleOutput))
								{
									$isValidRule = true;
									$set['rules'] = array_merge($set['rules'], $ruleOutput);
								}
								break;

							case 'text-decoration':
								$isValidRule = true;

								if ($value == 'none')
								{
									$set['rules']['text-decoration'] = array('none' => 'none');
								}
								else
								{
									$decorations = preg_split('/\s+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
									$set['rules']['text-decoration'] = array_combine($decorations, $decorations);
								}
								break;

							case 'background':
								$ruleOutput = $this->parseBackgroundCss($value);
								if (is_array($ruleOutput))
								{
									$isValidRule = true;
									$set['rules'] = array_merge($set['rules'], $ruleOutput);
								}
								break;

							case 'background-image':
								$isValidRule = true;

								if (preg_match('/^url\(("|\'|)([^)]+)\\1\)$/iU', $value, $imageMatch))
								{
									$set['rules']['background-image'] = $imageMatch[2];
								}
								else
								{
									$set['rules']['background-image'] = $value;
								}
								break;

							case 'padding':
								if ($this->parsePaddingMarginCss($value, $paddingValues))
								{
									$isValidRule = true;
								}
								break;

							case 'padding-top':
							case 'padding-right':
							case 'padding-bottom':
							case 'padding-left':
								$paddingValues[substr($name, 8)] = $value;
								unset($paddingValues['all']);
								$isValidRule = true;
								break;

							case 'margin':
								if ($this->parsePaddingMarginCss($value, $marginValues))
								{
									$isValidRule = true;
								}
								break;

							case 'margin-top':
							case 'margin-right':
							case 'margin-bottom':
							case 'margin-left':
								$marginValues[substr($name, 7)] = $value;
								unset($marginValues['all']);
								$isValidRule = true;
								break;

							case 'border':
							case 'border-top':
							case 'border-right':
							case 'border-bottom':
							case 'border-left':
								$ruleOutput = $this->parseBorderCss($value, $name);
								if (is_array($ruleOutput))
								{
									$isValidRule = true;
									$set['rules'] = array_merge($set['rules'], $ruleOutput);
								}
								break;

							default:
								$isValidRule = true;
								$set['rules'][$name] = $value;
						}

						if (!$isValidRule)
						{
							$nonPropertyRules[] = "-xenforo-nomatch-" . $ruleMatch[0];
						}
					}
				}
			}

			if ($paddingValues)
			{
				if (isset($paddingValues['all']))
				{
					$set['rules']['padding-all'] = $paddingValues['all'];
				}
				else
				{
					foreach (array('top', 'right', 'bottom', 'left') AS $paddingSide)
					{
						if (isset($paddingValues[$paddingSide]))
						{
							$set['rules']["padding-$paddingSide"] = $paddingValues[$paddingSide];
						}
					}
				}
			}
			if ($marginValues)
			{
				if (isset($marginValues['all']))
				{
					$set['rules']['margin-all'] = $marginValues['all'];
				}
				else
				{
					foreach (array('top', 'right', 'bottom', 'left') AS $marginSide)
					{
						if (isset($marginValues[$marginSide]))
						{
							$set['rules']["margin-$marginSide"] = $marginValues[$marginSide];
						}
					}
				}
			}

			if ($extra || $comments)
			{
				$set['rules']['extra'] = '';

				if ($extra)
				{
					foreach ($extra AS $extraRule => $extraValue)
					{
						$set['rules']['extra'] .= "\n$extraRule: $extraValue;";
					}
				}
				if ($comments)
				{
					foreach ($comments AS $comment)
					{
						$set['rules']['extra'] .= "\n/*$comment*/";
					}
				}

				$set['rules']['extra'] = trim($set['rules']['extra']);
			}

			$outputProperties[] = $set;

			$replacement = '{xen:property ' . $match['name'] . '}';
			foreach ($nonPropertyRules AS $nonPropertyRule)
			{
				$replacement .= "\n\t$nonPropertyRule";
			}

			$outputText = str_replace($match[0], $replacement, $outputText);
		}

		$outputText = $this->convertAtPropertiesToTemplateSyntax($outputText, $properties);

		return $outputProperties;
	}

	/**
	 * Parses font shortcut CSS.
	 *
	 * @param string $value
	 *
	 * @return array|false List of property rules to apply or false if shortcut could not be parsed
	 */
	public function parseFontCss($value)
	{
		preg_match('/
			^
			((?P<font_style>italic|oblique|normal)\s+)?
			((?P<font_variant>small-caps|normal)\s+)?
			((?P<font_weight>bold(?:er)?|lighter|[1-9]00|normal)\s+)?
			(?P<font_size>
				xx-small|x-small|small|medium|large|x-large|xx-large|smaller|larger
				|0|-?\d+(\.\d+)?(%|[a-z]+)
				|\{xen:property\s+("|\'|)([a-z0-9._-]+)("|\'|)\s*\}
				|@[a-z0-9._-]+
			)
			\s+
			(?P<font_family>\S.*)
			$
			/siUx', $value, $fontMatch
		);
		if (!$fontMatch)
		{
			return false;
		}

		$output = array();
		if (!empty($fontMatch['font_style']) && strtolower($fontMatch['font_style']) != 'normal')
		{
			$output['font-style'] = 'italic';
		}
		else
		{
			$output['font-style'] = '';
		}

		if (!empty($fontMatch['font_variant']) && strtolower($fontMatch['font_variant']) != 'normal')
		{
			$output['font-variant'] = 'small-caps';
		}
		else
		{
			$output['font-variant'] = '';
		}

		if (!empty($fontMatch['font_weight']) && strtolower($fontMatch['font_weight']) != 'normal')
		{
			$output['font-weight'] = 'bold';
		}
		else
		{
			$output['font-weight'] = '';
		}

		$output['font-size'] = $fontMatch['font_size'];
		$output['font-family'] = $fontMatch['font_family'];

		return $output;
	}

	/**
	 * Parses background shortcut CSS.
	 *
	 * @param string $value
	 *
	 * @return array|false List of property rules to apply or false if shortcut could not be parsed
	 */
	public function parseBackgroundCss($value)
	{
		if (strtolower($value) == 'none')
		{
			return array(
				'background-none' => '1',
				'background-color' => '',
				'background-image' => '',
				'background-repeat' => '',
				'background-position' => ''
			);
		}

		$output = array();

		do
		{
			if (preg_match('/^(repeat-x|repeat-y|no-repeat|repeat)/i', $value, $match))
			{
				if (isset($output['background-repeat']))
				{
					return false;
				}
				$output['background-repeat'] = $match[0];
			}
			else if (preg_match('/^(none|url\(("|\'|)(?P<background_image_url>[^)]+)\\2\))/i', $value, $match))
			{
				if (isset($output['background-image']))
				{
					return false;
				}

				if ($match[0] == 'none')
				{
					$output['background-image'] = 'none';
				}
				else
				{
					$output['background-image'] = $match['background_image_url'];
				}
			}
			else if (preg_match('/^(
					(
						(left|center|right|0|-?\d+(\.\d+)?(%|[a-z]+))
						(
							\s+(top|center|bottom|0|-?\d+(\.\d+)?(%|[a-z]+))
						)?
					)|top|center|bottom
				)/ix', $value, $match))
			{
				if (isset($output['background-position']))
				{
					return false;
				}
				$output['background-position'] = $match[0];
			}
			else if (preg_match('/^(
					rgb\(\s*\d+%?\s*,\s*\d+%?\s*,\s*\d+%?\s*\)
					|rgba\(\s*\d+%?\s*,\s*\d+%?\s*,\s*\d+%?\s*,\s*[0-9.]+\s*\)
					|\#[a-f0-9]{6}|\#[a-f0-9]{3}
					|[a-z]+
				)/ix', $value, $match)
			)
			{
				if (isset($output['background-color']))
				{
					return false;
				}
				$output['background-color'] = $match[0];
			}
			else if (preg_match('/^(
					(\{xen:property\s+("|\'|)([a-z0-9._-]+)("|\'|)\s*\})
					|@[a-z0-9._-]+
				)/ix', $value, $match))
			{
				$handled = false;
				foreach (array('background-color', 'background-image', 'background-position') AS $ruleName)
				{
					if (!isset($output[$ruleName]))
					{
						$output[$ruleName] = $match[0];
						$handled = true;
						break;
					}
				}
				if (!$handled)
				{
					return false;
				}
			}
			else
			{
				return false;
			}

			$value = strval(substr($value, strlen($match[0])));

			if (preg_match('/^(\s+|$)/', $value))
			{
				$value = ltrim($value);
			}
			else
			{
				return false;
			}
		}
		while ($value !== '');

		if (!$output)
		{
			return false;
		}

		return array_merge(
			array(
				'background-color' => '',
				'background-image' => '',
				'background-repeat' => '',
				'background-position' => ''
			),
			$output
		);

	}

	/**
	 * Parses padding/margin shortcut CSS.
	 *
	 * @param string $value
	 * @param array $values By reference. Pushes out the effective padding/margin values to later be pulled together.
	 *
	 * @return boolean
	 */
	public function parsePaddingMarginCss($value, array &$values)
	{
		$value = preg_replace('#\{xen:property\s+("|\'|)([a-z0-9_-]+(\.[a-z0-9_-]+)?)\\1\s*\}#i', '@\\2', $value);

		$paddingParts = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
		if (count($paddingParts) > 4)
		{
			return false;
		}

		foreach ($paddingParts AS $paddingPart)
		{
			if ($paddingPart[0] !== '@' && !preg_match('#^0|auto|-?\d+(\.\d+)?(%|[a-z]+)$#i', $paddingPart))
			{
				return false;
			}
		}

		switch (count($paddingParts))
		{
			case 1:
				$values = array(
					'all' => $paddingParts[0],
					'top' => $paddingParts[0],
					'right' => $paddingParts[0],
					'bottom' => $paddingParts[0],
					'left' => $paddingParts[0],
				);
				break;

			case 2:
				$values = array(
					'top' => $paddingParts[0],
					'right' => $paddingParts[1],
					'bottom' => $paddingParts[0],
					'left' => $paddingParts[1],
				);
				break;

			case 3:
				$values = array(
					'top' => $paddingParts[0],
					'right' => $paddingParts[1],
					'bottom' => $paddingParts[2],
					'left' => $paddingParts[1],
				);
				break;

			case 4:
				$values = array(
					'top' => $paddingParts[0],
					'right' => $paddingParts[1],
					'bottom' => $paddingParts[2],
					'left' => $paddingParts[3],
				);
				break;
		}

		return true;
	}

	/**
	 * Parses border shortcut CSS.
	 *
	 * @param string $value
	 * @param string $name The name of the shortcut (border, border-top, etc)
	 *
	 * @return array|false List of property rules to apply or false if shortcut could not be parsed
	 */
	public function parseBorderCss($value, $name)
	{
		$output = array();

		do
		{
			if (preg_match('/^(thin|medium|thick|0|-?\d+(\.\d+)?[a-z]+)/i', $value, $match))
			{
				if (isset($output["$name-width"]))
				{
					return false;
				}
				$output["$name-width"] = $match[0];
			}
			else if (preg_match('/^(none|hidden|dashed|dotted|double|groove|inset|outset|ridge|solid)/i', $value, $match))
			{
				if (isset($output["$name-style"]))
				{
					return false;
				}
				$output["$name-style"] = $match[0];
			}
			else if (preg_match('/^(
					rgb\(\s*\d+%?\s*,\s*\d+%?\s*,\s*\d+%?\s*\)
					|rgba\(\s*\d+%?\s*,\s*\d+%?\s*,\s*\d+%?\s*,\s*[0-9.]+\s*\)
					|\#[a-f0-9]{6}|\#[a-f0-9]{3}
					|[a-z]+
				)/ix', $value, $match)
			)
			{
				if (isset($output["$name-color"]))
				{
					return false;
				}
				$output["$name-color"] = $match[0];
			}
			else if (preg_match('/^(
					(\{xen:property\s+("|\'|)([a-z0-9._-]+)\\2\s*\})
					|@[a-z0-9._-]+
				)/ix', $value, $match))
			{
				$handled = false;
				foreach (array("$name-width", "$name-color") AS $ruleName)
				{
					if (!isset($output[$ruleName]))
					{
						$output[$ruleName] = $match[0];
						$handled = true;
						break;
					}
				}
				if (!$handled)
				{
					return false;
				}
			}
			else
			{
				return false;
			}

			$value = strval(substr($value, strlen($match[0])));

			if (preg_match('/^(\s+|$)/', $value))
			{
				$value = ltrim($value);
			}
			else
			{
				return false;
			}
		}
		while ($value !== '');

		if (!$output)
		{
			return false;
		}

		return array_merge(
			array(
				"$name-width" => '1px',
				"$name-style" => 'none',
				"$name-color" => 'black'
			),
			$output
		);
	}

	/**
	 * Saves style properties in the specified style based on the @ property
	 * references that have been parsed out of the template(s).
	 *
	 * @param integer $styleId Style to save properties into
	 * @param array $updates List of property data to update (return from translateEditorPropertiesToArray)
	 * @param array $properties List of style properties available in this style. Keyed by name!
	 */
	public function saveStylePropertiesInStyleFromTemplate($styleId, array $updates, array $properties)
	{
		$input = array();

		foreach ($updates AS $update)
		{
			if (!isset($properties[$update['name']]))
			{
				continue;
			}

			$property = $properties[$update['name']];

			$definitionId = $property['property_definition_id'];

			if ($update['component'])
			{
				if (isset($input[$definitionId]))
				{
					$base = $input[$definitionId];
				}
				else
				{
					$base = unserialize($property['property_value']);
				}

				$input[$definitionId] = array_merge($base, $update['rules']);
			}
			else
			{
				$input[$definitionId] = $update['rules'];
			}
		}

		if ($input)
		{
			$this->saveStylePropertiesInStyleFromInput($styleId, $input);
		}
	}

	/**
	 * Updates the group_name of all style property definitions in group $sourceGroup to $destinationGroup.
	 *
	 * @param string $sourceGroup
	 * @param string $destinationGroup
	 */
	public function moveStylePropertiesBetweenGroups($sourceGroup, $destinationGroup)
	{
		XenForo_Db::beginTransaction($this->_getDb());

		foreach ($this->getStylePropertyDefinitionsByGroup($sourceGroup, 0) AS $property)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_StylePropertyDefinition', XenForo_DataWriter::ERROR_EXCEPTION);
			$dw->setExistingData($property['property_definition_id']);
			$dw->set('group_name', $destinationGroup);
			$dw->save();
		}

		XenForo_Db::commit($this->_getDb());
	}

	/**
	 * @return XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->getModelFromCache('XenForo_Model_Style');
	}
}