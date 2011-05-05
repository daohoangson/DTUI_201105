/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Controls for managing instant messaging account details on a form
	 *
	 * @param jQuery The form in which the controls exist
	 */
	XenForo.IdentityServicesForm = function($form) { this.__construct($form); };
	XenForo.IdentityServicesForm.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form.bind(
			{
				AutoValidationComplete: $.context(this, 'afterSubmit'),
				ImAccountsChanged: $.context(this, 'updateAccountAdder')
			});

			this.initialized = false;

			this.identityEditors = {};
			this.initIdentityEditors();

			this.initIdentityServiceAdder();
		},

		/**
		 * Finds all .ctrlUnit.IdentityServiceEditor elements and initializes them as IM Editors
		 */
		initIdentityEditors: function()
		{
			this.$identityEditors = $('.ctrlUnit.IdentityServiceEditor', this.$form);

			for (var i = 0; i < this.$identityEditors.length; i++)
			{
				this.addEditor($(this.$identityEditors[i]));
			}

			delete this.$identityEditors;
		},

		/**
		 * Initializes a single .ctrlUnit.IdentityServiceEditor element as a XenForo.IdentityServiceEditor
		 *
		 * @param jQuery .ctrlUnit.IdentityServiceEditor HTML element
		 *
		 * @return XenForo.IdentityServiceEditor
		 */
		addEditor: function($editor)
		{
			var identityEditor = XenForo.create('XenForo.IdentityServiceEditor', $editor);

			this.identityEditors[identityEditor.IdentityServiceId] = identityEditor;

			return identityEditor;
		},

		/**
		 * Initializes the control that allows undefined services to have details added for them.
		 * Searches for .ctrlUnit.IdentityServiceAdder, which contains a .IdentityServiceAddButton control,
		 * and a number of .IdentityServiceAddOption elements, each containing a checkbox.
		 */
		initIdentityServiceAdder: function()
		{
			this.$IdentityServiceAdder = $('.ctrlUnit.IdentityServiceAdder', this.$form);

			this.$addButton = $('.IdentityServiceAddButton', this.$IdentityServiceAdder)
				.click($.context(this, 'addServices'));

			this.servicesRemaining = false;
			this.$serviceOptions = $('.IdentityServiceAddOption', this.$IdentityServiceAdder);

			for (var i = 0; i < this.$serviceOptions.length; i++)
			{
				this.updateServiceOption(i);
			}

			this.$IdentityServiceAdder[(this.servicesRemaining ? 'xfShow' : 'xfHide')]();

			this.initialized = true;
		},

		/**
		 * Does a similar job to initIdentityServiceAdder, but uses animated show/hide
		 * and is designed to run after the form is initialized, after each time
		 * a field is added or removed.
		 *
		 * @param jQuery.Event e
		 */
		updateAccountAdder: function(e)
		{
			this.servicesRemaining = false;

			for (var i = 0; i < this.$serviceOptions.length; i++)
			{
				this.updateServiceOption(i);
			}

			if (this.servicesRemaining)
			{
				this.$IdentityServiceAdder.xfFadeDown(XenForo.speed.fast);
			}
			else
			{
				this.$IdentityServiceAdder.xfFadeUp(XenForo.speed.fast);
			}
		},

		/**
		 * Updates a single .IdentityServiceAddOption, showing or hiding it according to
		 * what services remain available to be added.
		 *
		 * @param integer Index of IdentityServiceAddOption within this.$serviceOptions
		 */
		updateServiceOption: function(i)
		{
			var $option = $(this.$serviceOptions[i]);

			if (this.identityEditors[$option.data('IdentityServiceId')].visible)
			{
				this.optionHide($option, this.initialized);
			}
			else
			{
				this.optionShow($option, this.initialized);
				this.servicesRemaining = true;
			}
		},

		/**
		 * Reads the options within this.$serviceOptions and adds new fields
		 * according to which options are checked.
		 */
		addServices: function()
		{
			this.focusField = null;

			for (var i = 0; i < this.$serviceOptions.length; i++)
			{
				if ($('input:checkbox:checked', this.$serviceOptions[i]).length)
				{
					this.identityEditors[$(this.$serviceOptions[i]).data('IdentityServiceId')].show();
				}
			}

			this.updateAccountAdder();

			// attempt to focus the first field added
			if (this.focusField !== null && this.identityEditors[this.focusField] !== undefined)
			{
				this.identityEditors[this.focusField].focus();
			}
		},

		/**
		 * Triggered by AutoValidationComplete - looks for empty IM account fields and hides them.
		 * Also runs this.addServices so that any checked options are added to the form.
		 */
		afterSubmit: function(e)
		{
			for (var i in this.identityEditors)
			{
				if (this.identityEditors[i].val() === '')
				{
					this.identityEditors[i].hide();
				}
			}

			this.addServices();
		},

		/**
		 * Show a single option from this.$serviceOptions
		 *
		 * @param jQuery Option element to show
		 * @param boolean Animate - if false, show instantly
		 *
		 * @return jQuery Option element
		 */
		optionShow: function($option, animate)
		{
			if ($option.is(':hidden'))
			{
				$option.find('input:checkbox').attr('checked', '');

				if (animate)
				{
					return $option.xfFadeDown(XenForo.speed.normal);
				}
				else
				{
					return $option.xfShow();
				}
			}

			return $option;
		},

		/**
		 * Hide a single option from this.$serviceOptions
		 *
		 * @param jQuery Option element to hide
		 * @param boolean Animate - if false, hide instantly
		 *
		 * @return jQuery Option element
		 */
		optionHide: function($option, animate)
		{
			if ($option.is(':visible'))
			{
				if (this.focusField === null)
				{
					this.focusField = $option.data('IdentityServiceId');
				}

				if (animate)
				{
					return $option.xfFadeUp(XenForo.speed.normal);
				}
				else
				{
					return $option.xfHide();
				}
			}
		}
	};

	// *********************************************************************

	/**
	 * An individual instant messaging service option.
	 * This is designed to be instantiated by XenForo.IdentityServicesForm
	 *
	 * @param jQuery .ctrlUnit.IdentityServiceEditor HTML element
	 */
	XenForo.IdentityServiceEditor = function($ctrlUnit) { this.__construct($ctrlUnit); };
	XenForo.IdentityServiceEditor.prototype =
	{
		__construct: function($ctrlUnit)
		{
			this.visible = true;

			this.$ctrlUnit = $ctrlUnit;

			this.IdentityServiceId = $ctrlUnit.data('IdentityServiceId');

			this.$input = $('input:text', this.$ctrlUnit);

			this.$remover = $('.IdentityServiceRemover', this.$ctrlUnit).click($.context(this, 'remove'));

			this.$form = $(this.$input.get(0).form);

			if (this.$input.val() === '')
			{
				this.hide(true);
			}
		},

		/**
		 * Empties the input field and hides the .ctrlUnit
		 *
		 * @param jQuery.Event e
		 *
		 * @return jQuery this.$ctrlUnit
		 */
		remove: function(e)
		{
			this.$input.val('');

			return this.hide();
		},

		/**
		 * Hides the .ctrlUnit
		 *
		 * @param boolean If true, hide instantly instead of animated removal
		 *
		 * @return jQuery this.$ctrlUnit
		 */
		hide: function(instant)
		{
			this.visible = false;

			if (instant)
			{
				return this.$ctrlUnit.xfHide();
			}

			return this.$ctrlUnit.xfFadeUp(XenForo.speed.normal, $.context(function()
			{
				this.$form.trigger('ImAccountsChanged');
			}, this));
		},

		/**
		 * Shows the .ctrlUnit
		 *
		 * @return jQuery this.$ctrlUnit
		 */
		show: function()
		{
			this.visible = true;

			return this.$ctrlUnit.xfFadeDown(XenForo.speed.normal);
		},

		/**
		 * Gets or sets the value of this.$input
		 *
		 * @param string Value to set into this.$input. Leave empty to get value.
		 *
		 * @return jQuery|string this.$input | this.$input.val()
		 */
		val: function(value)
		{
			if (value === undefined)
			{
				return this.$input.val();
			}
			else
			{
				return this.$input.val(value);
			}
		},

		/**
		 * Attempts to focus this.$input
		 *
		 * @return jQuery this.$input
		 */
		focus: function()
		{
			return this.$input.focus();
		}
	};

	// *********************************************************************

	XenForo.register('form.IdentityServicesForm', 'XenForo.IdentityServicesForm');

}
(jQuery, this, document);