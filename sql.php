<?php


	/**********
	This class hass all SQL statements relative to the functionality
	In order to create a new SQL query, prefix it with get
	**********/
	class sql_object {

		private $_sql_query;
		private $joinUser = ' JOIN msProfile ON msProfile.msID = mix.mspId ';

		public function __construct(){
		}   

		/*****************
		getUser method - Return SQL of for fettching one user out of the users tableA
		*
		* @param int    the user id of the user you're trying to get
		*
		* return string [sql]
		*****************/
		public function getUser($uid){
			//Apply filters
			$uid = (int) $uid;

			//Create the query
			$this->_sql_query = <<<EOF
		SELECT uid FROM users_tbl WHERE uid = {$uid}
EOF;

			//Return the query to be executed
			return $this->_sql_query;
		}   

		/*****************
		lastInsertidAS method - returns last id inserted of a row AS "last_id"
		return string [sql]
		******************/
		public function lastInsertIdAS(){
			$this->_sql_query = <<<EOF
			SELECT LAST_INSERT_ID() as last_id
EOF;
			return $this->_sql_query;
		}

		/*****************
		lastInsertid method - returns last id inserted of a row
		return string [sql]
		******************/
		public function lastInsertId(){
			$this->_sql_query = <<<EOF
			SELECT LAST_INSERT_ID()
EOF;
			return $this->_sql_query;
		}


		/*****************
		addNewMixDynamic method - Return SQL of inserting a new mix dynamically based off $_REQUEST params
		@param string   name of the table to insert a row into
		@param array    key = columns, values = values  
		return string [sql]
		******************/
		public function addNewRowDynamic($table_name,$col_data){
			$cols ='';
			$datas ='';
			foreach($col_data as $col => $data){
				$cols .= $col.',';

				if(is_int($data))
					$datas .= $data.',';
				else
					$datas .= "'$data'".",";
			}
			$cols = substr($cols,0,-1);
			$datas = substr($datas,0,-1);

			$this->_sql_query = <<<EOF
		INSERT INTO {$table_name}({$cols})

		values({$datas})
EOF;

			return $this->_sql_query;


		}

		/*****************
		addNewMixDynamic method - Return SQL of inserting a new mix dynamically based off $_REQUEST params
		@param string   name of the table to insert a row into
		@param array    key = columns, values = values  
		return string [sql]
		******************/
		public function updateProfile($fname,$flname,$email,$private,$zip,$lang,$country,state,$region,$about,$town,$uname,$anonCheck,$uid) {
			
			// You can change username only if you're a Guest User!
			$change_uname = " t2.uname=\"$uname\", ";
			if ($anonCheck != "1") $chane_uname = "";	

			$this->_sql_query = <<<EOF
				UPDATE profile AS t1
							JOIN login AS t2
							ON t1.uid = t2.uid
							SET
					t2.fname="$fname",
					t2.lname="$lname",
					t2.email="$email",
					t2.private=$private,
					$change_uname
					t1.zip=$zip,
					t1.language="$lang",
					t1.country="$country",
					t1.state="$state",
					t1.region="$region",
					t1.about="$about",
					t1.town="$town"
							WHERE t1.uid ={$uid}
EOF;

			return $this->_sql_query;
		}

}
