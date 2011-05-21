/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {

	XenForo.DTUI_NewOrderItemListener = function($element) { this.__construct($element); };
	XenForo.DTUI_NewOrderItemListener.prototype = {
		__construct: function($element) {
			$element.one('change', $.context(this, 'createChoice'));

			this.$element = $element;
			if (!this.$base) {
				this.$base = $element.clone();
			}
		},

		createChoice: function() {
			var $new = this.$base.clone(),
				nextCounter = this.$element.parent().children().length;

			$new.find('input[name], select[name]').each(function() {
				var $this = $(this);
				$this.attr('name', $this.attr('name').replace(/\[(\d+)\]/, '[' + nextCounter + ']'));
			});
			
			$new.find('*[id]').each(function() {
				var $this = $(this);
				$this.removeAttr('id');
				XenForo.uniqueId($this);

				if (XenForo.formCtrl) {
					XenForo.formCtrl.clean($this);
				}
			});

			$new.xfInsert('insertAfter', this.$element);

			this.__construct($new);
		}
	};

	// *********************************************************************
	
	XenForo.DTUI_NewOrderForm = function($form) { this.__construct($form); };
	XenForo.DTUI_NewOrderForm.prototype = {
		__construct: function($form) {
			this.$form = $form;

			$form.bind('AutoValidationComplete', $.context(this, 'AutoValidationComplete'))
		},
		
		AutoValidationComplete: function(e) {
			if (e.ajaxData && e.ajaxData.order && e.ajaxData.order.order_id > 0) {
				e.ajaxData.message = this.$form.data('successmessage');
			}
		}
	};

	// *********************************************************************

	XenForo.register('dl.DTUI_NewOrderItemListener', 'XenForo.DTUI_NewOrderItemListener');
	XenForo.register('form.DTUI_NewOrderForm', 'XenForo.DTUI_NewOrderForm');
	

}
(jQuery, this, document);