<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/exceptions.php';

abstract class LookupEntity
{
	protected const TABLE = '';
	protected const ID_COLUMN = '';
	protected const LABEL = 'Lookup';

	public static function all(): array
	{
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->query('SELECT ' . static::ID_COLUMN . ', name FROM ' . static::TABLE . ' ORDER BY name');
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function find($id): array
	{
		$id = self::validateId($id);
		$stmt = Database::getInstance()->getConnection()->prepare(
			'SELECT ' . static::ID_COLUMN . ', name FROM ' . static::TABLE . ' WHERE ' . static::ID_COLUMN . ' = ?'
		);
		$stmt->execute([$id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new NotFoundException(static::LABEL . ' not found.');
		}
		return $row;
	}

	public static function create($name): int
	{
		$name = self::validateName($name);
		$pdo = Database::getInstance()->getConnection();
		self::ensureNameAvailable($pdo, $name);
		$stmt = $pdo->prepare('INSERT INTO ' . static::TABLE . ' (name) VALUES (?)');
		try {
			$stmt->execute([$name]);
		} catch (PDOException $e) {
			if ($e->getCode() === '23000') {
				throw new ValidationException(static::LABEL . ' already exists.');
			}
			throw $e;
		}
		return (int) $pdo->lastInsertId();
	}

	public static function update($id, $name): void
	{
		$id = self::validateId($id);
		$name = self::validateName($name);
		self::find($id);
		$pdo = Database::getInstance()->getConnection();
		self::ensureNameAvailable($pdo, $name, $id);
		$stmt = $pdo->prepare(
			'UPDATE ' . static::TABLE . ' SET name = ? WHERE ' . static::ID_COLUMN . ' = ?'
		);
		try {
			$stmt->execute([$name, $id]);
		} catch (PDOException $e) {
			if ($e->getCode() === '23000') {
				throw new ValidationException(static::LABEL . ' already exists.');
			}
			throw $e;
		}
	}

	public static function delete($id): void
	{
		$id = self::validateId($id);
		self::find($id);
		$pdo = Database::getInstance()->getConnection();
		$stmt = $pdo->prepare('DELETE FROM ' . static::TABLE . ' WHERE ' . static::ID_COLUMN . ' = ?');
		try {
			$stmt->execute([$id]);
		} catch (PDOException $e) {
			if ($e->getCode() === '23000') {
				throw new BusinessRuleException(static::LABEL . ' is used by existing books and cannot be deleted.');
			}
			throw $e;
		}
	}

	private static function validateName($value): string
	{
		if (!is_scalar($value)) {
			throw new ValidationException(static::LABEL . ' name must be text.');
		}
		$name = trim((string) $value);
		if ($name === '' || strlen($name) > 150) {
			throw new ValidationException(static::LABEL . ' name is required and must be 150 characters or fewer.');
		}
		return $name;
	}

	private static function validateId($value): int
	{
		$id = is_scalar($value) ? filter_var(trim((string) $value), FILTER_VALIDATE_INT) : false;
		if ($id === false || $id < 1) {
			throw new ValidationException('Invalid ' . strtolower(static::LABEL) . ' ID.');
		}
		return (int) $id;
	}

	private static function ensureNameAvailable(PDO $pdo, string $name, ?int $ignoreId = null): void
	{
		$sql = 'SELECT 1 FROM ' . static::TABLE . ' WHERE name = ?';
		$params = [$name];
		if ($ignoreId !== null) {
			$sql .= ' AND ' . static::ID_COLUMN . ' <> ?';
			$params[] = $ignoreId;
		}
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		if ($stmt->fetchColumn() !== false) {
			throw new ValidationException(static::LABEL . ' already exists.');
		}
	}
}