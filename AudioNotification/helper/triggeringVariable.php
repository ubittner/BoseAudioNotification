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
                    $type = IPS_GetVariable($id)['VariableType'];
                    $trigger = $variable->Trigger;
                    $value = $variable->Value;
                    switch ($trigger) {
                        case 0: #on change (bool, integer, float, string)
                            if ($ValueChanged) {
                                $execute = true;
                            }
                            break;

                        case 1: #on update (bool, integer, float, string)
                            $execute = true;
                            break;

                        case 2: #on limit drop (integer, float)
                            switch ($type) {
                                case 1: #integer
                                    $actualValue = GetValueInteger($id);
                                    $triggerValue = intval($value);
                                    if ($actualValue < $triggerValue) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: #float
                                    $actualValue = GetValueFloat($id);
                                    $triggerValue = floatval(str_replace(',', '.', $value));
                                    if ($actualValue < $triggerValue) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                        case 3: #on limit exceed (integer, float)
                            switch ($type) {
                                case 1: #integer
                                    $actualValue = GetValueInteger($id);
                                    $triggerValue = intval($value);
                                    if ($actualValue > $triggerValue) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: #float
                                    $actualValue = GetValueFloat($id);
                                    $triggerValue = floatval(str_replace(',', '.', $value));
                                    if ($actualValue > $triggerValue) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                        case 4: #on specific value (bool, integer, float, string)
                            switch ($type) {
                                case 0: #bool
                                    $actualValue = GetValueBoolean($id);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    $triggerValue = boolval($value);
                                    if ($actualValue == $triggerValue) {
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

                                case 1: #integer
                                    $actualValue = GetValueInteger($id);
                                    $triggerValue = intval($value);
                                    if ($actualValue == $triggerValue) {
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

                                case 2: #float
                                    $actualValue = GetValueFloat($id);
                                    $triggerValue = floatval(str_replace(',', '.', $value));
                                    if ($actualValue == $triggerValue) {
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

                                case 3: #string
                                    $actualValue = GetValueString($id);
                                    $triggerValue = (string) $value;
                                    if ($actualValue == $triggerValue) {
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