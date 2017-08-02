<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\criteria\Criteria;
use dface\criteria\SqlCriteriaBuilder;
use dface\GenericStorage\Generic\GenericStorage;
use dface\Mysql\DuplicateEntryException;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\CompositeNode;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\Node;
use dface\sql\placeholders\ParserException;
use dface\sql\placeholders\PlainNode;

class MyStorage implements GenericStorage {

	/** @var string */
	private $className;
	/** @var MysqliConnection */
	private $dbi;
	/** @var string */
	private $tableName;
	/** @var string */
	private $idPropertyName;
	/** @var string[] */
	private $add_columns;
	/** @var string[] */
	private $add_indexes;
	/** @var bool */
	private $has_unique_secondary;
	/** @var callable */
	private $dedicatedConnectionFactory;
	/** @var bool */
	private $temporary;
	/** @var SqlCriteriaBuilder */
	private $criteriaBuilder;
	/** @var PlainNode */
	private $selectAllFromTable;
	/** @var int */
	private $batchListSize;
	/** @var int */
	private $idBatchSize;
	/** @var PlainNode */
	private $batchSuffix;
	/** @var PlainNode */
	private $batchSuffixDesc;

	public function __construct(
		string $className,
		MysqliConnection $dbi,
		string $tableName,
		$dedicatedConnectionFactory,
		string $idPropertyName = null,
		array $add_columns = [],
		array $add_indexes = [],
		bool $has_unique_secondary = false,
		bool $temporary = false,
		int $batch_list_size = 10000,
		int $id_batch_size = 500
	) {
		$this->className = $className;
		$this->dbi = $dbi;
		$this->tableName = $tableName;
		$this->idPropertyName = $idPropertyName;
		$this->add_columns = $add_columns;
		$this->add_indexes = $add_indexes;
		$this->dedicatedConnectionFactory = $temporary ? null : $dedicatedConnectionFactory;
		$this->temporary = $temporary;
		$this->has_unique_secondary = $has_unique_secondary;
		$this->criteriaBuilder = new SqlCriteriaBuilder();
		/** @noinspection SqlResolve */
		$this->selectAllFromTable = $this->dbi->build('SELECT $seq_id, LOWER(HEX($id)) as $id, $data FROM {i}', $this->tableName);
		if($batch_list_size < 0){
			throw new \InvalidArgumentException("Batch list size must be >=0, $batch_list_size given");
		}
		$this->batchListSize = $batch_list_size;
		$this->batchSuffix = $this->dbi->prepare(' AND $seq_id > {d} ORDER BY $seq_id LIMIT {d}');
		$this->batchSuffixDesc = $this->dbi->prepare(' AND $seq_id < {d} ORDER BY $seq_id DESC LIMIT {d}');
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
		static $q1;
		try{
			if($q1 === null){
				/** @noinspection SqlResolve */
				$q1 = $this->dbi->prepare('SELECT $data FROM {i} WHERE `$id`=UNHEX({s})');
			}
			$rec = $this->dbi->select($q1, $this->tableName, (string)$id)->getRecord();
			if($rec === null){
				return null;
			}
			return $this->deserialize($rec);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param array|\traversable $ids
	 * @return \traversable
	 *
	 * @throws MyStorageError
	 */
	public function getItems($ids) : \traversable {
		static $q1;
		try{
			if($q1 === null){
				$q1 = $this->dbi->prepare('UNHEX({s})');
			}
			$sub_list = [];
			foreach($ids as $id){
				$sub_list[] = $this->dbi->build($q1, $id);
				if(count($sub_list) === $this->idBatchSize){
					$where = new PlainNode(0, ' WHERE `$id` IN ('.implode(',', $sub_list).')');
					$node = new CompositeNode([$this->selectAllFromTable, $where]);
					yield from $this->iterateOverDecoded($node, [], 0);
					$sub_list = [];
				}
			}
			if($sub_list){
				$where = new PlainNode(0, ' WHERE `$id` IN ('.implode(',', $sub_list).')');
				$node = new CompositeNode([$this->selectAllFromTable, $where]);
				yield from $this->iterateOverDecoded($node, [], 0);
			}
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 * @throws MyStorageError
	 */
	public function saveItem($id, \JsonSerializable $item) : void {
		if(!$item instanceof $this->className){
			/** @noinspection ExceptionsAnnotatingAndHandlingInspection */
			throw new \InvalidArgumentException("Stored item must be instance of $this->className");
		}
		try{
			$arr = $item->jsonSerialize();
			$add_column_set_str = $this->createUpdateColumnsFragment($arr);
			$add_column_set_node = new PlainNode(0, $add_column_set_str ? (', '.$add_column_set_str) : '');
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

	private function serialize($id, array $arr) : ?string {
		$data = json_encode($arr, JSON_UNESCAPED_UNICODE);
		if(($len = strlen($data)) > 65535){
			throw new MyStorageError("Can't write $len bytes as $this->className#$id data at ".self::class);
		}
		return $data;
	}

	/**
	 * @param $id
	 * @throws MyStorageError
	 */
	public function removeItem($id) : void {
		try{
			$this->delete($id);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param string $id
	 * @param string $data
	 * @param Node $add_column_set_node
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function insert(string $id, string $data, Node $add_column_set_node) : void {
		static $q;
		if($q === null){
			/** @noinspection SqlResolve */
			$q = $this->dbi->prepare('INSERT INTO {i:1} SET `$id`=UNHEX({s:2}), `$data`={s:3} ');
		}
		$node = new CompositeNode([$q, $add_column_set_node]);
		$this->dbi->update($node, $this->tableName, $id, $data);
	}

	/**
	 * @param string $id
	 * @param string $data
	 * @param Node $add_column_set_node
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function update(string $id, string $data, Node $add_column_set_node) : void {
		static $q1, $q2;
		if($q1 === null){
			/** @noinspection SqlResolve */
			$q1 = $this->dbi->prepare('UPDATE {i:1} SET `$data`={s:3} ');
			$q2 = $this->dbi->prepare(' WHERE `$id`=UNHEX({s:2})');
		}
		$node = new CompositeNode([$q1, $add_column_set_node, $q2]);
		$this->dbi->update($node, $this->tableName, $id, $data);
	}

	/**
	 * @param string $id
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function delete(string $id) : void {
		static $q1;
		if($q1 === null){
			/** @noinspection SqlResolve */
			$q1 = $this->dbi->prepare('DELETE FROM {i:1} WHERE `$id`=UNHEX({s:2})');
		}
		$this->dbi->update($q1, $this->tableName, $id);
	}

	/**
	 * @param string $id
	 * @param string $data
	 * @param Node $add_column_set_str
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function insertOnDupUpdate(string $id, ?string $data, Node $add_column_set_str) : void {
		static $q1, $q2;
		if($q1 === null){
			/** @noinspection SqlResolve */
			$q1 = $this->dbi->prepare('INSERT INTO {i:1} SET `$id`=UNHEX({s:2}), `$data`={s:3} ');
			$q2 = $this->dbi->prepare(' ON DUPLICATE KEY UPDATE `$data`={s:3} ');
		}
		$node = new CompositeNode([$q1, $add_column_set_str, $q2, $add_column_set_str]);
		$this->dbi->update($node, $this->tableName, $id, $data);
	}

	/**
	 * @param $arr
	 * @return string
	 *
	 * @throws FormatterException|ParserException
	 */
	private function createUpdateColumnsFragment($arr) : string {
		static $q1;
		if($q1 === null){
			$q1 = $this->dbi->prepare('{i}={s}');
		}
		$add_column_set_str = [];
		foreach($this->add_columns as $i => $x){
			$default = $x['default'] ?? null;
			$v = $this->extractIndexValue($arr, $i, $default);
			$add_column_set_str[] = (string)$this->dbi->build($q1, $i, $v);
		}
		return implode(', ', $add_column_set_str);
	}

	/**
	 * @param $arr
	 * @param $index_name
	 * @param $default
	 * @return mixed
	 */
	private function extractIndexValue(array $arr, string $index_name, $default) {
		$path = explode('/', $index_name);
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
	 * @throws MyStorageError
	 */
	public function listAll(array $orderDef = [], int $limit = 0) : \traversable {
		static $q1;
		if($q1 === null){
			$q1 = new PlainNode(0, ' WHERE 1 ');
		}
		try{
			yield from $this->iterateOverDecoded(new CompositeNode([$this->selectAllFromTable, $q1]), $orderDef, $limit);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param Criteria $criteria
	 * @param array $orderDef
	 * @param int $limit
	 * @return \traversable
	 * @throws MyStorageError
	 */
	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : \traversable {
		try{
			$where = new PlainNode(0, ' WHERE '.$this->makeWhere($criteria));
			yield from $this->iterateOverDecoded(new CompositeNode([$this->selectAllFromTable, $where]), $orderDef, $limit);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param Criteria $criteria
	 * @return PlainNode
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function makeWhere(Criteria $criteria) : PlainNode {
		[$sql, $args] = $this->criteriaBuilder->build($criteria, function ($property) {
			return $property === $this->idPropertyName ? ['HEX({i})', ['$id']] : ['{i}', [$property]];
		});
		return $this->dbi->build($sql, ...$args);
	}

	public function updateColumns() : void {
		if($this->add_columns){
			$all = new PlainNode(0, ' WHERE 1 ');
			$it = $this->iterateOver(new CompositeNode([$this->selectAllFromTable, $all]), [], 0);
			foreach($it as $rec){
				$arr = json_decode($rec['$data'], true);
				// TODO: don't update if columns contain correct values
				$add_column_set_str = $this->createUpdateColumnsFragment($arr);
				/** @noinspection SqlResolve */
				$this->dbi->update("UPDATE {i} SET $add_column_set_str WHERE `\$seq_id`={s}",
					$this->tableName, $rec['$seq_id']);
			}
		}
	}

	/**
	 * @param Node $q
	 * @param int $limit
	 * @param $orderDef
	 * @return \Generator|null
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOverDecoded(Node $q, array $orderDef, int $limit) : ?\Generator {
		foreach($this->iterateOver($q, $orderDef, $limit) as $k=>$rec){
			$obj = $this->deserialize($rec);
			yield $k=>$obj;
		}
	}

	private function deserialize(array $rec) {
		$arr = json_decode($rec['$data'], true);
		return call_user_func([$this->className, 'deserialize'], $arr);
	}

	/**
	 * @param Node $query
	 * @param int $limit
	 * @param array[] $orderDef
	 * @return \Generator
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOver(Node $query, array $orderDef, int $limit) : \Generator {
		if($this->dedicatedConnectionFactory !== null){
			/** @var MysqliConnection $dbi */
			$dbi = call_user_func($this->dedicatedConnectionFactory);
			try{
				yield from $this->iterateOverDbi($dbi, $query, $orderDef, $limit);
			}finally{
				$dbi->close();
			}
		}else{
			yield from $this->iterateOverDbi($this->dbi, $query, $orderDef, $limit);
		}
	}

	private function iterateOverDbi(MysqliConnection $dbi, Node $baseQuery, array $orderDef, int $limit) : \Generator {
		if($orderDef){
			if(count($orderDef) === 1 && $orderDef[0][0] === $this->idPropertyName){
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
						$nodes[] = new PlainNode(0, ' LIMIT '.$limit);
					}
					$query = new CompositeNode($nodes);
					yield from $this->iterate($dbi, $query);
				}
			}else{
				$nodes = [
					$baseQuery,
					$this->buildOrderBy($orderDef),
				];
				if($this->batchListSize > 0){
					$query = new CompositeNode($nodes);
					yield from $this->iterateOverDbiBatchedByLimit($dbi, $query, $limit);
				}else{
					if($limit){
						$nodes[] = new PlainNode(0, ' LIMIT '.$limit);
					}
					$query = new CompositeNode($nodes);
					yield from $this->iterate($dbi, $query);
				}
			}
		}else{
			if($this->batchListSize > 0){
				yield from $this->iterateOverDbiBatchedBySeqId($dbi, $baseQuery, $limit);
			}else{
				if($limit){
					$query = new CompositeNode([$baseQuery, new PlainNode(0, 'LIMIT '.$limit)]);
					yield from $this->iterate($dbi, $query);
				}else{
					yield from $this->iterate($dbi, $baseQuery);
				}
			}
		}
	}

	private function buildOrderBy(array $orderDef) : PlainNode {
		static $q_asc, $q_desc;
		if($q_asc === null){
			$q_asc = $this->dbi->prepare('{i}');
			$q_desc = $this->dbi->prepare('{i} DESC');
		}
		$members = [];
		foreach($orderDef as [$property, $asc]){
			if($property === $this->idPropertyName){
				$property = '$id';
			}
			$members[] = $this->dbi->build($asc ? $q_asc : $q_desc, $property);
		}
		return new PlainNode(0, ' ORDER BY '.implode(', ', $members));
	}

	private function iterate(MysqliConnection $dbi, Node $baseQuery){
		$it = $dbi->query($baseQuery);
		try{
			foreach($it as $rec){
				yield $rec['$id'] => $rec;
			}
		}finally{
			$it->free();
		}
	}

	private function iterateOverDbiBatchedBySeqId(MysqliConnection $dbi, Node $baseQuery, int $limit) : \Generator {
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
			$list = $dbi->query(new CompositeNode([
				$baseQuery,
				$dbi->build($this->batchSuffix, $last_seq_id, $batch_size),
			]));
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

	private function iterateOverDbiBatchedBySeqIdDesc(MysqliConnection $dbi, Node $baseQuery, int $limit) : \Generator {
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
			$list = $dbi->query(new CompositeNode([
				$baseQuery,
				$dbi->build($this->batchSuffixDesc, $last_seq_id, $batch_size),
			]));
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

	private function iterateOverDbiBatchedByLimit(MysqliConnection $dbi, Node $baseQuery, int $limit) : \Generator {
		static $q1;
		if($q1 === null){
			$q1 = $dbi->prepare(' LIMIT {d}, {d}');
		}
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
			$list = $dbi->query(new CompositeNode([
				$baseQuery,
				$dbi->build($q1, $loaded, $batch_size),
			]));
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

	public function reset() : void {
		$add_columns = array_map(function ($type, $i) {
			$type = $type['type'] ?? $type;
			return $this->dbi->build("{i} $type", $i);
		}, $this->add_columns, array_keys($this->add_columns));
		$add_columns = $add_columns ? ','.implode("\n\t\t\t,", $add_columns) : '';
		$add_indexes = $this->add_indexes ? ','.implode("\n\t\t\t,", $this->add_indexes) : '';
		$this->dbi->query('DROP TABLE IF EXISTS {i}', $this->tableName);
		$tmp = $this->temporary ? 'TEMPORARY' : '';
		$this->dbi->query("CREATE $tmp TABLE {i} (
			`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			`\$id` BINARY(16) NOT NULL,
			`\$data` TEXT,
			`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE (`\$id`) USING HASH
			$add_columns
			$add_indexes
		) ENGINE=InnoDB", $this->tableName);
	}

}
