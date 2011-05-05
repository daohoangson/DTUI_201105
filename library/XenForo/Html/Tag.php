<?php

/**
 * Represents an individual tag within an HTML tree.
 *
 * @package XenForo_Html
 */
class XenForo_Html_Tag
{
	/**
	 * Name of the tag (lower case).
	 *
	 * @var string
	 */
	protected $_tagName = '';

	/**
	 * Key-value pairs of attributes for the tag
	 *
	 * @var array
	 */
	protected $_attributes = array();

	/**
	 * Parent tag object.
	 *
	 * @var XenForo_Html_Tag|null Null for root tag
	 */
	protected $_parent = null;

	/**
	 * List of child tags and text.
	 *
	 * @var array Values are XenForo_Html_Tag or XenForo_Html_Text elements
	 */
	protected $_children = array();

	/**
	 * List of tags that are considered to be block tags.
	 *
	 * @var array
	 */
	protected $_blockTags = array(
		'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
		'dl', 'dt', 'dd', 'ol', 'ul', 'li',
		'address', 'blockquote', 'del', 'div', 'hr', 'ins', 'pre',
		'table', 'thead', 'tbody', 'tfoot', 'tr',
		'header', 'nav', 'footer', 'article'
		// note that "" is not here
	);

	protected $_voidTags = array(
		'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'wbr'
	);

	/**
	 * Constructor.
	 *
	 * @param string $tagName
	 * @param array $attributes
	 * @param XenForo_Html_Tag $parent
	 */
	public function __construct($tagName, array $attributes = array(), XenForo_Html_Tag $parent = null)
	{
		$this->_tagName = strtolower($tagName);
		$this->_attributes = $attributes;
		$this->_parent = $parent;
	}

	/**
	 * Appends text to the tag. If the last child is text, it will be added
	 * to that child; otherwise, a new child will be created.
	 *
	 * @param string $text
	 */
	public function appendText($text)
	{
		if ($this->isVoid())
		{
			throw new XenForo_Exception('Void tag ' . htmlspecialchars($this->_tagName) . ' cannot have children');
		}

		if ($this->_children)
		{
			$keys = array_keys($this->_children);
			$lastKey = end($keys);
			if ($this->_children[$lastKey] instanceof XenForo_Html_Text)
			{
				$this->_children[$lastKey]->addText($text);
				return;
			}
		}

		$this->_children[] = new XenForo_Html_Text($text, $this);
	}

	/**
	 * Adds a new child tag.
	 *
	 * @param string $tagName
	 * @param array $attributes
	 *
	 * @return XenForo_Html_Tag New child tag
	 */
	public function addChildTag($tagName, array $attributes = array())
	{
		if ($this->isVoid())
		{
			throw new XenForo_Exception('Void tag ' . htmlspecialchars($this->_tagName) . ' cannot have children');
		}

		$child = new XenForo_Html_Tag($tagName, $attributes, $this);
		$this->_children[] = $child;

		return $child;
	}

	/**
	 * Closes the given tag. This generally does not require modifying the tag tree,
	 * unless invalid nesting occurred.
	 *
	 * @param string $tagName
	 *
	 * @return XenForo_Html_Tag The new "parent" tag that should be used by the parser
	 */
	public function closeTag($tagName)
	{
		$tagName = strtolower($tagName);
		if ($tagName == $this->_tagName || $this->isVoid())
		{
			return $this->_parent;
		}
		else
		{
			$stack = array();
			for ($tag = $this; $tag && $tag->tagName() != $tagName; $tag = $tag->parent())
			{
				$stack[] = $tag;
			}

			if ($tag)
			{
				$newParent = $tag->closeTag($tagName);
				while ($createTag = array_pop($stack))
				{
					$newParent = $newParent->addChildTag($createTag->tagName(), $createTag->attributes());
				}

				return $newParent;
			}
			else
			{
				// tag not found, ignore it
				return $this->_parent;
			}
		}
	}

	/**
	 * Gets the tag name.
	 *
	 * @return string
	 */
	public function tagName()
	{
		return $this->_tagName;
	}

	/**
	 * Gets the attributes.
	 *
	 * @return array
	 */
	public function attributes()
	{
		return $this->_attributes;
	}

	/**
	 * Gets the named attribute.
	 *
	 * @param string $attribute
	 *
	 * @return mixed|false
	 */
	public function attribute($attribute)
	{
		return (isset($this->_attributes[$attribute]) ? $this->_attributes[$attribute] : false);
	}

	/**
	 * Gets the parent tag.
	 *
	 * @return XenForo_Html_Tag|null
	 */
	public function parent()
	{
		return $this->_parent;
	}

	/**
	 * Sets the parent tag. This does not check for circular references!
	 *
	 * @param XenForo_Html_Tag $parent
	 */
	public function setParent(XenForo_Html_Tag $parent)
	{
		$this->_parent = $parent;
	}

	/**
	 * Gets the child tags and text.
	 *
	 * @return array
	 */
	public function children()
	{
		return $this->_children;
	}

	/**
	 * Copies this tag. Does not copy any children tags or this tag's parent. The
	 * parent will need to be set manually later.
	 *
	 * @return XenForo_HTml_Tag
	 */
	public function copy()
	{
		return new XenForo_Html_Tag($this->_tagName, $this->_attributes);
	}

	/**
	 * Determines if the tag has renderable content within.
	 *
	 * @return boolean
	 */
	public function isEmpty()
	{
		switch ($this->_tagName)
		{
			case 'img':
			case 'br':
				return false;
		}

		foreach ($this->children() AS $child)
		{
			if ($child instanceof XenForo_Html_Tag)
			{
				if (!$child->isEmpty())
				{
					return false;
				}
			}
			else if ($child instanceof XenForo_Html_Text)
			{
				if (trim($child->text()) !== '')
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Determines if this tag is a block-level tag.
	 *
	 * @return boolean
	 */
	public function isBlock()
	{
		return in_array($this->_tagName, $this->_blockTags);
	}

	/**
	 * Determines if this tag is a void tag. Void tags can't have children.
	 *
	 * @return boolean
	 */
	public function isVoid()
	{
		return in_array($this->_tagName, $this->_voidTags);
	}
}