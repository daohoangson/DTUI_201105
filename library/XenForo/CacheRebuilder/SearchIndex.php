<?php

/**
 * Cache rebuilder for the search index.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_SearchIndex extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('search_index');
	}

	/**
	 * Shows the exit link.
	 */
	public function showExitLink()
	{
		return true;
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$inputHandler = new XenForo_Input($options);
		$input = $inputHandler->filter(array(
			'batch' => XenForo_Input::UINT,
			'start' => XenForo_Input::UINT,
			'extra_data' => XenForo_Input::ARRAY_SIMPLE,
			'delay' => XenForo_Input::UNUM
		));

		if ($input['delay'] >= 0.01)
		{
			usleep($input['delay'] * 1000000);
		}

		/* @var $searchModel XenForo_Model_Search */
		$searchModel = XenForo_Model::create('XenForo_Model_Search');
		$searchContentTypes = $searchModel->getSearchContentTypes();

		// TODO: potentially look at truncating the table (user option?)

		$extraData = $input['extra_data'];
		if (!isset($extraData['content_types']) || !is_array($extraData['content_types']))
		{
			$extraData['content_types'] = array_keys($searchContentTypes);
		}
		if (empty($extraData['current_type']))
		{
			$extraData['current_type'] = array_shift($extraData['content_types']);
		}
		if (empty($extraData['type_start']))
		{
			$extraData['type_start'] = 0;
		}

		$originalExtraData = $extraData;

		while (!isset($searchContentTypes[$extraData['current_type']]))
		{
			if (!$extraData['content_types'])
			{
				return true;
			}

			$extraData['current_type'] = array_shift($extraData['content_types']);
		}

		$searchHandler = $searchContentTypes[$extraData['current_type']];
		$dataHandler = XenForo_Search_DataHandler_Abstract::create($searchHandler);
		$indexer = new XenForo_Search_Indexer();
		$indexer->setIsRebuild(true);

		$nextStart = $dataHandler->rebuildIndex($indexer, $extraData['type_start'], $input['batch']);

		$indexer->finalizeRebuildSet();

		if ($nextStart === false)
		{
			// move on to next type
			$extraData['current_type'] = '';
			$extraData['type_start'] = 0;
		}
		else
		{
			$extraData['type_start'] = $nextStart;
		}

		$options = array(
			'batch' => $input['batch'],
			'start' => $input['start'] + 1,
			'extra_data' => $extraData,
			'delay' => $input['delay']
		);

		$detailedMessage = "($originalExtraData[current_type] " . XenForo_Locale::numberFormat($originalExtraData['type_start']) . ")";

		return 1;
	}
}