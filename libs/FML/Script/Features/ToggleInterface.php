<?php

namespace FML\Script\Features;

use FML\Script\Builder;
use FML\Script\Script;
use FML\Script\ScriptLabel;

/**
 * Script Feature for toggling the complete ManiaLink via Key Press
 *
 * @author    steeffeen <mail@steeffeen.com>
 * @copyright FancyManiaLinks Copyright © 2017 Steffen Schröder
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ToggleInterface extends ScriptFeature
{

    /*
     * Constants
     */
    const VAR_ISVISIBLE = "FML_ToggleInterface_IsVisible";
    const VAR_WASVISIBLE = "FML_ToggleInterface_WasVisible";

    /**
     * @var string $keyName Key name
     */
    protected $keyName = null;

    /**
     * @var int $keyCode Key code
     */
    protected $keyCode = null;

    /**
     * Construct a new ToggleInterface
     *
     * @api
     * @param string|int $keyNameOrCode (optional) Key name or code
     */
    public function __construct($keyNameOrCode = null)
    {
        if (is_string($keyNameOrCode)) {
            $this->setKeyName($keyNameOrCode);
        } else if (is_int($keyNameOrCode)) {
            $this->setKeyCode($keyNameOrCode);
        }
    }

    /**
     * Get the key name
     *
     * @api
     * @return string
     */
    public function getKeyName()
    {
        return $this->keyName;
    }

    /**
     * Set the key name
     *
     * @api
     * @param string $keyName Key name
     * @return static
     */
    public function setKeyName($keyName)
    {
        $this->keyName = (string)$keyName;
        $this->keyCode = null;
        return $this;
    }

    /**
     * Get the key code
     *
     * @api
     * @return int
     */
    public function getKeyCode()
    {
        return $this->keyCode;
    }

    /**
     * Set the key code
     *
     * @api
     * @param int $keyCode Key code
     * @return static
     */
    public function setKeyCode($keyCode)
    {
        $this->keyCode = (int)$keyCode;
        $this->keyName = null;
        return $this;
    }

    /**
     * @see ScriptFeature::prepare()
     */
    public function prepare(Script $script)
    {
        $script->appendGenericScriptLabel(ScriptLabel::ONINIT, $this->getOnInitScriptText());
        if ($this->keyCode != null || $this->keyName != null) {
            $script->appendGenericScriptLabel(ScriptLabel::KEYPRESS, $this->getKeyPressScriptText());
        } else {
            $script->appendGenericScriptLabel(ScriptLabel::LOOP, $this->getLoopScriptText());
        }
        return $this;
    }

    /**
     * Get the on init script text
     *
     * @return string
     */
    protected function getOnInitScriptText()
    {
        $VarIsVisible = $this::VAR_ISVISIBLE;
        $VarWasVisible = $this::VAR_WASVISIBLE;
        return "
declare Boolean {$VarIsVisible} for UI = True;
declare Boolean Last_IsVisible = True;
";
    }

    /**
     * Get the key press script text
     *
     * @return string
     */
    protected function getKeyPressScriptText()
    {
        $keyProperty = null;
        $keyValue    = null;
        if ($this->keyName) {
            $keyProperty = "KeyName";
            $keyValue    = Builder::getText($this->keyName);
        } else if ($this->keyCode) {
            $keyProperty = "KeyCode";
            $keyValue    = Builder::getInteger($this->keyCode);
        }
        $VarIsVisible = $this::VAR_ISVISIBLE;
        $VarWasVisible = $this::VAR_WASVISIBLE;
        $scriptText = "
if (Event.{$keyProperty} == {$keyValue}) {
    {$VarIsVisible} = !{$VarIsVisible};
}";
        return $scriptText ;
    }

     /**
     * Get the key press script text
     *
     * @return string
     */
    protected function getLoopScriptText()
    {
        $VarIsVisible = $this::VAR_ISVISIBLE;
        $VarWasVisible = $this::VAR_WASVISIBLE;
        return "
if (Last_IsVisible != {$VarIsVisible}) {
    Last_IsVisible = {$VarIsVisible};
    foreach (Control in Page.MainFrame.Controls) {
        declare Boolean {$VarWasVisible} for Control = False;
        if (Last_IsVisible && {$VarWasVisible}) {
            Control.Visible = True;
            {$VarWasVisible} = False;
        } else if (!Last_IsVisible){
            {$VarWasVisible} = Control.Visible;
            Control.Visible = False;
        }
    }
}
";
    }
}