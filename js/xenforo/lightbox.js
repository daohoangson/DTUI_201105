/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.LightBox = function(Overlay, containerSelector) { this.__construct(Overlay, containerSelector); };
	XenForo.LightBox.prototype =
	{
		__construct: function(Overlay, containerSelector)
		{
			this.Overlay = Overlay;
			this.containerSelector = containerSelector;

			$('#LbPrev, #LbNext, #LbImage').click($.context(this, 'imageNavClick'));
		},

		setThumbStrip: function($container)
		{
			var images = {},
				$thumbStrip = $('#LbThumbs'),
				imageCount = 0;

			$container.find('img.LbImage').each($.context(function(i, image)
			{
				var $image = $(image),
					imgsrc = $image.data('src') || $image.attr('src');

				if (images[imgsrc] === undefined)
				{
					images[imgsrc] = $image;
					imageCount++;
				}
			}, this));

			console.info('Num images: %s', imageCount);

			switch (imageCount)
			{
				case 0: return false;
				case 1: $('#LightBox .imageNav').hide(); break;
				default: $('#LightBox .imageNav').show(); break;
			}

			// remove all existing thumbs
			$thumbStrip.find('li:not(#LbThumbTemplate)').xfRemove('hide', null, 0);

			// now add them in...
			$.each(images, $.context(function(src, $image)
			{
				$('#LbThumbTemplate').clone()
					.removeAttr('id')
					.appendTo($thumbStrip)
					.find('a')
						.data('src', src)
						.click($.context(function(e)
						{
							e.preventDefault();

							this.setImage($image, $image.closest(this.containerSelector));

						}, this))
						.find('img')
							.attr('src', $image.attr('src'));
			}, this));

			$('#LbThumbsScrollable').scrollable(
			{
				keyboard: false,
				items: '#LbThumbs',
				next: 'a.Next',
				prev: 'a.Prev'
			});

			return this;
		},

		/**
		 * Calculates the maximum allowable height of the lightbox image
		 *
		 * @returns {XenForo.LightBox}
		 */
		setImageMaxHeight: function()
		{
			var maxHeight = $(window).height()
				- (this.Overlay.getConf().top * 2)
				- $('#LbUpper').outerHeight()
				- $('#LbLower').outerHeight();

			console.info('window: %d, top: %d, lbUpper: %d, lbLower: %d',
				$(window).height(),
				this.Overlay.getConf().top * 2,
				$('#LbUpper').outerHeight(),
				$('#LbLower').outerHeight()
			);

			console.log('Setting LightBoxImage max height = %d', maxHeight);

			$('#LbImage')
				.css('max-height', maxHeight)
			//	.parent().css('height', maxHeight)
			;

			return this;
		},

		/**
		 * Sets a new image to be shown in the lightbox
		 *
		 * @param jQuery $image
		 * @param jQuery $container
		 *
		 * @returns {XenForo.LightBox}
		 */
		setImage: function($image, $container)
		{
			var imageSource = $image.data('src') || $image.attr('src'),
				$lightBoxImage = $('#LbImage'),
				animateWindow = false,
				animateSpeed = (XenForo.isTouchBrowser() ? 0 : XenForo.speed.fast);

			if (imageSource == $lightBoxImage.attr('src'))
			{
				console.log('Requested image is already displayed: %s.', imageSource);
				return this;
			}

			console.log('setImage to %s from %o', imageSource, $lightBoxImage);

			if (!$container)
			{
				$container = $image.closest(this.containerSelector);
			}

			this.setAvatar($container);

			this.setContentLink($container);

			this.selectThumb(imageSource);

			$('#LbUsername').text($container.data('author'));

			$('#LbDateTime').text($container.find('.DateTime:first').text());

			$('#LbNewWindow').attr('href', imageSource);

			$lightBoxImage.fadeTo(0, 0, function()
			{
				$('#LbProgress').show();

				$(this).attr('src', imageSource);

			}).one('load', function(e)
			{
				$('#LbProgress').hide();

				if (animateWindow)
				{
					$lightBoxImage.css('height', 40).closest('.image').animate({ height: $lightBoxImage.height() }, 0, function()
					{
						$lightBoxImage.css('height', 'auto').fadeTo(animateSpeed, 1);
					});
				}
				else
				{
					$lightBoxImage.fadeTo(animateSpeed, 1);
				}
			});

			return this;
		},

		/**
		 * Sets the avatar of the lightbox to the poster of the shown image
		 *
		 * @param jQuery $container
		 *
		 * @returns {XenForo.LightBox}
		 */
		setAvatar: function($container)
		{
			var $avatar = $container.find('a.avatar'),
				$avatarImg = $avatar.find('img'),
				avatarSrc;

			if ($avatarImg.length)
			{
				avatarSrc = $avatarImg.attr('src');
			}
			else
			{
				avatarSrc = $avatar.find('span.img').css('background-image').replace(/^url\(("|'|)([^\1]+)\1\)$/i, '$2');
			}

			$('#LbAvatar img').attr('src', avatarSrc);

			return this;
		},

		/**
		 * Sets the content link of the lightbox to the content containing the shown image
		 *
		 * @param jQuery $container
		 *
		 * @returns {XenForo.LightBox}
		 */
		setContentLink: function($container)
		{
			var id = $container.attr('id');

			if (id)
			{
				$('#LbContentLink, #LbDateTime')
					//.text('#' + id)
					.attr('href', window.location.href)
					.attr('hash', '#' + id);
			}
			else
			{
				$('#LbContentLink').text('').remoteAttr('href');
			}

			return this;
		},

		/**
		 * Navigates to another image relative to the one currently shown
		 *
		 * @param event e
		 */
		imageNavClick: function(e)
		{
			e.preventDefault();

			var src = $('#LbImage').attr('src'),
				$thumbs = $('#LbThumbs li:not(#LbThumbTemplate) a');

			$thumbs.each(function(i, thumb)
			{
				if ($(thumb).data('src') == src)
				{
					i += ($(e.target).closest('.imageNav').attr('id') == 'LbPrev' ? -1 : 1);

					if (i < 0)
					{
						i = $thumbs.length -1;
					}
					else if (i >= $thumbs.length)
					{
						i = 0;
					}

					$thumbs.eq(i).triggerHandler('click');

					return false;
				}
			})
		},

		/**
		 * Selects the appropriate thumb from the list
		 *
		 * @param string src
		 *
		 * @returns {XenForo.LightBox}
		 */
		selectThumb: function(src)
		{
			var $thumbs = $('#LbThumbs li:not(#LbThumbTemplate) a').removeClass('selected'),
				totalThumbs = $thumbs.length,
				selectedThumb = 0;

			$thumbs.each(function(i, thumb)
			{
				if ($(thumb).data('src') == src)
				{
					//$('#LbThumbsScrollable').data('scrollable').seekTo(i);
					//thumb.scrollIntoView();

					$(thumb).addClass('selected');
					$('#LbSelectedImage').text(i + 1);
					$('#LbTotalImages').text(totalThumbs);
					return false;
				}
			});

			return this;
		}
	};
}
(jQuery, this, document);