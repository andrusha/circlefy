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

	}
