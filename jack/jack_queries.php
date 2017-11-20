<?php

require_once "command.php";
require_once "jack_mysql.php";
require_once "utility.php";

class SetField extends UpdateCommand
{
	function __construct($index, $field, $table)
    {
        $this->index = $index;
        $this->field = $field;
        $this->table = $table;
    }
    public function Exec($value = null)
	{
        global $patchLog;
        $sanitizedValue = SanitizeUserInput($value[0]);
        $updQuery = "UPDATE `$this->table` SET `$this->field` = '$sanitizedValue' WHERE `index` = '$this->index';";
        $patchLog .= $updQuery;
        UpdateSQL($updQuery, false);
	}
    public function ParamType()
    {
        return array(self::kStringParam);
    }    
    private $index;
    private $field;
    private $table;
};

class SetFieldFixed extends UpdateCommand
{
	function __construct($index, $field, $table, $value)
	{
		$this->index = $index;
		$this->field = $field;
		$this->table = $table;
		$this->value = $value;
	}
	public function Exec($value = null)
	{
		global $patchLog;
		$updQuery = "UPDATE `$this->table` SET `$this->field` = '$this->value' WHERE `index` = '$this->index';";
		$patchLog .= $updQuery;
		UpdateSQL($updQuery, false);
	}
	public function ParamType()
	{
		return array(self::kStringParam);
	}
	private $index;
	private $field;
	private $table;
	private $value;
};


?>