<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/includes
 */
use Symfony\Component\Yaml\Yaml;
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Fran_Test
 * @subpackage Fran_Test/includes
 * @author     Your Name <email@example.com>
 */
class Fran_Test_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */

	const DB_VERSION = 2.7;

	/**
	 * @return array
	 */
	public static function load_questions_from_yaml() {
		/** @noinspection PhpIncludeInspection */
		require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';

		$db_folder = plugin_dir_path(dirname(__FILE__)) . 'db/';
		$data = Yaml::parseFile($db_folder.'questions.yaml');
		return $data;

	}

	/**
	 * @throws Exception
	 */
	public static function activate() {
		global $wpdb;


		//check to see if any tables are missing
		$b_force_create = false;
		$tables_to_check= ['fran_test_survey','fran_test_responses','fran_test_questions','fran_test_answers'];
		foreach ($tables_to_check as $tb) {
			$table_name = "{$wpdb->base_prefix}$tb";
			//check if table exists
			if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
				$b_force_create = true;
			}
		}

		$b_force_load_questions = false;
		//if questions table not there yet, flag specifically so we can load in the questions
		$table_name = "{$wpdb->base_prefix}fran_test_questions";
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			$b_force_load_questions = true;
		}


		$installed_ver = floatval( get_option( "_fran_test_db_version" ));
		if ( ($b_force_create) || ( Fran_Test_Activator::DB_VERSION > $installed_ver) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			$charset_collate = $wpdb->get_charset_collate();

			//do main survey table

			$sql = "CREATE TABLE `{$wpdb->base_prefix}fran_test_survey` (
              id int NOT NULL AUTO_INCREMENT,
              anon_key varchar(10) DEFAULT NULL,
              created_at datetime NOT NULL,
              completed_at datetime NULL,
              is_completed int NOT NULL default 0,
              survey_email varchar(80) default NULL,
              first_name text default NULL,
              last_name text default NULL,
              phone text default NULL,
              comments text default NULL,
              PRIMARY KEY  (id),
              UNIQUE KEY anon_key (anon_key),
              key is_completed_key (is_completed),
              key email_key (survey_email)
            ) $charset_collate;";


			dbDelta( $sql );

			//do questions table


			$sql = "CREATE TABLE `{$wpdb->base_prefix}fran_test_questions` (
              id int NOT NULL AUTO_INCREMENT,
              is_obsolete int NOT NULL default 0,
              answer_limit int default 0,
              created_at datetime NOT NULL,
              updated_at datetime default NULL,
              shortcode varchar(50),
              question text not null,
              PRIMARY KEY  (id),
              KEY is_obsolete_key (is_obsolete),
              KEY shortcode_key (shortcode)
              ) $charset_collate;";

			dbDelta( $sql );



			//do answers table

			$sql = "CREATE TABLE `{$wpdb->base_prefix}fran_test_answers` (
              id int NOT NULL AUTO_INCREMENT,
              question_id int not null ,
              answer text not null,
              created_at datetime NOT NULL,
              PRIMARY KEY  (id),
              KEY question_id_key (question_id)
              ) $charset_collate;";

			dbDelta( $sql );


			//do response table

			$sql = "CREATE TABLE `{$wpdb->base_prefix}fran_test_responses` (
              id int NOT NULL AUTO_INCREMENT,
              survey_id int not null ,
              question_id int not null ,
              answer_id int not null,
              PRIMARY KEY  (id),
              KEY survey_id_key (survey_id),
              KEY question_id_key (question_id),
              KEY answer_id_key (answer_id),
              UNIQUE KEY unique_survey_question (survey_id,question_id)
              ) $charset_collate;";

			dbDelta( $sql );
			update_option( '_fran_test_db_version', Fran_Test_Activator::DB_VERSION );
		}


		$installed_questions_ver = floatval(get_option( "_fran_test_question_version" ));
		$questions = Fran_Test_Activator::load_questions_from_yaml();
		if ($b_force_load_questions || ($questions['version'] > $installed_questions_ver)) {
			// if the questions version is greater than the last question version then mark the older questions as obsolete
			try {
				$wpdb->query('START TRANSACTION');

				$wpdb->query( /** @lang text */
					"UPDATE `{$wpdb->base_prefix}fran_test_questions` SET 
		            updated_at = NOW(),
		            is_obsolete = 1
		            WHERE is_obsolete = 0
	            ");

				if ( $wpdb->last_error ) {
					throw new Exception( 'issue updating the old questions: ' . $wpdb->last_error );
				}
				//add in new questions

				$questions_array = $questions['questions'];
				foreach ($questions_array as $q_node ) {
					if (!isset($q_node['question'])) {
						throw new Exception("questions.yaml missing the question in the format designed");
					}
					$da_words = $q_node['question'];
					$da_limit = isset($q_node['limit']) ? $q_node['limit'] : 1;
					$ladeda_shortcode = isset($q_node['shortcode']) ? $q_node['shortcode'] : 'none';

					$last_id = $wpdb->insert(
						$wpdb->base_prefix . 'fran_test_questions',
						array(
							'question' => $da_words,
							'answer_limit'      => $da_limit,
							'shortcode' => $ladeda_shortcode,
							'created_at'  => date( "Y-m-d H:m:s", time() )
						),
						array(
							'%s',
							'%s',
							'%s'
						)
					);

					if ( $wpdb->last_error ) {
						throw new Exception( $wpdb->last_error );
					}

					if ( $last_id === false ) {
						throw new Exception( "Could not create new Question" );
					}
					$new_question_id = $wpdb->insert_id;

					$answers = $q_node['answers'];
					if (!empty($answers)) {
						foreach ( $answers as $an_answer ) {
							$last_id = $wpdb->insert(
								$wpdb->base_prefix . 'fran_test_answers',
								array(
									'question_id' => $new_question_id,
									'answer'      => $an_answer,
									'created_at'  => date( "Y-m-d H:m:s", time() ),
								),
								array(
									'%s',
									'%s',
									'%s'
								)
							);

							if ( $wpdb->last_error ) {
								throw new Exception( $wpdb->last_error );
							}

							if ( $last_id === false ) {
								throw new Exception( "Could not create new Answer" );
							}
						}
					}
				}
				$wpdb->query('COMMIT');
				update_option( '_fran_test_question_version', $questions['version'] );
			}
			catch (Exception $e) {
				$wpdb->query('ROLLBACK');
				throw $e;
			}


		}







	}



}
