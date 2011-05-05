/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Loads and displays the next batch of news feed items from the server.
	 *
	 * @param jQuery Link to click in order to initiate loading
	 */
	XenForo.NewsFeedLoader = function($link) { this.__construct($link); };
	XenForo.NewsFeedLoader.prototype =
	{
		__construct: function($link)
		{
			this.$link = $link.click($.context(this, 'load'));

			this.xhr = null;
		},

		/**
		 * Loads up the next x news feed items from the server
		 *
		 * @param Event e
		 *
		 * @return boolean false
		 */
		load: function(e)
		{
			e.preventDefault();
			e.target.blur();

			if (this.xhr === null && this.$link.attr('href'))
			{
				this.xhr = XenForo.ajax(
					this.$link.attr('href'),
					{ news_feed_id: this.$link.data('oldestItemId') },
					$.context(this, 'display')
				);
			}

			return false;
		},

		/**
		 * Handles the AJAX response from load() and displays any returned news feed items.
		 *
		 * @param object JSON data from AJAX
		 * @param string textStatus
		 */
		display: function(ajaxData, textStatus)
		{
			this.xhr = null;

			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			this.$link.data('oldestItemId', ajaxData.oldestItemId);

			if (XenForo.hasTemplateHtml(ajaxData))
			{
				var $html = $(ajaxData.templateHtml);

				if ($html.length)
				{
					$html.find('.event:first').addClass('forceBorder');

					$html.xfInsert('insertBefore', this.$link.closest('.NewsFeedEnd'), 'xfSlideDown', XenForo.speed.slow);
				}
			}

			if (ajaxData.feedEnds)
			{
				this.$link.closest('.NewsFeedEnd').xfFadeOut();
			}
		}
	};

	// *********************************************************************

	/**
	 * Hides an individual news feed item
	 *
	 * @param jQuery Link to click in order to hide a news feed item
	 */
	XenForo.NewsFeedItemHider = function($link) { this.__construct($link); };
	XenForo.NewsFeedItemHider.prototype =
	{
		__construct: function($link)
		{
			this.$link = $link.click($.context(this, 'requestHide'));
		},

		/**
		 * Sends an AJAX request to the server, requesting that a news feed item be hidden
		 *
		 * @param Event e
		 *
		 * @return boolean false
		 */
		requestHide: function(e)
		{
			e.preventDefault();

			// hide immediately, assume success
			$(this.$link.closest('.NewsFeedItem')).xfRemove();

			XenForo.ajax(
				this.$link.attr('href'),
				'',
				$.context(this, 'hide')
			);
		},

		/**
		 * Receives the AJAX response from requestHide() and does the actual hiding.
		 *
		 * @param object JSON data from AJAX
		 * @param string textStatus
		 */
		hide: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			// nothing else to do now.
		}
	};

	// *********************************************************************

	XenForo.register('a.NewsFeedLoader', 'XenForo.NewsFeedLoader');

	XenForo.register('a.NewsFeedItemHider', 'XenForo.NewsFeedItemHider');
}
(jQuery, this, document);