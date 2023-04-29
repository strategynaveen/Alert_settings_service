<?php 

include "db_records.php";

class Api_handling{

    public $con;
    public $data;

    // constructor function
    public function __construct($gname){
        $server_name = "localhost";
        $user_name = "root";
        $password = "";
        $db_name = $gname;
        $this->con = mysqli_connect($server_name, $user_name, $password,$db_name);

        // Check connection
        if ($this->con->connect_error) {
            die("Connection failed: " . $this->con->connect_error);
        }

        $this->data = new db_records($gname);
        
    }


    public function getrecords(){

                
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
        $res['data'] = $this->func1();
        $res['data1'] = $normal_arr;
        return $res;
    }


    public function getMachineWiseOEE($from_date,$to_date){

        // $ref = "MachinewiseOEE";
        $ref="planned_unplanned_machine_off";
       
        $fromTime = $from_date;
        $toTime = $to_date;
        // return $fromTime." ".$toTime;

        //Machine Wise Calculated Data...........
        $MachinewiseData = $this->getDataRaw($ref,$fromTime,$toTime);

        // return $MachinewiseData;
        // Machine Name and ID Reference............
        $MachineName = $this->data->getMachineRecGraph();
        
        // Machine Id Conversion as per the Machine data.......
        // Need Not to change.........
        // $MachineName = $this->convertMachineId($MachineName);
        // General Settings Targets......
        $Targets =  $this->data->getGoalsFinancialData();

        $Availability= [];
        $Quality =[];
        $Performance =[];
        $MachineNameRef =[];
        $OEE=[];
        $AvailabilityTarget= [];
        $QualityTarget= [];
        $PerformanceTarget =[];
        $OEETarget=[];
        return $MachinewiseData;
        // foreach ($MachinewiseData as $key=>$value) {
        //     foreach ($MachineName as $name) {
        //         if ($name['machine_id'] == $value['Machine_Id']) {
        //             // array_push($MachineNameRef, $name['machine_name']);
        //             // array_push($Availability, ($value['Availability']));
        //             // array_push($Quality, ($value['Quality']));
        //             // array_push($Performance, ($value['Performance']));
        //             // array_push($OEE, ($value['OEE']));

        //             // array_push($AvailabilityTarget, $Targets[0]['availability']);
        //             // array_push($QualityTarget, $Targets[0]['quality']);
        //             // array_push($PerformanceTarget, $Targets[0]['performance']);
        //             // array_push($OEETarget, $Targets[0]['oee_target']);

        //             $MachinewiseData[$key]['machine_name'] = $name['machine_name'];
        //             $MachinewiseData[$key]['availability_target'] = $Targets[0]['availability'];
        //             $MachinewiseData[$key]['quality_target'] = $Targets[0]['quality'];
        //             $MachinewiseData[$key]['performance'] = $Targets[0]['performance'];
        //             $MachinewiseData[$key]['oee_target'] = $Targets[0]['oee_target'];

        //         }
        //     }
        // }

        // $graphData['Availability'] = $Availability;
        // $graphData['Quality'] = $Quality;
        // $graphData['Performance'] = $Performance;
        // $graphData['OEE'] = $OEE;
        // $graphData['MachineName'] = $MachineNameRef;
        // $graphData['AvailabilityTarget'] = $AvailabilityTarget;
        // $graphData['QualityTarget'] = $QualityTarget;
        // $graphData['PerformanceTarget'] = $PerformanceTarget;
        // $graphData['OEETarget'] = $OEETarget;

        // // $out = $this->selectionSortOEE($graphData,sizeof($graphData['OEE']));
        // return $MachinewiseData;
    }
    
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
        // return $output;
    
        // Data from PDM Events table for find the All Time Duration...........
        $getAllTimeValues = $this->data->getDataRawAll($FromDate,$ToDate);

        $getOfflineId = $this->data->getOfflineEventId($FromDate,$FromTime,$ToDate,$ToTime);
        // return $getOfflineId;
        // Get the Machine Record.............
        $machine = $this->data->getMachineRecActive($FromDate,$ToDate);

        //Part list Details from Production Info Table between the given from and To durations......
        $part = $this->data->getPartRec($FromDate,$ToDate);

        //Production Data for PDM_Production_Info Table......
        $production = $this->data->getProductionRec($FromDate,$ToDate);
        // return $production;
        // Get the Inactive(Current) Data.............
        $getInactiveMachine = $this->data->getInactiveMachineData();

        // Date Filte for PDM Reason Mapping Data........
        $len_id = sizeof($getOfflineId);
        // return $output;
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
                    if ($value['shift_date'] == $FromDate && $value['start_time'] <= $FromTime && $value['end_time'] >= $FromTime) {
                        $output[$key]['start_time'] = $FromTime;
                        if ($value['end_time']>= $ToTime) {
                            $output[$key]['end_time'] = $ToTime;
                        }
                        $output[$key]['split_duration'] = $this->getDuration($value['calendar_date']." ".$output[$key]['start_time'],$value['calendar_date']." ".$output[$key]['end_time']);
                    }
                    else if (($value['shift_date'] == $ToDate && $value['start_time']>=$value['end_time']) || $value['shift_date'] == $ToDate && $value['end_time'] >= $ToTime) {
                        $output[$key]['end_time'] = $ToTime;
                        $output[$key]['split_duration'] = $this->getDuration($value['calendar_date']." ".$output[$key]['start_time'],$value['calendar_date']." ".$output[$key]['end_time']);
                    }
                    else{
                        if ($value['shift_date'] == $FromDate  && strtotime($value['start_time']) < strtotime($FromTime)){
                            unset($output[$key]);
                        }
                        if ($value['shift_date'] == $FromDate  && $value['start_time'] >= $ToTime){
                            unset($output[$key]);
                        }

                        if ($value['shift_date'] == $ToDate  && strtotime($value['start_time']) > strtotime($ToTime)) {
                            unset($output[$key]);
                        }
                    }

                    //For remove the current data of inactive machines.........
                    foreach ($getInactiveMachine as $v) {
                        $t = explode(" ", $v['max(r.last_updated_on)']);

                        if ($value['shift_date'] >= $t[0]  && $value['start_time'] > $t[1] && $value['machine_id'] == $v['machine_id']){
                            unset($output[$key]);
                        }
                    }
                }
            }
        }
        // return $output;
        // Filter for Find the All Time.............
        foreach ($getAllTimeValues as $key => $value) {
            if ($value['duration']<0) {
                unset($getAllTimeValues[$key]);
            }
            else{
                if ($value['shift_date'] == $FromDate && $value['start_time'] <= $FromTime && $value['end_time'] >= $FromTime) {
                    $getAllTimeValues[$key]['start_time'] = $FromTime;
                    if ($value['end_time']>= $ToTime) {
                        $getAllTimeValues[$key]['end_time'] = $ToTime;
                    }
                    $getAllTimeValues[$key]['duration'] = $this->getDuration($value['calendar_date']." ".$getAllTimeValues[$key]['start_time'],$value['calendar_date']." ".$getAllTimeValues[$key]['end_time']);
                }
                else if (($value['shift_date'] == $ToDate && $value['start_time']>=$value['end_time']) || $value['shift_date'] == $ToDate && $value['end_time'] >= $ToTime) {
                    $getAllTimeValues[$key]['end_time'] = $ToTime;
                    $getAllTimeValues[$key]['duration'] = $this->getDuration($value['calendar_date']." ".$getAllTimeValues[$key]['start_time'],$value['calendar_date']." ".$getAllTimeValues[$key]['end_time']);
                }
                else{
                    if ($value['shift_date'] == $FromDate  && $value['start_time'] < $FromTime){
                        unset($getAllTimeValues[$key]);
                    }
                    if ($value['shift_date'] == $ToDate  && strtotime($value['end_time']) > strtotime($ToTime)) {
                        unset($getAllTimeValues[$key]);
                    }

                    if ($value['shift_date'] == $FromDate  && $value['start_time'] >= $ToTime){
                        unset($getAllTimeValues[$key]);
                    }
                }

                //For remove the current data of inactive machines.........
                foreach ($getInactiveMachine as $v) {
                    $t = explode(" ", $v['max(r.last_updated_on)']);

                    if ($value['shift_date'] >= $t[0]  && $value['start_time'] > $t[1] && $value['machine_id'] == $v['machine_id']){
                        unset($getAllTimeValues[$key]);
                    }
                }
            }
        }   

        // Filter for Production Info Table Data..........
        foreach ($production as $key => $value) {   
            if ($value['shift_date'] == $FromDate  && $value['start_time'] < $FromTime) {
                unset($production[$key]);
            }
            if ($value['shift_date'] == $FromDate  && $value['start_time'] >= $ToTime){
                    unset($production[$key]);
                }

            if (strtotime($value['shift_date']) == strtotime($ToDate)  && ($value['start_time']) >= ($ToTime)) {
                unset($production[$key]);
            }
            //For remove the current data of inactive machines.........
            foreach ($getInactiveMachine as $v) {
                $t = explode(" ", $v['max(r.last_updated_on)']);
                if ($value['shift_date'] == $t[0]  && $value['start_time'] > $t[1] && $value['machine_id'] == $v['machine_id'] OR $value['shift_date'] > $t[0] && $value['machine_id'] == $v['machine_id']){
                    unset($production[$key]);
                }
            }
        }

        //Downtime reasons data ordering.....
        // return $output;
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

        if($graphRef == "OpportunityTrendDay"){         
            $res['raw'] = $MachineWiseDataRaw;
            $res['machine'] = $machine;
            $res['part'] = $part;
            $res['downtimeTime']=$allTimeValuesDay;
            return $res;
        }
        
        //Part Details.....
        $partsDetails = $this->data->settings_tools(); 
        
        //Downtime data has been calculated......
        // To find Planned Downtime, Unplanned Downtime, Machine OFF Downtime.........
        //return $getAllTimeValues;
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
        if ($graphRef == "planned_unplanned_machine_off") {
            return $downtime;
        }

        //Machine wise Performance,Quality,Availability........

        $MachineWiseData = [];
        // return $downtime;
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
                $tmp_demo_data = [];
                foreach ($part as $p) {
                    $tmpCorrectedTPP_NICT = 0;
                    $tmpCorrectedTPP = 0;
                    $tmpReject = 0;
                    $part_tmp_arr = [];
                    foreach ($production as $product) {
                        if ($product['machine_id'] == $down['Machine_ID'] && $p['part_id'] == $product['part_id']) {
                            //To find NICT.....
                            $NICT = 0;

                            foreach ($partsDetails as $partVal) {
                                if ($p['part_id'] == $partVal->part_id) {
                                    $mnict = explode(".", $partVal->NICT);
                                    if (sizeof($mnict)>1) {
                                        $NICT = (($mnict[0]/60)+($mnict[1]/1000));
                                    }else{
                                        $NICT = ($mnict[0]/60);
                                    }
                                }
                            }

                            $corrected_tpp = (int)$product['production']+(int)($product['corrections']);
                            $CorrectedTPP_NICT = $NICT*$corrected_tpp;
                            // For Find Performance.....
                            $tmpCorrectedTPP_NICT = $tmpCorrectedTPP_NICT+$CorrectedTPP_NICT;

                            //For Find Quality.......
                            $tmpCorrectedTPP = $tmpCorrectedTPP+$corrected_tpp;
                            $tmpReject = $tmpReject+$product['rejections'];
                           
                            $tmp_data['machine_id'] = $product['machine_id'];
                            $tmp_data['part_id'] = $product['part_id'];
                            // return $tmp_data;
                            array_push($part_tmp_arr,$tmp_data);
                        }
                    }
                    // return $part_tmp_arr;
                    $TotalCTPP_NICT =$TotalCTPP_NICT+$tmpCorrectedTPP_NICT;
                    $TotalCTPP =$TotalCTPP+$tmpCorrectedTPP;
                    $TotalReject = $TotalReject+$tmpReject;
                   // $tmp_demo_data = $part_tmp_arr;
                }

                array_push($tmp_demo_data,$part_tmp_arr);
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
                $tmp = array("Machine_Id"=>$down['Machine_ID'],"Availability"=>$availability*100,"Performance"=>$performance*100,"Quality"=>$quality*100,"Availability_TEEP"=>$availTEEP*100,"Availability_OOE"=>$availOOE*100,"OEE"=>$oee*100,"TEEP"=>$teep*100,"OOE"=>$ooe*100,"part_data"=>$tmp_demo_data);
                array_push($MachineWiseData, $tmp);
            }
        }

        if ($graphRef == "MachinewiseOEE") {
            return $MachineWiseData;
        }
        if ($graphRef == "ReasonwiseMachine") {
            return $downtime;
        }

        $Overall = $this->calculateOverallOEE($MachineWiseData);
        
        if ($graphRef == "Overall") {
            return $MachineWiseData;
        }
    }



    // get oee raw data function
    public function getDataRaw_oee($graphRef,$fromTime=null,$toTime=null,$machine_arr,$part_arr){
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
        // return $output;
    
        // Data from PDM Events table for find the All Time Duration...........
        $getAllTimeValues = $this->data->getDataRawAll($FromDate,$ToDate);

        $getOfflineId = $this->data->getOfflineEventId($FromDate,$FromTime,$ToDate,$ToTime);
        // return $getOfflineId;
        // Get the Machine Record.............
        $machine = $this->data->getMachineRecActive($FromDate,$ToDate);

        //Part list Details from Production Info Table between the given from and To durations......
        $part = $this->data->getPartRec($FromDate,$ToDate);

        //Production Data for PDM_Production_Info Table......
        $production = $this->data->getProductionRec($FromDate,$ToDate);
        // return $production;
        // Get the Inactive(Current) Data.............
        $getInactiveMachine = $this->data->getInactiveMachineData();

        // Date Filte for PDM Reason Mapping Data........
        $len_id = sizeof($getOfflineId);
        // return $output;
        // remove offline records in pdm reason mapping
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
                    if ($value['shift_date'] == $FromDate && $value['start_time'] <= $FromTime && $value['end_time'] >= $FromTime) {
                        $output[$key]['start_time'] = $FromTime;
                        if ($value['end_time']>= $ToTime) {
                            $output[$key]['end_time'] = $ToTime;
                        }
                        $output[$key]['split_duration'] = $this->getDuration($value['calendar_date']." ".$output[$key]['start_time'],$value['calendar_date']." ".$output[$key]['end_time']);
                    }
                    else if (($value['shift_date'] == $ToDate && $value['start_time']>=$value['end_time']) || $value['shift_date'] == $ToDate && $value['end_time'] >= $ToTime) {
                        $output[$key]['end_time'] = $ToTime;
                        $output[$key]['split_duration'] = $this->getDuration($value['calendar_date']." ".$output[$key]['start_time'],$value['calendar_date']." ".$output[$key]['end_time']);
                    }
                    else{
                        if ($value['shift_date'] == $FromDate  && strtotime($value['start_time']) < strtotime($FromTime)){
                            unset($output[$key]);
                        }
                        if ($value['shift_date'] == $FromDate  && $value['start_time'] >= $ToTime){
                            unset($output[$key]);
                        }

                        if ($value['shift_date'] == $ToDate  && strtotime($value['start_time']) > strtotime($ToTime)) {
                            unset($output[$key]);
                        }
                    }

                    //For remove the current data of inactive machines.........
                    foreach ($getInactiveMachine as $v) {
                        $t = explode(" ", $v['max(r.last_updated_on)']);

                        if ($value['shift_date'] >= $t[0]  && $value['start_time'] > $t[1] && $value['machine_id'] == $v['machine_id']){
                            unset($output[$key]);
                        }
                    }
                }
            }
        }
        // return $output;
        // Filter for Find the All Time.............
        foreach ($getAllTimeValues as $key => $value) {
            if ($value['duration']<0) {
                unset($getAllTimeValues[$key]);
            }
            else{
                if ($value['shift_date'] == $FromDate && $value['start_time'] <= $FromTime && $value['end_time'] >= $FromTime) {
                    $getAllTimeValues[$key]['start_time'] = $FromTime;
                    if ($value['end_time']>= $ToTime) {
                        $getAllTimeValues[$key]['end_time'] = $ToTime;
                    }
                    $getAllTimeValues[$key]['duration'] = $this->getDuration($value['calendar_date']." ".$getAllTimeValues[$key]['start_time'],$value['calendar_date']." ".$getAllTimeValues[$key]['end_time']);
                }
                else if (($value['shift_date'] == $ToDate && $value['start_time']>=$value['end_time']) || $value['shift_date'] == $ToDate && $value['end_time'] >= $ToTime) {
                    $getAllTimeValues[$key]['end_time'] = $ToTime;
                    $getAllTimeValues[$key]['duration'] = $this->getDuration($value['calendar_date']." ".$getAllTimeValues[$key]['start_time'],$value['calendar_date']." ".$getAllTimeValues[$key]['end_time']);
                }
                else{
                    if ($value['shift_date'] == $FromDate  && $value['start_time'] < $FromTime){
                        unset($getAllTimeValues[$key]);
                    }
                    if ($value['shift_date'] == $ToDate  && strtotime($value['end_time']) > strtotime($ToTime)) {
                        unset($getAllTimeValues[$key]);
                    }

                    if ($value['shift_date'] == $FromDate  && $value['start_time'] >= $ToTime){
                        unset($getAllTimeValues[$key]);
                    }
                }

                //For remove the current data of inactive machines.........
                foreach ($getInactiveMachine as $v) {
                    $t = explode(" ", $v['max(r.last_updated_on)']);

                    if ($value['shift_date'] >= $t[0]  && $value['start_time'] > $t[1] && $value['machine_id'] == $v['machine_id']){
                        unset($getAllTimeValues[$key]);
                    }
                }
            }
        }   

        // Filter for Production Info Table Data..........
        // prodcution data time filter
        foreach ($production as $key => $value) {   
            if ($value['shift_date'] == $FromDate  && $value['start_time'] < $FromTime) {
                unset($production[$key]);
            }
            if ($value['shift_date'] == $FromDate  && $value['start_time'] >= $ToTime){
                    unset($production[$key]);
                }

            if (strtotime($value['shift_date']) == strtotime($ToDate)  && ($value['start_time']) >= ($ToTime)) {
                unset($production[$key]);
            }
            //For remove the current data of inactive machines.........
            foreach ($getInactiveMachine as $v) {
                $t = explode(" ", $v['max(r.last_updated_on)']);
                if ($value['shift_date'] == $t[0]  && $value['start_time'] > $t[1] && $value['machine_id'] == $v['machine_id'] OR $value['shift_date'] > $t[0] && $value['machine_id'] == $v['machine_id']){
                    unset($production[$key]);
                }
            }
        }

        //Downtime reasons data ordering.....
        // return $output;
        $MachineWiseDataRaw = $this->storeData($output,$machine,$part);

        // Machine-Wise Downtime........
        $allTimeValues = $this->allTimeFound($getAllTimeValues,$machine,$part,$FromDate,$ToDate);

        // Day-wise With Machine-Wise Downtime....
        $allTimeValuesDay = $this->allTimeFoundDay($getAllTimeValues,$machine,$part,$FromDate,$ToDate);

        //Function return for qualityOpportunity graph........
        // if ($graphRef == "qualityOpportunity") {
        //     return $production;
        // } 

        // if ($graphRef  == "AvailabilityReasonWise") {
        //     return $output;
        // }

        // if($graphRef == "OpportunityTrendDay"){         
        //     $res['raw'] = $MachineWiseDataRaw;
        //     $res['machine'] = $machine;
        //     $res['part'] = $part;
        //     $res['downtimeTime']=$allTimeValuesDay;
        //     return $res;
        // }
        
        //Part Details.....
        $partsDetails = $this->data->settings_tools(); 
        
        //Downtime data has been calculated......
        // To find Planned Downtime, Unplanned Downtime, Machine OFF Downtime.........
        //return $getAllTimeValues;
        if ($graphRef == "PLOpportunity") {
            $downtime = $this->oeeData($MachineWiseDataRaw,$getAllTimeValues,true);
        }else{
            $downtime = $this->oeeData($MachineWiseDataRaw,$getAllTimeValues);
        }

        // if ($graphRef == "PartPLOpportunity") {
        //     $res['production'] = $production;
        //     $res['downtime'] = $downtime;
        //     return $res;
        // }
        
        // //Function return for performanceOpportunity graph........
        // if ($graphRef == "PerformanceOpportunity") {
        //     $res['production'] = $production;
        //     $res['downtime'] = $downtime;
        //     $res['machineData'] = $MachineWiseDataRaw;
        //     $res['all']=$allTimeValues;
        //     return $res;
        // }

        //Function return for Profit and Loss Opportunity..........
        // if ($graphRef == "PLOpportunity") {
        //     return $downtime;
        // }
        // if ($graphRef == "planned_unplanned_machine_off") {
        //     return $downtime;
        // }

        //Machine wise Performance,Quality,Availability........

        $MachineWiseData = [];
            // return $downtime;
        foreach ($downtime as $down) {
            $PlannedDownTime = $down['Planned'];
            $UnplannedDownTime = $down['Unplanned'];
            $MachineOFFDownTime = $down['Machine_OFF'];
            $All = 0;
            if (in_array($down['Machine_ID'],$machine_arr)) {
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
                    $tmp_demo_data = [];
                    foreach ($part as $p) {
                        if (in_array($p['part_id'],$part_arr)) {
                                               
                            $tmpCorrectedTPP_NICT = 0;
                            $tmpCorrectedTPP = 0;
                            $tmpReject = 0;
                            $part_tmp_arr = [];

                            foreach ($production as $product) {
                                if ($product['machine_id'] == $down['Machine_ID'] && $p['part_id'] == $product['part_id']) {
                                    //To find NICT.....
                                    $NICT = 0;

                                    foreach ($partsDetails as $partVal) {
                                        if ($p['part_id'] == $partVal->part_id) {
                                            $mnict = explode(".", $partVal->NICT);
                                            if (sizeof($mnict)>1) {
                                                $NICT = (($mnict[0]/60)+($mnict[1]/1000));
                                            }else{
                                                $NICT = ($mnict[0]/60);
                                            }
                                        }
                                    }

                                    $corrected_tpp = (int)$product['production']+(int)($product['corrections']);
                                    $CorrectedTPP_NICT = $NICT*$corrected_tpp;
                                    // For Find Performance.....
                                    $tmpCorrectedTPP_NICT = $tmpCorrectedTPP_NICT+$CorrectedTPP_NICT;

                                    //For Find Quality.......
                                    $tmpCorrectedTPP = $tmpCorrectedTPP+$corrected_tpp;
                                    $tmpReject = $tmpReject+$product['rejections'];
                                
                                    $tmp_data['machine_id'] = $product['machine_id'];
                                    $tmp_data['part_id'] = $product['part_id'];
                                    // return $tmp_data;
                                    array_push($part_tmp_arr,$tmp_data);
                                }
                            }
                            // return $part_tmp_arr;
                            $TotalCTPP_NICT =$TotalCTPP_NICT+$tmpCorrectedTPP_NICT;
                            $TotalCTPP =$TotalCTPP+$tmpCorrectedTPP;
                            $TotalReject = $TotalReject+$tmpReject;
                            // $tmp_demo_data = $part_tmp_arr;
                        }
                    }

                    array_push($tmp_demo_data,$part_tmp_arr);
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
                    $tmp = array("Machine_Id"=>$down['Machine_ID'],"Availability"=>$availability*100,"Performance"=>$performance*100,"Quality"=>$quality*100,"Availability_TEEP"=>$availTEEP*100,"Availability_OOE"=>$availOOE*100,"OEE"=>$oee*100,"TEEP"=>$teep*100,"OOE"=>$ooe*100,"part_data"=>$tmp_demo_data);
                    array_push($MachineWiseData, $tmp);
                }
            }
        }

        if ($graphRef == "MachinewiseOEE") {
            return $MachineWiseData;
        }
        if ($graphRef == "ReasonwiseMachine") {
            return $downtime;
        }

        $Overall = $this->calculateOverallOEE($MachineWiseData);
        
        if ($graphRef == "Overall") {
            return $Overall;
        }
    }
    // duration function
    public function getDuration($f,$t){
        $from_time = strtotime($f); 
        $to_time = strtotime($t); 
        $diff_minutes = (int)(abs($from_time - $to_time) / 60);
        $diff_sec = abs($from_time - $to_time) % 60;
        $duration = $diff_minutes.".".$diff_sec;
        return $duration;
    }

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
            if ((int)$tempCalc>0) {
               $tmpDown = array("Machine_ID"=>$MachineId,"Planned"=>$PlannedDown,"Unplanned"=>$UnplannedDown,"Machine_OFF"=>$MachineOFFDown,"All"=>$tempCalc,"Part_Wise"=>$PartWiseDowntime);
                array_push($DowntimeTimeData, $tmpDown);
            }
        }
        return $DowntimeTimeData;
    }

    
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


    // sorting
    public function selectionSortOEE($arr, $n)
    {
      
        // One by one move boundary of unsorted subarray
        for ($i = 0; $i < $n-1; $i++)
        {
            // Find the minimum element in unsorted array
            $min_idx = $i;
            for ($j = $i+1; $j < $n; $j++){
                if ($arr['OEE'][$j] < $arr['OEE'][$min_idx]){
                    $min_idx = $j;
                }
            }

            $temp = $arr['OEE'][$i];
            $arr['OEE'][$i] = $arr['OEE'][$min_idx];
            $arr['OEE'][$min_idx] = $temp;


            $temp1 = $arr['MachineName'][$i];
            $arr['MachineName'][$i] = $arr['MachineName'][$min_idx];
            $arr['MachineName'][$min_idx] = $temp1;

            //Availability
            $temp1 = $arr['Availability'][$i];
            $arr['Availability'][$i] = $arr['Availability'][$min_idx];
            $arr['Availability'][$min_idx] = $temp1;
            //Performance
            $temp1 = $arr['Quality'][$i];
            $arr['Quality'][$i] = $arr['Quality'][$min_idx];
            $arr['Quality'][$min_idx] = $temp1;
            //Quality
            $temp1 = $arr['Performance'][$i];
            $arr['Performance'][$i] = $arr['Performance'][$min_idx];
            $arr['Performance'][$min_idx] = $temp1;
            
        }

       
        return $arr;
    }

    // this function get only active machine records
    public function getactive_machine_data(){
        $res = $this->data->getactive_machine_data();

        return $res;
    }

    // this function for time filter function
    public function time_filter_records($res,$from_time,$to_time){
        $ftime = date($from_time);
        $ttime = date($to_time);
        $demo_arr = [];
        foreach ($res as $key => $value) {
            $tmpf = $value['shift_date'].'T'.$value['start_time'];
            $tmpt = $value['shift_date'].'T'.$value['end_time'];
            $tmp_ftime = date($tmpf);
            $tmp_ttime = date($tmpt);
            if(($tmp_ftime>=$ftime) && ($tmp_ttime<=$ttime)){
                array_push($demo_arr, $res[$key]);
            }
        }

        return $demo_arr;
    }

    // this function get planned and unplanned reason mapping id and records
    public function getmachine_off_reason_mapping($ref,$from_time,$to_time,$machine_arr){
        $res = $this->data->getmachine_off_records($ref,$from_time,$to_time);

       
        $reason_part_expand_arr = [];
        foreach ($res as $key => $value) {
            if (in_array($value['machine_id'], $machine_arr)) {
                $reason['machine_id'] = $value['machine_id'];

                $tmp_part_arr = explode(",", $value['part_id']);
                $tmp_total_duration = explode(".",$value['split_duration']);
                $tmp_second = $tmp_total_duration[0]*60;
                if(count($tmp_total_duration)>1){
                    $tmp_total_Second =  $tmp_second + $tmp_total_duration[1];
                }else{
                    $tmp_total_Second = $tmp_second;
                }
               
                $splited_duration = $tmp_total_Second / count($tmp_part_arr);
                $tmp_arr = [];
                foreach ($tmp_part_arr as $k1 => $val) {
                    $tmp_arr1['part_id'] = $val;
                    $tmp_arr1['duration'] = $splited_duration;
                    array_push($tmp_arr,$tmp_arr1);
                }

                $reason['parts_data'] = $tmp_arr;
                $reason['shift_date'] = $value['shift_date'];
                $reason['Shift_id'] = $value['Shift_id'];
                $reason['start_time'] = $value['start_time'];
                $reason['end_time'] = $value['end_time'];
                $reason['downtime_reason_id'] = $value['downtime_reason_id'];
                $reason['split_id'] = $value['split_id'];
                $reason['tool_id'] = $value['tool_id'];
                $reason['total_duration'] = $value['split_duration'];
                array_push($reason_part_expand_arr,$reason);
            }
        }

        return $reason_part_expand_arr;
    }

    // this function get planned machine off function
    public function planned_machine_off($from_time,$to_time,$machine_arr){
        $machine_data = $this->getactive_machine_data();
        $ref = "Planned";
        $from_time_arr = explode("T",$from_time);
        $to_time_arr = explode("T",$to_time);
        $reason_mapping_records = $this->getmachine_off_reason_mapping($ref,$from_time_arr[0],$to_time_arr[0],$machine_arr);

        $res_final = $this->time_filter_records($reason_mapping_records,$from_time,$to_time);
        return $res_final;

    }

    // this function get unplanned machine off function
    public function unplanned_machine_off($from_time,$to_time,$machine_arr){
         $machine_data = $this->getactive_machine_data();
        $ref = "Unplanned";
        $from_time_arr = explode("T",$from_time);
        $to_time_arr = explode("T",$to_time);
        $reason_mapping_records = $this->getmachine_off_reason_mapping($ref,$from_time_arr[0],$to_time_arr[0],$machine_arr);
        $res_final = $this->time_filter_records($reason_mapping_records,$from_time,$to_time);
        return $reason_mapping_records;

    }

    // total unamed function
    public function total_unamed($from_time,$to_time,$machine_arr){
        $from_time_arr = explode("T",$from_time);
        $to_time_arr = explode("T",$to_time);
        $ref="total_unamed";
        // this function belong to the date filter and part expanded data retriveing 
        $result = $this->getmachine_off_reason_mapping($ref,$from_time_arr[0],$to_time_arr[0],$machine_arr);
        // time filter function
        $res_final = $this->time_filter_records($result,$from_time,$to_time);
        return $res_final;
    }

    // total downtime function
    public function total_downtime_metric($from_time,$to_time,$machine_arr){
        $from_time_arr = explode("T",$from_time);
        $to_time_arr = explode("T",$to_time);
        $ref="total_downtime";
        // this function belong to the date filter and part expanded data retriveing 
        $result = $this->getmachine_off_reason_mapping($ref,$from_time_arr[0],$to_time_arr[0],$machine_arr);
        // time filter function
        $res_final = $this->time_filter_records($result,$from_time,$to_time);
        return $res_final;
        // return $result;
    }

    // total rejection function
    public function total_rejection($from_date,$to_date,$machine_arr,$part_arr){
        $from_time_arr = explode("T",$from_date);
        $to_time_arr = explode("T",$to_date);
        $ref="total_rejection";
        // this function belong to the date filter and part expanded data retriveing 
        $result = $this->data->get_total_rejection_production($from_time_arr[0],$to_time_arr[0],$from_time_arr[1],$to_time_arr[1],$machine_arr,$part_arr);
        $total_rejection = 0;
        foreach ($result as $key => $value) {
            $total_rejection = $total_rejection + $value['total_Rejection'];
        }
        return $total_rejection;

    }

    // get all oee data function
    public function get_oee_data($fdate,$tdate,$machine_arr,$part_arr){
        // $tmp['fdate'] = $fdate;
        // $tmp['tdate'] = $tdate;
        // $tmp['machine_arr'] = $machine_arr;
        // $tmp['part_arr'] = $part_arr;
        // return $tmp;
        $ref="Overall";
        $fromTime = $fdate;
        $toTime = $tdate;
      
        $Overall = $this->getDataRaw_oee($ref,$fromTime,$toTime,$machine_arr,$part_arr);
        return $Overall['Overall_OEE'];
    }


    // get all ooe data function
    public function get_ooe_data($fdate,$tdate,$machine_arr,$part_arr){
        $ref="Overall";
        $fromTime = $fdate;
        $toTime = $tdate;
      
        $Overall = $this->getDataRaw_oee($ref,$fromTime,$toTime,$machine_arr,$part_arr);
        return $Overall['Overall_OOE'];
    }

    // get all TEEP function
    public function get_teep_data($fdate,$tdate,$machine_arr,$part_arr){
        $ref="Overall";
        $fromTime = $fdate;
        $toTime = $tdate;
        $Overall = $this->getDataRaw_oee($ref,$fromTime,$toTime,$machine_arr,$part_arr);
        return $Overall['Overall_TEEP'];
    }

    // its getting final result
    public function get_final_result($temp){

        // print_r($temp);
        // planned downtime condition

        $fdate_time = $temp['from_time'];
        $tdate_time = $temp['to_time'];
        $tmp_machine_arr = explode(",",$temp['machine_arr']);
        $tmp_part_arr = explode(",",$temp['part_arr']);
        $res = $temp['res'];
        
        $final_result = "";
        switch ($res) {
            case 'planned_downtime':
                $res = $this->getMachineWiseOEE($fdate_time,$tdate_time);
                // $tmp_arr = [];
                
                $final_arr = [];
                foreach($res as $Key => $value){
                    $demo_arr = [];
                    if (in_array($value['Machine_ID'],$tmp_machine_arr)) {
                        $demo_arr['machine_id'] = $value['Machine_ID'];
                        // $demo_arr['planned_downtime'] = $value['Planned'];
                        $tmp_arr = [];
                        $total_downtime_m = 0;
                        $total_downtime_s = 0;
                        foreach ($value['Part_Wise'] as $k1 => $val) {
                            if (in_array($val['part_id'],$tmp_part_arr)) {
                                $tmp['part_id'] = $val['part_id'];
                                $tmp['planned'] = $val['Planed'];
                                $formatted = sprintf("%0.2f", $val['Planed']);
                                $minute_arr = explode(".",$formatted);
                                if (count($minute_arr)>1) {
                                    $total_downtime_m = $total_downtime_m + $minute_arr[0];
                                    
                                    $total_downtime_s = $total_downtime_s + (int)$minute_arr[1];
                                }
                                else{
                                    $total_downtime_m = $total_downtime_m + $minute_arr[0];
                                }
                               
                                array_push($tmp_arr,$tmp);
                            }
                        }
                        $demo_arr['part_list'] = $tmp_arr;
                        if ($total_downtime_s > 59) {
                            $total_downtime_m = $total_downtime_m + (int) $total_downtime_s/60;
                        }else{
                            $total_downtime_m = $total_downtime_m.'.'.(int) $total_downtime_s;
                        }
                        
                        // $demo_arr['planned_downtime'] = $total_downtime_m;
    
                        array_push($final_arr,$total_downtime_m);
                    }              
                }
                $tmp11['arr'] = $final_arr;
                $tmp11['total_count'] = array_sum($final_arr)/60;
                $final_result =  array_sum($final_arr)/60;
                // $final_result = "planned downtime";
               
                break;
            case 'unplanned_downtime':
                $res = $this->getMachineWiseOEE($fdate_time,$tdate_time); 
                $unplanned_downtime_arr = [];
                foreach($res as $Key => $value){
                    $demo_arr = [];
                    if (in_array($value['Machine_ID'],$tmp_machine_arr)) {
                        $demo_arr['machine_id'] = $value['Machine_ID'];
                        // $demo_arr['planned_downtime'] = $value['Planned'];
                        $tmp_arr = [];
                        $total_downtime_m = 0;
                        $total_downtime_s = 0;
                        foreach ($value['Part_Wise'] as $k1 => $val) {
                            if (in_array($val['part_id'],$tmp_part_arr)) {
                                $tmp['part_id'] = $val['part_id'];
                                $tmp['unplanned'] = $val['Unplanned'];
                                $formatted = sprintf("%0.2f", $val['Unplanned']);
                                $minute_arr = explode(".",$formatted);
                                if (count($minute_arr)>1) {
                                    $total_downtime_m = $total_downtime_m + $minute_arr[0];
                                    
                                    $total_downtime_s = $total_downtime_s + (int)$minute_arr[1];
                                }
                                else{
                                    $total_downtime_m = $total_downtime_m + $minute_arr[0];
                                }
                               
                                array_push($tmp_arr,$tmp);
                            }
                        }
                        $demo_arr['part_list'] = $tmp_arr;
                        if ($total_downtime_s > 59) {
                            $total_downtime_m = $total_downtime_m + (int) $total_downtime_s/60;
                        }else{
                            $total_downtime_m = $total_downtime_m.'.'.(int) $total_downtime_s;
                        }
                        
                        $demo_arr['unplanned_downtime'] = $total_downtime_m;
                        array_push($unplanned_downtime_arr,$total_downtime_m);
                    }              
                }
                $tmp1['unplanned_arr'] = $unplanned_downtime_arr;
                $tmp1['total_Count'] = array_sum($unplanned_downtime_arr)/60;
    
                $final_result = array_sum($unplanned_downtime_arr)/60;
                // $final_result = "unplanned downtime";
                break;

            case 'planned_machine_off':
                $machine_data = $this->getactive_machine_data();
                $res = $this->planned_machine_off($fdate_time,$tdate_time,$tmp_machine_arr); 
            
                $planned_arr = [];
                foreach ($res as $key => $value) {
                    if(in_array($value['machine_id'], $tmp_machine_arr)){
                        $planned['machine_id'] = $value['machine_id'];
                        $planned_part = [];
                        $tmp_total_duration = 0;
                        foreach ($value['parts_data'] as $k1 => $val) {
                            if(in_array($val['part_id'], $tmp_part_arr)){
                                $tmp_total_duration = $tmp_total_duration + $val['duration'];
                                $plan['part_id'] = $val['part_id'];
                                $plan['duration'] = $val['duration'];
                                array_push($planned_part, $plan);
                            }
                        }
                        // seconds to minute convertion
                        $total_duration = $tmp_total_duration/60;
                        $planned['part_data'] = $planned_part;
                        $planned['total_duration'] = $total_duration;
                        
                    }
                    array_push($planned_arr,$total_duration);
                }            
                
                //total duration array
                $tmp1['arr'] = $planned_arr;
                $tmp1['total_duration'] = array_sum($planned_arr)/60;

                $final_result = array_sum($planned_arr)/60;
                // $final_result = "planned machine_off";

                break;

            case 'unplanned_machine_off':
                $res = $this->unplanned_machine_off($fdate_time,$tdate_time,$tmp_machine_arr);

                $unplanned_arr = [];
                foreach ($res as $key => $value) {
                    if(in_array($value['machine_id'], $tmp_machine_arr)){
                        $unplanned['machine_id'] = $value['machine_id'];
                        $unplanned_part = [];
                        $tmp_total_duration = 0;
                        foreach ($value['parts_data'] as $k1 => $val) {
                            if(in_array($val['part_id'], $tmp_part_arr)){
                                $tmp_total_duration = $tmp_total_duration + $val['duration'];
                                $unplan['part_id'] = $val['part_id'];
                                $unplan['duration'] = $val['duration'];
                                array_push($unplanned_part, $unplan);
                            }
                        }
                        // minutes convertion
                        $total_duration = $tmp_total_duration/60;
                        $unplanned['part_data'] = $unplanned_part;
                        $unplanned['total_duration'] = $total_duration;
                    }
                    array_push($unplanned_arr,$total_duration);
                }   
    
                $tmp1['arr'] = $unplanned_arr;
                $tmp1['total_duration'] = array_sum($unplanned_arr)/60;
    
                $final_result = array_sum($unplanned_arr)/60; 
                // $final_result = "unplanned machine off";
                break;
            
            case 'total_unamed':
                $res = $this->total_unamed($fdate_time,$tdate_time,$tmp_machine_arr);
                $unamed_arr = [];
                foreach ($res as $key => $value) {
                    if(in_array($value['machine_id'], $tmp_machine_arr)){
                        $unamed['machine_id'] = $value['machine_id'];
                        $unamed_part = [];
                        $tmp_total_duration = 0;
                        foreach ($value['parts_data'] as $k1 => $val) {
                            if(in_array($val['part_id'], $tmp_part_arr)){
                                $tmp_total_duration = $tmp_total_duration + $val['duration'];
                                $unamed_tmp['part_id'] = $val['part_id'];
                                $unamed_tmp['duration'] = $val['duration'];
                                array_push($unamed_part, $unamed_tmp);
                            }
                        }
                        // minutes convertion
                        $total_duration = $tmp_total_duration/60;
                        $unamed['part_data'] = $unamed_part;
                        $unamed['total_duration'] = $total_duration;
                    }
                    array_push($unamed_arr,$total_duration);
                }   

                $tmp1['arr'] = $unamed_arr;
                $tmp1['total_duration'] = array_sum($unamed_arr)/60;

                $final_result = array_sum($unamed_arr)/60; 
                // $final_result = "total unamed";
                break;

            case 'total_downtime':
                $res = $this->total_downtime_metric($fdate_time,$tdate_time,$tmp_machine_arr);

                $downtime_arr = [];
                foreach ($res as $key => $value) {
                    if(in_array($value['machine_id'], $tmp_machine_arr)){
                        $downtime['machine_id'] = $value['machine_id'];
                        $downtime_part = [];
                        $tmp_total_duration = 0;
                        foreach ($value['parts_data'] as $k1 => $val) {
                            if(in_array($val['part_id'], $tmp_part_arr)){
                                $tmp_total_duration = $tmp_total_duration + $val['duration'];
                                $downtime_tmp['part_id'] = $val['part_id'];
                                $downtime_tmp['duration'] = $val['duration'];
                                array_push($downtime_part, $downtime_tmp);
                            }
                        }
                        // minutes convertion
                        $total_duration = $tmp_total_duration/60;
                        $downtime['part_data'] = $downtime_part;
                        $downtime['total_duration'] = $total_duration;
                    }
                    array_push($downtime_arr,$total_duration);
                }   

                $tmp1['arr'] = $downtime_arr;
                $tmp1['total_duration'] = array_sum($downtime_arr)/60;

                $final_result =  array_sum($downtime_arr)/60; 
                // $final_result = "total downtime";
                break;

            case 'total_rejection':
                $res = $this->total_rejection($fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);

                $final_result = $res;
                // $final_result = "total rejeciton";
                break;
            
            case 'oee':
                $oee_data = $this->get_oee_data($fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);

                $final_result =  $oee_data;
                // $final_result = "overall oee";
                break;

            case 'ooe':
                $ooe_data = $this->get_ooe_data($fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);
                $final_result = $ooe_data;
                // $final_result = "overall ooe";
                break;

            case 'teep':
                $teep_data = $this->get_teep_data($fdate_time,$tdate_time,$tmp_machine_arr,$tmp_part_arr);

                $final_result =  $teep_data;
                // $final_result = "overall teep";
                break;
        }
        return $final_result;


    /*
        if ($temp['res'] == "planned_downtime") {
            $res = $this->getMachineWiseOEE($temp['from_time'],$temp['to_time']); 
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
                $final_arr = [];
                foreach($res as $Key => $value){
                    $demo_arr = [];
                    if (in_array($value['Machine_ID'],$tmp_machine_arr)) {
                        $demo_arr['machine_id'] = $value['Machine_ID'];
                        // $demo_arr['planned_downtime'] = $value['Planned'];
                        $tmp_arr = [];
                        $total_downtime_m = 0;
                        $total_downtime_s = 0;
                        foreach ($value['Part_Wise'] as $k1 => $val) {
                            if (in_array($val['part_id'],$tmp_part_arr)) {
                                $tmp['part_id'] = $val['part_id'];
                                $tmp['planned'] = $val['Planed'];
                                $formatted = sprintf("%0.2f", $val['Planed']);
                                $minute_arr = explode(".",$formatted);
                                if (count($minute_arr)>1) {
                                    $total_downtime_m = $total_downtime_m + $minute_arr[0];
                                    
                                    $total_downtime_s = $total_downtime_s + (int)$minute_arr[1];
                                }
                                else{
                                    $total_downtime_m = $total_downtime_m + $minute_arr[0];
                                }
                               
                                array_push($tmp_arr,$tmp);
                            }
                        }
                        $demo_arr['part_list'] = $tmp_arr;
                        if ($total_downtime_s > 59) {
                            $total_downtime_m = $total_downtime_m + (int) $total_downtime_s/60;
                        }else{
                            $total_downtime_m = $total_downtime_m.'.'.(int) $total_downtime_s;
                        }
                        
                        // $demo_arr['planned_downtime'] = $total_downtime_m;
    
                        array_push($final_arr,$total_downtime_m);
                    }              
                }
                $tmp11['arr'] = $final_arr;
                $tmp11['total_count'] = array_sum($final_arr)/60;
                return array_sum($final_arr)/60;
        }
        // unplanned downtime condition
        elseif ($temp['res'] == "unplanned_downtime") {
            $res = $this->getMachineWiseOEE($temp['from_time'],$temp['to_time']); 
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $unplanned_downtime_arr = [];
            foreach($res as $Key => $value){
                $demo_arr = [];
                if (in_array($value['Machine_ID'],$tmp_machine_arr)) {
                    $demo_arr['machine_id'] = $value['Machine_ID'];
                    // $demo_arr['planned_downtime'] = $value['Planned'];
                    $tmp_arr = [];
                    $total_downtime_m = 0;
                    $total_downtime_s = 0;
                    foreach ($value['Part_Wise'] as $k1 => $val) {
                        if (in_array($val['part_id'],$tmp_part_arr)) {
                            $tmp['part_id'] = $val['part_id'];
                            $tmp['unplanned'] = $val['Unplanned'];
                            $formatted = sprintf("%0.2f", $val['Unplanned']);
                            $minute_arr = explode(".",$formatted);
                            if (count($minute_arr)>1) {
                                $total_downtime_m = $total_downtime_m + $minute_arr[0];
                                
                                $total_downtime_s = $total_downtime_s + (int)$minute_arr[1];
                            }
                            else{
                                $total_downtime_m = $total_downtime_m + $minute_arr[0];
                            }
                           
                            array_push($tmp_arr,$tmp);
                        }
                    }
                    $demo_arr['part_list'] = $tmp_arr;
                    if ($total_downtime_s > 59) {
                        $total_downtime_m = $total_downtime_m + (int) $total_downtime_s/60;
                    }else{
                        $total_downtime_m = $total_downtime_m.'.'.(int) $total_downtime_s;
                    }
                    
                    $demo_arr['unplanned_downtime'] = $total_downtime_m;
                    array_push($unplanned_downtime_arr,$total_downtime_m);
                }              
            }

            $tmp1['unplanned_arr'] = $unplanned_downtime_arr;
            $tmp1['total_Count'] = array_sum($unplanned_downtime_arr)/60;

            return array_sum($unplanned_downtime_arr)/60;
            // return $res;
        }
        // planned machine off condition
        elseif ($temp['res'] == "planned_machine_off") {
            $machine_data = $this->getactive_machine_data();
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $res = $this->planned_machine_off($temp['from_time'],$temp['to_time'],$tmp_machine_arr); 
            
            $planned_arr = [];
            foreach ($res as $key => $value) {
                if(in_array($value['machine_id'], $tmp_machine_arr)){
                    $planned['machine_id'] = $value['machine_id'];
                    $planned_part = [];
                    $tmp_total_duration = 0;
                    foreach ($value['parts_data'] as $k1 => $val) {
                        if(in_array($val['part_id'], $tmp_part_arr)){
                            $tmp_total_duration = $tmp_total_duration + $val['duration'];
                            $plan['part_id'] = $val['part_id'];
                            $plan['duration'] = $val['duration'];
                            array_push($planned_part, $plan);
                        }
                    }
                    // seconds to minute convertion
                    $total_duration = $tmp_total_duration/60;
                    $planned['part_data'] = $planned_part;
                    $planned['total_duration'] = $total_duration;
                    
                }
                array_push($planned_arr,$total_duration);
            }            
            
            //total duration array
            $tmp1['arr'] = $planned_arr;
            $tmp1['total_duration'] = array_sum($planned_arr)/60;

            return array_sum($planned_arr)/60;
        }
        // unplanned machine off condition
        elseif ($temp['res'] == "unplanned_machine_off") {
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $res = $this->unplanned_machine_off($temp['from_time'],$temp['to_time'],$tmp_machine_arr);

            $unplanned_arr = [];
            foreach ($res as $key => $value) {
                if(in_array($value['machine_id'], $tmp_machine_arr)){
                    $unplanned['machine_id'] = $value['machine_id'];
                    $unplanned_part = [];
                    $tmp_total_duration = 0;
                    foreach ($value['parts_data'] as $k1 => $val) {
                        if(in_array($val['part_id'], $tmp_part_arr)){
                            $tmp_total_duration = $tmp_total_duration + $val['duration'];
                            $unplan['part_id'] = $val['part_id'];
                            $unplan['duration'] = $val['duration'];
                            array_push($unplanned_part, $unplan);
                        }
                    }
                    // minutes convertion
                    $total_duration = $tmp_total_duration/60;
                    $unplanned['part_data'] = $unplanned_part;
                    $unplanned['total_duration'] = $total_duration;
                }
                array_push($unplanned_arr,$total_duration);
            }   

            $tmp1['arr'] = $unplanned_arr;
            $tmp1['total_duration'] = array_sum($unplanned_arr)/60;

            return array_sum($unplanned_arr)/60; 
            
        }
        // total unamed condition 
        elseif ($temp['res']=="total_unamed") {
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $res = $this->total_unamed($temp['from_time'],$temp['to_time'],$tmp_machine_arr);

            $unamed_arr = [];
            foreach ($res as $key => $value) {
                if(in_array($value['machine_id'], $tmp_machine_arr)){
                    $unamed['machine_id'] = $value['machine_id'];
                    $unamed_part = [];
                    $tmp_total_duration = 0;
                    foreach ($value['parts_data'] as $k1 => $val) {
                        if(in_array($val['part_id'], $tmp_part_arr)){
                            $tmp_total_duration = $tmp_total_duration + $val['duration'];
                            $unamed_tmp['part_id'] = $val['part_id'];
                            $unamed_tmp['duration'] = $val['duration'];
                            array_push($unamed_part, $unamed_tmp);
                        }
                    }
                     // minutes convertion
                    $total_duration = $tmp_total_duration/60;
                    $unamed['part_data'] = $unamed_part;
                    $unamed['total_duration'] = $total_duration;
                }
                array_push($unamed_arr,$total_duration);
            }   

            $tmp1['arr'] = $unamed_arr;
            $tmp1['total_duration'] = array_sum($unamed_arr)/60;

            return array_sum($unamed_arr)/60; 
            // return $res;

        }
        // total downtime condition
        elseif ($temp['res']=="total_downtime") {
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $res = $this->total_downtime_metric($temp['from_time'],$temp['to_time'],$tmp_machine_arr);

            $downtime_arr = [];
            foreach ($res as $key => $value) {
                if(in_array($value['machine_id'], $tmp_machine_arr)){
                    $downtime['machine_id'] = $value['machine_id'];
                    $downtime_part = [];
                    $tmp_total_duration = 0;
                    foreach ($value['parts_data'] as $k1 => $val) {
                        if(in_array($val['part_id'], $tmp_part_arr)){
                            $tmp_total_duration = $tmp_total_duration + $val['duration'];
                            $downtime_tmp['part_id'] = $val['part_id'];
                            $downtime_tmp['duration'] = $val['duration'];
                            array_push($downtime_part, $downtime_tmp);
                        }
                    }
                    // minutes convertion
                    $total_duration = $tmp_total_duration/60;
                    $downtime['part_data'] = $downtime_part;
                    $downtime['total_duration'] = $total_duration;
                }
                array_push($downtime_arr,$total_duration);
            }   

            $tmp1['arr'] = $downtime_arr;
            $tmp1['total_duration'] = array_sum($downtime_arr)/60;

            return array_sum($downtime_arr)/60; 
            // return $res;

        }
        // total no of rejection condition 
        elseif ($temp['res']=="total_rejection") {
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $res = $this->total_rejection($temp['from_time'],$temp['to_time'],$tmp_machine_arr,$tmp_part_arr);

            return $res;
            
        }
        // total oee condition
        elseif($temp['res']=="oee"){
            // return "total_oee";
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);

            $oee_data = $this->get_oee_data($temp['from_time'],$temp['to_time'],$tmp_machine_arr,$tmp_part_arr);

            return $oee_data;

        }

        // TOTAL ooe condition
        elseif ($temp['res']=="ooe") {
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $ooe_data = $this->get_ooe_data($temp['from_time'],$temp['to_time'],$tmp_machine_arr,$tmp_part_arr);
            return $ooe_data;
        }

        elseif ($temp['res']=="teep") {
            $tmp_machine_arr = explode(",",$temp['machine_arr']);
            $tmp_part_arr = explode(",",$temp['part_arr']);
            $teep_data = $this->get_teep_data($temp['from_time'],$temp['to_time'],$tmp_machine_arr,$tmp_part_arr);

            return $teep_data;
        }

        */


    }

    

}


$obj = new Api_handling($_GET['site_id']);
// echo "<pre>";
// $fromtime = "2023-04-05T10:00:00";
// $totime = "2023-04-05T13:00:00";
// $res = "PlannedDowntime";
$temp['from_time'] = $_GET['from_time'];
$temp['to_time'] = $_GET['to_time'];
$temp['res'] = $_GET['res'];
$temp['machine_arr'] = $_GET['machine_arr'];
$temp['part_arr'] = $_GET['part_arr'];

echo  json_encode($obj->get_final_result($temp));





// $obj = new Api_handling('s1001');
// $temp['from_time'] = "2023-04-22T06:00:00";
// $temp['to_time'] = "2023-04-24T06:00:00";
// $temp['res'] = "teep";
// $temp['machine_arr'] = "all,MC1001,MC1002,MC1003,MC1004,MC1005,MC1006";
// $temp['part_arr'] = "all,PT1001,PT1002,PT1003,PT1004,PT1005,PT1006,PT1007,PT1008,PT1009,PT1010,PT1011,PT1012,PT1013,PT1014,PT1015,PT1016,PT1017,PT1018,PT1019,PT1020,PT1021,PT1022,PT1023,PT1024,PT1025,PT1026,PT1027,PT1028,PT1029,PT1030,PT1031,PT1032,PT1033,PT1034,PT1035,PT1036,PT1037,PT1038,PT1039,PT1040,PT1041,PT1042,PT1043,PT1044,PT1045";
// echo "<pre>";
// print_r($obj->get_final_result($temp));



?>