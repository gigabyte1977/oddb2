<?php
/**
 * pages/show_system.php
 * System anzeigen
 */

// Sicherheitsabfrage
if(!defined('ODDB')) die('unerlaubter Zugriff!');

// default-Unterseite definieren
if(!isset($_GET['sp'])) $_GET['sp'] = '';

// Template initialisieren
$tmpl = new template;


// benötigte Klassen laden
General::loadClass('datatable');
General::loadClass('Rechte');



// keine Berechtigung, irgendwelche Systeme anzuzeigen
if(!$user->rechte['show_system']) {
	$tmpl->error = 'Du hast keine Berechtigung, Systeme anzuzeigen!';
	$tmpl->output();
	die();
}

// keine ID übergeben
if(!isset($_GET['id'])) {
	$tmpl->error = 'Keine ID übergeben!';
	$tmpl->output();
	die();
}


// vorhandene Seiten (key = $_GET['sp'])
$pages = array(
	''=>true
);

// 404-Error
if(!isset($pages[$_GET['sp']])) {
	$tmpl->error = 'Die Seite existiert nicht!';
}


/**
 * Seiten
 */
 
 /*
 ----- Tooltip
 
 <div class="small2">
<br /><br />
<span style="line-height:18px">
	<b>Gr&ouml;&szlig;e</b>:31
	<br />
	<b>Bev&ouml;lkerung</b>: 4.000.000
</span>
<table class="showsysresst">
<tr>
	<td><div class="ress erz"></div></td>
	<td>100%</td>
	<td>222.222.222</td>
</tr>
<tr>
	<td><div class="ress metall"></div></td>
	<td>100%</td>
	<td>222.222.222</td>
</tr>
<tr>
	<td><div class="ress wolfram"></div></td>
	<td>100%</td>
	<td>222.222.222</td>
</tr>
<tr>
	<td><div class="ress kristall"></div></td>
	<td>100%</td>
	<td>222.222.222</td>
</tr>
<tr>
	<td><div class="ress fluor"></div></td>
	<td>100%</td>
	<td>222.222.222</td>
</tr>
</table>
<br />
Kommentar: Dieser Planet ist cool.
</div>
*/

// System anzeigen
else if($_GET['sp'] == '') {
	// Daten sichern
	$_GET['id'] = (int)$_GET['id'];
	
	// System-Daten abfragen
	$query = query("
		SELECT
			systemeName,
			systemeUpdateHidden,
			systemeUpdate,
			systemeScanReserv,
			systemeReservUser,
			systemeX,
			systemeY,
			systemeZ,
			systeme_galaxienID,
			systemeGateEntf,
			
			galaxienGate,
			galaxienGateSys
		FROM
			".PREFIX."systeme
			LEFT JOIN ".PREFIX."galaxien
				ON galaxienID = systeme_galaxienID
		WHERE
			systemeID = ".$_GET['id']."
	") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
	
	$data = false;
	
	if(mysql_num_rows($query)) $data = mysql_fetch_assoc($query);
	
	// das System existiert nicht
	if(!$data) {
		$tmpl->error = 'Das System wurde nicht gefunden!';
		$tmpl->output();
		die();
	}
	
	$pldata = array();
	
	if($data['systemeUpdateHidden']) {
		// Planeten-Daten abfragen
		$query = query("
			SELECT
				planetenID,
				planetenName,
				planetenPosition,
				planetenUpdateOverview,
				planetenUpdate,
				planetenUnscannbar,
				planetenTyp,
				planetenGroesse,
				planetenBevoelkerung,
				planetenGebPlanet,
				planetenGebOrbit,
				planetenGebSpezial,
				planetenMyrigate,
				planetenRiss,
				planetenRWErz,
				planetenRWWolfram,
				planetenRWKristall,
				planetenRWFluor,
				planetenRMErz,
				planetenRMMetall,
				planetenRMWolfram,
				planetenRMKristall,
				planetenRMFluor,
				planetenKommentar,
				planetenGeraidet,
				planetenGetoxxt,
				planetenNatives,
				planetenOrbiter,
				planeten_playerID,
				
				p1.playerName,
				p1.playerRasse,
				p1.playerUmod,
				p1.playerDeleted,
				p1.player_allianzenID,
				
				a1.allianzenTag,
				
				register_allianzenID,
				
				schiffeBergbau,
				schiffeTerraformer,
				
				p2.playerName AS BergbauName,
				p2.player_allianzenID AS BergbauAllyID,
				a2.allianzenTag AS BergbauAllyTag,
				
				statusStatus
			FROM
				".PREFIX."planeten
				LEFT JOIN ".GLOBPREFIX."player p1
					ON playerID = planeten_playerID
				LEFT JOIN ".GLOBPREFIX."allianzen a1
					ON a1.allianzenID = p1.player_allianzenID
				LEFT JOIN ".PREFIX."register
					ON register_allianzenID = a1.allianzenID
				LEFT JOIN ".PREFIX."planeten_schiffe
					ON schiffe_planetenID = planetenID
				LEFT JOIN ".GLOBPREFIX."player p2
					ON p2.playerID = schiffeBergbau
				LEFT JOIN ".GLOBPREFIX."allianzen a2
					ON a2.allianzenID = p2.player_allianzenID
				LEFT JOIN ".PREFIX."allianzen_status
					ON statusDBAllianz = ".$user->allianz."
					AND status_allianzenID = a1.allianzenID
			WHERE
				planeten_systemeID = ".$_GET['id']."
			ORDER BY
				planetenID ASC
		") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
		
		while($row = mysql_fetch_assoc($query)) {
			$pldata[$row['planetenPosition']] = $row;
		}
	}
	
	// Berechtigungen ermitteln
	// Galaxie gesperrt
	if($user->protectedGalas AND in_array($data['systeme_galaxienID'], $user->protectedGalas)) {
		$tmpl->error = 'Du hast keine Berechtigung, Systeme der Galaxie '.$data['systeme_galaxienID'].' anzeigen zu lassen!';
		$tmpl->output();
		die();
	}
	
	$allyplanet = false;
	foreach($pldata as $row) {
		// Allianzplanet
		if($user->allianz AND $row['player_allianzenID'] == $user->allianz) {
			// Berechtigung -> System jetzt auf jeden Fall anzeigen
			if($user->rechte['show_system_ally']) {
				$allyplanet = true;
			}
			// Allianzsysteme gesperrt
			else {
				$tmpl->error = 'Du hast keine Berechtigung, das System '.$_GET['id'].' anzeigen zu lassen!';
			}
		}
	}
	
	// Kein Allyplanet im System -> weitere Berechtigungen prüfen
	if(!$allyplanet AND $tmpl->error == '') {
		foreach($pldata as $row) {
			// keine Berechtigung (Allianz gesperrt)
			if($user->protectedAllies AND in_array($row['player_allianzenID'], $user->protectedAllies)) {
				$tmpl->error = 'Du hast keine Berechtigung, das System '.$_GET['id'].' anzeigen zu lassen!';
			}
			// keine Berechtigung (Meta gesperrt)
			else if(!$user->rechte['show_system_meta'] AND $row['statusStatus'] == $status_meta AND $user->allianz != $row['player_allianzenID']) {
				$tmpl->error = 'Du hast keine Berechtigung, das System '.$_GET['id'].' anzeigen zu lassen!';
			}
			// keine Berechtigung (registrierte Allianzen)
			else if(!$user->rechte['show_planet_register'] AND $row['register_allianzenID'] !== NULL AND $row['statusStatus'] != $status_meta) {
				$tmpl->error = 'Du hast keine Berechtigung, das System '.$_GET['id'].' anzeigen zu lassen!';
			}
		}
	}
	
	// heutigen Timestamp ermitteln
	$heute = strtotime('today');
	// Timestamp von letzter Woche ausrechnen
	$lastweek = time()-604800;
	
	// Fehler ausgeben und abbrechen
	/*if($tmpl->error != '') {
		$tmpl->output();
		die();
	}*/
	
	// Log-Eintrag
	if($config['logging'] >= 3) {
		insertlog(5, 'lässt das System '.$_GET['id'].' anzeigen');
	}
	
	// Freund-Myrigate im System
	$mg = false;
	
	$tmpl->name = 'G'.$data['systeme_galaxienID'].' '.$_GET['id'].' ('.htmlspecialchars($data['systemeName'], ENT_COMPAT, 'UTF-8').')';
	$tmpl->icon = 'system';
	
	$tmpl->content = '
		<div class="icontent" style="min-width:700px">';
	
	// Anzeigeberechtigung
	if(!$tmpl->error) {
		$tmpl->content .= '
		<div class="fcbox center small2">
			<span class="'.scan_color($data['systemeUpdate'], $config['scan_veraltet']).'">';
		// Scan-Datum
		if(!$data['systemeUpdateHidden']) {
			$tmpl->content .= 'Das System wurde noch nicht erfasst.<br />Bitte scanne es zumindest einmal verdeckt ein!';
		}
		else if(!$data['systemeUpdate']) {
			$tmpl->content .= 'noch kein voller Scan vorhanden';
		}
		else {
			$tmpl->content .= 'voller Scan '.datum($data['systemeUpdate'], true);
		}
		$tmpl->content .= '</span>';
		// verdeckter Scan
		if($data['systemeUpdateHidden'] > $data['systemeUpdate'] AND $data['systemeUpdateHidden'] > 1) {
			$tmpl->content .= ' &nbsp; (verdeckt '.datum($data['systemeUpdateHidden'], true).')';
		}
		$tmpl->content .= '
		</div>';
		// System wurde mindestens einmal verdeckt gescannt
		if($data['systemeUpdateHidden']) {
			// Myrigate- und Riss-Systeme ermitteln
			if($user->rechte['show_myrigates']) {
				$mgsys = array();
				// Ziel-Planeten ermitteln
				foreach($pldata as $pl) {
					if($pl['planetenMyrigate']) $mgsys[] = $pl['planetenMyrigate'];
					else if($pl['planetenRiss']) $mgsys[] = $pl['planetenRiss'];
				}
				
				// Ziel-Systeme ermitteln, wenn es Myrigates oder Risse im System gibt
				if(count($mgsys)) {
					$query = query("
						SELECT
							planetenID,
							planeten_systemeID
						FROM
							".PREFIX."planeten
						WHERE
							planetenID IN(".implode(', ', $mgsys).")
					") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
					
					$mgsys = array();
					while($row = mysql_fetch_assoc($query)) {
						$mgsys[$row['planetenID']] = $row['planeten_systemeID'];
					}
				}
			}
			
			// Invasionen etc. abfragen
			if($user->rechte['invasionen'] OR $user->rechte['fremdinvakolos']) {
				// laufende Invasionen etc ermitteln
				$ilabels = array(
					1=>'laufende Invasion',
					2=>'laufende Resonation',
					3=>'laufendes Genesis',
					4=>'laufende Besatzung',
					5=>'laufende Kolonisation'
				);

				$invasionen = array();
				
				$conds = array(
					"invasionen_systemeID = ".$_GET['id'],
					"(invasionenEnde > ".time()." OR invasionenEnde = 0)"
				);
				
				// Berechtigungen
				if(!$user->rechte['invasionen']) {
					$conds[] = "(invasionenFremd = 1 OR invasionenTyp = 5)";
				}
				if(!$user->rechte['fremdinvakolos']) {
					$conds[] = "(invasionenFremd = 0 OR invasionenTyp != 5)";
				}
				if($user->protectedAllies) {
					$conds[] = "(player_allianzenID IS NULL OR player_allianzenID NOT IN(".implode(", ", $user->protectedAllies)."))";
				}
				
				
				$query = query("
					SELECT
						invasionen_planetenID,
						invasionenTyp,
						invasionenEnde,
						
						playerName,
						allianzenTag
					FROM
						".PREFIX."invasionen
						LEFT JOIN ".GLOBPREFIX."player
							ON playerID = invasionenAggressor
						LEFT JOIN ".GLOBPREFIX."allianzen
							ON allianzenID = player_allianzenID
					WHERE
						".implode(' AND ', $conds)."
				") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
				
				while($row = mysql_fetch_assoc($query)) {
					if(!isset($invasionen[$row['invasionen_planetenID']])) {
						$invasionen[$row['invasionen_planetenID']] = array();
					}
					
					$invasionen[$row['invasionen_planetenID']][] = $row;
				}
			}
			
			// Ausgabe der Systemansicht
			$tmpl->content .= '
		<div class="sysshowbg">
			<div class="sysshowc">
				<table class="sysshowt">
				<tr class="sysshowtfirst">';
			// obere Zeile: ID, Name, Inhaber
			for($i=1;$i<=7;$i++) {
				if(isset($pldata[$i])) {
					$pl =& $pldata[$i];
					
					$tmpl->content .= '
					<td'.($pl['playerDeleted'] ? ' style="opacity:0.5;filter:alpha(opacity=50)"' : '').'>
						<a class="link winlink contextmenu link_planet" data-id="'.$pl['planetenID'].'" style="font-weight:bold" data-link="index.php?p=show_planet&amp;id='.$pl['planetenID'].'&amp;ajax">'.$pl['planetenID'].'</a>
						<br />
						<a class="link winlink contextmenu small link_planet" data-id="'.$pl['planetenID'].'" data-link="index.php?p=show_planet&amp;id='.$pl['planetenID'].'&amp;ajax">'.htmlspecialchars($pl['planetenName'], ENT_COMPAT, 'UTF-8').'</a>
						<br /><br />';
					// Inhaber
					if($pl['playerName'] != NULL) {
						$tmpl->content .= '
						<img src="img/layout/leer.gif" alt="" class="rasse showsysrasse '.$rassen2[$pl['playerRasse']].'" />
						<a class="link winlink contextmenu" data-link="index.php?p=show_player&amp;id='.$pl['planeten_playerID'].'&amp;ajax">'.htmlspecialchars($pl['playerName'], ENT_COMPAT, 'UTF-8').'</a>';
						if($pl['playerUmod']) {
							$tmpl->content .= '<sup class="small red">zzZ</sup>';
						}
					}
					// frei
					else if($pl['planeten_playerID'] == 0) {
						$tmpl->content .= '<i>frei</i>';
					}
					// Lux
					else if($pl['planeten_playerID'] == -2) {
						$tmpl->content .= '<span style="color:#ffff88;font-weight:bold;font-style:italic">Seze Lux</span>';
					}
					// Altrasse
					else if($pl['planeten_playerID'] == -3) {
						$tmpl->content .= '<span style="color:#ffff88;font-weight:bold;font-style:italic">Altrasse</span>';
					}
					// unbekannter Inhaber
					else {
						$tmpl->content .= '<i>unbekannt</i>';
					}
					
					// Allianz anzeigen, wenn Spieler bekannt
					if($pl['playerName'] != NULL) {
						$tmpl->content .= '
								<br />';
						// hat Allianz
						if($pl['allianzenTag'] != NULL) {
							if($pl['statusStatus'] == NULL) $pl['statusStatus'] = 0;
							$tmpl->content .= '<a class="link winlink contextmenu" data-link="index.php?p=show_ally&amp;id='.$pl['player_allianzenID'].'&amp;ajax">'.htmlspecialchars($pl['allianzenTag'], ENT_COMPAT, 'UTF-8').'</a>';
							// Status, wenn nicht eigene Allianz
							if($pl['player_allianzenID'] != $user->allianz) {
								$tmpl->content .= '<br /><span class="small hint" '.$status_color[$pl['statusStatus']].'>('.$status[$pl['statusStatus']].')</span>';
							}
						}
						// allianzlos
						else if(!$pl['player_allianzenID']) {
							//$tmpl->content .= '<i>allianzlos</i>';
						}
						// unbekannte Allianz
						else {
							$tmpl->content .= '<i>unbekannte Allianz</i>';
						}
					}
					$tmpl->content .= '
					</td>';
				}
				// kein Planet an dieser Position
				else {
					$tmpl->content .= '
					<td></td>';
				}
			}
			$tmpl->content .= '
				</tr>
				<tr>';
			// zweite Zeile: Planeten-Scan
			for($i=1;$i<=7;$i++) {
				if(isset($pldata[$i])) {
					$pl =& $pldata[$i];
					
					// Berechtigung überprüfen, den Scan zu sehen
					$r = Rechte::getRechteShowPlanet($pl);
					
					// Tooltip erzeugen
					$tt = '&lt;span style=&quot;line-height:18px&quot;&gt; &lt;b&gt;Gr&amp;ouml;&amp;szlig;e&lt;/b&gt;: '.($pl['planetenGroesse'] ? $pl['planetenGroesse'] : '&lt;span class=&quot;red&quot;&gt;unbewohnbar&lt;/span&gt;');
					
					if($pl['planetenBevoelkerung']) {
						$tt .= ' &lt;br /&gt; &lt;b&gt;Bev&amp;ouml;lkerung&lt;/b&gt;: '.ressmenge($pl['planetenBevoelkerung']).' &lt;/span&gt;';
					}
					
					if($pl['planetenOrbiter']) {
						$tt .= ' &lt;br /&gt; &lt;b&gt;Orbiter-Angriff&lt;/b&gt;: '.ressmenge($pl['planetenOrbiter']).' &lt;/span&gt;';
					}
					
					$tt .= '&lt;br /&gt;&lt;br /&gt;';

					// geraidet oder getoxxt?
					if($user->rechte['toxxraid'] AND ($pl['planetenGeraidet'] > $lastweek OR $pl['planetenGetoxxt'] > time())) {
						$tt .= '&lt;div class=&quot;showsysttadd&quot;&gt;';
						// geraidet
						if($pl['planetenGeraidet'] > $lastweek) {
							 $tt .= 'geraidet: '.datum($pl['planetenGeraidet']);
						}
						// getoxxt
						if($pl['planetenGetoxxt'] > time()) {
							 $tt .= '&lt;br /&gt;&lt;br /&gt;Toxx: '.datum($pl['planetenGetoxxt']);
						}
						$tt .= '&lt;/div&gt;';
					}
					$tt .= '&lt;table class=&quot;showsysresst&quot;&gt; &lt;tr&gt; &lt;td&gt;&lt;div class=&quot;ress erz&quot;&gt;&lt;/div&gt;&lt;/td&gt; &lt;td&gt;'.$pl['planetenRWErz'].'%&lt;/td&gt; &lt;td&gt;'.($pl['planetenUpdateOverview'] ? ressmenge($pl['planetenRMErz']) : '').'&lt;/td&gt; &lt;/tr&gt; &lt;tr&gt; &lt;td&gt;&lt;div class=&quot;ress metall&quot;&gt;&lt;/div&gt;&lt;/td&gt; &lt;td&gt;100%&lt;/td&gt; &lt;td&gt;'.($pl['planetenUpdateOverview'] ? ressmenge($pl['planetenRMMetall']) : '').'&lt;/td&gt; &lt;/tr&gt; &lt;tr&gt; &lt;td&gt;&lt;div class=&quot;ress wolfram&quot;&gt;&lt;/div&gt;&lt;/td&gt; &lt;td&gt;'.$pl['planetenRWWolfram'].'%&lt;/td&gt; &lt;td&gt;'.($pl['planetenUpdateOverview'] ? ressmenge($pl['planetenRMWolfram']) : '').'&lt;/td&gt; &lt;/tr&gt; &lt;tr&gt; &lt;td&gt;&lt;div class=&quot;ress kristall&quot;&gt;&lt;/div&gt;&lt;/td&gt; &lt;td&gt;'.$pl['planetenRWKristall'].'%&lt;/td&gt; &lt;td&gt;'.($pl['planetenUpdateOverview'] ? ressmenge($pl['planetenRMKristall']) : '').'&lt;/td&gt; &lt;/tr&gt; &lt;tr&gt; &lt;td&gt;&lt;div class=&quot;ress fluor&quot;&gt;&lt;/div&gt;&lt;/td&gt; &lt;td&gt;'.$pl['planetenRWFluor'].'%&lt;/td&gt; &lt;td&gt;'.($pl['planetenUpdateOverview'] ? ressmenge($pl['planetenRMFluor']) : '').'&lt;/td&gt; &lt;/tr&gt; &lt;/table&gt; '.(trim($pl['planetenKommentar']) != '' ? '&lt;br /&gt;&lt;br /&gt; Kommentar: '.htmlspecialchars(nl2br(htmlspecialchars($pl['planetenKommentar'], ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8') : '').($pl['planetenNatives'] ? '&lt;br /&gt;&lt;br /&gt; &lt;b&gt;'.$pl['planetenNatives'].' Natives&lt;/b&gt;' : '');
					
					// keine Berechtigung
					if(!$r) {
						$tmpl->content .= '
					<td'.($pl['playerDeleted'] ? ' style="opacity:0.5;filter:alpha(opacity=50)"' : '').'>
						<div style="height:54px"></div>
						<img src="img/planeten/'.$pl['planetenTyp'].'.jpg" width="100" height="100" alt="" />
						<span class="red">keine Berechtigung</span>';
					}
					// Planet noch nicht gescannt
					else if(!$pl['planetenUpdateOverview']) {
						$tmpl->content .= '
					<td'.($pl['playerDeleted'] ? ' style="opacity:0.5;filter:alpha(opacity=50)"' : '').'>
						<div style="height:54px"></div>
						<img src="img/planeten/'.$pl['planetenTyp'].'.jpg" width="100" height="100" alt="" class="link winlink contextmenu tooltip" data-link="index.php?p=show_planet&amp;id='.$pl['planetenID'].'&amp;ajax" data-tooltip="'.$tt.'" />';
						// Kommentar-Icon
						if(trim($pl['planetenKommentar']) != '') {
							$tmpl->content .= '<div class="kommentar" style="float:left"></div>';
						}
						$tmpl->content .= '
						<span class="red">noch nicht gescannt</span>';
						
						// Unscannbar
						if($pl['planetenUnscannbar'] > $pl['planetenUpdateOverview']) {
							$tmpl->content .= '<div class="red center bold">unscannbar!</div>';
						}
					}
					// Planet gescannt
					else {
						// Gebäude-Positionen ermitteln
						$gpl = gebaeude($pl['planetenGebPlanet'], $pl['planetenGroesse'], true);
						$gor = gebaeude($pl['planetenGebOrbit'], false, true);
						$gspec = gebaeude($pl['planetenGebSpezial'], false, true);
						
						// Scan-Farbe
						$tmpl->content .= '
					<td'.($pl['playerDeleted'] ? ' style="opacity:0.5;filter:alpha(opacity=50)"' : '').'>
						<div style="position:relative">
							<div class="link winlink contextmenu tooltip showsyslink" data-link="index.php?p=show_planet&amp;id='.$pl['planetenID'].'" data-tooltip="'.$tt.'"></div>
						</div>
						
						<table class="sysshoworgebt">
						<tr>
							<td style="background-position:-'.$gor[1].'px 0px"></td>
							<td style="background-position:-'.$gor[2].'px 0px"></td>
							<td style="background-position:-'.$gor[3].'px 0px"></td>
							<td style="background-position:-'.$gor[4].'px 0px"></td>
							<td style="background-position:-'.$gor[5].'px 0px"></td>
							<td style="background-position:-'.$gor[6].'px 0px"></td>
						</tr>
						<tr>
							<td style="background-position:-'.$gor[7].'px 0px"></td>
							<td style="background-position:-'.$gor[8].'px 0px"></td>
							<td style="background-position:-'.$gor[9].'px 0px"></td>
							<td style="background-position:-'.$gor[10].'px 0px"></td>
							<td style="background-position:-'.$gor[11].'px 0px"></td>
							<td style="background-position:-'.$gor[12].'px 0px"></td>
						</tr>
						</table>
						<table class="sysshoworgebt">
						<tr>
							<td style="background-position:-'.$gspec[1].'px 0px"></td>
							<td style="background-position:-'.$gspec[2].'px 0px"></td>
							<td style="background-position:-'.$gspec[3].'px 0px"></td>
							<td style="background-position:-'.$gspec[4].'px 0px"></td>
							<td style="background-position:-'.$gspec[5].'px 0px"></td>
							<td style="background-position:-'.$gspec[6].'px 0px"></td>
						</tr>
						<tr>
							<td style="background-position:-'.$gspec[7].'px 0px"></td>
							<td style="background-position:-'.$gspec[8].'px 0px"></td>
							<td style="background-position:-'.$gspec[9].'px 0px"></td>
							<td style="background-position:-'.$gspec[10].'px 0px"></td>
							<td></td>
							<td></td>
						</tr>
						</table>
						<table class="sysshowplgebt" style="background-image:url(img/planeten/'.$pl['planetenTyp'].'.jpg)">
						<tr>
							<td style="background-position:-'.$gpl[36].'px 0px"></td>
							<td style="background-position:-'.$gpl[35].'px 0px"></td>
							<td style="background-position:-'.$gpl[29].'px 0px"></td>
							<td style="background-position:-'.$gpl[23].'px 0px"></td>
							<td style="background-position:-'.$gpl[30].'px 0px"></td>
							<td style="background-position:-'.$gpl[34].'px 0px"></td>
						</tr>
						<tr>
							<td style="background-position:-'.$gpl[32].'px 0px"></td>
							<td style="background-position:-'.$gpl[24].'px 0px"></td>
							<td style="background-position:-'.$gpl[18].'px 0px"></td>
							<td style="background-position:-'.$gpl[10].'px 0px"></td>
							<td style="background-position:-'.$gpl[19].'px 0px"></td>
							<td style="background-position:-'.$gpl[25].'px 0px"></td>
						</tr>
						<tr>
							<td style="background-position:-'.$gpl[28].'px 0px"></td>
							<td style="background-position:-'.$gpl[14].'px 0px"></td>
							<td style="background-position:-'.$gpl[6].'px 0px"></td>
							<td style="background-position:-'.$gpl[2].'px 0px"></td>
							<td style="background-position:-'.$gpl[7].'px 0px"></td>
							<td style="background-position:-'.$gpl[15].'px 0px"></td>
						</tr>
						<tr>
							<td style="background-position:-'.$gpl[22].'px 0px"></td>
							<td style="background-position:-'.$gpl[13].'px 0px"></td>
							<td style="background-position:-'.$gpl[5].'px 0px"></td>
							<td style="background-position:-'.$gpl[1].'px 0px"></td>
							<td style="background-position:-'.$gpl[3].'px 0px"></td>
							<td style="background-position:-'.$gpl[11].'px 0px"></td>
						</tr>
						<tr>
							<td style="background-position:-'.$gpl[31].'px 0px"></td>
							<td style="background-position:-'.$gpl[17].'px 0px"></td>
							<td style="background-position:-'.$gpl[9].'px 0px"></td>
							<td style="background-position:-'.$gpl[4].'px 0px"></td>
							<td style="background-position:-'.$gpl[8].'px 0px"></td>
							<td style="background-position:-'.$gpl[16].'px 0px"></td>
						</tr>
						<tr>
							<td style="background-position:-'.$gpl[33].'px 0px"></td>
							<td style="background-position:-'.$gpl[27].'px 0px"></td>
							<td style="background-position:-'.$gpl[21].'px 0px"></td>
							<td style="background-position:-'.$gpl[12].'px 0px"></td>
							<td style="background-position:-'.$gpl[20].'px 0px"></td>
							<td style="background-position:-'.$gpl[26].'px 0px"></td>
						</tr>
						</table>';
						// Kommentar-Icon
						if(trim($pl['planetenKommentar']) != '') {
							$tmpl->content .= '<div class="kommentar" style="float:left;margin-top:-5px"></div>';
						}
						
						$tmpl->content .= '
						<span class="'.scan_color($pl['planetenUpdateOverview'], $config['scan_veraltet']).'">Scan: '.($pl['planetenUpdateOverview'] > $heute ? 'heute' : strftime('%d.%m.%y', $pl['planetenUpdateOverview'])).'</span>';
						
						// Unscannbar
						if($pl['planetenUnscannbar'] > $pl['planetenUpdateOverview']) {
							$tmpl->content .= '<div class="red center bold">unscannbar!</div>';
						}
					}
					// Gate
					$o = false;
					
					if($pl['planetenID'] == $data['galaxienGate']) {
						$o = true;
						$tmpl->content .= '<br />
						<img src="img/orbit/gate.gif" width="100" height="90" alt="Gate" class="tooltip" data-tooltip="Gate G'.$data['systeme_galaxienID'].'" />';
					}
					// Myrigate oder Riss (wenn Planet sichtbar)
					else if($pl['planetenMyrigate'] OR $pl['planetenRiss']) {
						// Berechtigungen ermitteln
						$r = Rechte::getRechteShowMyrigate($pl);
						
						// Berechtigung
						if($r) {
							$o = true;
							// Myrigate
							if($pl['planetenMyrigate']) {
								// Freund-Myrigate zwischenspeichern -> weitere Abfrage unnötig
								if($mg === false AND ($pl['player_allianzenID'] == $user->allianz OR in_array($pl['statusStatus'], $status_freund)) AND $pl['planetenMyrigate'] > 2) {
									$mg = $pl;
								}
								
								// Sprunggenerator
								if($pl['planetenMyrigate'] == 2) {
									$tmpl->content .= '
						<br />
						<img src="img/orbit/sprunggenerator.gif" width="100" height="90" alt="Sprunggenerator" class="tooltip" data-tooltip="Sprunggenerator" />';
								}
								// Ziel bekannt
								else if(isset($mgsys[$pl['planetenMyrigate']])) {
									$tmpl->content .= '
						<br />
						<img src="img/orbit/mgate.gif" width="100" height="90" alt="Myrigate" class="link contextmenu tooltip" data-tooltip="Myrigate nach '.$pl['planetenMyrigate'].' (System '.$mgsys[$pl['planetenMyrigate']].')" data-link="index.php?p=show_system&amp;id='.$mgsys[$pl['planetenMyrigate']].'" />';
								}
								else {
									$tmpl->content .= '
						<br />
						<img src="img/orbit/mgate.gif" width="100" height="90" alt="Myrigate" class="tooltip" data-tooltip="Myrigate nach '.$pl['planetenMyrigate'].'" />';
								}
							}
							// Riss
							else {
								if(isset($mgsys[$pl['planetenRiss']])) {
									$tmpl->content .= '
						<br />
						<img src="img/orbit/riss.gif" width="100" height="90" alt="Riss" class="link contextmenu tooltip" data-tooltip="Riss von '.$pl['planetenRiss'].' (System '.$mgsys[$pl['planetenRiss']].')" data-link="index.php?p=show_system&amp;id='.$mgsys[$pl['planetenRiss']].'" />';
								}
								else {
									$tmpl->content .= '
						<br />
							<img src="img/orbit/riss.gif" width="100" height="90" alt="Riss" class="tooltip" data-tooltip="Riss von '.$pl['planetenRiss'].'" />';
								}
							}
						}
					}
					
					// Bergbau und Terraformer
					$bergbau_tf = '';
					
					// Natives
					if($pl['planetenNatives'] > 0) {
						$bergbau_tf .= '<span class="red">Natives</span><br />';
					}
					
					if($user->rechte['fremdinvakolos']) {
						// Bergbau
						if($pl['schiffeBergbau'] !== NULL) {
							$bergbau_tf .= '<span class="lightgreen">Bergbau';
							
							// Bergbauer bekannt
							if($pl['BergbauName'] AND $pl['schiffeBergbau'] > 0) {
								$bergbau_tf .= ' <a class="link winlink contextmenu lightgreen" data-link="index.php?p=show_player&amp;id='.$pl['schiffeBergbau'].'&amp;ajax">'.h($pl['BergbauName']).'</a>';
								
								if($pl['BergbauAllyTag']) {
									$bergbau_tf .= ' <a class="link winlink contextmenu lightgreen" data-link="index.php?p=show_ally&amp;id='.$pl['BergbauAllyID'].'&amp;ajax">'.h($pl['BergbauAllyTag']).'</a>';
								}
							}
							
							$bergbau_tf .= '</span>';
						}
						// Terraformer
						if($pl['schiffeTerraformer']) {
							$bergbau_tf .= '<span class="lightgreen">Terraformer</span>';
						}
					}
					
					// Invasionen etc
					if(isset($invasionen[$pl['planetenID']])) {
						
						$tmpl->content .= '<div class="orbitinva'.($o ? '2' : '1').'">';
						
						foreach($invasionen[$pl['planetenID']] as $inva) {
						
							if(!isset($ilabels[$inva['invasionenTyp']])) {
								continue;
							}
						
							$tmpl->content .= '
								<div style="color:#ff3322;margin-bottom:0.5em">'.$ilabels[$inva['invasionenTyp']].'<br />';
							if($inva['playerName'] != NULL) {
								$tmpl->content .= htmlspecialchars($inva['playerName'], ENT_COMPAT, 'UTF-8');
								if($inva['allianzenTag'] != NULL) {
									$tmpl->content .= ' '.htmlspecialchars($inva['allianzenTag'], ENT_COMPAT, 'UTF-8');
								}
							}
							// Ende bei Besatzungen nicht anzeigen
							if($inva['invasionenTyp'] != 4) {
								$tmpl->content .= '<br />Ende: '.($inva['invasionenEnde'] ? datum($inva['invasionenEnde']) : '<i>unbekannt</i>');
							}
							
							$tmpl->content .= '</div>';
						}
						
						// Bergbau und Terraformer anhängen
						$tmpl->content .= $bergbau_tf.'</div>';
					}
					// nur Bergbau und Terraformer anzeigen
					else if($bergbau_tf != '') {
						$tmpl->content .= '
							<div class="orbitinva'.($o ? '2' : '1').'">
							'.$bergbau_tf.'
							</div>';
					}
					
					
					$tmpl->content .= '
				</td>';
				}
				// kein Planet an dieser Position
				else {
					$tmpl->content .= '
					<td></td>';
				}
			}
			$tmpl->content .= '
				</tr>
				</table>
			</div>
		</div>';
		}
	}
	// keine Anzeigeberechtigung
	else {
		$tmpl->content .= '<div class="icontent" style="text-align:center;margin:20px;font-size:16px;font-weight:bold"><img src="img/layout/error.png" width="150" height="137" alt="Fehler" /><br /><br />'.$tmpl->error.'</div>';
		$tmpl->error = '';
	}
	
	
	// Einstellungen ermitteln
	$fow = json_decode($user->settings['fow'], true);
	
	// FoW-Tabelle
	if(count($fow)) {
		$tmpl->content .= '
		<table class="data small2" style="width:100%;margin-top:5px">
		<tr>
			<th>&nbsp;</th>
			<th>System</th>
			<th>Planet</th>
			<th>Inhaber</th>
			<th>Allianz</th>
			<th>Entf (A'.$user->settings['antrieb'].')</th>
			<th>Scan</th>
			<th>&nbsp;</th>
		</tr>';
		
		// Gate
		if(isset($fow['gate'])) {
			$tmpl->content .= '
		<tr>
			<td>Gate G'.$data['systeme_galaxienID'].'</td>';
			if($data['galaxienGate']) {
				$tmpl->content .= '
			<td>'.datatable::system($data['galaxienGateSys']).'</td>
			<td>'.datatable::planet($data['galaxienGate']).'</a></td>
			<td><a class="link winlink contextmenu" data-link="index.php?p=show_player&amp;id=1&amp;ajax">X</a></td>
			<td>&nbsp;</td>
			<td>'.flugdauer($data['systemeGateEntf'], $user->settings['antrieb']).'</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>';
			}
			else {
				$tmpl->content .= '
			<td colspan="7"><i>noch kein Gate einscannt</i></td>';
			}
			$tmpl->content .= '
		</tr>';
		}
		
		// Myrigates
		if($user->rechte['show_myrigates'] AND isset($fow['mgate'])) {
			$tmpl->content .= '
		<tr>
			<td>n&auml;chstes Myrigate</td>';
			
			// Freund-Myrigate im System -> zusätzliche Daten ermitteln
			if($mg) {
				$mg['planetenEntfernung'] = entf(0,0,0,1,0,0,0,$mg['planetenPosition']);
				$mg['planeten_systemeID'] = $_GET['id'];
				$mg['systemeUpdate'] = $data['systemeUpdate'];
			}
			// Myrigates abfragen
			else {
				// alte Status-Abfrage
				// AND (statusStatus IS NULL OR statusStatus != ".$status_krieg.")
				//
				$query = query("
					SELECT
						planetenID,
						planeten_systemeID,
						planeten_playerID,
						
						systemeUpdate,
						
						playerName,
						playerUmod,
						player_allianzenID,
						
						allianzenTag,
						
						register_allianzenID,
						
						statusStatus,
						
						".entf_mysql("systemeX", "systemeY", "systemeZ", "planetenPosition", $data['systemeX'], $data['systemeY'], $data['systemeZ'], "1")." AS planetenEntfernung
					FROM
						".PREFIX."myrigates
						LEFT JOIN ".PREFIX."planeten
							ON planetenID = myrigates_planetenID
						LEFT JOIN ".PREFIX."systeme
							ON systemeID = planeten_systemeID
						LEFT JOIN ".GLOBPREFIX."player
							ON playerID = planeten_playerID
						LEFT JOIN ".GLOBPREFIX."allianzen
							ON allianzenID = player_allianzenID
						LEFT JOIN ".PREFIX."register
							ON register_allianzenID = allianzenID
						LEFT JOIN ".PREFIX."allianzen_status
							ON statusDBAllianz = ".$user->allianz."
							AND status_allianzenID = allianzenID
					WHERE
						myrigates_galaxienID = ".$data['systeme_galaxienID']."
						AND (
							(myrigatesSprung = 0 AND statusStatus IN(".implode(", ", $status_freund)."))
							OR
							(myrigatesSprung > 0 AND myrigatesSprungFeind = 0
								AND 
								(statusStatus IS NULL OR statusStatus NOT IN(".implode(", ", $status_feind)."))
							)
						)
					ORDER BY
						planetenEntfernung ASC
				") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
				
				while($row = mysql_fetch_assoc($query)) {
					// Berechtigung
					$r = Rechte::getRechteShowMyrigate($row);
					
					// nächstes Myrigate
					if($r) {
						$mg = $row;
						break;
					}
				}
			}
			// Myrigate gefunden
			if($mg) {
				$tmpl->content .= '
			<td>'.datatable::system($mg['planeten_systemeID']).'</td>
			<td>'.datatable::planet($mg['planetenID']).'</a></td>
			<td>'.datatable::inhaber($mg['planeten_playerID'], $mg['playerName'], $mg['playerUmod']).'</td><td>'.datatable::allianz($mg['player_allianzenID'], $mg['allianzenTag'], $mg['statusStatus'], true).'</td>
			<td>'.flugdauer($mg['planetenEntfernung'], $user->settings['antrieb']).'</td>
			<td>'.datatable::scan($mg['systemeUpdate'], $config['scan_veraltet']).'</td>
			<td><a class="link winlink contextmenu hint small2" data-link="index.php?p=search&amp;s=1&amp;g='.$data['systeme_galaxienID'].'&amp;mg=on&amp;sortt=1&amp;entf=sys'.$_GET['id'].'&amp;as=-1&amp;hide&amp;title='.urlencode('Myrigates von '.$_GET['id'].' aus').'&amp;ajax">[weitere]</a></td>
		</tr>';
			}
			// kein Myrigate gefunden
			else {
				$tmpl->content .= '
			<td colspan="7" style="font-style:italic">keine Freund-Myrigates in G'.$data['systeme_galaxienID'].' gefunden</td>
		</tr>';
			}
		}
		
		// Scoutziel
		if(isset($fow['scout'])) {
			$count = isset($fow['scoutcount']) ? $fow['scoutcount'] : 1;
			$scout_days = $fow['scout'] ? $fow['scout'] : $config['scan_veraltet'];
			
			$query = query("
				SELECT
					systemeID,
					systemeUpdate,
					".entf_mysql("systemeX", "systemeY", "systemeZ", "1", $data['systemeX'], $data['systemeY'], $data['systemeZ'], "1")." AS systemeEntfernung,
					
					planetenID,
					planeten_playerID,
					
					playerName,
					playerUmod,
					player_allianzenID,
					
					allianzenTag,
					
					statusStatus
				FROM
					".PREFIX."systeme
					LEFT JOIN ".PREFIX."planeten
						ON planeten_systemeID = systemeID
					LEFT JOIN ".GLOBPREFIX."player
						ON playerID = planeten_playerID
					LEFT JOIN ".GLOBPREFIX."allianzen
						ON allianzenID = player_allianzenID
					LEFT JOIN ".PREFIX."allianzen_status
						ON statusDBAllianz = ".$user->allianz."
						AND status_allianzenID = allianzenID
				WHERE
					systeme_galaxienID = ".$data['systeme_galaxienID']."
					AND systemeUpdate < ".(time()-$scout_days*86400)."
					AND systemeScanReserv < ".(time()-86400)."
					AND systemeID != ".$_GET['id']."
				ORDER BY
					systemeEntfernung ASC,
					planetenID ASC
				LIMIT ".$count."
			") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
			
			// nicht alle Systeme aktuell
			if(mysql_num_rows($query)) {
				$first = true;
				
				while($row = mysql_fetch_assoc($query)) {
					$tmpl->content .= '
		<tr>
			<td>'.($first ? 'Scoutziel' : '&nbsp;').'</td>
			<td>'.datatable::system($row['systemeID']).'</td>
			<td>'.datatable::planet($row['planetenID']).'</a></td>
			<td>'.datatable::inhaber($row['planeten_playerID'], $row['playerName'], $row['playerUmod']).'</td>
			<td>'.datatable::allianz($row['player_allianzenID'], $row['allianzenTag'], $row['statusStatus'], true).'</td>
			<td>'.flugdauer($row['systemeEntfernung'], $user->settings['antrieb']).'</td>
			<td>'.datatable::scan($row['systemeUpdate'], $config['scan_veraltet']).'</td>
			<td><span><a onclick="ajaxcall(\'index.php?p=ajax_general&amp;sp=reserve&amp;sys='.$row['systemeID'].'&amp;ajax\', this.parentNode, false, false)">reservieren</a></span> 
			'.($first ? '&nbsp;
			<a class="link winlink contextmenu hint" data-link="index.php?p=scout&amp;sp=extern_send&amp;start=sys'.$_GET['id'].'&amp;days='.$config['scan_veraltet'].'&amp;antrieb='.$user->settings['antrieb'].'&amp;syscount=15&amp;hidereserv">[weitere]</a>' : '').'</td>
		</tr>';
					$first = false;
				}
			}
			// alle Systeme aktuell
			else {
				$tmpl->content .= '
		<tr>
			<td>Scoutziel</td>
			<td colspan="7">alle Systeme in G'.$data['systeme_galaxienID'].' sind aktueller als '.$scout_days.' Tage</td>
		</tr>';
			}
		}
		
		// Routen und Listen
		if(isset($fow['routen']) AND count($fow['routen']) AND $user->rechte['routen']) {
			
			General::loadClass('route');
			
			$rids = array_keys($fow['routen']);
			
			$routen = array();
			
			// Daten abfragen
			$query = query("
				SELECT
					routenID,
					routenDate,
					routen_playerID,
					routen_galaxienID,
					routenName,
					routenListe,
					routenTyp,
					routenEdit,
					routenFinished,
					routenData,
					routenCount,
					routenMarker,
					routenAntrieb,
					
					user_playerName,
					user_allianzenID,
					statusStatus
				FROM
					".PREFIX."routen
					LEFT JOIN ".PREFIX."user
						ON user_playerID = routen_playerID
					LEFT JOIN ".PREFIX."allianzen_status
						ON statusDBAllianz = user_allianzenID
						AND status_allianzenID = ".$user->allianz."
				WHERE
					routenID IN(".implode(", ", $rids).")
					AND (routen_galaxienID = ".$data['systeme_galaxienID']." OR routen_galaxienID = 0)
					AND (routenFinished = 1 OR routenListe = 1)
			") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
			
			while($row = mysql_fetch_assoc($query)) {
				$routen[$row['routenID']] = $row;
			}
			
			$rziele = array();
			$rzieleall = array();
			
			// Routen durchgehen und Zielplaneten berechnen
			foreach($rids as $id) {
				if(isset($routen[$id])) {
					// Routen-Klasse laden
					$route = new route;
					if($route->load($id, $routen[$id]) === true) {
						$rziele[$id] = $route->compute_next($_GET['id'], $fow['routen'][$id]);
						if(is_array($rziele[$id])) {
							$rzieleall = array_merge($rzieleall, $rziele[$id]);
						}
						else {
							unset($rziele[$id]);
						}
					}
				}
			}
			
			// doppelte IDs entfernen
			array_unique($rzieleall);
			
			$rdata = array();
			
			if(count($rzieleall)) {
				// Bedingungen aufstellen
				$conds = array(
					"systeme_galaxienID = ".$data['systeme_galaxienID'],
					"planetenID IN(".implode(", ", $rzieleall).")"
				);
				
				// Planetendaten abfragen
				$query = query("
					SELECT
						planetenID,
						planeten_playerID,
						
						systemeID,
						systemeUpdate,
						".entf_mysql("systemeX", "systemeY", "systemeZ", "planetenPosition", $data['systemeX'], $data['systemeY'], $data['systemeZ'], "1")." AS planetenEntfernung,
						
						playerName,
						playerUmod,
						player_allianzenID,
						
						allianzenTag,
						
						statusStatus
					FROM
						".PREFIX."planeten
						LEFT JOIN ".PREFIX."systeme
							ON systemeID = planeten_systemeID
						LEFT JOIN ".GLOBPREFIX."player
							ON playerID = planeten_playerID
						LEFT JOIN ".GLOBPREFIX."allianzen
							ON allianzenID = player_allianzenID
						LEFT JOIN ".PREFIX."allianzen_status
							ON statusDBAllianz = ".$user->allianz."
							AND status_allianzenID = allianzenID
					WHERE
						".implode(" AND ", $conds)."
					ORDER BY
						NULL
				") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
				
				while($row = mysql_fetch_assoc($query)) {
					// Inhaber von gesperrten Allianzen ausblenden
					if($user->protectedAllies AND $row['player_allianzenID'] AND in_array($row['player_allianzenID'], $user->protectedAllies)) {
						$row['playerName'] = ' ';
						$row['allianzenTag'] = ' ';
						$row['planeten_playerID'] = 1;
						$row['player_allianzenID'] = 1;
					}
					$rdata[$row['planetenID']] = $row;
				}
			}
			
			// Routen durchgehen und ausgeben
			foreach($rids as $id) {
				if(isset($rziele[$id])) {
					$first = true;
					
					foreach($rziele[$id] as $plid) {
						if(isset($rdata[$plid])) {
							$row = $rdata[$plid];
							
							$tmpl->content .= '
		<tr>
			<td>'.($first ? htmlspecialchars($routen[$id]['routenName'], ENT_COMPAT, 'UTF-8') : '&nbsp;').'</td>
			<td>'.datatable::system($row['systemeID']).'</td>
			<td>'.datatable::planet($row['planetenID']).'</a></td>
			<td>'.datatable::inhaber($row['planeten_playerID'], $row['playerName'], $row['playerUmod']).'</td>
			<td>'.datatable::allianz($row['player_allianzenID'], $row['allianzenTag'], $row['statusStatus']).'</td>
			<td>'.flugdauer($row['planetenEntfernung'], ($routen[$id]['routenAntrieb'] ? $routen[$id]['routenAntrieb'] : $user->settings['antrieb'])).'</td>
			<td>'.datatable::scan($row['systemeUpdate'], $config['scan_veraltet']).'</td>
			<td>';
							
							// weitere-Link
							if($first) {
								if($routen[$id]['routenListe'] == 1) {
									$tmpl->content .= '<a class="link winlink contextmenu hint" data-link="index.php?p=route&amp;sp=view&amp;id='.$id.'&amp;sort=sys'.$_GET['id'].'">[weitere]</a>';
								}
								else {
									$tmpl->content .= '<a class="link winlink contextmenu hint" data-link="index.php?p=route&amp;sp=view&amp;id='.$id.'">[&ouml;ffnen]</a>';
								}
							}
							else {
								$tmpl->content .= '&nbsp;';
							}
							
							$tmpl->content .= '</td>
		</tr>';
							
							
							$first = false;
						}
					}
				}
			}
		}
		
		
		// Suchfilter
		if(isset($fow['search'])) {
			
			General::loadClass('Search');
			
			$entf = entf_mysql("systemeX", "systemeY", "systemeZ", "planetenPosition", $data['systemeX'], $data['systemeY'], $data['systemeZ'], "1");
			
			foreach($fow['search'] as $val) {
				
				/*
				 * 0 - Name
				 * 1 - Anzahl
				 * 2 - Sortierung
				 * 3 - Wert
				 * 4 - außerhalb des Systems (optional)
				 */
				
				
				// Filter erstellen
				$filter = array();
				parse_str($val[3], $filter);
				
				// auch nach Galaxie filtern
				$filter['g'] = $data['systeme_galaxienID'];
				
				$conds = Search::buildConditions($filter);
				
				if(isset($val[4])) {
					$conds[] = 'systemeID != '.$_GET['id'];
				}
				
				$sort = $entf." ".($val[2] ? "DESC" : "ASC");
				
				$query = Search::getSearchAsMySQL($conds, $entf, $sort, 0, $val[1]);
				
				// keinen Planeten gefunden
				if(!mysql_num_rows($query)) {
					$tmpl->content .= '
		<tr>
			<td>'.htmlspecialchars($val[0], ENT_COMPAT, 'UTF-8').'</td>
			<td colspan="7"><i>kein Planet gefunden</i></td>
		</tr>';
				}
				// Planeten gefunden
				else {
					$first = true;
					
					while($row = mysql_fetch_assoc($query)) {
						
						$tmpl->content .= '
		<tr>
			<td>'.($first ? htmlspecialchars($val[0], ENT_COMPAT, 'UTF-8') : '&nbsp;').'</td>
			<td>'.datatable::system($row['systemeID']).'</td>
			<td>'.datatable::planet($row['planetenID']).'</a></td>
			<td>'.datatable::inhaber($row['planeten_playerID'], $row['playerName'], $row['playerUmod']).'</td>
			<td>'.datatable::allianz($row['player_allianzenID'], $row['allianzenTag'], $row['statusStatus'], true).'</td>
			<td>'.flugdauer($row['planetenEntfernung'], $user->settings['antrieb']).'</td>
			<td>'.datatable::scan($row['systemeUpdate'], $config['scan_veraltet']).'</td>
			<td>'.($first ? '<a class="link winlink contextmenu hint" data-link="index.php?p=search&amp;sp=planet&amp;s=1&amp;hide&amp;'.h($val[3]).'&amp;g='.$data['systeme_galaxienID'].'&amp;sortt=1&amp;entf=sys'.$_GET['id'].($val[2] ? '&amp;sorto3=1' : '').'">[weitere]</a>' : '&nbsp;').'</td>
		</tr>';
						
						$first = false;
					}
				}
			}
		}
		
		$tmpl->content .= '
		</table>';
	}
	
	// Aktionen
	$tmpl->content .= '	
		<div class="fcbox center small2" style="line-height:25px">
		<table class="tneutral" style="margin:auto">
		<tr>
		<td style="width:50%;vertical-align:top">
			<div>';
	// Scan reservieren
	if($data['systemeScanReserv'] < time()-86400) {
		$tmpl->content .= '<a onclick="ajaxcall(\'index.php?p=ajax_general&amp;sp=reserve&amp;sys='.$_GET['id'].'&amp;long&amp;ajax\', this.parentNode, false, false)">Scan reservieren</a>';
	}
	// Scan bereits reserviert
	else {
		$tmpl->content .= '<i>Scan reserviert von '.htmlspecialchars($data['systemeReservUser'], ENT_COMPAT, 'UTF-8').'</i>';
	}
	$tmpl->content .= '</div>';
	// in OD öffnen
	$tmpl->content .= '
	<div>
		<a href="'.($user->odServer != '' ? $user->odServer : 'http://www.omega-day.com').'/game/?op=system&amp;sys='.$_GET['id'].'" target="_blank">System in OD &ouml;ffnen</a>
	</div>';
	// auf Karte anzeigen
	if($user->rechte['karte']) {
		$tmpl->content .= '
	<a class="link winlink contextmenu" data-link="index.php?p=karte&amp;gala='.$data['systeme_galaxienID'].'&amp;highlight='.$_GET['id'].'&amp;title=System%20'.$_GET['id'].'">System auf der Karte anzeigen</a>';
	}
	$tmpl->content .= '
	</td>
	<td style="vertical-align:top">';
	// benachbarte Systeme, Entfernung
	if($user->rechte['strecken_flug']) {
		$tmpl->content .= '
			<a class="link winlink contextmenu" data-link="index.php?p=strecken&amp;sp=flug_next&amp;start=sys'.$_GET['id'].'&amp;syscount=15&amp;antrieb='.$user->settings['antrieb'].'">benachbarte Systeme anzeigen</a>
			<form action="#" name="strecken_flug" onsubmit="form_send(this, \'index.php?p=strecken&amp;sp=flug_entf&amp;simple&amp;ajax\', $(this).siblings(\'.ajax\'));return false">
				<input type="hidden" name="start" value="sys'.$_GET['id'].'" />
				<input type="hidden" name="antrieb" value="'.$user->settings['antrieb'].'" />
				Entfernung nach <input type="text" class="text center small2" style="width:70px" name="dest_entf" /> <a onclick="$(this.parentNode).trigger(\'onsubmit\')">berechnen</a>
			</form>
			<div class="ajax"></div>';
	}
	$tmpl->content .= '
	</td>
	</tr>
	</table>
	</div>
	</div>';
}

// System-Navigation
$nav = false;
	
if(isset($_GET['nav']) AND is_numeric($_GET['nav'])) {
	// bei nichtexistenten Planeten Wildcards  entfernen
	$_GET['id'] = str_replace('%', '', $_GET['id']);
	$t = time();
	
	// Navileiste erzeugen
	$nav = '
		<div class="fcbox center small2" id="sysnavbox'.$_GET['nav'].'-'.$_GET['id'].'-'.$t.'">System-Navigation wird geladen...</div>';
	// JavaScript starten
	$tmpl->script = 'systemnav('.$_GET['id'].', '.$_GET['nav'].', '.$t.');';
}

// Fehler in Content umwandeln und Navigation davor setzen
if($nav AND $tmpl->error) {
	$tmpl->content = $nav.'<div class="icontent" style="text-align:center;margin:20px;font-size:16px;font-weight:bold"><img src="img/layout/error.png" width="150" height="137" alt="Fehler" /><br /><br />'.$tmpl->error.'</div>';
	// ursprünglichen Fehler entfernen
	$tmpl->error = '';
	$tmpl->name = 'Fehler!';
}
// Navigation vor den Content setzen
else if($nav) {
	$tmpl->content = $nav.$tmpl->content;
}

// Ausgabe
$tmpl->output();

?>
