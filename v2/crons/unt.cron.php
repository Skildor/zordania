<?php

$dep_unt = array("btc", "res");
$log_unt = "Unités";

function mbr_unt(&$_user) {
	global $_histo, $hro_list;

	$mid = $_user['mbr_mid'];
	$race = $_user['mbr_race'];

	$unt_todo = UntTodo::get($mid);

	$have_btc = $_user["btc"]; // batiments construits
	$prod_btc = array(); // productions d'unités maxi par batiments
	foreach($have_btc as $btc_type => $btc_nb){
		$prod_unt = Config::get($race, 'btc', $btc_type, 'prod_unt');
		if(is_numeric($prod_unt))
			$prod_btc[$btc_type] = $btc_nb * $prod_unt;
		else
			$prod_btc[$btc_type] = $btc_nb; // prod de 1 par batiments
	}

	$update_unt_todo = $update_unt = array();
	$unt_nb = Config::get($race, "race_cfg", "unt_nb");

	for($i = 1; $i <= $unt_nb; ++$i)
		$update_unt_todo[$i] = $update_unt[$i] = 0;

	foreach($unt_todo as $value) {
		$unt_type = $value["utdo_type"];
		$unt_nb = $value["utdo_nb"];
		$unt_id = $value["utdo_id"];

		$need_btc = Config::get($race, "unt", $unt_type, "in_btc");
		$max = $unt_nb;
		$unt_prod = 0;

		foreach($need_btc as $btc_type) {// liste des batiments requis
			if(isset($prod_btc[$btc_type])){// batiments requis sont construits?
				// on augmente la production du nombre de batiments construits

				if($max > $prod_btc[$btc_type]){ /* Si y'a pas assez de bâtiments */
					$unt_prod += $prod_btc[$btc_type]; /* On en fait autant qu'on a de bâtiment */
					$prod_btc[$btc_type] = 0;// tous ces batiments ont été utilisés
				}else{
					$unt_prod += $max; /* Sinon, on n'utilise pas tout les bâtiments */
					$prod_btc[$btc_type] -= $max;// soustraire le reste à faire, il reste des bats à produire
				}
				$max -= $unt_prod; // si il reste des unités à produire?
				if(!$max)
					break;
			}
		}

		if($unt_prod) {
			$update_unt[$unt_type] += $unt_prod;
			if(isset($_user["unt"]))
				$_user["unt"][$unt_type] += $unt_prod;
			if(!isset($update_unt_todo[$unt_id]))
				$update_unt_todo[$unt_id] = 0;
			$update_unt_todo[$unt_id] -= $unt_prod;
		}
	}

	/* Nourriture */
	$bouf = $_user["res"][GAME_RES_BOUF];
        $nb = Unt::join('leg', 'unt_lid', 'leg_id')->where('leg_mid', $mid)
                ->whereIn('leg_etat', [LEG_ETAT_VLG, LEG_ETAT_BTC])->count();

	if($bouf < $nb) { /* On tue des gens */
		$sql = "SELECT unt_type, unt_nb, leg_name FROM ".DB::getTablePrefix()."unt ";
		$sql.= "JOIN ".DB::getTablePrefix()."leg ON leg_id = unt_lid ";
		$sql.= "WHERE leg_mid = $mid AND leg_etat = ".LEG_ETAT_VLG." AND unt_nb > 0 ";
		$sql .= ' AND unt_type NOT IN ('. implode(',', $hro_list[$race]). ') ';
		$sql.= "ORDER BY RAND() LIMIT 1";
		$unt_array = DB::sel($sql);

		if($unt_array) {
			$unt_array = $unt_array[0];
			$type = $unt_array['unt_type'];
			$name = $unt_array['leg_name'];

			if(($nb - $bouf) > $unt_array['unt_nb'])
				$killed = rand(1,$unt_array['unt_nb']);
			else
				$killed = rand(1, $nb - $bouf);

			$update_unt[$type] -= $killed;
			$_histo->add($mid, $mid,HISTO_UNT_BOUFF ,array("unt_type" => $type, "unt_nb" => $killed, "leg_name" => $name));
		}

		$nb = $bouf;
	}

	Res::mod($mid, array(GAME_RES_BOUF => -$nb));// nourriture consommée

	Unt::editVlg($mid, $update_unt);// MAJ unités formées ou mort de faim

	$sql = "";
	foreach($update_unt_todo as $id => $nb) {
		if($nb)
			$sql.= "WHEN utdo_id = $id THEN utdo_nb + $nb ";
	}

	if($sql) {
		$sql = "UPDATE ".DB::getTablePrefix()."unt_todo SET utdo_nb = CASE ". $sql;
		$sql.= " ELSE utdo_nb END WHERE utdo_mid = $mid ";
		DB::update($sql);
	}
}

function glob_unt() {
    UntTodo::where('utdo_nb', '<=', 0)->delete();

    Unt::where('unt_nb', '<=', 0)->delete();

	$sql="UPDATE ".DB::getTablePrefix()."mbr SET mbr_population = IFNULL((SELECT SUM(unt_nb) FROM "
                .DB::getTablePrefix()."leg JOIN "
                .DB::getTablePrefix()."unt ON leg_id = unt_lid WHERE leg_mid = mbr_mid),0)";
	DB::update($sql);

        Hro::where('hro_bonus_to', '<', DB::raw('NOW()'))->update('hro_bonus', 0);
}
