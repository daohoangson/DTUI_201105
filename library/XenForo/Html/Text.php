<?php

class XenForo_Html_Text
{
	protected $_text = '';
	protected $_parent = null;

	public function __construct($text, XenForo_Html_Tag $parent = null)
	{
		$this->_text = $text;
		$this->_parent = $parent;
	}

	public function addText($text)
	{
		$this->_text .= $text;
	}

	public function text()
	{
		return $this->_text;
	}

	public function parent()
	{
		return $this->_parent;
	}

	public function setParent(XenForo_Html_Tag $parent)
	{
		$this->_parent = $parent;
	}

	public function copy()
	{
		return new XenForo_Html_Text($this->_text);
	}

	public function __toString()
	{
		return $this->text();
	}
}