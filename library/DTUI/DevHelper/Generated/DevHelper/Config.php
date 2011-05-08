<?php
class DTUI_DevHelper_Config extends DevHelper_Config_Base {
	protected $_dataClasses = array(
		'category' => array(
			'name' => 'category',
			'camelCase' => 'Category',
			'camelCaseWSpace' => 'Category',
			'fields' => array(
				'category_id' => array('name' => 'category_id', 'type' => 'uint', 'autoIncrement' => true),
				'category_name' => array('name' => 'category_name', 'type' => 'string', 'length' => 255, 'required' => true),
				'category_description' => array('name' => 'category_description', 'type' => 'string'),
				'category_options' => array('name' => 'category_options', 'type' => 'serialized')
			),
			'id_field' => 'category_id',
			'title_field' => 'category_name',
			'primaryKey' => array('category_id'),
			'indeces' => array(),
			'files' => array(
				'data_writer' => array('className' => 'DTUI_DataWriter_Category', 'hash' => 'abf30b333745a0c9a45383e58db292f9'),
				'model' => array('className' => 'DTUI_Model_Category', 'hash' => 'd9f5e3c133c5d1deae066002888ea43d'),
				'route_prefix_admin' => array('className' => 'DTUI_Route_PrefixAdmin_Category', 'hash' => '2d31a24070772351003076a4b536ff08'),
				'controller_admin' => array('className' => 'DTUI_ControllerAdmin_Category', 'hash' => 'd8f3fbee765113b31f4f7ed409f2708c')
			)
		),
		'item' => array(
			'name' => 'item',
			'camelCase' => 'Item',
			'camelCaseWSpace' => 'Item',
			'fields' => array(
				'item_id' => array('name' => 'item_id', 'type' => 'uint', 'autoIncrement' => true),
				'item_name' => array('name' => 'item_name', 'type' => 'string', 'length' => 255, 'required' => true),
				'item_description' => array('name' => 'item_description', 'type' => 'string'),
				'category_id' => array('name' => 'category_id', 'type' => 'uint', 'required' => true),
				'price' => array('name' => 'price', 'type' => 'float', 'required' => true),
				'item_options' => array('name' => 'item_options', 'type' => 'serialized'),
				'item_order_count' => array('name' => 'item_order_count', 'type' => 'uint', 'required' => true, 'default' => 0),
				'last_update_date' => array('name' => 'uint', 'type' => 'uint', 'required' => true)
			),
			'id_field' => 'item_id',
			'title_field' => 'item_name',
			'primaryKey' => array('item_id'),
			'indeces' => array(
				'category_id' => array('name' => 'category_id', 'fields' => array('category_id'), 'type' => 'NORMAL')
			),
			'files' => array(
				'data_writer' => array('className' => 'DTUI_DataWriter_Item', 'hash' => 'a877a96dd40b630f3635a6a3bd2801b8'),
				'model' => array('className' => 'DTUI_Model_Item', 'hash' => '48af7666e0a4ec6f22a37a0326e0e914'),
				'route_prefix_admin' => array('className' => 'DTUI_Route_PrefixAdmin_Item', 'hash' => '3a079a8ddf77e697a2f608f2e0d09839'),
				'controller_admin' => array('className' => 'DTUI_ControllerAdmin_Item', 'hash' => '421bb6d479c199ac5658ea307e9250fa')
			)
		),
		'table' => array(
			'name' => 'table',
			'camelCase' => 'Table',
			'camelCaseWSpace' => 'Table',
			'fields' => array(
				'table_id' => array('name' => 'table_id', 'type' => 'uint', 'autoIncrement' => true),
				'table_name' => array('name' => 'table_name', 'type' => 'string', 'length' => 255, 'required' => true),
				'table_description' => array('name' => 'table_description', 'type' => 'string'),
				'is_busy' => array('name' => 'is_busy', 'type' => 'boolean', 'required' => true, 'default' => 0),
				'last_order_id' => array('name' => 'last_order_id', 'type' => 'uint', 'required' => true, 'default' => 0),
				'table_order_count' => array('name' => 'table_order_count', 'type' => 'uint', 'required' => true, 'default' => 0)
			),
			'id_field' => 'table_id',
			'title_field' => 'table_name',
			'primaryKey' => array('table_id'),
			'indeces' => array(),
			'files' => array(
				'data_writer' => array('className' => 'DTUI_DataWriter_Table', 'hash' => 'caa5a549eb9f62aec913f09f13e206b7'),
				'model' => array('className' => 'DTUI_Model_Table', 'hash' => 'c4eb0ea07d5a4a931bfa573bdd3f97ba'),
				'route_prefix_admin' => array('className' => 'DTUI_Route_PrefixAdmin_Table', 'hash' => 'fa704fb17f7952dcaa4fd66470abf93c'),
				'controller_admin' => array('className' => 'DTUI_ControllerAdmin_Table', 'hash' => '00327a070e8a8d5ff23e25da56fbc0ec')
			)
		),
		'order' => array(
			'name' => 'order',
			'camelCase' => 'Order',
			'camelCaseWSpace' => 'Order',
			'fields' => array(
				'order_id' => array('name' => 'order_id', 'type' => 'uint', 'autoIncrement' => true),
				'table_id' => array('name' => 'table_id', 'type' => 'uint', 'required' => true),
				'order_date' => array('name' => 'order_date', 'type' => 'uint', 'required' => true),
				'is_paid' => array('name' => 'is_paid', 'type' => 'boolean', 'required' => true, 'default' => 0),
				'paid_amount' => array('name' => 'paid_amount', 'type' => 'float', 'required' => true, 'default' => 0)
			),
			'id_field' => 'order_id',
			'title_field' => false,
			'primaryKey' => array('order_id'),
			'indeces' => array(),
			'files' => array(
				'data_writer' => array('className' => 'DTUI_DataWriter_Order', 'hash' => 'ce0ff5c945dd220ecb4d52abdc56474f'),
				'model' => array('className' => 'DTUI_Model_Order', 'hash' => 'c260e4d2934e4f5e8555ea7658a0e903'),
				'route_prefix_admin' => array('className' => 'DTUI_Route_PrefixAdmin_Order', 'hash' => '70c80940aaac6e5819c0c74164982902'),
				'controller_admin' => array('className' => 'DTUI_ControllerAdmin_Order', 'hash' => '6887dd909039c22e41a8b27a02b83f49')
			)
		),
		'order_item' => array(
			'name' => 'order_item',
			'camelCase' => 'OrderItem',
			'camelCaseWSpace' => 'Order Item',
			'fields' => array(
				'order_item_id' => array('name' => 'order_item_id', 'type' => 'uint', 'autoIncrement' => true),
				'order_id' => array('name' => 'order_id', 'type' => 'uint', 'required' => true),
				'trigger_user_id' => array('name' => 'trigger_user_id', 'type' => 'uint', 'required' => true),
				'target_user_id' => array('name' => 'target_user_id', 'type' => 'uint', 'required' => true),
				'item_id' => array('name' => 'item_id', 'type' => 'uint', 'required' => true),
				'order_item_date' => array('name' => 'order_item_date', 'type' => 'uint', 'required' => true),
				'status' => array(
					'name' => 'status',
					'type' => 'string',
					'allowedValues' => array('waiting', 'served', 'paid'),
					'required' => true
				)
			),
			'id_field' => 'order_item_id',
			'title_field' => 'status',
			'primaryKey' => array('order_item_id'),
			'indeces' => array(
				'order_id' => array('name' => 'order_id', 'fields' => array('order_id'), 'type' => 'NORMAL'),
				'target_user_id' => array('name' => 'target_user_id', 'fields' => array('target_user_id'), 'type' => 'NORMAL')
			),
			'files' => array(
				'data_writer' => array('className' => 'DTUI_DataWriter_OrderItem', 'hash' => '11b1a93d7183f4e8969e5abb94fa0c8f'),
				'model' => array('className' => 'DTUI_Model_OrderItem', 'hash' => '8fe63c47cc8d4773a6a079f173f29e02'),
				'route_prefix_admin' => false,
				'controller_admin' => false
			)
		)
	);
	protected $_dataPatches = array();
	protected $_exportPath = 'C:\\xampp\\htdocs\\dtui\\addons\\DTUI';

	/**
	 * Return false to trigger the upgrade!
	 * common use methods:
	 * 	public function addDataClass($name, $fields = array(), $primaryKey = false, $indeces = array())
	 *	public function addDataPatch($table, array $field)
	 *	public function setExportPath($path)
	**/
	protected function _upgrade() {
		return true; // remove this line to trigger update

		/*
		$this->addDataClass(
			'name_here',
			array( // fields
				'field_here' => array(
					'type' => 'type_here',
					// 'length' => 'length_here',
					// 'required' => true,
					// 'allowedValues' => array('value_1', 'value_2'), 
				),
				// other fields go here
			),
			'primary_key_field_here',
			array( // indeces
				array(
					'fields' => array('field_1', 'field_2'),
					'type' => 'NORMAL', // UNIQUE or FULLTEXT
				),
			),
		);
		*/
	}
}