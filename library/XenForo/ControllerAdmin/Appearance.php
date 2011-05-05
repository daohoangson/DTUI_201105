<?php

class XenForo_ControllerAdmin_Appearance extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		// styles
		$styleModel = $this->getModelFromCache('XenForo_Model_Style');

		$styles = $styleModel->getAllStylesAsFlattenedTree();
		$styles = $styleModel->countCustomTemplatesPerStyle($styles);

		if ($styleModel->showMasterStyle())
		{
			$masterStyle =  $styleModel->getStyleById(0, true);
			$masterTemplates = $styleModel->countMasterTemplates();
		}
		else
		{
			$masterStyle = null;
			$masterTemplates = 0;
		}

		// languages
		$languageModel = $this->getModelFromCache('XenForo_Model_Language');

		$languages = $languageModel->getAllLanguagesAsFlattenedTree();
		$languages = $languageModel->countTranslatedPhrasesPerLanguage($languages);

		if ($languageModel->showMasterLanguage())
		{
			$masterLanguage =  $languageModel->getLanguageById(0, true);
			$masterPhrases = $languageModel->countMasterPhrases();
		}
		else
		{
			$masterLanguage = null;
			$masterPhrases = 0;
		}

		$visitor = XenForo_Visitor::getInstance();

		$viewParams = array(
			'canEditStyles' => $visitor->hasAdminPermission('style'),
			'canEditLanguages' => $visitor->hasAdminPermission('language'),

			'styles' => $styles,
			'masterStyle' => $masterStyle,
			'masterTemplates' => $masterTemplates,

			'languages' => $languages,
			'masterLanguage' => $masterLanguage,
			'masterPhrases' => $masterPhrases,
		);

		return $this->responseView('XenForo_ViewAdmin_Appearance_Splash', 'appearance_splash', $viewParams);
	}
}