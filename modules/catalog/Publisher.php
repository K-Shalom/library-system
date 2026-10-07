<?php
/**
 * File: Publisher.php
 * Module: Catalog
 * Assigned to: Augustin Mugisha
 * Status: DONE
 * Description: Publisher lookup OOP model
 */

require_once __DIR__ . '/LookupEntity.php';

class Publisher extends LookupEntity
{
	protected const TABLE = 'publishers';
	protected const ID_COLUMN = 'publisher_id';
	protected const LABEL = 'Publisher';
}
