<?php

/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait playNotification
{
    /**
     * Plays an audio notification.
     *
     * @param string $Text
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function PlayAudioNotification(string $Text): bool
    {
        if (!$this->CheckUsability()) {
            return false;
        }
        $awsPolly = $this->ReadPropertyInteger('AWSPolly');
        if ($awsPolly == 0 || @!IPS_ObjectExists($awsPolly)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, Text-to-Speech (AWS Polly) is invalid!'), 0);
            return false;
        }
        $outputDevice = $this->ReadPropertyInteger('OutputDevice');
        if ($outputDevice == 0 || @!IPS_ObjectExists($outputDevice)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, output device is invalid!'), 0);
            return false;
        }
        if (!$this->GetValue('Notification')) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, notification is disabled!'), 0);
            return false;
        }
        //Providing audio data to the WebHook
        $this->SetBuffer('AudioData', base64_decode(TTSAWSPOLLY_GenerateData($awsPolly, $Text)));
        //Play
        $result = false;
        switch ($this->ReadPropertyInteger('OutputType')) {
            case self::BOSE_SOUNDTOUCH:
                $result = true; # BST_PlayAudioNotification has no result at the moment
                BST_PlayAudioNotification($outputDevice, 'Audio Notification', sprintf('http://%s:3777/hook/BoseAudioNotification/%s/notification.mp3', $this->ReadPropertyString('Host'), $this->InstanceID), $this->ReadPropertyInteger('Volume'));
                break;

            case self::BOSE_SWITCHBOARD:
                $result = BOSESB_PlayDeviceAudioNotification($outputDevice, sprintf('http://%s:3777/hook/BoseAudioNotification/%s/notification.mp3', $this->ReadPropertyString('Host'), $this->InstanceID), $this->ReadPropertyInteger('Volume'));
                break;
        }
        return $result;
    }
}