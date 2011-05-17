<?php

/**
 * Admin controller for handling actions on phrases.
 *
 * @package XenForo_Phrases
 */
class XenForo_ControllerAdmin_Phrase extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('language');
	}

	/**
	 * Phrase index. This is a list of phrases, so redirect this to a
	 * language-specific list.
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionIndex()
	{
		$languageId = XenForo_Helper_Cookie::getCookie('edit_language_id');
		if ($languageId === false)
		{
			$languageId = (XenForo_Application::debugMode()
				? 0
				: XenForo_Application::get('options')->defaultLanguageId
			);
		}

		if (!XenForo_Application::debugMode() && !$languageId)
		{
			$languageId = XenForo_Application::get('options')->defaultLanguageId;
		}

		$language = $this->_getLanguageModel()->getLanguageById($languageId, true);
		if (!$language || !$this->_getPhraseModel()->canModifyPhraseInLanguage($languageId))
		{
			$language = $this->_getLanguageModel()->getLanguageById(XenForo_Application::get('options')->defaultLanguageId);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildAdminLink('languages/phrases', $language)
		);
	}

	/**
	 * Helper to get the phrase add/edit form controller response.
	 *
	 * @param array $phrase
	 * @param integer $inputLanguageId The language the phrase is being edited/created in
	 * @param integer $inputPhraseId The ID of the current phrase, or the phrase from which the value is inherited
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getPhraseAddEditResponse(array $phrase, $inputLanguageId, $inputPhraseId = 0)
	{
		$phraseModel = $this->_getPhraseModel();
		$languageModel = $this->_getLanguageModel();
		$addOnModel = $this->_getAddOnModel();

		if ($phrase['language_id'] != $inputLanguageId)
		{
			// actually adding a "copy" of this phrase in this language
			$phrase['phrase_id'] = 0;
			$phrase['language_id'] = $inputLanguageId;
			$phrase['global_cache'] = 0;
		}

		if (!$phraseModel->canModifyPhraseInLanguage($phrase['language_id']))
		{
			return $this->responseError(new XenForo_Phrase('phrases_in_this_language_can_not_be_modified'));
		}

		if ($phrase['language_id'] == 0)
		{
			$addOnOptions = $addOnModel->getAddOnOptionsListIfAvailable();
			$addOnSelected = (isset($phrase['addon_id']) ? $phrase['addon_id'] : $addOnModel->getDefaultAddOnId());
		}
		else
		{
			$addOnOptions = array();
			$addOnSelected = (isset($phrase['addon_id']) ? $phrase['addon_id'] : '');
		}

		if ($phrase['language_id'] > 0 && !empty($phrase['title']))
		{
			$masterValue = $phraseModel->getMasterPhraseValue($phrase['title']);
		}
		else
		{
			$masterValue = false;
		}

		$viewParams = array(
			'phrase' => $phrase,
			'masterValue' => $masterValue,
			'language' => $languageModel->getLanguageById($phrase['language_id'], true),
			'showGlobalCacheOption' => ($phrase['language_id'] == 0),
			'addOnOptions' => $addOnOptions,
			'addOnSelected' => $addOnSelected,
			'listItemId' => ($phrase['phrase_id'] ? $phrase['phrase_id'] : $inputPhraseId),
		);

		return $this->responseView('XenForo_ViewAdmin_Phrase_Edit', 'phrase_edit', $viewParams);
	}

	/**
	 * Form to add a phrase to the specified language. If not in debug mode,
	 * users are prevented from adding a phrase to the master language.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);

		$phrase = array(
			'phrase_id' => 0,
			'language_id' => $languageId
		);

		return $this->_getPhraseAddEditResponse($phrase, $languageId);
	}

	/**
	 * Form to edit a specified phrase. A language_id input must be specified. If the language ID
	 * of the requested phrase and the language ID of the input differ, the request is
	 * treated as adding a customized version of the requested phrase in the input
	 * language.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$input = $this->_input->filter(array(
			'phrase_id' => XenForo_Input::UINT,
			'language_id' => XenForo_Input::UINT
		));

		$phrase = $this->_getPhraseOrError($input['phrase_id']);

		if (!$this->_input->inRequest('language_id'))
		{
			// default to editing in the specified lang
			$input['language_id'] = $phrase['language_id'];
		}

		return $this->_getPhraseAddEditResponse($phrase, $input['language_id'], $input['phrase_id']);
	}

	/**
	 * Saves a phrase. This may either be an insert or an update.
	 *
	 * @return XenForo_ControllerResponse
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$data = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'phrase_text' => array(XenForo_Input::STRING, 'noTrim' => true),
			'language_id' => XenForo_Input::UINT,
			'global_cache' => XenForo_INPUT::UINT,
			'addon_id' => XenForo_Input::STRING
		));

		if (!$this->_getPhraseModel()->canModifyPhraseInLanguage($data['language_id']))
		{
			return $this->responseError(new XenForo_Phrase('this_phrase_can_not_be_modified'));
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
		if ($phraseId = $this->_input->filterSingle('phrase_id', XenForo_Input::UINT))
		{
			$writer->setExistingData($phraseId);
		}

		$writer->bulkSet($data);

		if ($writer->isChanged('title') || $writer->isChanged('phrase_text') || $writer->get('language_id') > 0)
		{
			$writer->updateVersionId();
		}

		$writer->save();

		if ($this->_input->filterSingle('reload', XenForo_Input::STRING))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('phrases/edit', $writer->getMergedData(), array('language_id' => $writer->get('language_id')))
			);
		}
		else
		{
			$language = $this->_getLanguageModel()->getLanguageById($writer->get('language_id'), true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('languages/phrases', $language) . $this->getLastHash($writer->get('phrase_id'))
			);
		}
	}

	/**
	 * Deletes a phrase.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$phraseId = $this->_input->filterSingle('phrase_id', XenForo_Input::UINT);

		if ($this->isConfirmedPost())
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
			$writer->setExistingData($phraseId);
			if (!$this->_getPhraseModel()->canModifyPhraseInLanguage($writer->get('language_id')))
			{
				return $this->responseError(new XenForo_Phrase('this_phrase_can_not_be_modified'));
			}

			$writer->delete();

			$language = $this->_getLanguageModel()->getLanguageById($writer->get('language_id'), true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('languages/phrases', $language)
			);
		}
		else // show confirmation dialog
		{
			$phrase = $this->_getPhraseOrError($phraseId);

			$viewParams = array(
				'phrase' => $phrase,
				'language' => $this->_getLanguageModel()->getLanguageById($phrase['language_id']),
			);

			return $this->responseView('XenForo_ViewAdmin_Phrase_Delete', 'phrase_delete', $viewParams);
		}
	}

	/**
	 * Phrase searching.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearch()
	{
		$languageModel = $this->_getLanguageModel();

		$defaultLanguageId = (XenForo_Application::debugMode()
			? 0
			: XenForo_Application::get('options')->defaultLanguageId
		);

		if ($this->_input->inRequest('language_id'))
		{
			$languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);
		}
		else
		{
			$languageId = XenForo_Helper_Cookie::getCookie('edit_language_id');
			if ($languageId === false)
			{
				$languageId = $defaultLanguageId;
			}
		}

		if ($this->_input->filterSingle('search', XenForo_Input::UINT))
		{
			$phraseModel = $this->_getPhraseModel();

			$input = $this->_input->filter(array(
				'title' => XenForo_Input::STRING,
				'phrase_text' => XenForo_Input::STRING,
				'phrase_state' => array(XenForo_Input::STRING, 'array' => true)
			));

			if (!$phraseModel->canModifyPhraseInLanguage($languageId))
			{
				return $this->responseError(new XenForo_Phrase('phrases_in_this_language_can_not_be_modified'));
			}

			$conditions = array();
			if (!empty($input['title']))
			{
				$conditions['title'] = $input['title'];
			}
			if (!empty($input['phrase_text']))
			{
				$conditions['phrase_text'] = $input['phrase_text'];
			}
			if ($languageId && !empty($input['phrase_state']) && count($input['phrase_state']) < 3)
			{
				$conditions['phrase_state'] = $input['phrase_state'];
			}

			if (empty($conditions))
			{
				return $this->responseError(new XenForo_Phrase('please_complete_required_fields'));
			}

			$phrases = $phraseModel->getEffectivePhraseListForLanguage($languageId, $conditions);

			$viewParams = array(
				'language' => $languageModel->getLanguageById($languageId, true),
				'phrases' => $phrases
			);
			return $this->responseView('XenForo_ViewAdmin_Phrase_SearchResults', 'phrase_search_results', $viewParams);
		}
		else
		{
			$showMaster = $languageModel->showMasterLanguage();

			$viewParams = array(
				'languages' => $languageModel->getAllLanguagesAsFlattenedTree($showMaster ? 1 : 0),
				'masterLanguage' => $showMaster ? $languageModel->getLanguageById(0, true) : false,
				'languageId' => $languageId
			);
			return $this->responseView('XenForo_ViewAdmin_Phrase_Search', 'phrase_search', $viewParams);
		}
	}

	/**
	 * Displays a list of outdated phrases.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionOutdated()
	{
		$phrases = $this->_getPhraseModel()->getOutdatedPhrases();
		if (!$phrases)
		{
			return $this->responseMessage(new XenForo_Phrase('there_are_no_outdated_phrases'));
		}

		$grouped = array();
		foreach ($phrases AS $phrase)
		{
			$grouped[$phrase['language_id']][$phrase['phrase_id']] = $phrase;
		}

		$viewParams = array(
			'phrasesGrouped' => $grouped,
			'totalPhrases' => count($phrases),
			'languages' => $this->_getLanguageModel()->getAllLanguages()
		);
		return $this->responseView('XenForo_ViewAdmin_Phrase_Outdated', 'phrase_outdated', $viewParams);
	}

	/**
	 * Gets the specified phrase or throws an exception.
	 *
	 * @param integer $phraseId
	 *
	 * @return array
	 */
	protected function _getPhraseOrError($phraseId)
	{
		$info = $this->_getPhraseModel()->getPhraseById($phraseId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_phrase_not_found'), 404));
		}

		return $info;
	}


	/**
	 * Lazy load the phrase model object.
	 *
	 * @return  XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}

	/**
	 * Lazy load the language model object.
	 *
	 * @return  XenForo_Model_Language
	 */
	protected function _getLanguageModel()
	{
		return $this->getModelFromCache('XenForo_Model_Language');
	}

	/**
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}