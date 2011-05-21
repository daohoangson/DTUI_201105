<?php
class DTUI_ViewPublic_EntryPoint_OrderNew extends XenForo_ViewPublic_Base {
	public function renderJson() {
		$this->_params['itemsGrouped'] = array();
		foreach ($this->_params['items'] as $item) {
			$this->_params['itemsGrouped'][$item['category_name']][$item['item_id']] = $item;
		}
	}
}