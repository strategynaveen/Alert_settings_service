<?php 


include "db_records.php";

class oee_handling{
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


    public function get_oee($temp){
    	$res = $this->data->getactive_machine_data();
    	
    	return $res;
    }
}



$obj = new oee_handling("s1001");
$temp['from_time'] = "2023-04-18T11:00:00";
$temp['to_time'] = "2023-04-20T13:00:00";
$temp['res'] = "planned_machine_off";
$temp['machine_arr'] = "all,MC1001,MC1002,MC1003,MC1004,MC1005,MC1006";
$temp['part_arr'] = "all,PT1001,PT1002,PT1003,PT1004,PT1005,PT1006,PT1007,PT1008,PT1009,PT1010,PT1011,PT1012,PT1013,PT1014,PT1015,PT1016,PT1017,PT1018,PT1019,PT1020,PT1021,PT1022,PT1023,PT1024,PT1025,PT1026,PT1027,PT1028,PT1029,PT1030,PT1031,PT1032,PT1033,PT1034,PT1035,PT1036,PT1037,PT1038,PT1039,PT1040,PT1041,PT1042,PT1043,PT1044,PT1045";

echo "<pre>";
print_r($obj->get_oee($temp));




?>