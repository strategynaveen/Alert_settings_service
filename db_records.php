<?php 


class db_records{

    public $con;
    public function __construct($site_id){
        $server_name = "localhost";
        $user_name = "root";
        $password = "";
        $db_name = $site_id;
        $this->con = mysqli_connect($server_name, $user_name, $password,$db_name);

        // Check connection
        if ($this->con->connect_error) {
            die("Connection failed: " . $this->con->connect_error);
        }
    }



    public function PartDetails(){
        $sql = "SELECT part_id,part_price,NICT,part_name from settings_part_current";
        $res = $this->con->query($sql);
        $part_arr = [];
        if ($res->num_rows>0) {
            while($row = $res->fetch_assoc()){
                array_push($part_arr,$row);
            }
        }
        return $part_arr;
    }




    public function getMachineDetails(){
        $sql = "SELECT machine_id,rate_per_hour,machine_offrate_per_hour,machine_name from settings_machine_current";
        $res = $this->con->query($sql);
        $machine_arr = [];
        if ($res->num_rows>0) {
            while($row = $res->fetch_assoc()){
                array_push($machine_arr,$row);
            }
        }
        return $machine_arr;
    }


    // raw data production info table data
    public function getDataRaw($FromDate,$FromTime,$ToDate,$ToTime){
        $sql_reason = "SELECT t.machine_event_id,t.machine_id,t.downtime_reason_id,t.tool_id,t.part_id,t.shift_date,t.start_time,t.end_time,t.split_duration,t.calendar_date,r.downtime_category,r.downtime_reason FROM `pdm_downtime_reason_mapping` as t INNER JOIN settings_downtime_reasons as r on t.downtime_reason_id = r.downtime_reason_id  WHERE t.shift_date>='$FromDate' and t.shift_date<='$ToDate'";
        $result = $this->con->query($sql_reason);
        $production_data_reason = [];
        if ($result) {
            if ($result->num_rows>0) {
                while ($row = $result->fetch_assoc()) {
                    array_push($production_data_reason,$row);
                }
            } 
        }
       
       
	    return $production_data_reason;
    }

    // events data
    public function getDataRawAll($FromDate,$ToDate){
        $query_sql = "SELECT * FROM `pdm_events` WHERE shift_date>='$FromDate' and shift_date<='$ToDate' and event!='Offline' and event != 'No Data' ";
        $res = $this->con->query($query_sql);
        $pdm_event_data = [];
        if ($res) {
            if ($res->num_rows>0) {
                while ($row = $res->fetch_assoc()) {
                    array_push($pdm_event_data,$row);
                }
            }
        }
       

        return $pdm_event_data;
    }

    // get offline and downtime records
    public function getOfflineEventId($FromDate,$FromTime,$ToDate,$ToTime){
        $query = "SELECT * FROM `pdm_events` WHERE shift_date>='$FromDate' and shift_date<='$ToDate' and event='Offline' or event = 'No Data' ";
        $res = $this->con->query($query);

        $pdm_event_data1 = [];
        if ($res->num_rows>0) {
            while ($row = $res->fetch_assoc()) {
                array_push($pdm_event_data1,$row);
            }
        }

        return $pdm_event_data1;
    }

    // get active machine records
    public function getMachineRecActive($FromDate,$ToDate){
        $sql = "SELECT DISTINCT(machine_id) FROM pdm_production_info WHERE shift_date>='$FromDate' AND shift_date<='$ToDate'";
        $res = $this->con->query($sql);
        $pdm_info_data = [];
        if ($res->num_rows>0) {
            while ($row = $res->fetch_assoc()) {
                array_push($pdm_info_data,$row);
            }
        }

        return $pdm_info_data;
    }

    // 
    public function getPartRec($FromDate,$ToDate){
       
        $sql_query = "SELECT DISTINCT(part_id) FROM pdm_production_info WHERE shift_date>='$FromDate' and shift_date<='$ToDate'";
        $res = $this->con->query($sql_query);
        $pdm_info_data1 = [];
        if ($res->num_rows>0) {
            while ($row = $res->fetch_assoc()) {
                array_push($pdm_info_data1,$row);
            }
        }
        return $pdm_info_data1;
    }   


    // info records data
    public function getProductionRec($FromDate,$ToDate){
       
        $sql_query = "SELECT machine_id,calendar_date,shift_date,start_time,end_time,part_id,tool_id,production,corrections,rejections,reject_reason,actual_shot_count FROM pdm_production_info WHERE shift_date>='$FromDate' and shift_date<='$ToDate' and production != null";
        $res = $this->con->query($sql_query);
        $pdm_info_record = [];
        if ($res) {
            if ($res->num_rows>0) {
                while ($row = $res->fetch_assoc()) {
                    array_push($pdm_info_record,$row); 
                }
            } 
        }
       
        return $pdm_info_record;
    }

    public function getInactiveMachineData(){
       
        $query = "SELECT p.machine_id,max(r.last_updated_on) FROM settings_machine_current as p INNER JOIN settings_machine_log as r ON r.machine_id = p.machine_id WHERE p.status=0 and r.status=0 GROUP BY r.machine_id order by r.last_updated_on desc ";
        $result = $this->con->query($query);
        $settings_arr = [];
        if ($result->num_rows>0) {
            while($row = $result->fetch_assoc()){
                array_push($settings_arr,$row);
            }
        }

        return $settings_arr;
    }

    public function settings_tools()
    {
       
        $sql = "SELECT s.*,t.tool_name FROM settings_part_current as s  inner join settings_tool_table as t on t.tool_id = s.tool_id";
        $result = $this->con->query($sql);
        $settings_part_arr = [];
        if ($result->num_rows>0) {
            while($row = $result->fetch_assoc()){
                array_push($settings_part_arr,$row);
            }
        }
        return $settings_part_arr;
    }

    public function getMachineRecGraph()
    {
        $sql = "SELECT machine_id,machine_name,machine_offrate_per_hour,rate_per_hour FROM settings_machine_current ";
        $res = $this->con->query($sql);
        $machine_arr_data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                array_push($machine_arr_data,$row);
            }
        }
        return $machine_arr_data;

    }

    public function getGoalsFinancialData(){
       
        $sql_query = "SELECT * FROM settings_financial_metrics_goals ORDER BY last_updated_on DESC limit 1 ";
        $result = $this->con->query($sql_query);
        $financial_arr = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                array_push($financial_arr,$row);
            }
        }

        return $financial_arr;

    }


    // this function get only active machine records only
    public function getactive_machine_data(){
        $sql_query = "SELECT * FROM `settings_machine_current` WHERE `status`!=0";
        $res = $this->con->query($sql_query);

        $machine_arr = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                array_push($machine_arr, $row);
            }
        }

        return $machine_arr;
    }

    // this function get machine off records 
    public function getmachine_off_records($ref,$from_time,$to_time){

        if ($ref=="total_unamed") {
            $sql = "SELECT * FROM pdm_downtime_reason_mapping WHERE downtime_reason_id=0 and shift_date>='$from_time' and shift_date<='$to_time'";
            $res = $this->con->query($sql);
            $total_unamed_arr = [];
            if ($res) {
                while($row = $res->fetch_assoc()){
                    array_push($total_unamed_arr, $row);
                }
            }
    
            return $total_unamed_arr;
            
        }elseif ($ref=="total_downtime") {
            $sql_query = "SELECT * FROM pdm_downtime_reason_mapping WHERE shift_date>='$from_time' and shift_date<='$to_time'";
            $res_final = $this->con->query($sql_query);
            $total_downtime_arr = [];
            if ($res_final) {
                while ($data = $res_final->fetch_assoc()) {
                    array_push($total_downtime_arr,$data);
                }
            }
            
            return $total_downtime_arr;
        }else{

            $sql_query = "SELECT p.* FROM `settings_downtime_reasons` as s INNER join `pdm_downtime_reason_mapping` as p on s.downtime_reason_id=p.downtime_reason_id WHERE s.downtime_category='$ref' and s.downtime_reason='Machine OFF' and p.shift_date>='$from_time' and p.shift_date<='$to_time' ";
            $res = $this->con->query($sql_query);
            $reason_mapping_arr = [];
            if ($res) {
                while($row = $res->fetch_assoc()){
                    array_push($reason_mapping_arr, $row);
                }
            }
    
            return $reason_mapping_arr;

        }


       
      
    }

     // total rejection function 
    public function get_total_rejection_production($from_date,$to_date,$stime,$ttime,$machine_arr,$part_arr){
        $condition_machine = implode(" ',' ",$machine_arr);
        $condition_part = implode(" ',' ",$part_arr);
        $sql= "SELECT count(rejections) AS total_Rejection FROM `pdm_production_info` WHERE shift_date>='$from_date' and shift_date<='$to_date' and start_time>='$stime' and end_time<='$ttime' and machine_id IN ('" . implode( "', '", $machine_arr ) . "') and part_id IN ('".implode("','",$part_arr)."')"; 
        $res = $this->con->query($sql);
        $production_data = [];
        if ($res) {
            while($data = $res->fetch_assoc()){
                array_push($production_data, $data);
            }
        }
           
        return $production_data;
        
    }



  


}

















?>