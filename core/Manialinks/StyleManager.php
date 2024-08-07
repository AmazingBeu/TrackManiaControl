<?php

namespace ManiaControl\Manialinks;

use FML\Controls\Entry;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Button;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_BgRaceScore2;
use FML\Controls\Quads\Quad_Bgs1InRace;
use FML\Controls\Quads\Quad_BgsPlayerCard;
use FML\Controls\Quads\Quad_Icons64x64_1;

use FML\Script\Features\Paging;
use FML\Script\Script;
use ManiaControl\General\UsageInformationAble;
use ManiaControl\General\UsageInformationTrait;
use ManiaControl\ManiaControl;

/**
 * Class managing default Control Styles
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class StyleManager implements UsageInformationAble {
	use UsageInformationTrait;

	/*
	 * Constants
	 */
	const SETTING_LABEL_DEFAULT_STYLE   = 'Default Label Style';
	const SETTING_QUAD_DEFAULT_STYLE    = 'Default Quad Style';
	const SETTING_QUAD_DEFAULT_SUBSTYLE = 'Default Quad SubStyle';

	const SETTING_MAIN_WIDGET_DEFAULT_STYLE    = 'Main Widget Default Quad Style';
	const SETTING_MAIN_WIDGET_DEFAULT_SUBSTYLE = 'Main Widget Default Quad SubStyle';
	const SETTING_LIST_WIDGETS_WIDTH           = 'List Widgets Width';
	const SETTING_LIST_WIDGETS_HEIGHT          = 'List Widgets Height';


	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * Construct a new style manager instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LABEL_DEFAULT_STYLE, Label_Text::STYLE_TextTitle1);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_QUAD_DEFAULT_STYLE, Quad_Bgs1InRace::STYLE);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_QUAD_DEFAULT_SUBSTYLE, Quad_Bgs1InRace::SUBSTYLE_BgTitleShadow);

		// Main Widget
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MAIN_WIDGET_DEFAULT_STYLE, Quad_BgRaceScore2::STYLE);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MAIN_WIDGET_DEFAULT_SUBSTYLE, Quad_BgRaceScore2::SUBSTYLE_HandleSelectable);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LIST_WIDGETS_WIDTH, 150.);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LIST_WIDGETS_HEIGHT, 80.);
	}

	/**
	 * Get the default label style
	 *
	 * @return string
	 */
	public function getDefaultLabelStyle() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LABEL_DEFAULT_STYLE);
	}

	/**
	 * Get the default quad style
	 *
	 * @return string
	 */
	public function getDefaultQuadStyle() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_QUAD_DEFAULT_STYLE);
	}

	/**
	 * Get the default quad substyle
	 *
	 * @return string
	 */
	public function getDefaultQuadSubstyle() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_QUAD_DEFAULT_SUBSTYLE);
	}

	/**
	 * Gets the Default Description Label
	 *
	 * @return \FML\Controls\Label
	 */
	public function getDefaultDescriptionLabel() {
		$width  = $this->getListWidgetsWidth();
		$height = $this->getListWidgetsHeight();

		// Predefine Description Label
		$descriptionLabel = new Label();
		$descriptionLabel->setAlign($descriptionLabel::LEFT, $descriptionLabel::TOP)->setPosition($width * -0.5 + 10, $height * -0.5 + 5)->setZ(1)->setSize($width * 0.7, 4)->setTextSize(1)->setVisible(false);

		return $descriptionLabel;
	}


	/**
	 * Gets the default buttons and textbox for a map search
	 *
	 * @param      $actionMapNameSearch
	 * @param      $actionAuthorSearch
	 * @param null $actionReset
	 * @return \FML\Controls\Frame
	 */
	public function getDefaultMapSearch($actionMapNameSearch, $actionAuthorSearch, $actionReset = null, $entryvalue = "") {
		$width = $this->getListWidgetsWidth();

		$frame = new Frame();

		$posX = -$width / 2 + 5;

		$label = new Label_Text();
		$frame->addChild($label);
		$label->setPosition($posX, 0);
		$label->setHorizontalAlign($label::LEFT);
		$label->setTextSize(1);
		$label->setText('Search: ');

		$posX += 10;

		$entry = new Entry();
		$frame->addChild($entry);
		$entry->setStyle(Label_Text::STYLE_TextValueSmall);
		$entry->setHorizontalAlign($entry::LEFT);
		$entry->setPosition($posX, 0);
		$entry->setTextSize(1);
		$entry->setSize($width * 0.28, 4);
		$entry->setName('SearchString');
		$entry->setDefault($entryvalue);

		$posX += $width * 0.28 + 10;

		if ($actionReset) {
			$quad = new Quad_Icons64x64_1();
			$frame->addChild($quad);
			$quad->setSubStyle($quad::SUBSTYLE_QuitRace);
			$quad->setColorize('aaa');
			$quad->setSize(5, 5);
			$quad->setPosition($posX - 12, 0);
			$quad->setZ(1);
			$quad->setAction($actionReset);
		}

		//Search for Map-Name
		$mapNameButton = $this->maniaControl->getManialinkManager()->getElementBuilder()->buildRoundTextButton(
			'MapName',
			18,
			5,
			$actionMapNameSearch
		);
		$frame->addChild($mapNameButton);
		$mapNameButton->setX($posX);

		$posX += 20;

		//Search for Author
		$authorButton = $this->maniaControl->getManialinkManager()->getElementBuilder()->buildRoundTextButton(
			'Author',
			18,
			5,
			$actionAuthorSearch
		);
		$frame->addChild($authorButton);
		$authorButton->setX($posX);

		return $frame;
	}

	/**
	 * Get the Default List Widgets Width
	 *
	 * @return float
	 */
	public function getListWidgetsWidth() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LIST_WIDGETS_WIDTH);
	}

	/**
	 * Get the default list widget height
	 *
	 * @return float
	 */
	public function getListWidgetsHeight() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LIST_WIDGETS_HEIGHT);
	}

	/**
	 * Builds the Default List Frame
	 *
	 * @param mixed $script
	 * @param mixed $paging
	 * @return \FML\Controls\Frame
	 */
	public function getDefaultListFrame($script = null, $paging = null) {
		$args   = func_get_args();
		$script = null;
		$paging = null;
		foreach ($args as $arg) {
			if ($arg instanceof Script) {
				$script = $arg;
			}
			if ($arg instanceof Paging) {
				$paging = $arg;
			}
		}

		$width        = $this->getListWidgetsWidth();
		$height       = $this->getListWidgetsHeight();
		$quadStyle    = $this->getDefaultMainWindowStyle();
		$quadSubstyle = $this->getDefaultMainWindowSubStyle();

		// mainframe
		$frame = new Frame();
		$frame->setSize($width, $height)->setZ(ManialinkManager::MAIN_MANIALINK_Z_VALUE);

		// Background Quad
		$backgroundQuad = new Quad();
		$frame->addChild($backgroundQuad);
		$backgroundQuad->setZ(-2)->setSize($width, $height)->setStyles($quadStyle, $quadSubstyle);

		// Add Close Quad (X)
		$closeQuad = new Quad_Icons64x64_1();
		$frame->addChild($closeQuad);
		$closeQuad->setPosition($width / 2 - 3, $height / 2 - 3, 3)->setSize(6, 6)->setSubStyle($closeQuad::SUBSTYLE_QuitRace)->setAction(ManialinkManager::ACTION_CLOSEWIDGET);

		if ($script) {
			$pagerSize = 6.;
			$pagerPrev = new Quad_Icons64x64_1();
			$frame->addChild($pagerPrev);
			$pagerPrev->setPosition($width * 0.5 - 12, $height * -0.5 + 5, 2);
			$pagerPrev->setSize($pagerSize, $pagerSize);
			$pagerPrev->setSubStyle($pagerPrev::SUBSTYLE_ArrowPrev);

			$pagerNext = new Quad_Icons64x64_1();
			$frame->addChild($pagerNext);
			$pagerNext->setPosition($width * 0.5 - 5, $height * -0.5 + 5, 2);
			$pagerNext->setSize($pagerSize, $pagerSize);
			$pagerNext->setSubStyle($pagerNext::SUBSTYLE_ArrowNext);

			$pageCountLabel = new Label_Text();
			$frame->addChild($pageCountLabel);
			$pageCountLabel->setHorizontalAlign($pageCountLabel::RIGHT);
			$pageCountLabel->setPosition($width * 0.5 - 16, $height * -0.5 + 5, 1);
			$pageCountLabel->setStyle($pageCountLabel::STYLE_TextTitle1);
			$pageCountLabel->setTextSize(1);

			if ($paging) {
				$paging->addButtonControl($pagerNext);
				$paging->addButtonControl($pagerPrev);
				$paging->setLabel($pageCountLabel);
			}
		}

		return $frame;
	}

	/**
	 * Get the default main window style
	 *
	 * @return string
	 */
	public function getDefaultMainWindowStyle() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MAIN_WIDGET_DEFAULT_STYLE);
	}

	/**
	 * Get the default main window substyle
	 *
	 * @return string
	 */
	public function getDefaultMainWindowSubStyle() {
		return $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MAIN_WIDGET_DEFAULT_SUBSTYLE);
	}
}
