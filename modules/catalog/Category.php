<?php
/**
 * File: Category.php
 * Module: Catalog
 * Assigned to: Augustin Mugisha
 * Status: DONE
 * Description: Book category OOP model
 */

require_once __DIR__ . '/LookupEntity.php';

class Category extends LookupEntity
{
	protected const TABLE = 'categories';
	protected const ID_COLUMN = 'category_id';
	protected const LABEL = 'Category';
}
