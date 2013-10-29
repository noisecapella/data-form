<?php

require_once "../../../../../lib/main_lib.php";

require_once FILE_BASE_PATH . "/www/browser/lib/data_table/data_form.php";
require_once "data.php";

/**
 * @param DataFormState $state
 * @return DataForm
 */
function make_form($state) {
	$this_url = HTTP_BASE_PATH . "/browser/lib/data_table/examples/multi_step_selection.php";
	$next_url = HTTP_BASE_PATH . "/browser/lib/data_table/examples/multi_step_selection_2.php";

	$columns = array();
	$columns[] = new DataTableColumn("Select", "city", new DataTableCheckboxCellFormatter());
	$columns[] = new DataTableColumn("City", "city");

	$buttons = array();
	$buttons[] = new DataTableButton("Continue >>", "submit", $next_url,
		new DataTableBehaviorSubmit());

	$rows = array();
	foreach (get_data() as $obj) {
		$rows[$obj["city"]] = array("city" => $obj["city"]);
	}

	$table = DataTableBuilder::create()->columns($columns)->rows($rows)->buttons($buttons)->remote($this_url)->build();
	$form = DataFormBuilder::create("select_cities")->tables(array($table))->build();
	return $form;
}

try {
	$state = new DataFormState("select_cities", $_POST);
	$form = make_form($state);
	if ($state->only_display_form()) {
		echo $form->display_form($state);
	}
	else
	{
		gfy_header("Select cities", "");
		echo $form->display($state);
	}
}
catch (Exception $e) {
	echo "<pre>" . $e . "</pre>";
}