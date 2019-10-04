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
				'field1' => [ 'type' => 'varchar(15)', 'nullable' => true, 'default' => null ],
				'field2' => [ 'type' => 'int unsigned', 'nullable' => false, 'default' => 1 ]
			],
		];

		$method->invokeArgs($reflection, [$tables]);
		$this->assertTrue(class_exists('One'));
		$generated_reflection = new \ReflectionClass('One');
		$this->assertTrue($generated_reflection->isSubclassOf('Swolley\YardBird\Models\AbstractModel'));
		
		eval('private $field1;
			public function getField1() { return $this->field1; }
			public function setField1(?string $field1 = null ) { if(strlen($field1) <= 15 || $field1 === null) $this->field1 = $field1; }
			private $field2;
			public function getField2() { return $this->field2; }
			public function setField2(int $field2 = 1 ) { if($field2 > 0) $this->field2 = $field2; }
		');
		$expected_reflection = new \ReflectionClass('Two');

		$mapped_expected = array_map(function($prop) { 
			return [\Reflection::getModifierNames($prop->getModifiers()), $prop->getName()]; 
		}, $expected_reflection->getProperties());
		$mapped_generated = array_map(function($prop) { 
			return [\Reflection::getModifierNames($prop->getModifiers()), $prop->getName()]; 
		}, $generated_reflection->getProperties());
		$this->assertEquals($mapped_expected, $mapped_generated);
		
		$mapped_expected = array_map(function($method) { 
			return [\Reflection::getModifierNames($method->getModifiers()), $method->getName(), $method->getReturnType(), array_map(function($param) { 
				$parsed_param = [$param->getName(), $param->getType(), $param->isOptional()]; 
				if($param->isDefaultValueAvailable()) $parsed_param[] = $param->getDefaultValue();
				return $parsed_param;
			}, $method->getParameters())]; 
		}, $expected_reflection->getMethods());
		$mapped_generated = array_map(function($method) { 
			return [\Reflection::getModifierNames($method->getModifiers()), $method->getName(), $method->getReturnType(), array_map(function($param) { 
				$parsed_param = [$param->getName(), $param->getType(), $param->isOptional()]; 
				if($param->isDefaultValueAvailable()) $parsed_param[] = $param->getDefaultValue();
				return $parsed_param;
			}, $method->getParameters())]; 
		}, $generated_reflection->getMethods());
		$this->assertEquals($mapped_expected, $mapped_generated);

		echo "pippo";
	}
}