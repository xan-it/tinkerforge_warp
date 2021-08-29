<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';

class warp1 extends IPSModule
{
    use VariableProfileHelper;

    private $kwh_start = 0.0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        $this->RegisterProfileIntegerEx('Warp.State', 'Car', '', '', [
            [0, $this->Translate('disconnected'),  '', 0xadadad],
            [1, $this->Translate('connected'),  '', 0xe1ffbf],
            [2, $this->Translate('charging'),  '', 0xbfe9ff],
            [3, $this->Translate('error'),  '', 0xffa7a1]
        ]);
        $this->RegisterProfileIntegerEx('Warp.Error', 'Information', '', '', [
            [0, $this->Translate('ok'),  '', 0xe1ffbf],
            [1, $this->Translate('switch error'),  '', 0xffa7a1],
            [2, $this->Translate('calibration error'),  '', 0xffa7a1],
            [3, $this->Translate('contactor error'),  '', 0xffa7a1],
            [4, $this->Translate('communication error'),  '', 0xffa7a1]
        ]);

        $this->RegisterPropertyString('MQTTTopic', 'warp/USF');
        $this->RegisterPropertyString('Type', 'warp1');
        
        $this->RegisterVariableInteger('Warp_ConnectState', $this->Translate('Vehicle state'), 'Warp.State');
 
        $this->RegisterVariableBoolean('Charge_Control', $this->Translate('Charge control'), '~Switch');   
        $this->EnableAction('Charge_Control');
    }

    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        // filter ReceiveData
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');

        if (($this->ReadPropertyString('Type') == 'warp1') || ($this->ReadPropertyString('Type') == 'warp2')) {
            $this->RegisterVariableFloat('Charging_Current', $this->Translate('Charging current'), '~Ampere');
            $this->RegisterVariableFloat('Power_Consumption', $this->Translate('Power consumption'), '~Power');
            $this->RegisterVariableInteger('Error_Condition', $this->Translate('Error condition'), 'Warp.Error');
            $this->RegisterVariableString('Charging_Time', $this->Translate('Charging time'), '');
            $this->RegisterVariableFloat('Charging_Consumption', $this->Translate('Charging consumption'), '~Electricity');
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Charging_Control':
                $this->SwitchMode($Value);
                break;
        }
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $buffer = json_decode($JSONString);
            switch ($buffer->DataID) {
                case '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}': // MQTT Server
                    $data = $buffer;
                    break;
                case '{DBDA9DF7-5D04-F49D-370A-2B9153D00D9B}': //MQTT Client
                    $data = json_decode($buffer->Buffer);
                    break;
                default:
                    $this->LogMessage('Invalid Parent', KL_ERROR);
                    return;
            }
            if (property_exists($data, 'Topic')) {
                $this->SendDebug('MQTT Topic', $data->Topic, 0);
                if (fnmatch('*/evse/state', $data->Topic)) {
                    // evse/state
                    $this->SendDebug('State Payload', $data->Payload, 0);
                    $payload = json_decode($data->Payload);
                    if ($payload->vehicle_state == 2) {
                        $this->SetValue('Charging_Time', $this->SecondsToDHMS(floor($payload->time_since_state_change / 1000))); // ms > s > Unix Timestamp String
                        if ($this->GetValue('Warp_ConnectState') != 2) {
                            $this->kwh_start = 0.0; // mark start point with zero
                            $this->SetValue('Charging_Control', 1);
                        }
                    } else {
                        if ($this->GetValue('Charging_Control') == 1) {
                            $this->SetValue('Charging_Control', 0);
                        }
                    }
                    $this->SetValue('Warp_ConnectState', $payload->vehicle_state);
                    $this->SetValue('Charging_Current', $payload->allowed_charging_current / 1000); // mA > A
                    $this->SetValue('Error_Condition', $payload->error_state);
                }
                if (fnmatch('*/meter/state', $data->Topic)) {
                    // meter/state
                    $this->SendDebug('Meter Payload', $data->Payload, 0);
                    $payload = json_decode($data->Payload);
                    $this->SetValue('Power_Consumption', $payload->power / 1000);
                    if ($this->kwh_start == 0.0) {
                        $this->kwh_start = $payload->energy_abs;
                    }
                    $this->SetValue('Power_Consumption', $payload->power / 1000);
                    $this->SetValue('Charging_Consumption', $payload->energy_abs - $this->kwh_start);
                }
            }
        }
    }

    private function SecondsToDHMS($seconds)
    {
        $days = floor($seconds/86400);
        $hrs = intval(floor($seconds / 3600) % 24);
        $mins = intval(($seconds / 60) % 60); 
        $sec = intval($seconds % 60);
        $return_days = ($days>0 ? $days." ".$this->Translate('days')." " : "");
        $hrs = str_pad(strval($hrs),2,'0',STR_PAD_LEFT);
        $mins = str_pad(strval($mins),2,'0',STR_PAD_LEFT);
        $sec = str_pad(strval($sec),2,'0',STR_PAD_LEFT);
        return $return_days.$hrs.":".$mins.":".$sec;
    }

    private function SwitchMode(bool $Value)
    {
        $Topic = $this->ReadPropertyString('MQTTTopic') . '/evse/';
        if ($Value) {
            $Topic = $Topic.'start_charging';
        } else {
            $Topic = $Topic.'stop_charging';
        }
        echo "sendMQTT:".$Topic."\n";
    //    $this->sendMQTT($Topic, '');
    }

}
