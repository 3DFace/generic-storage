<?php

namespace dface\GenericStorage\Mysql;

class MyStorageBuilder
{

	private string $className;
	private MyLinkProvider $linkProvider;
	private string $tableName;
	private ?string $idPropertyName = null;
	private string $idColumnDef = 'BINARY(16)';
	private ?string $revisionPropertyName = null;
	private ?string $seqIdPropertyName = null;
	/** @var string[] */
	private array $add_generated_columns = [];
	/** @var string[] */
	private array $add_columns = [];
	/** @var string[] */
	private array $add_indexes = [];
	private bool $has_unique_secondary = false;
	private bool $temporary = false;
	private int $batchListSize = 10000;
	private int $idBatchSize = 500;
	private string $dataColumnDef = 'TEXT';
	private int $dataMaxSize = 65535;
	private bool $compressed = true;

	public function __construct($className, MyLinkProvider $link, $tableName)
	{
		$this->className = $className;
		$this->linkProvider = $link;
		$this->tableName = $tableName;
	}

	public function setIdPropertyName(string $idPropertyName) : MyStorageBuilder
	{
		$this->idPropertyName = $idPropertyName;
		return $this;
	}

	public function setIdColumnDef(string $idColumnDef) : MyStorageBuilder
	{
		$this->idColumnDef = $idColumnDef;
		return $this;
	}

	public function setRevisionPropertyName(string $revisionPropertyName) : MyStorageBuilder
	{
		$this->revisionPropertyName = $revisionPropertyName;
		return $this;
	}

	public function setSeqIdPropertyName(string $seqIdPropertyName) : MyStorageBuilder
	{
		$this->seqIdPropertyName = $seqIdPropertyName;
		return $this;
	}

	public function addColumns(array $add_columns) : MyStorageBuilder
	{
		$this->add_columns = $add_columns;
		return $this;
	}

	public function addGeneratedColumns(array $add_generated_columns) : MyStorageBuilder
	{
		$this->add_generated_columns = $add_generated_columns;
		return $this;
	}

	public function addIndexes(array $add_indexes) : MyStorageBuilder
	{
		$this->add_indexes = $add_indexes;
		return $this;
	}

	public function setHasUniqueSecondary(bool $has_unique_secondary) : MyStorageBuilder
	{
		$this->has_unique_secondary = $has_unique_secondary;
		return $this;
	}

	public function setTemporary(bool $temporary) : MyStorageBuilder
	{
		$this->temporary = $temporary;
		return $this;
	}

	public function setBatchListSize(int $batchListSize) : MyStorageBuilder
	{
		$this->batchListSize = $batchListSize;
		return $this;
	}

	public function setIdBatchSize(int $idBatchSize) : MyStorageBuilder
	{
		$this->idBatchSize = $idBatchSize;
		return $this;
	}

	public function setDataColumnDef(string $dataColumnDef) : MyStorageBuilder
	{
		$this->dataColumnDef = $dataColumnDef;
		return $this;
	}

	public function setDataMaxSize(int $dataMaxSize) : MyStorageBuilder
	{
		$this->dataMaxSize = $dataMaxSize;
		return $this;
	}

	public function setCompressed(bool $compressed) : MyStorageBuilder
	{
		$this->compressed = $compressed;
		return $this;
	}

	/**
	 * @return MyStorage
	 * @throws \InvalidArgumentException
	 */
	public function build() : MyStorage
	{
		return new MyStorage(
			$this->className,
			$this->linkProvider,
			$this->tableName,
			$this->idPropertyName,
			$this->idColumnDef,
			$this->revisionPropertyName,
			$this->seqIdPropertyName,
			$this->add_generated_columns,
			$this->add_columns,
			$this->add_indexes,
			$this->has_unique_secondary,
			$this->temporary,
			$this->batchListSize,
			$this->idBatchSize,
			$this->dataColumnDef,
			$this->dataMaxSize,
			$this->compressed);
	}

}
