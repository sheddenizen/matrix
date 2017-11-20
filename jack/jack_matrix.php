<?php
require_once "command.php";
require_once "jack_mysql.php";
require_once "utility.php";
require_once "jack_queries.php";
require_once "jack_meter.php";

const kMatrixStateCmd = "MxState";
const kChangePatchCmd = "MxPatch";

function GetMatrixLinesQuery($id)
{
    
$fromWhere = $id == 0 ? "FROM line" :
"
FROM

matrixIO,line

WHERE

matrixIO.matrix = '$id' AND
matrixIO.line = line.index
";

return 
"
SELECT 

line.name, 
line.index,
line.isOutput,
(SELECT COUNT(*) FROM portMap WHERE portMap.line = line.index) AS portCount,
(SELECT COUNT(*) FROM portMap,jackPortState WHERE portMap.line = line.index AND jackPortState.jackPort = portMap.jackPort) AS actualPortCount,
line.description

$fromWhere

ORDER BY isOutput DESC, line.name
;
";
}

function GetMatrixStateQuery($id)
{
$fromMx = $id != 0 ?
"
matrixIO AS mioIn,
matrixIO AS mioOut,
" : "";

$whereMx = $id != 0 ?
"
portIn.line = mioIn.line AND
portOut.line = mioOut.line AND
mioIn.matrix = '$id' AND
mioOut.matrix = '$id' AND
" : "";

return
"
SELECT

input,
output,
SUM(NOT actual) AS desired, 
SUM(actual AND channelIn = channelOut) AS actual, 
SUM(actual AND channelIn != channelOut) AS incorrect

FROM

(SELECT 

portIn.line AS input,
portOut.line AS output,
portIn.channel AS channelIn,
portOut.channel AS channelOut, 
'0' AS actual

FROM

$fromMx
patch,
portMap AS portIn,
portMap AS portOut

WHERE 

$whereMx
patch.input = portIn.line AND
patch.output = portOut.line AND
portIn.channel = portOut.channel

UNION

SELECT 

portIn.line AS input,
portOut.line AS output,
portIn.channel AS channelIn,
portOut.channel AS channelOut,
'1'

FROM 

$fromMx
portMap AS portIn,
portMap AS portOut,
jackPortState AS psIn,
jackPortState AS psOut,
jackPatchState

WHERE 

$whereMx
psIn.jackPort = portIn.jackPort AND 
psOut.jackPort = portOut.jackPort AND
psOut.isOutput = '1' AND
psIn.isOutput = '0' AND
jackPatchState.input = psIn.index AND
jackPatchState.output = psOut.index

) AS mxState

GROUP BY input,output
;
";
}

function StateName($desired, $actual, $incorrect)
{
	if (!$desired && !$actual && !$incorrect)
	{
		return array("class"=>"btnUnpatched", "value"=>"-", "action"=>1);
	}
	
	if ($incorrect > 0)
	{
		$patchState = "Fucked";
	}
	elseif ($desired == $actual)
	{
		$patchState = "Patched";
	}
	elseif ($desired && $actual == 0)
	{
		$patchState = "Inactive";
	}
	elseif ($desired > $actual)
	{
		$patchState = "PartialPatch";
	}
	else
	{
		$patchState = "OverPatch";
	}
	return array("class"=>"btn".$patchState, "value"=>$patchState, "action"=>(($desired > 0) ? 0 : 1));
}




class ChangePatch extends UpdateCommand
{
	function __construct()
    {}
    public function ParamType()
    {
        return array(self::kIntParam, self::kIntParam, self::kIntParam, self::kIntParam);
    }    
    public function Exec($value = null)
	{
        global $patchLog;
        $output = $value[0];
        $input = $value[1];
        $mtxId =$value[2];
        $isMake =$value[3];
        
        if (!$isMake)
        {
            $updQuery = "DELETE FROM `patch` WHERE `input`='$input' AND `output`='$output';";
        }
        else
        {
            $updQuery = "INSERT INTO `patch` (`input` ,`output`) VALUES ('$input',  '$output');";
        }
        $patchLog .= $updQuery."\n";
		if (UpdateSQL($updQuery, false))
		{
			$patchLog .= "Ok";
        }
        GenerateRequiredPatchList();
        
        if ($mtxId)
        {
            $updQuery = "DELETE FROM patch USING patch, matrixIO, line, matrix WHERE ".
            "matrix.index = '$mtxId' AND matrix.isExclusiveIn = '1' AND matrixIO.matrix = matrix.index AND ".
            "patch.output = line.index AND matrixIO.line = line.index AND patch.input = '$input' AND NOT patch.output = '$output';";
			$patchLog .= $updQuery;
      		if (UpdateSQL($updQuery, false))
			{
				$patchLog .= "Ok";
			}
			/*
            $updQuery = "SELECT * FROM patch,matrixIO WHERE matrixIO.matrix='7' AND ((patch.input=matrixIO.line AND patch.output='14' AND patch.input != 42) OR (patch.output=matrixIO.line AND patch.input='42' AND patch.output != '14'))";
        	*/
        }
	}
};

class Patch extends UpdateCommand
{
	function __construct($input, $output)
    {
        $this->input = $input;
        $this->output = $output;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $updQuery = "INSERT INTO `patch` (`input` ,`output`) VALUES ('$this->input',  '$this->output');";
        $patchLog .= $updQuery."\n";
		$ok = UpdateSQL($updQuery, false);
        $patchLog .= $ok ? "OK\n" : "FAILED\n";
        GenerateRequiredPatchList();
	}
    private $input;
    private $output;
};

class UnPatch extends UpdateCommand
{
	function __construct($input, $output)
    {
        $this->input = $input;
        $this->output = $output;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $updQuery = "DELETE FROM `patch` WHERE `input`='$this->input' AND `output`='$this->output';";
        $patchLog .= $updQuery."\n";
		$ok = UpdateSQL($updQuery, false);
        $patchLog .= $ok ? "OK\n" : "FAILED\n";        
        GenerateRequiredPatchList();
    }
    private $input;
    private $output;
};


class ClearMatrixLines extends UpdateCommand
{
	function __construct($matrix)
    {
        $this->matrix = $matrix;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $updQuery = "DELETE FROM `matrixIO` WHERE `matrix` = '$this->matrix';";
        Debug($updQuery);
        $patchLog .= $updQuery."\n";
		UpdateSQL($updQuery, false);
        $updQuery = "DELETE FROM `meter` WHERE `matrix` = '$this->matrix';";
        Debug($updQuery);
        $patchLog .= $updQuery."\n";
		UpdateSQL($updQuery, false);
		$updQuery = "UPDATE `matrix` SET `isExclusiveIn` = '0', `isPushToTalk` = '0' WHERE `index` = '$this->matrix';";
        Debug($updQuery);
        $patchLog .= $updQuery."\n";
		UpdateSQL($updQuery, false);
	}
    private $matrix;
};

class AddLineToMatrix extends UpdateCommand
{
	function __construct($matrix, $line)
    {
        $this->matrix = $matrix;
        $this->line = $line;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $updQuery = "INSERT INTO `matrixIO` (`line`,`matrix`) VALUES ('$this->line', '$this->matrix');";
        $patchLog .= $updQuery."\n";
		UpdateSQL($updQuery, false);
	}
    private $matrix;
    private $line;
};

class AddMeterToMatrix extends UpdateCommand
{
	function __construct($matrix, $line)
    {
        $this->matrix = $matrix;
        $this->line = $line;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $updQuery = "INSERT INTO `meter` (`line`,`matrix`) VALUES ('$this->line', '$this->matrix');";
        $patchLog .= $updQuery."\n";
		UpdateSQL($updQuery, false);
	}
    private $matrix;
    private $line;
};

class EditMatrix extends DisplayCommand
{
	function __construct($index)
    {
        $this->index = $index;
    }
    public function Exec($value = null)
	{
        $h = new HtmlState();
        $h->LeafElem("h3",array(), "Edit Matrix");
        $index = $this->index;
        
       	$h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));

        $info = GetSingleResult("SELECT * FROM matrix WHERE matrix.index='$index';");
        Debug($info);
        
        // When run this will clear all the existing line and meter mappings and clear the matrix boolean/checkbox options 
        $submitAction = AddSessionCommand(new ClearMatrixLines($index));
        
        // Edit name and description
        $h->LeafElem("p", array("class"=>"nameLabel"), "Name: ");
        $h->LeafElem("input", array("type"=>"text", "class"=>"txtName", "value"=>$info["name"],
                "name"=>AddSessionCommand(new SetField($index, "name", "matrix"))));

        $h->LeafElem("p", array("class"=>"descEdit"), "Description: ");
        $h->LeafElem("textarea", array("class"=>"txtDesc",
                "name"=>AddSessionCommand(new SetField($index, "description", "matrix"))),
                $info["description"]);
        $h->LeafElem("br");

        $h->LeafElem("input", array("type"=>"hidden",
                "name"=>AddSessionCommand(new ShowMatrices())));
        
        $checkAttrs = array("type"=>"checkbox", "value"=>"set",
        		"name"=>AddSessionCommand(new SetFieldFixed($index, "isExclusiveIn", "matrix", 1)));
        $checkAttrs += $info["isExclusiveIn"] ? array("checked"=>"checked") : array();
        $h->LeafElem("input", $checkAttrs, "Exclusive Inputs");
        
        $checkAttrs = array("type"=>"checkbox", "value"=>"set",
        		"name"=>AddSessionCommand(new SetFieldFixed($index, "isPushToTalk", "matrix", 1)));
        $checkAttrs += $info["isPushToTalk"] ? array("checked"=>"checked") : array();
        $h->LeafElem("input", $checkAttrs, "Push To Talk");
        
        $h->LeafElem("br");

        // Submit button
        $h->LeafElem("input", array("type"=>"submit", "value"=>"Submit", "name"=>$submitAction));
        
        SetSQLQuery(GetLinesQuery($index), false);

        $h->Elem("table", array("class"=>"portMap"));
        $h->Elem("tr");
        $h->LeafElem("th", array("class"=>"linesOut"), "Outputs");
        $h->LeafElem("th", array("class"=>"linesIn"), "Inputs");
        $h->LeafElem("th", array("class"=>"linesMeter"), "Meters");
        $h->ElemEnd(); // tr
        $h->Elem("tr");
        $h->Elem("td");
        $isOutput = 1;
        
        while ($res = GetMultipleResult())
        {
            if ($res["isOutput"] != $isOutput)
            {
                $h->ElemEnd(); // td
                $h->Elem("td");
                $isOutput = $res["isOutput"];
            }
            $checkAttrs = array("type"=>"checkbox", "value"=>"set",
                "name"=>AddSessionCommand(new AddLineToMatrix($index, $res["index"])));
            $checkAttrs += $res["inMatrix"] ? array("checked"=>"checked") : array();
            $h->LeafElem("input", $checkAttrs, $res["name"]);
            $h->LeafElem("br");
        }
        $h->ElemEnd(); // td
        $h->Elem("td");
        Debug(GetLinesQuery($index, true));
        SetSQLQuery(GetLinesQuery($index, true), false);
        while ($res = GetMultipleResult())
        {
            $checkAttrs = array("type"=>"checkbox", "value"=>"set",
                "name"=>AddSessionCommand(new AddMeterToMatrix($index, $res["index"])));
            $checkAttrs += $res["isMxMeter"] ? array("checked"=>"checked") : array();
            $h->LeafElem("input", $checkAttrs, $res["name"]);
            $h->LeafElem("br");
        }
        $h->ElemEnd(); // td
        $h->ElemEnd(); // tr
        $h->ElemEnd(); // table
        
        // Second submit button
        $h->LeafElem("input", array("type"=>"submit", "value"=>"Submit", "name"=>$submitAction));
	}
    private $index;
};

class DeleteMatrix extends DisplayCommand
{
	function __construct($index)
    {
        $this->index = $index;
    }
    public function Exec($value = null)
	{
		UpdateSQL("DELETE FROM `matrix` WHERE `index` = '$this->index';", false);        
		UpdateSQL("DELETE FROM `matrixIO` WHERE `matrix` = '$this->index';", false);        
	}
    private $index;
};

class NewMatrix extends UpdateCommand
{
	function __construct()
    {}
    public function Exec($value = null)
	{
        global $patchLog;
        $name = "New Matrix";
        $updQuery = "INSERT INTO `matrix` (`name`) VALUES ('$name');";
        Debug($updQuery);
        $patchLog .= $updQuery."\n";
		$res = UpdateSQL($updQuery, false);
        if ($res)
        {
            AddDeferredCommand(new EditMatrix($res), null);
        }
	}
    private $isOutput;
};

class ShowMatrices extends DisplayCommand
{
	function __construct()
    {}
    public function Exec($value = null)
    {
        $h = new HtmlState();
        SetSQLQuery("SELECT * FROM matrix ORDER BY matrix.index;", false);
        $h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));
        $h->Elem("ul", array("class"=>"cfgList"));
        while ($res = GetMultipleResult())
        {
            $h->Elem("li");
            $h->LeafElem("input", array("class"=>"btnEdit","type"=>"submit",
                    "name"=>AddSessionCommand(new EditMatrix($res["index"]), true),"value"=>"Edit"));
            $h->LeafElem("input", array("class"=>"btnDelete","type"=>"submit",
                    "onClick"=>"return confirmDelete()",
                    "name"=>AddSessionCommand(new DeleteMatrix($res["index"]), true),"value"=>"Delete"));
            $h->LeafElem("span", array("class"=>"cfgName"),$res["name"]); 
            $h->LeafElem("span", array("class"=>"cfgDesc"),$res["description"]);
            $h->ElemEnd(); // li
        }
        $h->Elem("li");
        $h->LeafElem("input", array("type"=>"submit", "value"=>"New", "class"=>"btnEdit",
                                    "name"=>AddSessionCommand(new NewMatrix())));

    }
}

class DrawMatrix extends DisplayCommand
{
	function __construct()
    {
    }
    public function IsPersistent()
    {
        return true;
    }
    public function ParamType()
    {
        return array(self::kIntParam);
    }    
    public function Exec($value = null)
    {
        $id = $value[0] + 0;
        $lines = array();
        $ins = array();
        $outs = array();
        
        // Get a list of lines in the matrix and populate in/out arrays    
        // Debug(GetMatrixLinesQuery($id));
        SetSqlQuery(GetMatrixLinesQuery($id), false);
        while (1)
        {
            $res = GetMultipleResult();
            if (!$res) break;
            if ($res[2])
            {
                $outs[] = $res[1];
            }
            else
            {
                $ins[] = $res[1];
            }
            $cfgports = $res["portCount"];
            $actualports = $res["actualPortCount"];
            if ($cfgports == 0)
                $res["class"] = "matrixBad";
            elseif ($actualports == $cfgports)
                $res["class"] = "matrixOk";
            else $res["class"] = "matrixPartial";

            $lines[$res[1]] = $res;
        }
    
        // Preamble for the table
        $h = new HtmlState();

        if (!self::$scriptInjected)
        {
        	$h->Elem("script", array("type" => "application/javascript", "src"=>"matrixupdate.js"));
        	$h->ElemEnd();
        	self::$scriptInjected = true;
        }
        
        $h->Elem("script", array("type" => "application/javascript"));
		$h->Comment("\nmxcmd = \"".kCmdPrefix.kMatrixStateCmd."=$id&\";\n");
        $h->ElemEnd();
		
        if ($id == 0)
        {
        	$res = array("name"=>"Master Matrix", "description"=>"All known connections", "isPushToTalk"=>0);
        }
        else
        {
        	$res = GetSingleResult("SELECT * FROM matrix WHERE matrix.index = '$id';");
        }
        $h->LeafElem("h3", array("class"=>"mtxName"), $res["name"]);
        $h->LeafElem("p", array("class"=>"mtxDesc"), $res["description"]);
        $isPtt = $res["isPushToTalk"];

        $action = AddSessionCommand(new ChangePatch());
        
        // Display the meters, if any
        $meters = new DrawMeters;
        $meters->Exec(array($id));
        //$h->LeafElem("hr", array("style"=>"color: darkblue; height: 10px;"));

        $h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));
        $h->Elem("p");
        AddPersistHiddenFields();
        $h->ElemEnd();
        //$h->LeafElem("input", array("type"=>"hidden","name"=>AddSessionCommand(new DrawMatrix($id), true),"value"=>"matrix"));
        $h->Elem("table", array("class"=>"tblMatrix"));
    
        // Print out the column headings (output names)
        $h->Elem("tr");
        // Empty cell in the corner
        //$h->LeafElem("th");
        foreach ($outs as $out)
        {
            $h->LeafElem("th", array("class"=>$lines[$out]["class"], "title"=>$lines[$out]["description"]), $lines[$out]["name"]/*."-".$lines[$out]["index"]*/);
        }
        $h->LeafElem("th", array());
        $h->ElemEnd();
    
        // Get the list of patches in the matrix
        Debug(GetMatrixStateQuery($id));
        SetSqlQuery(GetMatrixStateQuery($id), false);
        $patches = array();
        while ($res = GetMultipleResult())
        {
            $patches[$res["input"]][$res["output"]] = $res;
        }
        
        // Go through each table cell
        foreach ($ins as $in)
        {
            // First the row heading (input name)
            $h->Elem("tr");
            // $h->LeafElem("th", array("class"=>$lines[$in]["class"]), $lines[$in]["name"]/*."-".$lines[$in]["index"]*/);
            
            // Now print each cell showing the state of each patch
            foreach ($outs as $out)
            {
                $h->Elem("td", array("class"=>"tdMatrix"));
                $patchState = "Unpatched";
                $stateDisp = "-";
                $patch = &$patches[$in][$out];
                
                if (isset($patch))
                {
                	$state = StateName($patch["desired"], $patch["actual"], $patch["incorrect"]);
                }
                else
                {
                 	$state = StateName(0,0,0);
                }
                $elemId = $out.kParamSeparator.$in;
                $actionParams = kParamSeparator.$elemId.kParamSeparator.$id.kParamSeparator.$state["action"];
                $hint = $lines[$out]["name"]." (".$lines[$out]["description"] .") => ". $lines[$in]["name"]." (".$lines[$in]["description"].")";
                
                $attrs = array("id"=>$elemId, "class"=>$state["class"], "title"=>$hint, "type"=>"submit",
                		"name"=>$action.$actionParams, "value"=>$state["value"]);
                if ($isPtt)
                {
                	$attrs["onclick"] = "return false";
                	$attrs["onmousedown"] = "mxPttOn(\"$elemId\")";
                	$attrs["onmouseup"] = "mxPttOff()";
                	$attrs["onmouseout"] = "mxPttOff()";
                }
                else 
                {
                	$attrs["onclick"] = "return mxClick(\"$elemId\")";
                }
                                
                $h->LeafElem("input", $attrs);
                Debug("$in, $out");
                $h->ElemEnd(); // td
            }
            $h->LeafElem("th", array("class"=>$lines[$in]["class"]."Col", "title"=>$lines[$in]["description"]), $lines[$in]["name"]);
            $h->ElemEnd();
        }        
    }
	static private $scriptInjected = false;
    private $index;
};


class GetMatrixState extends DisplayCommand
{
	function __construct()
    {
    }
    public function ParamType()
    {
        return array(self::kIntParam);
    }    
    public function Exec($value = null)
    {
        $id = $value[0];
        $ins = array();
        $outs = array();
    
        // Get the list of patches in the matrix
        Debug(GetMatrixStateQuery($id));
        SetSqlQuery(GetMatrixStateQuery($id), false);
        while ($res = GetMultipleResult())
        {
        	$state = StateName($res["desired"], $res["actual"], $res["incorrect"]);
        	 
            echo($res["output"].kParamSeparator.$res["input"].",".$state["class"].
                 ",".$state["value"].",".$state["action"]."/");
        }
    }
    private $index;
};

?>
