#!/usr/bin/php -q
<?
/* Simple player/recorder for the Yate PHP interface
   To test add in regexroute.conf

   ^NNN$=external/nodata/playrec.php

   where NNN is the number you want to assign
*/
require_once("libyate.php");

/* Always the first action to do */
Yate::Init();

/* Install a handler for the engine generated timer message */
Yate::Install("chan.dtmf",10);
Yate::Install("chan.notify");

$ourcallid = "external/" . uniqid(rand(),1);
$partycallid = "";
$state = "call";
$dir = "/tmp";

function setState($newstate)
{
    global $ourcallid;
    global $partycallid;
    global $state;
    global $dir;

    // are we exiting?
    if ($state == "")
	return;

    Yate::Output("setState('" . $newstate . "') state: " . $state);

    // always obey a return to prompt
    if ($newstate == "prompt") {
	$state = $newstate;
	$m = new Yate("chan.attach");
	$m->params["source"] = "wave/play/scripts/playrec_menu.au";
	$m->Dispatch();
	$m = new Yate("chan.attach");
	$m->params["consumer"] = "wave/record/-";
	$m->params["maxlen"] = 320000;
	$m->params["notify"] = $ourcallid;
	$m->Dispatch();
	return;
    }

    if ($newstate == $state)
	return;

    switch ($newstate) {
	case "record":
	    $m = new Yate("chan.attach");
	    $m->params["source"] = "wave/play/-";
	    $m->params["consumer"] = "wave/record/" . $dir . "/playrec.slin";
	    $m->params["maxlen"] = 80000;
	    $m->params["notify"] = $ourcallid;
	    $m->Dispatch();
	    break;
	case "play":
	    $m = new Yate("chan.attach");
	    $m->params["source"] = "wave/play/" . $dir . "/playrec.slin";
	    $m->params["consumer"] = "wave/record/-";
	    $m->params["maxlen"] = 4800000;
	    $m->params["notify"] = $ourcallid;
	    $m->Dispatch();
	    break;
	case "goodbye":
	    $m = new Yate("chan.attach");
	    $m->params["source"] = "tone/congestion";
	    $m->params["consumer"] = "wave/record/-";
	    $m->params["maxlen"] = 32000;
	    $m->params["notify"] = $ourcallid;
	    $m->Dispatch();
	    break;
    }
    $state = $newstate;
}

function gotNotify()
{
    global $ourcallid;
    global $partycallid;
    global $state;

    Yate::Output("gotNotify() state: " . $state);

    switch ($state) {
	case "goodbye":
	    setState("");
	    break;
	case "prompt":
	    setState("goodbye");
	    break;
	case "record":
	case "play":
	    setState("prompt");
	    break;
    }
}

function gotDTMF($text)
{
    global $ourcallid;
    global $partycallid;
    global $state;

    Yate::Output("gotDTMF('" . $text . "') state: " . $state);

    switch ($text) {
	case "1":
	    setState("record");
	    break;
	case "2":
	    setState("play");
	    break;
	case "3":
	    setState("");
	    break;
	case "#":
	    setState("prompt");
	    break;
    }
}

/* The main loop. We pick events and handle them */
while ($state != "") {
    $ev=Yate::GetEvent();
    /* If Yate disconnected us then exit cleanly */
    if ($ev == "EOF")
        break;
    /* Empty events are normal in non-blocking operation.
       This is an opportunity to do idle tasks and check timers */
    if ($ev == "")
        continue;
    /* If we reached here we should have a valid object */
    switch ($ev->type) {
	case "incoming":
//	    Yate::Output("PHP Message: " . $ev->name . " our id: " . $ev->params["id"] . " target id: " . $ev->params["targetid"]);
//	    Yate::Output("current state: " . $state);
	    switch ($ev->name) {
		case "call.execute":
		    $partycallid = $ev->params["id"];
		    $ev->params["targetid"] = $ourcallid;
		    $ev->handled = true;
		    // we must ACK this message before dispatching a call.answered
		    $ev->Acknowledge();
		    // we already ACKed this message
		    $ev = "";

		    $m = new Yate("call.answered");
		    $m->params["id"] = $ourcallid;
		    $m->params["targetid"] = $partycallid;
		    $m->Dispatch();

		    setState("prompt");
		    break;

		case "chan.notify":
		    if ($ev->params["targetid"] == $ourcallid) {
			gotNotify();
			$ev->handled = true;
		    }
		    break;

		case "chan.dtmf":
		    if ($ev->params["targetid"] == $ourcallid ) {
			gotDTMF($ev->params["text"]);
			$ev->handled = true;
		    }   
		    break;
	    }
	    /* This is extremely important.
	       We MUST let messages return, handled or not */
	    if ($ev != "")
		$ev->Acknowledge();
	    break;
	case "answer":
	    Yate::Output("PHP Answered: " . $ev->name . " id: " . $ev->id);
	    break;
	case "installed":
	    Yate::Output("PHP Installed: " . $ev->name);
	    break;
	case "uninstalled":
	    Yate::Output("PHP Uninstalled: " . $ev->name);
	    break;
	default:
	    Yate::Output("PHP Event: " . $ev->type);
    }
}

Yate::Output("PHP: bye!");

/* vi: set ts=8 sw=4 sts=4 noet: */
?>
