<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\GenericManyToMany;
use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;
use dface\sql\placeholders\PlainNode;

class MyManyToMany implements GenericManyToMany {

	/** @var \mysqli */
	private $link;
	/** @var MysqliConnection */
	private $dbi;
	/** @var string */
	private $tableName;
	/** @var string */
	private $tableNameEscaped;
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
		\mysqli $link,
		string $tableName,
		string $leftClassName,
		string $rightClassName,
		string $leftColumnName = 'left',
		string $rightColumnName = 'right',
		bool $temporary = false
	) {
		$this->link = $link;
		$this->dbi = new MysqliConnection($link, new DefaultParser(), new DefaultFormatter());
		$this->tableName = $tableName;
		$this->tableNameEscaped = str_replace('`', '``', $tableName);
		$this->leftClassName = $leftClassName;
		$this->rightClassName = $rightClassName;
		$this->leftColumnName = $leftColumnName;
		$this->rightColumnName = $rightColumnName;
		$this->temporary = $temporary;
	}

	/**
	 * @param $left
	 * @return \traversable
	 * @throws UnderlyingStorageError
	 */
	public function getAllByLeft($left) : \traversable {
		try{
			yield from $this->getAllByColumn($this->rightClassName, $this->rightColumnName, $this->leftColumnName, $left);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $right
	 * @return \traversable
	 *
	 * @throws UnderlyingStorageError
	 */
	public function getAllByRight($right) : \traversable {
		try{
			yield from $this->getAllByColumn($this->leftClassName, $this->leftColumnName, $this->rightColumnName, $right);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
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
		$e_data_col = str_replace('`', '``', $dataColumn);
		$e_by_col = str_replace('`', '``', $byColumn);
		$e_by_val = $this->link->real_escape_string($byValue);
		/** @noinspection SqlResolve */
		$q1 = new PlainNode(0, "SELECT HEX(`$e_data_col`) `$e_data_col` FROM `$this->tableNameEscaped` WHERE `$e_by_col`=UNHEX('$e_by_val')");
		$it = $this->dbi->select($q1);
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
	 * @throws UnderlyingStorageError
	 */
	public function add($left, $right) : void {
		try{
			$e_left_col = str_replace('`', '``', $this->leftColumnName);
			$e_right_col = str_replace('`', '``', $this->rightColumnName);
			$e_left_val = $this->link->real_escape_string($left);
			$e_right_val = $this->link->real_escape_string($right);
			/** @noinspection SqlResolve */
			$q1 = new PlainNode(0, "INSERT IGNORE INTO `$this->tableNameEscaped` (`$e_left_col`, `$e_right_col`) VALUES (UNHEX('$e_left_val'), UNHEX('$e_right_val'))");
			$this->dbi->update($q1);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $left
	 * @param $right
	 *
	 * @throws UnderlyingStorageError
	 */
	public function remove($left, $right) : void {
		try{
			$e_left_col = str_replace('`', '``', $this->leftColumnName);
			$e_right_col = str_replace('`', '``', $this->rightColumnName);
			$e_left_val = $this->link->real_escape_string($left);
			$e_right_val = $this->link->real_escape_string($right);
			/** @noinspection SqlResolve */
			$q1 = new PlainNode(0, "DELETE FROM `$this->tableNameEscaped` WHERE `$e_left_col`=UNHEX('$e_left_val') AND `$e_right_col`=UNHEX('$e_right_val')");
			$this->dbi->update($q1);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $left
	 *
	 * @throws UnderlyingStorageError
	 */
	public function clearLeft($left) : void {
		try{
			$this->clearByColumn($this->leftColumnName, $left);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $right
	 *
	 * @throws UnderlyingStorageError
	 */
	public function clearRight($right) : void {
		try{
			$this->clearByColumn($this->rightColumnName, $right);
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param string $column
	 * @param string $value
	 *
	 * @throws MysqlException|FormatterException|ParserException
	 */
	private function clearByColumn(string $column, string $value) : void {
		$e_col = str_replace('`', '``', $column);
		$e_val = $this->link->real_escape_string($value);
		/** @noinspection SqlResolve */
		$q1 = new PlainNode(0, "DELETE FROM `$this->tableNameEscaped` WHERE `$e_col`=UNHEX('$e_val')");
		$this->dbi->update($q1);
	}

	/**
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	public function reset() : void {
		$this->dbi->query('DROP TABLE IF EXISTS {i}', $this->tableName);
		$tmp = $this->temporary ? 'TEMPORARY' : '';
		$this->dbi->query("CREATE $tmp TABLE {i:1} (
			`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			{i:2} BINARY(16) NOT NULL,
			{i:3} BINARY(16) NOT NULL,
			`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE({i:2}, {i:3}),
			UNIQUE({i:3}, {i:2})
		) ENGINE=InnoDB", $this->tableName, $this->leftColumnName, $this->rightColumnName);
	}

}
