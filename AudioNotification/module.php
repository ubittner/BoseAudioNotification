<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class BoseAudioNotification extends IPSModule
{
    //Helper
    use muteMode;
    use playNotification;
    use triggeringVariable;
    use webHook;

    //Constants
    const CORE_WEBHOOK_GUID = '{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}';
    const GUID_AWS_POLLY = '{6EFA02E1-360F-4120-B3DE-31EFCDAF0BAF}';
    const BOSE_SOUNDTOUCH = 0;
    const GUID_BOSE_SOUNDTOUCH_DEVICE = '{4836EF46-FF79-4D6A-91C9-FE54F1BDF2DB}';
    const BOSE_SWITCHBOARD = 1;
    const GUID_BOSE_SWITCHBOARD_DEVICE = '{3A8BE899-3400-6755-BCAB-375C47D9451E}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->RegisterVariables();
        $this->RegisterTimers();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
        $this->UnregisterHook('/hook/BoseAudioNotification/' . $this->InstanceID);
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        //Never delete this line!
        parent::ApplyChanges();
        //Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (!$this->ValidateConfiguration()) {
            return;
        }
        $this->RegisterHook('/hook/BoseAudioNotification/' . $this->InstanceID);
        $this->RegisterMessages();
        $this->SetMuteModeTimer();
        $this->CheckMuteModeTimer();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:
                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value
                $valueChanged = 'false';
                if ($Data[1]) {
                    $valueChanged = 'true';
                }
                //$this->CheckTriggeringVariable($SenderID, $Data[1]);
                $scriptText = 'BOSEAN_CheckTriggeringVariable(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                $this->SendDebug(__FUNCTION__, $scriptText, 0);
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Host
        $options = [];
        $networkInfo = Sys_GetNetworkInfo();
        for ($i = 0; $i < count($networkInfo); $i++) {
            $options[] = [
                'caption' => $networkInfo[$i]['IP'],
                'value'   => $networkInfo[$i]['IP']
            ];
        }
        $formData['elements'][4] = [
            'type'    => 'Select',
            'name'    => 'Host',
            'caption' => 'IP-Address (Symcon)',
            'options' => $options
        ];
        //AWS Polly
        $formData['elements'][5] = [
            'type'     => 'SelectModule',
            'name'     => 'AWSPolly',
            'caption'  => 'Text-to-Speech (AWS Polly)',
            'moduleID' => self::GUID_AWS_POLLY
        ];
        //Output type
        $options = [];
        $options[] = [
            'value'   => self::BOSE_SOUNDTOUCH,
            'caption' => 'Bose SoundTouch'
        ];
        $options[] = [
            'value'   => self::BOSE_SWITCHBOARD,
            'caption' => 'Bose Switchboard'
        ];
        $formData['elements'][6] = [
            'type'     => 'Select',
            'name'     => 'OutputType',
            'caption'  => 'Output Type',
            'options'  => $options,
            'onChange' => 'BOSEAN_UpdateOutputType($id, $OutputType);'

        ];
        //Output device
        $formData['elements'][7] = [
            'type'     => 'SelectModule',
            'name'     => 'OutputDevice',
            'caption'  => 'Output Device',
            'moduleID' => $this->ReadPropertyInteger('OutputType') === self::BOSE_SOUNDTOUCH ? self::GUID_BOSE_SOUNDTOUCH_DEVICE : self::GUID_BOSE_SWITCHBOARD_DEVICE
        ];
        //Volume
        $formData['elements'][8] = [
            'type'     => 'HorizontalSlider',
            'name'     => 'Volume',
            'caption'  => $this->Translate('Volume') . ' ' . $this->ReadPropertyInteger('Volume') . '%',
            'minimum'  => 0,
            'maximum'  => 100,
            'onChange' => 'BOSEAN_UpdateVolumeSlider($id, $Volume);'
        ];
        return json_encode($formData);
    }

    public function UpdateOutputType(int $OutputType): void
    {
        switch ($OutputType) {
            case self::BOSE_SOUNDTOUCH:
                $this->UpdateFormField('OutputDevice', 'moduleID', self::GUID_BOSE_SOUNDTOUCH_DEVICE);
                $this->UpdateFormField('OutputDevice', 'value', 0);
                break;

            case self::BOSE_SWITCHBOARD:
                $this->UpdateFormField('OutputDevice', 'moduleID', self::GUID_BOSE_SWITCHBOARD_DEVICE);
                $this->UpdateFormField('OutputDevice', 'value', 0);
                break;

        }
    }

    public function UpdateVolumeSlider(int $Volume): void
    {
        $this->UpdateFormField('Volume', 'caption', $this->Translate('Volume') . ' ' . $Volume . '%');
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        $this->SetValue($Ident, $Value);
        switch ($Ident) {
            case 'TTSText':
                $this->PlayAudioNotification($Value);
                break;
        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyBoolean('Usability', false);
        $this->RegisterPropertyString('Host', (count(Sys_GetNetworkInfo()) > 0) ? Sys_GetNetworkInfo()[0]['IP'] : '');
        $this->RegisterPropertyInteger('AWSPolly', 0);
        $this->RegisterPropertyInteger('OutputType', self::BOSE_SOUNDTOUCH);
        $this->RegisterPropertyInteger('OutputDevice', 0);
        $this->RegisterPropertyInteger('Volume', 25);
        $this->RegisterPropertyString('TriggeringVariables', '[]');
        $this->RegisterPropertyBoolean('UseAutomaticMuteMode', false);
        $this->RegisterPropertyString('MuteModeStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('MuteModeEndTime', '{"hour":6,"minute":0,"second":0}');
    }

    private function RegisterVariables(): void
    {
        //Notification
        $id = @$this->GetIDForIdent('Notification');
        $this->RegisterVariableBoolean('Notification', $this->Translate('Notification'), 'Switch', 10);
        $this->EnableAction('Notification');
        if ($id === false) {
            IPS_SetIcon($this->GetIDForIdent('Notification'), 'Speaker');
        }
        //TTSText
        $id = @$this->GetIDForIdent('TTSText');
        $this->RegisterVariableString('TTSText', 'Text', '', 20);
        $this->EnableAction('TTSText');
        if ($id === false) {
            IPS_SetIcon($this->GetIDForIdent('TTSText'), 'Edit');
        }
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('StartMuteMode', 0, 'BOSEAN_StartMuteMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopMuteMode', 0, 'BOSEAN_StopMuteMode(' . $this->InstanceID . ',);');
    }

    private function RegisterMessages(): void
    {
        //Unregister
        $messages = $this->GetMessageList();
        if (!empty($messages)) {
            foreach ($messages as $id => $message) {
                foreach ($message as $messageType) {
                    if ($messageType == VM_UPDATE) {
                        $this->UnregisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
        //Register
        $variables = json_decode($this->ReadPropertyString('TriggeringVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->TriggeringVariable != 0 && @IPS_ObjectExists($variable->TriggeringVariable)) {
                        $this->RegisterMessage($variable->TriggeringVariable, VM_UPDATE);
                    }
                }
            }
        }
    }

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        $use = $this->CheckUsability();
        if (!$use || !$this->CheckAWSPolly() || !$this->CheckOutputDevice()) {
            $result = false;
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, !$use);
        $this->SetStatus($status);
        return $result;
    }

    private function CheckUsability(): bool
    {
        if (!$this->ReadPropertyBoolean('Usability')) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, the instance is inactive!'), 0);
            $this->LogMessage($this->Translate('Abort, the instance ') . $this->InstanceID . $this->Translate(' is inactive!'), KL_WARNING);
            return false;
        }
        return true;
    }

    private function CheckAWSPolly(): bool
    {
        $id = $this->ReadPropertyInteger('AWSPolly');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, Text-to-Speech (AWS Polly) is invalid!'), 0);
            return false;
        }
        return true;
    }

    private function CheckOutputDevice(): bool
    {
        $id = $this->ReadPropertyInteger('OutputDevice');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, output device is invalid!'), 0);
            return false;
        }
        return true;
    }
}