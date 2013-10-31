<?php
class DataTableTextbox implements IDataTableWidget {
	/** @var  string */
	protected $text;
	/** @var  string */
	protected $name;
	/** @var  string URL to submit to */
	protected $action;
	/** @var  IDataTableBehavior */
	protected $submit_behavior;
	/** @var string either 'top' or 'bottom' */
	protected $placement;

	public function __construct($text, $name, $form_action, $submit_behavior = null, $placement = self::placement_top) {
		$this->text = $text;
		$this->name = $name;
		$this->action = $form_action;
		$this->submit_behavior = $submit_behavior;
		$this->placement = $placement;

	}

	public function display($form_name, $form_method, $state)
	{
		return self::display_textbox($form_name, array($this->name), $this->action, $form_method, $this->submit_behavior, $this->text, $state);
	}

	public function get_placement()
	{
		return $this->placement;
	}

	/**
	 * @param $form_name string
	 * @param $name_array string[] Name for select. Each item will be surrounded by square brackets and concatenated
	 * @param $action string
	 * @param $form_method string GET or POST
	 * @param $behavior IDataTableBehavior
	 * @param $default_text string
	 * @param $state DataFormState
	 * @return string
	 */
	public static function display_textbox($form_name, $name_array, $action, $form_method, $behavior, $default_text, $state=null) {
		if ($action && $behavior) {
			$onchange = $behavior->action($form_name, $action, $form_method);
		}
		else
		{
			$onchange = "";
		}

		if ($name_array && $state) {
			$text = $state->find_item($name_array);
		}
		else
		{
			$text = $default_text;
		}

		if ($name_array) {
			$qualified_name = $form_name;
			foreach ($name_array as $name) {
				// TODO: sanitize
				$qualified_name .= "[" . $name . "]";
			}

			$ret = '<input type="text" name="' . htmlspecialchars($qualified_name) . '" onsubmit="' . htmlspecialchars($onchange) . '" value="' . htmlspecialchars($text) . '" />';
		}
		else
		{
			$ret = '<input type="text" onsubmit="' . htmlspecialchars($onchange) . '" value="' . htmlspecialchars($text) . '" />';
		}

		$ret .= "</select>";
		return $ret;
	}
}

class DataTableTextboxCellFormatter implements IDataTableCellFormatter {
	public function format($form_name, $column_header, $column_data, $rowid, $state)
	{
		return DataTableTextbox::display_textbox($form_name, array($column_header, $rowid), "", "POST", null, $column_data, $state);
	}
}