<?php

require_once "jack_line.php";
require_once "jack_matrix.php";


class MatrixDrawCommands
{
    const kDrawMatrixPrefix = "ShowMatrix";
    const kMasterId = "0";
    private $matricies = false;
    
    function __construct()
    {
        AddFixedCommand(new DrawMatrix(), self::kDrawMatrixPrefix);
    }
    public function Get()
    {
        if (!$this->matricies)
        {
            SetSQLQuery("SELECT * FROM matrix ORDER BY matrix.name", false);
            while ($res = GetMultipleResult())
            {
                $id = substr("0000".$res["index"],-4);
                $this->matricies[$res["name"]] = kCmdPrefix.self::kDrawMatrixPrefix.kParamSeparator.$id;
            }
        }
        return $this->matricies;
    }
    public function GetMaster()
    {
        return kCmdPrefix.self::kDrawMatrixPrefix.kParamSeparator.self::kMasterId;
    }
};


class ConfigureCommands
{
    private $cmds;
    function __construct()
    {
        $this->cmds["Source"] = AddFixedCommand(new ShowLines(true, false), "CfgOutputs");
        $this->cmds["Destination"] = AddFixedCommand(new ShowLines(false, false), "CfgInputs");
        $this->cmds["Meters"] = AddFixedCommand(new ShowLines(false, true), "CfgMeters");
        $this->cmds["Matrices"] = AddFixedCommand(new ShowMatrices(), "CfgMatrices");
        $this->cmds["Master Matrix"] = MatrixDrawCommands::GetMaster();
    }
    public function Get()
    {
        return $this->cmds;
    }
}
?>
