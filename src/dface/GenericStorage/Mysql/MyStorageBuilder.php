<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

class MyStorageBuilder {

	/** @var string */
	private $className;
	/** @var \mysqli */
	private $link;
	/** @var string */
	private $tableName;

	/** @var string */
	private $idPropertyName;
	/** @var string[] */
	private $add_columns = [];
	/** @var string[] */
	private $add_indexes = [];
	/** @var bool */
	private $has_unique_secondary = false;
	/** @var callable */
	private $dedicatedConnectionFactory;
	/** @var bool */
	private $temporary = false;
	/** @var int */
	private $batchListSize = 10000;
	/** @var int */
	private $idBatchSize = 500;
	/** @var string|null */
	private $dbCharset;
	/** @var string|null */
	private $projectCharset;

	public function __construct(string $className, \mysqli $link, string $tableName) {
		$this->className = $className;
		$this->link = $link;
		$this->tableName = $tableName;
	}

	public function setIdPropertyName(?string $idPropertyName) : MyStorageBuilder {
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

	public function setBatchListSize(int $batchListSize) : MyStorageBuilder {
		$this->batchListSize = $batchListSize;
		return $this;
	}

	public function setIdBatchSize(int $idBatchSize) : MyStorageBuilder {
		$this->idBatchSize = $idBatchSize;
		return $this;
	}

	public function setDbCharset(?string $dbCharset) : MyStorageBuilder {
		$this->dbCharset = $dbCharset;
		return $this;
	}

	public function setProjectCharset(?string $projectCharset) : MyStorageBuilder {
		$this->projectCharset = $projectCharset;
		return $this;
	}

	public function build() : MyStorage {
		return new MyStorage(
			$this->className,
			$this->link,
			$this->tableName,
			$this->dedicatedConnectionFactory,
			$this->idPropertyName,
			$this->add_columns,
			$this->add_indexes,
			$this->has_unique_secondary,
			$this->temporary,
			$this->batchListSize,
			$this->idBatchSize,
			$this->dbCharset,
			$this->projectCharset);
	}

}
