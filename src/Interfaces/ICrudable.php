<?php
namespace Swolley\YardBird\Interfaces;

interface ICrudable
{
	/**
	 * execute inline or complex queries query
	 * @param 	string  		$query          			query text with placeholders
	 * @param 	array|object  	$params         			assoc array with placeholder's name and relative values
	 * @return	mixed										response array or error message
	 */
	function sql(string $query, $params = []): AbstractResult;

	/**
	 * with sql drivers this is a very simple and limited SELECT query builder whit list of fields and AND-separated where clauses
	 * @param  string		$table						table name
	 * @param  array 		$columns 					array with columns'name or column => alias
	 * @param  array 		$where  					string query part or assoc array with placeholder's name and relative values. Logical separator between elements will be AND
	 * @param  array 		$join 						joins array
	 * @param  array 		$orderBy					order by array
	 * @return mixed	response array or error message
	 */
	function select(string $table, array $params = [], array $where = [], array $join = [], array $orderBy = [], $limit = null): AbstractResult;

	/**
	 * execute insert query
	 * @param   string  		$table      table name
	 * @param   array|object	$params     assoc array with placeholder's name and relative values
	 * @param   boolean 		$ignore		performes an 'insert ignore' query
	 * @return  int|string|bool            	new row id if key is autoincremental or boolean
	 */
	function insert(string $table, $params, bool $ignore = false): AbstractResult;

	/**
	 * execute update query. Where is required, no massive update permitted
	 * @param   string  		$table	    table name
	 * @param   array|object	$params     assoc array with placeholder's name and relative values
	 * @param   string|array  	$where      where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
	 * @return  bool	                   	correct query execution confirm as boolean or error message
	 */
	function update(string $table, $params, $where = null): AbstractResult;

	/**
	 * execute delete query. Where is required, no massive delete permitted
	 * @param   string  		$table		table name
	 * @param   string|array  	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
	 * @param   array   		$params		assoc array with placeholder's name and relative values for where condition
	 * @return  bool						correct query execution confirm as boolean or error message
	 */
	function delete(string $table, $where = null, array $params): AbstractResult;

	/**
	 * execute procedure call.
	 * @param  string		$table          			procedure name
	 * @param  array	  	$inParams       			array of input parameters
	 * @param  array	  	$outParams      			array of output parameters
	 * @return mixed									AbstractResult, output params array or error message
	 */
	function procedure(string $name, array $inParams = [], array $outParams = []);
}