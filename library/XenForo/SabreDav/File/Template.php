<?php

class XenForo_SabreDav_File_Template extends Sabre_DAV_File
{
	protected $_template = null;
	protected $_templateText = null;
	protected $_style;
	protected $_title;

	protected static $_templateModel = null;
	protected static $_propertyCache = array();
	protected static $_propertyModel = null;

	public function __construct(array $template = null, array $style, $title = null)
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

		$this->_style = $style;
	}

	public function getName()
	{
		if (strpos($this->_title, '.css') === false)
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

		// TODO: don't save a new template if the contents are the same?

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
		if ($this->_template && $this->_template['style_id'] == $this->_style['style_id'])
		{
			// only set this as the existing template if it truly exists in this style
			$dw->setExistingData($this->_template);
		}
		else
		{
			$dw->set('style_id', $this->_style['style_id']);
			if ($this->_template)
			{
				$addOnId = $this->_template['addon_id'];
			}
			else if (!$this->_style['style_id'])
			{
				$addOnId = XenForo_Model::create('XenForo_Model_AddOn')->getDefaultAddOnId();
			}
			else
			{
				$addOnId = '';
			}
			$dw->set('addon_id', $addOnId);
		}

		$dw->set('title', $this->_title);

		$properties = self::_getPropertiesInStyle($this->_style['style_id']);

		$propertyChanges = self::_getPropertyModel()->translateEditorPropertiesToArray(
			stream_get_contents($data), $contents, $properties
		);
		$contents = self::_getTemplateModel()->replaceLinkRelWithIncludes($contents);
		$dw->set('template', $contents);

		if ($dw->isChanged('title') || $dw->isChanged('template') || $dw->get('style_id') > 0)
		{
			$dw->updateVersionId();
		}

		XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'save', 'Public template');
		$dw->save();

		self::_getPropertyModel()->saveStylePropertiesInStyleFromTemplate(
			$this->_style['style_id'], $propertyChanges, $properties
		);
	}

	public function delete()
	{
		if ($this->_template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$dw->setExistingData($this->_template);

			XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'delete', 'Public template');
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
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$dw->setExistingData($this->_template);
			$dw->set('title', $title);
			if ($dw->isChanged('title') || $dw->isChanged('template') || $dw->get('style_id') > 0)
			{
				$dw->updateVersionId();
			}

			XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'save', 'Public template');
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
				$this->_template['template'], $this->_style['style_id'],
				self::_getPropertiesInStyle($this->_style['style_id'])
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