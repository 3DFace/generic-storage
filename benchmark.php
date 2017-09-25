<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

use dface\GenericStorage\Mysql\MyStorageBuilder;
use dface\GenericStorage\TestEntity;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;

include_once __DIR__.'/bootstrap.php';

$link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_BASE);
$link->set_charset(DB_CHARSET);
$parser = new DefaultParser();
$formatter = new DefaultFormatter();
$dbi = new MysqliConnection($link, $parser, $formatter);

$storage = (new MyStorageBuilder(TestEntity::class, $link, 'test_gen_storage'))
	->setIdPropertyName('id')
	->addColumns([
		'email' => 'VARCHAR(128)',
		'data/a' => 'VARCHAR(128)',
	])
	->addIndexes([
		'INDEX email(email)',
	])
	->setBatchListSize(0)
//	->setTemporary(true)
//	->setDbCharset('cp1251')
//	->setProjectCharset('koi8r')
	->build();
$storage->reset();

$limit = 10000;

/** @var TestEntity[] $data */
$data = [];
for($i=0; $i<$limit; $i++){
	$id = new \dface\GenericStorage\TestId();
	$e = new TestEntity($id, 'name-'.$i, 'name-'.$i.'@benchmark', new \dface\GenericStorage\TestData('asd', $i));
	$data[] = $e;
}

$started = microtime(true);

foreach($data as $e){
	$id = $e->getId();
	$storage->saveItem($id, $e);
	$storage->getItem($id);
}

echo (microtime(true) - $started);
