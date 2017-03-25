<?php

namespace MCTeam;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_BgRaceScore2;
use FML\Controls\Quads\Quad_BgsPlayerCard;
use FML\ManiaLink;
use FML\Script\Features\Paging;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Bills\BillManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;

/**
 * ManiaControl Donation Plugin
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2017 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class DonationPlugin implements CallbackListener, CommandListener, Plugin {
	/*
	 * Constants
	 */
	const ID                               = 3;
	const VERSION                          = 0.1;
	const AUTHOR                           = 'MCTeam';
	const NAME                             = 'Donation Plugin';
	const SETTING_ANNOUNCE_SERVER_DONATION = 'Enable Server-Donation Announcements';
	const STAT_PLAYER_DONATIONS            = 'Donated Planets';
	const ACTION_DONATE_VALUE              = 'Donate.DonateValue';

	// DonateWidget Properties
	const MLID_DONATE_WIDGET              = 'DonationPlugin.DonateWidget';
	const SETTING_DONATE_WIDGET_ACTIVATED = 'Donate-Widget Activated';
	const SETTING_DONATE_WIDGET_POSX      = 'Donate-Widget-Position: X';
	const SETTING_DONATE_WIDGET_POSY      = 'Donate-Widget-Position: Y';
	const SETTING_DONATE_WIDGET_WIDTH     = 'Donate-Widget-Size: Width';
	const SETTING_DONATE_WIDGET_HEIGHT    = 'Donate-Widget-Size: Height';
	const SETTING_DONATION_VALUES         = 'Donation Values';
	const SETTING_MIN_AMOUNT_SHOWN        = 'Minimum Donation amount to get shown';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * @see \ManiaControl\Plugins\Plugin::prepare()
	 */
	public static function prepare(ManiaControl $maniaControl) {
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getId()
	 */
	public static function getId() {
		return self::ID;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getName()
	 */
	public static function getName() {
		return self::NAME;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getVersion()
	 */
	public static function getVersion() {
		return self::VERSION;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getAuthor()
	 */
	public static function getAuthor() {
		return self::AUTHOR;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getDescription()
	 */
	public static function getDescription() {
		return 'Plugin offering Commands like /donate, /pay and /planets and a Donation Widget.';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Register for commands
		$this->maniaControl->getCommandManager()->registerCommandListener('donate', $this, 'command_Donate', false, 'Donate some planets to the server.');
		$this->maniaControl->getCommandManager()->registerCommandListener('pay', $this, 'command_Pay', true, 'Pays planets from the server to a player.');
		$this->maniaControl->getCommandManager()->registerCommandListener(array('getplanets', 'planets'), $this, 'command_GetPlanets', true, 'Checks the planets-balance of the server.');
		$this->maniaControl->getCommandManager()->registerCommandListener('topdons', $this, 'command_TopDons', false, 'Provides an overview of who donated the most planets.');

		// Register for callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');

		// Define player stats
		$this->maniaControl->getStatisticManager()->defineStatMetaData(self::STAT_PLAYER_DONATIONS);

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DONATE_WIDGET_ACTIVATED, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DONATE_WIDGET_POSX, 156.);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DONATE_WIDGET_POSY, -31.4);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DONATE_WIDGET_WIDTH, 6);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DONATE_WIDGET_HEIGHT, 6);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DONATION_VALUES, "20,50,100,500,1000,2000");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MIN_AMOUNT_SHOWN, 100);

		// Register Stat in Simple StatsList
		$this->maniaControl->getStatisticManager()->getSimpleStatsList()->registerStat(self::STAT_PLAYER_DONATIONS, 90, "DP", 15);

		$this->displayWidget();
		return true;
	}

	/**
	 * Display the widget
	 */
	public function displayWidget() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DONATE_WIDGET_ACTIVATED)
		) {
			$this->displayDonateWidget();
		}
	}

	/**
	 * Display the Donation Widget
	 *
	 * @param string $login
	 */
	public function displayDonateWidget($login = null) {
		$posX              = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DONATE_WIDGET_POSX);
		$posY              = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DONATE_WIDGET_POSY);
		$width             = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DONATE_WIDGET_WIDTH);
		$height            = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DONATE_WIDGET_HEIGHT);
		$values            = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DONATION_VALUES);
		$shootManiaOffset  = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultIconOffsetSM();
		$quadStyle         = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
		$quadSubstyle      = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
		$itemMarginFactorX = 1.3;
		$itemMarginFactorY = 1.2;

		//If game is shootmania lower the icons position by 20
		if ($this->maniaControl->getMapManager()->getCurrentMap()->getGame() === 'sm'
		) {
			$posY -= $shootManiaOffset;
		}

		$itemSize = $width;

		$maniaLink = new ManiaLink(self::MLID_DONATE_WIDGET);

		// Donate Menu Icon Frame
		$frame = new Frame();
		$maniaLink->addChild($frame);
		$frame->setPosition($posX, $posY);

		$backgroundQuad = new Quad();
		$frame->addChild($backgroundQuad);
		$backgroundQuad->setSize($width * $itemMarginFactorX, $height * $itemMarginFactorY);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		$iconFrame = new Frame();
		$frame->addChild($iconFrame);

		$iconFrame->setSize($itemSize, $itemSize);
		$itemQuad = new Quad_BgRaceScore2();
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_Points);
		$itemQuad->setSize($itemSize, $itemSize);
		$iconFrame->addChild($itemQuad);

		$valueArray = explode(',', $values);

		// Values Menu
		$popoutFrame = new Frame();
		$maniaLink->addChild($popoutFrame);
		$popoutFrame->setPosition($posX - $itemSize * 0.5, $posY);
		$popoutFrame->setHorizontalAlign($popoutFrame::RIGHT);
		$popoutFrame->setSize(4 * $itemSize * $itemMarginFactorX, $itemSize * $itemMarginFactorY);
		$popoutFrame->setVisible(false);

		$quad = new Quad();
		$popoutFrame->addChild($quad);
		$quad->setHorizontalAlign($quad::RIGHT);
		$quad->setStyles($quadStyle, $quadSubstyle);
		$quad->setSize(strlen($values) * 2 + count($valueArray) * 1, $itemSize * $itemMarginFactorY);

		$popoutFrame->addChild($quad);
		$itemQuad->addToggleFeature($popoutFrame);

		// Description Label
		$descriptionFrame = new Frame();
		$maniaLink->addChild($descriptionFrame);
		$descriptionFrame->setPosition($posX - 50, $posY - 5);
		$descriptionFrame->setHorizontalAlign($descriptionFrame::RIGHT);

		$descriptionLabel = new Label();
		$descriptionFrame->addChild($descriptionLabel);
		$descriptionLabel->setAlign($descriptionLabel::LEFT, $descriptionLabel::TOP);
		$descriptionLabel->setSize(40, 4);
		$descriptionLabel->setTextSize(2);
		$descriptionLabel->setVisible(true);
		$descriptionLabel->setTextColor('0f0');

		// Add items
		$posX = -2;
		foreach (array_reverse($valueArray) as $value) {
			$label = new Label_Text();
			$popoutFrame->addChild($label);
			$label->setX($posX);
			$label->setHorizontalAlign($label::RIGHT);
			$label->setText('$s$FFF' . $value . '$09FP');
			$label->setTextSize(1.2);
			$label->setAction(self::ACTION_DONATE_VALUE . "." . $value);
			$label->setStyle($label::STYLE_TextCardSmall);
			$description = "Donate {$value} Planets";
			$label->addTooltipLabelFeature($descriptionLabel, $description);

			$posX -= strlen($value) * 2 + 1.7;
		}

		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_DONATE_WIDGET);
	}

	/**
	 * Handle ManialinkPageAnswer Callback
	 *
	 * @param array $callback
	 */
	public function handleManialinkPageAnswer(array $callback) {
		$actionId    = $callback[1][2];
		$boolSetting = (strpos($actionId, self::ACTION_DONATE_VALUE) === 0);
		if (!$boolSetting) {
			return;
		}
		$login       = $callback[1][1];
		$player      = $this->maniaControl->getPlayerManager()->getPlayer($login);
		$actionArray = explode(".", $callback[1][2]);
		$this->handleDonation($player, intval($actionArray[2]));
	}

	/**
	 * Handle a Player Donation
	 *
	 * @param Player $player
	 * @param int    $amount
	 * @param string $receiver
	 * @param string $receiverName
	 */
	private function handleDonation(Player $player, $amount, $receiver = '', $receiverName = null) {
		if ($amount > 1000000) {
			// Prevent too huge donation amounts that would cause xmlrpc parsing errors
			$message = "You can only donate 1.000.000 Planets at a time!";
			$this->maniaControl->getChat()->sendError($message, $player);
			return;
		}

		//FIXME if you write "/donate 50 hallo" than comes Message: Donate to Hallo
		if (!$receiverName) {
			$serverName = $this->maniaControl->getClient()->getServerName();
			$message    = 'Donate ' . $amount . ' Planets to $<' . $serverName . '$>?';
		} else {
			$message = 'Donate ' . $amount . ' Planets to $<' . $receiverName . '$>?';
		}

		//Send and Handle the Bill
		$this->maniaControl->getBillManager()->sendBill(function ($data, $status) use (&$player, $amount, $receiver) {
			switch ($status) {
				case BillManager::DONATED_TO_SERVER:
					if ($this->maniaControl->getSettingManager()->getSettingValue($this, DonationPlugin::SETTING_ANNOUNCE_SERVER_DONATION, true)
					    && $amount >= $this->maniaControl->getSettingManager()->getSettingValue($this, DonationPlugin::SETTING_MIN_AMOUNT_SHOWN, true)
					) {
						$login   = null;
						$message = $player->getEscapedNickname() . ' donated ' . $amount . ' Planets! Thanks.';
					} else {
						$login   = $player->login;
						$message = 'Donation successful! Thanks.';
					}
					$this->maniaControl->getChat()->sendSuccess($message, $login);
					$this->maniaControl->getStatisticManager()->insertStat(DonationPlugin::STAT_PLAYER_DONATIONS, $player, $this->maniaControl->getServer()->index, $amount);
					break;
				case BillManager::DONATED_TO_RECEIVER:
					$message = "Successfully donated {$amount} to '{$receiver}'!";
					$this->maniaControl->getChat()->sendSuccess($message, $player);
					break;
				case BillManager::PLAYER_REFUSED_DONATION:
					$message = 'Transaction cancelled.';
					$this->maniaControl->getChat()->sendError($message, $player);
					break;
				case BillManager::ERROR_WHILE_TRANSACTION:
					$message = $data;
					$this->maniaControl->getChat()->sendError($message, $player);
					break;
			}
		}, $player, $amount, $message);
	}

	/**
	 * Handle PlayerConnect callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		// Display Map Widget
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DONATE_WIDGET_ACTIVATED)
		) {
			$this->displayDonateWidget($player->login);
		}
	}

	/**
	 * Handle /donate command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_Donate(array $chatCallback, Player $player) {
		$text   = $chatCallback[1][2];
		$params = explode(' ', $text);
		if (count($params) < 2) {
			$this->sendDonateUsageExample($player);
			return;
		}
		$amount = (int) $params[1];
		if (!$amount || $amount <= 0) {
			$this->sendDonateUsageExample($player);
			return;
		}
		if (count($params) >= 3) {
			$receiver       = $params[2];
			$receiverPlayer = $this->maniaControl->getPlayerManager()->getPlayer($receiver);
			$receiverName   = ($receiverPlayer ? $receiverPlayer->nickname : $receiver);
		} else {
			$receiver     = '';
			$receiverName = $this->maniaControl->getClient()->getServerName();
		}

		$this->handleDonation($player, $amount, $receiver, $receiverName);
	}

	/**
	 * Send an usage example for /donate to the player
	 *
	 * @param Player $player
	 */
	private function sendDonateUsageExample(Player $player) {
		$message = "Usage Example: '/donate 100'";
		$this->maniaControl->getChat()->sendChat($message, $player);
	}

	/**
	 * Handle //pay command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_Pay(array $chatCallback, Player $player) {
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::AUTH_LEVEL_SUPERADMIN)
		) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		$text   = $chatCallback[1][2];
		$params = explode(' ', $text);
		if (count($params) < 2) {
			$this->sendPayUsageExample($player);
			return;
		}
		$amount = (int) $params[1];
		if (!$amount || $amount <= 0) {
			$this->sendPayUsageExample($player);
			return;
		}
		if (count($params) >= 3) {
			$receiver = $params[2];
		} else {
			$receiver = $player->login;
		}
		$message = 'Payout from $<' . $this->maniaControl->getClient()->getServerName() . '$>.';

		$this->maniaControl->getBillManager()->sendPlanets(function ($data, $status) use (&$player, $amount, $receiver) {
			switch ($status) {
				case BillManager::PAYED_FROM_SERVER:
					$message = "Successfully payed out {$amount} to '{$receiver}'!";
					$this->maniaControl->getChat()->sendSuccess($message, $player);
					break;
				case BillManager::PLAYER_REFUSED_DONATION:
					$message = 'Transaction cancelled.';
					$this->maniaControl->getChat()->sendError($message, $player);
					break;
				case BillManager::ERROR_WHILE_TRANSACTION:
					$message = $data;
					$this->maniaControl->getChat()->sendError($message, $player);
					break;
			}
		}, $receiver, $amount, $message);
	}

	/**
	 * Send an usage example for /pay to the player
	 *
	 * @param Player $player
	 */
	private function sendPayUsageExample(Player $player) {
		$message = "Usage Example: '//pay 100 login'";
		$this->maniaControl->getChat()->sendChat($message, $player);
	}

	/**
	 * Handle //getplanets command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_GetPlanets(array $chatCallback, Player $player) {
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::AUTH_LEVEL_ADMIN)
		) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		$planets = $this->maniaControl->getClient()->getServerPlanets();
		$message = "This Server has {$planets} Planets!";
		$this->maniaControl->getChat()->sendInformation($message, $player);
	}

	/**
	 * Handles the /topdons command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_TopDons(array $chatCallback, Player $player) {
		$this->showTopDonsList($player);
	}

	/**
	 * Provide an Overview ManiaLink with Donators
	 *
	 * @param Player $player
	 */
	private function showTopDonsList(Player $player) {
		$stats = $this->maniaControl->getStatisticManager()->getStatsRanking(self::STAT_PLAYER_DONATIONS);

		$width  = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
		$height = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsHeight();

		// create manialink
		$maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);
		$script    = $maniaLink->getScript();
		$paging    = new Paging();
		$script->addFeature($paging);

		// Main frame
		$frame = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultListFrame($script, $paging);
		$maniaLink->addChild($frame);

		// Start offsets
		$posX = -$width / 2;
		$posY = $height / 2;

		//Predefine description Label
		$descriptionLabel = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultDescriptionLabel();
		$frame->addChild($descriptionLabel);

		// Headline
		$headFrame = new Frame();
		$frame->addChild($headFrame);
		$headFrame->setY($posY - 5);
		$array = array('$oId' => $posX + 5, '$oNickname' => $posX + 18, '$oLogin' => $posX + 70, '$oDonated planets' => $posX + 110);
		$this->maniaControl->getManialinkManager()->labelLine($headFrame, $array);

		$index     = 1;
		$posY      = $posY - 10;
		$pageFrame = null;

		foreach ($stats as $playerIndex => $donations) {
			if ($index % 15 === 1) {
				$pageFrame = new Frame();
				$frame->addChild($pageFrame);
				$posY = $height / 2 - 10;
				$paging->addPage($pageFrame);
			}

			$playerFrame = new Frame();
			$pageFrame->addChild($playerFrame);
			$playerFrame->setY($posY);

			if ($index % 2 !== 0) {
				$lineQuad = new Quad_BgsPlayerCard();
				$playerFrame->addChild($lineQuad);
				$lineQuad->setSize($width, 4);
				$lineQuad->setSubStyle($lineQuad::SUBSTYLE_BgPlayerCardBig);
				$lineQuad->setZ(0.001);
			}

			$donatingPlayer = $this->maniaControl->getPlayerManager()->getPlayerByIndex($playerIndex);

			$positions = array($posX + 5, $posX + 18, $posX + 70, $posX + 110);
			$texts     = array($index, $donatingPlayer->nickname, $donatingPlayer->login, $donations);
			$this->maniaControl->getManialinkManager()->labelLine($playerFrame, array($positions, $texts));

			$posY -= 4;
			$index++;

			if ($index > 100) {
				break;
			}
		}

		// Render and display xml
		$this->maniaControl->getManialinkManager()->displayWidget($maniaLink, $player, 'TopDons');
	}
}
