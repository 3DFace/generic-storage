<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\Mysql\MysqliConnection;

class MyStorageBuilder {

	/** @var string */
	private $className;
	/** @var MysqliConnection */
	private $dbi;
	/** @var string */
	private $tableName;

	/** @var string */
	private $idColumnType = 'CHAR(32) CHARACTER SET ASCII';
	/** @var string */
	private $idPropertyName;
	/** @var string[] */
	private $add_columns = [];
	/** @var string[] */
	private $add_indexes = [];
	/** @var bool */
	private $has_unique_secondary;
	/** @var callable */
	private $dedicatedConnectionFactory;
	/** @var bool */
	private $temporary;

	/**
	 * MyStorageBuilder constructor.
	 * @param string $className
	 * @param MysqliConnection $dbi
	 * @param string $tableName
	 */
	public function __construct($className, MysqliConnection $dbi, $tableName) {
		$this->className = $className;
		$this->dbi = $dbi;
		$this->tableName = $tableName;
	}

	public function setIdColumnType($idColumnType) : MyStorageBuilder {
		$this->idColumnType = $idColumnType;
		return $this;
	}

	public function setIdPropertyName($idPropertyName) : MyStorageBuilder {
		$this->idPropertyName = $idPropertyName;
		return $this;
	}

	public function addColumns(array $add_columns) : MyStorageBuilder {
		$this->add_columns = $add_columns;
		return $this;
	}

	public function addIndexes(array $add_indexes) : MyStorageBuilder {
		$this->add_indexes = $add_indexes;
		return $this;
	}

	public function setHasUniqueSecondary(bool $has_unique_secondary) : MyStorageBuilder {
		$this->has_unique_secondary = $has_unique_secondary;
		return $this;
	}

	public function setDedicatedConnectionFactory($dedicatedConnectionFactory) : MyStorageBuilder {
		$this->dedicatedConnectionFactory = $dedicatedConnectionFactory;
		return $this;
	}

	public function setTemporary(bool $temporary) : MyStorageBuilder {
		$this->temporary = $temporary;
		return $this;
	}

	public function build() : MyStorage {
		return new MyStorage(
			$this->className,
			$this->dbi,
			$this->tableName,
			$this->dedicatedConnectionFactory,
			$this->idColumnType,
			$this->idPropertyName,
			$this->add_columns,
			$this->add_indexes,
			$this->has_unique_secondary,
			$this->temporary
		);
	}

}
