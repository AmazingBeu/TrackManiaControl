<?php

namespace ManiaControl\Manialinks;

use FML\Controls\Control;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_BgRaceScore2;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\Script\Features\Paging;
use FML\Script\Script;
use ManiaControl\ManiaControl;

/**
 * Class managing default Control Styles
 *
 * @author steeffeen & kremsy
 * @copyright ManiaControl Copyright © 2014 ManiaControl Team
 * @license http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class StyleManager {
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

	const SETTING_ICON_DEFAULT_OFFSET_SM = 'Default Icon Offset in Shootmania';

	/*
	 * Private Properties
	 */
	private $maniaControl = null;

	/**
	 * Create a new style manager instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Init settings
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_LABEL_DEFAULT_STYLE, 'TextTitle1');
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_QUAD_DEFAULT_STYLE, 'Bgs1InRace');
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_QUAD_DEFAULT_SUBSTYLE, 'BgTitleShadow');

		//Main Widget
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_MAIN_WIDGET_DEFAULT_STYLE, Quad_BgRaceScore2::STYLE);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_MAIN_WIDGET_DEFAULT_SUBSTYLE, Quad_BgRaceScore2::SUBSTYLE_HandleSelectable);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_LIST_WIDGETS_WIDTH, '150');
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_LIST_WIDGETS_HEIGHT, '80');
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_ICON_DEFAULT_OFFSET_SM, '20');
	}

	/**
	 * Get the default Icon Offset for shootmania
	 *
	 * @return string
	 */
	public function getDefaultIconOffsetSM() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_ICON_DEFAULT_OFFSET_SM);
	}

	/**
	 * Get the default label style
	 *
	 * @return string
	 */
	public function getDefaultLabelStyle() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_LABEL_DEFAULT_STYLE);
	}

	/**
	 * Get the default quad style
	 *
	 * @return string
	 */
	public function getDefaultQuadStyle() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_QUAD_DEFAULT_STYLE);
	}

	/**
	 * Get the default quad substyle
	 *
	 * @return string
	 */
	public function getDefaultQuadSubstyle() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_QUAD_DEFAULT_SUBSTYLE);
	}

	/**
	 * Get the default main window style
	 *
	 * @return string
	 */
	public function getDefaultMainWindowStyle() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_MAIN_WIDGET_DEFAULT_STYLE);
	}

	/**
	 * Get the default main window substyle
	 *
	 * @return string
	 */
	public function getDefaultMainWindowSubStyle() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_MAIN_WIDGET_DEFAULT_SUBSTYLE);
	}

	/**
	 * Get the default list widget width
	 *
	 * @return string
	 */
	public function getListWidgetsWidth() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_LIST_WIDGETS_WIDTH);
	}

	/**
	 * Get the default list widget height
	 *
	 * @return string
	 */
	public function getListWidgetsHeight() {
		return $this->maniaControl->settingManager->getSetting($this, self::SETTING_LIST_WIDGETS_HEIGHT);
	}


	/**
	 * Gets the Default Description Label
	 *
	 * @return Label
	 */
	public function getDefaultDescriptionLabel() {
		$width  = $this->getListWidgetsWidth();
		$height = $this->getListWidgetsHeight();

		// Predefine Description Label
		$descriptionLabel = new Label();
		$descriptionLabel->setAlign(Control::LEFT, Control::TOP);
		$descriptionLabel->setPosition(-$width / 2 + 10, -$height / 2 + 5);
		$descriptionLabel->setSize($width * 0.7, 4);
		$descriptionLabel->setTextSize(2);
		$descriptionLabel->setVisible(false);

		return $descriptionLabel;
	}

	/**
	 * Builds the Default List Frame
	 *
	 * @return Frame $frame
	 */
	public function getDefaultListFrame(Script $script = null, $pagesId = '') {
        $paging = null;
        if ($script) {
            $paging = new Paging();
            $script->addFeature($paging);
        }
		$width        = $this->getListWidgetsWidth();
		$height       = $this->getListWidgetsHeight();
		$quadStyle    = $this->getDefaultMainWindowStyle();
		$quadSubstyle = $this->getDefaultMainWindowSubStyle();

		// mainframe
		$frame = new Frame();
		$frame->setSize($width, $height);
		$frame->setPosition(0, 0, 35); //TODO place before scoreboards

		// Background Quad
		$backgroundQuad = new Quad();
		$frame->add($backgroundQuad);
		$backgroundQuad->setSize($width, $height);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		// Add Close Quad (X)
		$closeQuad = new Quad_Icons64x64_1();
		$frame->add($closeQuad);
		$closeQuad->setPosition($width * 0.483, $height * 0.467, 3);
		$closeQuad->setSize(6, 6);
		$closeQuad->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_QuitRace);
		$closeQuad->setAction(ManialinkManager::ACTION_CLOSEWIDGET);

		if ($pagesId && isset($script)) {
			$pagerSize = 6.;
			$pagerPrev = new Quad_Icons64x64_1();
			$frame->add($pagerPrev);
			$pagerPrev->setPosition($width * 0.42, $height * -0.44, 2);
			$pagerPrev->setSize($pagerSize, $pagerSize);
			$pagerPrev->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_ArrowPrev);

			$pagerNext = new Quad_Icons64x64_1();
			$frame->add($pagerNext);
			$pagerNext->setPosition($width * 0.45, $height * -0.44, 2);
			$pagerNext->setSize($pagerSize, $pagerSize);
			$pagerNext->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_ArrowNext);

            if ($paging) {
                $paging->addButton($pagerNext);
                $paging->addButton($pagerPrev);
            }

			$pageCountLabel = new Label_Text();
			$frame->add($pageCountLabel);
			$pageCountLabel->setHAlign(Control::RIGHT);
			$pageCountLabel->setPosition($width * 0.40, $height * -0.44, 1);
			$pageCountLabel->setStyle($pageCountLabel::STYLE_TextTitle1);
			$pageCountLabel->setTextSize(1.3);
            if ($paging) {
                $paging->setLabel($pageCountLabel);
            }
		}

		return $frame;
	}
}
