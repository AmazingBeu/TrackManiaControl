<?php

namespace FML\Controls\Quads;

use FML\Controls\Quad;

/**
 * Quad Class for 'Icons128x32_1' Style
 *
 * @author steeffeen
 * @copyright FancyManiaLinks Copyright © 2014 Steffen Schröder
 * @license http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class Quad_Icons128x32_1 extends Quad {
	/*
	 * Constants
	 */
	const STYLE = 'Icons128x32_1';
	const SUBSTYLE_Empty = 'Empty';
	const SUBSTYLE_ManiaLinkHome = 'ManiaLinkHome';
	const SUBSTYLE_ManiaLinkSwitch = 'ManiaLinkSwitch';
	const SUBSTYLE_ManiaPlanet = 'ManiaPlanet';
	const SUBSTYLE_Minimize = 'Minimize';
	const SUBSTYLE_Music = 'Music';
	const SUBSTYLE_PainterBrush = 'PainterBrush';
	const SUBSTYLE_PainterFill = 'PainterFill';
	const SUBSTYLE_PainterLayer = 'PainterLayer';
	const SUBSTYLE_PainterMirror = 'PainterMirror';
	const SUBSTYLE_PainterSticker = 'PainterSticker';
	const SUBSTYLE_PainterTeam = 'PainterTeam';
	const SUBSTYLE_RT_Cup = 'RT_Cup';
	const SUBSTYLE_RT_Laps = 'RT_Laps';
	const SUBSTYLE_RT_Rounds = 'RT_Rounds';
	const SUBSTYLE_RT_Script = 'RT_Script';
	const SUBSTYLE_RT_Team = 'RT_Team';
	const SUBSTYLE_RT_TimeAttack = 'RT_TimeAttack';
	const SUBSTYLE_RT_Stunts = 'RT_Stunts';
	const SUBSTYLE_Settings = 'Settings';
	const SUBSTYLE_SliderBar = 'SliderBar';
	const SUBSTYLE_SliderBar2 = 'SliderBar2';
	const SUBSTYLE_SliderCursor = 'SliderCursor';
	const SUBSTYLE_Sound = 'Sound';
	const SUBSTYLE_UrlBg = 'UrlBg';
	const SUBSTYLE_Windowed = 'Windowed';

	/**
	 * Create a new Quad_Icons128x32_1 Control
	 *
	 * @param string $id (optional) Control Id
	 * @return \FML\Controls\Quads\Quad_Icons128x32_1
	 */
	public static function create($id = null) {
		$quadIcons128x32_1 = new Quad_Icons128x32_1($id);
		return $quadIcons128x32_1;
	}

	/**
	 * Construct a new Quad_Icons128x32_1 Control
	 *
	 * @param string $id (optional) Control Id
	 */
	public function __construct($id = null) {
		parent::__construct($id);
		$this->setStyle(self::STYLE);
	}
}
