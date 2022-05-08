<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class ServerOptions extends AbstractStructure
{
	/** @var string */
	public $name;
	/** @var string */
	public $comment;
	/** @var string */
	public $password;
	/** @var string */
	public $passwordForSpectator;
	/** @var float */
	public $callVoteRatio;

	/**
	 * @internal
	 * @return bool
	 */
	function isValid()
	{
		return is_string($this->name)
			&& is_string($this->comment)
			&& is_string($this->password)
			&& is_string($this->passwordForSpectator)
			&& is_int($this->nextCallVoteTimeOut)
			&& VoteRatio::isRatio($this->callVoteRatio);
	}

	/**
	 * @internal
	 * @return mixed[]
	 */
	function toSetterArray()
	{
		$out = array();
		foreach(get_object_vars($this) as $key => $value)
		{
			if(substr($key, 0, 7) == 'current' || $value === null)
				continue;
			if($key == 'nextUseChangingValidationSeed')
				$key = 'useChangingValidationSeed';
			$out[ucfirst($key)] = $value;
		}
		return $out;
	}
}
