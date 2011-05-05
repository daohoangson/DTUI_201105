<?php

/**
* Class to handle compiling template tag calls for "listitem" in admin areas.
*
* @package XenForo_Template
*/
class XenForo_Template_Compiler_Tag_Admin_ListItem extends XenForo_Template_Compiler_Tag_Admin_Abstract implements XenForo_Template_Compiler_Tag_Interface
{
	/**
	* Compile the specified tag and return PHP code to handle it.
	*
	* @param XenForo_Template_Compiler The invoking compiler
	* @param string                 Name of the tag called
	* @param array                  Attributes for the tag (may be empty)
	* @param array                  Nodes (tags/curlies/text) within this tag (may be empty)
	* @param array                  Compilation options
	*
	* @return string
	*/
	public function compile(XenForo_Template_Compiler $compiler, $tag, array $attributes, array $children, array $options)
	{
		$data = $attributes;
		$html = null;
		$popups = array();
		$links = array();
		$tempVars = array();

		$statement = $compiler->getNewRawStatement();

		foreach ($children AS $child)
		{
			if ($compiler->isSegmentNamedTag($child, 'label'))
			{
				$data['label'] = $child['children'];
			}
			else if ($compiler->isSegmentNamedTag($child, 'snippet'))
			{
				$data['snippet'] = $child['children'];
			}
			else if ($compiler->isSegmentNamedTag($child, 'html'))
			{
				$html = $child['children'];
			}
			else if ($compiler->isSegmentNamedTag($child, 'popup'))
			{
				$tempVar = $compiler->getUniqueVar();

				$oldOutputVar = $compiler->getOutputVar();
				$compiler->setOutputVar($tempVar);

				$popupStatement = XenForo_Template_Compiler_Tag_Admin_Popup::compilePopup(
					$compiler, $child['attributes'], $child['children'], $options, 'div', 'Left'
				);

				$statement->addStatement(
					$compiler->getOutputVarInitializer()
					. $popupStatement->getFullStatements($tempVar)
				);

				$popups[] = '$' . $tempVar;
				$tempVars[] = '$' . $tempVar;

				$compiler->setOutputVar($oldOutputVar);
			}
			else if ($compiler->isSegmentNamedTag($child, 'beforelabel'))
			{
				$data['beforelabel'] = $child['children'];
			}
			/*else if ($compiler->isSegmentNamedTag($child, 'link'))
			{
				$tempVar = $compiler->getUniqueVar();

				$oldOutputVar = $compiler->getOutputVar();
				$compiler->setOutputVar($tempVar);

				$linkStatement = XenForo_Template_Compiler_Tag_Admin_ListItemLink::compileLink(
					$compiler, $child['attributes'], $child['children'], $options
				);

				$statement->addStatement(
					$compiler->getOutputVarInitializer()
					. $linkStatement->getFullStatements($tempVar)
				);

				$links[] = '$' . $tempVar;
				$tempVars[] = '$' . $tempVar;

				$compiler->setOutputVar($oldOutputVar);
			}*/
		}

		if (!isset($data['label']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('list_items_must_specify_label'), $child);
		}

		if (!isset($data['id']))
		{
			throw $compiler->getNewCompilerException(new XenForo_Phrase('list_items_must_specify_an_id'), $child);
		}

		$compiledData = $compiler->compileNamedParams($data, $options);

		if ($html)
		{
			$htmlCode = $compiler->compileIntoVariable($html, $htmlOutputVar, $options);
			$statement->addStatement($htmlCode);
			$compiledData['html'] = '$' . $htmlOutputVar;

			$tempVars[] = '$' . $htmlOutputVar;
		}

		$controlData = $compiler->buildNamedParamCode($compiledData);

		if ($popups)
		{
			$popupData = $compiler->buildNamedParamCode($popups);
		}
		else
		{
			$popupData = 'array()';
		}

		if ($links)
		{
			$linkData = $compiler->buildNamedParamCode($links);
		}
		else
		{
			$linkData = 'array()';
		}

		$statement->addStatement(
			'$' . $compiler->getOutputVar() . ' .= XenForo_Template_Helper_Admin::listItem(' . $controlData . ', ' . $popupData . ', ' . $linkData . ");\n"
		);
		if ($tempVars)
		{
			$statement->addStatement('unset(' . implode(', ', $tempVars) . ");\n");
		}

		return $statement;
	}
}