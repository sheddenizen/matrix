<?php

require_once "command.php";
require_once "jack_mysql.php";
require_once "jack_queries.php";
require_once "jack_patching.php";


function GetPortsQuery($isOutput, $line, $isMeter)
{
$meterCond = $isMeter ? " AND jackClient.isMeter = '1' " : "";

return
"
SELECT 

jackPort.index AS jackPortIndex,
jackPort.channels+0 AS channels,
CONCAT(jackClient.name,':',jackPort.name) AS jackId,
(
SELECT BIT_OR(1 << portMap.channel) FROM portMap
WHERE portMap.jackPort = jackPort.index AND portMap.line = '$line'
) AS memberChannels,
'1' AS portKnown,
NULL AS jackPortStateIndex,
EXISTS(SELECT * FROM jackPortState WHERE jackPortState.jackPort = jackPort.index) AS portExists,
jackClient.isMeter AS isMeter

FROM jackPort, jackClient

WHERE

jackPort.jackClient = jackClient.index AND
jackPort.isOutput = '$isOutput'
$meterCond

UNION

SELECT

NULL AS jackPortIndex,
'3' AS channels,
jackPortState.port AS jackId,
'0' AS memberChannels,
'0' AS portKnown,
jackPortState.index AS jackPortStateIndex,
'1' AS portExists,
'$isMeter' AS isMeter

FROM jackPortState

WHERE

jackPortState.jackPort IS NULL AND
jackPortState.isOutput = '$isOutput' AND ".($isMeter ? "" : "NOT")."
EXISTS (SELECT * FROM jackClient WHERE jackClient.isMeter = '1' AND jackClient.name = SUBSTRING_INDEX(jackPortState.port, ':', 1))

ORDER BY portKnown DESC, portExists DESC, jackPortIndex, jackPortStateIndex;
";
}

// Get all lines ordered by output/input indicating if they
// belong to the specified matrix
function GetLinesQuery($matrix, $metersOnly = false)
{
$meterCond = $metersOnly ?
"
WHERE NOT EXISTS (SELECT * FROM jackClient,jackPort,portMap
WHERE jackClient.isMeter = '0' AND portMap.line = line.index AND portMap.jackPort = jackPort.index AND jackPort.jackClient = jackClient.index)
"
: "";
    
return
"
SELECT

line.index,
line.name,
line.isOutput,
EXISTS (SELECT * FROM matrixIO WHERE matrixIO.matrix = '$matrix' AND matrixIO.line = line.index) AS inMatrix,
EXISTS (SELECT * FROM meter WHERE meter.line = line.index AND meter.matrix = '$matrix') AS isMxMeter

FROM

line 

$meterCond

ORDER BY isOutput DESC, line.index;
";
}

function GetMeterLinesQuery($matrix)
{
return
"
SELECT

line.index,
line.name,
line.isOutput,
(SELECT COUNT(*) FROM meter WHERE meter.matrix = '$matrix' AND meter.line = line.index) AS inMatrix

FROM jackClient,jackPort,portMap

WHERE portMap.line = line.index AND portMap.jackPort = jackPort.index AND jackPort.jackClient = jackClient.index AND jackClient.isMeter = '1') AS isMeter
;
";
}

function GetLinesForCfgQuery($isOutput, $isMeter)
{
$meterCond = $isMeter ? "NOT" : "";

return
"
SELECT line.*, GROUP_CONCAT(CONCAT(jackClient.name, jackPort.name) SEPARATOR ', ') AS ports

FROM 

jackClient, jackPort, portMap, line

WHERE 

jackPort.jackClient = jackClient.index AND 
portMap.jackPort = jackPort.index AND 
portMap.line = line.index AND
line.isOutput = '$isOutput' AND
$meterCond EXISTS
(
SELECT * FROM jackClient,jackPort,portMap
WHERE
jackClient.isMeter = '0' AND portMap.line = line.index AND
portMap.jackPort = jackPort.index AND
jackPort.jackClient = jackClient.index
)


GROUP BY line.index

UNION

SELECT line.*, NULL

FROM

line

WHERE

line.isOutput = '$isOutput' AND
NOT EXISTS (SELECT * FROM portMap, jackPort WHERE portMap.line = line.index AND portMap.jackPort = jackPort.index)

ORDER BY name
;
";
}

class SetPortLine extends UpdateCommand
{
	function __construct($port, $line, $channel, $jps)
    {
        $this->port = $port;
        $this->line = $line;
        $this->channel = $channel;
        $this->jps = $jps;
    }
    public function Exec($value = null)
	{
        $port = $this->port;
        if (!$this->port)
        {
            $port = AddNewPort($this->jps);
        }
        if ($port > 0)
        {
            $updQuery = "INSERT INTO `portMap` (`line`, `jackPort`,`channel`) ".
                        "VALUES ('$this->line', '$port', '$this->channel');";
            // echo($updQuery."<br>\n");
            UpdateSQL($updQuery, false);
        }
        else
        {
            echo("Unable to add port to line, $this->line, $this->jps<br>\n");
            Debug("Unable to add port to line, $this->line, $this->jps");
        }
	}
    private $port;
    private $line;
    private $channel;
    private $jps;
};

class ClearPortLines extends UpdateCommand
{
	function __construct($line)
    {
        $this->line = $line;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $updQuery = "DELETE FROM `portMap` WHERE `line`= '$this->line';";
        Debug($updQuery);
        $patchLog .= $updQuery."\n";
		UpdateSQL($updQuery, false);
	}
    private $line;
};

class EditLine extends DisplayCommand
{
	function __construct($line, $isMeter)
    {
        $this->line = $line;
        $this->isMeter = $isMeter;
    }
    public function Exec($value = null)
	{
        $h = new HtmlState();
        
       	$h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));

        $info = GetSingleResult("SELECT * FROM line WHERE line.index='$this->line';");
        Debug($info);
        $h->LeafElem("h3",array(), "Edit ".($info["isOutput"] ? "Output" : ($this->isMeter ? "Meter" : "Input")));

        $index = $info["index"];

        $h->LeafElem("p", array("class"=>"nameLabel"), "Name: ");
        $h->LeafElem("input", array("type"=>"text", "class"=>"txtName", "value"=>$info["name"],
                "name"=>AddSessionCommand(new SetField($index, "name", "line"))));

        $h->LeafElem("p", array("class"=>"descEdit"), "Description: ");
        $h->LeafElem("textarea", array("class"=>"txtDesc",
                "name"=>AddSessionCommand(new SetField($index, "description", "line"))),
                $info["description"]);
        $h->LeafElem("br");

        $h->LeafElem("input", array("type"=>"submit",
                "name"=>AddSessionCommand(new ClearPortLines($index))));
        $h->LeafElem("input", array("type"=>"hidden",
                "name"=>AddSessionCommand(new ShowLines($info["isOutput"], $this->isMeter))));
Debug(GetPortsQuery($info["isOutput"], $index, $this->isMeter));
        SetSQLQuery(GetPortsQuery($info["isOutput"], $index, $this->isMeter), false);

        $h->Elem("table", array("class"=>"portMap"));
        $h->Elem("tr");
        $maxChans = 2;
        for ($chan = 0; $chan < $maxChans; ++$chan)
        {
            $h->LeafElem("th", array("class"=>"chan"),$chan);
        }
        $h->LeafElem("th", array("class"=>"port"), "Port");
        while ($res = GetMultipleResult())
        {
            $h->Elem("tr");
            for ($chan = 0; $chan < $maxChans; ++$chan)
            {
                $h->Elem("td");
                if (($res["channels"] | $res["memberChannels"]) & (1 << $chan))
                {
                    $checkAttrs = array("type"=>"checkbox", "value"=>"set");
                    $checkAttrs += ($res["memberChannels"] & (1 << $chan)) ?
                                                array("checked"=>"checked") : array();
                    $checkAttrs += array("name"=>AddSessionCommand(new SetPortLine($res["jackPortIndex"], $index, $chan, $res["jackPortStateIndex"])));
                    $h->LeafElem("input", $checkAttrs);
                }
                $h->ElemEnd(); // td
            }
            if ($res["portKnown"])
            {
                $class = $res["portExists"] ? "portKnown" : "portKnownUnavail";
            }
            else
            {
                $class = "portUnknown";
            }
            // If we're not looking at a meter line, we show the mapped meters, and qualify them as such
            $h->LeafElem("td", array("class"=>$class), $res["jackId"] . ($res["isMeter"] && !$this->isMeter ? " (meter)":""));
        }
	}
    private $line;
    private $isMeter;
};

class NewLine extends UpdateCommand
{
	function __construct($isOutput, $isMeter = false)
    {
        $this->isOutput = $isOutput;
        $this->isMeter = $isMeter;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $name = "New ". (($this->isOutput) ? "Output" : "Input");
        $updQuery = "INSERT INTO `line` (`name`, `isOutput`) VALUES ('$name','".($this->isOutput ? 1 : 0)."');";
        Debug($updQuery);
        $patchLog .= $updQuery."\n";
		$res = UpdateSQL($updQuery, false);
        if ($res)
        {
            AddDeferredCommand(new EditLine($res, $this->isMeter), null);
        }
	}
    private $isOutput;
    private $isMeter;
};

class DeleteLine extends DisplayCommand
{
	function __construct($index)
    {
        $this->index = $index;
    }
    public function Exec($value = null)
	{
		UpdateSQL("DELETE FROM `line` WHERE `index` = '$this->index';", false);        
		UpdateSQL("DELETE FROM `matrixIO` WHERE `line` = '$this->index';", false);        
		UpdateSQL("UPDATE `jackPort` SET `line` = NULL WHERE `line` = '$this->index';", false);        
		UpdateSQL("DELETE FROM `patch` WHERE `input` = '$this->index' OR `output` = '$this->index';", false);        
        GenerateRequiredPatchList();
	}
    private $index;
};

class ShowLines extends DisplayCommand
{
	function __construct($isOutput, $isMeter)
    {
        $this->isOutput = $isOutput;
        $this->isMeter = $isMeter;
        assert(!$isOutput || !$isMeter);  
    }
    public function Exec($value = null)
    {
        $h = new HtmlState();
        SetSQLQuery(GetLinesForCfgQuery($this->isOutput, $this->isMeter), false);
        $h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));
        $h->Elem("ul", array("class"=>"cfgList"));
        while ($res = GetMultipleResult())
        {
            $h->Elem("li");
            $h->LeafElem("input", array("class"=>"btnEdit","type"=>"submit",
                    "name"=>AddSessionCommand(new EditLine($res["index"], $this->isMeter), true),"value"=>"Edit"));
            $h->LeafElem("input", array("class"=>"btnDelete", "type"=>"submit",
                    "onClick"=>"return confirmDelete()",
                    "name"=>AddSessionCommand(new DeleteLine($res["index"]), true),"value"=>"Delete"));
            $h->LeafElem("span", array("class"=>"cfgName"),$res["name"]); 
            $h->LeafElem("span", array("class"=>"cfgDesc"),$res["description"]);
            if ($res["ports"])
            {
                $h->LeafElem("span", array("class"=>"cfgDetail"),$res["ports"]);                
            }
            else
            {
                $h->LeafElem("span", array("class"=>"cfgNoPorts"),"No Ports Mapped!");
            }
            $h->ElemEnd(); // li
        }
        $h->Elem("li");
        $h->LeafElem("input", array("type"=>"submit", "value"=>"New", "class"=>"btnEdit",
                                    "name"=>AddSessionCommand(new NewLine($this->isOutput, $this->isMeter))));

    }
    private $isOutput;
    private $isMeter;
}



?>