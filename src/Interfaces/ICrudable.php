<?php
namespace Swolley\YardBird\Interfaces;

interface ICrudable
{
	/**
	 * execute inline or complex queries query
	 * @param 	string  		$query          			query text with placeholders
	 * @param 	array|object  	$params         			assoc array with placeholder's name and relative values
	 * @param 	int     		$fetchMode     				(optional) PDO fetch mode. default = associative array
	 * @param	int|string		$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param	int|string		$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return	mixed										response array or error message
	 */
	function sql(string $query, $params = [], int $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []);

	/**
	 * with sql drivers this is a very simple and limited SELECT query builder whit list of fields and AND-separated where clauses
	 * @param  string		$table						table name
	 * @param  array 		$columns 					array with columns'name or column => alias
	 * @param  array 		$where  					string query part or assoc array with placeholder's name and relative values. Logical separator between elements will be AND
	 * @param  array 		$join 						joins array
	 * @param  array 		$orderBy					order by array
	 * @param  int 			$fetchMode  				(optional) PDO fetch mode. default = associative array
	 * @param  int|string	$fetchModeParam 			(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param  int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return mixed	response array or error message
	 */
	function select(string $table, array $params = [], array $where = [], array $join = [], array $orderBy = [], $limit = null, int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	/**
	 * execute insert query
	 * @param   string  		$table      table name
	 * @param   array|object	$params     assoc array with placeholder's name and relative values
	 * @param   boolean 		$ignore		performes an 'insert ignore' query
	 * @return  int|string|bool            	new row id if key is autoincremental or boolean
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
	function delete(string $table, $where = null, array $params): bool;

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
	function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): ?array;
}