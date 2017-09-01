<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\GenericSet;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;
use dface\sql\placeholders\PlainNode;

class MySet implements GenericSet {

	/** @var \mysqli */
	private $link;
	/** @var MysqliConnection */
	private $dbi;
	/** @var string */
	private $tableName;
	/** @var string */
	private $tableNameEscaped;
	/** @var string */
	private $className;
	/** @var bool */
	private $temporary;

	public function __construct(
		\mysqli $link,
		string $tableName,
		string $className,
		bool $temporary
	) {
		$this->link = $link;
		$this->dbi = new MysqliConnection($link, new DefaultParser(), new DefaultFormatter());
		$this->tableName = $tableName;
		$this->tableNameEscaped = str_replace('`', '``', $tableName);
		$this->className = $className;
		$this->temporary = $temporary;
	}

	/**
	 * @param $id
	 * @return bool
	 *
	 * @throws MyStorageError
	 */
	public function contains($id) : bool {
		try{
			$e_id = $this->link->real_escape_string($id);
			/** @noinspection SqlResolve */
			return $this->dbi->select(new PlainNode(0, "SELECT 1 FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')"))->getValue() !== null;
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $id
	 *
	 * @throws MyStorageError
	 */
	public function add($id) : void {
		try{
			$e_id = $this->link->real_escape_string($id);
			/** @noinspection SqlResolve */
			$this->dbi->update(new PlainNode(0, "INSERT IGNORE INTO `$this->tableNameEscaped` (`\$id`) VALUES (UNHEX('$e_id'))"));
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param $id
	 *
	 * @throws MyStorageError
	 */
	public function remove($id) : void {
		try{
			$e_id = $this->link->real_escape_string($id);
			/** @noinspection SqlResolve */
			$this->dbi->update(new PlainNode(0, "DELETE FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')"));
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @return \traversable
	 *
	 * @throws MyStorageError
	 */
	public function iterate() : \traversable {
		try{
			/** @noinspection SqlResolve */
			$it = $this->dbi->select(new PlainNode(0, "SELECT HEX(`\$id`) `\$id` FROM `$this->tableNameEscaped`"));
			$className = $this->className;
			foreach($it as $rec){
				/** @noinspection PhpUndefinedMethodInspection */
				$x = $className::deserialize($rec['$id']);
				yield $x;
			}
		}catch(MysqlException|FormatterException|ParserException $e){
			throw new MyStorageError($e->getMessage(), 0, $e);
		}
	}

	public function reset() : void {
		$this->dbi->query('DROP TABLE IF EXISTS {i}', $this->tableName);
		$tmp = $this->temporary ? 'TEMPORARY' : '';
		$this->dbi->query("CREATE $tmp TABLE {i} (
			`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			`\$id` BINARY(16) NOT NULL UNIQUE,
			`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) ENGINE=InnoDB", $this->tableName);
	}

}
