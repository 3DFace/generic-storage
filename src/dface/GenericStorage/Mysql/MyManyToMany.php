<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\GenericManyToMany;
use dface\GenericStorage\Generic\UnderlyingStorageError;

class MyManyToMany implements GenericManyToMany
{

	/** @var MyLinkProvider */
	private $linkProvider;
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
	/** @var string */
	private $leftColumnNameEscaped;
	/** @var string */
	private $rightColumnNameEscaped;
	/** @var bool */
	private $temporary;

	public function __construct(
		MyLinkProvider $linkProvider,
		string $tableName,
		string $leftClassName,
		string $rightClassName,
		string $leftColumnName = 'left',
		string $rightColumnName = 'right',
		bool $temporary = false
	) {
		$this->linkProvider = $linkProvider;
		$this->tableNameEscaped = str_replace('`', '``', $tableName);
		$this->leftClassName = $leftClassName;
		$this->rightClassName = $rightClassName;
		$this->leftColumnName = $leftColumnName;
		$this->rightColumnName = $rightColumnName;
		$this->leftColumnNameEscaped = str_replace('`', '``', $leftColumnName);
		$this->rightColumnNameEscaped = str_replace('`', '``', $rightColumnName);
		$this->temporary = $temporary;
	}

	/**
	 * @param $left
	 * @return \traversable
	 */
	public function getAllByLeft($left) : \traversable
	{
		return $this->linkProvider->withLink(function (\mysqli $link) use ($left) {
			yield from $this->getAllByColumn($link, true, $left);
		});
	}

	/**
	 * @param $right
	 * @return \traversable
	 */
	public function getAllByRight($right) : \traversable
	{
		return $this->linkProvider->withLink(function (\mysqli $link) use ($right) {
			yield from $this->getAllByColumn($link, false, $right);
		});
	}

	/**
	 * @param \mysqli $link
	 * @param bool $byLeft
	 * @param string $byValue
	 * @return \Generator
	 * @throws UnderlyingStorageError
	 */
	private function getAllByColumn(
		\mysqli $link,
		bool $byLeft,
		string $byValue
	) : \Generator {
		if ($byLeft) {
			$e_data_col = $this->rightColumnNameEscaped;
			$e_by_col = $this->leftColumnNameEscaped;
			$dataColumn = $this->rightColumnName;
			$dataClassName = $this->rightClassName;
		}else {
			$e_data_col = $this->leftColumnNameEscaped;
			$e_by_col = $this->rightColumnNameEscaped;
			$dataColumn = $this->leftColumnName;
			$dataClassName = $this->leftClassName;
		}
		$e_by_val = $link->real_escape_string($byValue);
		/** @noinspection SqlResolve */
		$q1 = "SELECT HEX(`$e_data_col`) `$e_data_col` FROM `$this->tableNameEscaped` WHERE `$e_by_col`=UNHEX('$e_by_val')";
		$it = MyFun::query($link, $q1);
		foreach ($it as $rec) {
			/** @noinspection PhpUndefinedMethodInspection */
			$x = $dataClassName::deserialize($rec[$dataColumn]);
			yield $x;
		}
	}

	/**
	 * @param $left
	 * @param $right
	 * @return bool
	 */
	public function has($left, $right) : bool
	{
		return $this->linkProvider->withLink(function (\mysqli $link) use ($left, $right) {
			$e_left_col = str_replace('`', '``', $this->leftColumnName);
			$e_right_col = str_replace('`', '``', $this->rightColumnName);
			$e_left_val = $link->real_escape_string($left);
			$e_right_val = $link->real_escape_string($right);
			/** @noinspection SqlResolve */
			$q1 = "SELECT 1 FROM `$this->tableNameEscaped`
				WHERE `$e_left_col`=UNHEX('$e_left_val') AND `$e_right_col`=UNHEX('$e_right_val')";
			$res = MyFun::query($link, $q1);
			return $res->fetch_row() !== null;
		});
	}

	/**
	 * @param $left
	 * @param $right
	 */
	public function add($left, $right) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($left, $right) {
			$e_left_col = str_replace('`', '``', $this->leftColumnName);
			$e_right_col = str_replace('`', '``', $this->rightColumnName);
			$e_left_val = $link->real_escape_string($left);
			$e_right_val = $link->real_escape_string($right);
			/** @noinspection SqlResolve */
			$q1 = "INSERT IGNORE INTO `$this->tableNameEscaped` (`$e_left_col`, `$e_right_col`)
 					VALUES (UNHEX('$e_left_val'), UNHEX('$e_right_val'))";
			MyFun::query($link, $q1);
		});
	}

	/**
	 * @param $left
	 * @param $right
	 */
	public function remove($left, $right) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($left, $right) {
			$e_left_col = str_replace('`', '``', $this->leftColumnName);
			$e_right_col = str_replace('`', '``', $this->rightColumnName);
			$e_left_val = $link->real_escape_string($left);
			$e_right_val = $link->real_escape_string($right);
			/** @noinspection SqlResolve */
			$q1 = "DELETE FROM `$this->tableNameEscaped` 
				WHERE `$e_left_col`=UNHEX('$e_left_val') AND `$e_right_col`=UNHEX('$e_right_val')";
			MyFun::query($link, $q1);
		});
	}

	/**
	 * @param $left
	 */
	public function clearLeft($left) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($left) {
			$this->clearByColumn($link, $this->leftColumnName, $left);
		});
	}

	/**
	 * @param $right
	 */
	public function clearRight($right) : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) use ($right) {
			$this->clearByColumn($link, $this->rightColumnName, $right);
		});
	}

	/**
	 * @param \mysqli $link
	 * @param string $column
	 * @param string $value
	 * @throws UnderlyingStorageError
	 */
	private function clearByColumn(\mysqli $link, string $column, string $value) : void
	{
		$e_col = str_replace('`', '``', $column);
		$e_val = $link->real_escape_string($value);
		/** @noinspection SqlResolve */
		MyFun::query($link, "DELETE FROM `$this->tableNameEscaped` WHERE `$e_col`=UNHEX('$e_val')");
	}

	public function reset() : void
	{
		$this->linkProvider->withLink(function (\mysqli $link) {
			$e_left_col = str_replace('`', '``', $this->leftColumnName);
			$e_right_col = str_replace('`', '``', $this->rightColumnName);
			MyFun::query($link, "DROP TABLE IF EXISTS `$this->tableNameEscaped`");
			$tmp = $this->temporary ? 'TEMPORARY' : '';
			$q1 = "CREATE $tmp TABLE `$this->tableNameEscaped` (
				`\$seq_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				`$e_left_col` BINARY(16) NOT NULL,
				`$e_right_col` BINARY(16) NOT NULL,
				`\$store_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE(`$e_left_col`, `$e_right_col`),
				UNIQUE(`$e_right_col`, `$e_left_col`)
			) ENGINE=InnoDB";
			MyFun::query($link, $q1);
		});
	}

}
