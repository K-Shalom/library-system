<?php
/**
 * File: Author.php
 * Module: Catalog
 * Assigned to: Augustin Mugisha
 * Status: DONE
 * Description: Author lookup OOP model
 */

require_once __DIR__ . '/LookupEntity.php';

class Author extends LookupEntity
{
	protected const TABLE = 'authors';
	protected const ID_COLUMN = 'author_id';
	protected const LABEL = 'Author';
}
