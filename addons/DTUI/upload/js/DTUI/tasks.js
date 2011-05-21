/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
	
	XenForo.DTUI_TasksForm = function($form) { this.__construct($form); };
	XenForo.DTUI_TasksForm.prototype = {
		__construct: function($form) {
			this.$form = $form;

			$form.bind('AutoValidationComplete', $.context(this, 'AutoValidationComplete'))
		},
		
		AutoValidationComplete: function(e) {
			if (e.ajaxData && e.ajaxData.orderItems) {
				var orderItems = e.ajaxData.orderItems;
				var foundAnOrderItem = false;

				for (var k in orderItems) {
					if (orderItems[k].order_item_id) {
						$('#task-' + orderItems[k].order_item_id).xfRemove();
						foundAnOrderItem = true;
					}
				}
				
				if (foundAnOrderItem) {
					XenForo.alert(this.$form.data('successmessage'), 'info', 2500);
					
					e.preventDefault();
				}
			}
		}
	};

	// *********************************************************************

	XenForo.register('form.DTUI_TasksForm', 'XenForo.DTUI_TasksForm');
	

}
(jQuery, this, document);