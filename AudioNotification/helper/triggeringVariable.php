<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

trait triggeringVariable
{
    public function CheckTriggeringVariable(int $VariableID, bool $ValueChanged): void
    {
        $this->SendDebug(__FUNCTION__, 'Variable: ' . $VariableID, 0);
        $this->SendDebug(__FUNCTION__, 'Value changed: ' . json_encode($ValueChanged), 0);
        if (!$this->CheckUsability()) {
            return;
        }
        if (!$this->CheckAWSPolly()) {
            return;
        }
        if (!$this->CheckOutputDevice()) {
            return;
        }
        $triggeringVariables = json_decode($this->ReadPropertyString('TriggeringVariables'));
        if (empty($triggeringVariables)) {
            return;
        }
        foreach ($triggeringVariables as $variable) {
            $id = $variable->TriggeringVariable;
            if ($VariableID == $id) {
                if ($variable->Use) {
                    $this->SendDebug(__FUNCTION__, 'Variable ' . $VariableID . ' is active', 0);
                    $execute = false;
                    $trigger = $variable->Trigger;
                    switch ($trigger) {
                        case 0: #on change
                            if ($ValueChanged) {
                                $execute = true;
                            }
                            break;
                        case 1: #on update
                            $execute = true;
                            break;

                        case 2: # on specific value
                            $actualValue = intval(GetValue($id));
                            $value = $variable->Value;
                            if ($actualValue == $value - 1) {
                                $condition = $variable->Condition;
                                switch ($condition) {
                                    case 1: #trigger once
                                        if ($ValueChanged) {
                                            $execute = true;
                                        }
                                        break;

                                    case 2: #trigger every time
                                        $execute = true;
                                }
                            }
                            break;
                    }
                    if ($execute) {
                        $this->PlayAudioNotification($variable->Notification);
                    }
                }
            }
        }
    }
}