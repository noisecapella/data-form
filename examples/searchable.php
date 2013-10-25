<?php

require_once "../../../../../lib/main_lib.php";

require_once FILE_BASE_PATH . "/www/browser/lib/data_table/data_form.php";

function compare_bird_column_asc($a, $b) {
	return $a["bird"] < $b["bird"];
}

function compare_bird_column_desc($a, $b) {
	return $a["bird"] > $b["bird"];
}

/**
 * @param DataFormState $state
 * @return DataForm
 */
function make_form($state) {
	$this_url = HTTP_BASE_PATH . "/browser/lib/data_table/examples/searchable.php";

	$columns = array();
	$columns[] = new DataTableColumn("Bird", "bird", null, null, true, true);

	$birds = array("1.1 Struthioniformes",
		"1.2 Anseriformes",
		"1.3 Galliformes",
		"1.4 Charadriiformes",
		"1.5 Gruiformes",
		"1.6 Podicipediformes",
		"1.7 Ciconiiformes",
		"1.8 Pelecaniformes",
		"1.9 Procellariiformes",
		"1.10 Sphenisciformes",
		"1.11 Columbiformes",
		"1.12 Psittaciformes",
		"1.13 Cuculiformes",
		"1.14 Falconiformes",
		"1.15 Strigiformes",
		"1.16 Caprimulgiformes",
		"1.17 Apodiformes",
		"1.18 Coraciiformes",
		"1.19 Piciformes",
		"1.20 Passeriformes");

	$rows = array();
	foreach ($birds as $bird) {
		// note: case sensitive search
		if (!$state->get_searching_state("bird") || strpos($bird, $state->get_searching_state("bird")) !== false) {
			$rows[] = array("bird" => $bird);
		}
	}

	if ($state->get_sorting_state("bird") == DataFormState::sorting_state_asc) {
		usort($rows, "compare_bird_column_asc");
	}
	elseif ($state->get_sorting_state("bird") == DataFormState::sorting_state_desc) {
		usort($rows, "compare_bird_column_desc");
	}

	$buttons = array();
	$buttons[] = new DataTableButton("Refresh", "refresh", $this_url, new DataTableBehaviorRefresh());

	$table = DataTableBuilder::create()->columns($columns)->rows($rows)->remote($this_url)->buttons($buttons)->build();
	$form = DataFormBuilder::create("searchable")->tables(array($table))->build();
	return $form;
}

try {
	$state = new DataFormState("searchable", $_POST);
	$form = make_form($state);
	if ($state->only_display_form()) {
		echo $form->display_form($state);
	}
	else
	{
		gfy_header("Simple table example", "");
		echo $form->display($state);
	}
}
catch (Exception $e) {
	echo "<pre>" . $e . "</pre>";
}