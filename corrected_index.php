<?php

include "db_records.php";

class Api_handling{

    public $data;

    // base function connecting database
    public function __construct($db_data){
        $server_name = $db_data['servername'];
        $user_name = $db_data['username'];
        $password = $db_data['password'];
        $db_name = $db_data['site_id'];
        $this->con = mysqli_connect($server_name, $user_name, $password,$db_name);

        // Check connection
        if ($this->con->connect_error) {
            die("Connection failed: " . $this->con->connect_error);
        }

        $this->data = new db_records($db_name);
    }

    // public function db_connection check 
    public function getmachine_data(){
        $sql = "SELECT * FROM settings_part_log";
        $result = $this->con->query($sql);
                
        $normal_arr = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                array_push($normal_arr,$row);
                // echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
            }
        //    return $result;
        } 
        // $res['data'] = $this->func1();
        $res['data1'] = $normal_arr;
        return $res;
    }


    // calculate overall oee function 
    public function calculateOverallOEE($MachineWiseData){
        //temporary variable for Overall OEE,OOE,TEEP.....
        $tmpOEE=0;
        $tmpOOE =0;
        $tmpTEEP=0;

        foreach ($MachineWiseData as $value) {
            $tmpOEE = $tmpOEE + floatval($value['OEE']);
            $tmpOOE = $tmpOOE+floatval($value['OOE']);
            $tmpTEEP = $tmpTEEP+floatval($value['TEEP']);
        }

        //Average of the OEE to calculate Overall OEE....
        $OverallOEE['Overall_OEE'] = number_format((($tmpOEE/(sizeof($MachineWiseData)))),2);
        $OverallOEE['Overall_OOE'] = number_format((($tmpOOE/(sizeof($MachineWiseData)))),2);
        $OverallOEE['Overall_TEEP'] = number_format((($tmpTEEP/(sizeof($MachineWiseData)))),2);

        return $OverallOEE;
    }

    //Downtime reasons data ordering.....
    public function storeData($rawData,$machine,$part){ 
        $MachineWiseDataRaw = [];
        foreach ($machine as $m) {
            //Temporary variable for machine wise data split.......
            $tmpMachineWise = [];
            foreach ($part as $p) {
                //Temporary variable for part wise data split.......
                $tmpPartWise = [];
                foreach ($rawData as $r) {
                    if (($r['machine_id'] == $m['machine_id'])) {
                        $tmpPart = explode(",", $r['part_id']);
                        foreach ($tmpPart as $k) {
                            if ($k == $p['part_id']) {
                                array_push($tmpPartWise, $r);
                            }
                        }
                    }
                }
                $tmp = array($p['part_id']=> $tmpPartWise);
                array_push($tmpMachineWise, $tmp);
            }
            $tmpMachine = array($m['machine_id'] =>$tmpMachineWise);
            array_push($MachineWiseDataRaw, $tmpMachine);
        }
        return $MachineWiseDataRaw;
    }


    // Machine-Wise Downtime........
    public function allTimeFound($output,$machine,$part,$start_date,$end_date){
        // $days=[];
        // while (strtotime($start_date) <= strtotime($end_date)) {
        //     array_push($days,$start_date);
        //     $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
        // }
    
        $alltime=[]; 
            foreach ($machine as $key => $m) {
                $duration_min =0;
                $duration_sec = 0;
                foreach ($output as $key => $value) {
                    $event = trim($value['event']);
                    $event = str_replace(' ', '', $event);
                    $event = strtolower($event);
                    if (($value['machine_id'] == $m['machine_id'] and $event!="nodata") or ($value['machine_id'] == $m['machine_id'] and $event!="offline")) {
                        $tmp = explode(".", $value['duration']);
                        if (sizeof($tmp) >1) {
                            $duration_min = floatval($duration_min) + floatval($tmp[0]);
                            $duration_sec = floatval($duration_sec) + floatval($tmp[1]);
                        }
                        else{
                            $duration_min = floatval($duration_min) + floatval($tmp[0]);
                        }
                    }
                }
                $duration = $duration_min + ($duration_sec/60);
                $tmp = array('machine_id' => $m['machine_id'],'duration'=>$duration);
                array_push($alltime, $tmp);
            }
        return $alltime;
    }
    
    // Day-wise With Machine-Wise Downtime....
    public function allTimeFoundDay($output,$machine,$part,$start_date,$end_date){
        $days=[];
        while (strtotime($start_date) <= strtotime($end_date)) {
            array_push($days,$start_date);
            $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
        }
        
        $dayWise=[];
        foreach ($days as $key => $d) {
            $alltime=[];
            foreach ($machine as $key => $m) {
                $duration_min =0;
                $duration_sec = 0;
                foreach ($output as $key => $value) {
                    $event = trim($value['event']);
                    $event = str_replace(' ', '', $event);
                    $event = strtolower($event);
                    if (($value['machine_id'] == $m['machine_id'] and $value['shift_date']==$d and $event!="nodata") or ($value['machine_id'] == $m['machine_id'] and $value['shift_date']==$d and $event!="offline")) {
                        $tmp = explode(".", $value['duration']);
                        if (sizeof($tmp) >1) {
                            $duration_min = floatval($duration_min) + floatval($tmp[0]);
                            $duration_sec = floatval($duration_sec) + floatval($tmp[1]);
                        }
                        else{
                            $duration_min = floatval($duration_min) + floatval($tmp[0]);
                        }
                    }
                }
                $duration = $duration_min + ($duration_sec / 60);
                $tmp = array('machine_id' => $m['machine_id'],'duration'=>$duration);
                array_push($alltime, $tmp);
            }
            $t= array('date' => $d, 'data' => $alltime);
            array_push($dayWise, $t);
        }
        return $dayWise;
    }

    // find planned downtime and unplanned downtime and machine off downtime function
    public function oeeData($MachineWiseDataRaw,$getAllTimeValues,$noplan=false){
        $DowntimeTimeData =[];
        foreach ($MachineWiseDataRaw as $Machine){
            $MachineOFFDown = 0;
            $UnplannedDown = 0;
            $PlannedDown = 0;
            $MachineId = "";
            $PartWiseDowntime=[];

            $MachineOFFDownSec=0;
            $UnplannedDownSec=0;
            $PlannedDownSec=0;
            foreach ($Machine as $key => $Part) {
                $MachineId = $key;
                foreach ($Part as $Record) {
                    $PartMachineOFFDown = 0;
                    $PartUnplannedDown = 0;
                    $PartPlannedDown = 0;
                    $PartInMachine =0;

                    $PartMachineOFFDownSec = 0;
                    $PartUnplannedDownSec = 0;
                    $PartPlannedDownSec = 0;
                    $PartInMachineSec =0;
                    $part_id="";
                    foreach ($Record as $Values) {
                        $tmpMachineOFFDown = 0;
                        $tmpPlannedDown = 0;
                        $tmpUnplannedDown = 0;

                        $tmpMachineOFFDownSec = 0;
                        $tmpPlannedDownSec = 0;
                        $tmpUnplannedDownSec = 0;

                        // For Part in Machine
                        $tmpUnplannedDownPart=0;
                        $tmpUnplannedDownPartSec=0;

                        foreach ($Values as $key => $DTR) {
                            $part_id=$DTR['part_id'];
                            $st = explode(".", $DTR['split_duration']);
                            // One Tool, Multi-Part
                            $part_count = explode(",", $DTR['part_id']);
                            $st[0]=$st[0]/sizeof($part_count);
                            if (sizeof($st) > 1) {
                                $st[1]=$st[1]/sizeof($part_count);
                            }   

                            $noplan = trim($DTR['downtime_reason']);
                            $noplan = strtolower(str_replace(" ","",$noplan));
                            if ($DTR['downtime_category'] == 'Planned' && $noplan == 'noplan' && $noplan == true) {
                                if (sizeof($st) > 1) {
                                    $tmpMachineOFFDown = $tmpMachineOFFDown + $st[0];
                                    $tmpMachineOFFDownSec = $tmpMachineOFFDownSec + $st[1];
                                }
                                else{
                                    $tmpMachineOFFDown = $tmpMachineOFFDown + $st[0];   
                                }
                            }
                            else if($DTR['downtime_category'] == 'Unplanned'){
                                // $st = explode(".", $DTR['split_duration']);
                                if (sizeof($st) > 1) {
                                    $tmpUnplannedDown = $tmpUnplannedDown + $st[0];
                                    $tmpUnplannedDownSec = $tmpUnplannedDownSec +$st[1];
                                }
                                else {
                                    $tmpUnplannedDown = $tmpUnplannedDown + $st[0];
                                }
                                // echo "<br>";
                                // echo $tmpUnplannedDown;
                            }
                            else if(($DTR['downtime_category'] == 'Planned') && ($DTR['downtime_reason'] == 'Machine OFF')){
                                // $st = explode(".", $DTR['split_duration']);
                                if (sizeof($st) > 1) {
                                    $tmpMachineOFFDown = $tmpMachineOFFDown + $st[0];
                                    $tmpMachineOFFDownSec = $tmpMachineOFFDownSec + $st[1];
                                }
                                else{
                                    $tmpMachineOFFDown = $tmpMachineOFFDown + $st[0];   
                                }
                                
                            }
                            else {
                                // $st = explode(".", $DTR['split_duration']);

                                if (sizeof($st) > 1) {
                                    $tmpPlannedDown = $tmpPlannedDown + $st[0];
                                    $tmpPlannedDownSec = $tmpPlannedDownSec + $st[1];
                                }
                                else{
                                    $tmpPlannedDown = $tmpPlannedDown + $st[0];   
                                }
                                
                            }

                            if ($DTR['downtime_reason'] != 'Machine OFF' || ($DTR['downtime_category'] == 'Planned' && $noplan == 'noplan' && $noplan == true)) {
                                if (sizeof($st) > 1) {
                                    $PartInMachine = $PartInMachine + $st[0];
                                    $PartInMachineSec = $PartInMachineSec + $st[1];
                                }
                                else{
                                    $PartInMachine = $PartInMachine + $st[0];   
                                }
                            }

                            if($DTR['downtime_category'] == 'Unplanned' and $DTR['downtime_reason'] != 'Machine OFF'){
                                // $st = explode(".", $DTR['split_duration']);
                                if (sizeof($st) > 1) {
                                    $tmpUnplannedDownPart = $tmpUnplannedDownPart + $st[0];
                                    $tmpUnplannedDownPartSec = $tmpUnplannedDownPartSec +$st[1];
                                }
                                else {
                                    $tmpUnplannedDownPart = $tmpUnplannedDownPart + $st[0];
                                }
                            }
                        }
                        $PartMachineOFFDown = $PartMachineOFFDown + $tmpMachineOFFDown; 
                        // $PartUnplannedDown = $PartUnplannedDown + $tmpUnplannedDown;
                        $PartUnplannedDown = $PartUnplannedDown + $tmpUnplannedDownPart;
                        $PartPlannedDown = $PartPlannedDown + $tmpPlannedDown;

                        $PartMachineOFFDownSec = $PartMachineOFFDownSec + $tmpMachineOFFDownSec; 
                        // $PartUnplannedDownSec = $PartUnplannedDownSec + $tmpUnplannedDownSec;
                        $PartUnplannedDownSec = $PartUnplannedDownSec + $tmpUnplannedDownPartSec;
                        $PartPlannedDownSec = $PartPlannedDownSec + $tmpPlannedDownSec;
                    }
                    $MachineOFFDown = $PartMachineOFFDown + $MachineOFFDown; 
                    $UnplannedDown = $PartUnplannedDown + $UnplannedDown;
                    $PlannedDown = $PartPlannedDown + $PlannedDown;

                    $MachineOFFDownSec = $PartMachineOFFDownSec + $MachineOFFDownSec; 
                    $UnplannedDownSec = $PartUnplannedDownSec + $UnplannedDownSec;
                    $PlannedDownSec = $PartPlannedDownSec + $PlannedDownSec;

                    if ($part_id != "") {
                        $PartMachineOFFDown = floatval($PartMachineOFFDown)+floatval($PartMachineOFFDownSec/60);
                        $PartPlannedDown = floatval($PartPlannedDown)+floatval($PartPlannedDownSec/60);
                        $PartUnplannedDown = floatval($PartUnplannedDown) + floatval($PartUnplannedDownSec/60);

                        $PartInMachine = floatval($PartInMachine)+floatval($PartInMachineSec/60);

                        $tmpUpTimeMin = 0;
                        $tmpUpTimeSec = 0;
                        foreach ($getAllTimeValues as $key => $upt) {
                            if ($upt['machine_id']==$MachineId and $upt['part_id'] == $part_id and $upt['event']=="Active") {
                                $stu = explode(".", $upt['duration']);
                                $part_count = explode(",", $upt['part_id']);
                                $stu[0]=$stu[0]/sizeof($part_count);
                                if (sizeof($stu) > 1) {
                                    $stu[1]=$stu[1]/sizeof($part_count);
                                    $tmpUpTimeSec = $tmpUpTimeSec + $stu[1];
                                }
                                $tmpUpTimeMin = $tmpUpTimeMin + $stu[0];
                            }
                        }
                        $tmpPartTime = floatval($PartInMachine) +  floatval($tmpUpTimeMin) + floatval($tmpUpTimeSec/60);
                        $tmp= array('part_id' => $part_id,'Planed'=>$PartPlannedDown,'Unplanned'=>$PartUnplannedDown,'Machine_OFF'=>$PartMachineOFFDown,'PartInMachine' => $tmpPartTime);
                        array_push($PartWiseDowntime, $tmp);
                    }
                }
            }

            $MachineOFFDown = floatval($MachineOFFDown)+floatval($MachineOFFDownSec/60);
            $PlannedDown = floatval($PlannedDown)+floatval($PlannedDownSec/60);
            $UnplannedDown = floatval($UnplannedDown) + floatval($UnplannedDownSec/60);

            $tempCalc = $MachineOFFDown + $UnplannedDown + $PlannedDown;
            if ((float)$tempCalc>=0) {
               $tmpDown = array("Machine_ID"=>$MachineId,"Planned"=>$PlannedDown,"Unplanned"=>$UnplannedDown,"Machine_OFF"=>$MachineOFFDown,"All"=>$tempCalc,"Part_Wise"=>$PartWiseDowntime);
                array_push($DowntimeTimeData, $tmpDown);
            }
        }
        return $DowntimeTimeData;
    }

    // duration split function
    public function getDuration($f,$t){
        $from_time = strtotime($f); 
        $to_time = strtotime($t); 
        $diff_minutes = (int)(abs($from_time - $to_time) / 60);
        $diff_sec = abs($from_time - $to_time) % 60;
        $duration = $diff_minutes.".".$diff_sec;
        return $duration;
    }

    // data spliting in downtime and production main function for all graph
    public function getDataRaw($graphRef,$fromTime=null,$toTime=null){
        // Calculation for to find ALL time value
        $tmpFromDate =explode("T", $fromTime);
        $tmpToDate = explode("T", $toTime);

        //Difference between two dates......
        $diff = abs(strtotime($toTime) - strtotime($fromTime)); 
        $AllTime = 0;   

        //time split for date+time seperated values
        $tmpFrom = explode("T",$fromTime);
        $tmpTo = explode("T",$toTime);
        // temporary time......
        $tempFrom = explode(":",$tmpFrom[1]);
        $tempTo = explode(":",$tmpTo[1]);

        //From date
        $FromDate = $tmpFrom[0];
        //milli seconds added ":00", because in real data milli seconds added
        $FromTime = $tempFrom[0].":00".":00";
        //To Date
        $ToDate = $tmpTo[0];
        $ToTime = $tempTo[0].":00".":00";

        // // Data from reason mapping table...........
        $output = $this->data->getDataRaw($FromDate,$FromTime,$ToDate,$ToTime);
       
        // Data from PDM Events table for find the All Time Duration...........
        $getAllTimeValues = $this->data->getDataRawAll($FromDate,$ToDate);

        $getOfflineId = $this->data->getOfflineEventId($FromDate,$FromTime,$ToDate,$ToTime);
        
        // Get the Machine Record.............
        $machine = $this->data->getMachineRecActive($FromDate,$ToDate);
        
        //Part list Details from Production Info Table between the given from and To durations......
        $part = $this->data->getPartRec($FromDate,$ToDate);
        
        //Production Data for PDM_Production_Info Table......
        $production = $this->data->getProductionRec($FromDate,$ToDate);
       
        // Get the Inactive(Current) Data.............
        $getInactiveMachine = $this->data->getInactiveMachineData();

        // Date Filte for PDM Reason Mapping Data........
        $len_id = sizeof($getOfflineId);
        $s_time_range_limit =  strtotime($FromDate." ".$FromTime);
        $e_time_range_limit =  strtotime($ToDate." ".$ToTime);

        foreach ($output as $key => $value) {
            $check_no = 0;
            for($i=0;$i<$len_id;$i++){
                if ($getOfflineId[$i]['machine_event_id'] == $value['machine_event_id']) {
                    unset($output[$key]);
                    $check_no = 1;
                    break;
                }
            }
            if($check_no == 0){
                if ($value['split_duration']<0) {
                    unset($output[$key]);
                }
                else{
                    $s_time_range =  strtotime($value['calendar_date']." ".$value['start_time']);
                    // $e_time_range =  strtotime($value['calendar_date']." ".$value['end_time']);
                    $duration_min =0;
                    $duration_sec =0;

                    $tmp = explode(".", $value['split_duration']);
                    if (sizeof($tmp) >1) {
                        $duration_min = floatval($tmp[0]);
                        $duration_sec = floatval($tmp[1]);
                    }
                    else{
                        $duration_min = floatval($tmp[0]);
                    }
                    $duration = (int)(($duration_min*60) + ($duration_sec));
                    $e_time_range = $s_time_range + $duration;

                    if ($s_time_range <= $s_time_range_limit && $e_time_range >= $s_time_range_limit) {
                        $output[$key]['start_time'] = $FromTime;
                        if ($e_time_range >= $e_time_range_limit) {
                            $output[$key]['end_time'] = $ToTime;
                        }
                        $output[$key]['split_duration'] = $this->getDuration($value['calendar_date']." ".$output[$key]['start_time'],$value['calendar_date']." ".$output[$key]['end_time']);
                    }
                    else if ($s_time_range < $e_time_range_limit && $e_time_range > $e_time_range_limit) {
                        $output[$key]['end_time'] = $ToTime;
                        $output[$key]['split_duration'] = $this->getDuration($value['calendar_date']." ".$output[$key]['start_time'],$value['calendar_date']." ".$output[$key]['end_time']);
                    }
                    else{
                        if ($e_time_range <= $s_time_range_limit){
                            unset($output[$key]);
                        }
                        if ($s_time_range >= $e_time_range_limit){
                            unset($output[$key]);
                        }
                    }

                    //For remove the current data of inactive machines.........
                    foreach ($getInactiveMachine as $v) {
                        $t = explode(" ", $v['max(r.last_updated_on)']);
                        $start_time_range =  strtotime($v['max(r.last_updated_on)']);
                        if ($s_time_range_limit > $start_time_range && $value['machine_id'] == $v['machine_id']){
                            unset($output[$key]);
                        }
                    }
                }
            }
        }

        // Filter for Find the All Time.............
        foreach ($getAllTimeValues as $key => $value) {
            if ($value['duration']<0) {
                unset($getAllTimeValues[$key]);
            }
            else{
                $s_time_range =  strtotime($value['calendar_date']." ".$value['start_time']);
                // $e_time_range =  strtotime($value['calendar_date']." ".$value['end_time']);
                $duration_min =0;
                $duration_sec =0;

                $tmp = explode(".", $value['duration']);
                if (sizeof($tmp) >1) {
                    $duration_min = floatval($tmp[0]);
                    $duration_sec = floatval($tmp[1]);
                }
                else{
                    $duration_min = floatval($tmp[0]);
                }
                $duration = (int)(($duration_min*60) + ($duration_sec));
                $e_time_range = $s_time_range + $duration;

                if ($s_time_range <= $s_time_range_limit && $e_time_range >= $s_time_range_limit) {
                    $getAllTimeValues[$key]['start_time'] = $FromTime;
                    if ($e_time_range >= $e_time_range_limit) {
                        $getAllTimeValues[$key]['end_time'] = $ToTime;
                    }
                    $getAllTimeValues[$key]['duration'] = $this->getDuration($value['calendar_date']." ".$getAllTimeValues[$key]['start_time'],$value['calendar_date']." ".$getAllTimeValues[$key]['end_time']);
                }
                else if ($s_time_range < $e_time_range_limit && $e_time_range > $e_time_range_limit) {
                    $getAllTimeValues[$key]['end_time'] = $ToTime;
                    $getAllTimeValues[$key]['duration'] = $this->getDuration($value['calendar_date']." ".$getAllTimeValues[$key]['start_time'],$value['calendar_date']." ".$getAllTimeValues[$key]['end_time']);
                }
                else{
                    if ($e_time_range <= $s_time_range_limit){
                        unset($getAllTimeValues[$key]);
                    }
                    if ($s_time_range >= $e_time_range_limit){
                        unset($getAllTimeValues[$key]);
                    }
                }

                //For remove the current data of inactive machines.........
                foreach ($getInactiveMachine as $v) {
                    $start_time_range =  strtotime($v['max(r.last_updated_on)']);
                    if ($s_time_range_limit > $start_time_range && $value['machine_id'] == $v['machine_id']){
                        unset($getAllTimeValues[$key]);
                    }
                }
            }
        }   

        // Filter for Production Info Table Data..........
        foreach ($production as $key => $value) {   
            $s_time_range =  strtotime($value['calendar_date']." ".$value['start_time']);
            $e_time_range =  strtotime($value['calendar_date']." ".$value['end_time']);
            
            if ($s_time_range < $s_time_range_limit) {
                unset($production[$key]);
            }
            elseif ($e_time_range > $e_time_range_limit){
                unset($production[$key]);
            }
            elseif ($s_time_range >= $e_time_range_limit) {
                unset($production[$key]);
            }

            // For remove the current data of inactive machines.........
            foreach ($getInactiveMachine as $v) {
                $start_time_range =  strtotime($v['max(r.last_updated_on)']);
                if ($s_time_range_limit > $start_time_range && $value['machine_id'] == $v['machine_id']){
                    unset($production[$key]);
                }
            }
        }


        //Downtime reasons data ordering.....
        $MachineWiseDataRaw = $this->storeData($output,$machine,$part);

        // Machine-Wise Downtime........
       
        $allTimeValues = $this->allTimeFound($getAllTimeValues,$machine,$part,$FromDate,$ToDate);

        // Day-wise With Machine-Wise Downtime....
        $allTimeValuesDay = $this->allTimeFoundDay($getAllTimeValues,$machine,$part,$FromDate,$ToDate);

        //Function return for qualityOpportunity graph........
        if ($graphRef == "qualityOpportunity") {
            return $production;
        } 

        if ($graphRef  == "AvailabilityReasonWise") {
            return $output;
        }

        if ($graphRef == "unplanned_machineoff") {
            return $getAllTimeValues;
        }

        if($graphRef == "OpportunityTrendDay"){         
            $res['raw'] = $MachineWiseDataRaw;
            $res['machine'] = $machine;
            $res['part'] = $part;
            $res['downtimeTime']=$allTimeValuesDay;
            return $res;
        }
        
        //Part Details.....
        $partsDetails = $this->data->settings_tools(); 
        // return $partsDetails;
        //Downtime data has been calculated......
        // To find Planned Downtime, Unplanned Downtime, Machine OFF Downtime.........

        if ($graphRef == "PLOpportunity") {
            $downtime = $this->oeeData($MachineWiseDataRaw,$getAllTimeValues,true);
        }else{
            $downtime = $this->oeeData($MachineWiseDataRaw,$getAllTimeValues);
        }

        if ($graphRef == "PartPLOpportunity") {
            $res['production'] = $production;
            $res['downtime'] = $downtime;
            return $res;
        }
        
        //Function return for performanceOpportunity graph........
        if ($graphRef == "PerformanceOpportunity") {
            $res['production'] = $production;
            $res['downtime'] = $downtime;
            $res['machineData'] = $MachineWiseDataRaw;
            $res['all']=$allTimeValues;
            return $res;
        }

        //Function return for Profit and Loss Opportunity..........
        if ($graphRef == "PLOpportunity") {
            return $downtime;
        }

        //Machine wise Performance,Quality,Availability........
        if ($graphRef == "Overall_graph_oee") {
            $over_all['downtime'] = $downtime;
            $over_all['all_time'] = $allTimeValues;
            $over_all['part'] = $part;
            $over_all['production'] = $production;
            $over_all['part_details'] = $partsDetails;

            return $over_all;
        }
        /*
        $MachineWiseData = [];
        foreach ($downtime as $down) {
            $PlannedDownTime = $down['Planned'];
            $UnplannedDownTime = $down['Unplanned'];
            $MachineOFFDownTime = $down['Machine_OFF'];
            $All = 0;
            foreach ($allTimeValues as $a) {
                if ($a['machine_id']==$down['Machine_ID']) {
                    $All = floatval($a['duration']);
                }
            }
            if ($All >0) {
                $TotalCTPP_NICT = 0;
                $TotalCTPP = 0;
                $TotalReject = 0;
                $TotalCTPP_NICT_Arry = [];
                foreach ($part as $p) {
                    $tmpCorrectedTPP_NICT = 0;
                    $tmpCorrectedTPP = 0;
                    $tmpReject = 0;
                    foreach ($production as $product) {
                        if ($product['machine_id'] == $down['Machine_ID'] && $p['part_id'] == $product['part_id']) {
                            //To find NICT.....
                            $NICT = 0;

                            foreach ($partsDetails as $partVal) {
                                if ($p['part_id'] == $partVal['part_id']) {
                                    $mnict = explode(".", $partVal['NICT']);
                                    if (sizeof($mnict)>1) {
                                        $NICT = (($mnict[0])+($mnict[1]/1000))/60;
                                    }else{
                                        $NICT = ($mnict[0]/60);
                                    }
                                }
                            }

                            $corrected_tpp = (int)($product['production'])+(int)($product['corrections']);
                            $CorrectedTPP_NICT = $NICT*$corrected_tpp;
                            // For Find Performance.....
                            $tmpCorrectedTPP_NICT = $tmpCorrectedTPP_NICT+$CorrectedTPP_NICT;

                            //For Find Quality.......
                            $tmpCorrectedTPP = $tmpCorrectedTPP+$corrected_tpp;
                            $tmpReject = $tmpReject+$product['rejections'];

                        }
                    }

                    $TotalCTPP_NICT =$TotalCTPP_NICT+$tmpCorrectedTPP_NICT;
                    $TotalCTPP =$TotalCTPP+$tmpCorrectedTPP;
                    $TotalReject = $TotalReject+$tmpReject;
                }

                //Machine Wise Performance ......
                $tp=floatval($All)-(floatval($down['Planned'])+floatval($down['Unplanned'])+floatval($down['Machine_OFF']));
                $performance=0;
                
                if (floatval($tp)>0) {
                    $performance = floatval($TotalCTPP_NICT)/floatval($tp);
                }
                else{
                    $performance=0;
                }
                
                //Machine Wise Quality ......
                if ($TotalCTPP <=0) {
                    $quality = 0;
                }
                else{
                    $quality = floatval(((floatval($TotalCTPP) - floatval($TotalReject))/floatval($TotalCTPP)));
                }

                //Machine Wise Availability ......
                if (floatval($All-(floatval($down['Planned'])+floatval($down['Machine_OFF']))) >0) {
                    $availability = floatval($All-(floatval($down['Planned'])+floatval($down['Unplanned'])+floatval($down['Machine_OFF'])))/($All-(floatval($down['Planned'])+floatval($down['Machine_OFF'])));
                }
                else{
                    $availability=0;
                }

                // Machine Wise Availability TEEP.......
                if (floatval($All) >0) {
                    $availTEEP = (($All-($down['Planned']+$down['Unplanned']+$down['Machine_OFF']))/($All));
                }else{
                    $availTEEP=0;
                }
                // Machine Wise Availability OOE.....
                if (floatval($All-$down['Machine_OFF'])>0) {
                    $availOOE = (($All-($down['Planned']+$down['Unplanned']+$down['Machine_OFF']))/($All-$down['Machine_OFF']));
                }
                else{
                    $availOOE=0;
                }
                //Machine Wise OEE .......
                $oee = number_format(($performance*$quality*$availability),2);

                // Machine Wise TEEP.....
                $teep = number_format(($performance*$quality*$availTEEP),2);
                // Machine Wise OOE.....
                $ooe = number_format(($performance*$quality*$availOOE),2);

                //Store Machine wise Data......
                $tmp = array("Machine_Id"=>$down['Machine_ID'],"Availability"=>$availability*100,"Performance"=>$performance*100,"Quality"=>$quality*100,"Availability_TEEP"=>$availTEEP*100,"Availability_OOE"=>$availOOE*100,"OEE"=>$oee*100,"TEEP"=>$teep*100,"OOE"=>$ooe*100);
                array_push($MachineWiseData, $tmp);
            }
        }
        

        if ($graphRef == "MachinewiseOEE") {
            return $MachineWiseData;
        }
        */
        if ($graphRef == "ReasonwiseMachine") {
            return $downtime;
        }
        
        /*
        $Overall = $this->calculateOverallOEE($MachineWiseData);       
        if ($graphRef == "Overall") {
            return $Overall;
        }
        */

    }

    // OEE TREND DAY WISE GRAPH 
    public function oeeDataTreand($MachineWiseDataRaw,$x,$part,$days,$noplan=false){
        $downData=[];
        foreach ($days as $d) {
            $DowntimeTimeData =[];
            foreach ($MachineWiseDataRaw as $Machine){
                $MachineOFFDown = 0;
                $UnplannedDown = 0;
                $PlannedDown = 0;
                $MachineId = "";
                foreach ($Machine as $key => $Part) {
                    $MachineId = $key;
                    foreach ($Part as $Record) {
                        $PartMachineOFFDown = 0;
                        $PartUnplannedDown = 0;
                        $PartPlannedDown = 0;
                        foreach ($Record as $Values) {
                            $tmpMachineOFFDown = 0;
                            $tmpPlannedDown = 0;
                            $tmpUnplannedDown = 0;
                            foreach ($Values as $key => $DTR) {
                                // if (($value['machine_id'] == $m['machine_id'] and $value['calendar_date']==$d and $event!="nodata") or ($value['machine_id'] == $m['machine_id'] and $DTR['calendar_date']==$d and $event!="offline")) {
                                if ($DTR['shift_date'] == $d) {
                                    $part_id=$DTR['part_id'];
                                    $st = explode(".", $DTR['split_duration']);
                                    // One Tool, Multi-Part
                                    $part_count = explode(",", $DTR['part_id']);
                                    $st[0]=$st[0]/sizeof($part_count);
                                    if (sizeof($st) > 1) {
                                        $st[1]=$st[1]/sizeof($part_count);
                                    }

                                    if (sizeof($st)>1) {
                                        $duration = ($st[0]+($st[1]/60));
                                    }
                                    else{
                                        $duration = ($st[0]);
                                    }

                                    $noplan_c = trim($DTR['downtime_reason']);
                                    $noplan_c = strtolower(str_replace(" ","",$noplan));
                                    if ($DTR['downtime_category'] == 'Planned' && $noplan_c == 'noplan' && $noplan == true) {
                                        $tmpMachineOFFDown = $tmpMachineOFFDown + $duration;
                                    }
                                    else if($DTR['downtime_category'] == 'Unplanned'){
                                        $tmpUnplannedDown = $tmpUnplannedDown + $duration;
                                    }
                                    else if(($DTR['downtime_category'] == 'Planned') && ($DTR['downtime_reason'] == 'Machine OFF')){
                                        $tmpMachineOFFDown = $tmpMachineOFFDown + $duration;
                                    }
                                    else {
                                        $tmpPlannedDown = $tmpPlannedDown + $duration;
                                    }
                                }
                            }
                            $PartMachineOFFDown = $PartMachineOFFDown + $tmpMachineOFFDown; 
                            $PartUnplannedDown = $PartUnplannedDown + $tmpUnplannedDown;
                            $PartPlannedDown = $PartPlannedDown + $tmpPlannedDown;
                        }
                        $MachineOFFDown = $PartMachineOFFDown + $MachineOFFDown; 
                        $UnplannedDown = $PartUnplannedDown + $UnplannedDown;
                        $PlannedDown = $PartPlannedDown + $PlannedDown;
                    }
                }

                $tempCalc = $MachineOFFDown + $UnplannedDown + $PlannedDown;
                $tmpDown = array("Machine_ID"=>$MachineId,"Planned"=>$PlannedDown,"Unplanned"=>$UnplannedDown,"Machine_OFF"=>$MachineOFFDown,"All"=>$tempCalc);
                array_push($DowntimeTimeData, $tmpDown);
            }
            $tmp = array("date"=>$d,"data"=>$DowntimeTimeData);
            array_push($downData,$tmp);
        }
            return $downData;
    }


    // total rejection sum function 
    public function find_total_rejection($from_date,$to_date,$machine_arr,$part_arr){
        // get filtered prodcution data 
        $get_production_data = $this->getDataRaw("qualityOpportunity",$from_date,$to_date);
        // return $get_production_data;

        $total_rejection_val = 0;
        foreach ($get_production_data as $key => $value) {
            if (in_array($value['machine_id'],$machine_arr)) {
                if (in_array($value['part_id'],$part_arr)) {
                    $total_rejection_val = $total_rejection_val + $value['rejections'];
                }
            }
        }

        return $total_rejection_val;
    }


    // total unnamed hours function
    public function find_total_unnamed($ref,$fromdate,$todate,$machine_arr,$part_arr){
        // get downtime reason mapping records its filter nodata and offline event data
        $get_downtime_reason_mapping = $this->getDataRaw("AvailabilityReasonWise",$fromdate,$todate);
        // return $get_downtime_reason_mapping;
        
        if ($ref=="total_unnamed_hour") {
            // total unnamed hours duration
            $total_minutes_duration = 0;
            $total_duration_seconds = 0;
            foreach ($get_downtime_reason_mapping as $key => $value) {
                if (in_array($value['machine_id'],$machine_arr)) {
                    if (in_array($value['part_id'],$part_arr)) {
                        if ($value['downtime_reason_id']==0) {
                            $split_duration = explode(".",$value['split_duration']);
                            if (sizeof($split_duration)>1) {
                                $total_minutes_duration = $total_minutes_duration+$split_duration[0];
                                $total_duration_seconds = $total_duration_seconds + $split_duration[1];
                            }else{
                                $total_minutes_duration = $total_minutes_duration + $split_duration[0];
                            }
                        }
                    }
                }
            }
            $minutes_conversion_tmp = ($total_minutes_duration + ($total_duration_seconds/60));
            $hourly_convertion = ($minutes_conversion_tmp/60);
            return $hourly_convertion;
        }else if($ref=="total_unnamed_count"){
            // total unnamed count condition
            $total_unnamed_count = 0;
            foreach ($get_downtime_reason_mapping as $key => $value) {
                if (in_array($value['machine_id'],$machine_arr)) {
                    if (in_array($value['part_id'],$part_arr)) {
                        if ($value['downtime_reason_id']==0) {
                            $total_unnamed_count = $total_unnamed_count+1;
                        }
                    }
                }
            }
            return $total_unnamed_count;
        }
        
    }




    // split downtime for particular machine , part  planned downtime or machine off downtime or unplanned downtime
    public function split_downtime($ref,$fromdate,$todate,$machine_arr,$part_arr){
        $getdowntime = $this->getDataRaw("PLOpportunity",$fromdate,$todate);
        $res_category = "";
        if ($ref=="planned_downtime") {
            $res_category="Planed";
        }
        else if($ref=="unplanned_downtime"){
            $res_category="Unplanned";
        }
        else if($ref=="planned_machine_off"){
            $res_category="Machine_OFF";
        }
        else if($ref == "total_downtime"){
            $res_category = "total_downtime";
        }

        // global variable its collecting duration
        $total_minutes_duration = 0;
        foreach ($getdowntime as $key => $value) {
            $tmp_total_minutes = 0;
            if (in_array($value['Machine_ID'],$machine_arr)) {
                foreach ($value['Part_Wise'] as $k1 => $val) {
                    if (in_array($val['part_id'],$part_arr)) {
                        if ($res_category!="total_downtime") {
                            if ($res_category=="Planed") {
                                $tmp_total_minutes = $tmp_total_minutes+$val['Planed']; 
                            }
                            else if($res_category=="Unplanned"){
                                $tmp_total_minutes = $tmp_total_minutes+$val['Unplanned']; 
                            }
                            else if($res_category=="Machine_OFF"){
                                $tmp_total_minutes = $tmp_total_minutes+$val['Machine_OFF']; 
                            }
                        }else{
                            $collect_total_downtime = $val['Planed'] + $val['Unplanned'] + $val['Machine_OFF'];
                            $tmp_total_minutes = $tmp_total_minutes + $collect_total_downtime;
                        }
                    }
                }
                $total_minutes_duration = $tmp_total_minutes+$total_minutes_duration;
            }
            
        }
        
       
        
        $hours_conversion = (float)($total_minutes_duration/60);
        return $hours_conversion;
    }


    // split overall oee and ooe and teep percentage function
    public function split_overall_graph($ref,$fromdate,$todate,$machine_arr,$part_arr){
        $overall_data = $this->getDataRaw('Overall_graph_oee',$fromdate,$todate);
        // return $overall_data;
        $result_data = 0;
        $MachineWiseData = [];
        foreach ($overall_data['downtime'] as $down) {
            $PlannedDownTime = $down['Planned'];
            $UnplannedDownTime = $down['Unplanned'];
            $MachineOFFDownTime = $down['Machine_OFF'];
            if (in_array($down['Machine_ID'],$machine_arr)) {
                $All = 0;
                foreach ($overall_data['all_time'] as $a) {
                    if ($a['machine_id']==$down['Machine_ID']) {
                        $All = floatval($a['duration']);
                    }
                }
                if ($All >0) {
                    $TotalCTPP_NICT = 0;
                    $TotalCTPP = 0;
                    $TotalReject = 0;
                    $TotalCTPP_NICT_Arry = [];
                    foreach ($overall_data['part'] as $p) {
                        if (in_array($p['part_id'],$part_arr)) {
                            $tmpCorrectedTPP_NICT = 0;
                            $tmpCorrectedTPP = 0;
                            $tmpReject = 0;
                            foreach ($overall_data['production'] as $product) {
                                
                                if ($product['machine_id'] == $down['Machine_ID'] && $p['part_id'] == $product['part_id']) {
                                    //To find NICT.....
                                    $NICT = 0;
        
                                    foreach ($overall_data['part_details'] as $partVal) {
                                        if ($p['part_id'] == $partVal['part_id']) {
                                            $mnict = explode(".", $partVal['NICT']);
                                            if (sizeof($mnict)>1) {
                                                $NICT = (($mnict[0])+($mnict[1]/1000))/60;
                                            }else{
                                                $NICT = ($mnict[0]/60);
                                            }
                                        }
                                    }
        
                                    $corrected_tpp = (int)($product['production'])+(int)($product['corrections']);
                                    $CorrectedTPP_NICT = $NICT*$corrected_tpp;
                                    // For Find Performance.....
                                    $tmpCorrectedTPP_NICT = $tmpCorrectedTPP_NICT+$CorrectedTPP_NICT;
        
                                    //For Find Quality.......
                                    $tmpCorrectedTPP = $tmpCorrectedTPP+$corrected_tpp;
                                    $tmpReject = $tmpReject+$product['rejections'];
        
                                }
                            }
        
                            $TotalCTPP_NICT =$TotalCTPP_NICT+$tmpCorrectedTPP_NICT;
                            $TotalCTPP =$TotalCTPP+$tmpCorrectedTPP;
                            $TotalReject = $TotalReject+$tmpReject; 
                        }
                        
                    }
    
                    //Machine Wise Performance ......
                    $tp=floatval($All)-(floatval($down['Planned'])+floatval($down['Unplanned'])+floatval($down['Machine_OFF']));
                    $performance=0;
                    
                    if (floatval($tp)>0) {
                        $performance = floatval($TotalCTPP_NICT)/floatval($tp);
                    }
                    else{
                        $performance=0;
                    }
                    
                    //Machine Wise Quality ......
                    if ($TotalCTPP <=0) {
                        $quality = 0;
                    }
                    else{
                        $quality = floatval(((floatval($TotalCTPP) - floatval($TotalReject))/floatval($TotalCTPP)));
                    }
    
                    //Machine Wise Availability ......
                    if (floatval($All-(floatval($down['Planned'])+floatval($down['Machine_OFF']))) >0) {
                        $availability = floatval($All-(floatval($down['Planned'])+floatval($down['Unplanned'])+floatval($down['Machine_OFF'])))/($All-(floatval($down['Planned'])+floatval($down['Machine_OFF'])));
                    }
                    else{
                        $availability=0;
                    }
    
                    // Machine Wise Availability TEEP.......
                    if (floatval($All) >0) {
                        $availTEEP = (($All-($down['Planned']+$down['Unplanned']+$down['Machine_OFF']))/($All));
                    }else{
                        $availTEEP=0;
                    }
                    // Machine Wise Availability OOE.....
                    if (floatval($All-$down['Machine_OFF'])>0) {
                        $availOOE = (($All-($down['Planned']+$down['Unplanned']+$down['Machine_OFF']))/($All-$down['Machine_OFF']));
                    }
                    else{
                        $availOOE=0;
                    }
                    //Machine Wise OEE .......
                    $oee = number_format(($performance*$quality*$availability),2);
    
                    // Machine Wise TEEP.....
                    $teep = number_format(($performance*$quality*$availTEEP),2);
                    // Machine Wise OOE.....
                    $ooe = number_format(($performance*$quality*$availOOE),2);
    
                    //Store Machine wise Data......
                    $tmp = array("Machine_Id"=>$down['Machine_ID'],"Availability"=>$availability*100,"Performance"=>$performance*100,"Quality"=>$quality*100,"Availability_TEEP"=>$availTEEP*100,"Availability_OOE"=>$availOOE*100,"OEE"=>$oee*100,"TEEP"=>$teep*100,"OOE"=>$ooe*100);
                    array_push($MachineWiseData, $tmp);
                }
            }
            
        }
        $Overall = $this->calculateOverallOEE($MachineWiseData);
       
        if ($ref == "ooe") {
            $result_data = $Overall['Overall_OOE'];
        }
        else if($ref == "oee"){
            $result_data = $Overall['Overall_OEE'];
        }
        else if($ref == "teep"){
            $result_data = $Overall['Overall_TEEP'];
        }
        return $result_data;
    }

    // unplanned machine off duration calculation function
    // public function unplanned_machineoff_calculation($from_data,$to_date,$machine_arr,$part_arr){
    //     // return $tmp_data;
    //     $event_data = $this->getDataRaw("unplanned_machineoff",$from_data,$to_date);
    //     // return $event_data;
    //     $total_minutes = 0;
    //     $total_seconds = 0;
    //     $count = 0;
    //     // $demo_arr = [];
    //     foreach ($event_data as $key => $value) {
    //         if (in_array($value['machine_id'],$machine_arr)) {
    //             if (in_array($value['part_id'],$part_arr)) {
    //                 // $tmp_event = implode(" ",$value['event']);
    //                 if ($value['event'] == "Machine OFF") {
    //                     // array_push($demo_arr,$event_data[$key]);
    //                     $duration_arr = explode(".",$value['duration']);
    //                     if (sizeof($duration_arr)>1) {
    //                        $total_minutes = $total_minutes + $duration_arr[0];
    //                        $total_seconds = $total_seconds + $duration_arr[1]; 
    //                     }else{
    //                         $total_seconds = $total_seconds + $duration_arr[0];
    //                     }
    //                 }
    //             }
    //         }
    //     }


    //     // $tmp_dm_data['data'] = $demo_arr;
    //     // $tmp_dm_data['total_minutes'] = $total_minutes;
    //     // $tmp_dm_data['total_seconds'] = $total_seconds;
    //     // first seconds convert minutes after sume the total minutes and seconds conversion minutes
    //     $total_minutes_convertion = ($total_minutes + ($total_seconds/60));
    //     // minutes to hourly connection
    //     $hourly_result = (float)($total_minutes_convertion/60);
    //     return $hourly_result;

    // }


    // main function its the result function
    public function get_final_result($temp){

       
        $fdate_time = $temp['from_time'];
        $tdate_time = $temp['to_time'];
        $tmp_machine_arr = explode(",",$temp['machine_arr']);
        $tmp_part_arr = explode(",",$temp['part_arr']);
        $res = $temp['res'];
        
        $final_result = "";
        

        switch ($res) {
            case 'planned_downtime':
                $hourly_res = $this->split_downtime("planned_downtime",$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr); 
                $final_result = $hourly_res;
                break;
            case 'unplanned_downtime':
                $hourly_resunp = $this->split_downtime("unplanned_downtime",$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr); 
                $final_result = $hourly_resunp;
                break;

            case 'planned_machine_off':
                $hourly_respm = $this->split_downtime("planned_machine_off",$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr); 
                $final_result = $hourly_respm;
                break;

            // case 'unplanned_machine_off':
            //     $hourly_unm = $this->unplanned_machineoff_calculation($fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
            //     $final_result = $hourly_unm;
            //     break;
            
            case 'total_unnamed_hour':
                $hourly_unnamed = $this->find_total_unnamed("total_unnamed_hour",$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
                $final_result = $hourly_unnamed;
                break;

            case 'total_unnamed_count':
                $count_unnamed = $this->find_total_unnamed("total_unnamed_count",$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
                $final_result = $count_unnamed;
                break;

            case 'total_downtime':
                $hourly_tdt = $this->split_downtime("total_downtime",$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr); 
                $final_result = $total_downtime;
                break;

            case 'total_rejection':
               $get_total_sum = $this->find_total_rejection($fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
               $final_result = $get_total_sum;
                break;
            
            case 'oee':
                $get_oee_data = $this->split_overall_graph('oee',$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
                $final_result = $get_oee_data;
                break;

            case 'ooe':
                $get_oee_data = $this->split_overall_graph('ooe',$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
                $final_result = $get_oee_data;
                break;

            case 'teep':
                $get_oee_data = $this->split_overall_graph('teep',$fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
                $final_result = $get_oee_data;
                break;
        }


        return $final_result;

    }



}




// automation
$dbcon['servername'] = "localhost";
$dbcon['username'] = "root";
$dbcon['password'] = "quantanics123";
$dbcon['site_id'] = $_GET['site_id'];
$obj = new Api_handling($dbcon);
$temp['from_time'] = $_GET['from_time'];
$temp['to_time'] = $_GET['to_time'];
$temp['res'] = $_GET['res'];
$temp['machine_arr'] = $_GET['machine_arr'];
$temp['part_arr'] = $_GET['part_arr'];

echo  json_encode($obj->get_final_result($temp));







// manual runing code
/*
$dbcon['servername'] = "localhost";
$dbcon['username'] = "root";
$dbcon['password'] = "quantanics123";
$dbcon['site_id'] = "S1002";
$machine_arr_tmp = "all,MC1001,MC1002,MC1003,MC1004,MC1005,MC1006";
$part_arr_tmp = "all,PT1001,PT1002,PT1003,PT1004,PT1005,PT1006,PT1007,PT1008,PT1009,PT1010,PT1011,PT1012,PT1013,PT1014,PT1015,PT1016,PT1017,PT1018,PT1019,PT1020,PT1021,PT1022,PT1023,PT1024,PT1025,PT1026,PT1027,PT1028,PT1029,PT1030,PT1031,PT1032,PT1033,PT1034,PT1035,PT1036,PT1037,PT1038,PT1039,PT1040,PT1041,PT1042,PT1043,PT1044,PT1045,PT1046,PT1047,PT1048,PT1049,PT1050,PT1051,PT1052,PT1053,PT1054,PT1055,PT1056,PT1057,PT1058,PT1059,PT1060,PT1061,PT1062,PT1063,PT1064,PT1065,PT1066,PT1067,PT1068,PT1069,PT1070";
$machine_arr = explode(",",$machine_arr_tmp);
$part_arr = explode(",",$part_arr_tmp);
$obj = new Api_handling($dbcon);
$temp['machine_arr'] = $machine_arr_tmp;
$temp['part_arr'] = $part_arr_tmp;
$temp['from_time'] = "2023-11-21T06:00:00";
$temp['to_time'] = "2023-11-27T06:00:00";
$temp['res'] = "total_rejection";
$realdata = $obj->get_final_result($temp);
echo "<pre>";
print_r($realdata);
*/



?>