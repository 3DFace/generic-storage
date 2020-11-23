<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorage;
use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageBatched10Test extends GenericStorageTest
{

	protected function getIdColumnLength() : int
	{
		return 32;
	}

	protected function setUp() : void
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setIdExtracted(true)
			->setIdColumnDef('BINARY(32)')
			->setRevisionPropertyName('revision')
			->addColumns([
				'email' => ['type' => 'VARCHAR(128)', 'mode' => MyStorage::COLUMN_MODE_SEPARATED],
				'data/a' => 'VARCHAR(128)',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(10)
			->setIdBatchSize(10)
			->setTemporary(false)
			->build();
		$this->storage->reset();
	}

}
