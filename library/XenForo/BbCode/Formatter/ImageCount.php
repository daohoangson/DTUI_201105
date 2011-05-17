<?php

/**
 * BB code formatter that follows the formatting of the base,
 * but also counts the number of times an [img] or [media] tag is rendered.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_Formatter_ImageCount extends XenForo_BbCode_Formatter_Base
{
	/**
	 * Counter of number of times an [img] tag is rendered.
	 *
	 * @var integer
	 */
	protected $_imageCount = 0;

	/**
	 * Counter of number of times a [media] tag is rendered.
	 *
	 * @var integer
	 */
	protected $_mediaCount = 0;

	/**
	 * Overridden image renderer. Counts images and does standard rendering.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagImage(array $tag, array $rendererStates)
	{
		$this->_imageCount++;
		return parent::renderTagImage($tag, $rendererStates);
	}

	/**
	 * Overridden media renderer. Counts media and does standard rendering.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagMedia(array $tag, array $rendererStates)
	{
		$this->_mediaCount++;
		return parent::renderTagMedia($tag, $rendererStates);
	}

	/**
	 * @return integer
	 */
	public function getImageCount()
	{
		return $this->_imageCount;
	}

	/**
	 * @return integer
	 */
	public function getMediaCount()
	{
		return $this->_mediaCount;
	}
}