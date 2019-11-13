<?php
namespace Swolley\YardBird\Interfaces;

interface IRelationalConnectable extends IConnectable
{
	/**
	 * bind out params by reference with custom parameters depending by driver
	 * @param	mixed	$params			parameters to be binded
	 * @param	mixed	$stmt				statement. Mongo has no statement
	 * @param 	mixed	$outResult		reference to variable that will contain out values
	 * @param	int		$maxLength		max $outResultRef length
	 */
	static function bindOutParams(&$params, &$stmt, &$outResult, int $maxLength = 40000): void;

	public function transaction(): bool;

	public function commit(): bool;

	public function rollback(): bool;
}
