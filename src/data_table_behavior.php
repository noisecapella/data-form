<?php
/**
 * LICENSE: This source file and any compiled code are the property of its
 * respective author(s).  All Rights Reserved.  Unauthorized use is prohibited.
 *
 * @package    GFY Web Inteface
 * @author     George Schneeloch <george_schneeloch@hms.harvard.edu>
 * @copyright  2013 Above Authors and the President and Fellows of Harvard University
 */

/**
 * An interface which describes Javascript to execute when a button is clicked, menu item is selected, etc.
 *
 * Default implementations are mostly defined in data_form.js
 */
interface IDataTableBehavior {
	/**
	 * @param string $form_name Name of form
	 * @param string $form_action URL to submit to or refresh from
	 * @param string $form_method Method of form, either GET or POST. Should be same as what form is declared as in HTML
	 * @return string Javascript to execute when submit button is clicked or select item is changed
	 */
	function action($form_name, $form_action, $form_method);
}

class DataTableBehaviorNone implements IDataTableBehavior {
	function action($form_name, $form_action, $form_method) {
		return "event.preventDefault();";
	}
}

/**
 * Here for backwards compatibility. Use DataTableBehaviorSubmit instead
 */
class DataTableBehaviorSetParamsThenSubmit implements IDataTableBehavior {
	/** @var  IDataTableBehavior */
	protected $behavior;
	public function __construct($params=array(), $form_params=array()) {
		$this->behavior = new DataTableBehaviorSubmit($params, $form_params);
	}
	function action($form_name, $form_action, $form_method)
	{
		return $this->behavior->action($form_name, $form_action, $form_method);
	}
}

/**
 * Set form action for form then submit form
 *
 * Important: parameters set via the constructor must already exist
 * as hidden fields on the form for them to be set
 */
class DataTableBehaviorSubmit implements IDataTableBehavior {
	/** @var  array */
	protected $form_params;
	/** @var array */
	protected $params;

	/**
	 * @param array $form_params parameters where key is attribute name on form, value is value for attribute
	 * @param array $params parameters where key is ID of hidden input field, value is the value attribute
	 * @throws Exception
	 */
	public function __construct($params=array(), $form_params=array()) {
		if (!is_array($params)) {
			throw new Exception("params must be array");
		}
		if (!is_array($form_params)) {
			throw new Exception("form_params must be an array");
		}
		$this->form_params = $form_params;
		$this->params = $params;
	}
	function action($form_name, $form_action, $form_method) {
		if (!$form_action) {
			throw new Exception("form_action is empty");
		}
		$form_params = $this->form_params;
		$form_params["action"] = $form_action;
		$form_params["method"] = $form_method;
		if (!array_key_exists("target", $form_params)) {
			$form_params["target"] = "";
		}

		$options = array(
			"form_params" => $form_params,
			"params" => $this->params
		);

		return 'return DataForm.submit(this, event, ' . json_encode($options) . ');';
	}
}

/**
 * Use AJAX to validate form, then submit form if validation succeeded, else display errors in flash area.
 *
 * TODO: change name to ValidateAndSubmit when convenient
 */
class DataTableBehaviorValidateThenSubmit implements IDataTableBehavior {
	/** @var  string */
	protected $validation_url;
	public function __construct($validation_url) {
		if (!$validation_url || !is_string($validation_url)) {
			throw new Exception("validation_url must be a non-empty string");
		}
		$this->validation_url = $validation_url;
	}

	function action($form_name, $form_action, $form_method)
	{
		$validate_name = DataFormState::make_field_name($form_name, DataFormState::only_validate_key());
		$params = array($validate_name => "true");

		$method = strtolower($form_method);
		if ($method != "post" && $method != "get") {
			throw new Exception("Unknown method '$method'");
		}
		$flash_name = $form_name . "_flash";

		// form_action, method, validation_url, flash_name, params
		$options = array(
			"form_action" => $form_action,
			"form_method" => $form_method,
			"validation_url" => $this->validation_url,
			"flash_name" => $flash_name,
			"params" => $params
		);


		// first submit data with validation parameter to validation url
		// If a non-empty result is received (which would be errors), put it in flash div,
		// else do the submit
		return 'return DataForm.validateThenSubmit(this, event, ' . json_encode($options) . ');';
	}
}

/**
 * Use AJAX to get updated copy of form.
 */
class DataTableBehaviorRefresh implements IDataTableBehavior {
	/** @var array */
	protected $extra_params;
	public function __construct($extra_params=array()) {
		if (!is_array($extra_params)) {
			throw new Exception("params must be in an array");
		}
		foreach ($extra_params as $k => $v) {
			if (!is_string($k) || trim($k) === "") {
				throw new Exception("Each key in extra_params must be a non-empty string");
			}
		}
		$this->extra_params = $extra_params;
	}
	function action($form_name, $form_action, $form_method) {
		$only_display_form_name = DataFormState::make_field_name($form_name, DataFormState::only_display_form_key());
		$params = $this->extra_params;
		$params[$only_display_form_name] = "true";

		$method = strtolower($form_method);
		if ($method != "post" && $method != "get") {
			throw new Exception("Unknown method '$method'");
		}
		$flash_name = $form_name . "_flash";

		// form_action, method, form_name, flash_name, params
		$options = array(
			"form_action" => $form_action,
			"form_method" => $form_method,
			"form_name" => $form_name,
			"flash_name" => $flash_name,
			"params" => $params
		);

		return 'return DataForm.refresh(this, event, ' . json_encode($options) . ');';
	}
}

/**
 * Send form information using AJAX with $div height and width, and put result in $div (probably HTML with link to an image)
 */
class DataTableBehaviorRefreshImage implements IDataTableBehavior {
	/** @var array */
	protected $extra_params;
	/**
	 * @var string The name of the div to refresh with data. If falsey the form's div will be refreshed
	 */
	protected $div;
	/**
	 * @var string The name of the div which overlays the other div with some loading animation
	 */
	protected $div_overlay;

	const height_key = "height";
	const width_key = "width";

	public function __construct($div, $div_overlay, $extra_params=array()) {
		if (!is_array($extra_params)) {
			throw new Exception("params must be in an array");
		}
		if (!is_string($div) || trim($div) === "") {
			throw new Exception("div id must be a non-empty string");
		}
		if (!is_string($div_overlay) || trim($div_overlay) === "") {
			throw new Exception("div_overlay must be a non-empty string");
		}
		foreach ($extra_params as $k => $v) {
			if (!is_string($k) || trim($k) === "") {
				throw new Exception("Each key in extra_params must be a non-empty string");
			}
		}
		$this->extra_params = $extra_params;
		$this->div = $div;
		$this->div_overlay = $div_overlay;
	}

	function action($form_name, $form_action, $form_method)
	{
		$only_display_form_name = DataFormState::make_field_name($form_name, DataFormState::only_display_form_key());
		$params = $this->extra_params;
		$params[$only_display_form_name] = "true";

		if ($this->div) {
			$div = $this->div;
		}
		else
		{
			$div = $form_name;
		}

		$height_name = DataFormState::make_field_name($form_name, array(self::height_key));
		$width_name = DataFormState::make_field_name($form_name, array(self::width_key));

		$options = array(
			"form_action" => $form_action,
			"form_method" => $form_method,
			"div_name" => $div,
			"div_overlay_name" => $this->div_overlay,
			"height_name" => $height_name,
			"width_name" => $width_name,
			"params" => $params
		);

		return 'return DataForm.refreshImage(this, event, ' . json_encode($options) . ');';
	}
}

/**
 * Clear sorting state then refresh form using AJAX
 */
class DataTableBehaviorClearSortThenRefresh implements IDataTableBehavior {
	/** @var $extra_params array */
	protected $extra_params;
	public function __construct($extra_params) {
		$this->extra_params = $extra_params;
	}

	function action($form_name, $form_action, $form_method) {
		$only_display_form_name = DataFormState::make_field_name($form_name, DataFormState::only_display_form_key());
		$params = $this->extra_params;
		$params[$only_display_form_name] = "true";

		$method = strtolower($form_method);
		if ($method != "post" && $method != "get") {
			throw new Exception("Unknown method '$method'");
		}

		$flash_name = $form_name . "_flash";

		$options = array(
			"form_action" => $form_action,
			"form_method" => $form_method,
			"form_name" => $form_name,
			"flash_name" => $flash_name,
			"params" => $params
		);
		return 'return DataForm.clearSortThenRefresh(this, event, ' . json_encode($options) . ');';

	}
}
class DataTableBehaviorDefault implements IDataTableBehavior {
	function action($form_name, $form_action, $form_method) {
		return "";
	}
}

/**
 * Do whatever you want here. Be careful!
 */
class DataTableBehaviorCustom implements IDataTableBehavior {
	/** @var  string */
	protected $javascript;
	public function __construct($javascript) {
		$this->javascript = $javascript;
	}
	function action($form_name, $form_action, $form_method) {
		return $this->javascript;
	}
}

/**
 * Refresh and ignore the previous state
 */
class DataTableBehaviorReset implements IDataTableBehavior {

	function action($form_name, $form_action, $form_method)
	{
		$reset_key = DataFormState::make_field_name($form_name, DataFormState::get_reset_key());

		$refresh = new DataTableBehaviorRefresh(array($reset_key => "true"));
		return $refresh->action($form_name, $form_action, $form_method);
	}
}