<?php
	/*
	*	Commodum database ( CoDB ) library, tiny DB manipulation wrapper
	*	Copyright (C) 2013 customhost.com.ua
	*	
	*	This program is free software; you can redistribute it and/or modify
	*	it under the terms of the GNU General Public License as published by
	*	the Free Software Foundation; either version 2 of the License, or
	*	(at your option) any later version.
	*	
	*	This program is distributed in the hope that it will be useful,
	*	but WITHOUT ANY WARRANTY; without even the implied warranty of
	*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	*	GNU General Public License for more details.
	*
	*	You should have received a copy of the GNU General Public License along
	*	with this program; if not, write to the Free Software Foundation, Inc.,
	*	51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
	*/
	
	
	/** 
	*	@author customhost.com.ua
	*	@copyright 2013 customhost.com.ua
	*	@license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
	*	
	*/

	abstract class codb {
		
		/** connection error, message template */
		const CODB_ERROR_CONNECTION = "Connection error <strong>%s line %d</strong>: %s";
		
		/** DB-link error, message template */
		const CODB_ERROR_NO_LINK = "Cannot execute query, no database link ( <strong>query called from %s line %d</strong> )";
		
		/** query execution error, message template */
		const CODB_ERROR_EXECUTE = "Cannot execute SQL-query at the <strong>%s line %d</strong>: %s";
		
		/** CODB_ERROR_QUERY_FAILED query failed error, message template */
		const CODB_ERROR_QUERY_FAILED = "Query failed ( <strong>query called from %s line %d</strong> ): %s";
		
		
		/** engine mismatch error, message template */
		const CODB_ERROR_ENGINE_MISMATCH = "Cannot perform action ( Called from <strong>%s line %d</strong> ), dabatase engine mismatch: %s";
		
		/** no table fields error, message template */
		const CODB_ERROR_NO_TABLE_FIELDS = "Table has no fields ( Called from <strong>%s line %d</strong> ), omitting table: `%s`";
		
		/** table exists error, message template ( for create table ) */
		const CODB_ERROR_TABLE_EXISTS = "Can't create table ( Called from <strong>%s line %d</strong> ), table exists: `%s`";
		
		/** table create error, message template */
		const CODB_ERROR_TABLE_CREATE = "Can't create table ( Called from <strong>%s line %d</strong> ): %s ( table: %s  )";
		
		/** alter table error, message template */
		const CODB_ERROR_TABLE_ALTER = "Can't alter table ( Called from <strong>%s line %d</strong> ): %s ( table: %s  )";
		
		/** table index create error, message template */
		const CODB_ERROR_INDEX_CREATE = "Can't create table indexes ( Called from <strong>%s line %d</strong> ): %s ( table: %s  ); Query: ";
		
		/** insert table data error, message template */
		const CODB_ERROR_INSERT_DATA = "Unable to pre-populate table ( Called from <strong>%s line %d</strong> ): %s ( table: %s  ); Query: ";
		
		
		private $initialized = false;
		protected $database_link;
		
		protected $database_user = '';
		protected $database_passwd = '';
		protected $database_host = 'localhost';
		protected $database_db = '';
		protected $database_prefix = '';
		protected $database_charset = '';
		protected $error_stack = array();
		
		protected $available_languages = array();
		protected $current_language = '';
		
		protected $is_query_failed = false;
		protected $affected_rows = 0;
		protected $last_inserted_id = 0;
		
		protected $error_log = array();
		
		protected $use_backtracing = true;
		protected $backtrace_stub = array( "file" => "unknown file", "line" => "unknown line" );
		
		
		
		/* ========================== Main static methods ========================== */
		
		/** Create and return DB instance
		*
		*	Create and return DB instance based on defined DBMS-engine and connection types. 
		*	
		*	This function must be used only to get initialized CoDB instance.
		*	<b>For e.g.:</b> If you are using MySQL you must provide mysql in this parameter. You can get all available DBMS engines by using codb::getAvailableDBMS\endlink method
		*	
		*	There are only two required parameters: database engine and database name.
		*	For file-based DBs ( such as SQLite ) you need provide only engine and full path to the database file
		*
		*	@param string $database_engine DBMS-engine name to use
		*	@param string $define_database Database name or file which been used after initialization or when connection was established
		*	@param string $define_user Database user name
		*	@param string $define_passwd Database passwors
		*	@param string $define_host Override config-default DB-host
		*	
		*	<a href="http://php.net/manual/en/features.persistent-connections.php">Persistent Database Connections</a>
		*/
		protected static function create( $database_engine, $define_database, $define_user = null, $define_passwd = null, $define_host = 'localhost' ) {
			
			$engine_file = dirname(__FILE__) . "/engine/{$database_engine}.engine.php";
			$engine_class = "codb_{$database_engine}";
			
			if( file_exists( $engine_file ) ) {
				include_once( $engine_file );
				
				if( empty( $define_host ) || !is_string( $define_host ) ) {
					$define_host = 'localhost';
				}
				
				if( class_exists( $engine_class ) ) {
					$database_instance = new $engine_class( $define_database, $define_user, $define_passwd, $define_host );
				}
				else {
					trigger_error( "Database engine class {$engine_class} does not exists. Cannot create database instance",  E_USER_WARNING );
					$database_instance = false;
				}
			}
			 else {
			 	trigger_error( "Engine file {$engine_file} does not exists. Cannot create database instance",  E_USER_WARNING );
				$database_instance = false;
			}
			
			return $database_instance;
		}
		
		/** @ignore */
		public static function __callStatic( $name, $parameters ) {
			$engines = self::getAvailableDBMS();
			
			if( in_array( $name, $engines ) && is_array( $parameters ) ) {
				
				// add engine name for the front of parameters
				array_unshift( $parameters, $name );
				return call_user_func_array( array( 'codb', 'create' ), $parameters );
			}
			
			if( !in_array( $name, $engines ) ) {
				trigger_error( "Database engine {$name} is not supported, try another one: " . implode( ",", $engines ),  E_USER_WARNING );
			}
			else {
				trigger_error( "Wrong database parameters was passed.",  E_USER_WARNING );
			}
			return false;
		}
		
		/** Return available DBMS engines
		*
		* 	Check for currently available DBMS-related engines and return list of it
		*	@return mixed array with available DBMS-implemetations or false if no implemetations are available
		*/
		public static function getAvailableDBMS() {
			$engines = scandir( dirname(__FILE__) . "/engine/" );
			
			$available_DBMS = array();
			
			foreach( $engines as $entry ) {
				if( preg_match( "~\.engine\.php$~", $entry ) ) {
					$available_DBMS[] = str_replace( '.engine.php', '', $entry );
				}
			}
			
			return ( count( $available_DBMS ) ? $available_DBMS : false );
		}
		/* ========================== EO Main static methods ========================== */
		
		
		
		/* ========================== Basic class methods ========================== */
		/** Init internal variables and perform database connection
		*
		*	This method will take all necessary database-connection variables and store it into the CoDB instance.
		*	After that codb::init() method will call codb::connect() method for target engine implemetation to connect to the database and/or selected appropriate database
		*	
		*	@param string $define_database Database name to connect to
		*	@param string $define_user [optional] Database username to connect with
		*	@param string $define_passwd [optional] Password to use for connect to the database
		*	@param string $define_host [optional] Database host
		*/
		final public function init( $define_database, $define_user = null, $define_passwd = null, $define_host = 'localhost' ) {
			if( !$initialized ) {
				$initialized = true;
				
				$this->database_user = $define_user;
				$this->database_passwd = $define_passwd;
				$this->database_db = $define_database;
				$this->database_host = $define_host;
				
				$this->connect();
			}
			
			return $this;
		}
		
		/// Add new error to the error_stack
		final protected function addError( $error, $database_error = 'no addtional info', $table = '' ) {
			$this->is_query_failed = true;
			
			$_backtrace = ( $this->use_backtracing ? debug_backtrace() : $this->backtrace_stub );
			
			$_caller = $_backtrace[1];
			if( isset( $_backtrace[2] ) && $_caller['function'] == 'makeQuery' ) {
				$_caller = $_backtrace[2];
			}
			
			$this->error_stack[] = sprintf( $error, $_caller['file'], $_caller['line'], $database_error, $table );
		}
		
		/// Return internal variable $use_backtracing
		final public function getBacktraceState() {
			return $this->use_backtracing;
		}
		
		/// Set internal variable $use_backtracing
		final public function setBacktraceState( $turn_on ) {
			if( is_bool( $turn_on ) ) {
				$this->use_backtracing = $turn_on;
			}
			 else {
				$this->addError( "Cannot change <b>use_backtracing</b> property. '{$turn_on}' is not in a boolean type" );
			}
			
			return $this;
		}
		
		/// Set internal tables prefix
		final public function setDBPrefix( $prefix ) {
			if( !empty( $prefix ) ) {
				if( is_string( $prefix ) ) {
					$this->database_prefix = $define_prefix;
				}
				else {
					$this->addError( "Cannot set DB prefix -- not valid string parameter." );
				}
			}
			
			return $this;
		}
		
		/// Get tables prefix
		final public function getDBPrefix() {
			return $this->database_prefix;
		}
		
		/// Set internal variable $use_backtracing
		final public function setCharset( $codepage ) {
			if( is_string( $codepage ) && !empty( $codepage ) ) {
				$this->database_charset = $codepage;
				
				if( $this->database_link ) {
					$this->changeCharset( $codepage );
				}
			}
			else {
				$this->addError( "Cannot set DB charset — not valid string parameter." );
			}
			
			return $this;
		}
		
		/// Define available languages
		final public function setLanguages( $languages = array() ) {
			if( is_array( $languages ) && !empty( $languages ) ) {
				$this->available_languages = $languages;
			}
		}
		
		/// Define currently used languages
		final public function setCurrentLanguage( $language ) {
			if( !empty( $language ) ) {
				$this->current_language = $language;
			}
		}
		
		/// Return true is an error was occurred during previous query-execution
		final public function isError() {
			return $this->is_query_failed;
		}
		
		/// Return rows count been affected by query
		final public function affectedRows() {
			return $this->affected_rows;
		}
		
		/// Return last autoincrement key value which been
		final public function insertedID() {
			return $this->last_inserted_id;
		}
		
		/// Return last database error
		final public function getError( $plain_text = false ) {
			
			if( count( $this->error_stack ) ) {
				$keys = array_keys( $this->error_stack );
				$last_key = array_pop( $keys );
				$last_message = $this->error_stack[ $last_key ];
				
				return ( $plain_text ? strip_tags( $last_message ) : $last_message );
			}
			else {
			 	return false;
			}
		}
		
		/// Get whole error stack glued with the defined text
		final public function getAllErrors( $glue = "\n", $plain_text = false ) {
			
			if( count( $this->error_stack ) ) {
				if( $plain_text ) {
					foreach( $this->error_stack as $key => $value ) {
						$this->error_stack[ $key ] = strip_tags( $value );
					}
				}
				
				return implode( $glue, $this->error_stack );
			}
			else {
				return false;
			}
		}
		
		/// Process macros
		final protected function processMacros( $sql_query ) {
			if( is_string( $sql_query ) && !empty( $sql_query ) ) {
				$current_language = $this->current_language;
				if( !empty( $current_language ) ) {
					$current_language = "_{$current_language}";
				}
				
				$macros['src'] = array(
					'~codb_lang\(([\w]{1,})\)~',
					'~codb_table\(([\w_-]{1,})\)~',
				);
				
				$macros['result'] = array(
					"`\\1{$current_language}`",
					"`{$this->database_db}`.`{$this->database_prefix}\\1`",
				);
				
				/// All-languages field macros
				$all_lang_macros = array();
				if( strpos( $sql_query, 'codb_lang_all(' ) !== false ) {
					preg_match_all( '~codb_lang_all\(([\w]{1,})\)~', $sql_query,  $all_lang_macros );
					
					foreach( $all_lang_macros[1] as $macros_id => $all_lang_field ) {
						$langs_list = array();
						foreach( $this->available_languages as $lang ) {
							$langs_list[] = "`{$all_lang_field}_{$lang}`";
							
							if( $lang == $this->current_language ) {
								$langs_list[] = "`{$all_lang_field}_{$lang}` AS '{$all_lang_field}_current'";
							}
						}
						
						$fields_to_replace = implode( ', ', $langs_list );
						$sql_query = str_replace( $all_lang_macros[0][ $macros_id ], $fields_to_replace, $sql_query );
					}
				}
				
				/// Macro-processing
				$sql_query = preg_replace( $macros['src'], $macros['result'], $sql_query );
			}
			
			return $sql_query;
		}
		/* ========================== EO Basic class methods ========================== */
		
		
		
		/* ========================== Inline fields processing methods ========================== */
		
		/// Alias for escapeString()
		final public function quote( $raw_string ) {
			return $this->escapeString( $raw_string );
		}
		
		/// Cast to int
		final public function int( $value ) {
			if( !empty( $value ) ) {
				$value = intval( $value );
			}
			else {
				$value = 0;
			}
			
			// NULL will be converted to the empty string, msut avoid it
			if( !$value ) {
				$value = '0';
			}
			
			return $value;
		}
		
		/// Cast to float
		final public function float( $value ) {
			
			if( !empty( $value ) ) {
				if( is_string( $value ) ) {
					$value = str_replace( ",", '.', $value );
				}
				
				$value = strval( floatval( $value ) );
				
				// PHP return value with decimal separatarot based on locale if float-to-string conversion, must always be dot .
				$value = str_replace( ",", '.', $value );
			}
			else {
				$value = 0;
			}
			
			// NULL will be converted to the empty string, msut avoid it
			if( !$value ) {
				$value = '0';
			}
			
			return $value;
		}
		
		/* ========================== Inline fields processing methods ========================== */
		
		
		
		
		/* ========================== Abstract methods ========================== */
		/// Perform DB connection
		abstract protected function connect();
		
		/// Perform check for duplicated value within defined column
		abstract public function isDuplicated( $value, $field, $table );
		
		/// Change DB session charset with engine-specific way
		abstract public function changeCharset( $sql_query );
		
		/// Execute SQL-query. Return false on error or true if no errors
		abstract public function query( $sql_query );
		
		/// Return SQL query for LIMIT operatorm or it's analog
		abstract public function limit( $offset_count, $row_count = null );
		
		/// Perform SQL query, fetch associative array and return it or \c false on error
		abstract public function getAssoc( $sql_query, $use_array_key_as_primary_key = null, $group_by_primary_key = false, $use_array_key_as_child_key = null, $one_record_array_field = null );
				
		/// Perform SQL query and return one column. If SQL-result consist more than one column — will be returned first or defined in \c $return_field_name
		abstract public function getCol( $sql_query, $return_field_name = null, $use_array_key_as_primary_key = null );
		
		/// Perform SQL query and return one row. If SQL-result consist more than one column — first will be returned
		abstract public function getRow( $sql_query );
		
		/// Perform SQL query and return one record as the string. If SQL-result consist
		abstract public function getOne( $sql_query );
		
		/// Perform string escaping and return SQL-safe string
		abstract public function escapeString( $string );
		
		/// Trying to start transaction
		abstract public function transactionStart();
		
		/// Commit transaction
		abstract public function transactionCommit();
		
		/// Rollback transaction
		abstract public function transactionRollback();
		
		/// AMF-related function: Create tables using info-array and languages
		abstract public function createTables( $database_info, $languages );
		
		/// AMF-related function: Alter tables using info-array and languages
		abstract public function alterTables( $database_info, $languages );
		
		/// AMF-related function: Create table using table info fron XML and languages array
		abstract public function createTable( $database_info, $languages );
		
		/// AMF-related function: Alter table using table info fron XML and languages array
		abstract public function alterTable( $database_info, $languages );
		/* ========================== EO Abstract methods ========================== */
	}
/*
	/**	\page about About CoDB
	* 	\b CoDB — is a modular library which uses modules to provide database-specific extension calls and allows you work with all modern relative databases via the same public interfaces.\n
	*	For this purpose, main \ref codb class provide a strong framework with all necessary methods, which every particular engine must implemeted for particular database engine.\n
	*	
	*	CoDB was developed by Artem Oliynyk as an alternative to the fat and excessive dabatase wrapper libraries ( such as PEAR DB or MDB2 ) for the internal usage of Customhost team.\n
	*	First project where are CoDB was used is a proprietary modular framework developed by Customhost.\n
	*	Lately, \ref codb was used for other project, as well and recommedec itself as a good small tool for project of any size.
	*	
	*	The main idea of codb is to allow programmers get rid of code which repeats again and again, but keep standard querying model as on native PHP.\n
	*	Unlike the ORM libraries, \b codb doesn't allow you to treat database as an object. This way imply that the developer must know SQL languages to work with SQL databases.\n
	*
	*	\section about-features Main features
	*	CoDB has a clean and easy interface to work with DB results.\n
	*	Every method of CoDB will return standard PHP type, but not the database resousce.\n
	*	
	*	CoDB has built-in functionality to work with multi-lingual tables and table prefixes, implemeted as a macros.\n
	*	Also, CoDB allows you to convert variable types and escape string which will be used in query. And you can use it within the string directly, without any concatenation.\n
	*	
	*	\section about-samples Library samples
	*	Connect to the database with a defined engine and parameters.\n
	*	This sample show us how to the MySQL database named \c statistic on the remote host \c mysql-db.example.net with given user name and password
	*	\code{.php}
	*	codb::mysql( 'statistic', 'username', 'my$passwors', 'mysql-db.example.net' );
	*	\endcode
	*	
	*	\n
	*	CoDB codb::init method has only one required parameter which must be always passed, all other can be omited according to the init method
	*	\code{.php}
	*	final public function init( $define_database, $define_user = null, $define_passwd = null, $define_host = 'localhost' ) {}
	*	\endcode
	*	
	*	\n
	*	That's mean you can call init method without some parameters.\n
	*	This example show us how we can connect to the dabatase using only dabatase name and username
	*	\code{.php}
	*	codb::mysqli( 'cacti', 'debian-maint' );
	*	\endcode
	*	
	*	\n
	*	Some database engines allows you to connect without username, passwors and engine, just by using path to the database file.\n
	*	For this reason first parameter is for \c database and you can use it for such engine as SQLite as well as for PostgreSQL or MSSQL.
	*	\code{.php}
	*	codb::sqlite3( '/var/www/example.com/hosting-panel/private/user.db' );
	*	\endcode
	*	
	*	\n
	*	You can get query result as an array by calling only one method.
	*	\code{.php}
	*	$db = codb::mysql( 'statistic', 'username', 'my$passwors', 'mysql-db.example.net' ); // connect to the database
	*	$result = $db->getAssoc( 'SELECT * FROM `users_stat` WHERE `user_id` = 1' ); // get results
	*	\endcode
	*	
	*	\n
	*	You can also use macros for the automatic tables prefix and built-in methods to convert and/or quote requests data
	*	\code{.php}
	*	$db = codb::mysql( 'statistic', 'username', 'my$passwors', 'mysql-db.example.net' ); // connect to the database
	*	$db->setDBPrefix( 'host2_' )->setCharset( 'UTF8' ); // define tables prefix and set charset to the UTF8
	*	
	*	$users = $db->getAssoc( "SELECT * FROM codb_table(user_sessions) WHERE `user_id` = {$db->int( $_GET['user'] )} AND `status` = '{$db->quote( $_GET['stat'] )}'" ); // get result with filters
	*	\endcode
	*	
	*	\n
	*	Last line with SQL code from the sample above is equivalent to the next code:
	*	\code{.php}
	*	$users = $db->getAssoc( "SELECT * FROM `host2_user_sessions`
	*			WHERE `user_id` = " . intval( $_GET['user'] ) ."
	*			AND `status` = '" . mysql_real_escape_string( $_GET['stat'] ) . "'" );
	*	\endcode
	*/
	
/*	
	/** \page architecture CoDB Architecture
	*
	*	\b CoDB using object model with inheritance and abstract methods.\n
	*	Main abstract class \ref codb provides a framework for engines implemetation.\n
	*	This class can be extended by engine implemetation ( "plugins" ) to provide engine-specific methods to work with DB.\n
	*	
	*	Database engine implemetation a is single file located in \b engine directory and defines one class which must extend parent class
	*	
	*	For Example, MySQLi plugin which implemets <a href="http://php.net/manual/en/book.mysqli.php">MySQLi Extension</a> methods must be located in <b>engine/mysqli.engine.php</b> file with such declaration:
	*	 \code{.php}
	*	 class codb_mysqli extends codb { }
	*	 \endcode
	*	
	*	Plugin class must implemet engine specific call for native PHP library to provide unified interfaces for different database engines.
	*	
	*
	*	\section arch-methods Primary methods to work with database
	*	CoDB provide three methods' types:
	*	\li Query methods — methods which work directly with DB
	*	\li Informational methods — methods to configure \ref codb instance or get addtional information
	*	\li Helpers
	*
	*	
	*	\subsection arch-methods-info Informational methods
	*	Informational methods allows you to configure your \b codb instance in a run-time, get last query status, last inserted ID ( for tables with autoincrement key-field), check for errors, change charset, languages configuration, etc.\n
	*	
	*	Informational methods:
	*	\li codb::getAvailableDBMS() — returns list ( array ) of engines currently available to use with \b CoDB
	*	\li codb::getBacktraceState() — returns internal flag value of \b $use_backtracing
	*	\li codb::getError() — returns last error
	*	\li codb::getAllErrors() — returns all error ( entrine error stack )
	*	\li codb::isDuplicated() — perform check for duplicated value within defined column and return duplicate status
	*	\li codb::isError() — returns last query status
	*	\li codb::affectedRows() — returns numbers of rows was affected by last executed query
	*	\li codb::insertedID() — returns last inserted ID ( for table with autoincrement primary field )
	*	
	*	Configuration methods:
	*	\li codb::setBacktraceState() — enable or disable backtracing
	*	\li codb::setDBPrefix() — defines default prefix for tables within database
	*	\li codb::setCharset() — set connection charset
	*	\li codb::setLanguages() — defines available languages
	*	\li codb::setCurrentLanguage() — set default language
	*	\li codb::changeCharset() — change default charset
	*	
	*	
	*	\subsection arch-methods-helpers Helpers
	*	Helpers is similar to the informational but they returns SQL-well values and must be used widthin query ( for e.g.: type-casting )
	*	\li codb::escapeString() — escape value and return SQL-safe string
	*	\li codb::quote() — alias for codb::escapeString()
	*	\li codb::int() — cast given value and return \b integer
	*	\li codb::float() — cast given value and return \b float
	*	
	*	
	*	\subsection arch-methods-query Query methods
	*	Query methods provides interface to get data from tables, execute queries or work with transaction ( only for database engines which are support transactions )\n
	*	
	*	Query methods:
	*	\li codb::query() — perform query and return query status ( \b true or \b false )
	*	\li codb::getAssoc() — perform query and fetch query result as an associative array
	*	\li codb::getCol() — perform query and fetch result as a list ( column ).
	*	\li codb::getRow() — perform query and fetch result as a list ( row ).
	*	\li codb::getOne() — perform query and return one fetched value
	*	
	*	Transaction methods:
	*	\li codb::transactionStart() — start transaction
	*	\li codb::transactionCommit() — commit ( apply ) transaction
	*	\li codb::transactionRollback() — rollback ( cancel ) transaction
	*/
