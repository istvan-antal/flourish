<?php
/**
 * Handles validation for (@link fActiveRecord} classes
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMValidation
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
 */
class fORMValidation
{	
	/**
	 * Conditional validation rules
	 * 
	 * @var array
	 */
	static private $conditional_validation_rules = array();
	
	/**
	 * Ordering rules for validation messages
	 * 
	 * @var array
	 */
	static private $message_orders = array();
	
	/**
	 * One or more validation rules
	 * 
	 * @var array
	 */
	static private $one_or_more_validation_rules = array();
	
	/**
	 * Only one validation rules
	 * 
	 * @var array
	 */
	static private $only_one_validation_rules = array();
	
	/**
	 * Validation rules that require at least one or more *-to-many related records to be associated
	 * 
	 * @var array
	 */
	static private $related_one_or_more_validation_rules = array();
	
	
	/**
	 * Adds a conditional validation rule
	 *
	 * @param  mixed  $class                The class name or instance of the class this validation rule applies to
	 * @param  string $main_column          The column to check for a value
	 * @param  array  $conditional_values   If empty, any value in the main column will trigger the conditional columns, otherwise the value must match one of these
	 * @param  array  $conditional_columns  The columns that are to be required
	 * @return void
	 */
	static public function addConditionalValidationRule($class, $main_column, $conditional_values, $conditional_columns)
	{
		$class = fORM::getClassName($class);
		
		if (!isset(self::$conditional_validation_rules[$class])) {
			self::$conditional_validation_rules[$class] = array();
		}
		
		$rule = array();
		$rule['main_column']         = $main_column;
		$rule['conditional_values']  = $conditional_values;
		$rule['conditional_columns'] = $conditional_columns;
		
		self::$conditional_validation_rules[$class][] = $rule;
	}
	
	
	/**
	 * Add a many-to-many validation rule that requires at least one related record is associated with the current record
	 *
	 * @param  mixed  $class          The class name or instance of the class to add the rule for
	 * @param  string $related_class  The name of the related class
	 * @param  string $route          The route to the related class
	 * @return void
	 */
	static public function addManyToManyValidationRule($class, $related_class, $route=NULL)
	{
		$class = fORM::getClassName($class);
		
		if (!isset(self::$related_one_or_more_validation_rules[$class])) {
			self::$related_one_or_more_validation_rules[$class] = array();
		}
		
		if (!isset(self::$related_one_or_more_validation_rules[$class][$related_class])) {
			self::$related_one_or_more_validation_rules[$class][$related_class] = array();
		}
		
		$table = fORM::tablize($class);
		$route = fORMSchema::getRouteName($table, $related_table, $route, 'many-to-many');
		
		self::$related_one_or_more_validation_rules[$class][$related_class][$route] = TRUE;
	}
	
	
	/**
	 * Adds a one-or-more validation rule
	 *
	 * @param  mixed $class    The class name or instance of the class the columns exists in
	 * @param  array $columns  The columns to check
	 * @return void
	 */
	static public function addOneOrMoreValidationRule($class, $columns)
	{
		$class = fORM::getClassName($class);
		
		settype($columns, 'array');
		
		if (!isset(self::$one_or_more_validation_rules[$class])) {
			self::$one_or_more_validation_rules[$class] = array();
		}
		
		$rule = array();
		$rule['columns'] = $columns;
		
		self::$one_or_more_validation_rules[$class][] = $rule;
	}
	
	
	/**
	 * Add a one-to-many validation rule that requires at least one related record is associated with the current record
	 *
	 * @param  mixed  $class          The class name or instance of the class to add the rule for
	 * @param  string $related_class  The name of the related class
	 * @param  string $route          The route to the related class
	 * @return void
	 */
	static public function addOneToManyValidationRule($class, $related_class, $route=NULL)
	{
		$class = fORM::getClassName($class);
		
		if (!isset(self::$related_one_or_more_validation_rules[$class])) {
			self::$related_one_or_more_validation_rules[$class] = array();
		}
		
		if (!isset(self::$related_one_or_more_validation_rules[$class][$related_class])) {
			self::$related_one_or_more_validation_rules[$class][$related_class] = array();
		}
		
		$table = fORM::tablize($class);
		$route = fORMSchema::getRouteName($table, $related_table, $route, 'one-to-many');
		
		self::$related_one_or_more_validation_rules[$class][$related_class][$route] = TRUE;
	}
	
	
	/**
	 * Add an only-one validation rule
	 *
	 * @param  mixed $class    The class name or instance of the class the columns exists in
	 * @param  array $columns  The columns to check
	 * @return void
	 */
	static public function addOnlyOneValidationRule($class, $columns)
	{
		$class = fORM::getClassName($class);
		
		settype($columns, 'array');
		
		if (!isset(self::$only_one_validation_rules[$class])) {
			self::$only_one_validation_rules[$class] = array();
		}
		
		$rule = array();
		$rule['columns'] = $columns;
		
		self::$only_one_validation_rules[$class][] = $rule;
	}
	
	
	/**
	 * Validates a value against the database schema
	 *
	 * @param  mixed  $class        The class name or instance of the class the column is part of
	 * @param  string $column       The column to check
	 * @param  array  &$values      An associative array of all values going into the row (needs all for multi-field unique constraint checking)
	 * @param  array  &$old_values  The old values from the record
	 * @return string  A validation error message for the column specified
	 */
	static private function checkAgainstSchema($class, $column, &$values, &$old_values)
	{
		$table = fORM::tablize($class);
		
		$column_info = fORMSchema::getInstance()->getColumnInfo($table, $column);
		// Make sure a value is provided for required columns
		if ($values[$column] === NULL && $column_info['not_null'] && $column_info['default'] === NULL && $column_info['auto_increment'] === FALSE) {
			return fORM::getColumnName($class, $column) . ': Please enter a value';
		}
		
		$message = self::checkDataType($class, $column, $values[$column]);
		if ($message) { return $message; }
		
		// Make sure a valid value is chosen
		if (isset($column_info['valid_values']) && $values[$column] !== NULL && !in_array($values[$column], $column_info['valid_values'])) {
			return fORM::getColumnName($class, $column) . ': Please choose from one of the following: ' . join(', ', $column_info['valid_values']);
		}
		// Make sure the value isn't too long
		if (isset($column_info['max_length']) && $values[$column] !== NULL && is_string($values[$column]) && fUTF8::len($values[$column]) > $column_info['max_length']) {
			return fORM::getColumnName($class, $column) . ': Please enter a value no longer than ' . $column_info['max_length'] . ' characters';
		}
		
		$message = self::checkUniqueConstraints($class, $column, $values, $old_values);
		if ($message) { return $message; }
		$message = self::checkForeignKeyConstraints($class, $column, $values);
		if ($message) { return $message; }
	}
	
	
	/**
	 * Validates against a conditional validation rule
	 *
	 * @param  mixed  $class                The class name or instance of the class this validation rule applies to
	 * @param  array  &$values              An associative array of all values for the record
	 * @param  string $main_column          The column to check for a value
	 * @param  array  $conditional_values   If empty, any value in the main column will trigger the conditional columns, otherwise the value must match one of these
	 * @param  array  $conditional_columns  The columns that are to be required
	 * @return array  The validation error messages for the rule specified
	 */
	static private function checkConditionalRule($class, &$values, $main_column, $conditional_values, $conditional_columns)
	{
		if (!empty($conditional_values))  {
			settype($conditional_values, 'array');
		}
		settype($conditional_columns, 'array');
		
		if ($values[$main_column] === NULL) {
			return;
		}
		
		if ((!empty($conditional_values) && in_array($values[$main_column], $conditional_values)) || (empty($conditional_values))) {
			$messages = array();
			foreach ($conditional_columns as $conditional_column) {
				if ($values[$conditional_column] === NULL) {
					$messages[] = fORM::getColumnName($class, $conditional_column) . ': Please enter a value';
				}
			}
			if (!empty($messages)) {
				return $messages;
			}
		}
	}
	
	
	/**
	 * Validates a value against the database data type
	 *
	 * @param  mixed  $class   The class name or instance of the class the column is part of
	 * @param  string $column  The column to check
	 * @param  mixed  $value   The value to check
	 * @return string  A validation error message for the column specified
	 */
	static private function checkDataType($class, $column, $value)
	{
		$table       = fORM::tablize($class);
		$column_info = fORMSchema::getInstance()->getColumnInfo($table, $column);
		
		if ($value !== NULL) {
			switch ($column_info['type']) {
				case 'varchar':
				case 'char':
				case 'text':
				case 'blob':
					if (!is_string($value) && !is_numeric($value)) {
						return fORM::getColumnName($class, $column) . ': Please enter a string';
					}
					break;
				case 'integer':
				case 'float':
					if (!is_numeric($value)) {
						return fORM::getColumnName($class, $column) . ': Please enter a number';
					}
					break;
				case 'timestamp':
					if (strtotime($value) === FALSE) {
						return fORM::getColumnName($class, $column) . ': Please enter a date/time';
					}
					break;
				case 'date':
					if (strtotime($value) === FALSE) {
						return fORM::getColumnName($class, $column) . ': Please enter a date';
					}
					break;
				case 'time':
					if (strtotime($value) === FALSE) {
						return fORM::getColumnName($class, $column) . ': Please enter a time';
					}
					break;
				
			}
		}
	}
	
	
	/**
	 * Validates values against foreign key constraints
	 *
	 * @param  mixed  $class    The class name or instance of the class to check the foreign keys for
	 * @param  string $column   The column to check
	 * @param  array  &$values  The values to check
	 * @return string  A validation error message for the column specified
	 */
	static private function checkForeignKeyConstraints($class, $column, &$values)
	{
		if ($values[$column] === NULL) {
			return;
		}
		
		$table        = fORM::tablize($class);
		$foreign_keys = fORMSchema::getInstance()->getKeys($table, 'foreign');
		
		foreach ($foreign_keys AS $foreign_key) {
			if ($foreign_key['column'] == $column) {
				try {
					$sql  = "SELECT " . $foreign_key['foreign_column'];
					$sql .= " FROM " . $foreign_key['foreign_table'];
					$sql .= " WHERE ";
					$sql .= $column . fORMDatabase::prepareBySchema($table, $column, $values[$column], '=');
					$sql  = str_replace('WHERE ' . $column, 'WHERE ' . $foreign_key['foreign_column'], $sql);
					
					$result = fORMDatabase::getInstance()->translatedQuery($sql);
					$result->tossIfNoResults();
				} catch (fNoResultsException $e) {
					return fORM::getColumnName($class, $column) . ': The value specified is invalid';
				}
			}
		}
	}
	
	
	/**
	 * Validates against a one-or-more validation rule
	 *
	 * @param  mixed  $class    The class name or instance of the class the columns are part of
	 * @param  array  &$values  An associative array of all values for the record
	 * @param  array  $columns  The columns to check
	 * @return string  A validation error message for the rule
	 */
	static private function checkOneOrMoreRule($class, &$values, $columns)
	{
		settype($columns, 'array');
		
		$found_value = FALSE;
		foreach ($columns as $column) {
			if ($values[$column] !== NULL) {
				$found_value = TRUE;
			}
		}
		
		if (!$found_value) {
			$column_names = '';
			$column_num = 0;
			foreach ($columns as $column) {
				if ($column_num) { $column_names .= ', '; }
				$column_names .= fORM::getColumnName($class, $column);
				$column_num++;
			}
			return $column_names . ': Please enter a value for at least one';
		}
	}
	
	
	/**
	 * Validates against an only-one validation rule
	 *
	 * @param  mixed  $class    The class name or instance of the class the columns are part of
	 * @param  array  &$values  An associative array of all values for the record
	 * @param  array  $columns  The columns to check
	 * @return string  A validation error message for the rule
	 */
	static private function checkOnlyOneRule($class, &$values, $columns)
	{
		settype($columns, 'array');
		
		$found_value = FALSE;
		foreach ($columns as $column) {
			if ($values[$column] !== NULL) {
				if ($found_value) {
					$column_names = '';
					$column_num = 0;
					foreach ($columns as $column) {
						if ($column_num) { $column_names .= ', '; }
						$column_names .= fORM::getColumnName($class, $column);
						$column_num++;
					}
					return $column_names . ': Please enter a value for only one';
				}
				$found_value = TRUE;
			}
		}
	}
	
	
	/**
	 * Makes sure a record with the same primary keys is not already in the database
	 *
	 * @param  mixed  $class        The class name or instance of the class to check
	 * @param  array  &$values      An associative array of all values going into the row (needs all for multi-field unique constraint checking)
	 * @param  array  &$old_values  The old values for the record
	 * @return string  A validation error message 
	 */
	static private function checkPrimaryKeys($class, &$values, &$old_values)
	{
		$table = fORM::tablize($class);
		
		$primary_keys = fORMSchema::getInstance()->getKeys($table, 'primary');
		
		$exists = TRUE;
		$key_set = FALSE;
		foreach ($primary_keys as $primary_key) {
			if ((array_key_exists($primary_key, $old_values) && $old_values[$primary_key] === NULL) || $values[$primary_key] === NULL) {
				$exists = FALSE;
			}
			if ($values[$primary_key] !== NULL) {
				$key_set = TRUE;
			}
		}
		
		// We don't need to check if the record is existing
		if ($exists || !$key_set) {
			return;
		}
		
		try {
			$sql = "SELECT * FROM " . $table . " WHERE ";
			$key_num = 0;
			$columns = '';
			foreach ($primary_keys as $primary_key) {
				if ($key_num) { $sql .= " AND "; $columns.= ', '; }
				$sql .= $primary_key . fORMDatabase::prepareBySchema($table, $primary_key, (!empty($old_values[$primary_key])) ? $old_values[$primary_key] : $values[$primary_key], '=');
				$columns .= fORM::getColumnName($class, $primary_key);
				$key_num++;
			}
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			$result->tossIfNoResults();
			
			return 'A ' . fORM::getRecordName($class) . ' with the same ' . $columns . ' already exists';
			
		} catch (fNoResultsException $e) { }
	}
	
	
	/**
	 * Validates against a *-to-many one or more validation rule
	 *
	 * @param  mixed  $class             The class name or instance of the class we are checking
	 * @param  array  &$related_records  The related records array to check
	 * @param  string $related_class     The name of the related class
	 * @param  string $route             The name of the route from the class to the related class 
	 * @return string  A validation error message for the rule
	 */
	static private function checkRelatedOneOrMoreRule($class, &$related_records, $related_class, $route)
	{
		if (!empty($related_records[$related_class][$route]) && $related_records[$related_class][$route]->isFlaggedForAssociation()) {
			return;		
		}
		
		return fORMRelated::getRelatedRecordName($class, $related_class, $route) . ': Please select at least one';
	}
	
	
	/**
	 * Validates values against unique constraints
	 *
	 * @param  mixed  $class        The class name or instance of the class to check
	 * @param  string $column       The column to check
	 * @param  array  &$values      The values to check
	 * @param  array  &$old_values  The old values for the record
	 * @return string  A validation error message for the unique constraints
	 */
	static private function checkUniqueConstraints($class, $column, &$values, &$old_values)
	{
		$table = fORM::tablize($class);
		
		$key_info = fORMSchema::getInstance()->getKeys($table);
		
		$primary_keys = $key_info['primary'];
		$unique_keys  = $key_info['unique'];
		
		$exists = TRUE;
		foreach ($primary_keys as $primary_key) {
			if ((array_key_exists($primary_key, $old_values) && $old_values[$primary_key] === NULL) || $values[$primary_key] === NULL) {
				$exists = FALSE;
			}
		}
		
		foreach ($unique_keys AS $unique_columns) {
			if (in_array($column, $unique_columns)) {
				$sql = "SELECT " . join(', ', $key_info['primary']) . " FROM " . $table . " WHERE ";
				$column_num = 0;
				foreach ($unique_columns as $unique_column) {
					if ($column_num) { $sql .= " AND "; }
					$sql .= $unique_column . fORMDatabase::prepareBySchema($table, $unique_column, $values[$unique_column], '=');
					$column_num++;
				}
				
				if ($exists) {
					$sql .= ' AND (';
					$first = TRUE;
					foreach ($primary_keys as $primary_key) {
						$sql .= ($first && !$first = FALSE) ? '' : ' AND '; 
						$sql .= $table . '.' . $primary_key . fORMDatabase::prepareBySchema($table, $primary_key, $values[$primary_key], '<>');	
					}
					$sql .= ')';
				}
				
				try {
					$result = fORMDatabase::getInstance()->translatedQuery($sql);
					$result->tossIfNoResults();
				
					// If an exception was not throw, we have existing values
					$column_names = '';
					$column_num = 0;
					foreach ($unique_columns as $unique_column) {
						if ($column_num) { $column_names .= ', '; }
						$column_names .= fORM::getColumnName($class, $unique_column);
						$column_num++;
					}
					return $column_names . ': The values specified must be a unique combination, but the specified combination already exists';
				
				} catch (fNoResultsException $e) { }
			}
		}
	}
	
	
	/**
	 * Makes sure each rule array is set to at least an empty array
	 *
	 * @internal
	 * 
	 * @param  mixed  $class  The class name or an instance of the class to initilize the arrays for
	 * @return void
	 */
	static private function initializeRuleArrays($class)
	{
		self::$conditional_validation_rules[$class]         = (isset(self::$conditional_validation_rules[$class]))         ? self::$conditional_validation_rules[$class]         : array();
		self::$one_or_more_validation_rules[$class]         = (isset(self::$one_or_more_validation_rules[$class]))         ? self::$one_or_more_validation_rules[$class]         : array();
		self::$only_one_validation_rules[$class]            = (isset(self::$only_one_validation_rules[$class]))            ? self::$only_one_validation_rules[$class]            : array();
		self::$related_one_or_more_validation_rules[$class] = (isset(self::$related_one_or_more_validation_rules[$class])) ? self::$related_one_or_more_validation_rules[$class] : array();
	}
	
	
	/**
	 * Reorders list items in an html string based on their contents
	 * 
	 * @internal
	 * 
	 * @param  mixed $class                 The class name or an instance of the class to reorder messages for                 
	 * @param  array &$validation_messages  An array of one validation message per value
	 * @return void
	 */
	static public function reorderMessages($class, &$validation_messages)
	{
		$class = fORM::getClassName($class);
		
		if (!isset(self::$message_orders[$class])) {
			return;
		}
			
		$matches = self::$message_orders[$class];
		
		$ordered_items = array_fill(0, sizeof($matches), array());
		$other_items   = array();
		
		foreach ($validation_messages as $validation_message) {
			foreach ($matches as $num => $match_string) {
				if (strpos($validation_message, $match_string) !== FALSE) {
					$ordered_items[$num][] = $validation_message;
					continue 2;
				}
			}
			
			$other_items[] = $validation_message;
		}
		
		$final_list = array();
		foreach ($ordered_items as $ordered_item) {
			$final_list = array_merge($final_list, $ordered_item);
		}
		$validation_messages = array_merge($final_list, $other_items);
	}
	
	
	/**
	 * Allows setting the order that the list items in a validation message will be displayed
	 *
	 * @param  mixed $class    The class name or an instance of the class to set the message order for
	 * @param  array $matches  This should be an ordered array of strings. If a line contains the string it will be displayed in the relative order it occurs in this array.
	 * @return void
	 */
	static public function setMessageOrder($class, $matches)
	{
		$class = fORM::getClassName($class);
		self::$message_orders[$class] = $matches;
	}
	
	
	/**
	 * Validates values for an {@link fActiveRecord} object
	 *
	 * @internal
	 * 
	 * @param  mixed   $class       The class name or instance of the class to validate
	 * @param  array   $values      The values to validate
	 * @param  array   $old_values  The old values for the record
	 * @param  boolean $existing    If the record currently exists in the database
	 * @return array  An array of validation messages
	 */
	static public function validate($class, $values, $old_values)
	{
		$class = fORM::getClassName($class);
		$table = fORM::tablize($class);
		
		self::initializeRuleArrays($class);
		
		$validation_messages = array();
		
		// Convert objects into values for validation
		foreach ($values as $column => $value) {
			$values[$column] = fORM::scalarize($class, $column, $value);
		}
		foreach ($old_values as $column => $value) {
			$old_values[$column] = fORM::scalarize($class, $column, $value);
		}
		
		$message = self::checkPrimaryKeys($class, $values, $old_values);
		if ($message) { $validation_messages[] = $message; }
		
		$column_info = fORMSchema::getInstance()->getColumnInfo($table);
		foreach ($column_info as $column => $info) {
			$message = self::checkAgainstSchema($class, $column, $values, $old_values);
			if ($message) { $validation_messages[] = $message; }
		}
		
		foreach (self::$conditional_validation_rules[$class] as $rule) {
			$messages = self::checkConditionalRule($class, $values, $rule['main_column'], $rule['conditional_values'], $rule['conditional_columns']);
			if ($messages) { $validation_messages = array_merge($validation_messages, $messages); }
		}
		
		foreach (self::$one_or_more_validation_rules[$class] as $rule) {
			$message = self::checkOneOrMoreRule($class, $values, $rule['columns']);
			if ($message) { $validation_messages[] = $message; }
		}
		
		foreach (self::$only_one_validation_rules[$class] as $rule) {
			$message = self::checkOnlyOneRule($class, $values, $rule['columns']);
			if ($message) { $validation_messages[] = $message; }
		}
		
		foreach (self::$related_one_or_more_validation_rules[$class] as $related_class => $routes) {
			foreach ($routes as $route) {
				$message = self::checkRelatedOneOrMoreRule($class, $related_records, $related_class, $route);
				if ($message) { $validation_messages[] = $message; }
			}
		}
		
		return $validation_messages;
	}
	
	
	/**
	 * Validates related records for an {@link fActiveRecord} object
	 *
	 * @internal
	 * 
	 * @param  mixed $class             The class name or instance of the class to validate
	 * @param  array &$related_records  The related records to validate
	 * @return array  An array of validation messages
	 */
	static public function validateRelated($class, &$related_records)
	{
		$table = fORM::tablize($class);
		
		$validation_messages = array();
		
		// Find the record sets to validate
		foreach ($related_records as $related_table => $routes) {
			foreach ($routes as $route => $record_set) {
				if (!$record_set->isFlaggedForAssociation()) {
					continue;
				}
				
				$related_class = fORM::classize($related_table); 
				$relationship  = fORMSchema::getRoute($table, $related_table, $route);	
																												
				if (isset($relationship['join_table'])) {
					$related_messages = self::validateManyToMany($class, $related_class, $route, $record_set);	
				} else {
					$related_messages = self::validateOneToMany($class, $related_class, $route, $record_set);
				}
				
				$validation_messages = array_merge($validation_messages, $related_messages);
			}
		}
		
		return $validation_messages;	
	}
	
	
	/**
	 * Validates one-to-many related records
	 *
	 * @internal
	 * 
	 * @param  mixed      $class          The class name or instance of the class these records are related to
	 * @param  string     $related_class  The name of the class for this record set
	 * @param  string     $route          The route between the table and related table
	 * @param  fRecordSet $record_set     The related records to validate
	 * @return array  An array of validation messages
	 */
	static private function validateOneToMany($class, $related_class, $route, $record_set)
	{
		$table               = fORM::tablize($class);
		$related_table       = fORM::tablize($related_class);
		$route_name          = fORMSchema::getRouteName($table, $related_table, $route, 'one-to-many');
		$filter              = fORMRelated::determineRequestFilter($class, $related_class, $route);
		$related_record_name = fORMRelated::getRelatedRecordName($class, $related_class, $route);
		
		$record_number = 1;
		
		$messages = array();
		
		foreach ($record_set as $record) {
			fRequest::filter($filter, $record_number-1);
			$record_messages = $record->validate(TRUE);
			foreach ($record_messages as $record_message) {
				// Ignore validation messages about the primary key since it will be added 
				if (strpos($record_message, fORM::getColumnName($related_class, $route_name)) !== FALSE) {
					continue;	
				}
				$messages[] = $related_record_name . ' #' . $record_number . ' ' . $record_message;	
			}
			$record_number++;
			fRequest::unfilter();
		}	
		
		return $messages;	
	}
	
	
	/**
	 * Validates many-to-many related records
	 *
	 * @internal
	 * 
	 * @param  mixed      $class          The class name or instance of the class these records are related to
	 * @param  string     $related_class  The name of the class for this record set
	 * @param  string     $route          The route between the table and related table
	 * @param  fRecordSet $record_set     The related records to validate
	 * @return array  An array of validation messages
	 */
	static private function validateManyToMany($class, $related_class, $route, $record_set)
	{
		$related_record_name = fORMRelated::getRelatedRecordName($class, $related_class, $route);
		$record_number = 1;
		
		$messages = array();
		
		foreach ($record_set as $record) {
			if (!$record->exists()) {
				$messages[] = $related_record_name . ' #' . $record_number . ': Please select a ' . $related_record_name;		
			}
			$record_number++;
		}	
		
		return $messages;	
	}
}



/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */