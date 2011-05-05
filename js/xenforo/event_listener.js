/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{

	XenForo.EventListenerOption = function($element) { this.__construct($element); };
	XenForo.EventListenerOption.prototype =
	{
		__construct: function($select)
		{
			this.$select = $select;
			this.url = $select.data('descUrl');
			this.$target = $($select.data('descTarget'));
			if (!this.url || !this.$target.length)
			{
				return;
			}

			$select.bind(
			{
				keyup: $.context(this, 'fetchDescriptionDelayed'),
				change: $.context(this, 'fetchDescription')
			});
			if ($select.val().length)
			{
				this.fetchDescription();
			}
		},

		fetchDescriptionDelayed: function()
		{
			if (this.delayTimer)
			{
				clearTimeout(this.delayTimer);
			}

			this.delayTimer = setTimeout($.context(this, 'fetchDescription'), 250);
		},

		fetchDescription: function()
		{
			if (!this.$select.val().length)
			{
				this.$target.html('');
				return;
			}

			if (this.xhr)
			{
				this.xhr.abort();
			}

			this.xhr = XenForo.ajax(
				this.url,
				{ event_id: this.$select.val() },
				$.context(this, 'ajaxSuccess'),
				{ error: false }
			);
		},

		ajaxSuccess: function(ajaxData)
		{
			if (ajaxData)
			{
				this.$target.html(ajaxData.description);
			}
			else
			{
				this.$target.html('');
			}
		}
	};

	XenForo.register('select.EventListenerOption', 'XenForo.EventListenerOption');

}
(jQuery, this, document);