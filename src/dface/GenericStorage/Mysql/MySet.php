<?php

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\GenericSet;

class MySet implements GenericSet
{

	private MyLinkProvider $linkProvider;
	private string $tableNameEscaped;
	private string $className;
	private bool $temporary;

	public function __construct(
		MyLinkProvider $linkProvider,
		string $tableName,
		string $className,
		bool $temporary
	) {
		$this->linkProvider = $linkProvider;
		$this->tableNameEscaped = \str_replace('`', '``', $tableName);
		$this->className = $className;
		$this->temporary = $temporary;
	}

	/**
	 * @param $id
	 * @return bool
	 */
	public function contains($id) : bool
	{
		return $this->linkProvider->withLink(function (MyLink $link) use ($id) {
			$e_id = $link->escapeString($id);
			/** @noinspection SqlResolve */
			$q1 = "SELECT 1 FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')";
			$res = $link->query($q1);
			return $res->fetchRow() !== null;
		});
	}

	/**
	 * @param $id
	 */
	public function add($id) : void
	{
		$this->linkProvider->withLink(function (MyLink $link) use ($id) {
			$e_id = $link->escapeString($id);
			/** @noinspection SqlResolve */
			$link->command("INSERT IGNORE INTO `$this->tableNameEscaped` (`\$id`) VALUES (UNHEX('$e_id'))");
		});
	}

	/**
	 * @param $id
	 */
	public function remove($id) : void
	{
		$this->linkProvider->withLink(function (MyLink $link) use ($id) {
			$e_id = $link->escapeString($id);
			/** @noinspection SqlResolve */
			$link->command("DELETE FROM `$this->tableNameEscaped` WHERE `\$id`=UNHEX('$e_id')");
		});
	}

	public function clear() : void
	{
		$this->linkProvider->withLink(function (MyLink $link) {
			/** @noinspection SqlResolve */
			$link->command("DELETE FROM `$this->tableNameEscaped`");
		});
	}

	/**
	 * @return iterable
	 */
	public function iterate() : iterable
	{
		return $this->linkProvider->withLink(function (MyLink $link) {
			/** @noinspection SqlResolve */
			$it = $link->query("SELECT HEX(`\$id`) `\$id` FROM `$this->tableNameEscaped`")->iterate();
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
		$this->linkProvider->withLink(function (MyLink $link) {
			$link->command("DROP TABLE IF EXISTS `$this->tableNameEscaped`");
			$tmp = $this->temporary ? 'TEMPORARY' : '';
			$q1 = "CREATE $tmp TABLE `$this->tableNameEscaped` (
				`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				`\$id` BINARY(16) NOT NULL UNIQUE,
				`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB";
			$link->command($q1);
		});
	}

}
