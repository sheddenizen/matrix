var mxstateidle = true;
var mxpatchreq = 0;
var mxtimer = setInterval(function(){updateMatrix()},1000);
var mxtouched = new Array();
var mxcmd = "";
var mxpttid = "";

function updateMatrix()
{
    if (!mxstateidle || mxcmd == "")
    {
        return;
    }
    mxstateidle = false;
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
        mxstateidle = xmlhttp.readyState == 4;
        if (mxstateidle && xmlhttp.status==200)
        {
	        var patches = String(xmlhttp.responseText).split("/");
	        var newtouched = new Array();
	        for(var i = 0; i < patches.length; ++i)
	        {
	        	var state = String(patches[i]).split(",");
	        	if (state.length < 4)
	        	{
	        		continue;
	        	}
	        	newtouched.push(state[0]);
	        	var btn = document.getElementById(state[0]);
	        	btn.className = state[1];
	        	btn.value = state[2];
	        	var action = btn.name;
	        	btn.name = action.replace(/_[01]$/, "_" + state[3]);
	        }
	        for (var i = 0; i < mxtouched.length; ++i)
	        {
	        	if (newtouched.indexOf(mxtouched[i]) == -1)
	        	{
	            	var btn = document.getElementById(mxtouched[i]);
	            	btn.className = "btnUnpatched";
	            	btn.value = "-";        		
		        	var action = btn.name;
	            	btn.name = action.replace(/_[01]$/, "_1");
	        	}
	        }
	        mxtouched = newtouched;
        }
    }
    var now = new Date();
    var url = "matrixstate.php?" + mxcmd + "x=" + now.getTime();
    xmlhttp.open("GET", url, true);
    xmlhttp.send();
}

function mxPttOn(id)
{
	if (mxpttid == "" && mxpatchreq == 0)
	{
		mxpttid = id;
		mxClick(id, 1);
	}
}

function mxPttOff()
{
	if (mxpttid != "")
	{
		if (mxpatchreq == 0)
		{
			mxClick(mxpttid, 0);
		}
		mxpttid = "";
	}
}

function mxClick(id, make)
{
	++mxpatchreq;
	mxtouched.push(id);
	var btn = document.getElementById(id);
	btn.className = "btnPending";
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
        if (xmlhttp.readyState == 4)
        {
        	--mxpatchreq;
            if (xmlhttp.status==200)
            {
            	updateMatrix();
            }
            if (make == 1 && mxpatchreq == 0 && mxpttid == "")
            {
            	mxClick(id, 0);
            }
        }
    }
    var now = new Date();
    var action = btn.name;
    action = action.replace(/^.*?_/, "cmd-MxPatch_");
    if (make != undefined)
   	{
        action = action.replace(/_[01]$/, "_" + make);
   	}
    var url = "matrixstate.php?" + action + "=1&x=" + now.getTime();
    xmlhttp.open("GET", url, true);
    xmlhttp.send();

    return false;
}
