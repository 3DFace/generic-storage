<?php

namespace dface\GenericStorage\Mysql;

use dface\criteria\builder\SqlCriteriaBuilder;
use dface\criteria\node\Criteria;
use dface\GenericStorage\Generic\ArrayPathNavigator;
use dface\GenericStorage\Generic\GenericStorageError;
use dface\GenericStorage\Generic\InvalidDataType;
use dface\GenericStorage\Generic\ItemAlreadyExists;
use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Generic\UnexpectedRevision;
use dface\GenericStorage\Generic\UniqueConstraintViolation;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;
use dface\sql\placeholders\Formatter;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\Parser;
use dface\sql\placeholders\ParserException;

class MyJsonStorage
{

	public const COLUMN_MODE_KEEP_BODY = 0;
	private const COLUMN_MODE_SAVE_EXTRACT = 1;
	public const COLUMN_MODE_LOAD_FALLBACK = 2;
	public const COLUMN_MODE_SEPARATED = 3;

	private string $className;
	private string $tableNameEscaped;
	private ?string $idPropertyName;
	/** @var string[] */
	private array $idPropertyPath;
	private string $idColumnDef;
	private bool $idBin;
	private bool $idExtracted;
	/** @var string[] */
	private array $seqIdPropertyPath;
	private ?string $seqIdPropertyName;
	/** @var string[] */
	private array $revisionPropertyPath;
	/** @var string[] */
	private array $add_generated_columns;
	/** @var string[] */
	private array $add_columns;
	/** @var string[] */
	private array $add_columns_data;
	/** @var string[] */
	private array $add_indexes;
	private bool $has_unique_secondary;
	private bool $temporary;
	private SqlCriteriaBuilder $criteriaBuilder;
	private string $selectAllFromTable;
	private int $batchListSize;
	private int $idBatchSize;
	private string $dataColumnDef;
	private int $dataMaxSize;
	private bool $compressed;

	private Formatter $formatter;
	private Parser $parser;

	/**
	 * @param string $className
	 * @param string $tableName
	 * @param string|null $idPropertyName
	 * @param string $idColumnDef
	 * @param bool $idExtracted
	 * @param string|null $revisionPropertyName
	 * @param string|null $seqIdPropertyName
	 * @param array $add_generated_columns
	 * @param array $add_columns
	 * @param array $add_indexes
	 * @param bool $has_unique_secondary
	 * @param bool $temporary
	 * @param int $batch_list_size
	 * @param int $id_batch_size
	 * @param string $dataColumnDef
	 * @param int $dataMaxSize
	 * @param bool $compressed
	 */
	public function __construct(
		string $className,
		string $tableName,
		?string $idPropertyName = null,
		string $idColumnDef = 'BINARY(16)',
		bool $idExtracted = false,
		?string $revisionPropertyName = null,
		?string $seqIdPropertyName = null,
		array $add_generated_columns = [],
		array $add_columns = [],
		array $add_indexes = [],
		bool $has_unique_secondary = false,
		bool $temporary = false,
		int $batch_list_size = 10000,
		int $id_batch_size = 500,
		string $dataColumnDef = 'TEXT',
		int $dataMaxSize = 65535,
		bool $compressed = true
	) {
		$this->formatter = new DefaultFormatter();
		$this->parser = new DefaultParser();
		$this->className = $className;
		$this->tableNameEscaped = \str_replace('`', '``', $tableName);
		$this->idPropertyName = $idPropertyName;
		$this->idPropertyPath = $idPropertyName !== null ? \explode('/', $idPropertyName) : [];
		$this->idColumnDef = $idColumnDef;
		$this->idExtracted = $idExtracted;
		$this->idBin = \stripos($idColumnDef, 'binary') !== false;
		$this->revisionPropertyPath = $revisionPropertyName !== null ? \explode('/', $revisionPropertyName) : [];
		$this->seqIdPropertyName = $seqIdPropertyName;
		$this->seqIdPropertyPath = $seqIdPropertyName !== null ? \explode('/', $seqIdPropertyName) : [];
		$this->add_generated_columns = $add_generated_columns;
		$this->add_columns = $add_columns;
		$this->add_columns_data = [];

		$add_select_str = [];
		foreach ($this->add_columns as $i => $x) {
			$col = [
				'escaped' => \str_replace('`', '``', $i),
				'path' => \explode('/', $i),
			];
			if (\is_array($x)) {
				if (!isset($x['type'])) {
					throw new \InvalidArgumentException("Column '$i' has no type def");
				}
				$col['mode'] = $x['mode'] ?? self::COLUMN_MODE_KEEP_BODY;
				$col['type'] = $x['type'];
				$col['default'] = $x['default'] ?? null;
			} else {
				$col['type'] = $x;
				$col['mode'] = self::COLUMN_MODE_KEEP_BODY;
				$col['default'] = null;
			}
			$this->add_columns_data[$i] = $col;
			if ($col['mode'] & self::COLUMN_MODE_LOAD_FALLBACK) {
				$e_col = '`'.$col['escaped'].'`';
				$add_select_str[] = $e_col;
			}
		}
		$loadColumnsFragment = $add_select_str ? (', '.\implode(', ', $add_select_str)) : '';

		$this->add_indexes = $add_indexes;

		$this->temporary = $temporary;
		$this->has_unique_secondary = $has_unique_secondary;
		$this->criteriaBuilder = new SqlCriteriaBuilder();
		$idSelector = '`$id`';
		if ($this->idBin) {
			$idSelector = "LOWER(HEX($idSelector))";
		}
		$this->selectAllFromTable = "SELECT `\$seq_id`, $idSelector as `\$id`, `\$data`, `\$revision` $loadColumnsFragment FROM `$this->tableNameEscaped`";
		if ($batch_list_size < 0) {
			throw new \InvalidArgumentException("Batch list size must be >=0, $batch_list_size given");
		}
		$this->batchListSize = $batch_list_size;
		if ($id_batch_size < 1) {
			throw new \InvalidArgumentException("Id batch size must be >0, $id_batch_size given");
		}
		$this->idBatchSize = $id_batch_size;
		$this->dataMaxSize = $dataMaxSize;
		$this->dataColumnDef = $dataColumnDef;
		$this->compressed = $compressed;
	}

	/**
	 * @param MyLink $link
	 * @param $id
	 * @return \JsonSerializable|null
	 * @throws UnderlyingStorageError
	 * @throws \JsonException
	 */
	public function getItem(MyLink $link, $id) : ?\JsonSerializable
	{
		$e_id_quoted = '\''.$link->escapeString($id).'\'';
		if ($this->idBin) {
			$e_id_quoted = "UNHEX($e_id_quoted)";
		}
		/** @noinspection SqlResolve */
		$res = $link->query("$this->selectAllFromTable WHERE `\$id`=$e_id_quoted");
		$rec = $res->fetchAssoc();
		return $rec ? $this->deserialize($rec) : null;
	}

	/**
	 * @param MyLink $link
	 * @param iterable $ids
	 * @return iterable
	 * @throws UnderlyingStorageError
	 * @throws \JsonException
	 */
	public function getItems(MyLink $link, iterable $ids) : iterable
	{
		$sub_list = [];
		foreach ($ids as $id) {
			if (\count($sub_list) === $this->idBatchSize) {
				$where = ' WHERE `$id` IN ('.\implode(',', $sub_list).')';
				$node = "$this->selectAllFromTable $where";
				yield from $this->iterateOverDecoded($link, $node, [], 0);
				$sub_list = [];
			}
			$e_id_quoted = '\''.$link->escapeString($id).'\'';
			if ($this->idBin) {
				$e_id_quoted = "UNHEX($e_id_quoted)";
			}
			$sub_list[] = $e_id_quoted;
		}
		if ($sub_list) {
			$where = ' WHERE `$id` IN ('.\implode(',', $sub_list).')';
			$node = "$this->selectAllFromTable $where";
			yield from $this->iterateOverDecoded($link, $node, [], 0);
		}
	}

	/**
	 * @param MyLink $link
	 * @param $id
	 * @param \JsonSerializable $item
	 * @param int|null $expectedRevision
	 * @param bool $idempotency
	 * @throws GenericStorageError
	 * @throws InvalidDataType
	 * @throws ItemAlreadyExists
	 * @throws UnderlyingStorageError
	 * @throws UnexpectedRevision
	 * @throws UniqueConstraintViolation
	 * @throws \JsonException
	 */
	public function saveItem(
		MyLink $link,
		$id,
		\JsonSerializable $item,
		int $expectedRevision = null,
		bool $idempotency = false
	) : void {

		if (!$item instanceof $this->className) {
			throw new InvalidDataType("Stored item must be instance of $this->className");
		}

		$arr = (array)$item->jsonSerialize();
		$full_arr = $arr;
		$add_column_set_node = $this->createUpdateColumnsFragment($link, $arr);
		$add_column_set_node = $add_column_set_node ? (', '.$add_column_set_node) : '';
		if ($this->idPropertyPath && $this->idExtracted) {
			ArrayPathNavigator::extractProperty($arr, $this->idPropertyPath);
		}
		if ($this->revisionPropertyPath) {
			ArrayPathNavigator::extractProperty($arr, $this->revisionPropertyPath);
		}
		if ($this->seqIdPropertyPath) {
			ArrayPathNavigator::extractProperty($arr, $this->seqIdPropertyPath);
		}
		$data = $this->serialize($id, $arr);

		if ($expectedRevision === 0) {

			try {
				$this->insert($link, $id, $data, $add_column_set_node);
			} catch (UnderlyingStorageError $e) {
				if ($duplicate = $this->detectDuplicateError($e->getMessage())) {
					[$key, $val] = $duplicate;
					if ($idempotency) {
						$check_rec = $this->loadRecById($link, $id);
						if ($check_rec === null) {
							throw new UniqueConstraintViolation($key, $val, $e->getMessage(),
								$e->getCode(), $e);
						}
						$check_rev = (int)$check_rec['$revision'];
						$check_obj = $this->deserialize($check_rec);
						$check_arr = (array)$check_obj->jsonSerialize();
						$idempotent_insert = $check_rev === 1 && $this->dataArrEquals($check_arr, $full_arr);
						if (!$idempotent_insert) {
							throw new ItemAlreadyExists("Item '$id' already exists");
						}
					} else {
						if ($this->has_unique_secondary && $key !== '$id') {
							throw new UniqueConstraintViolation($key, $val, $e->getMessage(),
								$e->getCode(), $e);
						}
						throw new ItemAlreadyExists("Item '$id' already exists");
					}
				} else {
					throw $e;
				}
			}

		} elseif ($expectedRevision > 0) {

			$result = $this->update($link, $id, $data, $add_column_set_node, $expectedRevision);
			$affected = $result->getAffectedRows();
			if ($affected === 0) {
				$check_rec = $this->loadRecById($link, $id);
				if ($check_rec === null) {
					throw new UnexpectedRevision("Item '$id' not found, expected revision $expectedRevision");
				}
				$check_rev = (int)$check_rec['$revision'];
				$check_obj = $this->deserialize($check_rec);
				$check_arr = (array)$check_obj->jsonSerialize();
				if ($idempotency) {
					$idempotent_update = ($check_rev - 1) === $expectedRevision && $this->dataArrEquals($check_arr, $full_arr);
					if (!$idempotent_update) {
						throw new UnexpectedRevision("Item '$id' expected revision $expectedRevision does not match actual $check_rev");
					}
				} else {
					throw new UnexpectedRevision("Item '$id' expected revision $expectedRevision does not match actual $check_rev");
				}
			}

		} elseif ($this->has_unique_secondary) {
			try {
				$this->insert($link, $id, $data, $add_column_set_node);
			} catch (UnderlyingStorageError $e) {
				if ($duplicate = $this->detectDuplicateError($e->getMessage())) {
					[$key, $val] = $duplicate;
					if ($key !== '$id') {
						throw new UniqueConstraintViolation($key, $val, $e->getMessage(),
							$e->getCode(), $e);
					}
					$this->update($link, $id, $data, $add_column_set_node, null);
				} else {
					throw $e;
				}
			}
		} else {
			$this->insertOnDupUpdate($link, $id, $data, $add_column_set_node);
		}
	}

	private function dataArrEquals($arr1, $arr2) : bool
	{
		if ($this->revisionPropertyPath) {
			ArrayPathNavigator::extractProperty($arr1, $this->revisionPropertyPath);
			ArrayPathNavigator::extractProperty($arr2, $this->revisionPropertyPath);
		}
		if ($this->seqIdPropertyPath) {
			ArrayPathNavigator::extractProperty($arr1, $this->seqIdPropertyPath);
			ArrayPathNavigator::extractProperty($arr2, $this->seqIdPropertyPath);
		}
		return $arr1 === $arr2;
	}

	private function detectDuplicateError(string $message)
	{
		if (\preg_match("/Duplicate entry '(.+)' for key '(.+)'/", $message, $m)) {
			return [$m[2], $m[1]];
		}
		return false;
	}

	/**
	 * @param $id
	 * @param array $arr
	 * @return null|string
	 * @throws GenericStorageError|\JsonException
	 */
	private function serialize($id, array $arr) : ?string
	{
		$data = \json_encode($arr, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
		if (($len = \strlen($data)) > $this->dataMaxSize) {
			throw new UnderlyingStorageError("Can't write $len bytes as $this->className#$id data at ".self::class);
		}
		return $data;
	}

	/**
	 * @param MyLink $link
	 * @param $id
	 * @throws UnderlyingStorageError
	 */
	public function removeItem(MyLink $link, $id) : void
	{
		$e_id_quoted = '\''.$link->escapeString($id).'\'';
		if ($this->idBin) {
			$e_id_quoted = "UNHEX($e_id_quoted)";
		}
		/** @noinspection SqlResolve */
		$link->command("DELETE FROM `$this->tableNameEscaped` WHERE `\$id`=$e_id_quoted");
	}

	/**
	 * @param MyLink $link
	 * @param Criteria $criteria
	 * @throws FormatterException
	 * @throws ParserException
	 * @throws UnderlyingStorageError
	 */
	public function removeByCriteria(MyLink $link, Criteria $criteria) : void
	{
		/** @noinspection SqlResolve */
		$link->command("DELETE FROM `$this->tableNameEscaped` WHERE ".$this->makeWhere($link, $criteria));
	}

	/**
	 * @param MyLink $link
	 * @throws UnderlyingStorageError
	 */
	public function clear(MyLink $link) : void
	{
		/** @noinspection SqlResolve */
		$link->command("DELETE FROM `$this->tableNameEscaped`");
	}

	/**
	 * @param MyLink $link
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_node
	 * @throws UnderlyingStorageError
	 */
	private function insert(MyLink $link, string $id, string $data, string $add_column_set_node) : void
	{
		$e_id_quoted = '\''.$link->escapeString($id).'\'';
		if ($this->idBin) {
			$e_id_quoted = "UNHEX($e_id_quoted)";
		}
		$e_data = $link->escapeString($data);
		/** @noinspection SqlResolve */
		$link->command("INSERT INTO `$this->tableNameEscaped` SET `\$id`=$e_id_quoted, `\$data`='$e_data' $add_column_set_node");
	}

	/**
	 * @param MyLink $link
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_node
	 * @param int|null $expected_rev
	 * @return MyCommandResult
	 * @throws UnderlyingStorageError
	 */
	private function update(
		MyLink $link,
		string $id,
		string $data,
		string $add_column_set_node,
		?int $expected_rev
	) : MyCommandResult {
		$e_id_quoted = '\''.$link->escapeString($id).'\'';
		if ($this->idBin) {
			$e_id_quoted = "UNHEX($e_id_quoted)";
		}
		$e_data = $link->escapeString($data);
		/** @noinspection SqlResolve */
		$update = "UPDATE `$this->tableNameEscaped` SET `\$data`='$e_data', `\$revision`=`\$revision`+1 ".
			"$add_column_set_node WHERE `\$id`=$e_id_quoted";
		if ($expected_rev === null) {
			return $link->command($update);
		}
		$update .= " AND `\$revision`=$expected_rev";
		return $link->command($update);
	}

	/**
	 * @param MyLink $link
	 * @param string $id
	 * @return array|null
	 * @throws UnderlyingStorageError
	 */
	private function loadRecById(MyLink $link, string $id) : ?array
	{
		$e_id_quoted = '\''.$link->escapeString($id).'\'';
		if ($this->idBin) {
			$e_id_quoted = "UNHEX($e_id_quoted)";
		}
		/** @noinspection SqlResolve */
		$res = $link->query("$this->selectAllFromTable WHERE `\$id`=$e_id_quoted");
		return $res->fetchAssoc();
	}

	/**
	 * @param MyLink $link
	 * @param string $id
	 * @param string|null $data
	 * @param string $add_column_set_str
	 * @throws UnderlyingStorageError
	 */
	private function insertOnDupUpdate(MyLink $link, string $id, ?string $data, string $add_column_set_str) : void
	{
		$e_id_quoted = '\''.$link->escapeString($id).'\'';
		if ($this->idBin) {
			$e_id_quoted = "UNHEX($e_id_quoted)";
		}
		$e_data = $link->escapeString($data);
		/** @noinspection SqlResolve */
		$q1 = "INSERT INTO `$this->tableNameEscaped` SET `\$id`=$e_id_quoted, `\$data`='$e_data' $add_column_set_str \n".
			"ON DUPLICATE KEY UPDATE `\$data`='$e_data', `\$revision`=`\$revision`+1 $add_column_set_str";
		$link->command($q1);
	}

	/**
	 * @param MyLink $link
	 * @param $arr
	 * @return string
	 */
	private function createUpdateColumnsFragment(MyLink $link, &$arr) : string
	{
		$add_column_set_str = [];
		foreach ($this->add_columns_data as $i => $col) {
			$default = $col['default'];
			if ($col['mode'] & self::COLUMN_MODE_SAVE_EXTRACT) {
				$v = ArrayPathNavigator::extractProperty($arr, $this->add_columns_data[$i]['path'], $default);
			} else {
				$v = ArrayPathNavigator::getPropertyValue($arr, $this->add_columns_data[$i]['path'], $default);
			}
			$e_col = '`'.$this->add_columns_data[$i]['escaped'].'`';
			$e_val = $v === null ? 'null' : "'".$link->escapeString($v)."'";
			$add_column_set_str[] = "$e_col=$e_val";
		}
		return \implode(', ', $add_column_set_str);
	}

	/**
	 * @param MyLink $link
	 * @param array $orderDef
	 * @param int $limit
	 * @return iterable
	 * @throws UnderlyingStorageError
	 * @throws \JsonException
	 */
	public function listAll(MyLink $link, array $orderDef = [], int $limit = 0) : iterable
	{
		$all = "$this->selectAllFromTable WHERE 1";
		yield from $this->iterateOverDecoded($link, $all, $orderDef, $limit);
	}

	/**
	 * @param MyLink $link
	 * @param Criteria $criteria
	 * @param array $orderDef
	 * @param int $limit
	 * @return iterable
	 * @throws FormatterException
	 * @throws ParserException
	 * @throws UnderlyingStorageError
	 * @throws \JsonException
	 */
	public function listByCriteria(MyLink $link, Criteria $criteria, array $orderDef = [], int $limit = 0) : iterable
	{
		$q = "$this->selectAllFromTable WHERE ".$this->makeWhere($link, $criteria);
		yield from $this->iterateOverDecoded($link, $q, $orderDef, $limit);
	}

	/**
	 * @param MyLink $link
	 * @param Criteria $criteria
	 * @return string
	 * @throws ParserException
	 * @throws FormatterException
	 */
	private function makeWhere(MyLink $link, Criteria $criteria) : string
	{
		[$sql, $args] = $this->criteriaBuilder->build($criteria, function ($property) {
			switch (true) {
				case $this->idPropertyName === $property && !isset($this->add_columns[$property]):
					if ($this->idBin) {
						return ['HEX({i})', ['$id']];
					}
					return ['{i}', ['$id']];
				case $this->seqIdPropertyName === $property:
					return ['{i}', ['$seq_id']];
				default:
					if (isset($this->add_columns[$property]) || isset($this->add_generated_columns[$property])) {
						return ['{i}', [$property]];
					}
					$dot_ref = '$.'.\str_replace('/', '.', $property);
					return ['JSON_UNQUOTE(JSON_EXTRACT({i}, {s}))', ['$data', $dot_ref]];
			}
		});
		$node = $this->parser->parse($sql);
		return $this->formatter->format($node, $args, [$link, 'escapeString']);
	}

	/**
	 * @param MyLink $link
	 * @param int $batch_size
	 * @throws GenericStorageError
	 * @throws UnderlyingStorageError
	 * @throws \JsonException
	 */
	public function updateColumns(MyLink $link, int $batch_size = 10000) : void
	{
		if ($batch_size <= 0) {
			throw new \InvalidArgumentException("Batch size must be > 0");
		}

		$updated = 0;
		$link->command('BEGIN');
		$it = $this->iterateOverLinkBatchedBySeqId($link, "$this->selectAllFromTable WHERE 1 ", $batch_size, 0);
		foreach ($it as $rec) {
			$obj = $this->deserialize($rec);
			$arr = (array)$obj->jsonSerialize();
			$seq_id = $rec['$seq_id'];
			$e_id = $link->escapeString($seq_id);

			$add_column_set_node = $this->createUpdateColumnsFragment($link, $arr);
			$add_column_set_node = $add_column_set_node ? (', '.$add_column_set_node) : '';
			if ($this->revisionPropertyPath) {
				ArrayPathNavigator::extractProperty($arr, $this->revisionPropertyPath);
			}
			if ($this->seqIdPropertyPath) {
				ArrayPathNavigator::extractProperty($arr, $this->seqIdPropertyPath);
			}
			if ($this->idPropertyPath && $this->idExtracted) {
				ArrayPathNavigator::extractProperty($arr, $this->idPropertyPath);
			}
			$data = $this->serialize('$seq_id='.$seq_id, $arr);
			$e_data = $link->escapeString($data);
			$expected_rev = (int)$rec['$revision'];
			/** @noinspection SqlResolve */
			$update = "UPDATE `$this->tableNameEscaped` SET `\$data`='$e_data', `\$store_time`=`\$store_time`".
				"$add_column_set_node WHERE `\$seq_id`='$e_id' AND `\$revision`=$expected_rev";
			$link->command($update);

			$updated++;
			if ($updated === $batch_size) {
				$link->command('COMMIT');
				$link->command('BEGIN');
				$updated = 0;
			}
		}
		$link->command('COMMIT');
	}

	/**
	 * @param MyLink $link
	 * @param string $q
	 * @param int $limit
	 * @param array $orderDef
	 * @return \Generator|null
	 * @throws UnderlyingStorageError|\JsonException
	 */
	private function iterateOverDecoded(MyLink $link, string $q, array $orderDef, int $limit) : ?\Generator
	{
		foreach ($this->iterateOverLink($link, $q, $orderDef, $limit) as $k => $rec) {
			$obj = $this->deserialize($rec);
			yield $k => $obj;
		}
	}

	/**
	 * @param array $rec
	 * @return array
	 * @throws \JsonException
	 */
	private function reconstituteData(array $rec) : array
	{
		$arr = \json_decode($rec['$data'], true, 512, JSON_THROW_ON_ERROR);
		if ($this->idPropertyPath && $this->idExtracted) {
			ArrayPathNavigator::setPropertyValue($arr, $this->idPropertyPath, $rec['$id']);
		}
		if ($this->revisionPropertyPath) {
			ArrayPathNavigator::setPropertyValue($arr, $this->revisionPropertyPath, $rec['$revision']);
		}
		if ($this->seqIdPropertyPath) {
			ArrayPathNavigator::setPropertyValue($arr, $this->seqIdPropertyPath, $rec['$seq_id']);
		}
		foreach ($this->add_columns_data as $i => $col) {
			if ($col['mode'] & self::COLUMN_MODE_LOAD_FALLBACK) {
				ArrayPathNavigator::fallbackPropertyValue($arr, $col['path'], $rec[$i]);
			}
		}
		return $arr;
	}

	/**
	 * @param array $rec
	 * @return \JsonSerializable
	 * @throws \JsonException
	 */
	private function deserialize(array $rec) : \JsonSerializable
	{
		$arr = $this->reconstituteData($rec);
		/** @noinspection PhpUndefinedMethodInspection */
		return $this->className::deserialize($arr);
	}

	/**
	 * @param MyLink $link
	 * @param string $baseQuery
	 * @param array $orderDef
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLink(MyLink $link, string $baseQuery, array $orderDef, int $limit) : \Generator
	{
		$use_batches = $this->batchListSize > 0;
		if ($orderDef) {
			if (\count($orderDef) === 1 && $orderDef[0][0] === $this->seqIdPropertyName) {
				if ($use_batches) {
					if ($orderDef[0][1]) {
						yield from $this->iterateOverLinkBatchedBySeqId($link, $baseQuery, $this->batchListSize, $limit);
					} else {
						yield from $this->iterateOverLinkBatchedBySeqIdDesc($link, $baseQuery, $limit);
					}
				} else {
					$nodes = [
						$baseQuery,
						$this->buildOrderBy($link, $orderDef)
					];
					if ($limit) {
						$nodes[] = " LIMIT $limit";
					}
					yield from $this->iterate($link, \implode(' ', $nodes));
				}
			} else {
				$orderDef = $this->enrichOrderDefWithUniqueField($orderDef);
				$nodes = [
					$baseQuery,
					$this->buildOrderBy($link, $orderDef),
				];
				if ($use_batches) {
					yield from $this->iterateOverLinkBatchedByLimit($link, \implode(' ', $nodes), $limit);
				} else {
					if ($limit) {
						$nodes[] = " LIMIT $limit";
					}
					yield from $this->iterate($link, \implode(' ', $nodes));
				}
			}
		} elseif ($use_batches) {
			yield from $this->iterateOverLinkBatchedBySeqId($link, $baseQuery, $this->batchListSize, $limit);
		} elseif ($limit) {
			yield from $this->iterate($link, "$baseQuery LIMIT $limit");
		} else {
			yield from $this->iterate($link, $baseQuery);
		}
	}

	private function enrichOrderDefWithUniqueField(array $orderDef) : array
	{
		$repeatable_order = false;
		foreach ($orderDef as [$prop]) {
			if (\in_array($prop, [$this->seqIdPropertyName, $this->idPropertyName, '$seq_id', '$id'], true)) {
				$repeatable_order = true;
				break;
			}
		}
		if (!$repeatable_order) {
			$orderDef[] = ['$seq_id', true];
		}
		return $orderDef;
	}

	/**
	 * @param MyLink $link
	 * @param array $orderDef
	 * @return string
	 */
	private function buildOrderBy(MyLink $link, array $orderDef) : string
	{
		$members = [];
		foreach ($orderDef as [$property, $asc]) {
			switch (true) {
				case $this->idPropertyName === $property || '$id' === $property:
					$e_col = '`$id`';
					break;
				case $this->seqIdPropertyName === $property || '$seq_id' === $property:
					$e_col = '`$seq_id`';
					break;
				default:
					if (isset($this->add_columns[$property]) || isset($this->add_generated_columns[$property])) {
						$e_col = '`'.\str_replace('`', '``', $property).'`';
					} else {
						$dot_ref = '$.'.\str_replace('/', '.', $property);
						$e_ref = $link->escapeString($dot_ref);
						$e_col = "JSON_UNQUOTE(JSON_EXTRACT(`\$data`, '$e_ref'))";
					}
			}
			$e_dir = $asc ? '' : 'DESC';
			$members[] = "$e_col $e_dir";
		}
		return ' ORDER BY '.\implode(', ', $members);
	}

	/**
	 * @param MyLink $link
	 * @param string $baseQuery
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterate(MyLink $link, string $baseQuery) : \Generator
	{
		$it = $link->query($baseQuery)->iterate();
		foreach ($it as $rec) {
			yield $rec['$id'] => $rec;
		}
	}

	/**
	 * @param MyLink $link
	 * @param string $baseQuery
	 * @param int $batchListSize
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLinkBatchedBySeqId(MyLink $link, string $baseQuery, int $batchListSize, int $limit) : \Generator
	{
		$last_seq_id = 0;
		$loaded = 0;
		do {
			$found = 0;
			$batch_size = $batchListSize;
			if ($limit > 0) {
				$to = $loaded + $batchListSize;
				if ($to > $limit) {
					$batch_size -= $to - $limit;
				}
			}
			$list = $link->query("$baseQuery  AND `\$seq_id` > $last_seq_id ORDER BY `\$seq_id` LIMIT $batch_size")->iterate();
			foreach ($list as $rec) {
				$found++;
				$loaded++;
				$last_seq_id = $rec['$seq_id'];
				yield $rec['$id'] => $rec;
			}
		} while ($found === $batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @param MyLink $link
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLinkBatchedBySeqIdDesc(MyLink $link, string $baseQuery, int $limit) : \Generator
	{
		$last_seq_id = PHP_INT_MAX;
		$loaded = 0;
		do {
			$found = 0;
			$batch_size = $this->batchListSize;
			if ($limit > 0) {
				$to = $loaded + $this->batchListSize;
				if ($to > $limit) {
					$batch_size -= $to - $limit;
				}
			}
			$list = $link->query("$baseQuery  AND `\$seq_id` < $last_seq_id ORDER BY `\$seq_id` DESC LIMIT $batch_size")->iterate();
			foreach ($list as $rec) {
				$found++;
				$loaded++;
				$last_seq_id = $rec['$seq_id'];
				yield $rec['$id'] => $rec;
			}
		} while ($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @param MyLink $link
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLinkBatchedByLimit(MyLink $link, string $baseQuery, int $limit) : \Generator
	{
		$loaded = 0;
		do {
			$found = 0;
			$batch_size = $this->batchListSize;
			if ($limit > 0) {
				$to = $loaded + $this->batchListSize;
				if ($to > $limit) {
					$batch_size -= $to - $limit;
				}
			}
			$list = $link->query("$baseQuery LIMIT $loaded, $batch_size")->iterate();
			foreach ($list as $rec) {
				$found++;
				$loaded++;
				yield $rec['$id'] => $rec;
			}
		} while ($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @param MyLink $link
	 * @throws UnderlyingStorageError
	 */
	public function reset(MyLink $link) : void
	{
		$add_columns = \array_map(function ($type, $i) {
			$type = $type['type'] ?? $type;
			return "`$i` $type";
		}, $this->add_columns, \array_keys($this->add_columns));
		$add_columns = $add_columns ? ','.\implode("\n\t\t\t,", $add_columns) : '';
		$add_generated_columns = '';
		if ($this->add_generated_columns) {
			$add_gen_arr = [];
			foreach ($this->add_generated_columns as $i => $def) {
				$add_gen_arr[] = "`$i` $def";
			}
			$add_generated_columns = ','.\implode("\n\t\t\t,", $add_gen_arr);
		}
		$add_indexes = $this->add_indexes ? ','.\implode("\n\t\t\t,", $this->add_indexes) : '';
		$link->command("DROP TABLE IF EXISTS `$this->tableNameEscaped`");
		$tmp = $this->temporary ? 'TEMPORARY' : '';
		$link->command("CREATE $tmp TABLE `$this->tableNameEscaped` (
			`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			`\$id` $this->idColumnDef NOT NULL,
			`\$data` $this->dataColumnDef,
			`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`\$revision` INT NOT NULL DEFAULT 1,
			UNIQUE (`\$id`)
			$add_columns
			$add_generated_columns
			$add_indexes
		) ENGINE=InnoDB".(!$this->temporary && $this->compressed ? ' ROW_FORMAT=COMPRESSED' : ''));
	}

}
