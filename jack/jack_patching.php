<?php

require_once "jack_mysql.php";

const kPatchSeparator = "=>";
const kDeletePortState = "DELETE FROM `jackPortState`;";
const kDeletePatchState = "DELETE FROM `jackPatchState`;";
const kDeleteRequiredPatch = "DELETE FROM `requiredPatch`;";
const kSetJackStatePreamble = "INSERT INTO `jackPortState` (`index`, `port`, `isOutput`) VALUES";
const kSetJackPatchPreamble = "INSERT INTO `jackPatchState` (`output`,`input`) VALUES ";

const kReconcilePortState =
"
UPDATE

`jackPortState`, `jackPort`, `jackClient`

SET 

jackPortState.jackPort = jackPort.index

WHERE

jackPortState.port = CONCAT(jackClient.name, ':', jackPort.name) AND
jackPort.jackClient = jackClient.index AND
jackPort.isOutput = jackPortState.isOutput	
;
";

// For all known ports wether registered or not, lists pairs that should be patched
const kGenerateRequiredPatchList =
"
INSERT IGNORE INTO requiredPatch

SELECT

jpOut.index AS outIndex,
jpIn.index AS inIndex,
CONCAT(jcOut.name, ':', jpOut.name) AS outName,
CONCAT(jcIn.name, ':', jpIn.name)  AS inName

FROM 

jackClient AS jcIn,
jackPort AS jpIn,
portMap AS portIn,
patch,
portMap AS portOut,
jackPort AS jpOut,
jackClient AS jcOut

WHERE 

jcIn.index = jpIn.jackClient AND 
jpIn.index = portIn.jackPort AND
jpIn.isOutput = '0' AND
portIn.line = patch.input AND
portOut.line = patch.output AND 
jpOut.isOutput = '1' AND
jcOut.index = jpOut.jackClient AND
jpOut.index = portOut.jackPort AND
portIn.channel = portOut.channel
";

// With all known registered ports, lists the port pairs that need to be patched
const kGetDesiredPatches =
"
SELECT DISTINCT

psOut.index AS outIndex,
psIn.index AS inIndex,
psOut.port AS outName,
psIn.port AS inName

FROM 

jackPortState AS psIn,
portMap AS portIn,
patch,
portMap AS portOut,
jackPortState AS psOut

WHERE 

psIn.jackPort = portIn.jackPort AND 
psIn.isOutput = '0' AND
portIn.line = patch.input AND
portOut.line = patch.output AND 
psOut.isOutput = '1' AND
psOut.jackPort = portOut.jackPort AND 
portIn.channel = portOut.channel
";


// With all known existing ports, lists the port pairs that are actually patched
define("kGetActualPatches",
"
SELECT 

psOut.index AS outIndex,
psIn.index AS inIndex,
psOut.port AS outName,
psIn.port AS inName

FROM 

jackPortState AS psIn,
jackPatchState,
jackPortState AS psOut 

WHERE

psIn.isOutput = '0' AND
jackPatchState.input = psIn.index AND
jackPatchState.output = psOut.index AND
psOut.isOutput = '1' AND
psIn.jackPort IS NOT NULL AND
psOut.jackPort IS NOT NULL
");

/*
const kGetActualPatches =
"
SELECT DISTINCT

psOut.index AS outIndex,
psIn.index AS inIndex,
psOut.port AS outName,
psIn.port AS inName

FROM 

jackPortState AS psIn,
portMap AS portIn,
jackPatchState,
portMap AS portOut,
jackPortState AS psOut 

WHERE

psIn.jackPort = portIn.jackPort AND
psIn.isOutput = '0' AND
jackPatchState.input = psIn.index AND
jackPatchState.output = psOut.index AND
psOut.isOutput = '1' AND
psOut.jackPort = portOut.jackPort
";
*/
define("kGetPatchList",
"
SELECT

desiredPatches.outName,
desiredPatches.inName

FROM

(".kGetDesiredPatches.") AS desiredPatches

LEFT OUTER JOIN 

(".kGetActualPatches.") AS actualPatches 

ON

desiredPatches.inIndex = actualPatches.inIndex AND
desiredPatches.outIndex = actualPatches.outIndex

WHERE

actualPatches.inIndex IS NULL  AND
actualPatches.outIndex IS NULL
;
");

define("kGetUnpatchList",
"
SELECT

actualPatches.outName,
actualPatches.inName

FROM

(".kGetActualPatches.") AS actualPatches 

LEFT OUTER JOIN 

(".kGetDesiredPatches.") AS desiredPatches

ON

desiredPatches.inIndex = actualPatches.inIndex AND
desiredPatches.outIndex = actualPatches.outIndex

WHERE

desiredPatches.inIndex IS NULL  AND
desiredPatches.outIndex IS NULL
;
");

const kCmdLimit = 4000;
const kParamPairLimit = 128;
const kConnectCmd = "/var/www/html/jack/jack_connect"; 
const kDisconnectCmd = "/var/www/html/jack/jack_disconnect";

	function GetLspOutput()
	{
        $lsp = popen ("/usr/bin/jack_lsp -t -c -p", "r");
        $res = "";
        do
        {
            $chunk = fread($lsp, 100000);
            $res .= $chunk;
        } while ($chunk !== "");

        #echo ("<pre>\n");
        #echo ($res);
        #echo ("\n</pre>\n");

        pclose($lsp);

        $lines = explode("\n", $res);
		return $lines;
	}

    function GetDesiredPatches()
    {
        $result = array();
        SetSqlQuery(kGetDesiredPatches, false);
        do
        {
            $res = GetMultipleResult();
            if ($res)
            {
                $result[$res[2]]=$res[3];
                $result[$res[3]]=$res[2];
            }
        } while ($res);
        return $result;
    }
   
    function PopulateState()
    {	
        UpdateSQL("LOCK TABLES jackClient READ, jackPort READ, jackPortState WRITE, jackPatchState WRITE;", true);

        $lines = GetLspOutput();
		Debug("Processing Lsp output");
        $outputs = array();
        $inputs = array();
        $clients = array();
		$index = 0;
		$patchTable = array();
        		
        foreach($lines as $line)
        {
            if (preg_match("/^[ \t]+([^ ].*)$/", $line, $what))
            {
                if (preg_match("/properties:/", $line))
                {
                    $isOutput = preg_match("/output/", $line);
                }
                else if (preg_match("/32 bit float mono audio/", $line))
                {
					// This is a vaild audio stream - now do something with it
					if ($isOutput)
					{
						$io = &$outputs;
					}
					else
					{
						$io = &$inputs;
					}
					if (!isset($io[$portName]))
					{
						$io[$portName] = ++$index;
						$portIdx = $index;
					}
					else
					{
						$portIdx = $io[$portName];
					}
					if ($isOutput)
					{
						foreach($patches as $patch)
						{
							if (!isset($inputs[$patch]))
							{
								$inputs[$patch] = ++$index;
								$portInIdx = $index;
							}
							else
							{
								$portInIdx = $inputs[$patch];
							}
							$patchTable[$portIdx][$portInIdx] = 1;
						}
					}

                }
                else if (preg_match("/8 bit raw midi/", $line))
                {}
                else
				{
					$patches[]= $what[1];
				}
            }
            else
            {
				$isOutput = false;
				$patches = array();
				$portName = $line;
            }      
        }
		$portDirs = array(0=>&$inputs, 1=>&$outputs);
		$insertCmd = kSetJackStatePreamble;
		foreach ($portDirs as $isOutput => $portDir)
		{
			foreach($portDir as $port => $index)
			{
				$insertCmd .= "\n('$index', '$port', '$isOutput'),";
			}
		}
		$insertCmd[strlen($insertCmd)-1] = ";";
		$inspatchCmd = kSetJackPatchPreamble;
		// echo("\n<!--\n$insertCmd\n-->\n");
		foreach ($patchTable as $output => $inputs)
		{
			foreach ($inputs as $input => $dummy)
			{
				$inspatchCmd .= "('$output', '$input'),";
			}
		}
		$inspatchCmd[strlen($inspatchCmd)-1] = ";";
		// echo("\n<!--\n$inspatchCmd\n-->\n");
		
		UpdateSQL(kDeletePortState, true);
		UpdateSQL(kDeletePatchState, true);
		UpdateSQL($insertCmd, true);
		if (count($patchTable))
		{
			UpdateSQL($inspatchCmd, true);
		}
		UpdateSQL(kReconcilePortState, true);
        
        UpdateSQL("UNLOCK TABLES;", true);
    }
 
	function ApplyPatching()
	{
        global $patchLog;
        $debugStr = "";
        
		$result =false;
		$actions = array(kGetPatchList=>kConnectCmd, kGetUnpatchList=>kDisconnectCmd);

        foreach ($actions as $query=>$cmdPrg)
        {
			// Debug($query);
            SetSqlQuery($query, false);
            $cmd = $cmdPrg;
            $pairCount = 0;
            do
            {
                $res = GetMultipleResult();

                if ((!$res && $pairCount) || strlen($cmd) >= kCmdLimit || $pairCount >= kParamPairLimit)
                {
                    $debugStr .= "$cmd\n".shell_exec("$cmd 2>&1")."\n";
                    $cmd = $cmdPrg;
                    $pairCount = 0;
                    $result = true;
                }            

                if ($res)
                {
                    $cmd .= " '$res[0]' '$res[1]'";
                    ++$pairCount;
                }
            } while ($res && $pairCount);
        }
		Debug($debugStr);
        $patchLog .= $debugStr;
        
		return $result;
	}
	
	function AddNewPort($jps)
	{
		$jpsRes = GetSingleResult("SELECT * FROM jackPortState WHERE jackPortState.index = $jps;");
		if (!$jpsRes)
		{
			Debug("Unable to get entry for jackPortState, entry $jps");
			return null;
		}
		if ($jpsRes["jackPort"])
		{
			// Already exists - just return the index
			Debug("Port $jps already exists as jackPort ".$jpsRes["jackPort"]);
			return $jpsRes["jackPort"];
		}
		$clientName = strstr($jpsRes["port"], ":", true);
		if (!$clientName)
		{
			Debug("Unable to split $jps, entry:");
			Debug($jpsRes["port"]);
			return null;
		}
		$portName = substr($jpsRes["port"], strlen($clientName) + 1);
		
		$clientRes = GetSingleResult("SELECT * FROM jackClient WHERE jackClient.name = '$clientName';");
		if ($clientRes)
		{
			Debug("Found existing client, $clientIndex for $clientName/$portName");
			$clientIndex = $clientRes["index"];
		}
		else
		{
			Debug("Adding new client, for $clientName/$portName");
			$clientIndex = UpdateSQL("INSERT IGNORE INTO jackClient (`name`) VALUES ('$clientName');", true);
		}
		if (!$clientIndex)
		{
			Debug("Unable to get/create client entry for $clientName");
			return null;
		}
		$portIndex = UpdateSQL("INSERT INTO `jackPort` (`name`,`jackClient`,`isOutput`) VALUES ('$portName','$clientIndex','".$jpsRes["isOutput"]."');", true);
		return $portIndex ? $portIndex : null;
	}

    function GenerateRequiredPatchList()
    {
        UpdateSQL(kDeleteRequiredPatch, true);
        UpdateSQL(kGenerateRequiredPatchList, true);
    }

?>
