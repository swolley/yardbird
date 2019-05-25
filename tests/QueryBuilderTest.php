<?php
declare (strict_types = 1);

use PHPUnit\Framework\TestCase;
use Swolley\Database\Utils\QueryBuilder;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

final class QueryBuilderTest extends TestCase
{
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	public function test_createQuery_should_return_exception_if_not_recognized_a_valid_query(): void
	{
		$this->expectException(UnexpectedValueException::class);
		(new QueryBuilder)->createQuery('invalid query');
	}

	public function test_createQuery_should_return_array_if_parameters_are_correct(): void
	{
		$queryBuilder = new QueryBuilder();
		$query = $queryBuilder->createQuery('SELECT id FROM table');
		$this->assertEquals('array', gettype($query));
		$this->assertEquals('select', $query['type']);
		$this->assertEquals('table', $query['table']);
		$this->assertEquals(['id'], $query['params']);
		$this->assertTrue(empty($query['options']));
		unset($query);

		$query = $queryBuilder->createQuery('INSERT INTO table(a, b) VALUES(:a, :b)');
		$this->assertEquals('array', gettype($query));
		$this->assertEquals('insert', $query['type']);
		$this->assertEquals('table', $query['table']);
		$this->assertEquals(['a' => ':a', 'b' => ':b'], $query['params']);
		$this->assertTrue(empty($query['options']));
		unset($query);

		$query = $queryBuilder->createQuery('UPDATE table SET a=:a, b=:b');
		$this->assertEquals('array', gettype($query));
		$this->assertEquals('update', $query['type']);
		$this->assertEquals('table', $query['table']);
		$this->assertEquals(['a' => 'a', 'b' => 'b'], $query['params']);
		$this->assertTrue(empty($query['options']));
		unset($query);

		$query = $queryBuilder->createQuery('DELETE FROM table');
		$this->assertEquals('array', gettype($query));
		$this->assertEquals('delete', $query['type']);
		$this->assertEquals('table', $query['table']);
		$this->assertEquals([], $query['params']);
		$this->assertTrue(empty($query['options']));
		unset($query);

		$query = $queryBuilder->createQuery('CALL procedure()');
		$this->assertEquals('array', gettype($query));
		$this->assertEquals('procedure', $query['type']);
		$this->assertEquals('procedure', $query['name']);
		$this->assertEquals([], $query['params']);
		$this->assertTrue(empty($query['options']));
		unset($query);
	}

	public function test_parseInsert_should_throw_exception_if_any_syntax_error(): void
	{
		$queryBuilder = new QueryBuilder();
		$this->expectException(UnexpectedValueException::class);
		$queryBuilder->createQuery('INSERT table');

		$this->expectException(UnexpectedValueException::class);
		$queryBuilder->createQuery('INSERT INTO table');
	}

	public function test_parseInsert_should_throw_exception_if_columns_count_differs_from_values(): void
	{
		$this->expectException(\Exception::class);
		(new QueryBuilder)->parseInsert('INSERT INTO table (`column1`, `missingColumn`) VALUES(:column)');
	}

	public function test_parseInsert_shold_return_array_if_parameters_are_correct(): void
	{
		$query = (new QueryBuilder)->parseInsert('INSERT IGNORE INTO table (`column1`) VALUES(:column)');
		$response = [
			'type' => 'insert',
			'table' => 'table',
			'params' => ['column1' => ':column'],
			'ignore' => true
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseDelete_should_throw_exception_if_any_syntax_error(): void
	{
		$this->expectException(UnexpectedValueException::class);
		(new QueryBuilder)->parseDelete('DELETE FROM table WHERE');
	}

	public function test_parseDelete_shold_return_array_if_parameters_are_correct(): void
	{
		$query = (new QueryBuilder)->parseDelete('DELETE FROM `table` WHERE (id<1 AND c<>2)');
		$response = [
			'type' => 'delete',
			'table' => 'table',
			'params' => ['$and' => ['id' => ['$lt' => 1], 'c' => ['$ne' => 2]]]
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseUpdate_should_throw_exception_if_any_syntax_error(): void
	{
		$this->expectException(UnexpectedValueException::class);
		(new QueryBuilder)->parseUpdate("UPDATE WHERE `column` != 'value'");
	}

	public function test_parseUpdate_shold_return_array_if_parameters_are_correct(): void
	{
		$query = (new QueryBuilder)->parseUpdate("UPDATE `table` SET id='value'");
		$response = [
			'type' => 'update',
			'table' => 'table',
			'params' => ['id' => 'value'],
			'where' => []
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseSelect_should_throw_exception_if_any_syntax_error(): void
	{
		$this->expectException(UnexpectedValueException::class);
		(new QueryBuilder)->parseSelect("SELECT INTO `table` WHERE `column` > 'value'");
	}

	public function test_parseSelect_shold_return_array_if_parameters_are_correct(): void
	{
		$query = (new QueryBuilder)->parseSelect("SELECT * FROM `table`");
		$response = [
			'type' => 'select',
			'table' => 'table',
			'params' => [],
			'options' => []
		];
		$this->assertEquals($response, $query);

		$query = (new QueryBuilder)->parseSelect("SELECT DISTINCT * FROM `table`");
		$response = [
			'type' => 'command',
			'table' => 'table',
			'params' => [],
			'options' => ['distinct' => 'table']
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseProcedure_should_throw_exception_if_any_syntax_error(): void
	{
		$this->expectException(BadMethodCallException::class);
		(new QueryBuilder)->parseProcedure("CALL procedure_name (:value1, :value2)");
	}

	public function test_parseProcedure_shold_return_array_if_parameters_are_correct(): void
	{
		$query = (new QueryBuilder)->parseProcedure("CALL procedure_name ()", []);
		$response = [
			'type' => 'procedure',
			'name' => 'procedure_name',
			'params' => []
		];
		$this->assertEquals($response, $query);
	}

	public function test_parseOperators_should_throw_exception_if_no_valid_operator_found(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$query = ["a!!b"];
		$params = [];
		$nested_level = $i = 0;

		(new QueryBuilder)->parseOperators($query, $params, $i, $nested_level);
	}

	public function test_parseOperators_should_return_array_if_parameters_are_correct(): void
	{
		$query = ["a=1", "b>'value'", 'id=:id'];
		$params = ['id' => 1];
		$nested_level = $i = 0;
		$parsed = (new QueryBuilder)->parseOperators($query, $params, $i, $nested_level);
		$this->assertEquals('array', gettype($parsed));
	}

	public function test_groupLogicalOperators_should_return_grouped_params_by_operators(): void
	{
		$query = [
			['a' => ['$eq' => 1]],
			'AND',
			['b' => ['$gt' => 'value']]
		];

		$parsed = (new QueryBuilder)->groupLogicalOperators($query);
		$response = [
			'$and' => [
				'a' => ['$eq' => 1],
				'b' => ['$gt' => 'value']
			]
		];
		$this->assertEquals($response, $parsed);
	}

	public function test_castValue_should_return_converted_value(): void
	{
		$queryBuilder = new QueryBuilder();
		$casted = $queryBuilder->castValue("'string'");
		$response = "string";
		$this->assertEquals($response, $casted);

		$casted = $queryBuilder->castValue(0);
		$response = 0;
		$this->assertEquals($response, $casted);
	}

	public function test_colonsToQuestionMarksPlaceholders_should_throw_exception_if_both_colon_and_questionmark_placeholders_found(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$query = ':value, ?';
		$params = ['value' => 1];
		(new QueryBuilder)->colonsToQuestionMarksPlaceholders($query, $params);
	}

	public function test_colonsToQuestionMarksPlaceholders_should_throw_exception_if_not_same_number_of_placeholders_and_params(): void
	{
		$this->expectException(BadMethodCallException::class);
		$query = ':value1, :value2';
		$params = ['value1' => 1];
		(new QueryBuilder)->colonsToQuestionMarksPlaceholders($query, $params);
	}

	public function test_colonsToQuestionMarksPlaceholders_should_throw_exception_if_no_corresponding_params_and_placeholders(): void
	{
		$this->expectException(BadMethodCallException::class);
		$query = ':value1, :value2';
		$params = ['value3' => 1];
		(new QueryBuilder)->colonsToQuestionMarksPlaceholders($query, $params);
	}

	public function test_operatorsToStandardSyntax_should_return_replaced_string(): void
	{
		$query = 'SELECT * FROM table WHERE value1<=1&&value2>=2 || value3=3 AND value4!=4';
		$expected = 'SELECT * FROM table WHERE value1<=1 AND value2>=2 OR value3=3 AND value4<>4';
		$this->assertEquals($expected, (new QueryBuilder)->operatorsToStandardSyntax($query));
	}
}
