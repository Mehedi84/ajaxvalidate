<?php

include '../libs/database.php';
include '../libs/functions.php';
session_start();

if (count($_REQUEST) >= 0) {
	$date_start = date_ymd($_REQUEST['date_start']);
	$date_end = date_ymd($_REQUEST['date_end']);
	$idate00 = $date_start . ' ' . '00:00:00';
	$edate00 = $date_start . ' ' . '23:59:59';
} else {
	$idate00 = date("Y-m-d") . ' ' . '00:00:00';
	$edate00 = date("Y-m-d") . ' ' . '23:59:59';
}
?>
<?php

$dropCount = mysql_query("SELECT count(*) as 'drop' FROM vicidial_closer_log where call_date >= '".$idate00."' AND call_date < '".$edate00."' AND (status = 'DROP' || status = 'AFTHRS')"); 
$datacount = mysql_fetch_row($dropCount);



$reachCount = mysql_query("SELECT count(*) as 'Reach' FROM vicidial_closer_log where call_date >= '".$idate00."' AND call_date < '".$edate00."' AND (status = 'Reach' || status = 'DSIMX')"); 
$reachedcall = mysql_fetch_row($reachCount);


/////////////////////////dailed call start////////////////////////////////

$idunique=array();
$outgoing_sql = "SELECT * FROM `vicidial_log` where `campaign_id` IN ('".check($_SESSION['user_group'])."') AND call_date >= '" . $idate00 . "' AND call_date <= '" . $edate00 . "'";
$out_sql_qry = mysql_query($outgoing_sql);
while($uniqid = mysql_fetch_assoc($out_sql_qry)){
	if($uniqid['uniqueid']!=''){
		array_push($idunique,$uniqid['uniqueid']);
	}
}
$outgoing_call_total = mysql_num_rows(mysql_query($outgoing_sql));

$outgoing_ans = "SELECT * FROM `vicidial_carrier_log` where `uniqueid` IN (".implode(',',$idunique).") AND `dialstatus` = 'ANSWER' AND call_date >= '" . $idate00 . "' AND call_date <= '" . $edate00 . "'";
$outgoing_ans_total = mysql_num_rows(mysql_query($outgoing_ans));

$countTo = mysql_query("SELECT SUM(length_in_sec) as length_in_sec FROM vicidial_log where `uniqueid` IN (".implode(',',$idunique).") and  call_date >= '" . $idate00 . "' and call_date <= '" . $edate00 . "'");
$count_ans_out = mysql_fetch_array($countTo);

////////////////////////////incomming call Start///////////////////////////////////

$skill_all = checkData($_SESSION["user_id"]);

foreach ($skill_all as  $value) {
	$count_call = mysql_query("SELECT count(*) as tot FROM vicidial_closer_log where `campaign_id` ='".$value."' and call_date >= '" . $idate00 . "' and call_date <= '" . $edate00 . "'");
	$count = mysql_fetch_array($count_call);
	$total_call = $count[0];

	$count_callAnswer = mysql_query("SELECT count(*) as tot, SUM(length_in_sec) as length_in_sec FROM vicidial_closer_log where `campaign_id` ='".$value."' and status !='DROP' and status !='AFTHRS'and call_date >= '" . $idate00 . "' and call_date <= '" . $edate00 . "'");
	$count_ans = mysql_fetch_array($count_callAnswer);
	$total_call_ans = $count_ans[0];

	$sqli = mysql_query("SELECT * FROM vicidial_closer_log where `campaign_id` ='".$value."' and  call_date >= '" . $idate00 . "' and call_date <= '" . $edate00 . "'");
	$totalDuration=0;
	while($unique = mysql_fetch_assoc($sqli)){
		$count_pause = mysql_query("SELECT SUM(`pause_sec`) as pause_sec, SUM(`wait_sec`) as wait_sec, SUM(`talk_sec`) as talk_sec, SUM(`dispo_sec`) as dispo_sec, SUM(`dead_sec`) as dead_sec  FROM vicidial_agent_log where `uniqueid`='".$unique['uniqueid']."' and  event_time >= '" . $idate00 . "' and event_time <= '" . $edate00 . "'");
		$pause = mysql_fetch_assoc($count_pause);
		$totalDuration += $pause['talk_sec']-$pause['dead_sec'];
	}
///////////////////////////////////////incomming End///////////////////////
///////////////////////////////////////Drop start///////////////////////
	$dropCount = mysql_query("SELECT * FROM vicidial_closer_log where `campaign_id` ='".$value."' and call_date >= '".$idate00."' AND call_date < '".$edate00."' AND (status = 'DROP')"); 
	$count=0;
	while($datacount = mysql_fetch_assoc($dropCount)){
		$out = mysql_num_rows(mysql_query("SELECT * FROM `user_call_log` WHERE `phone_number` LIKE '".$datacount['phone_number']."' AND `call_type`='MANUAL_DIALNOW' AND `call_date` > '".$idate00."' AND `call_date` <='".$edate00."'"));
		if($out>0){
			$count+=1;
		}
	}

	$tot_drop = mysql_num_rows($dropCount);
	$drop = $tot_drop-$count;
	$datacountCallback = $tot_drop-$drop;
	$dropPersent = floor(($drop*100)/$total_call);
	// $dropPersent = number_format(($drop*100)/$total_call,2);
}


$totalCall = $outgoing_ans_total+$total_call_ans;

$totalCollInLength = $count_ans_out['length_in_sec']+$count_ans['length_in_sec'];

$avgcalltime =$totalCollInLength / $totalCall;
$totalDurationto = time_convert1($totalCollInLength);

$Callback = mysql_num_rows(mysql_query("SELECT * FROM vicidial_closer_log where `campaign_id` ='".$value."' and call_date >= '".$idate00."' AND call_date < '".$edate00."' AND (status = 'CALLBK')")); 
///////////////////////////////////////Drop End///////////////////////

$json['total_dailed'] = $outgoing_call_total;
$json['total_abandond'] = $outgoing_call_total-$outgoing_ans_total;
$json['total_connected'] = $outgoing_ans_total;
$json['total_connected_per'] = floor(($outgoing_ans_total*100)/$outgoing_call_total);


$json['total_in_call'] = $total_call;
$json['total_answered_incoming_coonected'] = $total_call_ans;
$json['totalDuration'] = $totalDurationto;
$json['avgcalltime'] = gmdate("i:s",$avgcalltime);


$json['total_dropped'] = $tot_drop;
$json['reachedcall'] = $datacountCallback;
$json['actualDrop'] = $drop;
$json['actualDropdropPersent'] = $dropPersent;

$json['callBaclReq'] = $Callback;
$json['callBaclReached'] = 0;
$json['callBaclNed'] = 0;
$json['callBaclReqPersent'] = 0;

// encode to json formate
echo json_encode($json);

?>





