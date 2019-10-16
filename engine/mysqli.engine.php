<?php
	/*
	*	Commodum database ( CoDB ) library, tiny DB manipulation wrapper
	*
	*	MIT License
	*	Copyright (C) 2013 Artem Oliynyk <https://artemoliynyk.com>
	*
	*	Permission is hereby granted, free of charge, to any person obtaining a copy
	*	of this software and associated documentation files (the "Software"), to deal
	*	in the Software without restriction, including without limitation the rights
	*	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	*	copies of the Software, and to permit persons to whom the Software is
	*	furnished to do so, subject to the following conditions:
	*
	*	The above copyright notice and this permission notice shall be included in all
	*	copies or substantial portions of the Software.
	*
	*	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	*	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	*	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	*	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	*	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	*	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
	*	SOFTWARE.
	*/


	/**
	*	CODB :: MySQLi engine
	*	Commodum DB - is advanced lihtweight object DataBase engine wrapper
	*/

	/**	\file mysqli.engine.php
	*	\brief <b>Commodum DB — MySQLi-extension interface implementation</b> \n
	*	MySQLi API bind for CODB, advanced lightweight object DataBase engine wrapper \n
	*/

	class codb_mysqli extends codb {

		const engine = 'mysqli';

		/// Constructon gets database connection options ( optional ) and initialize private members.
		public function __construct( $define_database, $define_user = null, $define_passwd = null, $define_host = 'localhost' ) {
			$this->database_user = $define_user;
			$this->database_passwd = $define_passwd;
			$this->database_db = $define_database;
			$this->database_host = $define_host;

			$this->connect();
		}

		private function makeQuery( $sql_query ) {
			if( !$this->database_link ) {
				return false;
			}

			$this->is_query_failed = false;
			$this->affected_rows = 0;
			$this->last_inserted_id = 0;

			$sql_query = $this->processMacros( $sql_query );

			if( $this->database_link ) {
				$sql = $this->database_link->query( $sql_query );

				$this->affected_rows = $this->database_link->affected_rows;

				if( $sql ) {

					if( $this->affected_rows > 0 ) {
						$this->last_inserted_id = $this->database_link->insert_id;
					}

					return $sql;
				}
				else {
					$this->addError( codb::CODB_ERROR_QUERY_FAILED, $this->database_link->error );

					return false;
				}
			}
			 else {
				$this->addError( codb::CODB_ERROR_NO_LINK );
				return false;
			}
		}

		/** Perform connection to the database using the defined parameters
		*
		*/
		protected function connect() {
			$this->database_link = new mysqli( $this->database_host, $this->database_user, $this->database_passwd, $this->database_db );

			if( $this->database_link->connect_error ) {
				$this->addError( codb::CODB_ERROR_CONNECTION, "using {$this->database_user}@{$this->database_host}" );

				trigger_error( "Make sure you have MySQL-user {$this->database_user}, execute this query to create: CREATE USER '{$this->database_user}'@'localhost' IDENTIFIED BY '<user-password>';", E_USER_WARNING );

				trigger_error( "Make sure database {$this->database_db} exists, execute this query to create it: CREATE DATABASE `{$this->database_db}` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;", E_USER_WARNING );

				trigger_error( "Make sure that MySQL-user {$this->database_user} has rights to access database {$this->database_db}, execute this query to grant permissions: GRANT ALL PRIVILEGES ON `{$this->database_db}` . * TO '{$this->database_user}'@'localhost' WITH GRANT OPTION;", E_USER_ERROR );

				unset( $this );
			}

			return $this->database_link != false;
		}

		/// Change DB session charset with engine-specific way
		public function changeCharset( $charset ) {
			$charset = $this->quote( $charset );
			$this->query( "SET NAMES '{$charset}'" );
		}

		/// Execute SQL-query. Return false on error or true if no errors
		public function query( $sql_query ) {
			$sql = $this->makeQuery( $sql_query );
			return $sql !== false;
		}

		/// Return SQL query for LIMIT operatorm or it's analog
		public function limit( $offset_count, $row_count = null ) {
			$limit_stm = '';

			$offset_count = intval( $offset_count );
			$row_count = intval( $row_count );

			if( $offset_count >= 0 ) {
				$limit_stm = "LIMIT {$offset_count}";

				if( !is_null( $row_count ) && $row_count > 0 ) {
					$limit_stm .= ", {$row_count}";
				}
			}

			return $limit_stm;
		}

		/// Perform SQL query fetch associative array and return it or false on error
		public function getAssoc( $sql_query, $use_array_key_as_primary_key = null, $group_by_primary_key = false, $use_array_key_as_child_key = null, $one_record_array_field = null ) {
			$sql = $this->makeQuery( $sql_query );

			if( $sql ) {
				$is_key_found = $is_child_key_found = false;
				$stop_key_searching = $stop_child_key_searching = false;
				$return_array = array();

				while( $tmp_data = $sql->fetch_assoc() ) {

					/// If defined \c $one_record_array_field and such key exists in array — use it as a array value otherwise -- use whole array
					if( !empty( $one_record_array_field ) && isset( $tmp_data[ $one_record_array_field ] ) ) {
						$final_array_value = $tmp_data[ $one_record_array_field ];
					}
					else {
						$final_array_value = $tmp_data;
					}

					/// Looking for the primary key in fetched data ( if $use_array_key_as_primary_key was defined )
					if( !empty($use_array_key_as_primary_key) && !$stop_key_searching && !$is_key_found ) {
						$stop_key_searching = true; /// Search for key only one time
						$is_key_found = isset( $tmp_data[ $use_array_key_as_primary_key ] );
					}

					/// Looking for the child key in fetched data ( if data will be grouped by primary key and $use_array_key_as_child_key was defined )
					if( !empty($use_array_key_as_child_key) && $group_by_primary_key && !$stop_child_key_searching && !$is_child_key_found ) {
						$stop_child_key_searching = true; /// Search for key only one time
						$is_child_key_found = isset( $tmp_data[ $use_array_key_as_child_key ] );
					}

					if( $is_key_found ) {
						/// Grouping data using $use_array_key_as_primary_key as group id

						if( $group_by_primary_key ) {
							/// If $use_array_key_as_child_key exists in fetched data --- use it for the value key
							if( $is_child_key_found ) {
								$return_array[ $tmp_data[ $use_array_key_as_primary_key ] ][ $tmp_data[ $use_array_key_as_child_key ] ] = $final_array_value;
							}
							else {
								$return_array[ $tmp_data[ $use_array_key_as_primary_key ] ][] = $final_array_value;
							}
						}
						else {
							$return_array[ $tmp_data[ $use_array_key_as_primary_key ] ] = $final_array_value;
						}
					}
					else {
						$return_array[] = $final_array_value;
					}
				}

				return $return_array;
			}
			else {
				return false;
			}
		}

		/// Perform SQL query and return one column. If SQL-query consist more than one column — will be returned first or defined in $return_field_name
		public function getCol( $sql_query, $return_field_name = null, $use_array_key_as_primary_key = null ) {
			$sql = $this->makeQuery( $sql_query );

			if( $sql ) {
				$is_key_found = $is_field_name_found = false;
				$stop_key_searching = $stop_field_name_searching = false;
				$return_array = array();

				while( $tmp_row = $sql->fetch_assoc() ) {

					/// Looking for the field name ( to be returned ) in fetched data ( if $return_field_name was defined )
					if( !empty( $return_field_name ) && !$stop_field_name_searching && !$is_field_name_found ) {
						$is_field_name_found = isset( $tmp_row[ $return_field_name ] );  /// Search for key only one time
					}

					/// Looking for the primary key in fetched data ( if $use_array_key_as_primary_key was defined )
					if( !empty( $use_array_key_as_primary_key ) && !$stop_key_searching && !$is_key_found ) {
						$is_key_found = isset( $tmp_row[ $use_array_key_as_primary_key ] );  /// Search for key only one time
					}

					if( empty($return_field_name) || !$is_field_name_found ) {
						$return_tmp_val = array_shift( $tmp_row );	/// If field name defined in $return_field_name was not found --- return first array element
					}
					else {
						$return_tmp_val = $tmp_row[ $return_field_name ];	/// Otherwise return field defined in $return_field_name
					}

					if( !$is_key_found ) {
						$return_array[] = $return_tmp_val;	/// If no $use_array_key_as_primary_key was found --- add new value sequential
					}
					else {
						$return_array[ $tmp_row[ $use_array_key_as_primary_key ] ] = $return_tmp_val;	/// Otherwise use value with key $use_array_key_as_primary_key as the return value key
					}
				}

				return $return_array;
			}
			else {
				return false;
			}
		}

		/// Perform SQL query and return one row. If SQL-query consist more than one column — first will be returned
		public function getRow( $sql_query ) {
			$sql = $this->makeQuery( $sql_query );

			if( $sql ) {
				$return_array = $sql->fetch_assoc();


				if( !$return_array ) {
					return array();
				}

				return $return_array;
			}
			else {
				return false;
			}
		}

		/// Perform SQL query and return one record as the string. If SQL-result contains more then one field --- will be returned firts or $return_field_name field
		public function getOne( $sql_query, $return_field_name = null ) {

			$sql = $this->makeQuery( $sql_query );

			if( $sql ) {
				$return_array = $sql->fetch_assoc();

				$field_found = ( isset( $return_array[ $return_field_name ] ) && count( $return_array ) > 1 );

				if( empty( $return_field_name ) && !$field_found && !empty( $return_array ) ) {
					return array_shift( $return_array );
				}
				elseif( !empty( $return_array ) ) {
					return $return_array[ $return_field_name ];
				}
				 else {
					return array();
				}
			}
			else {
				return false;
			}
		}

		/// Perform string escaping and return SQL-safe string
		public function escapeString( $raw_string ) {
			if( !$this->database_link ) {
				return false;
			}

			return $this->database_link->real_escape_string( $raw_string );
		}

		/// Perform check for ducpicated value within defined column
		public function isDuplicated( $value, $field, $table ) {
			return false !== $this->getOne( "SELECT `{$field}` FROM codb_table({$table}) WHERE `{$field}` = '{$this->escapeString( $value )}'" );
		}

		private function buildFieldOptions( $field_info ) {
			$options = array(
				'type' => $field_info->type,
				'length_values' => '',
				'default' => '',
				'charset' => '',
				'collate' => '',
				'primary_key' => '',
				'signed' => '',
				'zerofill' => '',
				'binary' => '',
				'null' => '',
				'autoincrement' => '',
			);

			if(  !empty( $field_info->{'length-values'} )  ) {
				$options['length_values'] = "( {$field_info->{'length-values'}} )";
			}

			if(  !empty( $field_info->default )  ) {
				$default = $this->escapeString( $field_info->default );
				$options['default'] = "DEFAULT '{$default}'";
			}

			if(  !empty( $field_info->charset )  ) {
				$options['charset'] = "CHARACTER SET {$field_info->charset}";
			}

			if(  !empty( $field_info->collate )  ) {
				$options['collate'] = "COLLATE {$field_info->collate}";
			}

			if(  isset( $field_info->{'primary-key'} )  ) {
				$options['primary_key'] = "PRIMARY KEY";
			}

			if(  isset( $field_info->autoincrement )  ) {
				$options['autoincrement'] = "AUTO_INCREMENT";
			}

			if(  isset( $field_info->null )  ) {
				$options['null'] = "NULL";
			}
			else {
				$options['null'] = "NOT NULL";
			}

			if(  isset( $field_info->signed ) ) {
				$options['signed'] = "SIGNED";
			}
			elseif( isset( $field_info->unsigned ) ) {
				$options['signed'] = "UNSIGNED";
			}

			if(  isset( $field_info->zerofill )  ) {
				$options['zerofill'] = "ZEROFILL";
			}

			if(  isset( $field_info->binary )  ) {
				$options['binary'] = "BINARY";
			}

			if( isset( $field_info->comment ) && !empty( $field_info->comment ) ) {
				$options['comment'] = "COMMENT '{$this->escapeString( $field_info->comment )}'";
			}

			return implode( "\x20", $options );
		}

		/// AMF-related function: Create all tables from info-array using createTable() method
		public function createTables( $database_info, $languages ) {
			if( !$this->database_link ) {
				return false;
			}

			$ok = true;
			if( $database_info['type'] == self::engine || $database_info['type'] == str_replace( 'sqli', 'sql', self::engine ) ) {
				foreach( $database_info->tables->children() as $table ) {
					$table =  self::createTable( $table, $languages );
					$ok = $ok && $table;
				}

				return $ok;
			}
			else {
				$this->addError( codb::CODB_ERROR_ENGINE_MISMATCH, $database_info['type'] );
				return false;
			}
		}

		/// AMF-related function: Alter table using info-array and languages
		public function alterTables( $database_info, $languages ) {
			if( !$this->database_link ) {
				return false;
			}

			$fail = false;

			if( $database_info['type'] == self::engine || $database_info['type'] == str_replace( 'sqli', 'sql', self::engine ) ) {

				foreach( $database_info->tables->children() as $table ) {
					if( !self::alterTable( $table, $languages ) && !$fail ) {
						$fail = true;
					}
				}
			}
			else {
				$this->addError( codb::CODB_ERROR_ENGINE_MISMATCH, $database_info['type'] );
			}

			return ( $fail ? false : true );
		}

		/// AMF-related function: Create table using info-array
		public function createTable( $table, $languages ) {
			$table_exists = $this->getOne( "SHOW TABLES LIKE '{$table['name']}'" );

			$table_fields_natural_order = array();

			if( $table->fields && count( $table->fields->children() ) && !$table_exists ) {
				$charset = ( !empty( $table->options->charset ) ? "DEFAULT CHARSET = {$table->options->charset}" : '' );
				$collate = ( !empty( $table->options->collate ) ? "DEFAULT COLLATE = {$table->options->collate}" : '' );

				$fields = array();
				$multilang_fields = array();
				foreach( $table->fields->children() as $field ) {
					$field_name = trim( strval( $field->name ) );

					/// Save all table fields, ordered as they defined in XML
					$table_fields_natural_order[] = $field_name;

					/// Creating option string for the field ( type, length, comments, etc. )
					$options = $this->buildFieldOptions( $field );

					if( isset( $field->{'multi-lingual'} ) ) {
						$multilang_fields[] = $field_name;

						foreach( $languages as $lang ) {
							$fields[] = "`{$field_name}_{$lang}` {$options}";
						}
					}
					else {
						$fields[] = "`{$field_name}` {$options}";
					}
				}

				$field_string = implode( ",\n\t", $fields );

				$create_sql = "CREATE TABLE codb_table({$table['name']}) ( \n\t{$field_string}\n ) ENGINE = {$table->options->engine}  {$charset}  {$collate};";

				$create_res = $this->query( $create_sql );

				if( !$create_res ) {
					$this->addError( codb::CODB_ERROR_TABLE_CREATE, $table['name'], $this->database_link->error, $table['name'] );
					return false;
				}

				/// Creating table indexes in exists
				if( count( $table->indexes->children() ) ) {

					/// Looping into the index nodes
					foreach( $table->indexes->children() as $index_type => $indexes ) {

						/// Define index type
						$index_type = ( $index_type == 'index' ? "INDEX" : "{$index_type} INDEX" );

						foreach( $indexes->children() as $node_type => $index_field ) {

							$index_fields_arr = array();

							/// If index contains more than one field ( group index )
							if( $node_type == 'index-group' ) {
								$index_name = trim( strval( $index_field['group-name'] ) );

								/// Looping through the index fields and store
								foreach( $index_field->children() as $group_index_field ) {
									$idx_length = '';

									if( !empty( $group_index_field['length'] ) ) {
										$idx_length = "( {$group_index_field['length']} )";
									}

									if( in_array( $group_index_field['field'], $multilang_fields ) ) {
										foreach( $languages as $lang ) {
											$index_fields_arr[] = "`{$group_index_field['field']}_{$lang}` {$idx_length}";
										}
									}
									else {
										$index_fields_arr[] = "`{$group_index_field['field']}` {$idx_length}";
									}
								}
							}
							/// Single field index
							else {
								$index_name = trim( strval( $index_field['name'] ) );

								$idx_length = '';

								if( !empty( $index_field['length'] ) ) {
									$idx_length = "( {$index_field['length']} )";
								}

								/// Create multi-language fields as a separated indexes, not group. To prevent max. index length exceed
								if( in_array( $index_field['field'], $multilang_fields ) ) {
									foreach( $languages as $lang ) {
										$index_field_name = "{$index_field['field']}_{$lang}";
										$index_field_title = "{$index_name}_{$lang}";

										$index_query = "CREATE {$index_type} `{$index_field_title}` ON codb_table({$table['name']}) ( `{$index_field_name}` {$idx_length} )";
										$create_index_res = $this->query( $index_query );

										if( !$create_index_res ) {
											$this->addError( codb::CODB_ERROR_INDEX_CREATE . $index_query, $this->database_link->error, $table['name'] );
// 											return false;
										}
									}
								}
								else {
									$index_fields_arr[] = "`{$index_field['field']}` {$idx_length}";
								}
							}

							if( !in_array( $index_field['field'], $multilang_fields ) ) {
								$fields_to_index = implode( ", ", $index_fields_arr );
								$indexes_query = "CREATE {$index_type} `{$index_name}` ON codb_table({$table['name']}) ( $fields_to_index )";

								$create_indexes_res = $this->query( $indexes_query );

								if( !$create_indexes_res ) {
									$this->addError( codb::CODB_ERROR_INDEX_CREATE . $indexes_query, $this->database_link->error, $table['name'] );
// 									return false;
								}
							}
						}
					}
				}

				/// Trying pre-populate table
				$this->fillTables( $table, $multilang_fields );
			}
			elseif( !$table->fields || !count( $table->fields->children() ) ) {
				$this->addError( codb::CODB_ERROR_NO_TABLE_FIELDS, $table['name'] );
				return false;
			}
			elseif( $table_exists ) {
				$this->addError( codb::CODB_ERROR_TABLE_EXISTS, $table['name'] );
				return false;
			}

			return true;
		}

		public function alterTable( $table, $languages ) {
			/// TODO Adding insufficient indexes creating indexes on new fields, "SHOW INDEXES FROM `TABLE`"

			$table_name = trim( strval( $table['name'] ) );
			$table_exists = $this->getOne( "SHOW TABLES LIKE '{$table_name}'" );

			if( $table_exists ) {
				$current_fields = $this->getCol( "DESCRIBE codb_table({$table_name})", 'Field', 'Field' );

				if( isset( $table->{'alter-rules'} ) && count( $table->{'alter-rules'}->children() ) ) {
					foreach( $table->{'alter-rules'}->children() as $alter_action => $alter_data ) {

						switch( $alter_action ) {
							case 'rename-field':
								$fields_structure = $this->getRow( "SHOW COLUMNS FROM codb_table({$table_name}) LIKE '{$alter_data['target']}'" );

								if( !empty( $fields_structure['Default'] ) ) {
									$default_val = $this->escapeString( $fields_structure['Default'] );
									$default = "DEFAULT {$default_val}";
								}
								else {
									$default = '';
								}

								$null = 'NULL';
								if( mb_strtolower( $fields_structure['Null'] ) == 'no' ) {
									$null = 'NOT NULL';
								}

								$alter_sql = "ALTER TABLE codb_table({$table_name}) CHANGE COLUMN `{$alter_data['target']}` `{$alter_data['value']}` {$fields_structure['Type']} {$fields_structure['Extra']} {$default} {$null}";
							break;

							case 'delete-field':
								$alter_sql = "ALTER TABLE codb_table({$table_name}) DROP COLUMN `{$alter_data['target']}`";
							break;

							case 'alter-field':
								$alter_options = $this->buildFieldOptions( $alter_data->children() );
								$alter_sql = "ALTER TABLE codb_table({$table_name}) MODIFY COLUMN `{$alter_data['target']}` {$alter_options}";
							break;

							case 'drop-index':
								$alter_sql = "DROP INDEX {$alter_data['target']} ON codb_table({$table_name})";
							break;

							default:
								$alter_sql = false;
						}

						if( $alter_sql ) {
							$alter_status = $this->query( $alter_sql );

							if( !$alter_status ) {
								$this->addError( codb::CODB_ERROR_TABLE_ALTER, $table_name, $this->database_link->error, $table_name );
								return false;
							}
						}
					}
				}

				$multilang_fields = $new_fields = $all_fields_info = array();
				if( $table->fields && count( $table->fields->children() ) && $table_exists ) {
					foreach( $table->fields->children() as $new_field_info ) {
						$new_field_name = trim( strval( $new_field_info->name ) );

						$all_fields_info[ $new_field_name ] = $new_field_info;

						if( in_array( $new_field_name, $current_fields ) || isset( $new_field_info->{'multi-lingual'} ) ) {

							if( isset( $new_field_info->{'multi-lingual'} ) ) {
								$multilang_fields[] = $new_field_name;
							}
						}
						else {
							$new_fields[] = $new_field_name;
						}
					}

					if( count( $new_fields ) ) {
						foreach( $new_fields as $field_name ) {
							$options = $this->buildFieldOptions( $all_fields_info[ $field_name ] );

							$alter_status = $this->query( "ALTER TABLE codb_table({$table_name}) ADD `{$field_name}` {$options}" );

							if( !$alter_status ) {
								$this->addError( codb::CODB_ERROR_TABLE_ALTER, $table_name, $this->database_link->error, $table_name );
								return false;
							}
						}
					}

					if( count( $multilang_fields ) ) {

						$fields_seq = array_keys( $all_fields_info );

						foreach( $multilang_fields as $field_name ) {
							foreach( $languages as $lang ) {
								if( !in_array( "{$field_name}_{$lang}", $current_fields ) ) {

									$alter_position = '';

									$curren_idx = array_search( $field_name, $fields_seq );
									$prev_idx = $curren_idx -1;

									if( $curren_idx === 0 ) {
										$alter_position = 'FIRST';
									}

									if( $curren_idx !== 0 && isset( $fields_seq[ $prev_idx ] ) ) {
										$prev_field_name = $fields_seq[ $prev_idx ];

										if( isset( $current_fields[ $prev_field_name ] ) ) {
											$alter_position = "AFTER {$prev_field_name}";
										}
									}

									$options = $this->buildFieldOptions( $all_fields_info[ $field_name ] );

									$alter_status = $this->query( "ALTER TABLE codb_table({$table_name}) ADD `{$field_name}_{$lang}` {$options} {$alter_position}" );
									if( !$alter_status ) {
										$this->addError( codb::CODB_ERROR_TABLE_ALTER, $table_name, $this->database_link->error, $table_name );
										return false;
									}
								}
							}
						}
					}
				}

				return true;
			}
		}


		/// AMF-related function: Insert default values to the table
		public function fillTables( $table_xml_structure, $multilang_fields = array() ) {
			$children = $table_xml_structure->data->children();

			/// If pre-populate data was defined
			if( $table_xml_structure->data && !empty( $children ) && count( $children ) ) {
				foreach( $children as $data ) {

					$named_fields = null;
					$field_values_array = array();

					foreach( $data->children() as $field ) {
						$field_value = strval( $field ); ///< Field value to insert

						/// Check for the named fields ( if first field has name -- we decide that all fields are named )
						if( $named_fields === null ) {
							$named_fields = !empty( $field['name'] );
						}

						/// If named fields are in use
						if( $named_fields ) {
							$field_name = trim( strval( $field['name'] ) ); ///< Field name

							if( in_array( $field_name, $multilang_fields ) ) {
								foreach( $this->available_languages as $lang ) {
									$field_values_array[] = "`{$field_name}_{$lang}` = '{$this->escapeString( $field_value )}'";
								}
							}
							else {
								$field_values_array[] = "`{$field_name}` = '{$this->escapeString( $field_value )}'";
							}
						}
						else {
							$field_values_array[] = "'{$this->escapeString( $field_value )}'";
						}
					}


					$insert_values = implode( ", ", $field_values_array );

					if( $named_fields ) {
						$inser_sql = "INSERT INTO codb_table({$table_xml_structure['name']}) SET {$insert_values}";
					}
					else {
						$fields_names_arr = array_slice( $table_fields_natural_order, 0, count( $data->children() ) );
						$fields_names = implode( ", ", $fields_names_arr );

						$inser_sql = "INSERT INTO codb_table({$table_xml_structure['name']}) ( {$fields_names} ) VALUES( {$insert_values} )";
					}

					if( !$this->query( $inser_sql ) ) {
						$this->addError( codb::CODB_ERROR_INSERT_DATA, $table_xml_structure['name'] );
					}
				}
			}
		}

		/// Trying to start transaction
		public function transactionStart() {
			$this->database_link->autocommit( false );
		}

		/// Commit transaction
		public function transactionCommit() {
			$this->database_link>commit();
			$this->database_link->autocommit( true );
		}

		/// Rollback transaction
		public function transactionRollback() {
			$this->database_link->rollback();
			$this->database_link->autocommit( true );
		}
	}

?>