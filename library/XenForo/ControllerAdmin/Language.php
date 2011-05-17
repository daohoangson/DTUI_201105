<?php

class XenForo_ControllerAdmin_Language extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('language');
	}

	public function actionIndex()
	{
		$languageModel = $this->_getLanguageModel();

		$languages = $languageModel->getAllLanguagesAsFlattenedTree();

		$masterLanguage = $languageModel->showMasterLanguage() ? $languageModel->getLanguageById(0, true) : array();

		if ($masterLanguage)
		{
			foreach ($languages AS &$language)
			{
				$language['depth']++;
			}
		}

		$viewParams = array(
			'languages' => $languages,
			'masterLanguage' => $masterLanguage,
			'totalLanguages' => count($languages) + ($languageModel->showMasterLanguage() ? 1 : 0)
		);

		return $this->responseView('XenForo_ViewAdmin_Language_List', 'language_list', $viewParams);
	}

	public function _getLanguageAddEditResponse(array $language)
	{
		$languageModel = $this->_getLanguageModel();

		list($dateFormats, $timeFormats) = $languageModel->getLanguageFormatExamples();

		$viewParams = array(
			'languages' => $languageModel->getAllLanguagesAsFlattenedTree(),
			'language' => $language,
			'locales' => XenForo_Helper_Language::getLocaleList(),
			'dateFormats' => $dateFormats,
			'timeFormats' => $timeFormats
		);

		return $this->responseView('XenForo_ViewAdmin_Language_Edit', 'language_edit', $viewParams);
	}

	public function actionAdd()
	{
		$language = $this->_getLanguageModel()->getDefaultLanguage();

		return $this->_getLanguageAddEditResponse($language);
	}

	public function actionEdit()
	{
		$languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);
		$language = $this->_getLanguageOrError($languageId);

		return $this->_getLanguageAddEditResponse($language);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			// user clicked delete
			return $this->responseReroute('XenForo_ControllerAdmin_Language', 'deleteConfirm');
		}

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'parent_id' => XenForo_Input::UINT,
			'date_format' => XenForo_Input::STRING,
			'time_format' => XenForo_Input::STRING,
			'decimal_point' => array(XenForo_Input::STRING, 'noTrim' => true),
			'thousands_separator' => array(XenForo_Input::STRING, 'noTrim' => true),
			'language_code' => XenForo_Input::STRING,
		));
		$languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);

		if ($input['date_format'] === '')
		{
			$input['date_format'] = $this->_input->filterSingle('date_format_other', XenForo_Input::STRING);
		}
		if ($input['time_format'] === '')
		{
			$input['time_format'] = $this->_input->filterSingle('time_format_other', XenForo_Input::STRING);
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Language');
		if ($languageId)
		{
			$writer->setExistingData($languageId);
		}

		$writer->bulkSet($input);
		$writer->save();

		return XenForo_CacheRebuilder_Abstract::getRebuilderResponse(
			$this, $writer->getExtraData(XenForo_DataWriter_Language::DATA_REBUILD_CACHES),
				XenForo_Link::buildAdminLink('languages') . $this->getLastHash($languageId)
		);
	}

	/**
	 * Deletes a language.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Language');
			$dw->setExistingData($this->_input->filterSingle('language_id', XenForo_Input::UINT));
			$dw->delete();

			return XenForo_CacheRebuilder_Abstract::getRebuilderResponse(
				$this, $dw->getExtraData(XenForo_DataWriter_Language::DATA_REBUILD_CACHES), XenForo_Link::buildAdminLink('languages')
			);
		}
		else
		{
			$languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);
			$language = $this->_getLanguageOrError($languageId);

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Language', XenForo_DataWriter::ERROR_EXCEPTION);
			$writer->setExistingData($language);
			$writer->preDelete();

			$viewParams = array(
				'language' => $language
			);

			return $this->responseView('XenForo_ViewAdmin_Language_Delete', 'language_delete', $viewParams);
		}
	}

	/**
	* Displays the list of phrases in the specified language.
	*
	* @return XenForo_ControllerResponse_Abstract
	*/
	public function actionPhrases()
	{
		$languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);
		$language = $this->_getLanguageOrError($languageId, true);

		$languageModel = $this->_getLanguageModel();
		$phraseModel = $this->_getPhraseModel();

		if (!$phraseModel->canModifyPhraseInLanguage($languageId))
		{
			return $this->responseError(new XenForo_Phrase('phrases_in_this_language_can_not_be_modified'));
		}

		// set an edit_language_id cookie so we can switch to another area and maintain the current style selection
		XenForo_Helper_Cookie::setCookie('edit_language_id', $languageId);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 100;

		$conditions = array();

		$filter = $this->_input->filterSingle('_filter', XenForo_Input::ARRAY_SIMPLE);
		if ($filter && isset($filter['value']))
		{
			$conditions['title'] = array($filter['value'], empty($filter['prefix']) ? 'lr' : 'r');
			$filterView = true;
		}
		else
		{
			$filterView = false;
		}

		$fetchOptions = array(
			'page' => $page,
			'perPage' => $perPage
		);

		$totalPhrases = $phraseModel->countEffectivePhrasesInLanguage($languageId, $conditions, $fetchOptions);

		$viewParams = array(
			'phrases' => $phraseModel->getEffectivePhraseListForLanguage($languageId, $conditions, $fetchOptions),
			'languages' => $languageModel->getAllLanguagesAsFlattenedTree($languageModel->showMasterLanguage() ? 1 : 0),
			'masterLanguage' => $languageModel->showMasterLanguage() ? $languageModel->getLanguageById(0, true) : array(),
			'language' => $languageModel->getLanguageById($languageId, true),

			'page' => $page,
			'perPage' => $perPage,
			'totalPhrases' => $totalPhrases,

			'filterView' => $filterView,
			'filterMore' => ($filterView && $totalPhrases > $perPage)
		);

		return $this->responseView('XenForo_ViewAdmin_Phrase_List', 'phrase_list', $viewParams);
	}

	public function actionExport()
	{
		$languageId = $this->_input->filterSingle('language_id', XenForo_Input::UINT);
		$language = $this->_getLanguageOrError($languageId, true);

		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'addon_id' => XenForo_Input::STRING,
				'untranslated' => XenForo_Input::UINT
			));

			$this->_routeMatch->setResponseType('xml');

			$addOnId = ($input['addon_id'] ? $input['addon_id'] : null);

			$viewParams = array(
				'language' => $language,
				'xml' => $this->_getLanguageModel()->getLanguageXml($language, $addOnId, $input['untranslated'])
			);

			return $this->responseView('XenForo_ViewAdmin_Language_ExportXml', '', $viewParams);
		}
		else
		{
			$viewParams = array(
				'language' => $language,
				'addOnOptions' => $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnOptionsList(false, true)
			);

			return $this->responseView('XenForo_ViewAdmin_Language_Export', 'language_export', $viewParams);
		}
	}

	public function actionImport()
	{
		$languageModel = $this->_getLanguageModel();

		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'target' => XenForo_Input::STRING,
				'parent_language_id' => XenForo_Input::UINT,
				'overwrite_language_id' => XenForo_Input::UINT
			));

			$upload = XenForo_Upload::getUploadedFile('upload');
			if (!$upload)
			{
				return $this->responseError(new XenForo_Phrase('please_upload_valid_language_xml_file'));
			}

			if ($input['target'] == 'overwrite')
			{
				$this->_getLanguageOrError($input['overwrite_language_id']);
				$input['parent_language_id'] = 0;
			}
			else
			{
				$input['overwrite_language_id'] = 0;
			}

			$document = $this->getHelper('Xml')->getXmlFromFile($upload);
			$caches = $languageModel->importLanguageXml($document, $input['parent_language_id'], $input['overwrite_language_id']);

			return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches, XenForo_Link::buildAdminLink('languages'));
		}
		else
		{
			$viewParams = array(
				'languages' => $languageModel->getAllLanguagesAsFlattenedTree()
			);

			return $this->responseView('XenForo_ViewAdmin_Language_Import', 'language_import', $viewParams);
		}
	}

	/**
	 * Gets the specified language or throws an error.
	 *
	 * @param integer $languageId
	 * @param boolean $allowMaster Allow the master language (0) to be fetched
	 *
	 * @return array
	 */
	protected function _getLanguageOrError($languageId, $allowMaster = false)
	{
		$language = $this->_getLanguageModel()->getLanguageById($languageId, $allowMaster);
		if (!$language)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_language_not_found'), 404));
		}

		return $language;
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
}