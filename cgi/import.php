<?php
ini_set('auto_detect_line_endings', true);

# http://www.opensource.org/licenses/mit-license.php MIT License

#require_once("locale.php");
#require_once("db.php");
?>
<?php

include_once('phpQuery-onefile.php');
#include_once('helper.php');

# HACK!
if (!function_exists('gettext')) {
    function gettext($str) {
        return $str;
    }
}
if (!function_exists('_')) {
    function _($str) {
        return $str;
    }
}

$posMap = array("Window"=>"W", "Middle"=>"M", "Aisle"=>"A");
$classMap = array("Economy"=>"Y", "Prem.Eco"=>"P", "Business"=>"C", "First"=>"F");
$reasonMap = array("Business"=>"B", "Personal"=>"L", "Crew"=>"C", "Other"=>"O");

function nth_text($element, $n) {
    $xpath = new DOMXPath($element->ownerDocument);
    return nbsp_trim($xpath->query('.//text()', $element)->item($n)->textContent);
}

function text_count($element) {
    $xpath = new DOMXPath($element->ownerDocument);
    return $xpath->query('.//text()', $element)->length;
}

function nbsp_trim($string) {
    return trim($string, "\xC2\xA0"); // UTF-8 NBSP
}

// Validate date field
// Must be one of YYYY, MM-DD-YYYY (FM only), YYYY-MM-DD (CSV only), MM/DD/YYYY or DD.MM.YYYY
function check_date($date) {
    $arr = explode("-", $date);
    $date = $arr[2] . "-" . $arr[0] . "-" . $arr[1];
    return $date;
}

// Validate that the importing user owns this trip
function check_trip($db, $uid, $trid) {
    // If no trip set, return OK
    if(!$trid || $trid == "") {
        return array(null, "#fff");
    }

    $sql = "select uid from trips where trid=" . mysql_real_escape_string($trid);
    $result = mysql_query($sql, $db);
    if(@mysql_num_rows($result) == 1) {
        if($uid == mysql_result($result, 0)) {
            $color = "#fff";
        } else {
            $color = "#faa";
        }
    } else {
        $color = "#faa";
    }
    return array($trid, $color);
}

function die_nicely($msg) {
    print $msg . "<br><br>";
    print "<INPUT type='button' value='" . _("Upload again") . "' title='" . _("Cancel this import and return to file upload page") . "' onClick='history.back(-1)'>";
    exit;
}


# Seat Type (W/A/M)
function convert_seatpos($seatpos) {
    if("Window" == $seatpos) {
        return "W";
    }
    else if("Middle" == $seatpos) {
        return "M";
    }
    else if("Aisle" == $seatpos) {
        return "A";
    }
    else {
        return "";
    }
}

# Class (F/C/P/Y)
function convert_seatclass($seatclass) {
    if("First" == $seatclass) {
        return "F";
    }
    else if("Business" == $seatclass) {
        return "C";
    }
    else if("EconomyPlus" == $seatclass) {
        return "P";
    }
    else if("Economy" == $seatclass) {
        return "Y";
    }
    else {
        return "";
    }
}

# Reason (B/L/C/O)
function convert_seatreason($seatreason) {
    if("Business" == $seatreason) {
        return "B";
    }
    else if("Personal" == $seatreason) {
        return "L";
    }
    else if("Crew" == $seatreason) {
        return "C";
    }
    else if("Other" == $seatreason) {
        return "O";
    }
    else {
        return "";
    }
    return $seatreason;
}

if($argc <= 1) {
    print "No arguments!\n";
    exit(1);
}
else if($argc == 2) {
    $first = "True";
    $comments = "True";
}
else if($argc == 3) {
    $first = $argv[2];
    $comments = "True";
}
else {
    $first = $argv[2];
    $comments = $argv[3];
}
$uploadfile = $argv[1];
$history = "yes"; # Yes for historical airline mode.  All airline names have been preserved exactly as is.
$status = "";

// Parse it
$html = phpQuery::newDocumentFileHTML($uploadfile, 'ISO-8859-1');

if($html['title']->text() != "FlightMemory - FlightData") {
    die_nicely(_("Sorry, the file $uploadfile does not appear contain FlightMemory FlightData."));
}

// 3rd table has the data
$rows = pq('table:nth-of-type(3) tr[valign=top]')->elements;

if("True" == $first) {
    print "Date,From,To,Flight_Number,Airline,Distance,Duration,Seat,Seat_Type,Class,Reason,Plane,Registration,Trip,Note,From_OID,To_OID,Airline_OID,Plane_OID\n";
}
$count = 0;
foreach($rows as $row) {
    $row = pq($row);
    $cols = $row['> td, th']->elements;
    $id = pq($cols[0])->text();

    // Read and validate date field
    //     <td class="liste_rot"><nobr>10-05-2009</nobr><br>06:10<br>17:35 -1</td>
    $src_date = nth_text($cols[1], 0);
    $src_time = nth_text($cols[1], 1);
    if(strlen($src_time) < 4) $src_time = NULL; # a stray -1 or +1 is not a time
    $src_date = check_date($src_date);

    $src_iata = $cols[2]->textContent;
    $dst_iata = $cols[4]->textContent;

    // <td class="liste"><b>Country</b><br>Town<br>Airport Blah Blah</td>
    //                                             ^^^^^^^ target
    $src_name = reset(preg_split('/[ \/<]/', nth_text($cols[3], 2)));
    $dst_name = reset(preg_split('/[ \/<]/', nth_text($cols[5], 2)));

    # TODO
    #list($src_apid, $src_iata, $src_bgcolor) = check_airport($src_iata, $src_name);
    #list($dst_apid, $dst_iata, $dst_bgcolor) = check_airport($dst_iata, $dst_name);

    // <th class="liste_gross" align="right">
    //   <table border="0" cellspacing="0" cellpadding="0">
    //       <tr><td align="right">429&nbsp;</td><td>mi</td></tr>
    //       <tr><td align="right">1:27&nbsp;</td><td>h</td></tr></table></th>
    $cells = $row['table td']->elements;
    $distance = $cells[0]->textContent;
    $distance = str_replace(',', '', nbsp_trim($distance));
    $dist_unit = $cells[1]->textContent;
    if($dist_unit == "km") {
        $distance = round($distance/1.609344); // km to mi
    }
    $duration = nbsp_trim($cells[2]->textContent);

    // <td>Airline<br>number</td>
    $airline = nth_text($cols[6], 0);
    $number = nth_text($cols[6], 1);
    # TODO
    #list($alid, $airline, $airline_bgcolor) = check_airline($db, $number, $airline, $uid, $history);

    // Load plane model (plid)
    // <TD class=liste>Boeing 737-600<BR>LN-RCW<BR>Yngvar Viking</TD>
    $plane = nth_text($cols[7], 0);
    $reg = nth_text($cols[7], 1);
    if(text_count($cols[7]) > 2) {
        $reg .= " " . nth_text($cols[7], 2);
    }

    // <td class="liste">12A/Window<br><small>Economy<br>Passenger<br>Business</small></td>
    // 2nd field may be blank, so we count fields and offset 1 if it's there
    $seat = nth_text($cols[8], 0);
    list($seatnumber, $seatpos) = explode('/', $seat);
    if(text_count($cols[8]) == 4) {
        $seatclass = nth_text($cols[8], 1);
        $offset = 1;
    } else {
        $seatclass = "Economy";
        $offset = 0;
    }
    $seattype = nth_text($cols[8], 1 + $offset);
    $seatreason = nth_text($cols[8], 2 + $offset);

    // <td class="liste_rot"><span title="Comment: 2.5-hr delay due to tire puncture">Com</span><br> ...
    $comment = pq($cols[9])->find('span')->attr('title');
    #if($comment) {
    #    $comment = trim(substr($comment, 9));
    #}

    # Date: $src_date
    # Departure Time: $src_time
    # Airline: $airline
    # Flight Number: $number 
    # From: $src_iata
    # To: $dst_iata
    # Miles: $distance
    # Time: $duration
    # Plane: $plane
    # Reg: $reg
    # Seat Number: $seatnumber
    # Seat Position: $seatpos
    # Class: $seatclass
    # Type: $seattype
    # Reason: $seatreason
    # Trip: $trid
    # Comment: $comment
    #printf ("Date: %s, Departure Time: %s, Airline: %s, Flight Number: %s, From: %s, To: %s, Miles: %s, Time: %s, Plane: %s, Reg: %s, Seat Number: %s, Seat Position: %s, Class: %s, Type: %s, Reason: %s, Trip: %s, Comment: %s\n",
    #$src_date, $src_time, $airline, $number , $src_iata, $dst_iata, $distance, $duration, $plane, $reg, $seatnumber, $seatpos, $seatclass, $seattype, $seatreason, $trid, $comment); 
    
    # convert for openflights.org CSV
    $seatpos = convert_seatpos($seatpos); # Seat Type (W/A/M)
    $seatclass = convert_seatclass($seatclass); # Class (F/C/P/Y)
    $seatreason = convert_seatreason($seatreason); # Reason (B/L/C/O)

    # See http://openflights.org/help.csv.html
    printf ("%s %s", $src_date, $src_time); # Date: YYYY-MM-DD HH:MM
    printf (",%s,%s", $src_iata, $dst_iata); # From/To: IATA or ICAO code
    printf (",%s,%s", $number, $airline); # Flight Number and Airline
    printf (",%s,%s", $distance, $duration); # Distance and Duration
    printf (",%s,%s", $seatnumber, $seatpos); # Seat (number) and Seat Type (W/A/M)
    printf (",%s,%s", $seatclass, $seatreason); # Class (F/C/P/Y) and Reason (B/L/C/O)
    printf (",%s,%s", $plane, $reg); # Plane and Registration
    if("True" == $comments) {
        printf (",,%s", $comment); # Trip (ID) and Note
    }
    else {
        printf (",,"); # Trip (ID) and Note
    }
    printf ("\n");
}
?>
