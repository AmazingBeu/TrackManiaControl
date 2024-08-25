<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

#[\AllowDynamicProperties] // Allow Dynamic Properties for php 8.2 and above
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
	/** @var string */
	public $nextCallVoteTimeOut;
	/** @var float */
	public $callVoteRatio;

	/**
	 * @internal
	 * @return bool
	 */
	public function isValid(): bool
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
	public function toSetterArray()
	{
		$out = [];
		foreach (get_object_vars($this) as $key => $value) {
			if (str_starts_with($key, 'current') || $value === null) {
				continue;
			}
			if ($key === 'nextUseChangingValidationSeed') {
				$key = 'useChangingValidationSeed';
			}
			$out[ucfirst($key)] = $value;
		}
		return $out;
	}
}
