/**
 * @author kier
 */

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	/**
	 * Avatar Editor
	 *
	 * @param jQuery $a .AvatarEditor
	 */
	XenForo.AvatarEditor = function($editor) { this.__construct($editor); };
	XenForo.AvatarEditor.prototype =
	{
		__construct: function($editor)
		{
			this.$form = $editor.bind(
			{
				submit:                   $.context(this, 'saveChanges'),
				reset:                    $.context(this, 'resetForm'),
				AutoInlineUploadComplete: $.context(this, 'uploadComplete') // catch the event of a new avatar being successfully uploaded
			});

			this.$cropObj = $editor.find('.AvatarCropControl').bind(
			{
				dragstart: $.context(this, 'dragStart'),
				dragend:   $.context(this, 'dragEnd'),
				drag:      $.context(this, 'drag')
			});

			this.$cropImg = this.$cropObj.find('img').load($.context(this, 'imageLoaded'));

			// This is a failsafe, as image.load (above) only fires if the image is not already cached
			$(window).load($.context(this, 'imageLoaded'));

			this.$form.find('#GravatarTest').click($.context(this, 'gravatarTest'));

			// fields to store the cropx and cropy data
			this.$outputX = $editor.find('input[name=avatar_crop_x]');
			this.$outputY = $editor.find('input[name=avatar_crop_y]');

			// hide crop / delete controls if we don't have a custom avatar
			this.setCropFormVisibility($editor.find('input[name=avatar_date]').val());

			this.cropX = this.$outputX.val();
			this.cropY = this.$outputY.val();
		},

		/**
		 * Gets the positions and dimensions necessary for dragging to function
		 */
		getPositions: function()
		{
			// dimensions of the crop control container box
			this.objSizeX = this.$cropObj.innerWidth();
			this.objSizeY = this.$cropObj.innerHeight();

			// dimensions of the image within the crop container
			this.imageSizeX = this.$cropImg.outerWidth();
			this.imageSizeY = this.$cropImg.outerHeight();


			this.deltaX = (this.imageSizeX - this.objSizeX) * -1;
			this.deltaY = (this.imageSizeY - this.objSizeY) * -1;

			this.imagePos = this.$cropImg.position();

			this.objOffset = this.$cropObj.offset();
		},

		/**
		 * Fires when the crop image has finished loading, sets its crop position
		 *
		 * @param event e
		 */
		imageLoaded: function(e)
		{
			if (!this.positionSet)
			{
				this.getPositions();

				this.setPosition(this.$outputX.val() * -1, this.$outputY.val() * -1, false);

				this.positionSet = true;
			}
		},

		/**
		 * Sets the position of the crop image within the crop control
		 *
		 * @param integer X coordinate of crop start
		 * @param integer Y coordinate of crop start
		 * @param boolean Check that the image remains within container constraints (use when dragging)
		 */
		setPosition: function(x, y, checkDelta)
		{
			// constraints: crop must be from within image boundaries
			if (x > 0)
			{
				x = 0;
			}
			else if (checkDelta && x < this.deltaX)
			{
				x = this.deltaX;
			}

			if (y > 0)
			{
				y = 0;
			}
			else if (checkDelta && y < this.deltaY)
			{
				y = this.deltaY;
			}

			// reposition the crop image
			this.$cropImg.css({ left: x, top: y });
		},

		/**
		 * Fires when dragging starts. Gets the positions of the crop image etc.
		 *
		 * @param event e
		 */
		dragStart: function(e)
		{
			if (!this.positionSet)
			{
				this.imageLoaded();
			}

			this.getPositions();
		},

		/**
		 * Fires repeatedly during dragging. Repositions the crop image.
		 *
		 * @param event e
		 */
		drag: function(e)
		{
			this.setPosition(
				e.offsetX - this.objOffset.left + this.imagePos.left,
				e.offsetY - this.objOffset.top + this.imagePos.top,
				true
			);
		},

		/**
		 * Fires when dragging stops. Updates the hidden cropx/cropy input fields
		 *
		 * @param event e
		 */
		dragEnd: function(e)
		{
			var imagePos = this.$cropImg.position();

			this.$outputX.val(imagePos.left * -1);
			this.$outputY.val(imagePos.top * -1);

			console.info('Avatar crop dragged to %d, %d %o', this.$outputX.val(), this.$outputY.val(), this.$cropObj);
		},

		/**
		 * Fires when the upload iframe is loaded.
		 * Updates the editor with the details from e.ajaxData
		 *
		 * @param event e From XenForo.AutoInlineUploader -> AutoInlineUploadComplete
		 */
		uploadComplete: function(e)
		{
			this.updateEditor(e.ajaxData);
		},

		/**
		 * Updates the editor with new details contained in ajaxData
		 *
		 * @param object ajaxData
		 */
		updateEditor: function(ajaxData)
		{
			console.info('Update Avatar Editor %o', ajaxData);

			XenForo.updateUserAvatars(ajaxData.user_id, ajaxData.urls, $('#ctrl_useGravatar_0').is(':checked'));

			$('.avatarCropper .Av' + ajaxData.user_id + 'l img').css(ajaxData.cropCss);

			// show or hide the crop form
			this.setCropFormVisibility(ajaxData.avatar_date);

			// reset the dimensions of the crop-drag control
			this.$cropImg.css({ width: 'auto', height: 'auto' });
			this.$cropImg.css(ajaxData.maxDimension, ajaxData.maxWidth);

			// reset the crop position
			this.cropX = ajaxData.cropX * -1;
			this.cropY = ajaxData.cropY * -1;

			this.$outputX.val(ajaxData.cropX);
			this.$outputY.val(ajaxData.cropY);

			this.setPosition(this.cropX, this.cropY, false);
		},

		/**
		 * Show or hide the modification form for avatars,
		 * depending on whether or not an avatar exists.
		 *
		 * @param integer avatarDate
		 */
		setCropFormVisibility: function(avatarDate)
		{
			if (parseInt(avatarDate, 10))
			{
				this.$form.find('#DeleteAvatar').removeAttr('checked');
				$('label[for=DeleteAvatar], #ExistingCustom').xfFadeIn(XenForo.speed.normal);
			}
			else
			{
				$('label[for=DeleteAvatar], #ExistingCustom').hide();
			}
		},

		/**
		 * Intercepts submit events from the non-upload form and submits with AJAX
		 *
		 * @param event e
		 *
		 * @return boolean false
		 */
		saveChanges: function(e)
		{
			if (this.$form.find('input[name=_xfUploader]').length)
			{
				return true;
			}

			e.preventDefault();

			XenForo.ajax(
				this.$form.attr('action'),
				this.$form.serializeArray(),
				$.context(this, 'saveChangesSuccess')
			);
		},

		/**
		 * Receives the success event from saveChanges().
		 * Updates the editor with the new info.
		 *
		 * @param object ajaxData
		 * @param string textStatus
		 */
		saveChangesSuccess: function(ajaxData, textStatus)
		{
			if (XenForo.hasResponseError(ajaxData))
			{
				return false;
			}

			this.updateEditor(ajaxData);

			var overlay;
			if (overlay = this.$form.closest('.xenOverlay').data('overlay'))
			{
				overlay.close();
			}
		},

		/**
		 * Resets the crop position to the last received from ajaxData.
		 *
		 * @param event e
		 */
		resetForm: function(e)
		{
			this.setPosition(this.cropX * -1, this.cropY * -1, false);
		},

		gravatarTest: function(e)
		{
			var $testButton = $(e.target),
				$testSrc = $($testButton.data('testSrc')),
				$testImg = $($testButton.data('testImg')),
				$testErr = $($testButton.data('testErr')),
				testUrl = $testButton.data('testUrl'),
				email = $testSrc.val(),
				maxWidth = this.$form.data('maxWidth');

			if ($testSrc.data('XenForo.Prompt'))
			{
				email = $testSrc.data('XenForo.Prompt').val();
			}


			if (!email) // empty is valid
			{
				$testErr.slideUp(XenForo.speed.fast);
				return true;
			}
			else if (email.length < 5) // email addresses really can't be that short
			{
				return false;
			}

			$testButton.attr('disabled', true);

			XenForo.ajax(
				$testButton.data('testUrl'),
				{
					email: email,
					size: maxWidth
				},
				function(ajaxData, textStatus)
				{
					$testButton.removeAttr('disabled');

					if (typeof ajaxData == 'object')
					{
						if (ajaxData.error)
						{
							$testErr.hide().html(ajaxData.error[0]).xfFadeDown(XenForo.speed.fast);
						}
						else
						{
							$testErr.slideUp(XenForo.speed.fast);
						}

						if (ajaxData.gravatarUrl)
						{
							$testImg.attr('src', ajaxData.gravatarUrl);
						}

						$testSrc.focus();
					}
				}
			);

		}
	};

	// *********************************************************************

	XenForo.register('.AvatarEditor', 'XenForo.AvatarEditor');

}
(jQuery, this, document);

/*
jquery.event.drag.js ~ v1.5 ~ Copyright (c) 2008, Three Dub Media (http://threedubmedia.com)
Liscensed under the MIT License ~ http://threedubmedia.googlecode.com/files/MIT-LICENSE.txt
*/
(function(E){E.fn.drag=function(L,K,J){if(K){this.bind("dragstart",L)}if(J){this.bind("dragend",J)}return !L?this.trigger("drag"):this.bind("drag",K?K:L)};var A=E.event,B=A.special,F=B.drag={not:":input",distance:0,which:1,dragging:false,setup:function(J){J=E.extend({distance:F.distance,which:F.which,not:F.not},J||{});J.distance=I(J.distance);A.add(this,"mousedown",H,J);if(this.attachEvent){this.attachEvent("ondragstart",D)}},teardown:function(){A.remove(this,"mousedown",H);if(this===F.dragging){F.dragging=F.proxy=false}G(this,true);if(this.detachEvent){this.detachEvent("ondragstart",D)}}};B.dragstart=B.dragend={setup:function(){},teardown:function(){}};function H(L){var K=this,J,M=L.data||{};if(M.elem){K=L.dragTarget=M.elem;L.dragProxy=F.proxy||K;L.cursorOffsetX=M.pageX-M.left;L.cursorOffsetY=M.pageY-M.top;L.offsetX=L.pageX-L.cursorOffsetX;L.offsetY=L.pageY-L.cursorOffsetY}else{if(F.dragging||(M.which>0&&L.which!=M.which)||E(L.target).is(M.not)){return }}switch(L.type){case"mousedown":E.extend(M,E(K).offset(),{elem:K,target:L.target,pageX:L.pageX,pageY:L.pageY});A.add(document,"mousemove mouseup",H,M);G(K,false);F.dragging=null;return false;case !F.dragging&&"mousemove":if(I(L.pageX-M.pageX)+I(L.pageY-M.pageY)<M.distance){break}L.target=M.target;J=C(L,"dragstart",K);if(J!==false){F.dragging=K;F.proxy=L.dragProxy=E(J||K)[0]}case"mousemove":if(F.dragging){J=C(L,"drag",K);if(B.drop){B.drop.allowed=(J!==false);B.drop.handler(L)}if(J!==false){break}L.type="mouseup"}case"mouseup":A.remove(document,"mousemove mouseup",H);if(F.dragging){if(B.drop){B.drop.handler(L)}C(L,"dragend",K)}G(K,true);F.dragging=F.proxy=M.elem=false;break}return true}function C(M,K,L){M.type=K;var J=E.event.handle.call(L,M);return J===false?false:J||M.result}function I(J){return Math.pow(J,2)}function D(){return(F.dragging===false)}function G(K,J){if(!K){return }K.unselectable=J?"off":"on";K.onselectstart=function(){return J};if(K.style){K.style.MozUserSelect=J?"":"none"}}})(jQuery);