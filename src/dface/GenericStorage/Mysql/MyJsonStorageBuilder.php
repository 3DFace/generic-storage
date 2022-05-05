<?php

namespace dface\GenericStorage\Mysql;

class MyJsonStorageBuilder
{

	private string $className;
	private string $tableName;
	private ?string $idPropertyName = null;
	private bool $idExtracted = false;
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

	public function __construct(string $className, string $tableName)
	{
		$this->className = $className;
		$this->tableName = $tableName;
	}

	public function setIdPropertyName(string $idPropertyName) : self
	{
		$this->idPropertyName = $idPropertyName;
		return $this;
	}

	public function setIdExtracted(bool $extracted) : self
	{
		$this->idExtracted = $extracted;
		return $this;
	}

	public function setIdColumnDef(string $idColumnDef) : self
	{
		$this->idColumnDef = $idColumnDef;
		return $this;
	}

	public function setRevisionPropertyName(string $revisionPropertyName) : self
	{
		$this->revisionPropertyName = $revisionPropertyName;
		return $this;
	}

	public function setSeqIdPropertyName(string $seqIdPropertyName) : self
	{
		$this->seqIdPropertyName = $seqIdPropertyName;
		return $this;
	}

	public function addColumns(array $add_columns) : self
	{
		$this->add_columns = $add_columns;
		return $this;
	}

	public function addGeneratedColumns(array $add_generated_columns) : self
	{
		$this->add_generated_columns = $add_generated_columns;
		return $this;
	}

	public function addIndexes(array $add_indexes) : self
	{
		$this->add_indexes = $add_indexes;
		return $this;
	}

	public function setHasUniqueSecondary(bool $has_unique_secondary) : self
	{
		$this->has_unique_secondary = $has_unique_secondary;
		return $this;
	}

	public function setTemporary(bool $temporary) : self
	{
		$this->temporary = $temporary;
		return $this;
	}

	public function setBatchListSize(int $batchListSize) : self
	{
		$this->batchListSize = $batchListSize;
		return $this;
	}

	public function setIdBatchSize(int $idBatchSize) : self
	{
		$this->idBatchSize = $idBatchSize;
		return $this;
	}

	public function setDataColumnDef(string $dataColumnDef) : self
	{
		$this->dataColumnDef = $dataColumnDef;
		return $this;
	}

	public function setDataMaxSize(int $dataMaxSize) : self
	{
		$this->dataMaxSize = $dataMaxSize;
		return $this;
	}

	public function setCompressed(bool $compressed) : self
	{
		$this->compressed = $compressed;
		return $this;
	}

	public function build() : MyJsonStorage
	{
		return new MyJsonStorage(
			$this->className,
			$this->tableName,
			$this->idPropertyName,
			$this->idColumnDef,
			$this->idExtracted,
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
