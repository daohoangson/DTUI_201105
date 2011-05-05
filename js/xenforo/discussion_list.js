/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Initializes various controls for discussion listings
	 *
	 * @param jQuery $('form.DiscussionList')
	 */
	XenForo.DiscussionList = function($form) { this.__construct($form); };
	XenForo.DiscussionList.prototype =
	{
		__construct: function($form)
		{
			this.$form = $form;

			$('a.EditControl', this.$form).live('click', $.context(this, 'editControlClick'));

			this.$editor = null;
			this.loaderXhr = null;
		},

		/**
		 * Handles clicks on the 'Edit' control
		 *
		 * @param event e
		 *
		 * @return boolean
		 */
		editControlClick: function(e)
		{
			if (this.loaderXhr)
			{
				return false;
			}

			var $editControl = $(e.target),
				$discussionListItem = $editControl.closest('.discussionListItem');

			if (this.$editor)
			{
				if (this.$editor.is(':animated'))
				{
					return false;
				}

				/*if (this.$editor.data('discussionListItemId') == $discussionListItem.attr('id'))
				{
					alert('moo');
					// a second click on the same edit control - go to the full editor
					return true;
				}
				else*/
				{
					this.$editor.xfRemove('xfSlideUp');
				}
			}

			$discussionListItem.addClass('AjaxProgress');

			this.loaderXhr = XenForo.ajax(
				$editControl.attr('href'),
				'',
				$.context(this, 'editorLoaded')
			);

			return false;
		},

		/**
		 * Runs when the ajax editor loader returns its data, initializes the new editor
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		editorLoaded: function(ajaxData, textStatus)
		{
			this.loaderXhr = null;

			var $discussionListItem = $('#thread-' + ajaxData.threadId + '.discussionListItem');

			if (XenForo.hasResponseError(ajaxData))
			{
				$discussionListItem.removeClass('AjaxProgress');
				return false;
			}

			new XenForo.ExtLoader(ajaxData, $.context(function()
			{
				this.$editor = $(ajaxData.templateHtml)
					.data('discussionListItemId', $discussionListItem.attr('id'))
					.xfInsert('insertAfter', $discussionListItem, 'xfSlideDown', XenForo.speed.fast, function()
					{
						$discussionListItem.removeClass('AjaxProgress');
					});
			}, this));
		}
	};

	// *********************************************************************

	/**
	 * Handler for the inline thread editor on thread lists
	 *
	 * @param jQuery .discussionListItemEdit
	 */
	XenForo.DiscussionListItemEditor = function($editor) { this.__construct($editor); };
	XenForo.DiscussionListItemEditor.prototype =
	{
		__construct: function($editor)
		{
			this.$editor = $editor;

			this.$saveButton = $('input:submit', this.$editor).click($.context(this, 'save'));

			this.$cancelButton = $('input:reset', this.$editor).click($.context(this, 'cancel'));
		},

		/**
		 * Saves the changes made to the inline editor
		 *
		 * @param event e
		 *
		 * @return boolean
		 */
		save: function(e)
		{
			if (!this.saverXhr)
			{
				var ajaxData = this.$editor.closest('form').serializeArray();
					ajaxData = XenForo.ajaxDataPush(ajaxData, '_returnDiscussionListItem', 1);

				this.$editor.addClass('InProgress');

				this.saverXhr = XenForo.ajax(
					this.$saveButton.data('submitUrl'),
					ajaxData,
					$.context(this, 'saveSuccess')
				);
			}

			return false;
		},

		/**
		 * Cancels an edit, removes the editor
		 *
		 * @param event e
		 *
		 * @return boolean false
		 */
		cancel: function(e)
		{
			this.removeEditor();

			return false;
		},

		/**
		 * Handles the save method's returned ajax data
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		saveSuccess: function(ajaxData, textStatus)
		{
			this.saverXhr = null;
			this.$editor.removeClass('InProgress');

			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			this.removeEditor();

			var $oldDiscussionListItem = $('#thread-' + ajaxData.threadId);

			$oldDiscussionListItem.fadeOut(XenForo.speed.normal, function()
			{
				$(ajaxData.templateHtml).xfInsert('insertBefore', $oldDiscussionListItem, 'xfFadeIn', XenForo.speed.normal);

				$oldDiscussionListItem.remove();
			});
		},

		/**
		 * Removes the editor from the DOM
		 */
		removeEditor: function()
		{
			// TODO: why doesn't this use xfRemove() ?
			this.$editor.parent().xfSlideUp(
			{
				duration: XenForo.speed.slow,
				easing: 'easeOutBounce',
				complete: function()
				{
					$(this).remove();
				}
			});

			this.$editor = null;
		}
	};

	// *********************************************************************

	/**
	 * Pops open the thread list control panel
	 *
	 * @param jQuery .DiscussionListOptionsHandle a
	 */
	XenForo.DiscussionListOptions = function($handle) { this.__construct($handle); };
	XenForo.DiscussionListOptions.prototype =
	{
		__construct: function($handle)
		{
			this.$handle = $handle.click($.context(this, 'toggleOptions'));

			this.$options = $('form.DiscussionListOptions').hide();

			this.$submit = $('input:submit', this.$options).click($.context(this, 'hideOptions'));
			this.$reset = $('input:reset', this.$options).click($.context(this, 'hideOptions'));
		},

		/**
		 * Shows or hides the options panel
		 *
		 * @param event e
		 *
		 * @return boolean false
		 */
		toggleOptions: function(e)
		{
			if (this.$options.is(':animated'))
			{
				return false;
			}

			if (this.$options.is(':hidden'))
			{
				this.showOptions();
			}
			else
			{
				this.hideOptions();
			}

			return false;
		},

		/**
		 * Shows the options panel
		 */
		showOptions: function()
		{
			this.$options.xfFadeDown(XenForo.speed.normal, function()
			{
				$(this).find('input, select, textarea, button').get(0).focus();
			});
		},

		/**
		 * Hides the options panel
		 */
		hideOptions: function()
		{
			this.$options.xfFadeUp(XenForo.speed.normal);
		}
	};

	// *********************************************************************

	XenForo.register('form.DiscussionList', 'XenForo.DiscussionList');

	XenForo.register('.discussionListItemEdit', 'XenForo.DiscussionListItemEditor');

	XenForo.register('#DiscussionListOptionsHandle a', 'XenForo.DiscussionListOptions');

}
(jQuery, this, document);