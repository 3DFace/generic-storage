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

	public function __construct(
		string $className,
		MysqliConnection $dbi,
		$dedicatedConnectionFactory,
		string $tableName,
		array $add_columns = [],
		array $add_indexes = [],
		$has_unique_secondary = false,
		$temporary = false
	) {
		$this->className = $className;
		$this->dbi = $dbi;
		$this->tableName = $tableName;
		$this->add_columns = $add_columns;
		$this->add_indexes = $add_indexes;
		$this->dedicatedConnectionFactory = $temporary ? null : $dedicatedConnectionFactory;
		$this->temporary = $temporary;
		$this->has_unique_secondary = $has_unique_secondary;
		$this->criteriaBuilder = new SqlCriteriaBuilder();
		$this->selectAllFromTable = $this->dbi->build('SELECT * FROM {i}', $this->tableName);
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
				$q1 = $this->dbi->prepare('SELECT `$data` FROM {i} WHERE `$id`=UNHEX({s})');
			}
			$data = $this->dbi->select($q1, $this->tableName, (string)$id)->getValue();
			if($data === null){
				return null;
			}
			return call_user_func([$this->className, 'deserialize'], json_decode($data, true));
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
			$batch_size = 500;
			$sub_list = [];
			foreach($ids as $id){
				$sub_list[] = '0x'.(string)$id;
				if(count($sub_list) === $batch_size){
					$arr_str = implode(",\n", $sub_list);
					yield from $this->iterateOverDecoded(new CompositeNode([$this->selectAllFromTable, new PlainNode(0, " WHERE `\$id` IN ($arr_str)")]));
					$sub_list = [];
				}
			}
			if($sub_list){
				$arr_str = implode(",\n", $sub_list);
				yield from $this->iterateOverDecoded(new CompositeNode([$this->selectAllFromTable, new PlainNode(0, " WHERE `\$id` IN ($arr_str)")]));
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
		$arr = $item->jsonSerialize();
		try{
			$add_column_set_str = $this->createUpdateColumnsFragment($arr);
			$add_column_set_node = new PlainNode(0, $add_column_set_str ? (', '.$add_column_set_str) : '');
			$data = json_encode($arr, JSON_UNESCAPED_UNICODE);
			if(($len = strlen($data)) > 65535){
				throw new MyStorageError("Can't write $len bytes as $this->className#$id data at ".self::class);
			}
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
	private function insertOnDupUpdate(string $id, string $data, Node $add_column_set_str) : void {
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
			$v = $this->getIndexValue($arr, $i, $default);
			$add_column_set_str[] = (string)$this->dbi->build($q1, $i, $v);
		}
		return implode(', ', $add_column_set_str);
	}

	/**
	 * @param $x
	 * @param $index_name
	 * @param $default
	 * @return mixed
	 */
	private function getIndexValue($x, $index_name, $default) {
		$path = explode('/', $index_name);
		foreach($path as $p){
			if(!isset($x[$p])){
				return $default;
			}
			$x = $x[$p];
		}
		return $x;
	}

	/**
	 * @return \traversable
	 *
	 * @throws MyStorageError
	 */
	public function listAll() : \traversable {
		try{
			yield from $this->iterateOverDecoded($this->selectAllFromTable);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param Criteria $criteria
	 * @return \traversable
	 *
	 * @throws MyStorageError
	 */
	public function listByCriteria(Criteria $criteria) : \traversable {
		try{
			$where = new PlainNode(0, ' WHERE '.$this->makeWhere($criteria));
			yield from $this->iterateOverDecoded(new CompositeNode([$this->selectAllFromTable, $where]));
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
		[$sql, $args] = $this->criteriaBuilder->build($criteria);
		return $this->dbi->build($sql, ...$args);
	}

	public function updateColumns() : void {
		if($this->add_columns){
			foreach($this->iterateOver($this->selectAllFromTable) as $rec){
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
	 * @return \Generator|null
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOverDecoded(Node $q) : ?\Generator {
		foreach($this->iterateOver($q) as $rec){
			$arr = json_decode($rec['$data'], true);
			yield call_user_func([$this->className, 'deserialize'], $arr);
		}
	}

	/**
	 * @param $query
	 * @return \Generator
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOver(Node $query) : \Generator {
		if($this->dedicatedConnectionFactory !== null){
			yield from $this->iterateOverDedicated($query);
		}else{
			$list = $this->dbi->select($query);
			foreach($list as $rec){
				yield $rec;
			}
		}
	}

	/**
	 * @param Node $query
	 * @return \Generator
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function iterateOverDedicated(Node $query) : \Generator {
		/** @var MysqliConnection $dbi */
		$dbi = call_user_func($this->dedicatedConnectionFactory);
		try{
			$list = $dbi->selectOpt($query, MYSQLI_USE_RESULT);
			foreach($list as $rec){
				yield $rec;
			}
		}finally{
			$dbi->close();
		}
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
			`\$id` BINARY(16) NOT NULL UNIQUE,
			`\$data` TEXT NOT NULL,
			`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX(`\$store_time`) 
			$add_columns
			$add_indexes
		) ENGINE=InnoDB", $this->tableName);
	}

}
