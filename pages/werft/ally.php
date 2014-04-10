<?php
/**
 * pages/werft/ally.php
 * verbündete Werften
 */

// Sicherheitsabfrage
if(!defined('ODDB')) die('unerlaubter Zugriff!');


// Tabellenklasse laden
if(!class_exists('datatable')) {
	include './common/datatable.php';
}

// Berechtigung, den Bedarf zu bearbeiten
$rechte_bedarf = ($user->rechte['flags_edit_ally'] OR $user->rechte['flags_edit_meta']);



$content =& $csw->data['ally']['content'];

$content = '
<div class="hl2 werft_allyhead">Verb&uuml;ndete Werften</div>
<div class="icontent">
<form name="werft_ally" action="#" onsubmit="return form_sendget(this, \'index.php?p=werft&amp;sp=ally&amp;s=1\')">
<div class="fcbox center formbox">
beschr&auml;nken auf eingetragene Werften deiner 
&nbsp;<select name="meta">
'.($user->rechte['werft_ally'] ? '<option value="">Allianz</option>' : '').'
'.($user->rechte['werft_meta'] ? '<option value="1"'.(isset($_GET['meta']) ? ' selected="selected"' : '').'>Meta</option>' : '').'
</select>&nbsp; 
in Galaxie 
&nbsp;<input type="text" class="smalltext" name="g" value="'.(isset($_GET['g']) ? htmlspecialchars($_GET['g'], ENT_COMPAT, 'UTF-8') : '').'" />
<br />
Inhaber <input type="text" class="text" name="player" value="'.(isset($_GET['player']) ? htmlspecialchars($_GET['player'], ENT_COMPAT, 'UTF-8') : '').'" />
&nbsp; &nbsp;
Rasse 
<select name="ra" size="1">
	<option value="">egal</option>';
foreach($rassen as $key=>$val) {
	$content .= '
	<option value="'.$key.'"'.((isset($_GET['ra']) AND $_GET['ra'] == $key) ? ' selected="selected"' : '').'>'.$val.'</option>';
}
$content .= '
</select>
<br />
<input type="checkbox" name="leer" value="1"'.(isset($_GET['leer']) ? ' checked="checked"': '').' /> <span class="togglecheckbox" data-name="leer">nur leerstehende Werften</span> &nbsp; &nbsp; &nbsp;
<input type="checkbox" name="bed" value="1"'.(isset($_GET['bed']) ? ' checked="checked"': '').' /> <span class="togglecheckbox" data-name="bed">nur Werften mit Ressbedarf</span>
<br />
<input type="submit" class="button" style="width:120px" value="Werften filtern" /> 
<input type="button" class="button link" style="width:120px" value="Filter aufheben" data-link="index.php?p=werft&amp;sp=ally" />
</div>
</form>

<br /><br />';

// 2 Tage in der Vergangenheit
$t_scan = time()-172800;

// Bedingungen
$conds = array(
	"planetenWerft = 1"
);

// eingeschränkte Berechtigungen
if(!$user->rechte['werft_ally'] AND $user->allianz) {
	$conds[] = "player_allianzenID != ".$user->allianz;
}
if(!$user->rechte['werft_meta'] AND $user->allianz) {
	$conds[] = "(player_allianzenID = ".$user->allianz." OR statusStatus IS NULL OR statusStatus != ".$status_meta.")";
}
if($user->protectedAllies) {
	$conds[] = "player_allianzenID NOT IN(".implode(", ", $user->protectedAllies).")";
}
if($user->protectedGalas) {
	$conds[] = "systeme_galaxienID NOT IN(".implode(", ", $user->protectedGalas).")";
}

// Suchfilter

// Meta
if(isset($_GET['meta'])) {
	$conds[] = "statusStatus = ".$status_meta;
}
// Ally
else {
	$conds[] = "player_allianzenID = ".$user->allianz;
}

// Gala
if(isset($_GET['g']) AND (int)$_GET['g']) {
	$conds[] = "systeme_galaxienID = ".(int)$_GET['g'];
}

// Rasse
if(isset($_GET['ra'])) {
	$_GET['ra'] = (int)$_GET['ra'];
	// Lux
	if($_GET['ra'] == 10) $conds[] = '(playerRasse = '.$_GET['ra'].' OR planeten_playerID = -2)';
	// bestimmte Altrasse
	else $conds[] = 'playerRasse = '.$_GET['ra'];
}

// Inhaber
if(isset($_GET['player']) AND $_GET['player'] != '') {
	// Name eingegeben
	if(preg_replace('/[\d, ]/', '', $_GET['player']) != '') {
		$query = query("
			SELECT
				playerID,
				player_allianzenID
			FROM
				".GLOBPREFIX."player
			WHERE
				playerName = '".escape($_GET['player'])."'
		") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());
		
		// nicht gefunden
		if(!mysql_num_rows($query)) {
			$conds[] = "1 = 0";
		}
		else {
			$data = mysql_fetch_assoc($query);
			// Allianz gesperrt
			if($user->protectedAllies AND in_array($data['player_allianzenID'], $user->protectedAllies)) {
				$tmpl->error = 'Du hast keinen Zugriff auf die Allianz dieses Spielers!';
			}
			else {
				$conds[] = "planeten_playerID = ".$data['playerID'];
			}
		}
	}
	// eine ID eingegeben
	else if(strpos($_GET['player'], ',') === false) {
		$_GET['player'] = (int)$_GET['player'];
		if($_GET['player'] > 0) {
			$conds[] = "(planeten_playerID = ".$_GET['player']." OR playerName = '".$_GET['player']."')";
		}
	}
	// mehrere IDs eingegeben
	else {
		$_GET['player'] = explode(',', $_GET['player']);
		foreach($_GET['player'] as $key=>$val) {
			$val = (int)$val;
			if($val > 0) $_GET['player'][$key] = $val;
			else unset($_GET['player'][$key]);
		}
		if(count($_GET['player'])) {
			$conds[] = "planeten_playerID IN(".implode(",", $_GET['player']).")";
		}
	}
}

// nur leerstehende Werften
if(isset($_GET['leer'])) {
	$conds[] = "planetenWerftFinish < ".time();
	// muss innerhalb der letzten 2 Tage eingescannt worden sein
	$conds[] = "planetenUpdateOverview > ".$t_scan;
}

// Sortierung
$sort = array(
	'standard'=>'(planetenUpdateOverview > '.$t_scan.') DESC, planetenWerftFinish ASC',
	'id'=>'planetenID ASC',
	'name'=>'planetenName ASC',
	'player'=>'playerName ASC',
	'ally'=>'player_allianzenID ASC',
	'groesse'=>'planetenGroesse ASC',
	'scan'=>'planetenUpdateOverview ASC'
);

if(!isset($_GET['sort']) OR !isset($sort[$_GET['sort']])) {
	$sort = $sort['standard'];
}
else {
	$sort = $sort[$_GET['sort']];
}

// Sortierung nach Entfernung
$esort = false;
if(isset($_GET['sort']) AND $_GET['sort'] == 'entf' AND isset($_GET['entf']) AND $_GET['entf'] != '') {
	$esort = flug_point($_GET['entf']);
	
	// Fehler
	if(!is_array($esort)) {
		$esort = false;
	}
	else {
		$conds[] = 'systeme_galaxienID = '.$esort[0];
		$sort = 'planetenEntfernung ASC';
	}
}

$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace('/&sort=([a-z]+)/', '', $querystring);
$querystring = preg_replace('/&entf=.*(&|$)/', '', $querystring);
$querystring = str_replace('&switch', '', $querystring);
$querystring = htmlspecialchars($querystring, ENT_COMPAT, 'UTF-8');

$t = time();
$ids = array();
$sids = array();


// Werften abfragen
$query = query("
	SELECT
		planetenID,
		planeten_playerID,
		planeten_systemeID,
		planetenName,
		planetenGroesse,
		planetenTyp,
		planetenKategorie,
		planetenRMErz,
		planetenRMMetall,
		planetenRMWolfram,
		planetenRMKristall,
		planetenRMFluor,
		planetenUpdateOverview,
		planetenUpdate,
		planetenGebPlanet,
		planetenGebOrbit,
		planetenGebSpezial,
		planetenKommentar,
		planetenWerftFinish,
		planetenWerftBedarf,
		planetenBelieferer,
		planetenBeliefererTime,
		
		systemeX,
		systemeZ,
		systeme_galaxienID,
		
		".($esort ? entf_mysql("systemeX", "systemeY", "systemeZ", "1", $esort[1], $esort[2], $esort[3], $esort[4])." AS planetenEntfernung," : "")."
		
		playerName,
		player_allianzenID,
		playerRasse,
		playerUmod,
		
		allianzenTag,
		
		user_playerName,
		
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
		LEFT JOIN ".PREFIX."user
			ON user_playerID = planetenBelieferer
	WHERE
		".implode(" AND ", $conds)."
	ORDER BY
		".$sort."
") OR die("Fehler in ".__FILE__." Zeile ".__LINE__.": ".mysql_error());


// nach Entfernung sortieren
$content .= '
	<div class="center">
		<form action="#" name="sort_inva" onsubmit="return form_sendget(this, \'index.php?'.$querystring.'&amp;sort=entf\')">
			<a class="link" style="display:none" data-link=""></a>
			Werften nach Entfernung zu 
			&nbsp;<input type="text" class="smalltext tooltip" style="width:80px" name="entf" data-tooltip="bei System-IDs &lt;b&gt;sys&lt;/b&gt; davorsetzen (z.B. sys1234)"'.(isset($_GET['entf']) ? ' value="'.htmlspecialchars($_GET['entf'], ENT_COMPAT, 'UTF-8').'"' : '').' />
			&nbsp;<input type="submit" class="button" value="sortieren" />
			&nbsp;<span class="small hint">(Planet, System oder Koordinaten)</span>
		</form>
		'.((isset($_GET['entf']) AND $_GET['entf'] != '' AND !$esort) ? '<span class="error">Ausgangspunkt ung&uuml;ltig</span>' : '').'
	</div>
	<br /><br />';

// Planeten eingetragen
if(mysql_num_rows($query)) {
	
	if($user->rechte['routen'] OR $rechte_bedarf) {
		$content .= '
	<form name="werft_allyroutenform" onsubmit="return false">';
	}
	$content .= '
	<table class="data searchtbl thighlight" style="width:100%;margin:auto">
	<tr>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=id">G</a></th>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=id">System</a></th>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=id">ID</a></th>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=name">Name</a></th>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=player">Inhaber</a></th>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=ally">Allianz</a></th>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=groesse">Gr&ouml;&szlig;e</a></th>
	<th>&nbsp;</th>
	'.($esort ? '<th>Entf <span class="small">(A'.$user->settings['antrieb'].')</span></th>' : '').'
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=scan">Scan</a></th>
	<th><a class="link" data-link="index.php?'.$querystring.'&amp;sort=standard">fertig</a></th>
	<th>Bedarf</th>
	<th>&nbsp;</th>
	<th>&nbsp;</th>
	<th>&nbsp;</th>
	<th>&nbsp;</th>';
	if($user->rechte['routen'] OR $rechte_bedarf) {
		$content .= '
	<th>&nbsp;</th>';
	}
	$content .= '
	</tr>';
	
	$bedarf_list = array();
	
	$heute = strtotime('today');
	$t_belieferer = time()-43200; // 12 Stunden
	
	while($row = mysql_fetch_assoc($query)) {
		// Bedarf ausrechnen
		$bedarf = false;
		$bedarfUnknown = false;
		$bedarfNotNull = false;
		
		if($row['planetenWerftBedarf'] != '') {
			$b = json_decode($row['planetenWerftBedarf'], true);
			if($row['planetenRMErz'] < $b[0] OR $row['planetenRMMetall'] < $b[1] OR $row['planetenRMWolfram'] < $b[2] OR $row['planetenRMKristall'] < $b[3] OR $row['planetenRMFluor'] < $b[4]) {
				$bedarf = true;
			}
			
			// zur Schnellauswahl hinzufügen
			$b2 = $b;
			foreach($b2 as $key=>$val) {
				$b2[$key] = ressmenge2($val);
				
				if($val > 0) {
					$bedarfNotNull = true;
				}
			}
			
			$bedarf_list[implode('-', $b)] = implode(' - ', $b2);
		}
		else {
			$bedarfNotNull = true;
			$bedarfUnknown = true;
		}
		
		// Suchfilter Ressbedarf
		if(isset($_GET['bed']) AND !$bedarf) {
			continue;
		}
		
		$content .= '
	<tr>
	<td>'.datatable::galaxie($row['systeme_galaxienID'], $row['systemeX'], $row['systemeZ']).'</td>
	<td>'.datatable::system($row['planeten_systemeID'], $t).'</td>
	<td>'.datatable::planet($row['planetenID'], false, $t).'</a></td>
	<td>'.datatable::planet($row['planetenID'], $row['planetenName'], $t).'</td>
	<td>'.datatable::inhaber($row['planeten_playerID'], $row['playerName'], $row['playerUmod'], $row['playerRasse']).'</td>
	<td>'.datatable::allianz($row['player_allianzenID'], $row['allianzenTag']).'</td>
	<td>'.$row['planetenGroesse'].'</td>
	<td>'.datatable::typ($row['planetenTyp']).'</td>';
		
		// Entfernung
		if(isset($row['planetenEntfernung'])) {
			$content .= '
			<td>'.flugdauer($row['planetenEntfernung'], $user->settings['antrieb']).'</td>';
		}
		
		$content .= '
	<td>'.datatable::scan($row['planetenUpdateOverview'], $config['scan_veraltet']).'</td>
	<td>';
		// fertig
		// unbekannt
		if($row['planetenUpdateOverview'] < $t_scan) {
			$content .= '<span class="yellow" style="font-style:italic">unbekannt</span>';
		}
		// leerstehend
		else if($row['planetenWerftFinish'] < time()) {
			$content .= '<span class="red" style="font-weight:bold">leerstehend</span>';
		}
		// Schiff im Bau
		else if($row['planetenWerftFinish'] < time()+7200) {
			$content .= '<b>'.datum($row['planetenWerftFinish']).'</b>';
		}
		else {
			$content .= datum($row['planetenWerftFinish']);
		}
		$content .= '</td>
	<td>';
		// Bedarf
		// unbekannt
		if($row['planetenWerftBedarf'] == '' OR $row['planetenUpdateOverview'] == 0) {
			$content .= '<span class="yellow" style="font-style:italic">unbekannt</span>';
			$bedarfUnknown = true;
		}
		else {
			// Bedarf ausrechnen
			$m = array(
				$row['planetenRMErz'],
				$row['planetenRMMetall'],
				$row['planetenRMWolfram'],
				$row['planetenRMKristall'],
				$row['planetenRMFluor']
			);
			
			$bm = array();
			foreach($m as $key=>$val) {
				$w = $b[$key]-$val;
				if($w > 0) {
					$bm[$key] = $w;
				}
			}
			
			// Tooltip erzeugen
			$tt = '<b>Vorrat:</b><br />';
			foreach($m as $key=>$val) {
				$tt .= $ress[$key].ressmenge2($val).' ';
			}
			$tt .= '<br /><br /><b>Bedarf:</b><br />';
			foreach($b as $key=>$val) {
				$tt .= $ress[$key].ressmenge2($val, true).' ';
			}
			if($bedarf) {
				$tt .= '<br /><br /><b>&raquo; noch zu liefern:</b><br />';
				foreach($bm as $key=>$val) {
					$tt .= $ress[$key].ressmenge2($val, true).' ';
				}
			}
			
			// Label erzeugen
			if(!$bedarfNotNull) {
				$color = 'green';
				$label = 'nein';
			}
			else if($row['planetenUpdateOverview'] < $t_scan) {
				$color = 'yellow italic';
				$label = 'Scan veraltet';
			}
			else if($bedarf) {
				$color = 'red bold';
				$label = 'ja';
			}
			else {
				$color = 'green';
				$label = 'nein';
			}
			
			$content .= '<span class="'.$color.' tooltip" data-tooltip="'.htmlspecialchars($tt, ENT_COMPAT, 'UTF-8').'">'.$label.'</span>';
		}
		
		$content .= '</td>
	<td>'.datatable::screenshot($row, $config['scan_veraltet']).'</td>
	<td>'.datatable::kategorie($row['planetenKategorie'], $row['planetenUpdateOverview'], $row).'</td>
	<td>'.datatable::kommentar($row['planetenKommentar'], $row['planetenID']).'</td>
	<td>';
		
		// Belieferer eingetragen
		$showBelieferer = $bedarfNotNull AND ($bedarf OR $bedarfUnknown);
		
		if($showBelieferer AND $row['planetenBelieferer'] AND $row['planetenBeliefererTime'] > $t_belieferer) {
			if($row['planetenBelieferer'] == $user->id) {
				$content .= '<b>';
			}
			
			$content .= datatable::inhaber($row['planetenBelieferer'], $row['user_playerName']);
			
			if($row['planetenBelieferer'] == $user->id) {
				$content .= '</b>
					&nbsp; <a onclick="ajaxcall(\'index.php?p=werft&amp;sp=beliefern_del&amp;id='.$row['planetenID'].'\', this.parentNode)" class="red bold" title="als Belieferer austragen">X</a>';
			}
		}
		// kein Belieferer
		else if($showBelieferer) {
			$content .= '<a onclick="ajaxcall(\'index.php?p=werft&amp;sp=beliefern&amp;id='.$row['planetenID'].'\', this.parentNode)"><i>beliefern</i></a>';
		}
		else {
			$content .= '&nbsp;';
		}
		
		$content .= '</td>';
		
		if($user->rechte['routen'] OR $rechte_bedarf) {
			$content .= '<td><input type="checkbox" name="'.$row['planetenID'].'" /></td>';
		}
		$content .= '
	</tr>';
		
		$ids[] = $row['planetenID'];
		
		if(!in_array($row['planeten_systemeID'], $sids)) {
			$sids[] = $row['planeten_systemeID'];
		}
	}
	
	$content .= '
	</table>';
	
	// hidden-Feld für die Suchnavigation
	$content .= '
		<input type="hidden" id="snav'.$t.'" value="'.implode('-', $ids).'" />
		<input type="hidden" id="sysnav'.$t.'" value="'.implode('-', $sids).'" />';
	
	if($user->rechte['routen'] OR $rechte_bedarf) {
		$content .= '
	<div style="text-align:right;margin-top:4px">
	markieren: 
	<a onclick="$(this).parents(\'form\').find(\'input\').prop(\'checked\', true);" style="font-style:italic">alle</a> /
	<a onclick="$(this).parents(\'form\').find(\'input\').prop(\'checked\', false);" style="font-style:italic">keine</a> 
	</div>';
		if($user->rechte['routen']) {
			$content .= '
	<br />
	<div class="small2" style="text-align:right"><a onclick="ajaxcall(\'index.php?p=ajax_general&amp;sp=route_addmarked&amp;ajax\', this.parentNode, false, true)">markierte Planeten zu einer Route / Liste hinzuf&uuml;gen</a></div>';
		}
		$content .= '
	</form>';
	}
	
	// Formular zum Ändern des Bedarfs anzeigen
	if($rechte_bedarf) {
		
		$content .= '<br /><br />
	<div class="hl2">Bedarf aller markierten Werften &auml;ndern</div>
	';
		
		// Hinweise bei fehlenden Berechtigungen
		if(!$user->rechte['flags_edit_ally']) {
			$content .= '
			<span class="small hint">Werften deiner Allianz bleiben unver&auml;ndert, da du hierf&uuml;r keine Berechtigung hast!</span><br />';
		}
		if(!$user->rechte['flags_edit_meta']) {
			$content .= '
			<span class="small hint">Werften deiner Meta (au&szlig;erhalb deiner Allianz) bleiben unver&auml;ndert, da du hierf&uuml;r keine Berechtigung hast!</span><br />';
		}
		
		$content .= '
	<br />
	<form name="werft_editall" action="#" onsubmit="$(this).find(\'[name=ids]\').val($(this).siblings(\'[name=werft_allyroutenform]\').serialize());return form_send(this, \'index.php?p=werft&amp;sp=edit_all_ally\', $(this).siblings(\'.ajax\'))">
	<input type="hidden" name="ids" value="" />
	<br />
	<div class="center">
		<img src="img/layout/leer.gif" class="ress ress_form erz" /> 
		<input type="text" class="smalltext" name="erz" /> 
		<img src="img/layout/leer.gif" class="ress ress_form metall" /> 
		<input type="text" class="smalltext" name="metall" /> 
		<img src="img/layout/leer.gif" class="ress ress_form wolfram" /> 
		<input type="text" class="smalltext" name="wolfram" /> 
		<img src="img/layout/leer.gif" class="ress ress_form kristall" /> 
		<input type="text" class="smalltext" name="kristall" /> 
		<img src="img/layout/leer.gif" class="ress ress_form fluor" /> 
		<input type="text" class="smalltext" name="fluor" />
		&nbsp;
		<input type="submit" class="button" style="width:100px" value="speichern" />
		<br /><br />
	</div>
	</form>
	<div class="ajax center"></div>

	<br />
	Schnellauswahl: <select size="1" onchange="werftPage.setBedarf(this)">
		<option></option>';
		
		foreach($bedarf_list as $value=>$label) {
			$content .= '<option value="'.$value.'">'.$label.'</option>';
		}
		
		$content .= '
	</select>
	';
		
	}
}
// keine Planeten eingetragen
else {
	$content .= '
	<br />
	<div class="center" style="font-weight:bold">Keine Werften gefunden, die den Kriterien entsprechen!</div>
	<br />';
}

$content .= '
</div>';

// scrollen
if(isset($_GET['s']) OR isset($_GET['sort'])) {
	$tmpl->script .= 'if($(\'#contentc .content:visible .werft_allyhead\').length){$(\'html,body\').scrollTop($(\'#contentc .content:visible .werft_allyhead\').offset().top-10);}';
}

// Logfile-Eintrag
if($config['logging'] >= 3) {
	insertlog(5, 'zeigt die Liste der verbündeten Werften an');
}



?>