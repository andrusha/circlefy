<?php
/* CALLS:
	homepage.phtml
*/
session_start();
require('../config.php');

$type = $_POST['type'];
$code = $_POST['code'];
$state = $_POST['state'];

if(isset($code)){
   	$geo_function = new geo_functions();
	if($type == 1)
	        $results = $geo_function->state_geo($code);
	if($type == 2)
	        $results = $geo_function->region_geo($code);
	if($type == 3)
	        $results = $geo_function->place_geo($code,$state);
        echo $results;
}


class geo_functions{

                private $mysqli;
                private $last_id = "SELECT LAST_INSERT_ID() AS last_id;";
                private $results;

        function __construct(){
                                $this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
        }

        function state_geo($code){
		$geo_query = <<<EOF
		SELECT

		cd.admin1_code AS 'region',cd.city_name as city,
		cn.name as Country

		FROM cities AS cd
			JOIN country_translate AS cn ON cd.country_code = cn.abbr2
		WHERE feature_code = 'ADM1' AND country_code = '{$code}' 

		ORDER BY city LIMIT 500;

EOF;
                $geo_results = $this->mysqli->query($geo_query);
	
		while($res = $geo_results->fetch_assoc()){
			$region = $res['region'];
			$city = $res['city'];
			
			$geo_data[] = array(
				'region'=>$region,
				'city'=>$city
			);

		}
			return json_encode(array('geo' => $geo_data));
	}

	function region_geo($code){
                $geo_query = <<<EOF
		SELECT

		cd.admin2_code AS 'region',cd.admin1_code AS 'state',cd.city_name as city,
		cn.name as Country

		FROM cities AS cd
			JOIN country_translate AS cn ON cd.country_code = cn.abbr2
		WHERE feature_code = 'ADM2' AND admin1_code = '{$code}' LIMIT 500;
EOF;

                $geo_results = $this->mysqli->query($geo_query);

                while($res = $geo_results->fetch_assoc()){
                        $region = $res['region'];
                        $state = $res['state'];
                        $city = $res['city'];

                        $geo_data[] = array(
                                'region'=>$region,
                                'state'=>$state,
				'city'=> $city
                        );
                }
                        return json_encode(array('geo' => $geo_data));
        }

	

	function place_geo($code,$state){
                $geo_query = <<<EOF
		SELECT

		cd.admin1_code AS 'region',cd.city_name as city,
		cn.name as Country

		FROM cities AS cd
			JOIN country_translate AS cn ON cd.country_code = cn.abbr2

		WHERE admin1_code = '{$state}' AND admin2_code = '{$code}' AND feature_code = 'PPL' LIMIT 1000;
EOF;

                $geo_results = $this->mysqli->query($geo_query);

                while($res = $geo_results->fetch_assoc()){
                        $region = $res['region'];
                        $city = $res['city'];

                        $geo_data[] = array(
                                'region'=>$region,
				'city'=> $city
                        );

                }
                        return json_encode(array('geo' => $geo_data));
        }

}
