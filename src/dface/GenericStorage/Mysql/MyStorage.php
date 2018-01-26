<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\criteria\Criteria;
use dface\criteria\SqlCriteriaBuilder;
use dface\GenericStorage\Generic\GenericStorage;
use dface\GenericStorage\Generic\GenericStorageError;
use dface\GenericStorage\Generic\InvalidDataType;
use dface\Mysql\DuplicateEntryException;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;
use dface\sql\placeholders\PlainNode;

class MyStorage implements GenericStorage {

	/** @var string */
	private $className;
	/** @var MysqliConnection */
	private $dbi;
	/** @var \mysqli */
	private $link;
	/** @var string */
	private $tableNameEscaped;
	/** @var string */
	private $idPropertyName;
	/** @var string[] */
	private $add_columns;
	/** @var string[] */
	private $add_columns_data;
	/** @var string[] */
	private $add_indexes;
	/** @var bool */
	private $has_unique_secondary;
	/** @var callable */
	private $dedicatedLinkFactory;
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

	/**
	 * @param string $className
	 * @param \mysqli $link
	 * @param string $tableName
	 * @param $dedicatedLinkFactory
	 * @param string|null $idPropertyName
	 * @param array $add_columns
	 * @param array $add_indexes
	 * @param bool $has_unique_secondary
	 * @param bool $temporary
	 * @param int $batch_list_size
	 * @param int $id_batch_size
	 * @throws \InvalidArgumentException
	 */
	public function __construct(
		string $className,
		\mysqli $link,
		string $tableName,
		$dedicatedLinkFactory,
		string $idPropertyName = null,
		array $add_columns = [],
		array $add_indexes = [],
		bool $has_unique_secondary = false,
		bool $temporary = false,
		int $batch_list_size = 10000,
		int $id_batch_size = 500
	) {
		$this->className = $className;
		$this->dbi = new MysqliConnection($link, new DefaultParser(), new DefaultFormatter());
		$this->link = $link;
		$this->tableNameEscaped = str_replace('`', '``', $tableName);
		$this->idPropertyName = $idPropertyName;
		$this->add_columns = $add_columns;
		$this->add_columns_data = [];
		foreach($this->add_columns as $i => $x){
			$this->add_columns_data[$i] = [
				'escaped' => str_replace('`', '``', $i),
				'path' => explode('/', $i),
			];
		}
		$this->add_indexes = $add_indexes;
		$this->dedicatedLinkFactory = $temporary ? null : $dedicatedLinkFactory;
		$this->temporary = $temporary;
		$this->has_unique_secondary = $has_unique_secondary;
		$this->criteriaBuilder = new SqlCriteriaBuilder();
		/** @noinspection SqlResolve */
		$this->selectAllFromTable = "SELECT `\$seq_id`, LOWER(HEX(`\$id`)) as `\$id`, `\$data` FROM `$this->tableNameEscaped`";
		if($batch_list_size < 0){
			throw new \InvalidArgumentException("Batch list size must be >=0, $batch_list_size given");
		}
		$this->batchListSize = $batch_list_size;
		if($id_batch_size < 1){
			throw new \InvalidArgumentException("Id batch size must be >0, $id_batch_size given");
		}
		$this->idBatchSize = $id_batch_size;
	}

	/**
	 * @param $id
	 * @return \JsonSerializable|null
	 *
	 * @throws MyStorageError
	 */
	public function getItem($id) : ?\JsonSerializable {
		try{
			$e_id = $this->link->real_escape_string($id);
			/** @var \Traversable $it1 */
			/** @noinspection SqlResolve */
			$it1 = $this->dbi->query(new PlainNode(0, "SELECT `\$data` FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')"));
			/** @noinspection LoopWhichDoesNotLoopInspection */
			foreach($it1 as $rec){
				return $this->deserialize($rec);
			}
			return null;
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param array|\traversable $ids
	 * @return \traversable
	 *
	 * @throws GenericStorageError
	 */
	public function getItems($ids) : \traversable {
		try{
			$sub_list = [];
			foreach($ids as $id){
				if(\count($sub_list) === $this->idBatchSize){
					$where = ' WHERE `$id` IN ('.implode(',', $sub_list).')';
					$node = "$this->selectAllFromTable $where";
					yield from $this->iterateOverDecoded($node, [], 0);
					$sub_list = [];
				}
				$e_id = $this->link->real_escape_string($id);
				$sub_list[] =  "UNHEX('$e_id')";
			}
			if($sub_list){
				$where = ' WHERE `$id` IN ('.implode(',', $sub_list).')';
				$node = "$this->selectAllFromTable $where";
				yield from $this->iterateOverDecoded($node, [], 0);
			}
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 * @throws GenericStorageError
	 */
	public function saveItem($id, \JsonSerializable $item) : void {
		if(!$item instanceof $this->className){
			throw new InvalidDataType("Stored item must be instance of $this->className");
		}
		try{
			$arr = $item->jsonSerialize();
			$add_column_set_node = $this->createUpdateColumnsFragment($arr);
			$add_column_set_node = $add_column_set_node ? (', '.$add_column_set_node) : '';
			$data = $this->serialize($id, $arr);
			if($this->has_unique_secondary){
				try{
					$this->insert($id, $data, $add_column_set_node);
				}catch(DuplicateEntryException $e){
					if($e->getKey() !== '$id'){
						throw new MyUniqueConstraintViolation($e->getKey(), $e->getEntry(), $e->getMessage(), $e->getCode(), $e);
					}
					$this->update($id, $data, $add_column_set_node);
				}
			}else{
				$this->insertOnDupUpdate($id, $data, $add_column_set_node);
			}
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $id
	 * @param array $arr
	 * @return null|string
	 * @throws GenericStorageError
	 */
	private function serialize($id, array $arr) : ?string {
		$data = json_encode($arr, JSON_UNESCAPED_UNICODE);
		if(($len = \strlen($data)) > 65535){
			throw new MyStorageError("Can't write $len bytes as $this->className#$id data at ".self::class);
		}
		return $data;
	}

	/**
	 * @param $id
	 * @throws GenericStorageError
	 */
	public function removeItem($id) : void {
		try{
			$this->delete($id);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param Criteria $criteria
	 * @throws GenericStorageError
	 */
	public function removeByCriteria(Criteria $criteria) : void {
		try{
			/** @noinspection SqlResolve */
			$this->dbi->query(new PlainNode(0,
				"DELETE FROM `$this->tableNameEscaped` WHERE ".$this->makeWhere($criteria)));
		}catch (MySqlException|FormatterException|ParserException $e){
			throw new MyStorageError('MyStorage removeByCriteria query failed', 0, $e);
		}
	}

	/**
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_node
	 *
	 * @throws MysqlException|FormatterException|ParserException|DuplicateEntryException
	 */
	private function insert(string $id, string $data, string $add_column_set_node) : void {
		$e_id = $this->link->real_escape_string($id);
		$e_data = $this->link->real_escape_string($data);
		/** @noinspection SqlResolve */
		$this->dbi->update(new PlainNode(0, "INSERT INTO `$this->tableNameEscaped` SET `\$id`=UNHEX('$e_id'), `\$data`='$e_data' $add_column_set_node"));
	}

	/**
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_node
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function update(string $id, string $data, string $add_column_set_node) : void {
		$e_id = $this->link->real_escape_string($id);
		$e_data = $this->link->real_escape_string($data);
		/** @noinspection SqlResolve */
		$this->dbi->update(new PlainNode(0, "UPDATE `$this->tableNameEscaped` SET `\$data`='$e_data' $add_column_set_node WHERE `\$id`=UNHEX('$e_id')"));
	}

	/**
	 * @param string $id
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function delete(string $id) : void {
		$e_id = $this->link->real_escape_string($id);
		/** @noinspection SqlResolve */
		$this->dbi->update(new PlainNode(0, "DELETE FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')"));
	}

	/**
	 * @param string $id
	 * @param string $data
	 * @param string $add_column_set_str
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function insertOnDupUpdate(string $id, ?string $data, string $add_column_set_str) : void {
		$e_id = $this->link->real_escape_string($id);
		$e_data = $this->link->real_escape_string($data);
		/** @noinspection SqlResolve */
		$q1 = new PlainNode(0, "INSERT INTO `$this->tableNameEscaped` SET `\$id`=UNHEX('$e_id'), `\$data`='$e_data' $add_column_set_str \n".
			"ON DUPLICATE KEY UPDATE `\$data`='$e_data' $add_column_set_str");
		$this->dbi->update($q1);
	}

	/**
	 * @param $arr
	 * @return string
	 */
	private function createUpdateColumnsFragment($arr) : string {
		$add_column_set_str = [];
		foreach($this->add_columns as $i => $x){
			$default = $x['default'] ?? null;
			$v = $this->extractIndexValue($arr, $this->add_columns_data[$i]['path'], $default);
			$e_col = '`'.$this->add_columns_data[$i]['escaped'].'`';
			$e_val = $v === null ? 'null' : "'".$this->link->real_escape_string($v)."'";
			$add_column_set_str[] = "$e_col=$e_val";
		}
		return implode(', ', $add_column_set_str);
	}

	/**
	 * @param $arr
	 * @param array $path
	 * @param $default
	 * @return mixed
	 */
	private function extractIndexValue(array $arr, array $path, $default) {
		$x = $arr;
		foreach($path as $p){
			if(!isset($x[$p])){
				return $default;
			}
			$x = $x[$p];
		}
		return $x;
	}

	/**
	 * @param array $orderDef
	 * @param int $limit
	 * @return \traversable
	 * @throws GenericStorageError
	 */
	public function listAll(array $orderDef = [], int $limit = 0) : \traversable {
		try{
			$all = "$this->selectAllFromTable WHERE 1";
			yield from $this->iterateOverDecoded($all, $orderDef, $limit);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param Criteria $criteria
	 * @param array $orderDef
	 * @param int $limit
	 * @return \traversable
	 * @throws GenericStorageError
	 */
	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : \traversable {
		try{
			$q = "$this->selectAllFromTable WHERE ".$this->makeWhere($criteria);
			yield from $this->iterateOverDecoded($q, $orderDef, $limit);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param Criteria $criteria
	 * @return string
	 * @throws FormatterException|ParserException
	 */
	private function makeWhere(Criteria $criteria) : string {
		[$sql, $args] = $this->criteriaBuilder->build($criteria, function ($property) {
			return $property === $this->idPropertyName ? ['HEX({i})', ['$id']] : ['{i}', [$property]];
		});
		return $this->dbi->build($sql, ...$args);
	}

	/**
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	public function updateColumns() : void {
		if($this->add_columns){
			$it = $this->iterateOver("$this->selectAllFromTable WHERE 1 ", [], 0);
			foreach($it as $rec){
				$arr = json_decode($rec['$data'], true);
				// TODO: don't update if columns contain correct values
				$add_column_set_str = $this->createUpdateColumnsFragment($arr);
				$e_id = $this->link->real_escape_string($rec['$seq_id']);
				/** @noinspection SqlResolve */
				$this->dbi->update(new PlainNode(0, "UPDATE `$this->tableNameEscaped` SET $add_column_set_str WHERE `\$seq_id`='$e_id'"));
			}
		}
	}

	/**
	 * @param string $q
	 * @param int $limit
	 * @param $orderDef
	 * @return \Generator|null
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOverDecoded(string $q, array $orderDef, int $limit) : ?\Generator {
		foreach($this->iterateOver($q, $orderDef, $limit) as $k=>$rec){
			$obj = $this->deserialize($rec);
			yield $k=>$obj;
		}
	}

	private function deserialize(array $rec) {
		$arr = json_decode($rec['$data'], true);
		return \call_user_func([$this->className, 'deserialize'], $arr);
	}

	/**
	 * @param string $query
	 * @param int $limit
	 * @param array[] $orderDef
	 * @return \Generator
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOver(string $query, array $orderDef, int $limit) : \Generator {
		if($this->dedicatedLinkFactory !== null){
			/** @var \mysqli $link */
			$link = \call_user_func($this->dedicatedLinkFactory);
			/** @var MysqliConnection $dbi */
			$dbi = new MysqliConnection($link, new DefaultParser(), new DefaultFormatter());
			try{
				yield from $this->iterateOverDbi($dbi, $query, $orderDef, $limit);
			}finally{
				$dbi->close();
			}
		}else{
			yield from $this->iterateOverDbi($this->dbi, $query, $orderDef, $limit);
		}
	}

	/**
	 * @param MysqliConnection $dbi
	 * @param string $baseQuery
	 * @param array $orderDef
	 * @param int $limit
	 * @return \Generator
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOverDbi(MysqliConnection $dbi, string $baseQuery, array $orderDef, int $limit) : \Generator {
		if($orderDef){
			if(\count($orderDef) === 1 && $orderDef[0][0] === $this->idPropertyName){
				if($this->batchListSize > 0){
					if($orderDef[0][1]){
						yield from $this->iterateOverDbiBatchedBySeqId($dbi, $baseQuery, $limit);
					}else{
						yield from $this->iterateOverDbiBatchedBySeqIdDesc($dbi, $baseQuery, $limit);
					}
				}else{
					$nodes = [
						$baseQuery,
						$this->buildOrderBy($orderDef)
					];
					if($limit){
						$nodes[] = " LIMIT $limit";
					}
					yield from $this->iterate($dbi, implode(' ', $nodes));
				}
			}else{
				$nodes = [
					$baseQuery,
					$this->buildOrderBy($orderDef),
				];
				if($this->batchListSize > 0){
					yield from $this->iterateOverDbiBatchedByLimit($dbi, implode(' ', $nodes), $limit);
				}else{
					if($limit){
						$nodes[] = " LIMIT $limit";
					}
					yield from $this->iterate($dbi, implode(' ', $nodes));
				}
			}
		}else{
			if($this->batchListSize > 0){
				yield from $this->iterateOverDbiBatchedBySeqId($dbi, $baseQuery, $limit);
			}else{
				if($limit){
					yield from $this->iterate($dbi, "$baseQuery LIMIT $limit");
				}else{
					yield from $this->iterate($dbi, $baseQuery);
				}
			}
		}
	}

	/**
	 * @param array $orderDef
	 * @return string
	 */
	private function buildOrderBy(array $orderDef) : string {
		$members = [];
		foreach($orderDef as [$property, $asc]){
			if($property === $this->idPropertyName){
				$property = '$id';
			}
			$e_col = str_replace('`', '``', $property);
			$e_dir = $asc ? '' : ' DESC';
			$members[] = "`$e_col`$e_dir";
		}
		return ' ORDER BY '.implode(', ', $members);
	}

	/**
	 * @param MysqliConnection $dbi
	 * @param string $baseQuery
	 * @return \Generator
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	private function iterate(MysqliConnection $dbi, string $baseQuery) : \Generator
	{
		$it = $dbi->query(new PlainNode(0, $baseQuery));
		try{
			foreach($it as $rec){
				yield $rec['$id'] => $rec;
			}
		}finally{
			$it->free();
		}
	}

	/**
	 * @param MysqliConnection $dbi
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	private function iterateOverDbiBatchedBySeqId(MysqliConnection $dbi, string $baseQuery, int $limit) : \Generator {
		$last_seq_id = 0;
		$loaded = 0;
		do{
			$found = 0;
			$batch_size = $this->batchListSize;
			if($limit > 0){
				$to = $loaded + $this->batchListSize;
				if($to > $limit){
					$batch_size -= $to - $limit;
				}
			}
			$list = $dbi->query(new PlainNode(0, "$baseQuery  AND `\$seq_id` > $last_seq_id ORDER BY `\$seq_id` LIMIT $batch_size"));
			try{
				foreach($list as $rec){
					$found++;
					$loaded++;
					$last_seq_id = $rec['$seq_id'];
					yield $rec['$id'] => $rec;
				}
			}finally{
				$list->free();
			}
		}while($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @param MysqliConnection $dbi
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	private function iterateOverDbiBatchedBySeqIdDesc(MysqliConnection $dbi, string $baseQuery, int $limit) : \Generator {
		$last_seq_id = PHP_INT_MAX;
		$loaded = 0;
		do{
			$found = 0;
			$batch_size = $this->batchListSize;
			if($limit > 0){
				$to = $loaded + $this->batchListSize;
				if($to > $limit){
					$batch_size -= $to - $limit;
				}
			}
			$list = $dbi->query(new PlainNode(0, "$baseQuery  AND `\$seq_id` < $last_seq_id ORDER BY `\$seq_id` DESC LIMIT $batch_size"));
			try{
				foreach($list as $rec){
					$found++;
					$loaded++;
					$last_seq_id = $rec['$seq_id'];
					yield $rec['$id'] => $rec;
				}
			}finally{
				$list->free();
			}
		}while($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @param MysqliConnection $dbi
	 * @param string $baseQuery
	 * @param int $limit
	 * @return \Generator
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	private function iterateOverDbiBatchedByLimit(MysqliConnection $dbi, string $baseQuery, int $limit) : \Generator {
		$loaded = 0;
		do{
			$found = 0;
			$batch_size = $this->batchListSize;
			if($limit > 0){
				$to = $loaded + $this->batchListSize;
				if($to > $limit){
					$batch_size -= $to - $limit;
				}
			}
			$list = $dbi->query(new PlainNode(0, "$baseQuery LIMIT $loaded, $batch_size"));
			try{
				foreach($list as $rec){
					$found++;
					$loaded++;
					yield $rec['$id'] => $rec;
				}
			}finally{
				$list->free();
			}
		}while($found === $this->batchListSize && (!$limit || $loaded < $limit));
	}

	/**
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	public function reset() : void {
		$add_columns = array_map(function ($type, $i) {
			$type = $type['type'] ?? $type;
			return $this->dbi->build("{i} $type", $i);
		}, $this->add_columns, array_keys($this->add_columns));
		$add_columns = $add_columns ? ','.implode("\n\t\t\t,", $add_columns) : '';
		$add_indexes = $this->add_indexes ? ','.implode("\n\t\t\t,", $this->add_indexes) : '';
		$this->dbi->query("DROP TABLE IF EXISTS `$this->tableNameEscaped`");
		$tmp = $this->temporary ? 'TEMPORARY' : '';
		$this->dbi->query("CREATE $tmp TABLE `$this->tableNameEscaped` (
			`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			`\$id` BINARY(16) NOT NULL,
			`\$data` TEXT,
			`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE (`\$id`) USING HASH
			$add_columns
			$add_indexes
		) ENGINE=InnoDB");
	}

}
