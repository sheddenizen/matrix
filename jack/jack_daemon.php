#!/usr/bin/php
<?php

declare(ticks = 1);

// Log file path
const kLogFile = "/var/www/html/jack/patchlog.txt";
// When log file reaches size limit, it is archived to this file, replacing the previous
const kLogFileArch = "/var/www/html/jack/patchlog.txt.1";
// Threshold for archival
const kLogSizeLimit = 1000000;

const kPokeFifo = "/dev/shm/jack_daemon_pokertron.fifo";

$logFile = NULL;
$logStat = stat(kLogFile);
$logSize = $logStat ? $logStat["size"] : 0;

function LogLine($line)
{
    global $logFile;
    global $logSize;
    
    if ($logSize >= kLogSizeLimit)
    {
        if (!is_null($logFile))
        {
            fclose($logFile);
            $logFile = NULL;
            $logSize = 0;
        }
        rename(kLogFile, kLogFileArch);
    }
    if (is_null($logFile))
    {
        $logFile = fopen(kLogFile, "a");
    }
    echo($line."\n");
    $utime = microtime(true); // Seconds 1970 in floating point
    $line = date("y-m-d H:i:s", $utime).".".sprintf('%04d', ($utime - floor($utime)) * 10000)." ".$line."\n";
    fwrite($logFile, $line);
    $logSize += strlen($line);
    fflush($logFile);
}

$debug = "LogLine";

require_once "jack_patching.php";

$externalNotify = false;

function HandleUsr1( int $signo )
{
    echo("Got signal ".$signo."\n");
	$externalNotify = true;
}

pcntl_signal(SIGUSR1, "HandleUsr1", false);
umask(0155);
posix_mkfifo(kPokeFifo,0622);
umask(0022);

while (1)
{
    echo ("Patching daemon\n");
#    $lsp = popen ("/var/www/html/jack/jack_evmon", "r");
    $lsp = popen ("stdbuf -o L /usr/bin/jack_evmon", "r");
    $fifo = fopen (kPokeFifo, "r+");
    stream_set_blocking($fifo, FALSE);   

    $newPort = false;
    $removedPort = false;
    $newConnect = false;
    $newDisconnect = false;

    if ($lsp === FALSE)
    {
        echo ("Unable to start ev_mon\n");
		exit(1);
	}
	echo ("Monitoring...\n");
	$res = FALSE;
	$timeout = 0;
	$patchingActive = false;

	while (!feof($lsp))
	{
		$write = NULL;
		$expected = NULL;
		$read = array($lsp, $fifo);
//		$read = array($lsp);
		$changed = stream_select($read, $write, $expected, $timeout / 1000, ($timeout % 1000) * 1000 );
		
		$timeout = 20000;
		
		LogLine("Changed: ".$changed);
		if ($changed == 0)
		{
			PopulateState();
			if ($newPort || $externalNotify ||
				( !$patchingActive && ( $newConnect || $newDisconnect )))
			{
				$reason = ($externalNotify ? "External, " : "").($newPort ? "New Port, " : "")
					.($newConnect ? "New Connection, " : "").($newConnect ? "New Disconnection, " : "")
					.($patchingActive ? "(Patching already in progress)" : "");
				if (ApplyPatching())
				{
					LogLine("Applied patching: ".$reason);
					# PopulateState();
				}
				else
				{
					LogLine("No additional patching required: ".$reason);
				}
				$patchingActive = true;
				$externalNotify = false;
			}
			else
			{
				$patchingActive = false;
			}
			$newPort = $removedPort = false;
			$newConnect = $newDisconnect = false;
		}
		else
		{
			foreach ($read as $fd)
			{
				if ($fd == $lsp)
				{
					$res = rtrim(fgets($lsp, 256));
					
					pcntl_signal_dispatch();
					
					if ($res !==false)
					{
						echo $res."\n";
						$newPort |= preg_match("/^Port.* registered/", $res);
						$removedPort |= preg_match("/^Port.* unregistered/", $res);
						$newConnect |= preg_match("/^Ports.* connected/", $res);
						$newDisconnect |= preg_match("/^Port.* disconnected/", $res);
					
						if ($newPort || $removedPort || $newConnect || $newDisconnect)
						{
							LogLine("Jack Activity: ".$res);
							$timeout = 20;
						}
						else
						{
							LogLine("Uninteresting: ".$res);							
						}
						
						if (preg_match("/^Graph reordered/", $res))
						{
							LogLine("Graph reordered - not supported anymore?");
						}
					}
				}
				if ($fd == $fifo)
				{
					LogLine("Poke from fifo");
					$res=fread($fifo, 256);
					LogLine("Got: $res");
					if (feof($fifo))
					{
						fclose($fifo);
						$fifo = fopen (kPokeFifo, "r+");
						stream_set_blocking($fifo, FALSE);   
					}
					$timeout = 0;
					$externalNotify = TRUE;
				}
			}
		}
	}
	pclose($lsp);
	fclose($fifo);
    sleep(5);
    LogLine("Broken pipe");
} 
unlink(kPokeFifo);
fclose($logFile);

?>
