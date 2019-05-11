<?php
namespace Swolley\Database\Interfaces;

interface IConnectable
{
	/**
	 * @param	array	$params	connection parameters
	 * @return  array	parsed and validated parameters
	 * @throws	\BadMethodCallException	if missing parameters
	 * @throws	\UnexpectedValueException if no requested driver available
	 */
	static function validateConnectionParams(array $params): array;

	/**
	 * @param	array	$params	connection parameters
	 * @return	array	parsed and aggregated connection's params
	 * @throws	\BadMethodCallException		if missing parameters
	 * @throws	\UnexpectedValueException	if wrong values in parameters
	 */
	static function composeConnectionParams(array $params, array $init_Array = []): array;

	/**
	 * execute generic query
	 * @param 	string  		$query          			query text with placeholders
	 * @param 	array|object  	$params         			assoc array with placeholder's name and relative values
	 * @param 	int     		$fetchMode     				(optional) PDO fetch mode. default = associative array
	 * @param	int|string		$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param	int|string		$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return	mixed										response array or error message
	 */
	function sql(string $query, $params = [], int $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	/**
	 * with sql drivers this is a very simple and limited SELECT query builder whit list of fields and AND-separated where clauses
	 * @param   string  		$table      				table name
	 * @param   array			$params     				assoc array with columns'name 
	 * @param   array			$where     					assoc array with placeholder's name and relative values. Logical separator between elements is AND
	 * @param 	int     		$fetchMode     				(optional) PDO fetch mode. default = associative array
	 * @param	int|string		$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param	int|string		$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return	mixed										response array or error message
	 */
	function select(string $table, array $params = [], array $where = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	/**
	 * execute insert query
	 * @param   string  		$table      table name
	 * @param   array|object	$params     assoc array with placeholder's name and relative values
	 * @param   boolean 		$ignore		performes an 'insert ignore' query
	 * @return  int|bool                 	new row id if key is autoincremental or boolean
	 */
	function insert(string $table, $params, bool $ignore = false);

	/**
	 * execute update query. Where is required, no massive update permitted
	 * @param   string  		$table	    table name
	 * @param   array|object	$params     assoc array with placeholder's name and relative values
	 * @param   string|array  	$where      where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
	 * @return  bool	                   	correct query execution confirm as boolean or error message
	 */
	function update(string $table, $params, $where = null): bool;

	/**
	 * execute delete query. Where is required, no massive delete permitted
	 * @param   string  		$table		table name
	 * @param   string|array  	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
	 * @param   array   		$params		assoc array with placeholder's name and relative values for where condition
	 * @return  bool						correct query execution confirm as boolean or error message
	 */
	function delete(string $table, array $params, $where = null): bool;

	/**
	 * execute procedure call.
	 * @param  string		$table          			procedure name
	 * @param  array	  	$inParams       			array of input parameters
	 * @param  array	  	$outParams      			array of output parameters
	 * @param  int     		$fetchMode     				(optional) PDO fetch mode. default = associative array
	 * @param	int|string	$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param	int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return mixed									response array or error message
	 */
	function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	/**
	 * fetch and parse query results
	 * @param	mixed		$st						statement
	 * @param	int			$fetchMode				(optional) fetch mode. default ASSOCIATIVE ARRAY
	 * @param	int|string	$fetchModeParam			(optional) fetch mode param if fetch mode is class or column
	 * @param	array		$fetchPropsLateParams	(optional) constructor params if fetch mode has FETCH_PROPS_LATE option
	 * @param	array								fetched and parsed data
	 */
	static function fetch($st, int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	/**
	 * bind passed parameters for sql injection
	 * @param	array	$params	parameters to be binded
	 * @param	mixed	$st		(optional) statement. Mongo has no statement
	 * @return	bool			params binded correctly
	 */
	static function bindParams(array &$params, &$st = null): bool;
}
