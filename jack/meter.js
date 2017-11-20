
// Update meters every 100ms
var metertimer = setInterval(function(){updateMeter()},100);
var meteridle = true;
var channels =[];

function updateMeter()
{
    if (!meteridle)
    {
        return;
    }
    meteridle = false;
    var xmlhttp;
    if (window.XMLHttpRequest)
    {// code for IE7+, Firefox, Chrome, Opera, Safari
        xmlhttp=new XMLHttpRequest();
    }
    else
    {// code for IE6, IE5
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange=function()
    {
        meteridle = xmlhttp.readyState == 4;
        if (xmlhttp.readyState==4 && xmlhttp.status==200)
        {
            var values = String(xmlhttp.responseText).split(',');
            for (var idx = 0; idx < channels.length; ++idx)
            {
                var channel = channels[idx];
                // document.getElementById("myDiv").innerHTML=xmlhttp.responseText;

                var rms = parseFloat(values[channel * 2 - 2]);
                var pk = parseFloat(values[channel * 2 - 1]);
                rms = (rms + kDbRange) * kMeterHeight/kDbRange;
                pk = (pk + kDbRange) * kMeterHeight/kDbRange;
                rms = rms < 1 ? 1 : rms;
                pk = (pk <= rms) ? 1 : pk - rms;
                document.getElementById("meterRms" + channel).style.height = rms + "px";
                document.getElementById("meterPeak" +  + channel).style.height = pk + "px";
            }
        }
    }
    var now = new Date();
    xmlhttp.open("GET","meters/matrixMeter1.lev?x=" + now.getTime(), true);
    xmlhttp.send();
}
