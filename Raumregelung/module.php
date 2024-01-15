<?
// Klassendefinition
class Raumregelung extends IPSModule {
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();

        $istemperature      = $this->RegisterVariableFloat("IsTemperature", $this->Translate("Is temperature"),"",0);
        $shouldtemperature  = $this->RegisterVariableFloat("ShouldTemperature", $this->Translate("Should temperature"),"",1);
        $reduction          = $this->RegisterVariableFloat("Reduction", $this->Translate("Reduction"),"",2);
        $heatingphase       = $this->RegisterVariableInteger("HeatingPhase", $this->Translate("Heatingphase"),"",3); 

        $this->RegisterPropertyInteger("Aktor-ID", 0);
        $this->RegisterPropertyString("Schaltbefehl-An", "0");
        $this->RegisterPropertyString("Schaltbefehl-Aus", "0");
        $this->RegisterPropertyString("Schaltbefehl", "0");
        $this->RegisterPropertyInteger("Temperatur-ID", 0);
        $this->RegisterPropertyString("DeviceType", "0");


        // Create variable profile
        if (!IPS_VariableProfileExists("SS.DS_Absenkung")) {
            IPS_CreateVariableProfile("SS.DS_Absenkung", 2);
            IPS_SetVariableProfileText("SS.DS_Absenkung", "", " °C");
            IPS_SetVariableProfileValues("SS.DS_Absenkung",0, 5, 1);
        }


        if (!IPS_VariableProfileExists("SS.DS_Absenkung")) {
            IPS_CreateVariableProfile("SS.DS_Heizphase", 1);
            IPS_SetVariableProfileAssociation("SS.DS_Heizphase", 0, "Ausgeschalten", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("SS.DS_Heizphase", 1, "Frostschutz", "", 0xFFFFFF);
            IPS_SetVariableProfileAssociation("SS.DS_Heizphase", 2, "Heizen", "", 0xFF0000);
            IPS_SetVariableProfileAssociation("SS.DS_Heizphase", 3, "Abgesenkt", "", 0xFF7F00);
           }     


        // Assign icon to variable profile
        IPS_SetVariableProfileIcon("SS.DS_Absenkung",  "Temperature");
        IPS_SetVariableProfileIcon("SS.DS_Heizphase",  "Radiator");



        // Assign variable profile
        IPS_SetVariableCustomProfile($istemperature, "~Temperature.Room");
        IPS_SetVariableCustomProfile($shouldtemperature, "~Temperature.Room");
        IPS_SetVariableCustomProfile($reduction, "~Temperature.Room");
        IPS_SetVariableCustomProfile($reduction, "SS.DS_Absenkung");
        IPS_SetVariableCustomProfile($heatingphase, "SS.DS_Heizphase");


        if (@$this->GetIDForIdent("heatingplan") == false) {
            $heatingplan = IPS_CreateEvent(2);
            IPS_SetParent($heatingplan, $this->InstanceID);
            IPS_SetPosition($heatingplan, 4);
            IPS_SetEventScheduleAction($heatingplan, 0, "Heizen", 0xFF0000, "");
            IPS_SetEventScheduleAction($heatingplan, 1, "Absenkung", 0xFF7F00, "");
            IPS_SetEventScheduleGroup($heatingplan, 0, 31); //Mo - Fr (1 + 2 + 4 + 8 + 16)
            IPS_SetEventScheduleGroup($heatingplan, 1, 96); //Sa + So (32 + 64)
            IPS_SetName($heatingplan, "Heizplan");
            IPS_SetIdent($heatingplan, "heatingplan");
        }

        $wochenplan = $this->GetIDForIdent("heatingplan");
        IPS_SetIcon($wochenplan, "Clock");


        $this->EnableAction("ShouldTemperature");   // Activate the default action of the status variable
        $this->EnableAction("Reduction");           // Activate the default action of the status variable
       

        // Set default values after variable creation
        $id = $this->GetIDForIdent("HeatingPhase");
        SetValue($id, 0);



    }
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();


        if($this->ReadPropertyInteger("Temperatur-ID") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("Temperatur-ID"), VM_UPDATE);
        }



    }



    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Get ID for Ident / Variable: IsTemperature
        $id = $this->GetIDForIdent("IsTemperature");
        $value = $this->ReadPropertyInteger("Temperatur-ID");
        $content = GetValueFloat($value);
        SetValue($id, $content);
    
    }


    public function RequestAction($Ident, $Value) {
        $this->get_heatingplan_status(); //Aufruf Heizplan Rausfinden
        $this->set_temperature();


          switch ($Ident) {
              case 'ShouldTemperature':
                SetValue(IPS_GetObjectIDByIdent('ShouldTemperature', $this->InstanceID), $Value);
                break;
              case 'Reduction':
               SetValue(IPS_GetObjectIDByIdent('Reduction', $this->InstanceID), $Value);
                break;
                }
    
        }


    public function set_temperature() {          
        $heizphase = $this->GetValue("HeatingPhase");
        $soll_absenkung = $this->GetValue("Reduction");
        $soll_temperatur = $this->GetValue("ShouldTemperature");
        $ist_temperature = $this->GetValue("IsTemperature");
        $schaltbefehl_an = $this->ReadPropertyString("Schaltbefehl-An");
        $schaltbefehl_aus = $this->ReadPropertyString("Schaltbefehl-Aus");
        $schaltbefehl = $this->ReadPropertyString("Schaltbefehl");
        $modus = $this->ReadPropertyString("DeviceType");
        #echo  $ist_temperature;


        if ($modus == "0" ) {
        #echo "fremd";

            switch ($heizphase) {
                case 0:
                    #Execute action "AUS"
                    $schaltbefehl_aus;
                    break;
                case 1:
                    #Execute action "FrostSchutz"
                    if ($ist_temperature < 6) {
                        $schaltbefehl_an;     
                    }
                    else {
                        $schaltbefehl_aus;  
                    }
                    break;
                case 2:
                    #Execute action "Absenkung"
                    if ($ist_temperature > $soll_temperatur-$soll_absenkung) {
                        $schaltbefehl_aus;     
                    }
                    else {
                        $schaltbefehl_an;  
                    }

                    break;
                case 3:
                    #Execute action "Soll-Temperatur"
                    if ($ist_temperature > $soll_temperatur) {
                        $schaltbefehl_aus;     
                    }
                    else {
                        $schaltbefehl_an;  
                    }
                    break;
            }           
       }
       else {
     #echo "selbst";
        switch ($heizphase) {
            case 0:
                #Execute action "AUS"
                $schaltbefehl;
                #echo $schaltbefehl;
                break;
            case 1:
                #Execute action "FrostSchutz"
                if ($ist_temperature < 6) {
                $schaltbefehl;   
                #echo $schaltbefehl;
                }
                break;
            case 2:
                #Execute action "Absenkung"
                if ($ist_temperature > $soll_temperatur-$soll_absenkung) {
                $schaltbefehl;
                #echo $schaltbefehl;
                }
            case 3:
                 #Execute action "Soll-Temperatur"
                if ($ist_temperature > $soll_temperatur) {
                $schaltbefehl;
                #echo $schaltbefehl;
                }
                break;
            }
     
       }
}


public function get_heatingplan_status() {
    $wochenplan = $this->wochenplan_status($this->GetIDForIdent("heatingplan"));
    if ($wochenplan == 0)
    {
        $this->SetValue("HeatingPhase", 2); #Heizen
        #echo "Wochenplan Aktion: Abgesenkt --> ";
        #echo $this->ReadPropertyString("DeviceType");
    }
    else
    {
        $this->SetValue("HeatingPhase", 3); #Abgesenkt
        #echo "Wochenplan Aktion: Heizen --> ";
        #echo $this->ReadPropertyString("DeviceType");
    }      
}


public function wochenplan_status($id) {
    $e = IPS_GetEvent($id);
    $actionID = false;
    //Durch alle Gruppen gehen
    foreach($e['ScheduleGroups'] as $g) {
        //Überprüfen ob die Gruppe für heute zuständig ist
        if(($g['Days'] & pow(2,date("N",time())-1)) > 0)  {
            //Aktuellen Schaltpunkt suchen. Wir nutzen die Eigenschaft, dass die Schaltpunkte immer aufsteigend sortiert sind.
            foreach($g['Points'] as $p) {
               if(date("H") * 3600 + date("i") * 60 + date("s") >= $p['Start']['Hour'] * 3600 + $p['Start']['Minute'] * 60 + $p['Start']['Second']) {
                  $actionID = $p['ActionID'];
               } else {
                  break; //Sobald wir drüber sind, können wir abbrechen.
               }
           }
            break; //Sobald wir unseren Tag gefunden haben, können wir die Schleife abbrechen. Jeder Tag darf nur in genau einer Gruppe sein.
        }
    }
    #var_dump($actionID);
    return $actionID;
}



}
?>