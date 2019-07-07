<?php
/*
* Ref. http://www.dxsdata.com/2016/07/php-class-for-gardena-smart-system-api/
* Ref. http://www.roboter-forum.com/showthread.php?16777-Gardena-Smart-System-Analyse
* Angepasst 03.07.2016 / WiBo
* angepasst 07.07.2019 complete Gardena API integration (watering, gateway, mower)
*/

class gardenapi
{
    var $user_id, $token, $locations;
    var $devices = [];

    const LOGINURL 	= "https://smart.gardena.com/sg-1/sessions";
    const LOCATIONSURL  = "https://smart.gardena.com/sg-1/locations/?user_id=";
    const DEVICESURL    = "https://smart.gardena.com/sg-1/devices?locationId=";
    const CMDURL        = "https://smart.gardena.com/sg-1/devices/|DEVICEID|/abilities/mower/command?locationId=";

    var $CMD_MOWER_PARK_UNTIL_NEXT_TIMER        = ["name" => "park_until_next_timer"];
    var $CMD_MOWER_PARK_UNTIL_FURTHER_NOTICE    = ["name" => "park_until_further_notice"];
    var $CMD_MOWER_START_RESUME_SCHEDULE        = ["name" => "start_resume_schedule"];
    var $CMD_MOWER_START_24HOURS                = ["name" => "start_override_timer", "parameters" => ["duration" => 1440] ];
    var $CMD_MOWER_START_3DAYS                  = ["name" => "start_override_timer", "parameters" => ["duration" => 4320] ];
    var $CMD_MOWER_START_2HOURS                 = ["name" => "start_override_timer", "parameters" => ["duration" => 2] ];

    var $CMD_SENSOR_REFRESH_TEMPERATURE = ["name" => "measure_ambient_temperature"];
    var $CMD_SENSOR_REFRESH_LIGHT = ["name" => "measure_light"];
    var $CMD_SENSOR_REFRESH_HUMIDITY = ["name" => "measure_humidity"];    
    
    var $CMD_WATERINGCOMPUTER_START_30MIN = ["name" => "manual_override", "parameters" => ["duration" => 30]];
    var $CMD_WATERINGCOMPUTER_STOP = ["name" => "cancel_override"];

    const CATEGORY_MOWER = "mower";
    const CATEGORY_GATEWAY = "gateway";
    const CATEGORY_SENSOR = "sensor";
    const CATEGORY_WATERINGCOMPUTER = "watering_computer";

    const PROPERTY_STATUS = "status";
    const PROPERTY_BATTERYLEVEL = "level";
    const PROPERTY_TEMPERATURE = "temperature";
    const PROPERTY_SOIL_HUMIDITY = "humidity";
    const PROPERTY_LIGHTLEVEL = "light";
    const PROPERTY_VALVE_OPEN = "valve_open";
    
    const ABILITY_CONNECTIONSTATE = "radio";
    const ABILITY_BATTERY = "battery";
    const ABILITY_TEMPERATURE = "ambient_temperature";
    const ABILITY_SOIL_HUMIDITY = "humidity";
    const ABILITY_LIGHT = "light";
    const ABILITY_OUTLET = "outlet";
	
    function __construct($user, $pw) {
        
        $data = ["sessions" => ["email" => "$user", "password" => "$pw"] ];                     
                                                               
        $data_string = json_encode($data);                                                                                   
                                                                                                                             
        $ch = curl_init(self::LOGINURL);                                                                      
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Content-Length: '.strlen($data_string) ] );   
            
        $result = curl_exec($ch);
        $data = json_decode($result);
 
        $this->token   = $data->sessions->token;
        $this->user_id = $data->sessions->user_id;
        
        $this->loadLocations();
        $this->loadDevices();        
    }

    function loadLocations() {
        $url = self::LOCATIONSURL.$this->user_id;
                                                                                                                             
        $ch = curl_init($url);                                                                      
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");                                                                                                                                     
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type:application/json','X-Session:'.$this->token] );   
            
        $this->locations = json_decode(curl_exec($ch))->locations;  
                                                                       
    }
    
    function loadDevices() {

        foreach($this->locations as $location)
        {
            $url = self::DEVICESURL.$location->id;
                                                                                                                                 
            $ch = curl_init($url);                                                                      
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");                                                                                                                                     
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json','X-Session:'.$this->token] );   
                
            $this->devices[$location->id] = json_decode(curl_exec($ch))->devices;
        }
    }

    function getAllDevices() {
        $alldevices = [];
        foreach($this->devices as $locationId => $devices) {        
            foreach($devices as $device) $alldevices[] = $device;
        }
        return $alldevices;
    }

    function getDevice($category) {
        $devices = reset($this->devices);
        foreach($devices as $device) {
            if ($category == $device->category) return $device;
        }
        return false;
    }

    function sendCommand($device, $command){

        $location = $this->getDeviceLocation($device);       
        $url = str_replace("|DEVICEID|", $device->id, self::CMDURL) . $location->id;                             
        $data_string = json_encode($command);       
       
        $ch = curl_init($url);                                                                      
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type:application/json', 'X-Session:'.($this -> token), 'Content-Length: '.strlen($data_string) ]);  
 
        $result =  curl_exec($ch);        
        
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == "204") return true;
            
        return json_encode($result);
    }       

    function getAbilityData($device, $abilityName){
        foreach($device->abilities as $ability)
            if ($ability->name == $abilityName)
                return $ability;
    }
    
    function getPropertyData($device, $abilityName, $propertyName){
        $ability = $this->getAbilityData($device, $abilityName);   
        foreach($ability->properties as $property)
            if ($property->name == $propertyName)
                return $property;
    }

    function getInfo($device, $category_name, $proberty_name)
    {
        foreach ($device->abilities as $ability)
            if ($ability->name == $category_name)
                foreach($ability->properties as $property)
                    if ($property->name == $proberty_name)
                        return $property->value;
    }
    
    # Gateway Functions
	
	
    # Mower Functions
    function getMowerState($device){
        return $this->getPropertyData($device, $this::CATEGORY_MOWER, $this::PROPERTY_STATUS) -> value;
    }
	
    # Watering Functions

	
    # Sensor Functions
	

}

?>
