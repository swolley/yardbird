<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Utils\ClassBuilder;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Exceptions\ConnectionException;

/**
 * @covers Swolley\YardBird\Utils\ClassBuilder
 */
final class ClassBuilderTest extends TestCase
{
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	/**
	 * @covers Swolley\YardBird\Utils\ClassBuilder::mapTables
	 * @group unit
	 */
	public function test_mapTables_should_generate_class(): void
	{
		$reflection = new \ReflectionClass(get_class(new ClassBuilder));
		$method = $reflection->getMethod('mapTables');
		$method->setAccessible(true);

		$tables = [
			'one' => [ 
				'field1' => [ 'type' => 'varchar(15)', 'nullable' => false, 'default' => null ],
				'field2' => [ 'type' => 'int unsigned', 'nullable' => true, 'default' => 1 ]
			],
		];

		$method->invokeArgs($reflection, [$tables]);
		$this->assertTrue(class_exists('One'));
		$generated_reflection = new \ReflectionClass('One');
		
		eval('final class Two extends Swolley\YardBird\Models\AbstractModel { 
			private $field1;
			public function getField1() { return $this->field1; }
			public function setField1(string $field1  ) { if(strlen($field1) <= 15) $this->field1 = $field1; }
			private $field2;
			public function getField2() { return $this->field2; }
			public function setField2(?int $field2 = 1 ) { if($field2 > 0) $this->field2 = $field2; }
		}');
		$expected_reflection = new \ReflectionClass('Two');

		$this->assertEquals(array_map(function($field) { return [$field->getName(), $$expected_reflection->getFields()), )		
	}
}