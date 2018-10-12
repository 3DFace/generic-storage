<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\GenericSet;

class MySet implements GenericSet
{

	/** @var MyLinkProvider */
	private $linkProvider;
	/** @var string */
	private $tableNameEscaped;
	/** @var string */
	private $className;
	/** @var bool */
	private $temporary;

	public function __construct(
		MyLinkProvider $linkProvider,
		string $tableName,
		string $className,
		bool $temporary
	) {
		$this->linkProvider = $linkProvider;
		$this->tableNameEscaped = str_replace('`', '``', $tableName);
		$this->className = $className;
		$this->temporary = $temporary;
	}

	/**
	 * @param $id
	 * @return bool
	 */
	public function contains($id) : bool
	{
		return $this->linkProvider->withLink(function (\mysqli $link) use ($id) {
			$e_id = $link->real_escape_string($id);
			/** @noinspection SqlResolve */
			$q1 = "SELECT 1 FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')";
			$res = MyFun::query($link, $q1);
			return $res->fetch_row() !== null;
		});
	}

	/**
	 * @param $id
	 */
	public function add($id) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($id) {
			$e_id = $link->real_escape_string($id);
			/** @noinspection SqlResolve */
			$q1 = "INSERT IGNORE INTO `$this->tableNameEscaped` (`\$id`) VALUES (UNHEX('$e_id'))";
			MyFun::query($link, $q1);
		});
	}

	/**
	 * @param $id
	 */
	public function remove($id) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($id) {
			$e_id = $link->real_escape_string($id);
			/** @noinspection SqlResolve */
			$q1 = "DELETE FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')";
			MyFun::query($link, $q1);
		});
	}

	/**
	 * @return \traversable
	 */
	public function iterate() : \traversable
	{
		return $this->linkProvider->withLink(function (\mysqli $link) {
			/** @noinspection SqlResolve */
			$q1 = "SELECT HEX(`\$id`) `\$id` FROM `$this->tableNameEscaped`";
			$it = MyFun::query($link, $q1);
			$className = $this->className;
			foreach ($it as $rec) {
				/** @noinspection PhpUndefinedMethodInspection */
				$x = $className::deserialize($rec['$id']);
				yield $x;
			}
		});
	}

	public function reset() : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) {
			MyFun::query($link, "DROP TABLE IF EXISTS `$this->tableNameEscaped`");
			$tmp = $this->temporary ? 'TEMPORARY' : '';
			$q1 = "CREATE $tmp TABLE `$this->tableNameEscaped` (
				`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				`\$id` BINARY(16) NOT NULL UNIQUE,
				`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB";
			MyFun::query($link, $q1);
		});
	}

}
