<?php

namespace ManiaControl\Callbacks\Structures\ManiaPlanet;



use ManiaControl\Callbacks\Structures\Common\BaseTimeStructure;
use ManiaControl\ManiaControl;

/**
 * Structure Class for the Default Start End Callbacks
 *
 * @api
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class StartEndStructure extends BaseTimeStructure {
	private $count;
	private $valid;

	/**
	 * StartEndStructure constructor.
	 *
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @param                            $data
	 */
	public function __construct(ManiaControl $maniaControl, $data) {
		parent::__construct($maniaControl, $data);

		$this->count = $this->getPlainJsonObject()->count;
		$this->valid = $this->getPlainJsonObject()->valid;
	}

	/**
	 * Get the Count of this Section
	 *
	 * @api
	 * @return int
	 */
	public function getCount() {
		return $this->count;
	}

	/**
	 * Get the Valid Round Count of this Section
	 *
	 * @api
	 * @return int
	 */
	public function getValidRoundCount() {
		return $this->valid;
	}
}