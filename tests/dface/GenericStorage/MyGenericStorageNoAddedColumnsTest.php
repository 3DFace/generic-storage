<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageNoAddedColumnsTest extends GenericStorageTest
{

	protected function setUp() : void
	{
		$linkProvider = LinkProviderFactory::createLinkProvider();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $linkProvider, 'test_gen_storage'))
			->setIdPropertyName('id')
			->setRevisionPropertyName('revision')
			->setBatchListSize(10)
			->setIdBatchSize(10)
			->setTemporary(false)
			->setDataMaxSize(65535)
			->setCompressed(true)
			->build();
		$this->storage->reset();
	}

}
