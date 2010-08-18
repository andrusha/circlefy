<?php
/* CALLS:
	homepage.phtml
 */
	$usage = <<<EOF
Usage:
	gid: id of the channel
	uid: id of the user to ban/promote
	action: ban , unban , promote , unpromote

	cid: id of the tap
	gid: channel of the tap [optional]
	action: get_channel_actions
EOF;

	session_start();
	require('../config.php');
	require('../api.php');

	$gid = $_POST['gid'];
	$target_uid = $_POST['target_uid'];
	$action = $_POST['action'];

	if(isset($gid) && isset($target_uid)){
		$group_mod = new group_mod();
		if ($action == "get_channel_actions") {
			$cid = $_POST['cid'];
			$res = $group_mod->get_channel_actions($gid,$target_uid,$cid);
		} else {
			$res = $group_mod->exec($gid,$target_uid,$action);
		}
		api_json_choose($res,$cb_enable);
	}else{
		api_usage($usage);
	}


	class group_mod {

		private $mysqli;
		private $results;


		function __construct(){
			$this->mysqli =  new mysqli(D_ADDR,D_USER,D_PASS,D_DATABASE);
		}

		function check_if_admin($gid,$uid){
			$check_if_admin_query = <<<EOF
				SELECT uid FROM group_members WHERE gid = {$gid} AND uid = {$uid} AND admin > 0
EOF;
			$check_if_admin_results = $this->mysqli->query($check_if_admin_query);
			if($check_if_admin_results->num_rows){
				return 1;
			}else{
				return 0;
			}
		}

		function get_channel_actions($gid, $target_uid, $cid) {
			$client_uid = $_SESSION["uid"];

			// Parte 1: PUBLIC. Let's generate this links:
			// 1. Go to channel (get $gid channel gname)
			// 2. Go to user profile (get $target_uid uname)
			
			$deletepermission = "no";	// At first, we won't show the DELETE option

			$get_info = "SELECT l.uname AS myuname FROM login l WHERE l.uid = $target_uid;";
			$get_info_res = $this->mysqli->query($get_info);
			$row = $get_info_res->fetch_assoc();
			$username = $row['myuname'];

			/*
			// If we don't know the GID, we find it using the CID:
			if ($gid) {
				$get_infochan = "SELECT g.gid AS mygid, g.gname AS mygname, g.symbol as mysymbol FROM groups g WHERE g.gid = $gid;";
			} else {
			 */

			// OK, now we're ALWAYS going to look for GID based on CID:
				$get_infochan =<<<EOF
SELECT g.gid AS mygid, g.gname AS mygname, g.symbol as mysymbol FROM groups g 
LEFT JOIN special_chat_meta s ON g.gid = s.gid
WHERE s.mid = '$cid' AND s.gid IS NOT NULL;
EOF;

			/* } */


				$get_infochan_res = $this->mysqli->query($get_infochan);
				$rowchan = $get_infochan_res->fetch_assoc();

				$channelname = $rowchan['mygname'];
				$channelsymbol = $rowchan['mysymbol'];
				$gid = $rowchan['mygid'];

				// If $client_uid owns this tap, he'll be able to delete it :)
				$ownership_q = "SELECT uid FROM special_chat s WHERE s.mid = '$cid' AND s.uid='$client_uid';";
				$ownership_res = $this->mysqli->query($ownership_q);
				if ($ownership_res->num_rows) {
					$deletepermission = "owner";
				}


			// Part 2: ADMIN. If $_SESSION['uid'] is admin on $gid...:
			// 1. Ban $target_uid from $gid
			// 2. Promote $target_uid to Admin in $gid
			$is_admin = $this->check_if_admin($gid,$client_uid);
			if (!$is_admin) {
				$var_admin = array();
			} else {
				// Do the logic...

				// SHOW BAN OR UNBAN LINK
				$ban_q = "SELECT abuid FROM block_group WHERE gid='${gid}' AND buid='${target_uid}';";
				$ban_res = $this->mysqli->query($ban_q);
				$banned = ($ban_res->num_rows) ? true : false;

				// SHOW PROMOTE OR UNPROMOTE LINK
				$promoted = false;
				$prom_q = "SELECT admin FROM group_members WHERE gid='${gid}' AND uid='${target_uid}';";
				$prom_res = $this->mysqli->query($prom_q);
				if ($prom_res->num_rows) {
					$prom_row = $prom_res->fetch_assoc();
					if ($prom_row['admin']=="1") $promoted = true;
				}

				// SHOW DELETE LINK
				//if ($deletepermission=="no") $deletepermission = "admin";
				$deletepermission = "admin";

				$ban_action 	= $banned	? "unban" : "ban";
				$prom_action 	= $promoted	? "unpromote" : "promote";

				if ($client_uid == $target_uid) {
					// We don't wanna allow you to UN/BAN or UN/PROMOTE yourself!:
					$ban_action = "";
					$prom_action = "";
				}
				$var_admin = array("moderator" => true, "ban_action" => $ban_action, "prom_action" => $prom_action);
			}

			sleep(1);

			return array(
				'public' => array(
					'gname' => "$channelname",
					'gid' => "$gid",
					'chansymbol' => "$channelsymbol", 
					'uname' => "$username"
				), 
				'admin' => $var_admin,
				'options' => array(
					'deletepermission' => $deletepermission
				)
			);
			
		}


		function exec($gid, $target_uid, $action) { 
			$success = false;
			$admin_uid = $_SESSION["uid"];
			$uname = $_SESSION["uname"];

			$admin_uid = $this->mysqli->real_escape_string($admin_uid);
			$gid = $this->mysqli->real_escape_string($gid);
			$target_uid = $this->mysqli->real_escape_string($target_uid);
			$actddion = $this->mysqli->real_escape_string($action);

			// Check admin
			$is_admin = $this->check_if_admin($gid,$admin_uid);
			if(!$is_admin) return array('success' => false, 'error' => "You($admin_uid) are not admin of that group($gid)!");

			switch($action) {
				case "ban":
					$success = $this->block_group($gid,$admin_uid,$target_uid,true);
					break;

				case "unban":
					$success = $this->block_group($gid,$admin_uid,$target_uid,false);
					break;

				case "promote":
				case "unpromote":
					// PROMOTE / UNPROMOTE
					$newAdmin=0;
					if ($action=="promote") $newAdmin=1;

					$group_mod_query = <<<EOF
INSERT INTO group_members (gid, uid, admin, tapd, inherit, group_outside_state) VALUES 
('$gid','$target_uid',$newAdmin,1,1,2) 
ON DUPLICATE KEY UPDATE 
admin = $newAdmin
EOF;
					$group_mod_results = $this->mysqli->query($group_mod_query);
					$res = $this->mysqli->affected_row;
					$success = true; // $res ?  true : false;

					break;

					default:
						return array('success' => False, 'error' => "group_mod.php Error: Invalid action!");
						break;
			}
			if($success) $success = true;
			return array('success' => $success, 'newAdmin' => $newAdmin, 'gid' => $gid, 'uid' => $target_uid, 'res' => $res);


		}


		function block_group($gid,$uid,$buid,$status){

			if($status)
				$block_query = "INSERT INTO block_group(buid,gid) values($buid,$gid) ";
			else
				$block_query = "DELETE FROM block_group WHERE buid = $buid AND gid = $gid";

			$block_result = $this->mysqli->query($block_query);

			$res = $this->mysqli->affected_row;
			
				$status = $status ? 0 : 1;
				$clean_up_query = "UPDATE group_members SET block = $status WHERE gid = $gid AND uid = $buid";
				$clean_up_result = $this->mysqli->query($clean_up_query);

			return ($res) ? true : false;
		}


	}
