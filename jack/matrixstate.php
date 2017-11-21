<?php
header("Content-type: text/plain; charset=utf8");
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

$patchLog = "";
function DoNothing($foo) {}
$debug = "DoNothing";

const kLogFile = "/var/log/matrix/patchlog.txt";
const kPokeFifo = "/dev/shm/jack_daemon_pokertron.fifo";


function LogLine($line)
{
	global $logFile;
	echo($line."\n");
	fwrite($logFile, date(DATE_ATOM)." ".$line."\n");
	fflush($logFile);
}

require_once "jack_matrix.php";
require_once "jack_patching.php";
require_once "commandproc.php";

AddFixedCommand(new GetMatrixState(true, false), kMatrixStateCmd);
AddFixedCommand(new ChangePatch(), kChangePatchCmd);
ProcessCommands(ExtractKeyCmds($_GET));
ProcessDeferredCommands();

if ($patchLog != "")
{
	global $logFile;
	global $debug;
	$logFile = fopen(kLogFile, "a");
	$debug = "LogLine";
	Debug($patchLog);
	// ProcessCommands($cmd);

	$fifo = fopen(kPokeFifo,"w");
	if ($fifo)
	{
		$res = fwrite($fifo,"MatrixUpdate\n");
		Debug("Poked fifo: res=".$res);
		fclose($fifo);
	}
	else
	{
		Debug("Unable to open fifo: ".kPokeFifo);
	}
	usleep(200000);
//	if (ApplyPatching())
//	{
//		PopulateState();
//	}
};


?>
