<?php
if(!defined("_INDEX_")){ exit; }

$_tpl->set("module_tpl","modules/ini/ini.tpl");

$reini = false;
$ini = false;

if($_user['groupe'] == GRP_VISITEUR)
	$_tpl->set("need_to_be_loged", true);
else {
	$vld = Vld::get($_user['mid']);

	if($_user['etat'] == MBR_ETAT_INI)
		$ini = true; // initialisation
	else if($vld){
		// rechercher une validation d'initialisation ou réinitialisation
		foreach($vld as $value)
			if($value['vld_act'] == 'new') {
				$vld = $value;
				$ini = true;
				break;
			}else if($value['vld_act'] == 'res') {
				$vld = $value;
				$reini = true;
				break;
			}

	}
}

if($reini || $ini) { /* N'importe qui ne peut pas venir ici */
	if($reini) {
		$array = Mbr::getFull($_user['mid']);
		$_ses->set('map_region', $array[0]['map_region']);
		$pseudo = request("pseudo", "string", "post", $_user['pseudo']);
		$vlg = request("vlg", "string", "post", $_user['vlg']);
		$race = request("race", "uint", "post", $_user['race']);
		$region = request("region", "uint", "post", $_user['map_region']);
		$sexe = request("sexe", "uint", "post", $array[0]['mbr_sexe']);
	} else {
		$_ses->set('map_region', 0);
		$pseudo = request("pseudo", "string", "post");
		$vlg = request("vlg", "string", "post");
		$race = request("race", "uint", "post");
		$region = request("region", "uint", "post");
		$sexe = request("sexe", "uint", "post", 1);
	}
	$pseudo = trim($pseudo);

	if($_user['etat'] == MBR_ETAT_INI)
		$key = true;
	else
		$key = request("key", "string", "post", request("key", "string", "get"));

	/* vérifier la race et si on y a droit */
	if(!isset($_races[$race])) // n'existe pas encore
		$race = 0;
	else if(!$_ses->canDo(DROIT_ANTI_FLOOD) and !$_races[$race]) // accès limité
		$race = 0;

	$infos_races = Mbr::countRaces();
	$regions_infos = Map::getRegions($_regions);

	/* virer les races inutiles = inaccessible au joueur lambda
	 DROIT_ANTI_FLOOD à partir de sage */
	if(!$_ses->canDo(DROIT_ANTI_FLOOD))
		foreach($_races as $cle => $value)
			if(!$value)
				unset($_races[$cle]);

	$_tpl->set("mbr_reini", $reini);
	$_tpl->set("mbr_pseudo", $pseudo);
	$_tpl->set("mbr_vlg", $vlg);
	$_tpl->set("mbr_race",$race);
	$_tpl->set("mbr_region",$region);
	$_tpl->set("mbr_key",$key);
	$_tpl->set("mbr_sexe",$sexe);
	$_tpl->set("infos_races",$infos_races);
	$_tpl->set("regions_infos", $regions_infos);
	$_tpl->set("_regions", $_regions);
	$_tpl->set("mbr_error", "");

	if($key || $pseudo || $vlg || $race || $region) { /* Y'a des trucs postés */
		if($key && $pseudo && $vlg && $race && $region && !empty($_POST)) { /* Tous */
			/* On verifie la clef */
			if($_user['etat'] == MBR_ETAT_INI)
				$vld['vld_rand'] = true;
			if($vld['vld_rand'] != $key) {
				$_tpl->set("mbr_error", "bad_key");
			} else {
				if($reini)
					$oldcid = $_user['mapcid'];

				/* Calcul de x et y en fonction de la région */
				if($region == $_user['map_region'] && $reini)
					$cid = $_user['mapcid'];
				else
					$cid = Map::getRand($region); 

				// vérifier l'unicité du pseudo
				if ($ini)
					$have_pseudo = Mbr::get(array('count'=>true, 'pseudo' => $pseudo));
				else // reini: on peut conserver le même pseudo
					$have_pseudo = Mbr::get(array('count'=>true, 'pseudo' => $pseudo, 'mid_excl'=>$_user['mid'], 'op'=>'AND'));
				$have_pseudo = ($have_pseudo[0]['mbr_nb']>0 ? true: false);

				if($region < 10 && !$regions_infos['libre'][$region][$race]) {
					$_tpl->set("mbr_error", "reg_full");
				} else if(!$cid) {
					$_tpl->set("mbr_error", "no_cid");
				} else if(!strverif($pseudo)) {
					$_tpl->set("mbr_error", "bad_pseudo");
				} else if(!strverif($vlg)) {
					$_tpl->set("mbr_error", "bad_vlg");
				} else if($have_pseudo) {
					$_tpl->set("mbr_error", "pseudo_unavailable");
				} else if($sexe != 1 and $sexe != 2) {
					$_tpl->set("mbr_error", "sexe_undefined");
				} else {

					$_ses->set("race", $race); /* Il faut absolument changer la race ! */
					$_ses->set("mapcid", $cid);

					if($reini){
						Mbr::reinit($_user['mid'], $pseudo, $vlg , $race, $cid, $oldcid, $_user['groupe'], $sexe);
					} else {
						Mbr::init($_user['mid'], $pseudo, $vlg, $race, $cid, GRP_JOUEUR, $sexe);
						/* envoyer le message de bienvenue */
						$msg = nl2br($_tpl->get("modules/inscr/msg/welcome.txt.tpl",1));
						$titre = $_tpl->get("modules/inscr/msg/welcome.obj.tpl",1);
						MsgRec::add(MBR_WELC, $_user['mid'] ,$titre, $msg,true);
					}
					/* On vire la clef */
					if($_user['etat'] != MBR_ETAT_INI)
						Vld::del($_user['mid']);

					$_tpl->set("mbr_ini_ok", true);
				}
			}
		} else /* Il en manque */
			$_tpl->set("not_all_post", true);
	}
} else if($_user['etat']==MBR_ETAT_INI)
	$_tpl->set("mbr_not_ini",true);
else
	$_tpl->set("mbr_ini",true);

?>
