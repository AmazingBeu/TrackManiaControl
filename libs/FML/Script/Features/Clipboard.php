<?php

namespace FML\Script\Features;

use FML\Controls\Control;
use FML\Controls\Label;
use FML\Script\Builder;
use FML\Script\Script;
use FML\Script\ScriptLabel;
use FML\Types\Scriptable;

/**
 * Script Feature for showing Tooltips
 *
 * @author    steeffeen <mail@steeffeen.com>
 * @copyright FancyManiaLinks Copyright © 2017 Steffen Schröder
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class Clipboard extends ScriptFeature
{
    public const DATASET_PROPERTY = 'clipboard-data';


    /**
     * @var Control $control
     */
    protected $control = null;

    /**
     * @var string $value value
     */
    protected $value = null;

    /**
     * @var Control $tooltipControl Tooltip Control
     */
    protected $tooltipControl = null;


    /**
     * Construct a new Tooltip
     *
     * @api
     * @param Control $hoverControl   Control
     * @param string  $value          Value to set in the Clipboard
     * @param Control $tooltipControl (optional) If tooltip is used
     */
    public function __construct(Control $control, mixed $value, ?Control $tooltipControl = null)
    {
        $this->setControl($control);
        $this->setValue($value);

        if ($tooltipControl) {
            $this->setTooltipControl($tooltipControl);
        }
    }

    /**
     * Set the Control
     *
     * @api
     * @param Control $control Control
     * @return static
     */
    public function setControl(Control $control)
    {
        $control->checkId();

        if ($this->control !== null) {
            $this->control->removeDataAttribute(self::DATASET_PROPERTY);
        }

        $this->control = $control;
        if ($this->control instanceof Scriptable) {
            $this->control->setScriptEvents(true);
        }

        if ($this->value !== null) {
            $this->setValue($this->value);
        }

        return $this;
    }

    /**
     * Set the value to copy
     *
     * @api
     * @param mixed $value value
     * @return static
     */
    public function setValue(mixed $value)
    {
        $this->value = (string) $value;
        $this->control->addDataAttribute(self::DATASET_PROPERTY, $this->value);

        return $this;
    }

    /**
     * Set the Tooltip Control
     *
     * @api
     * @param Control $tooltipControl Tooltip Control
     * @return static
     */
    public function setTooltipControl(Control $tooltipControl)
    {
        $tooltipControl->checkId();
        $this->tooltipControl = $tooltipControl;
        $tooltip = new Tooltip($this->control, $tooltipControl, false, false, "Click to copy");
        $this->control->addScriptFeature($tooltip);

        return $this;
    }

    /**
     * @see ScriptFeature::prepare()
     */
    public function prepare(Script $script)
    {
        $controlId = Builder::escapeText($this->control->getId());
        $datasetProperty = Builder::escapeText(self::DATASET_PROPERTY);

        $scriptText = "
if (Event.Control.ControlId == {$controlId}) {
    log(\"clipboard \"^ Event.Control.DataAttributeExists({$datasetProperty}));
    if (System != Null && Event.Control.DataAttributeExists({$datasetProperty})) {
        System.ClipboardSet(Event.Control.DataAttributeGet({$datasetProperty}));
    }
}";
        $script->appendGenericScriptLabel(ScriptLabel::MOUSECLICK, $scriptText);

        return $this;
    }

}
