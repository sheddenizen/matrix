<?php 

require_once "command.php";
require_once "jack_mysql.php";
require_once "utility.php";

class CodeEntry extends DisplayCommand
{
	function __construct()
	{
	}
	public function Exec($value = null)
	{
		$h = new HtmlState();
		
		$h->Elem("form", array("method"=>"get","action"=>$_SERVER["PHP_SELF"]));
		$h->Elem("table", array("class"=>"codeEntry"));
		$digits = array('1','2','3','4','5','6','7','8','9','*','0','#');
		for ($col = 0; $col < 3; ++$col)
		{
			$h->Elem("tr");
			for ($row = 0; $row < 4; ++$row)
			{
				$digIdx = $col + 3 * $row;
				$h->Elem("td");
				$h->LeafElem("input", array("type"=>"submit", "value"=>$digits[$digIdx]));
				$h->ElemEnd(); // td
			}
			$h->ElemEnd(); // tr
		}
	}
};


?>