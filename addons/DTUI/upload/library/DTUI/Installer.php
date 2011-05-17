<?php
class DTUI_Installer {
	protected static $_tables = array(
		'category' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_dtui_category` (
				`category_id` INT(10) UNSIGNED AUTO_INCREMENT
				,`category_name` VARCHAR(255) NOT NULL
				,`category_description` TEXT
				,`category_options` MEDIUMBLOB
				, PRIMARY KEY (`category_id`)
				
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => false
		),
		'item' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_dtui_item` (
				`item_id` INT(10) UNSIGNED AUTO_INCREMENT
				,`item_name` VARCHAR(255) NOT NULL
				,`item_description` TEXT
				,`category_id` INT(10) UNSIGNED NOT NULL
				,`price` FLOAT NOT NULL
				,`item_options` MEDIUMBLOB
				,`item_order_count` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
				, PRIMARY KEY (`item_id`)
				, INDEX `category_id` (`category_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => false
		),
		'table' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_dtui_table` (
				`table_id` INT(10) UNSIGNED AUTO_INCREMENT
				,`table_name` VARCHAR(255) NOT NULL
				,`table_description` TEXT
				,`is_busy` TINYINT(4) UNSIGNED NOT NULL DEFAULT \'0\'
				,`last_order_id` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
				,`table_order_count` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
				, PRIMARY KEY (`table_id`)
				
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => false
		),
		'order' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_dtui_order` (
				`order_id` INT(10) UNSIGNED AUTO_INCREMENT
				,`table_id` INT(10) UNSIGNED NOT NULL
				,`order_date` INT(10) UNSIGNED NOT NULL
				,`is_paid` TINYINT(4) UNSIGNED NOT NULL DEFAULT \'0\'
				,`paid_amount` FLOAT NOT NULL DEFAULT \'0\'
				, PRIMARY KEY (`order_id`)
				
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => false
		),
		'order_item' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_dtui_order_item` (
				`order_item_id` INT(10) UNSIGNED AUTO_INCREMENT
				,`order_id` INT(10) UNSIGNED NOT NULL
				,`trigger_user_id` INT(10) UNSIGNED NOT NULL
				,`target_user_id` INT(10) UNSIGNED NOT NULL
				,`item_id` INT(10) UNSIGNED NOT NULL
				,`order_item_date` INT(10) UNSIGNED NOT NULL
				,`status` ENUM(\'waiting\', \'prepared\', \'served\', \'paid\') NOT NULL
				, PRIMARY KEY (`order_item_id`)
				, INDEX `order_id` (`order_id`)
				, INDEX `target_user_id` (`target_user_id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => false
		)
	);
	protected static $_patches = array();

	public static function install() {
		$db = XenForo_Application::get('db');

		foreach (self::$_tables as $table) {
			$db->query($table['createQuery']);
		}
		
		foreach (self::$_patches as $patch) {
			$existed = $db->fetchOne($patch['showColumnsQuery']);
			if (empty($existed)) {
				$db->query($patch['alterTableAddColumnQuery']);
			}
		}
	}
	
	public static function uninstall() {
		// TODO
	}
}