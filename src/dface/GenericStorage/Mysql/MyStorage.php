<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\criteria\Criteria;
use dface\criteria\SqlCriteriaBuilder;
use dface\GenericStorage\Generic\ArrayPathNavigator;
use dface\GenericStorage\Generic\GenericStorage;
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

class MyStorage implements GenericStorage
{

	/** @var string */
	private $className;
	/** @var MyLinkProvider */
	private $linkProvider;
	/** @var string */
	private $tableNameEscaped;
	/** @var string */
	private $idPropertyName;
	/** @var int */
	private $idLength;
	/** @var string */
	private $revisionPropertyPath;
	/** @var string[] */
	private $add_generated_columns;
	/** @var string[] */
	private $add_columns;
	/** @var string[] */
	private $add_columns_data;
	/** @var string[] */
	private $add_indexes;
	/** @var bool */
	private $has_unique_secondary;
	/** @var bool */
	private $temporary;
	/** @var SqlCriteriaBuilder */
	private $criteriaBuilder;
	/** @var string */
	private $selectAllFromTable;
	/** @var int */
	private $batchListSize;
	/** @var int */
	private $idBatchSize;
	/** @var string */
	private $dataColumnDef;
	/** @var int */
	private $dataMaxSize;
	/** @var bool */
	private $compressed;

	/** @var Formatter */
	private $formatter;
	/** @var Parser */
	private $parser;

	/**
	 * @param string $className
	 * @param MyLinkProvider $link_provider
	 * @param string $tableName
	 * @param string|null $idPropertyName
	 * @param int $idLength
	 * @param string|null $revisionPropertyName
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
		MyLinkProvider $link_provider,
		string $tableName,
		string $idPropertyName = null,
		int $idLength = 16,
		string $revisionPropertyName = null,
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
		$this->linkProvider = $link_provider;
		$this->formatter = new DefaultFormatter();
		$this->parser = new DefaultParser();
		$this->className = $className;
		$this->tableNameEscaped = str_replace('`', '``', $tableName);
		$this->idPropertyName = $idPropertyName;
		$this->idLength = $idLength;
		if ($revisionPropertyName !== null) {
			$this->revisionPropertyPath = explode('/', $revisionPropertyName);
		}
		$this->add_generated_columns = $add_generated_columns;
		$this->add_columns = $add_columns;
		$this->add_columns_data = [];
		foreach ($this->add_columns as $i => $x) {
			$this->add_columns_data[$i] = [
				'escaped' => str_replace('`', '``', $i),
				'path' => explode('/', $i),
			];
		}
		$this->add_indexes = $add_indexes;

		$this->temporary = $temporary;
		$this->has_unique_secondary = $has_unique_secondary;
		$this->criteriaBuilder = new SqlCriteriaBuilder();
		/** @noinspection SqlResolve */
		$this->selectAllFromTable = "SELECT `\$seq_id`, LOWER(HEX(`\$id`)) as `\$id`, `\$data`, `\$revision` FROM `$this->tableNameEscaped`";
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
	 * @param $id
	 * @return \JsonSerializable|null
	 */
	public function getItem($id) : ?\JsonSerializable
	{
		return $this->linkProvider->withLink(
			function (\mysqli $link) use ($id) {
				$e_id = $link->real_escape_string($id);
				/** @noinspection SqlResolve */
				$it1 = MyFun::query($link,
					"SELECT `\$data`, `\$revision` FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')");
				/** @noinspection LoopWhichDoesNotLoopInspection */
				foreach ($it1 as $rec) {
					return $this->deserialize($rec);
				}
				return null;
			});
	}

	/**
	 * @param array|\traversable $ids
	 * @return \traversable
	 */
	public function getItems($ids) : \traversable
	{
		return $this->linkProvider->withLink(function (\mysqli $link) use ($ids) {
			$sub_list = [];
			foreach ($ids as $id) {
				if (\count($sub_list) === $this->idBatchSize) {
					$where = ' WHERE `$id` IN ('.implode(',', $sub_list).')';
					$node = "$this->selectAllFromTable $where";
					yield from $this->iterateOverDecoded($link, $node, [], 0);
					$sub_list = [];
				}
				$e_id = $link->real_escape_string($id);
				$sub_list[] = "UNHEX('$e_id')";
			}
			if ($sub_list) {
				$where = ' WHERE `$id` IN ('.implode(',', $sub_list).')';
				$node = "$this->selectAllFromTable $where";
				yield from $this->iterateOverDecoded($link, $node, [], 0);
			}
		});
	}

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 * @param int $expectedRevision
	 */
	public function saveItem($id, \JsonSerializable $item, int $expectedRevision = null) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($id, $item, $expectedRevision) {
			if (!$item instanceof $this->className) {
				throw new InvalidDataType("Stored item must be instance of $this->className");
			}

			$arr = $item->jsonSerialize();
			if ($this->revisionPropertyPath !== null) {
				ArrayPathNavigator::unsetProperty($arr, $this->revisionPropertyPath);
			}
			$add_column_set_node = $this->createUpdateColumnsFragment($link, $arr);
			$add_column_set_node = $add_column_set_node ? (', '.$add_column_set_node) : '';
			$data = $this->serialize($id, $arr);

			if ($expectedRevision === 0) {
				try{
					$this->insert($link, $id, $data, $add_column_set_node);
				}catch (UnderlyingStorageError $e){
					if ($duplicate = $this->detectDuplicateError($e->getMessage())) {
						throw new ItemAlreadyExists("Item '$id' already exists");
					}
					throw $e;
				}
			}elseif ($expectedRevision > 0) {
				$this->update($link, $id, $data, $add_column_set_node, $expectedRevision);
			}elseif ($this->has_unique_secondary) {
				try{
					$this->insert($link, $id, $data, $add_column_set_node);
				}catch (UnderlyingStorageError $e){
					if ($duplicate = $this->detectDuplicateError($e->getMessage())) {
						[$key, $val] = $duplicate;
						if ($key !== '$id') {
							throw new UniqueConstraintViolation($key, $val, $e->getMessage(),
								$e->getCode(), $e);
						}
						$this->update($link, $id, $data, $add_column_set_node, null);
					}else {
						throw $e;
					}
				}
			}else {
				$this->insertOnDupUpdate($link, $id, $data, $add_column_set_node);
			}
		});
	}

	private function detectDuplicateError(string $message)
	{
		if (preg_match("/Duplicate entry '(.+)' for key '(.+)'/", $message, $m)) {
			return [$m[2], $m[1]];
		}
		return false;
	}

	/**
	 * @param $id
	 * @param array $arr
	 * @return null|string
	 * @throws GenericStorageError
	 */
	private function serialize($id, array $arr) : ?string
	{
		$data = json_encode($arr, JSON_UNESCAPED_UNICODE);
		if (($len = \strlen($data)) > $this->dataMaxSize) {
			throw new UnderlyingStorageError("Can't write $len bytes as $this->className#$id data at ".self::class);
		}
		return $data;
	}

	/**
	 * @param $id
	 */
	public function removeItem($id) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($id) {
			$e_id = $link->real_escape_string($id);
			/** @noinspection SqlResolve */
			MyFun::query($link, "DELETE FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')");
		});
	}

	/**
	 * @param Criteria $criteria
	 */
	public function removeByCriteria(Criteria $criteria) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($criteria) {
			/** @noinspection SqlResolve */
			MyFun::query($link, "DELETE FROM `$this->tableNameEscaped` WHERE ".$this->makeWhere($link, $criteria));
		});
	}

	public function clear() : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) {
			/** @noinspection SqlResolve */
			MyFun::query($link, "DELETE FROM `$this->tableNameEscaped`");
		});
	}

	/**
	 * @param \mysqli $link
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_node
	 * @throws UnderlyingStorageError
	 */
	private function insert(\mysqli $link, string $id, string $data, string $add_column_set_node) : void
	{
		$e_id = $link->real_escape_string($id);
		$e_data = $link->real_escape_string($data);
		/** @noinspection SqlResolve */
		MyFun::query($link,
			"INSERT INTO `$this->tableNameEscaped` SET `\$id`=UNHEX('$e_id'), `\$data`='$e_data' $add_column_set_node");
	}

	/**
	 * @param \mysqli $link
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_node
	 * @param int $expected_rev
	 * @throws UnexpectedRevision|UnderlyingStorageError
	 */
	private function update(
		\mysqli $link,
		string $id,
		string $data,
		string $add_column_set_node,
		?int $expected_rev
	) : void {
		$e_id = $link->real_escape_string($id);
		$e_data = $link->real_escape_string($data);
		/** @noinspection SqlResolve */
		$update = "UPDATE `$this->tableNameEscaped` SET `\$data`='$e_data', `\$revision`=`\$revision`+1 ".
			"$add_column_set_node WHERE `\$id`=UNHEX('$e_id')";
		if ($expected_rev === null) {
			MyFun::query($link, $update);
		}else {
			$update .= " AND `\$revision`=$expected_rev";
			MyFun::query($link, $update);
			$affected = $link->affected_rows;
			if ($affected === 0) {
				/** @noinspection SqlResolve */
				$res = MyFun::query($link,
					"SELECT `\$revision` FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')");
				$rec = $res->fetch_assoc();
				$rev = $rec['$revision'];
				throw new UnexpectedRevision("Item '$id' expected revision $expected_rev does not match actual $rev");
			}
		}
	}

	/**
	 * @param \mysqli $link
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_str
	 * @throws UnderlyingStorageError
	 */
	private function insertOnDupUpdate(\mysqli $link, string $id, ?string $data, string $add_column_set_str) : void
	{
		$e_id = $link->real_escape_string($id);
		$e_data = $link->real_escape_string($data);
		/** @noinspection SqlResolve */
		$q1 = "INSERT INTO `$this->tableNameEscaped` SET `\$id`=UNHEX('$e_id'), `\$data`='$e_data' $add_column_set_str \n".
			"ON DUPLICATE KEY UPDATE `\$data`='$e_data', `\$revision`=`\$revision`+1 $add_column_set_str";
		MyFun::query($link, $q1);
	}

	/**
	 * @param \mysqli $link
	 * @param $arr
	 * @return string
	 */
	private function createUpdateColumnsFragment(\mysqli $link, $arr) : string
	{
		$add_column_set_str = [];
		foreach ($this->add_columns as $i => $x) {
			$default = $x['default'] ?? null;
			$v = ArrayPathNavigator::getPropertyValue($arr, $this->add_columns_data[$i]['path'], $default);
			$e_col = '`'.$this->add_columns_data[$i]['escaped'].'`';
			$e_val = $v === null ? 'null' : "'".$link->real_escape_string($v)."'";
			$add_column_set_str[] = "$e_col=$e_val";
		}
		return implode(', ', $add_column_set_str);
	}

	/**
	 * @param array $orderDef
	 * @param int $limit
	 * @return \traversable
	 */
	public function listAll(array $orderDef = [], int $limit = 0) : \traversable
	{
		return $this->linkProvider->withLink(function (\mysqli $link) use ($orderDef, $limit) {
			$all = "$this->selectAllFromTable WHERE 1";
			yield from $this->iterateOverDecoded($link, $all, $orderDef, $limit);
		});
	}

	/**
	 * @param Criteria $criteria
	 * @param array $orderDef
	 * @param int $limit
	 * @return \traversable
	 */
	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : \traversable
	{
		return $this->linkProvider->withLink(function (\mysqli $link) use ($criteria, $orderDef, $limit) {
			$q = "$this->selectAllFromTable WHERE ".$this->makeWhere($link, $criteria);
			yield from $this->iterateOverDecoded($link, $q, $orderDef, $limit);
		});
	}

	/**
	 * @param \mysqli $link
	 * @param Criteria $criteria
	 * @return string
	 * @throws ParserException
	 * @throws FormatterException
	 */
	private function makeWhere(\mysqli $link, Criteria $criteria) : string
	{
		[$sql, $args] = $this->criteriaBuilder->build($criteria, function ($property) {
			return $property === $this->idPropertyName ? ['HEX({i})', ['$id']] : ['{i}', [$property]];
		});
		$node = $this->parser->parse($sql);
		return $this->formatter->format($node, $args, [$link, 'real_escape_string']);
	}

	public function updateColumns() : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) {
			if ($this->add_columns) {
				$it = $this->iterateOver($link, "$this->selectAllFromTable WHERE 1 ", [], 0);
				foreach ($it as $rec) {
					$arr = \json_decode($rec['$data'], true);
					// TODO: don't update if columns contain correct values
					$add_column_set_str = $this->createUpdateColumnsFragment($link, $arr);
					$e_id = $link->real_escape_string($rec['$seq_id']);
					/** @noinspection SqlResolve */
					MyFun::query($link,
						"UPDATE `$this->tableNameEscaped` SET $add_column_set_str WHERE `\$seq_id`='$e_id'");
				}
			}
		});
	}

	/**
	 * @param \mysqli $link
	 * @param string $q
	 * @param int $limit
	 * @param $orderDef
	 * @return \Generator|null
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverDecoded(\mysqli $link, string $q, array $orderDef, int $limit) : ?\Generator
	{
		foreach ($this->iterateOver($link, $q, $orderDef, $limit) as $k => $rec) {
			$obj = $this->deserialize($rec);
			yield $k => $obj;
		}
	}

	private function deserialize(array $rec)
	{
		$arr = json_decode($rec['$data'], true);
		if ($this->revisionPropertyPath !== null) {
			ArrayPathNavigator::setPropertyValue($arr, $this->revisionPropertyPath, $rec['$revision']);
		}
		return \call_user_func([$this->className, 'deserialize'], $arr);
	}

	/**
	 * @param \mysqli $link
	 * @param string $query
	 * @param int $limit
	 * @param array[] $orderDef
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOver(\mysqli $link, string $query, array $orderDef, int $limit) : \Generator
	{
		yield from $this->iterateOverLink($link, $query, $orderDef, $limit);
	}

	/**
	 * @param \mysqli $link
	 * @param string $baseQuery
	 * @param array $orderDef
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLink(\mysqli $link, string $baseQuery, array $orderDef, int $limit) : \Generator
	{
		$use_batches = $this->batchListSize > 0;
		if ($orderDef) {
			if (\count($orderDef) === 1 && $orderDef[0][0] === $this->idPropertyName) {

				if ($use_batches) {
					if ($orderDef[0][1]) {
						yield from $this->iterateOverLinkBatchedBySeqId($link, $baseQuery, $limit);
					}else {
						yield from $this->iterateOverLinkBatchedBySeqIdDesc($link, $baseQuery, $limit);
					}
				}else {
					$nodes = [
						$baseQuery,
						$this->buildOrderBy($orderDef)
					];
					if ($limit) {
						$nodes[] = " LIMIT $limit";
					}
					yield from $this->iterate($link, implode(' ', $nodes));
				}
			}else {
				$nodes = [
					$baseQuery,
					$this->buildOrderBy($orderDef),
				];
				if ($use_batches) {
					yield from $this->iterateOverLinkBatchedByLimit($link, implode(' ', $nodes), $limit);
				}else {
					if ($limit) {
						$nodes[] = " LIMIT $limit";
					}
					yield from $this->iterate($link, implode(' ', $nodes));
				}
			}
		}elseif ($use_batches) {
			yield from $this->iterateOverLinkBatchedBySeqId($link, $baseQuery, $limit);
		}elseif ($limit) {
			yield from $this->iterate($link, "$baseQuery LIMIT $limit");
		}else {
			yield from $this->iterate($link, $baseQuery);
		}
	}

	/**
	 * @param array $orderDef
	 * @return string
	 */
	private function buildOrderBy(array $orderDef) : string
	{
		$members = [];
		foreach ($orderDef as [$property, $asc]) {
			if ($property === $this->idPropertyName) {
				$property = '$id';
			}
			$e_col = str_replace('`', '``', $property);
			$e_dir = $asc ? '' : ' DESC';
			$members[] = "`$e_col`$e_dir";
		}
		return ' ORDER BY '.implode(', ', $members);
	}

	/**
	 * @param \mysqli $link
	 * @param string $baseQuery
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterate(\mysqli $link, string $baseQuery) : \Generator
	{
		$it = MyFun::query($link, $baseQuery);
		try{
			foreach ($it as $rec) {
				yield $rec['$id'] => $rec;
			}
		}finally{
			$it->free();
		}
	}

	/**
	 * @param \mysqli $link
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLinkBatchedBySeqId(\mysqli $link, string $baseQuery, int $limit) : \Generator
	{
		$last_seq_id = 0;
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
			$list = MyFun::query($link,
				"$baseQuery  AND `\$seq_id` > $last_seq_id ORDER BY `\$seq_id` LIMIT $batch_size");
			try{
				foreach ($list as $rec) {
					$found++;
					$loaded++;
					$last_seq_id = $rec['$seq_id'];
					yield $rec['$id'] => $rec;
				}
			}finally{
				$list->free();
			}
		}while ($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @param \mysqli $link
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLinkBatchedBySeqIdDesc(\mysqli $link, string $baseQuery, int $limit) : \Generator
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
			$list = MyFun::query($link,
				"$baseQuery  AND `\$seq_id` < $last_seq_id ORDER BY `\$seq_id` DESC LIMIT $batch_size");
			try{
				foreach ($list as $rec) {
					$found++;
					$loaded++;
					$last_seq_id = $rec['$seq_id'];
					yield $rec['$id'] => $rec;
				}
			}finally{
				$list->free();
			}
		}while ($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @param \mysqli $link
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function iterateOverLinkBatchedByLimit(\mysqli $link, string $baseQuery, int $limit) : \Generator
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
			$list = MyFun::query($link, "$baseQuery LIMIT $loaded, $batch_size");
			try{
				foreach ($list as $rec) {
					$found++;
					$loaded++;
					yield $rec['$id'] => $rec;
				}
			}finally{
				$list->free();
			}
		}while ($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	public function reset() : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) {
			$add_columns = array_map(function ($type, $i) {
				$type = $type['type'] ?? $type;
				return "`$i` $type";
			}, $this->add_columns, array_keys($this->add_columns));
			$add_columns = $add_columns ? ','.implode("\n\t\t\t,", $add_columns) : '';
			$add_generated_columns = $this->add_generated_columns ? ','.implode("\n\t\t\t,",
					$this->add_generated_columns) : '';
			$add_indexes = $this->add_indexes ? ','.implode("\n\t\t\t,", $this->add_indexes) : '';
			MyFun::query($link, "DROP TABLE IF EXISTS `$this->tableNameEscaped`");
			$tmp = $this->temporary ? 'TEMPORARY' : '';
			MyFun::query($link, "CREATE $tmp TABLE `$this->tableNameEscaped` (
				`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				`\$id` BINARY({$this->idLength}) NOT NULL,
				`\$data` {$this->dataColumnDef},
				`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				`\$revision` INT NOT NULL DEFAULT 1,
				UNIQUE (`\$id`)
				$add_columns
				$add_generated_columns
				$add_indexes
			) ENGINE=InnoDB".(!$this->temporary && $this->compressed ? ' ROW_FORMAT=COMPRESSED' : ''));
		});
	}

}
