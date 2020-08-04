<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageBatched2Test extends GenericStorageTest
{

	protected function setUp() : void
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->seq_id_injected = true;
		$this->storage = (new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setSeqIdPropertyName('seq_id')
			->setRevisionPropertyName('revision')
			->addColumns([
				'email' => 'VARCHAR(128)',
				'data/a' => 'VARCHAR(128)',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(2)
			->setIdBatchSize(2)
			->setTemporary(true)
			->build();
		$this->storage->reset();
	}

}
