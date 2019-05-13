<?php
namespace Swolley\Database\Utils;

use Swolley\Database\Exceptions\UnexpectedValueException;
use Swolley\Database\Exceptions\BadMethodCallException;

trait TraitQueryBuilder
{
	protected function createQuery(string $query, array $params = [])
	{
		if (preg_match('/^select/i', $query) === 1) {
			return self::parseSelect($query, $params);
		} elseif (preg_match('/^insert/i', $query) === 1) {
			return self::parseInsert($query, $params);
		} elseif (preg_match('/^delete from/i', $query) === 1) {
			return self::parseDelete($query, $params);
		} elseif (preg_match('/^update/i', $query) === 1) {
			return self::parseUpdate($query, $params);
		} elseif (preg_match('/^call|exec|begin/i', $query) === 1) {
			return self::parseProcedure($query, $params);
		} else {
			throw new UnexpectedValueException('queryBuilder is unable to detect the query type');
		}
	}

	protected function parseInsert(string $query, array $params = [])
	{
		//recognize ignore keyword
		$ignore = false;
		if (preg_match('/^(insert\s)(ignore\s)/i', $query) === 1) {
			$ignore = true;
		}

		if(!strpos(strtolower($query), 'into')) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}

		$query = rtrim(preg_replace('/^(insert\s)(ignore\s)?(into\s)/i', '', $query), ';');

		//splits main macro blocks (table, columns, values)
		$matches = [];
		preg_match_all('/^(`?\w+(?=\s*)`?\s?)(\([^(]*\)\s?)(values\s?)(\([^(]*\))$/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);

		if(empty($query[0])) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}
		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);
		if (preg_match('/^values/i', $query[0][0]) === 1) {
			throw new UnexpectedValueException('parseInsert needs to know columns\' names');
		}

		//list of columns'names
		$keys_list = preg_split('/,\s?/', preg_replace('/\(|\)/', '', array_shift($query)[0]));
		if (count($keys_list) === 0) {
			throw new UnexpectedValueException('parseInsert needs to know columns\' names');
		}
		$keys_list = array_map(function ($key) {
			return preg_replace('/`|\s/', '', $key);
		}, $keys_list);

		//list of columns'values
		if (preg_match('/^values/i', array_shift($query)[0]) === 0) {
			throw new UnexpectedValueException('columns list must be followed by VALUES keyword');
		}

		$values_list = preg_split('/,\s?/', preg_replace('/\(|\)/', '', array_shift($query)[0]));
		if (count($values_list) === 0) {
			throw new UnexpectedValueException('parseInsert needs to know columns\' values');
		}
		$values_list = array_map(function ($value) {
			return $this->castValue($value);
		}, $values_list);

		if (count($keys_list) !== count($values_list)) {
			throw new \Exception('Columns count must match values count');
		}

		//substitute params in array of values
		foreach ($params as $key => $value) {
			if ($index = array_search(':' . $key, $values_list)) {
				$values_list[$index] = $value;
			}
		}

		//compose array column/value
		$params = array_combine($keys_list, $values_list);

		//query elements ready to be passed to driver function
		return [
			'type' => 'insert',
			'table' => $table,
			'params' => $params,
			'ignore' => $ignore
		];
	}

	protected function parseDelete(string $query, array $params = [])
	{
		//splits main macro blocks (table, columns, values)
		$query = rtrim(preg_replace('/^(delete from\s)/i', '', $query), ';');
		$matches = [];
		//TODO not found a better way to split with optional where clauses
		if(strpos(strtolower($query), 'where')) {
			preg_match_all('/^(`?\w+(?=\s*)`?\s?)(where\s?)(.*)$/i', $query, $matches);
		} else {
			preg_match_all('/^(`?\w+(?=\s*)`?\s?)$/i', $query, $matches);
		}
		$query = array_slice($matches, 1);
		unset($matches);
		if(empty(end($query)[0])) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);

		//checks for where clauses
		if (count($query) > 0 && preg_match('/^where/i', $query[0][0]) === 1) {
			array_shift($query);

			//splits on parentheses
			$query = preg_split('/\s/', $query[0][0]);
			for ($i = 0; $i < count($query); $i++) {
				if (strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
					$first_part = array_slice($query, 0, $i);
					$second_part = array_slice($query, $i + 1);
					if (substr($query[$i], 0, 1) === '(') {
						$first_part[] = '(';
						$first_part[] = substr($query[$i], 1);
					} elseif (substr($query[$i], -1, 1) === ')') {
						$first_part[] = substr($query[$i], 0, -1);
						$first_part[] = ')';
					}
					$query = array_merge($first_part, $second_part);
					$i++;
				}
			}

			//parse and nest parameters
			$i = 0;
			$nested_level = 0;
			$where_params = $this->parseOperators($query, $params, $i, $nested_level);

			//groups params by logical operators
			$final_nested = $this->groupLogicalOperators($where_params);
		} else {
			$final_nested = [];
		}

		//query elements ready to be passed to driver function
		return [
			'type' => 'delete',
			'table' => $table,
			'params' => $final_nested
		];
	}

	protected function parseUpdate(string $query, array $params = [])
	{
		//splits main macro blocks (table, columns, values)
		$query = rtrim(preg_replace('/^(update\s)/i', '', $query), ';');
		$matches = [];
		//TODO not found a better way to split with optional where clauses
		if(strpos(strtolower($query), 'where')) {
			preg_match_all('/^(`?\w+(?=\s*)`?\s?)(set\s)(.*\s?)(where\s)(.*)$/i', $query, $matches);
		} else {
			preg_match_all('/^(`?\w+(?=\s*)`?\s?)(set\s)(.*\s?)$/i', $query, $matches);
		}
		$query = array_slice($matches, 1);
		unset($matches);
		
		if(empty($query[0])) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);
		//removes SET keyword
		array_shift($query);

		//list of columns'names
		$keys_list = preg_split('/,\s?/', array_shift($query)[0]);
		if (count($keys_list) === 0) {
			throw new UnexpectedValueException('parseInsert needs to know columns\' names');
		}

		$parsed_params = [];
		foreach ($keys_list as $value) {
			$exploded = explode('=', preg_replace('/`|\s/', '', $value));
			$key = ltrim($exploded[1], ':');
			$parsed_params[$exploded[0]] = array_key_exists($key, $params) ? $params[$key] : $this->castValue($key);
		}
		unset($keys_list);

		//checks for where clauses
		if (count($query) > 0 && preg_match('/^where/i', $query[0][0]) === 1) {
			array_shift($query);

			//splits on parentheses
			$query = preg_split('/\s/', $query[0][0]);
			for ($i = 0; $i < count($query); $i++) {
				if (strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
					$first_part = array_slice($query, 0, $i);
					$second_part = array_slice($query, $i + 1);
					if (substr($query[$i], 0, 1) === '(') {
						$first_part[] = '(';
						$first_part[] = substr($query[$i], 1);
					} elseif (substr($query[$i], -1, 1) === ')') {
						$first_part[] = substr($query[$i], 0, -1);
						$first_part[] = ')';
					}
					$query = array_merge($first_part, $second_part);
					$i++;
				}
			}

			//parse and nest parameters
			$i = 0;
			$nested_level = 0;
			$where_params = $this->parseOperators($query, $params, $i, $nested_level);

			//groups params by logical operators
			$final_nested = $this->groupLogicalOperators($where_params);
		} else {
			$final_nested = [];
		}

		//query elements ready to be passed to driver function
		return [
			'type' => 'update',
			'table' => $table,
			'params' => $parsed_params,
			'where' => $final_nested
		];
	}

	protected function parseSelect(string $query, array $params = [])
	{
		/*
		SELECT a,b FROM users WHERE age=33 ORDER BY name	$db->users->find(array("age" => 33), array("a" => 1, "b" => 1))->sort(array("name" => 1));
		SELECT * FROM users WHERE name LIKE "%Joe%"	$db->users->find(array("name" => new MongoRegex("/Joe/")));
		SELECT * FROM users WHERE name LIKE "Joe%"	$db->users->find(array("name" => new MongoRegex("/^Joe/")));
		SELECT * FROM users ORDER BY name DESC	$db->users->find()->sort(array("name" => -1));
		SELECT * FROM users LIMIT 20, 10	$db->users->find()->limit(10)->skip(20);
		SELECT * FROM users LIMIT 1	$db->users->find()->limit(1);
		SELECT COUNT(*y) FROM users	$db->users->count();
		SELECT COUNT(*y) FROM users where AGE > 30	$db->users->find(array("age" => array('$gt' => 30)))->count();
		SELECT COUNT(AGE) from users	$db->users->find(array("age" => array('$exists' => true)))->count();
		*/

		//splits main macro blocks (table, columns, values)
		$query = rtrim(preg_replace('/^(select\s)/i', '', $query), ';');
		$matches = [];
		//TODO not found a better way to split with optional where clauses
		if(strpos(strtolower($query), 'where')) {
			preg_match_all('/^(distinct\s)?(.*)(from\s)(.*)(where\s?)(.*)$/i', $query, $matches);
		} else {
			preg_match_all('/^(distinct\s)?(.*)(from\s)(.*)$/i', $query, $matches);
		}
		$query = array_slice($matches, 1);
		unset($matches);

		if(empty($query[0])) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}

		//check if distinct
		$isDistinct = false;
		if (preg_match('/^distinct/i', $query[0][0]) === 1) {
			array_shift($query);
			$isDistinct = true;
		} elseif ($query[0][0] === '') {
			array_shift($query);
		}

		//parse columns to select
		$columns_list = preg_split('/,\s?/', array_shift($query)[0]);
		$columns_list = array_map(function ($key) {
			return trim(trim($key, '`'));
		}, $columns_list);

		if (count($columns_list) === 1 && $columns_list[0] === '*') {
			$columns_list = [];
		}

		//bypasse FROM keyword
		if (preg_match('/^from\s/i', $query[0][0]) === 0) {
			throw new UnexpectedValueException("select query require FROM keyword after columns list");
		} else {
			array_shift($query);
		}

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);

		if (count($query) > 0 && preg_match('/^where/i', $query[0][0]) === 1) {
			array_shift($query);

			//splits on parentheses
			$query = preg_split('/\s/', $query[0][0]);
			for ($i = 0; $i < count($query); $i++) {
				if (strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
					$first_part = array_slice($query, 0, $i);
					$second_part = array_slice($query, $i + 1);
					if (substr($query[$i], 0, 1) === '(') {
						$first_part[] = '(';
						$first_part[] = substr($query[$i], 1);
					} elseif (substr($query[$i], -1, 1) === ')') {
						$first_part[] = substr($query[$i], 0, -1);
						$first_part[] = ')';
					}
					$query = array_merge($first_part, $second_part);
					$i++;
				}
			}

			//parse and nest parameters
			$i = 0;
			$nested_level = 0;
			$where_params = $this->parseOperators($query, $params, $i, $nested_level);

			//groups params by logical operators
			$final_nested = $this->groupLogicalOperators($where_params);
		} else {
			$final_nested = [];
		}

		//query elements ready to be passed to driver function
		if ($isDistinct) {
			$final_nested = array_merge(['distinct' => $table], $final_nested);
		}

		return [
			'type' => $isDistinct ? 'command' : 'select',
			'table' => $table,
			'params' => $columns_list,
			'options' => $final_nested
		];
	}

	protected function parseProcedure(string $query, array $params = [])
	{
		$query = rtrim(preg_replace('/^(call|exec|begin)\s?/i', '', $query), ';');
		$matches = [];
		preg_match_all('/^(\w+\s?)(?>\(?)(.*)(?:;\s?end)?/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);

		//set procedure name
		$procedure = preg_replace('/`|\s/', '', array_shift($query)[0]);
		$parameters_list = preg_replace('/(\)|;).*/', '', array_shift($query)[0]);
		$parameters_list = preg_split('/,\s?/', $parameters_list);

		if(count($parameters_list) > 0 && !empty($parameters_list[0])) {
			foreach ($parameters_list as $key => $value) {
				//checks if every placeholder has a value in params
				if (strpos($value, ':') === 0) {
					$key = ltrim($value, ':');
					if (!array_key_exists($key, $params)) {
						throw new BadMethodCallException("Missing corresponding value to bind in params array");
					}
				} else {
					//if is not a placeholder create new element in params array with value found in query
					$first_part = array_slice($params, 0, $key);
					$second_part = array_slice($params, $key + 1);
					$first_part['param' . ($key + 1)] = $this->castValue($value);
					$params = array_merge($first_part, $second_part);
				}
			}
		} else {
			$params = [];
		}

		return [
			'type' => 'procedure',
			'name' => $procedure,
			'params' => $params
		];
	}

	protected function parseOperators(array &$query, array &$params, int &$i, int &$nested_level)
	{
		$where_params = [];
		while ($i < count($query)) {
			if (preg_match('/!?=|<=?|>=?/i', $query[$i]) === 1) {
				$splitted = preg_split('/(=|!=|<>|>=|<=|>(?!=)|<(?<!=)(?!>))/i', $query[$i], null, PREG_SPLIT_DELIM_CAPTURE);
				switch ($splitted[1]) {
					case '=':
						$operator = '$eq';
						break;
					case '!=':
					case '<>':
						$operator = '$ne';
						break;
					case '<':
						$operator = '$lt';
						break;
					case '<=':
						$operator = '$lte';
						break;
					case '>':
						$operator = '$gt';
						break;
					case '>=':
						$operator = '$gte';
						break;
					default:
						throw new UnexpectedValueException('Unrecognised operator');
				}

				$splitted[2] = $this->castValue($splitted[2]);
				$trimmed = ltrim($splitted[2], ':');
				if (isset($params[$trimmed])) {
					$splitted[2] = $params[$trimmed];
				}

				$where_params[] = [$splitted[0] => [$operator => $splitted[2]]];
			} elseif (preg_match('/and|&&|or|\|\|/i', $query[$i]) === 1) {
				$where_params[] = $query[$i];
			} elseif ($query[$i] === '(') {
				$i++;
				$nested_level++;
				$where_params[] = $this->parseOperators($query, $params, $i, $nested_level);
			} elseif ($query[$i] === ')') {
				//$i++;
				$nested_level--;
				break;
			} else {
				throw new UnexpectedValueException('Unexpected keyword ' . $query[$i]);
			}
			$i++;
		}

		return $where_params;
	}

	protected function groupLogicalOperators(array $query)
	{
		$nested_group = [];
		$i = 0;
		while ($i < count($query)) {
			if (array_key_exists($i + 1, $query)) {
				if (is_string($query[$i + 1])) {
					$operator = strtolower($query[$i + 1]);
					if ($operator === 'and' || $operator === 'or') {
						$i = $i + 2;
						$sub_group = $this->groupLogicalOperators($query[$i]);
						$merged_array = array_merge(count($query[$i - 2]) === 1 ? $query[$i - 2] : $nested_group, $sub_group);
						$nested_group = [
							'$' . $operator => $merged_array
						];
					}
				}
			} else {
				if (count($query) === 1) {
					$is_assoc = array_keys($query) !== range(0, count($query) - 1);
					$nested_group = $is_assoc ? $query : end($query);
				}
				break;
			}
		}

		return $nested_group;
	}

	protected function castValue($value)
	{
		if (preg_match("/^'|\"\w+'|\"$/", $value)) {
			return preg_replace("/'|\"/", '', $value);
		} elseif (is_numeric($value)) {
			return $value + 0;
		} elseif (is_bool($value)) {
			return (bool)$value;
		} else {
			return $value;
		}
	}

	public static function operatorsToStandardSyntax(string $query): string
	{
		$query = preg_replace("/\s?&&\s?/", ' AND ', $query);
		$query = preg_replace("/\s?\|\|\s?/", ' OR ', $query);
		$query = preg_replace("/\s?!=\s?/", '<>', $query);
		return $query;
	}

	public static function colonsToQuestionMarksPlaceholders(string &$query, array &$params): void
	{
		$total_params = count($params);
		$total_questionmark_placeholders = substr_count($query, '?');
		$colon_placeholders = [];
		preg_match_all('/(:\w+)/i', $query, $colon_placeholders);
		$colon_placeholders = array_shift($colon_placeholders);
		$total_colon_placeholders = count($colon_placeholders);

		if($total_colon_placeholders > 0 && $total_questionmark_placeholders > 0) {
			throw new UnexpectedValueException('Possible incongruence in query placeholders');
		}

		if(($total_colon_placeholders === 0 && $total_questionmark_placeholders !== $total_params) || ($total_questionmark_placeholders === 0 && $total_colon_placeholders !== $total_params)) {
			throw new BadMethodCallException('Number of params and placeholders must be the same');
		}
		
		//changes colon placeholders found they are switched to question marks because of mysqli bind restruction
		if($total_questionmark_placeholders === 0) {
			$reordered_params = [];
			foreach($colon_placeholders as $param) {
				$trimmed = ltrim($param, ':');
				if(array_key_exists($trimmed, $params)) {
					$reordered_params[] = $params[$trimmed];
					$query = str_replace($param, '?', $query);
				} else {
					throw new BadMethodCallException("`$param` not found in parameters list");
				}
			}

			$params = $reordered_params;
			unset($reordered_params);
		}
	}
}
