/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.PermissionChoicesOld = function($element) { this.__construct($element); };
	XenForo.PermissionChoicesOld.prototype =
	{
		__construct: function($form)
		{
			var $replaceBase, _this, $tooltipOptions, ttOptions = {};

			this.$form = $form;
			this.$selects = $form.find('select.PermissionChoice');
			this.$revokeOption = $form.find('.RevokeOption input[type="checkbox"]');

			$tooltipOptions = $form.find('.PermissionTooltipOption');
			if ($tooltipOptions.length)
			{
				$tooltipOptions.each(function()
				{
					var $this = $(this);
					$($this.data('permissionState').split(' ')).each(
						function(k, v) { ttOptions[v] = $this; }
					);
				});
			}
			this.tooltipOptions = ttOptions;

			this.$revokeOption.click($.context(this, 'updateRevokeStatus'));
			this.updateRevokeStatus(true);

			_this = this;

			this.$selects.each(function()
			{
				var $this = $(this),
					$replaceCore,
					$tooltip;

				$replaceCore = $('<span />')
					.click($.context(_this, 'handleClick'))
					.data('select', $this);

				$tooltip = $('<div class="xenTooltip permissionTooltip" />').hide();

				$this.hide()
					.before(
						$('<div />').append($replaceCore).append($tooltip)
					);

				$replaceCore.attr('title', XenForo.htmlspecialchars($replaceCore.attr('title'))).tooltip({
					/*effect: 'slide',
					slideInSpeed: 50,
					slideOutSpeed: 50,*/
					offset: [-12, 5],
					position: 'bottom right',
					relative: true,
					onBeforeShow: function(e)
					{
						if (this.getTip().is(':empty'))
						{
							e.preventDefault();
						}
					}
				});
				$replaceCore.data('tooltip', $tooltip);

				_this.setReplaceState($replaceCore, $this, $tooltip);
			});
		},

		handleClick: function(e)
		{
			var $replace = $(e.currentTarget),
				$tooltip = $replace.data('tooltip'),
				$select = $replace.data('select'),
				select = $select.get(0),
				selectedIndex = select.selectedIndex;

			if (selectedIndex + 1 < select.length)
			{
				select.selectedIndex += 1;
			}
			else
			{
				select.selectedIndex = 0;
			}

			this.setReplaceState($replace, $select);
		},

		setReplaceState: function($replace, $select)
		{
			var val = $select.val();

			$replace.attr('class', 'permissionChoice permissionChoice_' + val);
			$replace.text($select.find(':selected').text());

			this.setTooltipState($replace);
		},

		setTooltipState: function($replace)
		{
			var $select = $replace.data('select'),
				val = $select.val(),
				$tooltip = $replace.data('tooltip');

			if (this.tooltipOptions[val])
			{
				$tooltip.html(this.tooltipOptions[val].clone());
			}
			else
			{
				$tooltip.empty();
			}
		},

		updateRevokeStatus: function(instant)
		{
			var $options = this.$form.find('.PermissionOptions');

			if (this.$revokeOption.is(':checked'))
			{
				if (instant === true)
				{
					$options.hide();
				}
				else
				{
					$options.xfSlideUp();
				}
			}
			else
			{
				$options.xfSlideDown();
			}
		}
	};

	XenForo.PermissionNeverWarning = function($form)
	{
		var $neverTooltip = $('#PermissionNeverTooltip');
		if (!$neverTooltip.length)
		{
			return;
		}
		$neverTooltip.appendTo(document.body);

		$form.find('.permission td.deny').tooltip({
			tip: '#PermissionNeverTooltip',
			position: 'center right',
			offset: [0, 5],
			effect: 'fade',
			predelay: 250
		});
	};

	XenForo.register('form.PermissionChoices', 'XenForo.PermissionNeverWarning');
	//XenForo.register('form.PermissionChoices', 'XenForo.PermissionChoices');
}
(jQuery, this, document);
