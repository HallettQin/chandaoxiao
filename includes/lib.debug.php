<?php
//cgxlm
if (!defined('USE_DEBUGLIB')) {
	define('USE_DEBUGLIB', true);
}

if (USE_DEBUGLIB) {
	$MICROTIME_START = microtime();
	@$GLOBALS_initial_count = count($GLOBALS);
	class Print_a_class
	{
		public $look_for_leading_tabs = false;
		public $output;
		public $iterations;
		public $key_bg_color = '1E32C8';
		public $value_bg_color = 'DDDDEE';
		public $fontsize = '8pt';
		public $keyalign = 'left';
		public $fontfamily = 'Verdana';
		public $show_object_vars;
		public $limit;

		public function _only_numeric_keys($array)
		{
			$test = true;

			if (is_array($array)) {
				foreach (array_keys($array) as $key) {
					if (!is_numeric($key)) {
						$test = false;
					}
				}

				return $test;
			}
			else {
				return false;
			}
		}

		public function _handle_whitespace($string)
		{
			$string = str_replace(' ', '&nbsp;', $string);
			$string = preg_replace(array('/&nbsp;$/', '/^&nbsp;/'), '<span style="color:red;">_</span>', $string);
			$string = preg_replace('/\\t/', '&nbsp;&nbsp;<span style="border-bottom:#' . $this->value_bg_color . ' solid 1px;">&nbsp;</span>', $string);
			return $string;
		}

		public function print_a($array, $iteration = false, $key_bg_color = false)
		{
			$key_bg_color || ($key_bg_color = $this->key_bg_color);

			if ($iteration) {
				for ($i = 0; $i < 6; $i += 2) {
					$c = substr($key_bg_color, $i, 2);
					$c = hexdec($c);
					(255 < ($c += 15)) && ($c = 255);
					isset($tmp_key_bg_color) || ($tmp_key_bg_color = '');
					$tmp_key_bg_color .= sprintf('%02X', $c);
				}

				$key_bg_color = $tmp_key_bg_color;
			}

			$this->output .= '<table style="border:none;" cellspacing="1">';
			$only_numeric_keys = $this->_only_numeric_keys($array) || (50 < count($array));
			$i = 0;

			foreach ($array as $key => $value) {
				if ($only_numeric_keys && $this->limit && ($this->limit == $i++)) {
					break;
				}

				$value_style_box = 'color:black;';
				$key_style = 'color:white;';
				$type = gettype($value);
				$type_title = $type;
				$value_style_content = '';

				switch ($type) {
				case 'array':
					if (empty($value)) {
						$type_title = 'empty array';
					}

					break;

				case 'object':
					$key_style = 'color:#FF9B2F;';
					break;

				case 'integer':
					$value_style_box = 'color:green;';
					break;

				case 'double':
					$value_style_box = 'color:blue;';
					break;

				case 'boolean':
					if ($value == true) {
						$value_style_box = 'color:#D90081;';
					}
					else {
						$value_style_box = 'color:#84009F;';
					}

					break;

				case 'NULL':
					$value_style_box = 'color:darkorange;';
					break;

				case 'string':
					if ($value == '') {
						$value_style_box = 'color:darkorange;';
						$value = '\'\'';
						$type_title = 'empty string';
					}
					else {
						$value_style_box = 'color:black;';
						$value = htmlspecialchars($value);
						if ($this->look_for_leading_tabs && _check_for_leading_tabs($value)) {
							$value = _remove_exessive_leading_tabs($value);
						}

						$value = $this->_handle_whitespace($value);
						$value = nl2br($value);

						if (strstr($value, "\n")) {
							$value_style_content = 'background:#ECEDFE;';
						}
					}

					break;
				}

				$this->output .= '<tr>';
				$this->output .= '<td nowrap="nowrap" align="' . $this->keyalign . '" style="padding:0px 3px 0px 3px;background-color:#' . $key_bg_color . ';' . $key_style . ';font:bold ' . $this->fontsize . ' ' . $this->fontfamily . ';" title="' . gettype($key) . '[' . $type_title . ']">';
				$this->output .= $this->_handle_whitespace($key);
				$this->output .= '</td>';
				$this->output .= '<td nowrap="nowrap" style="background-color:#' . $this->value_bg_color . ';font: ' . $this->fontsize . ' ' . $this->fontfamily . '; color:black;">';
				if (($type == 'array') && preg_match('/#RAS/', $key)) {
					$this->output .= '<div style="' . $value_style_box . '">recursion!</div>';
				}
				else if ($type == 'array') {
					if (!empty($value)) {
						$this->print_a($value, true, $key_bg_color);
					}
					else {
						$this->output .= '<span style="color:darkorange;">[]</span>';
					}
				}
				else if ($type == 'object') {
					if ($this->show_object_vars) {
						$objects_class = get_class($value);
						$this->print_a(array('CLASS_NAME' => $objects_class), true, '204FB8');
						$this->print_a(array('CLASS_VARS' => get_class_vars($objects_class)), true, '2066B8');
						$this->print_a(array('CLASS_METHODS' => get_class_methods($objects_class)), true, '2067EB8');
						$this->print_a(array('OBJECT_VARS' => get_object_vars($value)), true, '2095B8');
					}
					else {
						$this->output .= '<div style="' . $value_style_box . '">OBJECT</div>';
					}
				}
				else if ($type == 'boolean') {
					$this->output .= '<div style="' . $value_style_box . '" title="' . $type . '">' . ($value ? 'true' : 'false') . '</div>';
				}
				else if ($type == 'NULL') {
					$this->output .= '<div style="' . $value_style_box . '" title="' . $type . '">NULL</div>';
				}
				else {
					$this->output .= '<div style="' . $value_style_box . '" title="' . $type . '"><span style="' . $value_style_content . '">' . $value . '</span></div>';
				}

				$this->output .= '</td>';
				$this->output .= '</tr>';
			}

			$entry_count = count($array);
			$skipped_count = $entry_count - $this->limit;
			if ($only_numeric_keys && $this->limit && ($this->limit < count($array))) {
				$this->output .= '<tr title="showing ' . $this->limit . ' of ' . $entry_count . ' entries in this array"><td style="text-align:right;color:darkgray;background-color:#' . $key_bg_color . ';font:bold ' . $this->fontsize . ' ' . $this->fontfamily . ';">...</td><td style="background-color:#' . $this->value_bg_color . ';font:' . $this->fontsize . ' ' . $this->fontfamily . ';color:darkgray;">[' . $skipped_count . ' skipped]</td></tr>';
			}

			$this->output .= '</table>';
		}
	}
	function print_a($array, $mode = 0, $show_object_vars = false, $limit = false)
	{
		$output = '';
		if (is_array($array) || is_object($array)) {
			if (empty($array)) {
				$output .= '<span style="color:red;font-size:small;">print_a( empty array )</span>';
			}

			$pa = &new Print_a_class();
			$show_object_vars && $pa->show_object_vars = true;

			if ($limit) {
				$pa->limit = $limit;
			}

			if (is_object($array)) {
				$pa->print_a(get_object_vars($array));
			}
			else {
				$pa->print_a($array);
			}

			$output .= $pa->output;
		}
		else if (gettype($array) == 'boolean') {
			$output .= '<span style="color:red;font-size:small;">print_a( ' . ($array === true ? 'true' : 'false') . ' )</span>';
		}
		else {
			$output .= '<span style="color:red;font-size:small;">print_a( ' . gettype($array) . ' )</span>';
		}

		if (($mode === 0) || ($mode == NULL) || ($mode == false)) {
			print($output);
			return true;
		}

		if ($mode == 1) {
			return $output;
		}

		if (is_string($mode) || ($mode == 2)) {
			$debugwindow_origin = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

			if (preg_match('/(.+)::(.+)/', $mode, $matches)) {
				$mode = $matches[1];
				$remote_addr = gethostbyname($matches[2]);

				if ($_SERVER['REMOTE_ADDR'] != $remote_addr) {
					return NULL;
				}
			}

			if (preg_match('/^_(.*)/', $mode, $matches)) {
				$output = '<fieldset style="width:100px;padding:2px;border:1px solid #666666;"><legend>' . $matches[1] . '</legend>' . $output . '</fieldset><br />';
				print($output);
			}
			else {
				print("\r\n                <script type=\"text/javascript\" language=\"JavaScript\">\r\n                var debugwindow;\r\n                debugwindow = window.open(\"\", \"T_" . md5($_SERVER['HTTP_HOST']) . (is_string($mode) ? $mode : '') . "\", \"menubar=no,scrollbars=yes,resizable=yes,width=640,height=480\");\r\n                debugwindow.document.open();\r\n                debugwindow.document.write(\"" . str_replace(array("\r\n", "\n", "\r"), '\\n', addslashes($output)) . "\");\r\n                debugwindow.document.close();\r\n                debugwindow.document.title = \"" . (is_string($mode) ? '(' . $mode . ')' : '') . ' Debugwindow for : http://' . $debugwindow_origin . "\";\r\n                debugwindow.focus();\r\n                </script>\r\n                ");
			}
		}

		if ($mode == 3) {
			print("\r\n                <script type=\"text/javascript\" language=\"JavaScript\">\r\n                    var debugwindow;\r\n                    debugwindow = window.open(\"\", \"S_" . md5($_SERVER['HTTP_HOST']) . "\", \"menubar=yes,scrollbars=yes,resizable=yes,width=640,height=480\");\r\n                    debugwindow.document.open();\r\n                    debugwindow.document.write(\"unserialize('" . str_replace('\'', '\\\'', addslashes(str_replace(array("\r\n", "\n", "\r"), '\\n', serialize($array)))) . "');\");\r\n                    debugwindow.document.close();\r\n                    debugwindow.document.title = \"Debugwindow for : http://" . $debugwindow_origin . "\";\r\n                    debugwindow.focus();\r\n                </script>\r\n            ");
		}
	}
	function print_result($RESULT)
	{
		if (!$RESULT) {
			return NULL;
		}

		if (mysql_num_rows($RESULT) < 1) {
			return NULL;
		}

		$fieldcount = mysql_num_fields($RESULT);

		for ($i = 0; $i < $fieldcount; $i++) {
			$tables[mysql_field_table($RESULT, $i)]++;
		}

		print("\r\n            <style type=\"text/css\">\r\n                .rs_tb_th {\r\n                    font-family: Verdana;\r\n                    font-size:9pt;\r\n                    font-weight:bold;\r\n                    color:white;\r\n                }\r\n                .rs_f_th {\r\n                    font-family:Verdana;\r\n                    font-size:7pt;\r\n                    font-weight:bold;\r\n                    color:white;\r\n                }\r\n                .rs_td {\r\n                    font-family:Verdana;\r\n                    font-size:7pt;\r\n                }\r\n            </style>\r\n            <script type=\"text/javascript\" language=\"JavaScript\">\r\n                var lastID;\r\n                function highlight(id) {\r\n                    if(lastID) {\r\n                        lastID.style.color = \"#000000\";\r\n                        lastID.style.textDecoration = \"none\";\r\n                    }\r\n                    tdToHighlight = document.getElementById(id);\r\n                    tdToHighlight.style.color =\"#FF0000\";\r\n                    tdToHighlight.style.textDecoration = \"underline\";\r\n                    lastID = tdToHighlight;\r\n                }\r\n            </script>\r\n        ");
		print('<table bgcolor="#000000" cellspacing="1" cellpadding="1">');
		print('<tr>');

		foreach ($tables as $tableName => $tableCount) {
			$col == '0054A6' ? $col = '003471' : $col = '0054A6';
			print('<th colspan="' . $tableCount . '" class="rs_tb_th" style="background-color:#' . $col . ';">' . $tableName . '</th>');
		}

		print('</tr>');
		print('<tr>');

		for ($i = 0; $i < mysql_num_fields($RESULT); $i++) {
			$FIELD = mysql_field_name($RESULT, $i);
			$col == '0054A6' ? $col = '003471' : $col = '0054A6';
			print('<td align="center" bgcolor="#' . $col . '" class="rs_f_th">' . $FIELD . '</td>');
		}

		print('</tr>');
		mysql_data_seek($RESULT, 0);

		while ($DB_ROW = mysql_fetch_array($RESULT, MYSQL_NUM)) {
			$pointer++;

			if ($toggle) {
				$col1 = 'E6E6E6';
				$col2 = 'DADADA';
			}
			else {
				$col1 = 'E1F0FF';
				$col2 = 'DAE8F7';
			}

			$toggle = !$toggle;
			print('<tr id="ROW' . $pointer . '" onMouseDown="highlight(\'ROW' . $pointer . '\');">');

			foreach ($DB_ROW as $value) {
				$col == $col1 ? $col = $col2 : $col = $col1;
				print('<td valign="top" bgcolor="#' . $col . '" class="rs_td" nowrap>' . nl2br($value) . '</td>');
			}

			print('</tr>');
		}

		print('</table>');
		mysql_data_seek($RESULT, 0);
	}
	function reset_script_runtime()
	{
		$GLOBALS['MICROTIME_START'] = microtime();
	}
	function script_runtime()
	{
		$MICROTIME_END = microtime();
		$MICROTIME_START = explode(' ', $GLOBALS['MICROTIME_START']);
		$MICROTIME_END = explode(' ', $MICROTIME_END);
		$GENERATIONSEC = $MICROTIME_END[1] - $MICROTIME_START[1];
		$GENERATIONMSEC = $MICROTIME_END[0] - $MICROTIME_START[0];
		$GENERATIONTIME = substr($GENERATIONSEC + $GENERATIONMSEC, 0, 8);
		return (double) $GENERATIONTIME;
	}
	function _script_globals()
	{
		global $GLOBALS_initial_count;
		$varcount = 0;

		foreach ($GLOBALS as $GLOBALS_current_key => $GLOBALS_current_value) {
			if ($GLOBALS_initial_count < ++$varcount) {
				if (($GLOBALS_current_key != 'HTTP_SESSION_VARS') && ($GLOBALS_current_key != '_SESSION')) {
					$script_GLOBALS[$GLOBALS_current_key] = $GLOBALS_current_value;
				}
			}
		}

		unset($script_GLOBALS['GLOBALS_initial_count']);
		return $script_GLOBALS;
	}
	function show_vars($show_all_vars = false, $show_object_vars = false, $limit = 5)
	{
		if ($limit === 0) {
			$limit = false;
		}

		if (isset($GLOBALS['no_vars'])) {
			return NULL;
		}

		$script_globals = _script_globals();
		print("\r\n            <style type=\"text/css\" media=\"screen\">\r\n                .vars-container {\r\n                    font-family: Verdana, Arial, Helvetica, Geneva, Swiss, SunSans-Regular, sans-serif;\r\n                    font-size: 8pt;\r\n                    padding:5px;\r\n                }\r\n                .varsname {\r\n                    font-weight:bold;\r\n                }\r\n                .showvars {\r\n                    background:white;\r\n                    border-style:dotted;\r\n                    border-width:1px;\r\n                    padding:2px;\r\n                    font-family: Verdana, Arial, Helvetica, Geneva, Swiss, SunSans-Regular, sans-serif;\r\n                    font-size:10pt;\r\n                    font-weight:bold;\"\r\n                }\r\n            </style>\r\n            <style type=\"text/css\" media=\"print\">\r\n                .showvars {\r\n                    display:none;\r\n                    visibility:invisible;\r\n                }\r\n            </style>\r\n        ");
		print("<br />\r\n            <div class=\"showvars\">\r\n            DEBUG <span style=\"color:red;font-weight:normal;font-size:9px;\">(runtime: " . script_runtime() . " sec)</span>\r\n        ");
		$vars_arr['script_globals'] = array('global script variables', '#7ACCC8');
		$vars_arr['_GET'] = array('$_GET', '#7DA7D9');
		$vars_arr['_POST'] = array('$_POST', '#F49AC1');
		$vars_arr['_FILES'] = array('$_FILES', '#82CA9C');
		$vars_arr['_SESSION'] = array('$_SESSION', '#FCDB26');
		$vars_arr['_COOKIE'] = array('$_COOKIE', '#A67C52');

		if ($show_all_vars) {
			$vars_arr['_SERVER'] = array('SERVER', '#A186BE');
			$vars_arr['_ENV'] = array('ENV', '#7ACCC8');
		}

		foreach ($vars_arr as $vars_name => $vars_data) {
			if ($vars_name != 'script_globals') {
				global $$vars_name;
			}

			if ($$vars_name) {
				print('<div class="vars-container" style="background-color:' . $vars_data[1] . ';"><span class="varsname">' . $vars_data[0] . '</span><br />');
				print_a($$vars_name, NULL, $show_object_vars, $limit);
				print('</div>');
			}
		}

		print('</div>');
	}
	function pre($string, $return_mode = false, $tabwidth = 3)
	{
		$tab = str_repeat('&nbsp;', $tabwidth);
		$string = preg_replace('/\\t+/em', 'str_repeat( \' \', strlen(\'\\0\') * ' . $tabwidth . ' );', $string);
		$out = '<pre>' . $string . "</pre>\n";

		if ($return_mode) {
			return $out;
		}
		else {
			print($out);
		}
	}
	function _check_for_leading_tabs($string)
	{
		return preg_match('/^\\t/m', $string);
	}
	function _remove_exessive_leading_tabs($string)
	{
		$string = preg_replace('/^\\s*\\n/', '', $string);
		$string = preg_replace('/\\s*$/', '', $string);
		preg_match_all('/^\\t+/', $string, $matches);
		$minTabCount = strlen(@min($matches[0]));
		$string = preg_replace('/^\\t{' . $minTabCount . '}/m', '', $string);
		return $string;
	}
}
else {
	function print_a()
	{
	}
	function print_result()
	{
	}
	function reset_script_runtime()
	{
	}
	function script_runtime()
	{
	}
	function show_vars()
	{
	}
	function pre()
	{
	}
}

?>
