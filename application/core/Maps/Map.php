<?php

namespace ManiaControl\Maps;

use ManiaControl\Formatter;
use ManiaControl\ManiaControl;

/**
 * Map Class
 *
 * @author kremsy & steeffeen
 */
class Map {
	/**
	 * Public Properties
	 */
	public $index = -1;
	public $name = 'undefined';
	public $uid = '';
	public $fileName = '';
	public $environment = '';
	public $goldTime = -1;
	public $copperPrice = -1;
	public $mapType = '';
	public $mapStyle = '';
	public $nbCheckpoints = -1;
	/** @var MXMapInfo $mx */
	public $mx = null;
	public $authorLogin = '';
	public $authorNick = '';
	public $authorZone = '';
	public $authorEInfo = '';
	public $comment = '';
	public $titleUid = '';
	public $startTime = -1;
	public $lastUpdate = 0;

	/**
	 * Private Properties
	 */
	private $maniaControl = null;

	/**
	 * Create a new Map Object from Rpc Data
	 *
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @param array                      $rpc_infos
	 */
	public function __construct(ManiaControl $maniaControl, $rpc_infos = null) {
		$this->maniaControl = $maniaControl;
		$this->startTime    = time();

		if(!$rpc_infos) {
			return;
		}
		$this->name        = FORMATTER::stripDirtyCodes($rpc_infos['Name']);
		$this->uid         = $rpc_infos['UId'];
		$this->fileName    = $rpc_infos['FileName'];
		$this->authorLogin = $rpc_infos['Author'];
		$this->environment = $rpc_infos['Environnement'];
		$this->goldTime    = $rpc_infos['GoldTime'];
		$this->copperPrice = $rpc_infos['CopperPrice'];
		$this->mapType     = $rpc_infos['MapType'];
		$this->mapStyle    = $rpc_infos['MapStyle'];

		if(isset($rpc_infos['NbCheckpoints'])) {
			$this->nbCheckpoints = $rpc_infos['NbCheckpoints'];
		}

		$this->authorNick = $this->authorLogin;

		$mapsDirectory = $this->maniaControl->server->getMapsDirectory();
		if($this->maniaControl->server->checkAccess($mapsDirectory)) {
			$mapFetcher = new \GBXChallMapFetcher(true);
			try {
				$mapFetcher->processFile($mapsDirectory . $this->fileName);
				$this->authorNick  = FORMATTER::stripDirtyCodes($mapFetcher->authorNick);
				$this->authorEInfo = $mapFetcher->authorEInfo;
				$this->authorZone  = $mapFetcher->authorZone;
				$this->comment     = $mapFetcher->comment;
			} catch(\Exception $e) {
				trigger_error($e->getMessage());
			}
		}
	}

	/**
	 * Checks if a map Update is available
	 *
	 * @return bool
	 */
	public function updateAvailable() {
		if($this->lastUpdate < $this->mx->updated || $this->uid != $this->mx->uid) {
			return true;
		} else {
			return false;
		}
	}
} 