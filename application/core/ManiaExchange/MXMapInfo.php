<?php

namespace ManiaControl\ManiaExchange;

use ManiaControl\Formatter;

/**
 * Mania Exchange Map Info Object
 *
 * @author  Xymph
 * @updated lukas and steeffeen
 */
class MXMapInfo {
	public $prefix, $id, $uid, $name, $userid, $author, $uploaded, $updated, $type, $maptype;
	public $titlepack, $style, $envir, $mood, $dispcost, $lightmap, $modname, $exever;
	public $exebld, $routes, $length, $unlimiter, $laps, $difficulty, $lbrating, $trkvalue;
	public $replaytyp, $replayid, $replaycnt, $acomment, $awards, $comments, $rating;
	public $ratingex, $ratingcnt, $pageurl, $replayurl, $imageurl, $thumburl, $dloadurl;

	/**
	 * Returns map object with all available data from MX map data
	 *
	 * @param String $prefix MX URL prefix
	 * @param        $mx
	 * @internal param Object $map The MX map data from MXInfoSearcher
	 * @return MXMapInfo
	 */
	public function __construct($prefix, $mx) {
		$this->prefix = $prefix;
		if ($mx) {
			if ($this->prefix == 'tm') {
				$dir = 'tracks';
			} else { // 'sm' || 'qm'
				$dir = 'maps';
			}

			if ($this->prefix == 'tm' || !property_exists($mx, "MapID")) {
				$this->id  = $mx->TrackID;
				$this->uid = $mx->TrackUID;
			} else {
				$this->id  = $mx->MapID;
				$this->uid = $mx->MapUID;
			}

			if (!isset($mx->GbxMapName) || $mx->GbxMapName == '?') {
				$this->name = $mx->Name;
			} else {
				$this->name = Formatter::stripDirtyCodes($mx->GbxMapName);
			}

			$this->userid     = $mx->UserID;
			$this->author     = $mx->Username;
			$this->uploaded   = $mx->UploadedAt;
			$this->updated    = $mx->UpdatedAt;
			$this->type       = $mx->TypeName;
			$this->maptype    = isset($mx->MapType) ? $mx->MapType : '';
			$this->titlepack  = isset($mx->TitlePack) ? $mx->TitlePack : '';
			$this->style      = isset($mx->StyleName) ? $mx->StyleName : '';
			$this->envir      = $mx->EnvironmentName;
			$this->mood       = $mx->Mood;
			$this->dispcost   = $mx->DisplayCost;
			$this->lightmap   = $mx->Lightmap;
			$this->modname    = isset($mx->ModName) ? $mx->ModName : '';
			$this->exever     = $mx->ExeVersion;
			$this->exebld     = $mx->ExeBuild;
			$this->routes     = isset($mx->RouteName) ? $mx->RouteName : '';
			$this->length     = isset($mx->LengthName) ? $mx->LengthName : '';
			$this->unlimiter  = isset($mx->UnlimiterRequired) ? $mx->UnlimiterRequired : false;
			$this->laps       = isset($mx->Laps) ? $mx->Laps : 0;
			$this->difficulty = $mx->DifficultyName;
			$this->lbrating   = isset($mx->LBRating) ? $mx->LBRating : 0;
			$this->trkvalue   = isset($mx->TrackValue) ? $mx->TrackValue : 0;
			$this->replaytyp  = isset($mx->ReplayTypeName) ? $mx->ReplayTypeName : '';
			$this->replayid   = isset($mx->ReplayWRID) ? $mx->ReplayWRID : 0;
			$this->replaycnt  = isset($mx->ReplayCount) ? $mx->ReplayCount : 0;
			$this->acomment   = $mx->Comments;
			$this->awards     = isset($mx->AwardCount) ? $mx->AwardCount : 0;
			$this->comments   = $mx->CommentCount;
			$this->rating     = isset($mx->Rating) ? $mx->Rating : 0.0;
			$this->ratingex   = isset($mx->RatingExact) ? $mx->RatingExact : 0.0;
			$this->ratingcnt  = isset($mx->RatingCount) ? $mx->RatingCount : 0;

			if ($this->trkvalue == 0 && $this->lbrating > 0) {
				$this->trkvalue = $this->lbrating;
			} elseif ($this->lbrating == 0 && $this->trkvalue > 0) {
				$this->lbrating = $this->trkvalue;
			}

			$search         = array(chr(31), '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[url]', '[/url]');
			$replace        = array('<br/>', '<b>', '</b>', '<i>', '</i>', '<u>', '</u>', '<i>', '</i>');
			$this->acomment = str_ireplace($search, $replace, $this->acomment);
			$this->acomment = preg_replace('/\[url=.*\]/', '<i>', $this->acomment);

			$this->pageurl  = 'http://' . $this->prefix . '.mania-exchange.com/' . $dir . '/view/' . $this->id;
			$this->imageurl = 'http://' . $this->prefix . '.mania-exchange.com/' . $dir . '/screenshot/normal/' . $this->id;
			$this->thumburl = 'http://' . $this->prefix . '.mania-exchange.com/' . $dir . '/screenshot/small/' . $this->id;
			$this->dloadurl = 'http://' . $this->prefix . '.mania-exchange.com/' . $dir . '/download/' . $this->id;

			if ($this->prefix == 'tm' && $this->replayid > 0) {
				$this->replayurl = 'http://' . $this->prefix . '.mania-exchange.com/replays/download/' . $this->replayid;
			} else {
				$this->replayurl = '';
			}
		}
	}
}