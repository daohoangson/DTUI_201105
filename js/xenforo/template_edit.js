/**
 * @author kier
 * @todo The order of methods in this class is somewhat random.
 */

//TODO: Capitalise all lowercase IDs required by Javascript (#templateEditor etc.)

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.TemplateEditor = {};

	/**
	 * Multi-tab template editor
	 *
	 * @param jQuery form#templateEditor
	 */
	XenForo.TemplateEditor = function($form) { this.__construct($form); };
	XenForo.TemplateEditor.prototype =
	{
		__construct: function($form)
		{
			this.useAjaxSave = true;

			this.setupEditors($form);
		},

		/**
		 * Setup for multi-tab editor
		 *
		 * @param jQuery Template Editor
		 */
		setupEditors: function($form)
		{
			this.initialized = false;

			/**
			 * Forms and form fields
			 */
			this.$form             = $form;
			this.$styleId          = $('#styleId');
			this.$templateId       = $('#templateId');

			this.$titleOriginal    = $('#templateTitleOriginal');

			/*if (!this.$titleOriginal.strval())
			{
				return;
			}*/

			this.$templateTitle    = $('#templateTitle');
			this.$templateTextarea = $('#templateTextarea');

			this.$saveReloadButton = $('#saveReloadButton');
			this.$saveExitButton   = $('#saveExitButton');

			/**
			 * Tab-related stuff
			 */
			this.$changeIndicator  = this.createChangeIndicator();
			this.$templateTab      = $('#templateTab');
			this.$editorTabs       = $('#editorTabs');

			/**
			 * Misc storage
			 */
			this.editors = {};
			this.templateData = {
				'': {
					style_id: 0,
					template_id: 0,
					template: ''
				}
			};

			/**
			 *  css requires and template includes
			 */
			this.requireRegex = new RegExp('<xen:(require|include|edithint) [^>]*(css|template)="([^"]+)"[^>]*/?>', 'gi');

			if (this.$titleOriginal.strval())
			{
				this.loadTemplates(this.getIncludeTitles());
			}
			else
			{
				this.initialize();
			}
		},

		/**
		 * Reads the main template to work out what CSS is required or templates included,
		 * loads the required templates via AJAX,
		 * then builds tabs and editors for the required templates
		 */
		loadTemplates: function(titles)
		{
			if (titles.length)
			{
				XenForo.ajax(
					this.getLoadUrl('json'),
					{
						includeTitles: titles,
						style_id: this.$styleId.val(),
						_TemplateEditorAjax: 1
					},
					$.context(this, 'ajaxLoadSuccess')
				);
			}
		},

		/**
		 * AJAX callback for the template loader
		 *
		 * @param data
		 * @param textStatus
		 */
		ajaxLoadSuccess: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			this.templateData = ajaxData.templates;

			if (!this.initialized)
			{
				this.initialize();
			}

			this.handleTitleChange();

			this.refreshEditors();
		},

		initialize: function()
		{
			this.initializePrimaryEditor();

			this.updateSaveActions();
		},

		/**
		 * Like createEditor(), except turns the initial, primary editor into
		 * a Javascript-activated editor
		 */
		initializePrimaryEditor: function()
		{
			var templateId = this.$templateId.strval(),
				templateTitle = this.$titleOriginal.strval(),
				$changeIndicator = this.createChangeIndicator();

			console.log('Initializing primary editor for template %s, id %s', (templateTitle ? templateTitle : '(untitled)'), templateId);

			this.editors[templateTitle] =
			{
				templateId: this.$templateId.val(),
				$changeIndicator: $changeIndicator,

				$styleId: $(document.createElement('input'))
					.attr({
						type: 'hidden',
						name: 'styleidArray[' + templateId + ']'
					})
					.val(this.templateData[templateTitle].style_id),

				$tab: this.$templateTab,

				$title: this.$templateTitle
					.attr({
						templateTitle: templateTitle,
						name: 'titleArray[' + templateId + ']'
					})
					.keyup($.context(this, 'eTitleChange'))
					.blur($.context(this, 'setBlurItem')),

				$textarea: this.$templateTextarea
					.attr({
						templateTitle: templateTitle,
						name: 'templateArray[' + templateId + ']'
					})
					.keyup($.context(this, 'eTemplateChange'))
					.blur($.context(this, 'setBlurItem'))
			};

			this.editors[templateTitle].$tab.find('a')
				.append('&nbsp;')
				.attr('templateTitle', templateTitle)
				.addClass(this.getInheritanceState(templateTitle))
				.click($.context(this, 'switchEditor'))
				.prepend(this.editors[templateTitle].$changeIndicator)
				.prepend(this.editors[templateTitle].$styleId);

			this.initialized = true;
		},

		/**
		 * Registers the last item to be blurred
		 *
		 * @param e
		 * @return
		 */
		setBlurItem: function(e)
		{
			this.blurItem = e.target;
		},

		/**
		 * Focuses the element most recently set as this.blurItem
		 */
		focusBlurItem: function()
		{
			if (this.blurItem !== undefined)
			{
				this.blurItem.focus();
			}
		},

		/**
		 * Creates tabs representing each required CSS / included template
		 */
		refreshEditors: function()
		{
			var templateTitle = null, $lastTab = null;

			for (templateTitle in this.templateData)
			{
				if (typeof this.templateData[templateTitle] != 'function')
				{
					if (this.editors[templateTitle] === undefined)
					{
						// create a new editor
						this.editors[templateTitle] = this.createEditor(templateTitle, $lastTab);
					}
					else
					{
						// update an existing editor
						this.updateEditor(templateTitle);
					}

					$lastTab = this.editors[templateTitle].$tab;
				}
			}

			// check for editors that still exist that are not in the templateData
			for (templateTitle in this.editors)
			{
				if (typeof this.editors[templateTitle] != 'function')
				{
					if (this.templateData[templateTitle] === undefined)
					{
						this.destroyEditor(templateTitle);
					}
				}
			}
		},

		/**
		 * Creates a single tab and editor
		 *
		 * @param integer Index of current data within this.templateData
		 *
		 * @return object Editor
		 */
		createEditor: function(templateTitle, $prevTab)
		{
			var data = this.templateData[templateTitle],
				$changeIndicator = this.createChangeIndicator(),
				$tab = $('<a />')
					.html(templateTitle + '&nbsp;')
					.attr('href', data.link)
					.attr('templateTitle', templateTitle)
					.addClass(this.getInheritanceState(templateTitle))
					.prepend($changeIndicator)
					.click($.context(this, 'switchEditor')),
				editor = {};

			editor =
			{
				templateId: data.template_id,

				$styleId: $(document.createElement('input'))
					.attr({
						type: 'hidden',
						name: 'styleidArray[' + data.template_id + ']'
					})
					.val(data.style_id),

				$changeIndicator: $changeIndicator,

				$tab: $('<li />').append($tab),

				$textarea: this.$templateTextarea.clone(true)
					.xfHide()
					.attr({
						templateTitle: templateTitle,
						name: 'templateArray[' + data.template_id + ']'
					})
					.removeAttr('id')
					.val(data.template)
					.keyup($.context(this, 'eTemplateChange')),

				$title: $(document.createElement('input'))
					.attr({
						type: 'hidden',
						name: 'titleArray[' + data.template_id + ']'
					})
					.val(data.title)
			};

			if ($prevTab)
			{
				$prevTab.after(editor.$tab);
			}
			else
			{
				this.$editorTabs.append(editor.$tab);
			}

			this.getTextareaWrapper()
				.append(editor.$textarea)
				.append(editor.$title)
				.append(editor.$styleId);

			return editor;
		},

		/**
		 * Updates a single editor
		 *
		 * @param string templateTitle
		 */
		updateEditor: function(templateTitle)
		{
			var editor = this.editors[templateTitle],
				data = this.templateData[templateTitle];

			if (editor.templateId != data.template_id)
			{
				if (this.isPrimaryTemplate(templateTitle))
				{
					console.log('Primary template updated');

					this.$templateId.val(data.template_id);
				}

				editor.$tab.find('a')
					.removeClass('master custom inherited')
					.addClass(this.getInheritanceState(templateTitle));

				editor.$textarea.attr('name', 'templateArray[' + data.template_id + ']');

				editor.$title.attr('name', 'titleArray[' + data.template_id + ']');

				editor.$styleId.attr('name', 'styleidArray[' + data.template_id + ']');
				editor.$styleId.val(data.style_id);

				editor.templateId = data.template_id;
			}

			this.handleTemplateChange(templateTitle);
		},

		/**
		 * Destroys an editor, its tab and its original value
		 */
		destroyEditor: function(templateTitle)
		{
			this.editors[templateTitle].$tab.remove();

			this.editors[templateTitle].$textarea.remove();

			delete this.editors[templateTitle];
		},

		/**
		 * Alters the behaviours of the default save/reload button so that saves are done via AJAX
		 */
		updateSaveActions: function()
		{
			if (this.useAjaxSave && this.getSaveUrl('json'))
			{
				this.$saveReloadButton
					.val(this.$saveReloadButton.data('ajaxvalue'))
					.click($.context(this, 'saveAjax'));

				this.$saveExitButton
					.click($.context(this, 'saveExit'));

				this.$form.attr('action', this.getSaveUrl());
			}
		},

		/**
		 * Save all templates via AJAX request
		 *
		 * @param Event e
		 *
		 * @return boolean True
		 */
		saveAjax: function(e)
		{
			var postParams, i, includeTitles;

			if (e)
			{
				e.preventDefault();
			}

			this.toggleUnchangeFieldNames(false);

			postParams = this.$form.serializeArray();

			this.toggleUnchangeFieldNames(true);

			includeTitles = this.getIncludeTitles();
			for (i = 0; i < includeTitles.length; i++)
			{
				XenForo.ajaxDataPush(postParams, 'includeTitles[]', includeTitles[i]);
			}

			XenForo.ajaxDataPush(postParams, '_TemplateEditorAjax', 1);

			XenForo.ajax(
				this.getSaveUrl('json'),
				postParams,
				$.context(this, 'ajaxSaveSuccess')
			);

			return true;
		},

		/**
		 * Intercept saving all templates via normal POST
		 *
		 * @param Event e
		 *
		 * @return boolean True
		 */
		saveExit: function(e)
		{
			this.toggleUnchangeFieldNames(false);

			return true;
		},

		/**
		 * Removes or restores the 'name' attribute for any editors whose content is unmodified
		 * in order to prevent their values being sent through by the jQuery .serialize() function.
		 *
		 * @param boolean If true, restore removed name, otherwise remove name if contents are unchanged.
		 */
		toggleUnchangeFieldNames: function(restore)
		{
			var $textarea, titleChanged;

			for (templateTitle in this.editors)
			{
				if (typeof this.editors[templateTitle] != 'function')
				{
					titleChanged = false;

					if (this.isPrimaryTemplate(templateTitle) && this.$templateTitle.strval() != this.$titleOriginal.strval())
					{
						titleChanged = true;
					}

					if (!this.isChanged(templateTitle) && !titleChanged)
					{
						$textarea = this.editors[templateTitle].$textarea;

						if (restore)
						{
							$textarea.attr('name', $textarea.attr('oName'));
							$textarea.removeAttr('oName');
						}
						else
						{
							$textarea.attr('oName', $textarea.attr('name'));
							$textarea.removeAttr('name');
						}
					}
				}
			}
		},

		/**
		 * AJAX callback for the template saver
		 */
		ajaxSaveSuccess: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			if (ajaxData.saveMessage)
			{
				XenForo.alert(ajaxData.saveMessage, '', 1000);
			}

			this.focusBlurItem();

			// handle template renaming
			var oldTitle = this.$titleOriginal.strval(),
				newTitle = this.$templateTitle.strval();

			/**
			 * Check to see if the stored original template title is different from that in
			 * the user-editable textbox. If it's different but the new title does not exist
			 * in the returned AJAX data, we are looking at a page refresh. However, if it's
			 * different and it DOES exist in the AJAX data, we are likely looking at a post-save
			 * load, and should update the main editor object accordingly.
			 */
			if (oldTitle != newTitle && ajaxData.templates[newTitle] !== undefined)
			{
				// update properties of main template editor object
				this.editors[oldTitle].$tab.attr('templateTitle', newTitle);
				this.editors[oldTitle].$title.attr('templateTitle', newTitle);
				this.editors[oldTitle].$textarea.attr('templateTitle', newTitle);

				// update 'hard-copy' of title value
				this.$titleOriginal.val(newTitle);

				// re-key main template editor object
				this.editors[newTitle] = this.editors[oldTitle];
				delete this.editors[oldTitle];
			}

			this.ajaxLoadSuccess(ajaxData, textStatus);
		},

		/**
		 * Fetches the inhertance state of a specified template
		 *
		 * @param string Template Title
		 *
		 * @return string master|custom|inherited
		 */
		getInheritanceState: function(templateTitle)
		{
			if (this.templateData[templateTitle].style_id === undefined)
			{
				// If undefined, we either have no data, or this is an admin template.
				return 'master';
			}

			switch (parseInt(this.templateData[templateTitle].style_id))
			{
				case 0: return 'master';

				case parseInt(this.$styleId.val()): return 'custom';

				default: return 'inherited';
			}
		},


		/**
		 * Read the primary template for t:require and t:include tags
		 * and return an array of all their names
		 *
		 * @return array
		 */
		getIncludeTitles: function()
		{
			var titles = new Array(),
				match,
				i;

			if (this.$titleOriginal.strval() != '')
			{
				titles = this.titlePush(this.$titleOriginal.strval(), titles);
			}

			if (this.$templateTitle.strval() != '')
			{
				titles = this.titlePush(this.$templateTitle.strval(), titles);
			}

			if (this.$templateTextarea.val().indexOf('{xen:pagenav') != -1)
			{
				titles = this.titlePush('page_nav', titles);
			}

			if (match = this.$templateTextarea.val().match(this.requireRegex))
			{
				for (i = 0; i < match.length; i++)
				{
					titles = this.titlePush(match[i].replace(this.requireRegex, '$3'), titles);
				}
			}

			return titles;
		},

		/**
		 * Pushes titleString and titleString.css onto titleArray
		 *
		 * @param string title
		 * @param array titles
		 *
		 * @return array
		 */
		titlePush: function(titleString, titleArray)
		{
			titleArray.push(titleString);

			if (!titleString.match(/\.css$/))
			{
				titleArray.push(titleString + '.css');
			}

			return titleArray;
		},

		/**
		 * Returns (and creates if necessary) the wrapper for the textarea
		 *
		 * @return jQuery Textarea wrapper
		 */
		getTextareaWrapper: function()
		{
			if (this._$textareaWrapper === undefined)
			{
				/*
				 * @todo:
				 * need to get rid of the margin on the textarea and add it to the wrapper,
				 * get the width of the textarea and apply it to the wrapper,
				 * move the inline styling to a stylesheet,
				 * investigate scrollIntoView vs setting scrollTo in order to make the other editors appear from outside of the overflowed area
				 */

				this.$templateTextarea.wrap('<div id="textareaWrapper" style="position:relative"></div>');

				this._$textareaWrapper = $('#textareaWrapper')
					.width(this.$templateTextarea.width());
			}
			else
			{
			}

			return this._$textareaWrapper;
		},

		/**
		 * Returns an indicator to be inserted into tabs to show that a template contains unsaved changes
		 *
		 * @return jQuery
		 */
		createChangeIndicator: function()
		{
			return $(document.createElement('span'))
				.html('&bull;')
				.css('visibility', 'hidden')
				.addClass('changeIndicator');
		},

		/**
		 * Alters various properties of tabs (etc) to show that the contents of an editor is or is not changed from its initial (saved) value
		 *
		 * @param object $element
		 * @param boolean changed
		 *
		 * @return boolean true
		 */
		setChanged: function($element, changed)
		{
			if ($element.attr('changed') != changed)
			{
				$element.attr('changed', changed);
				$element.css('visibility', (changed ? 'visible' : 'hidden'));
				$element.parent().css('color', (changed ? 'darkred' : 'inherit'));
			}
			return changed;
		},

		/**
		 * Handles a click on a tab, switching the active editor
		 * @param e
		 * @return
		 */
		switchEditor: function(e)
		{
			var $target = $(e.target).closest('a'),
				editor;

			// switch the active tab
			$target.closest('li')
				.addClass('active')
				.siblings().removeClass('active');

			// switch the active editor
			$('textarea', this.getTextareaWrapper())
				.xfHide();

			editor = this.editors[$target.attr('templateTitle')];

			editor.$textarea
				.xfShow()
				.focus();

			return false;
		},

		/**
		 * Key-up handler for title textbox
		 *
		 * @param event e
		 */
		eTitleChange: function(e)
		{
			window.clearTimeout(this.titleChangeTimeout);

			this.titleChangeTimeout = window.setTimeout($.context(function() { this.handleTitleChange(); }, this), 500);
		},

		/**
		 * Updates the name of the first tab to reflect what the title input box says
		 */
		handleTitleChange: function()
		{
			var title = this.$templateTitle.strval();

			$('.tabText', this.$templateTab)
				.html((title || this.$form.data('untitled').italics()));
		},

		/**
		 * Key-up handler for editor textareas
		 *
		 * @param event e
		 */
		eTemplateChange: function(e)
		{
			window.clearTimeout(this.templateChangeTimeout);

			var templateTitleClosure = $(e.target).attr('templateTitle');

			this.templateChangeTimeout = window.setTimeout($.context(function() { this.handleTemplateChange(templateTitleClosure); }, this), 500);
		},

		/**
		 * Returns whether or not the template specified is changed from its state at load
		 *
		 * @param string Template title
		 *
		 * @return boolean
		 */
		isChanged: function(templateTitle)
		{
			var editorValue = this.editors[templateTitle].$textarea.strval().replace(/\r/g, ''),
				defaultValue = this.templateData[templateTitle].template.replace(/\r/g, '');

			return (editorValue != defaultValue);
		},

		/**
		 * Checks that the specified template is the primary template being edited
		 *
		 * @param string Template title
		 *
		 * @return boolean
		 */
		isPrimaryTemplate: function(templateTitle)
		{
			return (templateTitle == this.$titleOriginal.strval());
		},

		/**
		 * Checks to see if an extra editor's value has changed from its default value
		 * and updates the change indicator if it has
		 *
		 * @param string templateTitle
		 *
		 * @return boolean
		 */
		handleTemplateChange: function(templateTitle)
		{
			var changed = this.isChanged(templateTitle);

			this.setChanged(this.editors[templateTitle].$changeIndicator, changed);

			return changed;
		},

		/**
		 * Gets the URL to load via AJAX for required templates
		 *
		 * @param string Request type
		 *
		 * @return string URL
		 */
		getLoadUrl: function(reqType)
		{
			return this.$form.data('loadurl') + (reqType ? ('.' + reqType) : '');
		},

		/**
		 * Gets the URL to save via AJAX for altered templates
		 *
		 * @param string Request type
		 *
		 * @return string
		 */
		getSaveUrl: function(reqType)
		{
			return this.$form.data('saveurl') + (reqType ? ('.' + reqType) : '');
		}
	};

	// *********************************************************************

	XenForo.register('form#templateEditor', 'XenForo.TemplateEditor');

}
(jQuery, this, document);