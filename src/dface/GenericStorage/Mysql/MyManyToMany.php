<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\GenericManyToMany;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;

class MyManyToMany implements GenericManyToMany {

	/** @var MysqliConnection */
	private $dbi;
	/** @var string */
	private $tableName;
	/** @var string */
	private $leftClassName;
	/** @var string */
	private $rightClassName;
	/** @var string */
	private $leftColumnName;
	/** @var string */
	private $rightColumnName;
	/** @var bool */
	private $temporary;

	public function __construct(
		MysqliConnection $dbi,
		string $tableName,
		string $leftClassName,
		string $rightClassName,
		string $leftColumnName = 'left',
		string $rightColumnName = 'right',
		bool $temporary = false
	) {
		$this->dbi = $dbi;
		$this->tableName = $tableName;
		$this->leftClassName = $leftClassName;
		$this->rightClassName = $rightClassName;
		$this->leftColumnName = $leftColumnName;
		$this->rightColumnName = $rightColumnName;
		$this->temporary = $temporary;
	}

	/**
	 * @param $left
	 * @return \traversable
	 * @throws MyStorageError
	 */
	public function getAllByLeft($left) : \traversable {
		try{
			yield from $this->getAllByColumn($this->rightClassName, $this->rightColumnName, $this->leftColumnName, $left);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $right
	 * @return \traversable
	 *
	 * @throws MyStorageError
	 */
	public function getAllByRight($right) : \traversable {
		try{
			yield from $this->getAllByColumn($this->leftClassName, $this->leftColumnName, $this->rightColumnName, $right);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param string $dataClassName
	 * @param string $dataColumn
	 * @param string $byColumn
	 * @param string $byValue
	 * @return \Generator
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function getAllByColumn(
		string $dataClassName,
		string $dataColumn,
		string $byColumn,
		string $byValue
	) : \Generator {
		static $q1;
		if($q1 === null){
			$q1 = $this->dbi->prepare('SELECT HEX({i:2}) {i:2} FROM {i:1} WHERE {i:3}=UNHEX({s:4})');
		}
		$it = $this->dbi->select($q1,
			$this->tableName,
			$dataColumn,
			$byColumn,
			$byValue);
		foreach($it as $rec){
			/** @noinspection PhpUndefinedMethodInspection */
			$x = $dataClassName::deserialize($rec[$dataColumn]);
			yield $x;
		}
	}

	/**
	 * @param $left
	 * @param $right
	 *
	 * @throws MyStorageError
	 */
	public function add($left, $right) : void {
		static $q1;
		try{
			if($q1 === null){
				$q1 = $this->dbi->prepare('INSERT IGNORE INTO {i} ({i}, {i}) VALUES (UNHEX({s}), UNHEX({s}))');
			}
			$this->dbi->update($q1,
				$this->tableName,
				$this->leftColumnName,
				$this->rightColumnName,
				(string)$left,
				(string)$right);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $left
	 * @param $right
	 *
	 * @throws MyStorageError
	 */
	public function remove($left, $right) : void {
		static $q1;
		try{
			if($q1 === null){
				$q1 = $this->dbi->prepare('DELETE FROM {i} WHERE {i}=UNHEX({s}) AND {i}=UNHEX({s})');
			}
			$this->dbi->update($q1,
				$this->tableName,
				$this->leftColumnName,
				(string)$left,
				$this->rightColumnName,
				(string)$right);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $left
	 *
	 * @throws MyStorageError
	 */
	public function clearLeft($left) : void {
		try{
			$this->clearByColumn($this->leftColumnName, $left);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $right
	 *
	 * @throws MyStorageError
	 */
	public function clearRight($right) : void {
		try{
			$this->clearByColumn($this->rightColumnName, $right);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param string $column
	 * @param string $value
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function clearByColumn(string $column, string $value) : void {
		static $q1;
		if($q1 === null){
			$q1 = $this->dbi->prepare('DELETE FROM {i} WHERE {i}=UNHEX({s})');
		}
		$this->dbi->update($q1, $this->tableName, $column, $value);
	}

	public function reset() : void {
		$this->dbi->query('DROP TABLE IF EXISTS {i}', $this->tableName);
		$tmp = $this->temporary ? 'TEMPORARY' : '';
		$this->dbi->query("CREATE $tmp TABLE {i:1} (
			seq_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			{i:2} BINARY(16) NOT NULL,
			{i:3} BINARY(16) NOT NULL,
			store_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE({i:2}, {i:3}),
			UNIQUE({i:3}, {i:2})
		) ENGINE=InnoDB", $this->tableName, $this->leftColumnName, $this->rightColumnName);
	}

}
