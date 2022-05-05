<?php

namespace dface\GenericStorage\Mysql;

class MyStorageBuilder
{

	private MyLinkProvider $linkProvider;
	private MyJsonStorageBuilder $builder;

	public function __construct(string $className, MyLinkProvider $link, string $tableName)
	{
		$this->linkProvider = $link;
		$this->builder = new MyJsonStorageBuilder($className, $tableName);
	}

	public function setIdPropertyName(string $idPropertyName) : self
	{
		$this->builder->setIdPropertyName($idPropertyName);
		return $this;
	}

	public function setIdExtracted(bool $extracted) : self
	{
		$this->builder->setIdExtracted($extracted);
		return $this;
	}

	public function setIdColumnDef(string $idColumnDef) : self
	{
		$this->builder->setIdColumnDef($idColumnDef);
		return $this;
	}

	public function setRevisionPropertyName(string $revisionPropertyName) : self
	{
		$this->builder->setRevisionPropertyName($revisionPropertyName);
		return $this;
	}

	public function setSeqIdPropertyName(string $seqIdPropertyName) : self
	{
		$this->builder->setSeqIdPropertyName($seqIdPropertyName);
		return $this;
	}

	public function addColumns(array $add_columns) : self
	{
		$this->builder->addColumns($add_columns);
		return $this;
	}

	public function addGeneratedColumns(array $add_generated_columns) : self
	{
		$this->builder->addGeneratedColumns($add_generated_columns);
		return $this;
	}

	public function addIndexes(array $add_indexes) : self
	{
		$this->builder->addIndexes($add_indexes);
		return $this;
	}

	public function setHasUniqueSecondary(bool $has_unique_secondary) : self
	{
		$this->builder->setHasUniqueSecondary($has_unique_secondary);
		return $this;
	}

	public function setTemporary(bool $temporary) : self
	{
		$this->builder->setTemporary($temporary);
		return $this;
	}

	public function setBatchListSize(int $batchListSize) : self
	{
		$this->builder->setBatchListSize($batchListSize);
		return $this;
	}

	public function setIdBatchSize(int $idBatchSize) : self
	{
		$this->builder->setIdBatchSize($idBatchSize);
		return $this;
	}

	public function setDataColumnDef(string $dataColumnDef) : self
	{
		$this->builder->setDataColumnDef($dataColumnDef);
		return $this;
	}

	public function setDataMaxSize(int $dataMaxSize) : self
	{
		$this->builder->setDataMaxSize($dataMaxSize);
		return $this;
	}

	public function setCompressed(bool $compressed) : self
	{
		$this->builder->setCompressed($compressed);
		return $this;
	}

	/**
	 * @return MyStorage
	 * @throws \InvalidArgumentException
	 */
	public function build() : MyStorage
	{
		$storage = $this->builder->build();
		return new MyStorage($storage, $this->linkProvider);
	}

}
