/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{

	XenForo.CacheRebuild = function($element) { this.__construct($element); };
	XenForo.CacheRebuild.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form;
			this.enabled = true;

			$form.submit($.context(this, 'formSubmit'));
			$form.submit();
		},

		formSubmit: function(e)
		{
			if (!this.enabled)
			{
				return;
			}

			$('#ProgressText').show();
			$('#ErrorText').hide();
			$('input:submit', this.$form).hide();

			$(document).trigger('PseudoAjaxStart');

			if (Math.random() > 0.9)
			{
				// randomly don't use ajax; this allows the user to refresh and not lose everything
				return;
			}

			XenForo.ajax(
				this.$form.attr('action'),
				this.$form.serializeArray(),
				$.context(this, 'formSubmitResponse'),
				{ error: $.context(this, 'formSubmitError'), timeout: 125000 } // allow for a ~120 second timeout
			);

			e.preventDefault();
		},

		formSubmitResponse: function(ajaxData)
		{
			var resubmit = false;

			if (ajaxData)
			{
				try
				{
					if (ajaxData.error)
					{
						resubmit = true;
					}

					if (ajaxData._redirectTarget)
					{
						window.location = ajaxData._redirectTarget;
						return;
					}

					if (!ajaxData.rebuildMessage)
					{
						ajaxData.rebuildMessage = '';
					}
					$('.RebuildMessage', this.$form).text(ajaxData.rebuildMessage);

					if (!ajaxData.detailedMessage)
					{
						ajaxData.detailedMessage = '';
					}
					$('.DetailedMessage', this.$form).text(ajaxData.detailedMessage);

					if (ajaxData.showExitLink)
					{
						$('#ExitLink').show();
					}
					else
					{
						$('#ExitLink').hide();
					}

					if (ajaxData.elements)
					{
						for (var i in ajaxData.elements)
						{
							$('input[name="' + i + '"]', this.$form).val(ajaxData.elements[i]);
						}

						this.$form.submit();
						return;
					}
				}
				catch (exception) {}
			}

			this._formSubmitError(resubmit);
		},

		formSubmitError: function(xhr, status, error)
		{
			var resubmit = (xhr && xhr.readyState == 4 && xhr.responseText);
			this._formSubmitError(resubmit);
		},

		_formSubmitError: function(resubmit)
		{
			this.enabled = false;

			if (this.$form.data('MultiSubmitEnable'))
			{
				this.$form.data('MultiSubmitEnable')();
			}

			$('#ProgressText').hide();
			$('#ErrorText').show();

			if (resubmit)
			{
				this.$form.submit();
			}
			else
			{
				$('input:submit', this.$form).show();
			}
		}
	};

	XenForo.register('form.CacheRebuild', 'XenForo.CacheRebuild');

}
(jQuery, this, document);