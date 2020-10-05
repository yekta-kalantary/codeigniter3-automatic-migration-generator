<?php

/**
 * AMG_Migration Class
 *
 * Create a Migration File from current DB
 *
 * @package		CodeIgniter
 * @subpackage		Libraries
 * @author		Ali R Kalantary
 * @link		https://github.com/TheBiGBoSS6000/codeigniter3-automatic-migration-generator
 */
class AMG_Migration {

	/**
	 * @var object $_ci codeigniter
	 */
	private $_ci = NULL;

	/**
	 * @var string migration folder name
	 */
	private $_migration_folder_name = 'migrations';

	/**
	 * @var array collect used timestamp
	 */
	private $_timestamp_set = array();

	/**
	 * @var array migration table set
	 */
	protected $migration_table_set = [];

	/**
	 * @var string path
	 */
	protected $path = '';

	/**
	 * @var array skip table name set
	 */
	protected $skip_tables = ['migrations'];

	/**
	 * @var bool add view
	 */
	protected $add_view = FALSE;

	/**
	 * @var string database name
	 */
	private $_db_name = '';

	/**
	 * Migration_lib constructor.
	 */
	public function __construct() {
		// Get Codeigniter Object
		if (!isset($this->_ci)) {
			$this->_ci = & get_instance();
		}

		$this->path = APPPATH . $this->_migration_folder_name;

		$this->_ci->load->database();
	}

	/**
	 * Generate Migrations Files
	 *
	 * @param string $tables
	 *
	 * @return boolean|string
	 */
	public function generate($tables = '*') {
		// check tables not empty
		if (empty($tables)) {
			echo 'InvalidParameter::tables';
			return FALSE;
		}

		// get database name
		$this->_db_name = $this->_ci->db->database;

		if ($tables === '*') {
			$query = $this->_ci->db->query('SHOW FULL TABLES FROM ' . $this->_ci->db->protect_identifiers($this->_db_name));

			// collect tables of migration
			$migration_table_set = array();

			// confirm table num
			if ($query->num_rows() > 0) {
				$table_name_key = "Tables_in_{$this->_db_name}";

				foreach ($query->result_array() as $table_info) {
					if (isset($table_info[$table_name_key]) && $table_info[$table_name_key] !== '') {
						$table_name = $table_info[$table_name_key];

						// check if table in skip arrays, if so, go next
						if (in_array($table_info[$table_name_key], $this->skip_tables)) {
							continue;
						}

						// skip views
						if (strtolower($table_info['Table_type']) == 'view') {
							continue;
						}

						$migration_table_set[] = $table_info["Tables_in_{$this->_db_name}"];
					}
				}
			}

			if ($this->_ci->db->dbprefix($this->_db_name) !== '') {
				array_walk($migration_table_set, [$this, '_remove_database_prefix']);
			}

			$this->migration_table_set = $migration_table_set;
		}
		else {
			$this->migration_table_set = is_array($tables) ? $tables : explode(',', $tables);
		}


		if (!empty($this->migration_table_set)) {
			// create migration file or override it.
			foreach ($this->migration_table_set as $table_name) {
				$file_content = $this->get_file_content($table_name);

				if (!empty($file_content)) {
					$this->write_file($table_name, $file_content);
					continue;
				}
			}

			echo "Create migration success!";
			return TRUE;
		}
		else {
			echo "Empty table set!";
			return FALSE;
		}
	}

	/**
	 * _remove_database_prefix
	 *
	 * @param string $table_name
	 *
	 * @return void
	 */
	private function _remove_database_prefix(&$table_name) {
		// insensitive replace
		$table_name = str_ireplace($this->_ci->db->dbprefix, '', $table_name);
	}

	/**
	 * get_file_content
	 *
	 * @param string $table_name table name
	 *
	 * @return string $file_content
	 */
	public function get_file_content($table_name) {
		$file_content = '<?php ';
		$file_content .= 'defined(\'BASEPATH\') OR exit(\'No direct script access allowed\');' . "\n\n";
		$file_content .= "class Migration_create_{$table_name} extends CI_Migration" . "\n";
		$file_content .= '{' . "\n";
		$file_content .= $this->get_function_up_content($table_name);
		$file_content .= $this->get_function_down_content($table_name);
		$file_content .= "\n" . '}' . "\n";

		// replace tab into 4 space
		$file_content = str_replace("\t", '    ', $file_content);

		return $file_content;
	}

	/**
	 * writeFile
	 *
	 * @param string $table_name   table name
	 * @param string $file_content file content
	 *
	 * @return void
	 */
	public function write_file($table_name, $file_content) {
		$file = $this->open_file($table_name);
		fwrite($file, $file_content);
		fclose($file);
	}

	/**
	 * openFile
	 *
	 * @param string $table_name table name
	 *
	 * @return bool|resource
	 */
	public function open_file($table_name) {
		$timestamp = $this->_get_timestamp($table_name);

		$file_path = $this->path . '/' . $timestamp . '_create_' . $table_name . '.php';

		// Open for reading and writing.
		// Place the file pointer at the beginning of the file and truncate the file to zero length.
		// If the file does not exist, attempt to create it.
		$file = fopen($file_path, 'w+');

		if (!$file) {
			return FALSE;
		}

		// add this timestamp to timestamp ser
		$this->_timestamp_set[] = $timestamp;

		return $file;
	}

	/**
	 * _get_timestamp get
	 *
	 * @param $table_name
	 * @return string
	 */
	private function _get_timestamp($table_name) {
		// get timestamp
		$query = $this->_ci->db->query(' SHOW TABLE STATUS WHERE Name = \'' . $table_name . '\'');

		$engines = $query->row_array();

		$timestamp = date('YmdHis', strtotime($engines['Create_time']));

		while (in_array($timestamp, $this->_timestamp_set)) {
			$timestamp += 1;
		}

		return $timestamp;
	}

	/**
	 * Base on table name create migration up function
	 *
	 * @param $table_name
	 *
	 * @return string
	 */
	public function get_function_up_content($table_name) {
		$str = "\n\t" . '/**' . "\n";
		$str .= "\t" . ' * up (create table)' . "\n";
		$str .= "\t" . ' *' . "\n";
		$str .= "\t" . ' * @return void' . "\n";
		$str .= "\t" . ' */' . "\n";
		$str .= "\t" . 'public function up()' . "\n";
		$str .= "\t" . '{' . "\n";

		$query = $this->_ci->db->query("SHOW FULL FIELDS FROM {$this->_ci->db->dbprefix($table_name)} FROM {$this->_db_name}");

		if ($query->result() === NULL) {
			return FALSE;
		}

		$columns = $query->result_array();

		$add_field_array = array();
		$add_primary_key_field_array = array();
		$add_key_field_array = array();
		foreach ($columns as $key => $value) {
			$add_field_array[$value['Field']]['type'] = $value['Type'];
			if ($value['Null'] == 'YES') {
				$add_field_array[$value['Field']]['null'] = TRUE;
			}
			if (strpos($value['Extra'], 'auto_increment') !== FALSE) {
				$add_field_array[$value['Field']]['auto_increment'] = TRUE;
			}
			if (strpos($value['Key'], 'PRI') !== FALSE) {
				$add_primary_key_field_array[] = $value['Field'];
			}
			elseif ($value['Key'] !== '') {
				$add_key_field_array[] = $value['Field'];
			}
		}
		
		$str .= "\n\t\t" . '// Add Fields.' . "\n";
		$add_field_str = "\t\t" . '$this->dbforge->add_field(';
		$add_field_str .= var_export($add_field_array, TRUE);
		$add_field_str .= ");" . "\n";
		$str .= str_replace(
				array("  ", "array (", "));"),
				array("\t\t\t", "\tarray (", "\t\t));"),
				$add_field_str
		);

		if (!empty($add_primary_key_field_array)) {
			$str .= "\n\t\t" . '// Add Primary Key(s).' . "\n";
			$add_primary_key_field_str = "\t\t" . '$this->dbforge->add_key(';
			$add_primary_key_field_str .= var_export($add_primary_key_field_array, TRUE);
			$add_primary_key_field_str .= ", TRUE);" . "\n";
			$str .= str_replace(
					array("  ", "array (", "), TRUE);"),
					array("\t\t\t", "\tarray (", "\t\t), TRUE);"),
					$add_primary_key_field_str
			);
		}

		if (!empty($add_key_field_array)) {
			$str .= "\n\t\t" . '// Add Key(s).' . "\n";
			$add_key_field_str = "\t\t" . '$this->dbforge->add_key(';
			$add_key_field_str .= var_export($add_key_field_array, TRUE);
			$add_key_field_str .= ");" . "\n";
			$str .= str_replace(
					array("  ", "array (", "));"),
					array("\t\t\t", "\tarray (", "\t\t));"),
					$add_key_field_str
			);
		}

		// create db

		$query = $this->_ci->db->query(' SHOW TABLE STATUS WHERE Name = \'' . $table_name . '\'');

		$engines = $query->row_array();

		$attributes_str = "\n\t\t" . '$attributes = array(' . "\n";

		$attributes_str .= ((string) $engines['Engine'] !== '') ? "\t\t\t'ENGINE' => '" . $engines['Engine'] . "'," . "\n" : '';
		$attributes_str .= ((string) $engines['Comment'] !== '') ? "\t\t\t'COMMENT' => '\\'" . str_replace("'", "\\'", $engines['Comment']) . "'\\'',\n" : '';
		$attributes_str .= "\t\t" . ');' . "\n";

		$str .= "\n\t\t" . '// Table attributes.' . "\n";
		$str .= $attributes_str;

		$str .= "\n\t\t" . '// Create Table ' . $table_name . "\n";
		$str .= "\t\t" . '$this->dbforge->create_table("' . $table_name . '", TRUE, $attributes);' . "\n";

		$str .= "\n\t" . '}' . "\n";

		return $str;
	}

	/**
	 * Base on table name create migration down function
	 *
	 * @param string $table_name table name
	 *
	 * @return string
	 */
	public function get_function_down_content($table_name) {
		$function_content = "\n\t" . '/**' . "\n";
		$function_content .= "\t" . ' * down (drop table)' . "\n";
		$function_content .= "\t" . ' *' . "\n";
		$function_content .= "\t" . ' * @return void' . "\n";
		$function_content .= "\t" . ' */' . "\n";

		$function_content .= "\t" . 'public function down()' . "\n";
		$function_content .= "\t" . '{' . "\n";
		$function_content .= "\t\t" . '// Drop table ' . $table_name . "\n";
		$function_content .= "\t\t" . '$this->dbforge->drop_table("' . $table_name . '", TRUE);' . "\n";
		$function_content .= "\t" . '}' . "\n";

		return $function_content;
	}

}
