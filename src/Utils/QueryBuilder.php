<?php
namespace Swolley\Database\Utils;

use Swolley\Database\Exceptions\UnexpectedValueException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Utils\Utils;
use MongoDB\BSON\Regex;
use phpDocumentor\Reflection\Types\Boolean;

class QueryBuilder
{
	/**
	 * generic query constructor
	 * @param	string	$query	query string
	 * @param	array	$params	values to be binded
	 * @return	object			composed query data
	 */
	public function sqlToMongo(string $query, array $params = []): object
	{
		if (preg_match('/^select/i', $query) === 1) {
			return self::sqlSelectToMongo($query, $params);
		} elseif (preg_match('/^insert/i', $query) === 1) {
			return self::sqlInsertToMongo($query, $params);
		} elseif (preg_match('/^delete from/i', $query) === 1) {
			return self::sqlDeleteToMongo($query, $params);
		} elseif (preg_match('/^update/i', $query) === 1) {
			return self::sqlUpdateToMongo($query, $params);
		} elseif (preg_match('/^call|exec|begin/i', $query) === 1) {
			return self::sqlProcedureToMongo($query, $params);
		} else {
			throw new UnexpectedValueException('queryBuilder is unable to detect the query type');
		}
	}

	/**
	 * insert query constructor
	 * @param	string	$query	query string
	 * @param	array	$params	values to be binded
	 * @return	object			composed query data
	 */
	public function sqlInsertToMongo(string $query, array $params = []): object
	{
		$query = Utils::trimQueryString($query);
		//recognize ignore keyword
		$ignore = false;

		$query = preg_split('/^(insert) (ignore\s)?(into) (`?\w+`?)\s?\((.*)\) (values)\s?\((.*)\)$/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		if (empty($query)) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}
		//removes insert
		array_shift($query);
		if (count($query) > 0 && preg_match('/ignore/i', $query[0]) === 1) {
			$ignore = true;
			array_shift($query);
		}

		if (count($query) === 0 || preg_match('/into/i', $query[0]) === 0) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}
		array_shift($query);

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query));
		if (preg_match('/^values/i', $query[0]) === 1) {
			throw new UnexpectedValueException('sqlInsertToMongo needs to know columns\' names');
		}

		//list of columns'names
		$keys_list = preg_split('/,\s?/', array_shift($query));
		$keys_list = array_map(function ($key) {
			return preg_replace('/`|\s/', '', $key);
		}, $keys_list);
		if (count($keys_list) === 0) {
			throw new UnexpectedValueException('sqlInsertToMongo needs to know columns\' names');
		}

		//list of columns'values
		if (preg_match('/^values/i', array_shift($query)) === 0) {
			throw new UnexpectedValueException('columns list must be followed by VALUES keyword');
		}
		$values_list = preg_split('/,\s?/', array_shift($query));
		$values_list = array_map(function ($value) {
			return self::castValue($value);
		}, $values_list);
		if (count($values_list) === 0) {
			throw new UnexpectedValueException('sqlInsertToMongo needs to know columns\' values');
		}

		if (count($keys_list) !== count($values_list)) {
			throw new BadMethodCallException('Columns count must match values count');
		}

		//substitute params in array of values
		foreach ($params as $key => $value) {
			$index = array_search(':' . $key, $values_list);
			if ($index !== false) {
				$values_list[$index] = $value;
			}
		}

		//compose array column/value
		$params = array_combine($keys_list, $values_list);

		//query elements ready to be passed to driver function
		return (object)[
			'type' => 'insert',
			'table' => $table,
			'params' => $params,
			'ignore' => $ignore
		];
	}

	/**
	 * delete query constructor. Cannot handle DELETE ORDER BY LIMIT
	 * @param	string	$query	query string
	 * @param	array	$params	values to be binded
	 * @return	object			composed query data
	 */
	public function sqlDeleteToMongo(string $query, array $params = []): object
	{
		$query = Utils::trimQueryString($query);
		//splits main macro blocks (table, columns, values)
		$query = preg_split('/^(delete from) (`?\w+`?)\s?|(?:(where) (.*))$/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		if (empty($query)) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}
		array_shift($query);
		//TABLE
		$table = preg_replace('/`|\s/', '', array_shift($query));

		//WHERE
		if (count($query) > 0 && preg_match('/where/i', $query[0]) === 1) {
			array_shift($query);

			if(count($query) === 0) {
				throw new UnexpectedValueException('WHERE keyword must be followed by clauses');
			}

			$query = self::splitsOnParenthesis($query);
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
		return (object)[
			'type' => 'delete',
			'table' => $table,
			'params' => $final_nested
		];
	}

	/**
	 * insert query constructor
	 * @param	string	$query	query string
	 * @param	array	$params	values to be binded
	 * @return	object			composed query data
	 */
	public function sqlUpdateToMongo(string $query, array $params = []): object
	{
		$query = Utils::trimQueryString($query);
		//splits main macro blocks (table, columns, values)
		$query = preg_split('/^(update) (`?\w+`?) (set) (.*)\s?|(?:(where) (.*))$/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		if (empty($query)) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}
		array_shift($query);

		//TABLE
		$table = preg_replace('/`|\s/', '', array_shift($query));
		//removes SET keyword
		if(preg_match('/set/i', $query[0]) === 0) {
			throw new UnexpectedValueException('Missing keywor SET');
		}
		array_shift($query);

		//list of columns'names
		$keys_list = preg_split('/,\s?/', array_shift($query));
		if (count($keys_list) === 0) {
			throw new UnexpectedValueException('sqlInsertToMongo needs to know columns\' names');
		}

		$parsed_params = [];
		foreach ($keys_list as $value) {
			$exploded = explode('=', preg_replace('/`|\s/', '', $value));
			$key = ltrim($exploded[1], ':');
			$parsed_params[$exploded[0]] = array_key_exists($key, $params) ? $params[$key] : self::castValue($key);
		}
		unset($keys_list);

		//checks for where clauses
		if (count($query) > 0 && preg_match('/^where/i', $query[0]) === 1) {
			array_shift($query);
			//splits on parentheses
			$query = self::splitsOnParenthesis($query);
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
		return (object)[
			'type' => 'update',
			'table' => $table,
			'params' => $parsed_params,
			'where' => $final_nested
		];
	}

	/**
	 * insert query constructor. COUNT, ASLIASES not handled, yet
	 * @param	string	$query	query string
	 * @param	array	$params	values to be binded
	 * @return	object			composed query data
	 */
	public function sqlSelectToMongo(string $query, array $params = []): object
	{
		/*
		TODO not handled yet
		SELECT COUNT(*y) FROM users	$db->users->count();
		SELECT COUNT(*y) FROM users where AGE > 30	$db->users->find(array("age" => array('$gt' => 30)))->count();
		SELECT COUNT(AGE) from users	$db->users->find(array("age" => array('$exists' => true)))->count();
		*/

		$query = Utils::trimQueryString($query);
		//splits main macro blocks (table, columns, values)
		$query = preg_split('/^(select) (distinct)?\s?|(from) |((?:left*|inner) join) |(where) |(order by) |(limit) /i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		if (empty($query)) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}
		//removes select
		array_shift($query);
		
		//DISTINCT
		$isDistinct = false;
		if (preg_match('/distinct/i', $query[0]) === 1) {
			array_shift($query);
			$isDistinct = true;
		}
		
		//COLUMNS
		$rename_aliases = [];
		$columns_list = preg_split('/,\s?/', trim(array_shift($query)));
		$projection = [];
		$is_unique_id = false;
		foreach( $columns_list as $key) {
			$splitted = preg_split('/ as /i', preg_replace('/`/', '', $key));
			if($splitted[0] === '_id') {
				$is_unique_id = true;
			}
			$projection[$splitted[0]] = 1;

			if(isset($splitted[1])) {
				$rename_aliases[$splitted[0]] = $splitted[1];
			}
		};
		//reset($projection);
		if (count($projection) === 1 && key($projection) === '*') {
			$projection = [];
		} elseif(!$is_unique_id) {
			$projection['_id'] = 0;
		}
		unset($columns_list);

		//FROM
		if (preg_match('/from/i', $query[0]) === 0) {
			throw new UnexpectedValueException("select query require FROM keyword after columns list");
		} else {
			array_shift($query);
		}

		//TABLE
		$table = preg_replace('/`|\s/', '', array_shift($query));

		//JOINS
		$aggregate = [];
		while(count($query) > 0 && preg_match('/join/i', $query[0]) === 1) {
			if(preg_match('/inner/i', $query[0]) === 1) {
				throw new UnexpectedValueException('MongoDB can only handles left joins');
			}

			array_shift($query);
			list($joined_table, $left_field, $right_field) = preg_split('/(\w+) on ((?:\w+.)?\w+)\s?(?:=|!=|<>|>=|<=|>(?!=)|<(?<!=)(?!>))\s?((?:\w+.)?\w+)\s?/i', array_shift($query), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$aggregate[] = [
				'$lookup' => [
					'from' => $joined_table,
					'localField' => preg_replace('/^\w+.?/', '', (preg_match('/^'.$joined_table.'./', $left_field) === 1 ? $left_field : $right_field)),
					'foreignField' => preg_replace('/^\w+.?/', '', (preg_match('/^'.$joined_table.'./', $left_field) === 1 ? $right_field : $left_field)),
					'as' => $joined_table
				]
			];
		}
		
		//WHERE
		if (count($query) > 0 && preg_match('/where/i', $query[0]) === 1) {
			array_shift($query);
			$query = self::splitsOnParenthesis($query);
			//parse and nest parameters
			$i = 0;
			$nested_level = 0;
			$where_params = $this->parseOperators($query, $params, $i, $nested_level);
			//groups params by logical operators
			$final_nested = $this->groupLogicalOperators($where_params);
		} else {
			$final_nested = [];
		}

		//ORDER BY
		$order_fields = [];
		if (count($query) > 0 && preg_match('/order by/i', $query[0]) === 1) {
			array_shift($query);
			$splitted = preg_split('/,\s?/', trim(array_shift($query)));
			foreach($splitted as $element) {
				$key_order = preg_split('/\s/', $element);
				$order_fields[$key_order[0]] = isset($key_order[1]) && strtolower($key_order[1]) === 'desc' ? -1 : 1;
			}
		}

		$limit = null;
		if (count($query) > 0 && preg_match('/limit/i', $query[0]) === 1) {
			array_shift($query);
			$limit = preg_split('/,/', array_shift($query));
			if(count($limit) === 0) {
				$limit = $limit[0];
			}
		}

		$result = [
			'type' => $isDistinct ? 'command' : 'select',
			'table' => $table,
			'filter' => $final_nested,
			'options' => [
				'projection' => $projection 
			],
			'aggregate' => $aggregate,
			'orderBy' => $order_fields,
			'limit' => $limit
		];

		if($isDistinct) {
			$result['options']['distinct'] = $table;
		}
		if(!empty($rename_aliases)) {
			$result['options']['rename'] = $rename_aliases;
		}

		return (object)$result;
	}

	/**
	 * parse procedure query contructor
	 * @param	string	$query	query string
	 * @param	array	$params	parameters
	 * @return	object	composed query
	 */
	public function sqlProcedureToMongo(string $query, array $params = []): object
	{
		$query = Utils::trimQueryString($query);
		$query = preg_match('/^call|begin/i', $query) === 1
			? preg_split('/^(call|begin)\s(\w+)\s?\((.*)\)(?:;\s?end)?/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)
			: preg_split('/^(exec) (\w+)(?:\s(.*))?/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		if (empty($query)) {
			throw new UnexpectedValueException('unable to parse query, check syntax');
		}
		array_shift($query);
		//PROCEDURE NAME
		$procedure = preg_replace('/`|\s/', '', array_shift($query));
		if(count($query) > 0) {
			$parameters_list = preg_split('/,\s?/', array_shift($query));
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
					$first_part['param' . ($key + 1)] = self::castValue($value);
					$params = array_merge($first_part, $second_part);
				}
			}
		} else {
			$param = [];
		}

		return (object)[
			'type' => 'procedure',
			'name' => $procedure,
			'params' => $params
		];
	}

	/**
	 * converts operator to mongo syntax and creates nested hierarchy on parenthesis
	 * @param	string	$query	query string
	 * @param	array	$params	parameters
	 * @param	integer	$i		index
	 * @param	integer	$nested_level	level of nesting
	 * @return	object	composed query
	 */
	public function parseOperators(array &$query, array &$params, int &$i, int &$nested_level)
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
				}

				$splitted[2] = self::castValue($splitted[2]);
				$trimmed = ltrim($splitted[2], ':');
				if (isset($params[$trimmed])) {
					$splitted[2] = $params[$trimmed];
				}

				$where_params[] = [$splitted[0] => [$operator => $splitted[2]]];
			} elseif (preg_match('/\slike\s/i', $query[$i]) === 1) {
				$splitted = preg_split('/\slike\s/i', $query[$i]);
				if(substr($splitted[1], 0, 1) === ':' && isset($params[substr($splitted[1], 1)])) {
					$splitted[1] = $params[substr($splitted[1], 1)];
				}
				//first_char
				$splitted[1] = substr($splitted[1], 0, 1) === '%' ? '^' . substr($splitted[1], 1) : $splitted[1];
				//last char
				$splitted[1] = substr($splitted[1], -1) === '%' ? substr($splitted[1], 0, -1) . '$' : $splitted[1];
				$where_params[] = [trim($splitted[0], '`') => new Regex($splitted[1], 'i')];
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

	/**
	 * groups fields by logical operators
	 * @param	array	$query	query structure
	 */
	public function groupLogicalOperators(array $query)
	{
		if (count($query) === 1) {
			$query = array_pop($query);
		}

		$nested_group = [];
		$i = 1;
		//start
		if(!is_numeric(key($query))) {
			$nested_group = array_merge($nested_group, $query);
		} else {
			do {
				$cur = $query[$i];
				$prev = $query[$i - 1];

				if (count($prev) === 1) {
					//single value with operator
					$new_array = ['$' . strtolower($cur) => $prev];
				} elseif (count($prev) > 1) {
					//sub group of operators
					$new_array['$' . strtolower($cur)] = $this->groupLogicalOperators($prev);
				}

				$first_key = key($new_array);
				if (!isset($nested_group[$first_key])) {
					$nested_group[$first_key] = $new_array[$first_key];
				} else {
					$nested_group[$first_key] = array_merge($nested_group[$first_key], $new_array[$first_key]);
				}

				$i = $i + 2;
			} while ($i <= count($query) - 1);

			//last sub array element
			$i--;
			$cur = $query[$i];
			$new_array = count($cur) === 1 ? $cur : $this->groupLogicalOperators($cur);
			$first_key = key($nested_group);
			if (!isset($nested_group[$first_key])) {
				$nested_group[$first_key] = $new_array;
			} else {
				$nested_group[$first_key] = array_merge($nested_group[$first_key], $new_array);
			}
		}

		return $nested_group;
	}

	/**
	 * cast values to their types
	 * @param	mixed	$value	value to parse
	 * @return	mixed	parsed value
	 * 
	 */
	public static function castValue($value)
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

	/**
	 * change query operators to correct sql syntax
	 * @param	string	$query	query string
	 * @return	string	parsed operators
	 */
	public static function operatorsToStandardSyntax(string $query): string
	{
		$query = preg_replace("/\s?&&\s?/", ' AND ', $query);
		$query = preg_replace("/\s?\|\|\s?/", ' OR ', $query);
		$query = preg_replace("/\s?!=\s?/", '<>', $query);
		return $query;
	}

	/**
	 * change named placeholders to question marks (mysqli driver does not support named placeholders)
	 * @param	string	$query	query string
	 * @param	array	$params	parameter's list
	 */
	public static function colonsToQuestionMarksPlaceholders(string &$query, array &$params): void
	{
		$total_params = count($params);
		$total_questionmark_placeholders = substr_count($query, '?');
		$colon_placeholders = [];
		preg_match_all('/(:\w+)/i', $query, $colon_placeholders);
		$colon_placeholders = array_shift($colon_placeholders);
		$total_colon_placeholders = count($colon_placeholders);

		if ($total_colon_placeholders > 0 && $total_questionmark_placeholders > 0) {
			throw new UnexpectedValueException('Possible incongruence in query placeholders');
		}

		if (($total_colon_placeholders === 0 && $total_questionmark_placeholders !== $total_params) || ($total_questionmark_placeholders === 0 && $total_colon_placeholders !== $total_params)) {
			throw new BadMethodCallException('Number of params and placeholders must be the same');
		}

		//changes colon placeholders found they are switched to question marks because of mysqli bind restruction
		if ($total_questionmark_placeholders === 0) {
			$reordered_params = [];
			foreach ($colon_placeholders as $param) {
				$trimmed = ltrim($param, ':');
				if (array_key_exists($trimmed, $params)) {
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

	/**
	 * splits query string on parenthesis
	 * @param	array	$query	query string
	 * @return	array	splitted query string
	 */
	private static function splitsOnParenthesis(array $query): array
	{
		//splits on parentheses
		$query = preg_split('/(?<!like)\s(?!like)/i', $query[0]);
		for ($i = 0; $i < count($query); $i++) {
			if (strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
				$first_part = array_slice($query, 0, $i);
				$second_part = array_slice($query, $i + 1);
				if (substr($query[$i], 0, 1) === '(') {
					$first_part[] = '(';
					$substr = substr($query[$i], 1);
					if ($substr !== ' ') {
						$first_part[] = $substr;
					}
				} elseif (substr($query[$i], -1, 1) === ')') {
					$substr = substr($query[$i], 0, -1);
					if ($substr !== ' ') {
						$first_part[] = $substr;
					}
					$first_part[] = ')';
				}

				$query = array_merge($first_part, $second_part);
				if (substr(end($first_part), 0, 1) !== '(') {
					$i++;
				}
			}
		}

		return $query;
	}

	/**
	 * composes join portion of the query
	 * @param	array	$join	join parameters
	 * @return	string	query portion
	 */
	public static function joinsToSql(array $join): string 
	{
		$stringed_joins = '';
		foreach($join as $joined_table => $join_options) {
			if(!isset($join_options['type'], $join_options['localField'], $join_options['joinedField'])) {
				throw new BadMethodCallException('Malformed join array');
			}

			$stringed_joins .= strtoupper(preg_match('/join$/', $join_options['type']) === 1 ? $join_options['type'] : $join_options['type'] . ' JOIN') 
				. ' ON ' 
				. (preg_match('/^\w+./', $join_options['localField']) === 1 ? $join_options['localField'] : $joined_table.'.'.$join_options['localField'])
				. (isset($join_options['operator']) && preg_match('/^(=|!=|<>|>=|<=|>(?!=)|<(?<!=)(?!>)$/', $join_options['operator']) === 1 ? $join_options['operator'] : '=')
				. (preg_match('/^\w+./', $join_options['joinedField']) === 1 ? $join_options['joinedField'] : $joined_table.'.'.$join_options['joinedField'])
				. ' ';
		}

		return $stringed_joins;
	}

	/**
	 * composes order by portion of the query
	 * @param	array	$orderBy	order by parameters
	 * @return	string	query portion
	 */
	public static function orderByToSql(array $orderBy): string
	{
		$stringed_order_by = '';
		foreach($orderBy as $key => $value) {
			if($value === 1) {
				$direction = 'ASC';
			} elseif($value === -1) {
				$direction = 'DESC';
			} else {
				throw new UnexpectedValueException("Unexpected order value. Use 1 for ASC, -1 for DESC");
			}

			$stringed_order_by .= "{$key} {$direction},"; 
		}
		rtrim($stringed_order_by, ',');
		if(!empty($stringed_order_by)) {
			$stringed_order_by = "ORDER BY {$stringed_order_by}";
		}

		return $stringed_order_by;
	}

	/**
	 * composes limit portion of the query
	 * @param	array	$limit	join parameters
	 * @return	string	query portion
	 */
	public static function limitToSql($limit): string
	{
		if(is_null($limit)) {
			$stringed_limit = '';
		} elseif(is_integer($limit)){
			$stringed_limit = $limit;
		} elseif(is_array($limit) && count($limit) === 2) {
			$stringed_limit = join(',', $limit);
		} else {
			throw new UnexpectedValueException("Unexpected limit value. Can be integer or array of integers");
		}
		if(!empty($stringed_limit)) {
			$stringed_limit = "LIMIT {$stringed_limit}";
		}

		return $stringed_limit;
	}

	/**
	 * composes where portion of the query
	 * @param	array	$where						where parameters
	 * @param	boolean	$questionMarkPlaceholders	use question marks instead of named placeholders
	 * @return	string	query portion
	 */
	public static function whereToSql($where, bool $questionMarkPlaceholders = false): string
	{
		$stringed_where = '';
		foreach ($where as $key => $value) {
			$stringed_where .= "`$key`=" . ($questionMarkPlaceholders ? '?' : ":{$key}") . " AND ";
		}
		$stringed_where = rtrim($stringed_where, 'AND ');
		if(!empty($stringed_where)) {
			$stringed_where = "WHERE {$stringed_where}";
		}

		return $stringed_where;
	}

	/**
	 * composes values list portion of the query
	 * @param	array	$params						where parameters
	 * @param	boolean	$questionMarkPlaceholders	use question marks instead of named placeholders
	 * @return	string	query portion
	 */
	public static function valuesListToSql(array $params, bool $questionMarkPlaceholders = false): string
	{
		$values = '';
		foreach ($params as $key => $value) {
			$values .= "`$key`=" . ($questionMarkPlaceholders ? '?' : ":{$key}") . ", ";
		}
		$values = rtrim($values, ', ');

		return $values;
	}
}
