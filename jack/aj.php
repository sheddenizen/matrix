<html>
<body><h1>XML</h1>
<p>AJTest</p>
<p>
<?php

$aj = file_get_contents("/home/sound/etc/ajtest.xml");

echo("<pre>");
    
$structure = simplexml_load_string($aj);
print_r($structure);
echo("</pre>");

?>
</p>
</body>
</html>
