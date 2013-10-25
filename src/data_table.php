<?php
/**
 * Created by JetBrains PhpStorm.
 * User: george
 * Date: 10/7/13
 * Time: 11:37 AM
 * To change this template use File | Settings | File Templates.
 */

require_once "data_table_behavior.php";
require_once "data_table_builder.php";
require_once "data_table_button.php";
require_once "data_table_cell_formatter.php";
require_once "data_table_checkbox.php";
require_once "data_table_column.php";
require_once "data_table_header_formatter.php";
require_once "data_table_link.php";
require_once "data_table_options.php";
require_once "data_table_radio.php";
require_once "data_form_state.php";
require_once "data_table_widget.php";

/**
 * This displays a table of data which is also a form.
 * Rows can be selectable and results are submitted to $form_action
 *
 * Table columns (which must be DataTableColumn) are displayed in order of $columns
 * and are matched with data if the key in $columns matches a key
 * from $sql_field_names or a row in $rows.
 *
 * A DataTableColumn is in charge of how to display the column, which may be string data or have a checkbox
 * or select widget or many other things. The user may use callbacks to customize the DataTableColumn
 * See the PHPDoc for DataTableColumn for more information
 *
 */
class DataTable
{
	/** @var \DataTableButton[]  */
	private $buttons;
	/** @var \DataTableColumn[]  */
	private $columns;
	/** @var \string[]  */
	private $sql_field_names;
	/** @var array  */
	private $rows;

	/** @var string|bool Either false or a URL to send pagination, sorting, or searching requests to */
	private $remote;

	/**
	 * @var string[] Mapping of row id to CSS classes. Can be null
	 */
	private $row_classes;

	/**
	 * @param DataTableBuilder $builder
	 * @throws Exception
	 */
	public function __construct($builder) {
		$this->buttons = $builder->get_buttons();
		$this->columns = $builder->get_columns();
		$this->sql_field_names = $builder->get_sql_field_names();
		$this->rows = $builder->get_rows();
		$this->remote = $builder->get_remote();
		$this->row_classes = $builder->get_row_classes();
	}

	/**
	 * Returns HTML for table. This is useful if sending it via ajax to populate a div
	 *
	 * @param string $form_name
	 * @param DataFormState $state
	 * @throws Exception
	 * @return string HTML
	 */
	public function display_table($form_name, $state=null) {
		$ret = "";

		$indexes = array();
		$count = 0;
		foreach ($this->sql_field_names as $field_name) {
			$indexes[$field_name] = $count;
			$count++;
		}

		foreach ($this->buttons as $button) {
			if ($button->get_placement() == DataTableButton::placement_top) {
				$ret .= $button->display($form_name, $state);
			}
		}

		if ($this->is_sortable()) {
			$ret .= "<table class='table-autosort table-stripeclass:shadedbg table-altstripeclass:shadedbg'>";
		}
		else
		{
			$ret .= "<table class='table-stripeclass:shadedbg table-altstripeclass:shadedbg'>";
		}
		$ret .= "<thead>";
		$ret .= "<tr class='standard-table-header'>";
		foreach ($this->columns as $column) {
			$column_key = $column->get_column_key();
			if ($column->get_sortable()) {
				if ($this->remote) {
					$ret .= "<th class='column_" . $column_key . "'>";
					if ($state) {
						$old_sorting_state = $state->get_sorting_state($column_key);
						$ret .= "<input type='hidden' name='" . $form_name . "[" . DataFormState::sorting_state_key . "][" . $column_key . "]' value='" . $old_sorting_state . "' />";
					}
					else
					{
						$old_sorting_state = null;
					}

					if ($old_sorting_state == DataFormState::sorting_state_asc) {
						$ret .= "&uarr; ";
					}
					elseif ($old_sorting_state == DataFormState::sorting_state_desc) {
						$ret .= "&darr; ";
					}
				}
				else
				{
					$ret .= "<th class='column_" . $column_key . " table-sortable:" . $column->get_sortable() . " table-sortable' title='Click to sort'>";
				}
			}
			else
			{
				$ret .= "<th class='column_" . $column_key . "'>";
			}

			/** @var DataTableColumn $column */
			if ($column->get_sortable() && $this->remote) {
				if ($state && $state->get_sorting_state($column_key)) {
					$old_sorting_state = $state->get_sorting_state($column_key);
				}
				else
				{
					// not really true but it provides a default
					$old_sorting_state = DataFormState::sorting_state_asc;
				}

				if ($old_sorting_state == DataFormState::sorting_state_asc) {
					$new_sorting_state = DataFormState::sorting_state_desc;
				}
				else
				{
					$new_sorting_state = DataFormState::sorting_state_asc;
				}
				$sort_string = "&" . $form_name . "[" . DataFormState::sorting_state_key . "][" . $column_key . "]=" . $new_sorting_state;

				$onclick_obj = new DataTableBehaviorRefresh($sort_string);
				$onclick = $onclick_obj->action($form_name, $this->remote);
				$ret .= "<a onclick='$onclick'>";
			}
			$ret .= $column->get_display_header($form_name, $column_key);
			if ($column->get_sortable() && $this->remote) {
				$ret .= "</a>";
			}
			$ret .= "</th>";
		}
		$ret .= "</tr>";

		if ($this->is_searchable()) {
			$ret .= "<tr class='standard-table-header'>";
			foreach ($this->columns as $column) {
				$column_key = $column->get_column_key();
				$ret .= "<th>";
				if ($column->get_searchable()) {
					if (!$this->remote) {
						$ret .= "<input size='8' onkeyup='Table.filter(this, this)' />";
					}
					else
					{
						if ($state && $state->get_searching_state($column_key)) {
							$old_searching_state = $state->get_searching_state($column_key);
						}
						else
						{
							$old_searching_state = "";
						}
						$name = $form_name . "[" . DataFormState::searching_state_key . "][" . $column_key . "]";
						$ret .= "<input size='8' name='" . $name . "' value='" . $old_searching_state . "' />";
					}
				}
				$ret .= "</th>";
			}
			$ret .= "</tr>";
		}
		$ret .= "</thead>";
		$ret .= "<tbody>";
		$row_count = 0;
		foreach ($this->rows as $row_id => $row) {
			if (!is_array($row)) {
				throw new Exception("Each row in rows expected to be an array");
			}
			$row_id = (string)$row_id;

			if ($this->row_classes && array_key_exists($row_id, $this->row_classes)) {
				$row_class = $this->row_classes[$row_id];
			}
			else {
				if ($row_count % 2 == 0) {
					$row_class = "standard_row_even";
				}
				else
				{
					$row_class = "standard_row_odd";
				}
			}

			$ret .= "<tr class='unshadedbg $row_class'>";


			foreach ($this->columns as $column) {
				$column_key = $column->get_column_key();
				$col_css = $column->get_css();
				$ret .= "<td class='column_$column_key $col_css'>";
				/** @var DataTableColumn $column */
				if (array_key_exists($column_key, $row)) {
					$cell = $row[$column_key];
				}
				elseif (array_key_exists($column_key, $indexes)) {
					$index = $indexes[$column_key];
					if ($index >= count($row)) {
						throw new Exception("Tried to get index $index of row with " . count($row) . " columns");
					}
					if (array_key_exists($index, $row)) {
						$cell = $row[$index];
					}
					else
					{
						$cell = null;
					}
				}
				else
				{
					// a column where there is no data is a common case, for instance
					// the row selection checkbox
					$cell = null;
				}
				$ret .= $column->get_display_data($form_name, $column_key, $cell, $row_id, $state);
				$ret .= "</td>";
			}

			$ret .= "</tr>";
			$row_count++;
		}
		$ret .= "</tbody>";
		$ret .= "</table>";
		foreach ($this->buttons as $button) {
			if ($button->get_placement() == DataTableButton::placement_bottom) {
				$ret .= $button->display($form_name, $state);
			}
		}

		return $ret;
	}

	public function is_sortable() {
		foreach ($this->columns as $column) {
			if ($column->get_sortable()) {
				return true;
			}
		}
		return false;
	}

	public function is_searchable() {
		foreach ($this->columns as $column) {
			if ($column->get_searchable()) {
				return true;
			}
		}
		return false;
	}
}
