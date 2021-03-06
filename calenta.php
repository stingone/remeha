<?php
// Uncomment to report Errors for Debug purposes
// error_reporting(E_ALL);

$sample_cycle = 0;

// remeha.ini file Variables
//
$ini_array = parse_ini_file("remeha.ini");
$ESPIPAddress = $ini_array['ESPIPAddress'];
$ESPPort = $ini_array['ESPPort'];
$retries = $ini_array['retries'];
$sleeptime = $ini_array['sleeptime'];
$sample_loops = $ini_array['sample_loops'];
$nanosleeptime =  $ini_array['nanosleeptime'];
$echo_flag = $ini_array['echo_flag'];
$newline = $ini_array['newline'];
	if ($newline == "terminal"){$newline = "\n";}
	elseif ($newline == "windows"){$newline = "\r\n";} 
	else {$newline = "<br />";}
$deg_symbol = $ini_array['deg_symbol'];
$remeha_sample = hex2bin($ini_array['remeha_sample']);
$remeha_counter1 = hex2bin($ini_array['remeha_counter1']);
$remeha_counter2 = hex2bin($ini_array['remeha_counter2']);
$remeha_counter3 = hex2bin($ini_array['remeha_counter3']);
$remeha_counter4 = hex2bin($ini_array['remeha_counter4']);

while (true) #infinite loop until false
{
	if ($sample_cycle < $sample_loops)
		{
		$fp = connect_to_esp($ESPIPAddress, $ESPPort, $retries, $newline);
		if (!$fp) 
			{
			exit("Unable to establish connection to $ESPIPAddress:$ESPPort$newline");
			} 
		else
			{
			echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
			stream_set_timeout($fp, 5);
			// Collect Sample Data Info
			conditional_echo(str_repeat("=", 166) . "$newline", $echo_flag);
			conditional_echo("Connected to $ESPIPAddress:$ESPPort$newline", $echo_flag);
			conditional_echo("Sending request...$newline", $echo_flag);
			fwrite($fp,$remeha_sample, 10);
			$data_sample = "";	
			$data_sample = bin2hex(fread($fp, 148));
			$data_sampleU = strtoupper($data_sample);
			conditional_echo("Sample Data read: $data_sampleU$newline", $echo_flag);
			$output = sample_data_dump($data_sample, $echo_flag, $newline);
			fclose($fp);
			sleep($sleeptime);
			$sample_cycle++;
			}
		}
	else
		{
		$fp = connect_to_esp($ESPIPAddress, $ESPPort, $retries, $newline);
		if (!$fp) 
			{
			exit("Unable to establish connection to $ESPIPAddress:$ESPPort$newline");
			} 
		else
			{
			echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
			stream_set_timeout($fp, 5);
			// Collect Counter Info
			conditional_echo(str_repeat("=", 148) . "$newline", $echo_flag);
			conditional_echo("Connected to $ESPIPAddress:$ESPPort$newline", $echo_flag);
			conditional_echo("Sending request...$newline", $echo_flag);
			fwrite($fp,$remeha_counter1, 10);
			$data_counter1 = "";
			$data_counter1 = bin2hex(fread($fp, 52));
			$data_counter1U = strtoupper($data_counter1);
			conditional_echo("Counter Data-1 read: $data_counter1U$newline", $echo_flag);
			usleep($nanosleeptime);

			fwrite($fp,$remeha_counter2, 10);
			$data_counter2 = "";
			$data_counter2 = bin2hex(fread($fp, 52));
			$data_counter2U = strtoupper($data_counter2);
			conditional_echo("Counter Data-2 read: $data_counter2U$newline", $echo_flag);
			usleep($nanosleeptime);

			fwrite($fp,$remeha_counter3, 10);
			$data_counter3="";
			$data_counter3=bin2hex(fread($fp, 52));
			$data_counter3U = strtoupper($data_counter3);
			conditional_echo("Counter Data-3 read: $data_counter3U$newline", $echo_flag);
			usleep($nanosleeptime);

			fwrite($fp,$remeha_counter4, 10);
			$data_counter4="";
			$data_counter4=bin2hex(fread($fp, 52));
			$data_counter4U = strtoupper($data_counter4);
			conditional_echo("Counter Data-4 read: $data_counter4U$newline", $echo_flag);
			$output = counter_data_dump($data_counter1, $data_counter2, $data_counter3, $data_counter4, $echo_flag, $newline);
			fclose($fp);

			sleep($sleeptime);
			$sample_cycle = 0;
			}
		}
}

// Time to 'Work the SAMPLE Data'
//
function sample_data_dump($data_sample, $echo_flag, $newline)
{

// Manipulate data & Do a CRC Check	
	$decode = str_split($data_sample, 2);
	$hexstr = str_split($data_sample, 148);
	$hexstrPayload = substr($data_sample, 2, 140);
	$hexstrCRC = substr($data_sample, 142, 4);
	$crcCalc = crc16_modbus($hexstrPayload);	

	// Write the contents to the file
	$ini_array = parse_ini_file("remeha.ini");
	$path = $ini_array['path_to_logs'];
	$filename = $ini_array['sample_data_log'];
	$deg_symbol = $ini_array['deg_symbol'];
	$file = "$path$filename";
	date_default_timezone_set('Europe/Amsterdam');
	$date = date_create();

	if ($hexstrCRC == $crcCalc)
		{
		conditional_echo("Data Integrity Good - CRCs Compute OK$newline", $echo_flag);
		$datatowrite = date_format($date, 'Y-m-d H:i:s') . ' | 02 ' . $hexstrPayload . ' ' . $hexstrCRC . ' ' .'03 |' . "\n";
		file_put_contents($file, $datatowrite, FILE_APPEND);
		conditional_echo("Data written to log: $file$newline", $echo_flag);
		conditional_echo(str_repeat("=", 166) . "$newline$newline", $echo_flag);
		}
	else
		{
		$datatowrite = '**** CRC Error **** | ' . date_format($date, 'Y-m-d H:i:s') . ' | 02 ' . $hexstrPayload . ' ' . $hexstrCRC . ' ' .'03| ' . "\n";
		file_put_contents($file, $datatowrite, FILE_APPEND);
		conditional_echo("Data written to log: $file$newline", $echo_flag);
		conditional_echo("$newline", $echo_flag);
		conditional_echo("************** CRC ERROR!!!! ***********$newline", $echo_flag);
		return;		# Don't continue with updating Sample data
		}

// Sample Data Info	
	$flowtemperature = $decode["8"];
	$flowtemperature .= $decode["7"];   
	$returntemperature = $decode["10"];
	$returntemperature .= $decode["9"];
	$dhwintemperature = $decode["12"];
	$dhwintemperature .= $decode["11"];
	if ($dhwintemperature == 8000) {$dhwintemperature = 0.00;}
	else {$dhwintemperature == $dhwintemperature;}
	$outsidetemperature = $decode["14"];
	$outsidetemperature .= $decode["13"];
	if ($outsidetemperature == 8000) {$outsidetemperature = 0.00;}
	else {$outsidetemperature == $outsidetemperature;} 	  
	$calorifiertemperature = $decode["16"]; # Documented as Byte 15, but doesn't seem to make sense
	$calorifiertemperature .= $decode["15"];
	if ($calorifiertemperature == 8000) {$calorifiertemperature = 0.00;}
	else {$calorifiertemperature == $calorifiertemperature;}
	$boilerctrltemperature = $decode["20"];
	$boilerctrltemperature .= $decode["19"];
	$roomtemperature = $decode["22"];
	$roomtemperature .= $decode["21"];
	$chsetpoint = $decode["24"];
	$chsetpoint .= $decode["23"];
	$dhwsetpoint = $decode["26"];
	$dhwsetpoint .= $decode["25"];
	$thermostat = $decode["28"];
	$thermostat .= $decode["27"];
	$fanspeedsetpoint = $decode["30"];
	$fanspeedsetpoint .= $decode["29"];
	$fanspeed = $decode["32"];
	$fanspeed .= $decode["31"];
	$ionisationcurrent = "";
	$ionisationcurrent .= $decode["33"];
	$internalsetpoint = $decode["35"];
	$internalsetpoint .= $decode["34"];
	$availablepower = $decode["36"];
	$pumppower = $decode["37"];
	$requiredoutput = $decode["39"];
	$actualpower = $decode["40"];
	$heatrequest = $decode["43"];
	$ionisation = $decode["44"];
	$valves = $decode["45"];
	$pump = $decode["46"];
	$state = $decode["47"];
	$lockout = $decode["48"];
	$blocking = $decode["49"];
	$substate = $decode["50"];  
	$pressure = $decode["56"];
	$controltemperature = $decode["59"];
	$controltemperature .= $decode["58"];
	$dhwflowrate = $decode["61"];
	$dhwflowrate .= $decode["60"];
	$solartemperature = $decode["64"];
	$solartemperature .= $decode["63"];
	if ($solartemperature == 8000) {$solartemperature = 0.00;}
	else {$solartemperature == $solartemperature;}

// END Sample Data Info

//Convert Hex2Dec
  	$flowtemperature = number_format(hexdecs($flowtemperature)/100, 2);
	$returntemperature = number_format(hexdecs($returntemperature)/100, 2);
	$dhwintemperature = number_format(hexdecs($dhwintemperature)/100, 2);
	$outsidetemperature = number_format(hexdecs($outsidetemperature)/100, 2);
	$calorifiertemperature = number_format(hexdecs($calorifiertemperature)/100, 2);
	$boilerctrltemperature = number_format(hexdecs($boilerctrltemperature)/100, 2);
	$roomtemperature = number_format(hexdecs($roomtemperature)/100, 2);
	$chsetpoint = number_format(hexdecs($chsetpoint)/100, 2);
	$dhwsetpoint = number_format(hexdecs($dhwsetpoint)/100, 2);
	$thermostat = number_format(hexdecs($thermostat)/100, 2);
	$fanspeedsetpoint = hexdec($fanspeedsetpoint);
	$fanspeed = hexdec($fanspeed);
	$ionisationcurrent = number_format(hexdec($ionisationcurrent)/10, 1);
	$internalsetpoint = number_format(hexdecs($internalsetpoint)/100, 2);
	$availablepower = hexdec($availablepower);
	$pumppower = hexdec($pumppower);
	$requiredoutput = hexdec($requiredoutput);
	$actualpower = hexdec($actualpower);
	$pressure = number_format(hexdec($pressure)/10, 1);
	$controltemperature = number_format(hexdecs($controltemperature)/100, 2);
	$dhwflowrate = number_format(hexdecs($dhwflowrate)/100, 2);
	$solartemperature = number_format(hexdecs($solartemperature)/100, 2);

// END Convert Hex2Dec

// Translate 'bits' to useful stuff

	// Modulating Controller Connected
	$heatrequestBIT0 = nbit(hexdec($heatrequest), 0);
	if ($heatrequestBIT0 == 0) {$heatrequestTXT0 = "No";}
	elseif ($heatrequestBIT0 == 1) {$heatrequestTXT0 = "Yes";}
	$heatrequest0 = "$heatrequestBIT0:$heatrequestTXT0";
	
	// Heat demand from mod. controller
	$heatrequestBIT1 = nbit(hexdec($heatrequest), 1);
	if ($heatrequestBIT1 == 0) {$heatrequestTXT1 = "No";}
	elseif ($heatrequestBIT1 == 1) {$heatrequestTXT1 = "Yes";}
	$heatrequest1 = "$heatrequestBIT1:$heatrequestTXT1";

	// Heat demand from on/off controller
	$heatrequestBIT2 = nbit(hexdec($heatrequest), 2);
	if ($heatrequestBIT2 == 0) {$heatrequestTXT2 = "No";}
	elseif ($heatrequestBIT2 == 1) {$heatrequestTXT2 = "Yes";}
	$heatrequest2 = "$heatrequestBIT2:$heatrequestTXT2";

	// Frost protection
	$heatrequestBIT3 = nbit(hexdec($heatrequest), 3);
	if ($heatrequestBIT3 == 0) {$heatrequestTXT3 = "No";}
	elseif ($heatrequestBIT3 == 1) {$heatrequestTXT3 = "Yes";}
	$heatrequest3 = "$heatrequestBIT3:$heatrequestTXT3";

	// DHW Eco - INVERT
	$heatrequestBIT4 = nbit(hexdec($heatrequest), 4);
	if (nbit($heatrequestBIT4,4) == 0) {$heatrequestTXT4 = "Yes";}
	elseif (nbit($heatrequestBIT4,4) == 1) {$heatrequestTXT4 = "No";}
	$heatrequest4 = "$heatrequestBIT4:$heatrequestTXT4";

	// DHW Blocking
	$heatrequestBIT5 = nbit(hexdec($heatrequest), 5);
	if ($heatrequestBIT5 == 0) {$heatrequestTXT5 = "No";}
	elseif ($heatrequestBIT5 == 1) {$heatrequestTXT5 = "Yes";}
	$heatrequest5 = "$heatrequestBIT5:$heatrequestTXT5";

	// Anti-Legionella
	$heatrequestBIT6 = nbit(hexdec($heatrequest), 6);
	if ($heatrequestBIT6 == 0) {$heatrequestTXT6 = "No";}
	elseif ($heatrequestBIT6 == 1) {$heatrequestTXT6 = "Yes";}
	$heatrequest6 = "$heatrequestBIT6:$heatrequestTXT6";

	// DHW heat demand	
	$heatrequestBIT7 = nbit(hexdec($heatrequest), 7);
	if ($heatrequestBIT7 == 0) {$heatrequestTXT7 = "No";}
	elseif ($heatrequestBIT7 == 1) {$heatrequestTXT7 = "Yes";}
	$heatrequest7 = "$heatrequestBIT7:$heatrequestTXT7";

	// Shutdown Input - INVERT
	$ionisationBIT0 = nbit(hexdec($ionisation), 0);
	if ($ionisationBIT0 == 0) {$ionisationTXT0 = "Closed";}
	elseif ($ionisationBIT0 == 1) {$ionisationTXT0 = "Open";}
	$ionisation0 = "$ionisationBIT0:$ionisationTXT0";

	// Release Input - INVERT
	$ionisationBIT1 = nbit(hexdec($ionisation), 1);
	if (nbit($ionisationBIT1,1) == 0) {$ionisationTXT1 = "Closed";}
	elseif (nbit($ionisationBIT1,1) == 1) {$ionisationTXT1 = "Open";}
	else {$ionisationTXT1 = "UNKNOWN";}
	$ionisation1 = "$ionisationBIT1:$ionisationTXT1";

	// Ionisation
	$ionisationBIT2 = nbit(hexdec($ionisation), 2);
	if (nbit($ionisationBIT2,2) == 0) {$ionisationTXT2 = "No";}
	elseif (nbit($ionisationBIT2,2) == 1) {$ionisationTXT2 = "Yes";}
	else {$ionisationTXT2 = "UNKNOWN";}
	$ionisation2 = "$ionisationBIT2:$ionisationTXT2";

	// Flow Switch for detecting DHW
	$ionisationBIT3 = nbit(hexdec($ionisation), 3);
	if (nbit($ionisationBIT3,3) == 0) {$ionisationTXT3 = "Open";}
	elseif (nbit($ionisationBIT3,3) == 1) {$ionisationTXT3 = "Closed";}
	else {$ionisationTXT3 = "UNKNOWN";}
	$ionisation3 = "$ionisationBIT3:$ionisationTXT3";

	// Min. Gas Pressure
	$ionisationBIT5 = nbit(hexdec($ionisation), 5);
	if (nbit($ionisationBIT5,5) == 0) {$ionisationTXT5 = "Open";}
	elseif (nbit($ionisationBIT5,5) == 1) {$ionisationTXT5 = "Closed";}
	else {$ionisationTXT5 = "UNKNOWN";}
	$ionisation5 = "$ionisationBIT5:$ionisationTXT5";

	// CH Enable
	$ionisationBIT6 = nbit(hexdec($ionisation), 6);
	if (nbit($ionisationBIT6,6) == 0) {$ionisationTXT6 = "No";}
	elseif (nbit($ionisationBIT6,6) == 1) {$ionisationTXT6 = "Yes";}
	else {$ionisationTXT6 = "UNKNOWN";}
	$ionisation6 = "$ionisationBIT6:$ionisationTXT6";

	// DHW Enable
	$ionisationBIT7 = nbit(hexdec($ionisation), 7);
	if ($ionisationBIT7 == 0) {$ionisationTXT2 = "No";}
	elseif ($ionisationBIT7 == 1) {$ionisationTXT7 = "Yes";}
	$ionisation7 = "$ionisationBIT7:$ionisationTXT7";

	// Gas valve - INVERT
	$gasvalveBIT0 = nbit(hexdec($valves), 0);
	if ($gasvalveBIT0 == 0) {$gasvalveTXT0 = "Open";}
	elseif ($gasvalveBIT0 == 1) {$gasvalveTXT0 = "Closed";}
	$gasvalve0 = "$gasvalveBIT0:$gasvalveTXT0";

	// Ignition
	$ignitionBIT2 = nbit(hexdec($valves), 2);
	if ($ignitionBIT2 == 0) {$ignitionTXT2 = "Off";}
	elseif ($ignitionBIT2 == 1) {$ignitionTXT2 = "On";}
	$ignition2 = "$ignitionBIT2:$ignitionTXT2";

	// 3-way valve
	$threewayvalveBIT3 = nbit(hexdec($valves), 3);
	if ($threewayvalveBIT3 == 0) {$threewayvalveTXT3 = "CH";}
	elseif ($threewayvalveBIT3 == 1) {$threewayvalveTXT3 = "DHW";}
	$threewayvalve3 = "$threewayvalveBIT3:$threewayvalveTXT3";

	// External 3-way valve
	$threewayvalveBIT4 = nbit(hexdec($valves), 4);
	if ($threewayvalveBIT4 == 0) {$threewayvalveTXT4 = "Open";}
	elseif ($threewayvalveBIT4 == 1) {$threewayvalveTXT4 = "Closed";}
	$threewayvalve4 = "$threewayvalveBIT4:$threewayvalveTXT4";

	// External Gas valve
	$gasvalveBIT6 = nbit(hexdec($valves), 6);
	if ($gasvalveBIT6 == 0) {$gasvalveTXT6 = "Closed";}
	elseif ($gasvalveBIT6 == 1) {$gasvalveTXT6 = "Open";}
	$gasvalve6 = "$gasvalveBIT6:$gasvalveTXT6";

	// Pump
	$pumpBIT0 = nbit(hexdec($pump), 0);
	if ($pumpBIT0 == 0) {$pumpTXT0 = "Off";}
	elseif ($pumpBIT0 == 1) {$pumpTXT0 = "On";}
	$pump0 = "$pumpBIT0:$pumpTXT0";

	// Calorifier Pump
	$pumpBIT1 = nbit(hexdec($pump), 1);
	if ($pumpBIT1 == 0) {$pumpTXT1 = "Open";}
	elseif ($pumpBIT1 == 1) {$pumpTXT1 = "Closed";}
	$pump1 = "$pumpBIT1:$pumpTXT1";

	// External CH Pump
	$pumpBIT2 = nbit(hexdec($pump), 2);
	if ($pumpBIT2 == 0) {$pumpTXT2 = "Off";}
	elseif ($pumpBIT2 == 1) {$pumpTXT2 = "On";}
	$pump2 = "$pumpBIT2:$pumpTXT2";

	// Status report
	$pumpBIT4 = nbit(hexdec($pump), 4);
	if ($pumpBIT4 == 0) {$pumpTXT4 = "Open";}
	elseif ($pumpBIT4 == 1) {$pumpTXT4 = "Closed";}
	$pump4 = "$pumpBIT4:$pumpTXT4";

	// Opentherm Smart Power
	$pumpBIT7 = nbit(hexdec($pump), 7);
	if ($pumpBIT7 == 0) {$pumpTXT7 = "Off";}
	elseif ($pumpBIT7 == 1) {$pumpTXT7 = "On";}
	$pump7 = "$pumpBIT7:$pumpTXT7";
// END translate 'bits' to useful stuff

// Mapping of Status & Sub-Status values
  	$state = hexdec($state);
	if ($state == 0) {$state = "0:Standby";}
	elseif ($state == 1) {$state = "1:Boiler start";}
	elseif ($state == 2) {$state = "2:Burner start";}
	elseif ($state == 3) {$state = "3:Burning CH";}
	elseif ($state == 4) {$state = "4:Burning DHW";}
	elseif ($state == 5) {$state = "5:Burner stop";}
	elseif ($state == 6) {$state = "6:Boiler stop";}
	elseif ($state == 7) {$state = "7:-";}
	elseif ($state == 8) {$state = "8:Controlled stop";}
	elseif ($state == 9) {$state = "9:Blocking mode";}
	elseif ($state == 10) {$state = "10:Locking mode";}
	elseif ($state == 11) {$state = "11:Chimney mode L";}
	elseif ($state == 12) {$state = "12:Chimney mode h";}
	elseif ($state == 13) {$state = "13:Chimney mode H";}
	elseif ($state == 14) {$state = "14:-";}
	elseif ($state == 15) {$state = "15:Manual Heat demand";}
	elseif ($state == 16) {$state = "16:Boiler-frost-protection";}
	elseif ($state == 17) {$state = "17:De-airation";}
	elseif ($state == 18) {$state = "18:Controller temp protection";}
	elseif ($state == 19) {$state = "19:-";}
	elseif ($state == 20) {$state = "20:-";}
	elseif ($state == 999) {$state = "Unkown State";}
	else {$state = "Unknown State";}

	$substate = hexdec($substate);
	if ($substate == 0) {$substate = "0:Standby";}
	elseif ($substate == 1) {$substate = "1:Anti-cycling";}
	elseif ($substate == 2) {$substate = "2:Open hydraulic valve";}
	elseif ($substate == 3) {$substate = "3:Pump start";}
	elseif ($substate == 4) {$substate = "4:Wait for burner start";}
	elseif ($substate == 5) {$substate = "5:-";}
	elseif ($substate == 6) {$substate = "6:-";}
	elseif ($substate == 7) {$substate = "7:-";}
	elseif ($substate == 8) {$substate = "8:-";}
	elseif ($substate == 9) {$substate = "9:-";}
	elseif ($substate == 10) {$substate = "10:Open external gas valve";}
	elseif ($substate == 11) {$substate = "11:Fan to fluegasvalve speed";}
	elseif ($substate == 12) {$substate = "12:Open fluegasvalve";}
	elseif ($substate == 13) {$substate = "13:Pre-purge";}
	elseif ($substate == 14) {$substate = "14:Wait for release";}
	elseif ($substate == 15) {$substate = "15:Burner start";}
	elseif ($substate == 16) {$substate = "16:VPS test";}
	elseif ($substate == 17) {$substate = "17:Pre-ignition";}
	elseif ($substate == 18) {$substate = "18:Ignition";}
	elseif ($substate == 19) {$substate = "19:Flame check";}
	elseif ($substate == 20) {$substate = "20:Interpurge";}
	elseif ($substate == 30) {$substate = "30:Normal internal setpoint";}
	elseif ($substate == 31) {$substate = "31:Limited internal setpoint";}
	elseif ($substate == 32) {$substate = "32:Normal power control";}
	elseif ($substate == 33) {$substate = "33:Gradient control level 1";}
	elseif ($substate == 34) {$substate = "34:Gradient control level 2";}
	elseif ($substate == 35) {$substate = "35:Gradient control level 3";}
	elseif ($substate == 36) {$substate = "36:Flame protection";}
	elseif ($substate == 37) {$substate = "37:Stabilization time";}
	elseif ($substate == 38) {$substate = "38:Cold start";}
	elseif ($substate == 39) {$substate = "39:Limited power Tfg";}
	elseif ($substate == 40) {$substate = "40:Burner stop";}
	elseif ($substate == 41) {$substate = "41:Post purge";}
	elseif ($substate == 42) {$substate = "42:Fan to flue gas valve speed";}
	elseif ($substate == 43) {$substate = "43:Close flue gas valve";}
	elseif ($substate == 44) {$substate = "44:Stop fan";}
	elseif ($substate == 45) {$substate = "45:Close external gas valve";}
	elseif ($substate == 46) {$substate = "46:-";}
	elseif ($substate == 47) {$substate = "47:-";}
	elseif ($substate == 48) {$substate = "48:-";}
	elseif ($substate == 49) {$substate = "49:-";}
	elseif ($substate == 39) {$substate = "39:Heat exchanger protection";}
	elseif ($substate == 60) {$substate = "60:Pump post running";}
	elseif ($substate == 61) {$substate = "61:Pump stop";}
	elseif ($substate == 62) {$substate = "62:Close hydraulic valve";}
	elseif ($substate == 63) {$substate = "63:Start anti-cycle timer";}
	elseif ($substate == 999) {$substate = "999:Unkown Sub-State";}
	elseif ($substate == 255) {$substate = "255:Reset wait time";}
	else {$substate = "Unknown Sub-State";}
	// Combine State & Sub-State to a single variable
	$state = $state . ' / ' . $substate;

	// Locking Codes
	$lockout = hexdec($lockout);
	if ($lockout == 255) {$lockout = "No Locking (Locking 255)";}
	elseif ($lockout == 0) {$lockout = "PSU not connected (Locking 0)";}	
	elseif ($lockout == 1) {$lockout = "SU parameter fault (Locking 1)";}
	elseif ($lockout == 2) {$lockout = "T HeatExch. closed (Locking 2)";}
	elseif ($lockout == 3) {$lockout = "T HeatExch. open (Locking 3)";}
	elseif ($lockout == 4) {$lockout = "T HeatExch. < min. (Locking 4)";}
	elseif ($lockout == 5) {$lockout = "T HeatExch. > max. (Locking 5)";}
	elseif ($lockout == 6) {$lockout = "T Return closed (Locking 6)";}
	elseif ($lockout == 7) {$lockout = "T Return open (Locking 7)";}
	elseif ($lockout == 8) {$lockout = "T Return < min. (Locking 8)";}
	elseif ($lockout == 9) {$lockout = "T Return > max. (Locking 9)";}
	elseif ($lockout == 10) {$lockout = "dT(HeatExch,Return) > max (Locking 10)";}
	elseif ($lockout == 11) {$lockout = "dT(Return,HeatExch) > max (Locking 11)";}
	elseif ($lockout == 12) {$lockout = "STB activated (Locking 12)";}
	elseif ($lockout == 13) {$lockout = "- (Locking 13)";}
	elseif ($lockout == 14) {$lockout = "5x Unsuccessful start (Locking 14)";}
	elseif ($lockout == 15) {$lockout = "5x VPS test failure (Locking 15)";}
	elseif ($lockout == 16) {$lockout = "False flame (Locking 16)";}
	elseif ($lockout == 17) {$lockout = "SU Gasvalve driver error (Locking 17)";}
	elseif ($lockout == 32) {$lockout = "T Flow closed (Locking 32)";}
	elseif ($lockout == 33) {$lockout = "T Flow open (Locking 33)";}
	elseif ($lockout == 34) {$lockout = "Fan out of control range (Locking 34)";}
	elseif ($lockout == 35) {$lockout = "Return over Flow temp. (Locking 35)";}
	elseif ($lockout == 36) {$lockout = "5x Flame loss (Locking 36)";}
	elseif ($lockout == 37) {$lockout = "SU communication (Locking 37)";}
	elseif ($lockout == 38) {$lockout = "SCU-S communication (Locking 38)";}
	elseif ($lockout == 39) {$lockout = "BL input as lockout (Locking 39)";}
	elseif ($lockout == 40) {$lockout = "- (Locking 40)";}
	elseif ($lockout == 41) {$lockout = "PCB temperature (Locking 41)";}
	elseif ($lockout == 42) {$lockout = "Low water pressure (Locking 42)";}
	elseif ($lockout == 43) {$lockout = "No gradient (Locking 43)";}
	elseif ($lockout == 44) {$lockout = "De-air test failed (Locking 44)";}
	elseif ($lockout == 50) {$lockout = "External PSU timeout (Locking 50)";}
	elseif ($lockout == 51) {$lockout = "Onboard PSU timeout (Locking 51)";}
	elseif ($lockout == 52) {$lockout = "GVC lockout (Locking 52)";}
	elseif ($lockout == 999) {$lockout = "Unknown locking code";}

	// Blocking Codes
	$blocking = hexdec($blocking);
	if ($blocking == 255) {$blocking = "No Blocking (Blocking 255)";}
	elseif ($blocking == 0) {$blocking = "PCU parameter fault (Blocking 0)";}
	elseif ($blocking == 1) {$blocking = "T Flow &gt; max.(Blocking 1)";}
	elseif ($blocking == 2) {$blocking = "dT/s Flow > max. (Blocking 2)";}
	elseif ($blocking == 3) {$blocking = "T HeatExch > max.(Blocking 3)";}
	elseif ($blocking == 4) {$blocking = "dT/s HeatExch > max.(Blocking 4)";}
	elseif ($blocking == 5) {$blocking = "dT(heatExch,Return) > max. (Blocking 5)";}
	elseif ($blocking == 6) {$blocking = "dT(Flow,HeatExch) > max.(Blocking 6)";}
	elseif ($blocking == 7) {$blocking = "dT(Flow,Return) > max.(Blocking 7)";}
	elseif ($blocking == 8) {$blocking = "No release signal(Blocking 8)";}
	elseif ($blocking == 9) {$blocking = "L-N swept(Blocking 9)";}
	elseif ($blocking == 10) {$blocking = "Blocking signal ex frost(Blocking 10)";}
	elseif ($blocking == 11) {$blocking = "Blocking signal inc frost(Blocking 11)";}
	elseif ($blocking == 12) {$blocking = "HMI not connected(Blocking 12)";}
	elseif ($blocking == 13) {$blocking = "SCU communication(Blocking 13)";}
	elseif ($blocking == 14) {$blocking = "Min. water pressure(Blocking 14)";}
	elseif ($blocking == 15) {$blocking = "Min. gas pressure(Blocking 15)";}
	elseif ($blocking == 16) {$blocking = "Ident. SU mismatch(Blocking 16)";}
	elseif ($blocking == 17) {$blocking = "Ident. dF/dU table error(Blocking 17)";}
	elseif ($blocking == 18) {$blocking = "Ident. PSU mismatch(Blocking 18)";}
	elseif ($blocking == 19) {$blocking = "Ident. dF/dU needed(Blocking 19)";}
	elseif ($blocking == 20) {$blocking = "Identification running(Blocking 20)";}
	elseif ($blocking == 21) {$blocking = "SU communications lost(Blocking 21)";}
	elseif ($blocking == 22) {$blocking = "Flame lost(Blocking 22)";}
	elseif ($blocking == 23) {$blocking = "-(Blocking 23)";}
	elseif ($blocking == 24) {$blocking = "VPS test failed(Blocking 24)";}
	elseif ($blocking == 25) {$blocking = "Internal SU error(Blocking 25)";}
	elseif ($blocking == 26) {$blocking = "Calorifier sensor error(Blocking 26)";}
	elseif ($blocking == 27) {$blocking = "DHW in sensor error(Blocking 27)";}
	elseif ($blocking == 28) {$blocking = "Reset in progress...(Blocking 28)";}
	elseif ($blocking == 29) {$blocking = "GVC parameter changed(Blocking 29)";}
	elseif ($blocking == 30) {$blocking = " -(Blocking 30)";}
	elseif ($blocking == 31) {$blocking = "31:-Flue gas temp limit exceeded";}
	elseif ($blocking == 32) {$blocking = "32:-Flue gas sensor error";}
	elseif ($blocking == 33) {$blocking = "33:-Internal PCU fault";}
	elseif ($blocking == 34) {$blocking = "34:-Diff between Tfg1 and Tfg2";}
	elseif ($blocking == 35) {$blocking = "35:-Flue gas temp 5* burner stop";}
	elseif ($blocking == 36) {$blocking = "36:-Flow temp 5* burner stop";}
	elseif ($blocking == 41) {$blocking = "41: Dt (Tf,Tr)  deair failed";}
	elseif ($blocking == 43) {$blocking = "43:Grad. low at burnerstart";}
	elseif ($blocking == 44) {$blocking = "44: DeltaT (Tf, Tr) too high";}
	elseif ($blocking == 45) {$blocking = "45: Air pressure too high";}
	elseif ($blocking == 999) {$blocking = "Unknown blocking code";}
// END mapping of Status, Sub-Status, Lockout & Blocking values

// START Display Sample Data as Captured
	echo str_repeat("=", 80) . "$newline";
	echo "Sample Data Received: " . date_format($date, 'Y-m-d H:i:s') . "$newline";
	echo str_repeat("=", 80) . "$newline";
	echo "Flow Temperature: $flowtemperature$deg_symbol$newline";
	echo "Return Temperature: $returntemperature$deg_symbol$newline";
	echo "DHW-in Temperature: $dhwintemperature$deg_symbol$newline";	
	echo "Calorifier Temperature: $calorifiertemperature$deg_symbol$newline";	
	echo "Outside Temperature: $outsidetemperature$deg_symbol$newline";	
	echo "Control Temperature: $controltemperature$deg_symbol$newline";
	echo "Internal Setpoint: $internalsetpoint$deg_symbol$newline";
	echo "CH Setpoint: $chsetpoint$deg_symbol$newline";
	echo "DHW Setpoint: $dhwsetpoint$deg_symbol$newline";
	echo "Room Temperature: $roomtemperature$deg_symbol$newline";
	echo "Room Temp. Setpoint: $thermostat$deg_symbol$newline";
	echo "Boiler Control Temperature: $boilerctrltemperature$deg_symbol$newline";
	echo "Solar Temperature: $solartemperature$deg_symbol$newline"; 
	echo "$newline";
	echo "Fan Speed setpoint: $fanspeedsetpoint"."rpm$newline";
	echo "Fan Speed: $fanspeed"."rpm$newline";
	echo "Ionisation Current: $ionisationcurrent"."μA$newline";
	echo "Pump Speed: $pumppower"."%$newline";
	echo "Hydro Pressure: $pressure"."bar$newline";
	echo "DHW Flow rate: $dhwflowrate"."litres/minute$newline";
	echo "Desired Max.Power from controller: $requiredoutput"."%$newline";
	echo "Output: $availablepower"."%$newline";
	echo "Actual Power from boiler: $actualpower"."%$newline";
	echo "$newline";
	echo "Valve Flags: 0"."$gasvalveBIT6"."0"."$threewayvalveBIT4$threewayvalveBIT3$ignitionBIT2"."0"."$gasvalveBIT0$newline";
	echo "Gas Valve[0]: $gasvalve0$newline";
	echo "Ignition[2]: $ignition2$newline";
	echo "3-Way Valve[3]: $threewayvalve3$newline";
	echo "External 3-Way Valve[4]: $threewayvalve4$newline";
	echo "Gas Valve[6]: $gasvalve6$newline";
	echo "$newline";
	echo "Pump Flags: $pumpBIT7"."00"."$pumpBIT4"."0"."$pumpBIT2$pumpBIT1$pumpBIT0$newline";
	echo "Pump[0]: $pump0$newline";
	echo "Calorifier Pump[1]: $pump1$newline";
	echo "External CH Pump[2]: $pump2$newline";
	echo "Status Report[4]: $pump4$newline";
	echo "Opentherm Smart Power[7]: $pump7$newline";
	echo "$newline";
	echo "Input Flags: $ionisationBIT7$ionisationBIT6$ionisationBIT5"."0"."$ionisationBIT3$ionisationBIT2$ionisationBIT1$ionisationBIT0$newline";
	echo "Shut down Input[0]: $ionisation0$newline";
	echo "Release Input[1]: $ionisation1$newline";
	echo "Ionisation[2]: $ionisation2$newline";
	echo "Flow Switch Detecting DHW[3]: $ionisation3$newline";
	echo "Minimum Gas Pressure[5]: $ionisation5$newline";
	echo "CH Enable[6]: $ionisation6$newline";
	echo "DHW Enable[7]: $ionisation7$newline";
	echo "$newline";
	echo "Heat Request Flags: $heatrequestBIT7$heatrequestBIT6$heatrequestBIT5$heatrequestBIT4$heatrequestBIT3$heatrequestBIT2$heatrequestBIT1$heatrequestBIT0$newline";	
	echo "Mod.controller Connected[0]: $heatrequest0$newline";
	echo "Heat Demand from Modulating Controller[1]: $heatrequest1$newline";
	echo "Heat Demand from ON/OFF controller[2]: $heatrequest2$newline";
	echo "Heat Demand from Frost Protection[3]: $heatrequest3$newline";
	echo "DHW Eco[4]: $heatrequest4$newline";
	echo "DHW Blocking[5]: $heatrequest5$newline";
	echo "Heat Demand from Anti Legionella[6]: $heatrequest6$newline";
	echo "Heat Demand from DHW[7]: $heatrequest7$newline";
	echo "$newline";
	echo "Combined State/Sub-State: $state$newline";
	echo "$newline";
	echo "Lockout E: $lockout$newline";
	echo "Blocking b: $blocking$newline";
	echo str_repeat("=", 80) . "$newline";
// END Display Sample Data as Captured

// Update Domoticz Devices with collected values
// DomoticZ Device ID's
	$flowtemperatureIDX = $ini_array['flowtemperatureIDX'];
	$returntemperatureIDX = $ini_array['returntemperatureIDX'];
	$dhwintemperatureIDX = $ini_array['dhwintemperatureIDX'];
	$calorifiertemperatureIDX = $ini_array['calorifiertemperatureIDX'];
	$outsidetemperatureIDX = $ini_array['outsidetemperatureIDX'];
	$controltemperatureIDX = $ini_array['controltemperatureIDX'];
	$internalsetpointIDX = $ini_array['internalsetpointIDX'];
	$chsetpointIDX = $ini_array['chsetpointIDX'];
	$dhwsetpointIDX = $ini_array['dhwsetpointIDX'];
	$roomtemperatureIDX = $ini_array['roomtemperatureIDX'];
	$thermostatIDX = $ini_array['thermostatIDX'];
	$boilerctrltemperatureIDX = $ini_array['boilerctrltemperatureIDX'];
	$fanspeedsetpointIDX = $ini_array['fanspeedsetpointIDX'];
	$fanspeedIDX = $ini_array['fanspeedIDX'];
	$ionisationcurrentIDX = $ini_array['ionisationcurrentIDX'];
	$pumppowerIDX = $ini_array['pumppowerIDX'];
	$pressureIDX = $ini_array['pressureIDX'];
	$dhwflowrateIDX = $ini_array['dhwflowrateIDX'];
	$requiredoutputIDX = $ini_array['requiredoutputIDX'];
	$availablepowerIDX = $ini_array['availablepowerIDX'];
	$actualpowerIDX = $ini_array['actualpowerIDX'];
	$modulationdemandIDX = $ini_array['modulationdemandIDX'];
	$ignitionIDX = $ini_array['ignitionIDX'];
	$gasIDX = $ini_array['gasIDX'];
	$ionisationIDX = $ini_array['ionisationIDX'];
	$pumpIDX = $ini_array['pumpIDX'];
	$threewayvalveIDX = $ini_array['threewayvalveIDX'];
	$dhwrequestIDX = $ini_array['dhwrequestIDX'];
	$dhwecoIDX = $ini_array['dhwecoIDX'];
	$stateIDX = $ini_array['stateIDX'];
	$lockoutIDX = $ini_array['lockoutIDX'];
	$blockingIDX = $ini_array['blockingIDX'];
	$solartemperatureIDX = $ini_array['solartemperatureIDX'];
// END Device ID's

// Set variables for cURL updates & call udevice function to update
	$DOMOIPAddress = $ini_array['DOMOIPAddress'];
	$DOMOPort = $ini_array['DOMOPort'];
	$Username = $ini_array['Username'];
	$Password = $ini_array['Password'];	

	$DOMOflowtemperature = udevice($flowtemperatureIDX, 0, $flowtemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOreturntemperature = udevice($returntemperatureIDX, 0, $returntemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
 	$DOMOdhwintemperature = udevice($dhwintemperatureIDX, 0, $dhwintemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
 	$DOMOcalorifiertemperature = udevice($calorifiertemperatureIDX, 0, $calorifiertemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOoutsidetemperature = udevice($outsidetemperatureIDX, 0, $outsidetemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOcontroltemperature = udevice($controltemperatureIDX, 0, $controltemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOinternalsetpoint = udevice($internalsetpointIDX, 0, $internalsetpoint, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOchsetpoint = udevice($chsetpointIDX, 0, $chsetpoint, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOdhwsetpoint = udevice($dhwsetpointIDX, 0, $dhwsetpoint, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOroomtemperature = udevice($roomtemperatureIDX, 0, $roomtemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOthermostat = udevice($thermostatIDX, 0, $thermostat, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOboilerctrltemp = udevice($boilerctrltemperatureIDX, 0, $boilerctrltemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOfanspeedsetpoint = udevice($fanspeedsetpointIDX, 0, $fanspeedsetpoint, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOfanSpeed = udevice($fanspeedIDX, 0, $fanspeed, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOionisationCurent = udevice($ionisationcurrentIDX, 0, $ionisationcurrent, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOpumppower = udevice($pumppowerIDX, 0, $pumppower, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOpressure = udevice($pressureIDX, 0, $pressure, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOdhwflowrate = udevice($dhwflowrateIDX, 0, $dhwflowrate, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOrequiredoutput = udevice($requiredoutputIDX, 0, $requiredoutput, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOavailablepower = udevice($availablepowerIDX, 0, $availablepower, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOactualpower = udevice($actualpowerIDX, 0, $actualpower, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOmodulationdemand = udevice($modulationdemandIDX, 0, $heatrequest1, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOignition = udevice($ignitionIDX, 0, $ignition2, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOgas = udevice($gasIDX, 0, $gasvalve0, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOionisation = udevice($ionisationIDX, 0, $ionisation2, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOpump = udevice($pumpIDX, 0, $pump0, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOthreewayvalve = udevice($threewayvalveIDX, 0, $threewayvalve3, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOdhwrequest = udevice($dhwrequestIDX, 0, $heatrequest7, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOdhweco = udevice($dhwecoIDX, 0, $heatrequest4, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOstatus = udevice($stateIDX, 0, str_replace(' ', '%20', $state), $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOstatus = udevice($lockoutIDX, 0, str_replace(' ', '%20', $lockout), $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOstatus = udevice($blockingIDX, 0, str_replace(' ', '%20', $blocking), $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOsolartemperature = udevice($solartemperatureIDX, 0, $solartemperature, $DOMOIPAddress, $DOMOPort, $Username, $Password);		
// END set variables for cURL updates
}

// Time to 'Work the COUNTER Data'
//

function counter_data_dump($data_counter1, $data_counter2, $data_counter3, $data_counter4, $echo_flag, $newline)
{

// Manipulate data & Do a CRC Check	
	$decode_cnt1 = str_split($data_counter1, 2);
	$hexstr_cnt1 = str_split($data_counter1, 52);
	$hexstrPayload_cnt1 = substr($data_counter1, 2, 44);
	$hexstrCRC_cnt1 = substr($data_counter1, 46, 4);
	$crcCalc_cnt1 = crc16_modbus($hexstrPayload_cnt1);	

	$decode_cnt2 = str_split($data_counter2, 2);
	$hexstr_cnt2 = str_split($data_counter2, 52);
	$hexstrPayload_cnt2 = substr($data_counter2, 2, 44);
	$hexstrCRC_cnt2 = substr($data_counter2, 46, 4);
	$crcCalc_cnt2 = crc16_modbus($hexstrPayload_cnt2);

	$decode_cnt3 = str_split($data_counter3, 2);
	$hexstr_cnt3 = str_split($data_counter3, 52);
	$hexstrPayload_cnt3 = substr($data_counter3, 2, 44);
	$hexstrCRC_cnt3 = substr($data_counter3, 46, 4);
	$crcCalc_cnt3 = crc16_modbus($hexstrPayload_cnt3);	
	
	$decode_cnt4 = str_split($data_counter4, 2);
	$hexstr_cnt4 = str_split($data_counter4, 52);
	$hexstrPayload_cnt4 = substr($data_counter4, 2, 44);
	$hexstrCRC_cnt4 = substr($data_counter4, 46, 4);
	$crcCalc_cnt4 = crc16_modbus($hexstrPayload_cnt4);	

	// Concatenate Counter data to work with
	$concat_counter = substr($hexstrPayload_cnt1, 12, 32).substr($hexstrPayload_cnt2, 12, 32).substr($hexstrPayload_cnt3, 12, 32).substr($hexstrPayload_cnt4, 12, 32);
	$decode_counter = str_split($concat_counter, 2);		

	// Write the contents to the file
	$ini_array = parse_ini_file("remeha.ini");
	$path = $ini_array['path_to_logs'];
	$filename = $ini_array['counter_data_log'];
	$file = "$path$filename";
	date_default_timezone_set('Europe/Amsterdam');
	$date = date_create();



	if (($hexstrCRC_cnt1 == $crcCalc_cnt1) && ($hexstrCRC_cnt2 == $crcCalc_cnt2) && ($hexstrCRC_cnt3 == $crcCalc_cnt3) && ($hexstrCRC_cnt4 == $crcCalc_cnt4))
		{
		conditional_echo("Data Integrity Good - CRCs Compute OK$newline", $echo_flag);
		$datatowrite = date_format($date, 'Y-m-d H:i:s') . ' | 02 ' . $hexstrPayload_cnt1 . ' ' . $hexstrCRC_cnt1 . ' ' .'03 | ' . '02 ' . $hexstrPayload_cnt2 . ' ' . $hexstrCRC_cnt2 . ' ' .'03 | ' . '02 ' . $hexstrPayload_cnt3 . ' ' . $hexstrCRC_cnt3 . ' ' .'03 | ' . '02 ' . $hexstrPayload_cnt4 . ' ' . $hexstrCRC_cnt4 . ' ' .'03 |' . "\n";
		file_put_contents($file, $datatowrite, FILE_APPEND);
		conditional_echo("Data written to log: $file$newline", $echo_flag);
		conditional_echo(str_repeat("=", 148) . "$newline$newline", $echo_flag);
		}
	else
		{
		$datatowrite = '**** CRC Error **** | ' . date_format($date, 'Y-m-d H:i:s') . ' | 02 ' . $hexstrPayload_cnt1 . ' ' . $hexstrCRC_cnt1 . ' ' .'03 | ' . '02 ' . $hexstrPayload_cnt2 . ' ' . $hexstrCRC_cnt2 . ' ' .'03 | ' . '02 ' . $hexstrPayload_cnt3 . ' ' . $hexstrCRC_cnt3 . ' ' .'03 | ' . '02 ' . $hexstrPayload_cnt4 . ' ' . $hexstrCRC_cnt4 . ' ' .'03 |' . "\n";
		file_put_contents($file, $datatowrite, FILE_APPEND);
		conditional_echo("Data written to log: $file$newline", $echo_flag);
		conditional_echo("$newline", $echo_flag);
		conditional_echo("************** CRC ERROR!!!! ***********$newline", $echo_flag);
		return;		# Don't continue with updating Counter data
		}

// Counter Info
	$pumphours_ch_dhw = $decode_counter["0"];
	$pumphours_ch_dhw .= $decode_counter["1"];
	$threewayvalvehours = $decode_counter["2"];
	$threewayvalvehours .= $decode_counter["3"];
	$hours_ch_dhw = $decode_counter["4"];
	$hours_ch_dhw .= $decode_counter["5"];
	$hours_dhw = $decode_counter["6"];
	$hours_dhw .= $decode_counter["7"];
	$powerhours_ch_dhw = $decode_counter["8"];
	$powerhours_ch_dhw .= $decode_counter["9"];
	$pumpstarts_ch_dhw = $decode_counter["10"];
	$pumpstarts_ch_dhw .= $decode_counter["11"];
	$nr_threewayvalvecycles = $decode_counter["12"];
	$nr_threewayvalvecycles .= $decode_counter["13"];
	$burnerstarts_dhw = $decode_counter["14"];
	$burnerstarts_dhw .= $decode_counter["15"];
	$tot_burnerstarts_ch_dhw = $decode_counter["16"];
	$tot_burnerstarts_ch_dhw .= $decode_counter["17"];
	$failed_burnerstarts = $decode_counter["18"];
	$failed_burnerstarts .= $decode_counter["19"];
	$nr_flame_loss = $decode_counter["20"];
	$nr_flame_loss .= $decode_counter["21"];
// END Counter Info

//Convert Hex2Dec
// Counters
	$pumphours_ch_dhw = hexdec($pumphours_ch_dhw)*2;	
	$threewayvalvehours = hexdec($threewayvalvehours)*2;
	$hours_ch_dhw = hexdec($hours_ch_dhw)*2;
	$hours_dhw = hexdec($hours_dhw)*1;
	$powerhours_ch_dhw = hexdec($powerhours_ch_dhw)*2;
	$pumpstarts_ch_dhw = hexdec($pumpstarts_ch_dhw)*8;
	$nr_threewayvalvecycles = hexdec($nr_threewayvalvecycles)*8;
	$burnerstarts_dhw = hexdec($burnerstarts_dhw)*8;
	$tot_burnerstarts_ch_dhw = hexdec($tot_burnerstarts_ch_dhw)*8;
	$failed_burnerstarts = hexdec($failed_burnerstarts)*1;
	$nr_flame_loss = hexdec($nr_flame_loss)*1;
// END Counters
// END Convert Hex2Dec

// START Display Counters
	echo str_repeat("=", 80) . "$newline";
	echo "Counters Received: " . date_format($date, 'Y-m-d H:i:s') . "$newline";
	echo str_repeat("=", 80) . "$newline";
	echo "Hours run pump CH+DHW: $pumphours_ch_dhw hours$newline";
	echo "Hours run 3-way valve DHW: $threewayvalvehours hours$newline";
	echo "Hours run CH+DHW: $hours_ch_dhw hours$newline";
	echo "Hours run DHW: $hours_dhw hours$newline";
	echo "Power Supply available hours: $powerhours_ch_dhw hours$newline";
	echo "Pump starts CH+DHW: $pumpstarts_ch_dhw starts$newline";
	echo "Number of 3-way valve cycles: $nr_threewayvalvecycles cycles$newline";
	echo "Burner Starts DHW: $burnerstarts_dhw starts$newline";
	echo "Total Burner Starts CH+DHW: $tot_burnerstarts_ch_dhw starts$newline";
	echo "Failed burner starts: $failed_burnerstarts starts$newline";
	echo "Number of flame loss: $nr_flame_loss times$newline";
	echo str_repeat("=", 40) . "$newline";
// END Display Counters

// Update Domoticz Devices with collected values
// DomoticZ Device ID's
	$pumphours_ch_dhwIDX = $ini_array['pumphours_ch_dhwIDX'];
	$threewayvalvehoursIDX = $ini_array['threewayvalvehoursIDX'];
	$hours_ch_dhwIDX = $ini_array['hours_ch_dhwIDX'];
	$hours_dhwIDX = $ini_array['hours_dhwIDX'];
	$powerhours_ch_dhwIDX = $ini_array['powerhours_ch_dhwIDX'];
	$pumpstarts_ch_dhwIDX = $ini_array['pumpstarts_ch_dhwIDX'];
	$nr_threewayvalvecyclesIDX = $ini_array['nr_threewayvalvecyclesIDX'];
	$burnerstarts_dhwIDX = $ini_array['burnerstarts_dhwIDX'];
	$tot_burnerstarts_ch_dhwIDX = $ini_array['tot_burnerstarts_ch_dhwIDX'];
	$failed_burnerstartsIDX = $ini_array['failed_burnerstartsIDX'];
	$nr_flame_lossIDX = $ini_array['nr_flame_lossIDX'];
// END Device ID's

// Set variables for cURL updates & call udevice function to update
	$DOMOIPAddress = $ini_array['DOMOIPAddress'];
	$DOMOPort = $ini_array['DOMOPort'];
	$Username = $ini_array['Username'];
	$Password = $ini_array['Password'];	

	$DOMOpumphours_ch_dhw = udevice($pumphours_ch_dhwIDX, 0, $pumphours_ch_dhw, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOthreewayvalvehours = udevice($threewayvalvehoursIDX, 0, $threewayvalvehours, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOhours_ch_dhw = udevice($hours_ch_dhwIDX, 0, $hours_ch_dhw, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOhours_dhw = udevice($hours_dhwIDX, 0, $hours_dhw, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOpowerhours_ch_dhw = udevice($powerhours_ch_dhwIDX, 0, $powerhours_ch_dhw, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOpumpstarts_ch_dhw = udevice($pumpstarts_ch_dhwIDX, 0, $pumpstarts_ch_dhw, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOnr_threewayvalvecycles = udevice($nr_threewayvalvecyclesIDX, 0, $nr_threewayvalvecycles, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOburnerstarts_dhw = udevice($burnerstarts_dhwIDX, 0, $burnerstarts_dhw, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOtot_burnerstarts_ch_dhw = udevice($tot_burnerstarts_ch_dhwIDX, 0, $tot_burnerstarts_ch_dhw, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOfailed_burnerstarts = udevice($failed_burnerstartsIDX, 0, $failed_burnerstarts, $DOMOIPAddress, $DOMOPort, $Username, $Password);
	$DOMOnr_flame_loss = udevice($nr_flame_lossIDX, 0, $nr_flame_loss, $DOMOIPAddress, $DOMOPort, $Username, $Password);
// END set variables for cURL updates
}

// Function to update Domoticz using cURL
//
function udevice($idx, $nvalue, $svalue, $DOMOIPAddress, $DOMOPort, $Username, $Password) 
{
	
// Comment if you don't want to update Domoticz	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, "http://$Username:$Password@$DOMOIPAddress:$DOMOPort/json.htm?type=command&param=udevice&idx=$idx&nvalue=$nvalue&svalue=$svalue");	
	curl_exec($ch);
	curl_close($ch);
// End Comment if you don't want to update Domoticz

// Sleep for 1/4 sec - in case system stressed/slow
	// usleep(250000);
	
// Debug cURL string
	// $newline ="\n";
	// echo "Debug Info - http://$Username:$Password@$DOMOIPAddress:$DOMOPort/json.htm?type=command&param=udevice&idx=$idx&nvalue=$nvalue&svalue=$svalue$newline";
	// echo "IDX: $idx Value: $svalue$newline";
}

// Function to work out BIT flags
// 
function nbit($number, $n) 
{
	return ($number >> $n) & 1;	#BITS Numbered 0-7
}

// Function to debug BITs
//
function showbits($val, $byte)
{
// Show workings of BITS
	$valDEC = hexdec($val);
	$valBIN = base_convert($val, 16, 2);
	$valBIN = sprintf( "%08d", $valBIN);

	$valBITx0 = ($valDEC & (1<<0));
	$valBITx1 = ($valDEC & (1<<1));
	$valBITx2 = ($valDEC & (1<<2));
	$valBITx3 = ($valDEC & (1<<3));
	$valBITx4 = ($valDEC & (1<<4));
	$valBITx5 = ($valDEC & (1<<5));
	$valBITx6 = ($valDEC & (1<<6));
	$valBITx7 = ($valDEC & (1<<7));
	$nbitX0 = nbit($valDEC, 0);
	$nbitX1 = nbit($valDEC, 1);
	$nbitX2 = nbit($valDEC, 2);
	$nbitX3 = nbit($valDEC, 3);
	$nbitX4 = nbit($valDEC, 4);
	$nbitX5 = nbit($valDEC, 5);
	$nbitX6 = nbit($valDEC, 6);
	$nbitX7 = nbit($valDEC, 7);

	echo str_repeat("=", 40) . "$newline";
	echo "Content & Value of BITS: $byte$newline";
	echo "Value (HEX, Dec, Bin): $val, $valDEC, $valBIN$newline";
	echo str_repeat("=", 40) . "$newline";
	echo "nBit0: 	$nbitX0" .' '."| Bit0: 	$valBITx0$newline";
	echo "nBit1: 	$nbitX1" .' '."| Bit1: 	$valBITx1$newline";
	echo "nBit2: 	$nbitX2" .' '."| Bit2: 	$valBITx2$newline";
	echo "nBit3: 	$nbitX3" .' '."| Bit3: 	$valBITx3$newline";
	echo "nBit4: 	$nbitX4" .' '."| Bit4: 	$valBITx4$newline";
	echo "nBit5: 	$nbitX5" .' '."| Bit5: 	$valBITx5$newline";
	echo "nBit6: 	$nbitX6" .' '."| Bit6: 	$valBITx6$newline";
	echo "nBit7: 	$nbitX7" .' '."| Bit7: 	$valBITx7$newline";
	echo str_repeat("=", 40) . "$newline";
// END workings of BITS
}

// ...and the CRC16 Modbus Check to check data integrity
//
function crc16_modbus($msg)
{
	$data = pack('H*',$msg);
	$crc = 0xFFFF;
	for ($i = 0; $i < strlen($data); $i++)
	{
		$crc ^=ord($data[$i]);
		for ($j = 8; $j !=0; $j--)
		{
			if (($crc & 0x0001) !=0)
			{
				$crc >>= 1;
				$crc ^= 0xA001;
			}
			else $crc >>= 1;
			}
		}

	$crc_semi_inverted = sprintf('%04x', $crc);
	$crc_modbus = substr($crc_semi_inverted, 2, 2).substr($crc_semi_inverted, 0, 2);
	$crc_modbus = hexdec($crc_modbus);
	return sprintf('%04x', $crc_modbus);
}

// Function to convert HEX to ASCII for Device ID, Serial, etc.
// usage (e.g. $hexstr = "43616c656e7461202020202020202020";) whic is "Calenta"
function hex2str($hex)
{
	$str = "";
	for($i=0;$i<strlen($hex);$i+=2) $str .= chr(hexdec(substr($hex, $i, 2)));
	return $str;
}

// Function to show statements or not based on a flag
// If variable $echo_flag = 1, show the output of "echo", otherwise hide it
function conditional_echo($string,$echo_flag)
{
	if ($echo_flag == 1)
	{
		echo $string;
	}
}

// Function to connect to ESP8266
//
function connect_to_esp($ESPIPAddress, $ESPPort, $retries, $newline)
{
	$connected = false;
	$retry = 0;
	
	// Keep looping until connected or met no. of retries if $retries is not zero
	while (!$connected && ($retries==0 || ($retries>0 && $retry<$retries)))
	{
		// try connecting to the ESP8266
		$fp = fsockopen($ESPIPAddress, $ESPPort, $errno, $errstr, 5);
		
		if ($fp)
		{
			return $fp;
			// connection was successful
		}
		else
		{
			echo "Unable to establish connection to ".$ESPIPAddress.":".$ESPPort." - Error:$errno:".$errstr."$newline";
			echo "Trying to reset ESP8266 @ $ESPIPAddress $newline";
			file_get_contents("http://$ESPIPAddress/log/reset");
		}
		sleep(10); // sleep for 10 seconds before trying again
		$retry++;
	}
	return $fp;
}

// Function to convert 'Signed HEX Values' to Decimal
//
function hexdecs($hex)
{
    $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex);
    $dec = hexdec($hex);
    $max = pow(2, 4 * (strlen($hex) + (strlen($hex) % 2)));
    $_dec = $max - $dec;
    return $dec > $_dec ? -$_dec : $dec;
}

function cls()
{
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
	{
    		echo '\r\n';
	} 
	elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'LIN')
	{
		array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
	}
	elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'DAR')
	{
		array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
	}

}
?>
