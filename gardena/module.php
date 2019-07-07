<?
    include("gardena-api-class.ips.php");
    
    class gardena extends IPSModule {

        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) {
            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
			// Diese Zeile nicht löschen.
			parent::Create();
			$this->RegisterPropertyString("Username", "Mail-Adresse bei Gardena"); 
			$this->RegisterPropertyString("Password", "Password"); 
			$this->RegisterPropertyInteger("Interval",5);

				//Variablenprofil anlegen ($name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon)
			$profilename = "GAR.Befehle";
			if (!IPS_VariableProfileExists($profilename)) {
				IPS_CreateVariableProfile($profilename, 1);
				IPS_SetVariableProfileIcon($profilename, "Flower");
				IPS_SetVariableProfileAssociation($profilename, 1, "bis auf weiteres parken", "", 0xFFFF00);
				IPS_SetVariableProfileAssociation($profilename, 2, "parken bis zum nächsten Timer", "", 0xFFFF00);
				IPS_SetVariableProfileAssociation($profilename, 3, "Start/Wiederaufname Timer", "", 0xFFFF00);
				IPS_SetVariableProfileAssociation($profilename, 4, "Start für 24 Stunden", "", 0xFFFF00);
				IPS_SetVariableProfileAssociation($profilename, 5, "Start für 3 Tage", "", 0xFFFF00);
				IPS_SetVariableProfileAssociation($profilename, 6, "Start für 2 Stunden", "", 0xFFFF00);
			}

			$profilename = "GAR.Ladestatus";
			if (IPS_VariableProfileExists($profilename)) IPS_DeleteVariableProfile ($profilename );

			if (!IPS_VariableProfileExists($profilename)) {
				IPS_CreateVariableProfile($profilename, 0);
				IPS_SetVariableProfileIcon($profilename, "Power");
				IPS_SetVariableProfileAssociation($profilename, true, "Lädt", "", 0xEA1A07);
				IPS_SetVariableProfileAssociation($profilename, false, "Lädt nicht", "", 0x62F442);            
			}
			$proberty_name = "action";
			$varID = @$this->GetIDForIdent($proberty_name);
			if (!IPS_VariableExists($varID)) {
				$varID = $this->RegisterVariableInteger($proberty_name,"Aktion","GAR.Befehle",0);
				$this->EnableAction($proberty_name);
			}
			$interv = $this->ReadPropertyInteger("Interval")*60000;
			$this->RegisterTimer("Update", $interv, 'GAR_StatusUpdate();');

		}

		public function RequestAction($Ident, $Value) {
			$username = $this->ReadPropertyString("Username");
			$password = $this->ReadPropertyString("Password");
			$gardena = new gardenapi($username, $password);
			$mower = $gardena -> getDevice($gardena::CATEGORY_MOWER);

			switch($Value) {
				case "1":
					 $gardena -> sendCommand($mower, $gardena -> CMD_MOWER_PARK_UNTIL_FURTHER_NOTICE);
					break;
				case "2":
					 $gardena -> sendCommand($mower, $gardena -> CMD_MOWER_PARK_UNTIL_NEXT_TIMER);
					break;
				case "3":
					 $gardena -> sendCommand($mower, $gardena -> CMD_MOWER_START_RESUME_SCHEDULE);
					break;
				case "4":
					  $gardena -> sendCommand($mower, $gardena -> CMD_MOWER_START_24HOURS);
					break;
				case "5":
					 $gardena -> sendCommand($mower, $gardena -> CMD_MOWER_START_3DAYS);
					break;
				default:
					throw new Exception("Invalid action");
			}
		}
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
			// Diese Zeile nicht löschen
			parent::ApplyChanges();
			//Instanz ist aktiv
			$this->SetStatus(102);

			if ($this->ReadPropertyString("Password") !== "Password") {
				$this->StatusUpdate();
				$this->SetTimerInterval("Update", $this->ReadPropertyInteger("Interval")*60000);
			}
        }
		
		public function getWert($gardena, $mower, $category_name, $proberty_name , $property, $typ, $check) {
			if ( $this->ReadPropertyBoolean($property) ) {
				$status = $gardena -> getInfo($mower, $category_name, $proberty_name);

				$varID = @$this->GetIDForIdent($proberty_name);
				if (!IPS_VariableExists($varID)) {
					if ($typ == "String" or $typ == "Date")
						$varID = $this->RegisterVariableString($proberty_name,substr($property,0,-2));
					if ($typ == "Integer") 
						$varID = $this->RegisterVariableInteger($proberty_name,substr($property,0,-2));
					if ($typ == "Boolean") {
						if ($proberty_name == "charging")
							$varID = $this->RegisterVariableBoolean($proberty_name,substr($property,0,-2), "GAR.Ladestatus");
						else
							$varID = $this->RegisterVariableBoolean($proberty_name,substr($property,0,-2));
					}
				}
				if ($typ == "Date") {
					$datum = new DateTime($status);
					$datum->setTimezone(new DateTimeZone(date_default_timezone_get()));
					$datum_f = $datum->format('d/m/Y H:i:s');
					$status = $datum_f;
				}
				SetValue($varID, $status);
			}
		}

       		public function CheckUsernameUndPassword() {
			$username = $this->ReadPropertyString("Username");
			$password = $this->ReadPropertyString("Password");	
				
			$data = [ "sessions" => ["email" => "$username", "password" => "$password"] ];
			$data_string = json_encode($data);
			$ch = curl_init(self::LOGINURL);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Content-Length: ' . strlen($data_string)] );
        		$result = curl_exec($ch);
			echo ($result);	   
		}

		public function StatusUpdate() {
			$username = $this->ReadPropertyString("Username");
			$password = $this->ReadPropertyString("Password");
			//echo ($username);
			//echo ($password);
			$gardena = new gardenapi($username, $password );
			$mower = $gardena -> getDevice($gardena::CATEGORY_MOWER);

			/// HIER Device Infos
			$this->getWert($gardena, $mower,"device_info", "manufacturer","Geraet_Hersteller_B", "String", true );
			$this->getWert($gardena, $mower,"device_info", "product","Geraet_Produktname_B", "String", true );
			$this->getWert($gardena, $mower,"device_info", "serial_number","Geraet_Serien_Nummer_B", "String" , true);
			$this->getWert($gardena, $mower,"device_info", "version","Geraet_Version_B", "String", true );
			$this->getWert($gardena, $mower,"device_info", "sgtin","Geraet_sgtin_B", "String", true );
			$this->getWert($gardena, $mower,"device_info", "last_time_online","Geraet_letzte_Onlinezeit_B", "Date" , true);
			$this->getWert($gardena, $mower,"device_info", "category","Geraet_Kategorie_B", "String", true );
			$this->getWert($gardena, $mower,"internal_temperature", "temperature","Geraet_Interne_Temperatur_B", "Integer", true );
			$this->getWert($gardena, $mower,"battery", "level","Batterie_Level_B", "Integer", true );
			$this->getWert($gardena, $mower,"battery", "rechargable_battery_status","Batterie_Status_B", "String", true );
			$this->getWert($gardena, $mower,"battery", "charging","Batterie_Ladestatus_B", "Boolean", true );
			$this->getWert($gardena, $mower,"radio", "quality","Funk_Staerke_B", "Integer", true );
			$this->getWert($gardena, $mower,"radio", "state","Funk_Qualitaet_B", "String" , true);
			$this->getWert($gardena, $mower,"radio", "connection_status","Funk_Status_B", "String" , true);
			$this->getWert($gardena, $mower,"mower", "manual_operation","Status_manuelle_Operation_B", "String" , true);
			$this->getWert($gardena, $mower,"mower", "timestamp_next_start","Status_Uhrzeit_naechster_Start_B", "Date" , true);
			$this->getWert($gardena, $mower,"mower", "override_end_time","Status_Ueberschriebene_Endzeit_B", "Date", true );
			$this->getWert($gardena, $mower,"mower", "status","Status_aktuelle_Aktion_B", "String", true );
			$this->getWert($gardena, $mower,"mower", "source_for_next_start","Status_Grund_B", "String" , true);			

        }

				
		public function AktionAusfuehren($action) {
	    		$username = $this->ReadPropertyString("Username");
			$password = $this->ReadPropertyString("Password");
			$gardena = new gardena($username, $password);
    			$mower = $gardena -> getDevice($gardena::CATEGORY_MOWER);

        	{
            	$switch = $action;
            	switch ($switch)
            	{
					// Parken ----------------------------------------------------------
					case 1:
						$gardena -> sendCommand($mower, $gardena -> CMD_MOWER_PARK_UNTIL_FURTHER_NOTICE);
						break;
					case 2:
						$gardena -> sendCommand($mower, $gardena -> CMD_MOWER_PARK_UNTIL_NEXT_TIMER);
						break;
					// Mähen -----------------------------------------------------------
					case 3:
						$gardena -> sendCommand($mower, $gardena -> CMD_MOWER_START_RESUME_SCHEDULE);
						break;
					case 4:
						$gardena -> sendCommand($mower, $gardena -> CMD_MOWER_START_24HOURS);
						break;
					case 5:
						$gardena -> sendCommand($mower, $gardena -> CMD_MOWER_START_3DAYS);
						break;
					case 6:
						$gardena -> sendCommand($mower, $gardena -> CMD_MOWER_START_2HOURS);
						break;			    
            	}
        
			}		
		}
		
    }
?>
