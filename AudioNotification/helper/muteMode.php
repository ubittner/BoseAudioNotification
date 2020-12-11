<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

trait muteMode
{
    public function StartMuteMode(): void
    {
        $this->SetValue('Notification', false);
        $this->SetMuteModeTimer();
    }

    public function StopMuteMode(): void
    {
        $this->SetValue('Notification', true);
        $this->SetMuteModeTimer();
    }

    ################### Private

    private function SetMuteModeTimer(): void
    {
        $use = $this->ReadPropertyBoolean('UseAutomaticMuteMode');
        //Start
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('MuteModeStartTime');
        }
        $this->SetTimerInterval('StartMuteMode', $milliseconds);
        //End
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('MuteModeEndTime');
        }
        $this->SetTimerInterval('StopMuteMode', $milliseconds);
    }

    private function GetInterval(string $TimerName): int
    {
        $timer = json_decode($this->ReadPropertyString($TimerName));
        $now = time();
        $hour = $timer->hour;
        $minute = $timer->minute;
        $second = $timer->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        return ($timestamp - $now) * 1000;
    }

    private function CheckMuteModeTimer(): void
    {
        if (!$this->ReadPropertyBoolean('UseAutomaticMuteMode')) {
            return;
        }
        $start = $this->GetTimerInterval('StartMuteMode');
        $this->SendDebug(__FUNCTION__, $start . ' Start', 0);
        $stop = $this->GetTimerInterval('StopMuteMode');
        $this->SendDebug(__FUNCTION__, $stop . ' Stop', 0);
        if ($start > $stop) {
            $value = false;
        } else {
            $value = true;
        }
        $this->SetValue('Notification', $value);
    }
}