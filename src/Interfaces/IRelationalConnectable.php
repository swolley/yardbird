<?php
namespace Swolley\YardBird\Interfaces;

interface IRelationalConnectable extends IConnectable
{
	/**
	 * bind out params by reference with custom parameters depending by driver
	 * @param	mixed	$params			parameters to be binded
	 * @param	mixed	$sth				statement. Mongo has no statement
	 * @param 	mixed	$outResult		reference to variable that will contain out values
	 * @param	int		$maxLength		max $outResultRef length
	 */
	static function bindOutParams(&$params, &$sth, &$outResult, int $maxLength = 40000): void;

	public function beginTransaction(): bool;

	public function commitTransaction(): bool;

	public function rollbackTransaction(): bool;
}
