<?php
class MELCloudControl extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');

        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('TokenExpiry', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function Sync()
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }
    }

    private function HasValidToken() {
        if ($this->ReadPropertyString('Token') == '') {
            IPS_LogMessage("SymconMELCloud", "No token present");
            return false;
        }

        $tokenExpiryString = $this->ReadPropertyString('TokenExpiry');

        if ($tokenExpiryString == '') {
            IPS_LogMessage("SymconMELCloud", "Token expiry is unknown");
            return false;
        }

        $tokenExpiry = strtotime($tokenExpiryString);
        if($tokenExpiry == false) {
            IPS_LogMessage("SymconMELCloud", "Token expiry is not a valid date");
            return false;
        }

        IPS_LogMessage("SymconMELCloud", "Token: $tokenExpiry");
        if($tokenExpiry <= strtotime('-1 hour')) {
            IPS_LogMessage("SymconMELCloud", "Token is expired or will in the next hour");
            return false;
        }

        IPS_LogMessage("SymconMELCloud", "Valid token was found");
        return true;
    }

    private function CreateToken()
    {
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Login/ClientLogin";

        $params = array();
        $params['Email'] = $this->ReadPropertyString('Email');
        $params['password'] = $this->ReadPropertyString('Password');
        $params['AppVersion'] = "1.7.1.0";

        $headers = array();
        $headers[] = "Accept: application/json";

        IPS_LogMessage("SymconMELCloud", "Requesting a new token from '$url'");
        $result = $this->Request($url, 'POST', $params, $headers);

        if (isset($result["LoginData"]) && isset($result["LoginData"]["ContextKey"])) {
            IPS_SetProperty($this->InstanceID, 'Token', $result["LoginData"]["ContextKey"]);
            IPS_SetProperty($this->InstanceID, 'TokenExpiry', $result["LoginData"]["Expiry"]);
            IPS_ApplyChanges($this->InstanceID);
        }

        if($this->HasValidToken()) {
            IPS_LogMessage("SymconMELCloud", "Successfully acquired a new token from '$url'");
            $this->SetStatus(102);
        } else {
            IPS_LogMessage("SymconMELCloud", "Failed to acquire a new token from '$url'");
            $this->SetStatus(201);
        }
    }

    public function Request($url, $method, $params = array(), $headers = array())
    {
        $client = curl_init($url);
        curl_setopt($client, CURLOPT_CUSTOMREQUEST, $method);
//        curl_setopt($client, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($client, CURLOPT_USERAGENT, 'SymconBotvac');
//        curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 5);
//        curl_setopt($client, CURLOPT_TIMEOUT, 5);

        if ($method == 'POST') {
            curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($client, CURLOPT_HTTPHEADER, $headers);


        $result = curl_exec($client);
        $status = curl_getinfo($client, CURLINFO_HTTP_CODE);

        curl_close($client);

        if ($status == '0') {
            $this->SetStatus(201);
            return false;
        } elseif ($status != '200' && $status != '201') {
            IPS_LogMessage("SymconMELCloud", "Response invalid. Code $status");
            $this->SetStatus(201);
            return false;
        } else {
            IPS_LogMessage("SymconMELCloud", "Response: '$result'");
            return json_decode($result, true);
        }
    }
}
