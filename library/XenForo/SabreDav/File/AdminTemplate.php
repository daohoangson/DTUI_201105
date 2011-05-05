<?php

class XenForo_SabreDav_File_AdminTemplate extends Sabre_DAV_File
{
	protected $_template = null;
	protected $_templateText = null;
	protected $_title;

	protected static $_adminTemplateModel = null;
	protected static $_templateModel = null;
	protected static $_propertyCache = array();
	protected static $_propertyModel = null;

	public function __construct(array $template = null, $title)
	{
		if ($template)
		{
			$this->_template = $template;
			$this->_title = $template['title'];
		}
		else
		{
			$this->_title = $title;
		}
	}

	public function getName()
	{
		if (strpos($this->_title, '.') === false)
		{
			return $this->_title . '.html';
		}
		else
		{
			return $this->_title;
		}
	}

	public function getLastModified()
	{
		return 0;
	}

	public function getETag()
	{
		$templateText = $this->_getTemplateText();
		if ($templateText === false)
		{
			return 'new';
		}
		else
		{
			return md5($templateText);
		}
	}

	public function get()
	{
		$templateText = $this->_getTemplateText();
		if ($templateText === false)
		{
			return '';
		}
		else
		{
			return $templateText;
		}
	}

	public function getSize()
	{
		$templateText = $this->_getTemplateText();
		if ($templateText === false)
		{
			return 0;
		}
		else
		{
			return strlen($templateText);
		}
	}

	public function getContentType()
	{
		if (strpos($this->_title, '.') === false)
		{
			return 'text/html';
		}
		else if (strpos($this->_title, '.css') !== false)
		{
			return 'text/css';
		}
		else
		{
			return null;
		}
	}

	public function put($data)
	{
		if (!$this->_title || $this->_title[0] == '.' || $this->_title == 'Thumbs.db' || $this->_title == 'desktop.ini')
		{
			// don't save files that are likely temporary
			return;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
		if ($this->_template)
		{
			// only set this as the existing template if it truly exists in this style
			$dw->setExistingData($this->_template);
		}
		else
		{
			$dw->set('addon_id', XenForo_Model::create('XenForo_Model_AddOn')->getDefaultAddOnId());
		}

		$dw->set('title', $this->_title);

		$properties = self::_getPropertiesInStyle(-1);

		$propertyChanges = self::_getPropertyModel()->translateEditorPropertiesToArray(
			stream_get_contents($data), $contents, $properties
		);
		$contents = self::_getTemplateModel()->replaceLinkRelWithIncludes($contents);

		$dw->set('template', $contents);

		XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'save', 'Admin template');
		$dw->save();

		self::_getPropertyModel()->saveStylePropertiesInStyleFromTemplate(
			-1, $propertyChanges, $properties
		);
	}

	public function delete()
	{
		if ($this->_template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			$dw->setExistingData($this->_template);

			XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'delete', 'Admin template');
			$dw->delete();
		}
	}

	public function setName($title)
	{
		if (substr($title, -5) == '.html')
		{
			$title = substr($title, 0, -5);
		}

		if ($this->_template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			$dw->setExistingData($this->_template);
			$dw->set('title', $title);

			XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'save', 'Admin template');
			$dw->save();
		}
	}

	protected function _getTemplateText()
	{
		if ($this->_templateText !== null)
		{
			return $this->_templateText;
		}

		if (!$this->_template)
		{
			$this->_templateText = false;
		}
		else
		{
			$this->_templateText = self::_getPropertyModel()->replacePropertiesInTemplateForEditor(
				$this->_template['template'], -1,
				self::_getPropertiesInStyle(-1)
			);

			$this->_templateText = self::_getTemplateModel()->replaceIncludesWithLinkRel($this->_templateText);
		}

		return $this->_templateText;
	}

	protected static function _getPropertiesInStyle($styleId)
	{
		if (!isset(self::$_propertyCache[$styleId]))
		{
			$propertyModel = self::_getPropertyModel();
			self::$_propertyCache[$styleId] = $propertyModel->keyPropertiesByName(
				$propertyModel->getEffectiveStylePropertiesInStyle($styleId)
			);
		}

		return self::$_propertyCache[$styleId];
	}

	/**
	 * @return XenForo_Model_AdminTemplate
	 */
	protected static function _getAdminTemplateModel()
	{
		if (!self::$_adminTemplateModel)
		{
			self::$_adminTemplateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');
		}

		return self::$_adminTemplateModel;
	}

	/**
	 * @return XenForo_Model_Template
	 */
	protected static function _getTemplateModel()
	{
		if (!self::$_templateModel)
		{
			self::$_templateModel = XenForo_Model::create('XenForo_Model_Template');
		}

		return self::$_templateModel;
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected static function _getPropertyModel()
	{
		if (!self::$_propertyModel)
		{
			self::$_propertyModel = XenForo_Model::create('XenForo_Model_StyleProperty');
		}

		return self::$_propertyModel;
	}
}