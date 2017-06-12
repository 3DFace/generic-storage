<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorage;
use dface\GenericStorage\Generic\GenericStorage;

class MyGenericStorageTest extends GenericStorageTest {

	protected function createStorage() : GenericStorage{
		$dbi = DbiFactory::getConnection();
		$dbiFac = DbiFactory::getConnectionFactory();
		$s = new MyStorage(
			TestEntity::class,
			$dbi,
			$dbiFac,
			'test_gen_storage',
			[
				'email' => 'VARCHAR(128)',
				'id' => [
					'type' => 'CHAR(32) CHARACTER SET ASCII',
				],
			],
			[
				'INDEX email(email)',
				'INDEX id(id)'
			],
			false,
			false);
		$s->reset();
		return $s;
	}

}
