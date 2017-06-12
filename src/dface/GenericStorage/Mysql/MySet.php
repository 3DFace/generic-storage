<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\GenericSet;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;

class MySet implements GenericSet {

	/** @var MysqliConnection */
	private $dbi;
	/** @var string */
	private $tableName;
	/** @var string */
	private $className;
	/** @var bool */
	private $temporary;

	public function __construct(
		MysqliConnection $dbi,
		string $tableName,
		string $className,
		bool $temporary
	) {
		$this->dbi = $dbi;
		$this->tableName = $tableName;
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
		static $q1;
		try{
			if($q1 === null){
				/** @noinspection SqlResolve */
				$q1 = $this->dbi->prepare('SELECT 1 FROM {i} WHERE `id`=UNHEX({s})');
			}
			return $this->dbi->select($q1, $this->tableName, (string)$id)->getValue() !== null;
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
		static $q1;
		try{
			if($q1 === null){
				/** @noinspection SqlResolve */
				$q1 = $this->dbi->prepare('INSERT IGNORE INTO {i} (`id`) VALUES (UNHEX({s}))');
			}
			$this->dbi->update($q1, $this->tableName, (string)$id);
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
		static $q1;
		try{
			if($q1 === null){
				/** @noinspection SqlResolve */
				$q1 = $this->dbi->prepare('DELETE FROM {i} WHERE `id`=UNHEX({s})');
			}
			$this->dbi->update($q1, $this->tableName, (string)$id);
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
		static $q1;
		try{
			if($q1 === null){
				/** @noinspection SqlResolve */
				$q1 = $this->dbi->prepare('SELECT HEX(`id`) `id` FROM {i}');
			}
			$it = $this->dbi->select($q1, $this->tableName);
			$className = $this->className;
			foreach($it as $rec){
				/** @noinspection PhpUndefinedMethodInspection */
				$x = $className::deserialize($rec['id']);
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
			seq_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			`id` BINARY(16) NOT NULL UNIQUE,
			store_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) ENGINE=InnoDB", $this->tableName);
	}

}
