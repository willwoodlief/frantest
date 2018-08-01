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

	const DB_VERSION = 1.91;

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



		$installed_ver = floatval( get_option( "_fran_test_db_version" ));
		if ( Fran_Test_Activator::DB_VERSION > $installed_ver ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			$charset_collate = $wpdb->get_charset_collate();

			//do main survey table

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}fran_test_survey` (
              id int NOT NULL AUTO_INCREMENT,
              anon_key varchar(10) DEFAULT NULL,
              created_at datetime NOT NULL,
              completed_at datetime  NULL,
              is_completed int NOT NULL default 0,
              comments text default NULL,
              PRIMARY KEY  (id),
              UNIQUE    (anon_key),
              key (is_completed)
            ) $charset_collate;";


			dbDelta( $sql );

			//do questions table


			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}fran_test_questions` (
              id int NOT NULL AUTO_INCREMENT,
              question text not null,
              is_obsolete int NOT NULL default 0,
              answer_limit int default 0,
              created_at datetime NOT NULL,
              updated_at datetime default NULL,
              PRIMARY KEY  (id),
              KEY    (is_obsolete)
              ) $charset_collate;";

			dbDelta( $sql );



			//do answers table

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}fran_test_answers` (
              id int NOT NULL AUTO_INCREMENT,
              question_id int not null ,
              answer text not null,
              created_at datetime NOT NULL,
              PRIMARY KEY  (id),
              KEY  (question_id),
              CONSTRAINT  FOREIGN KEY fk_answer_has_question(question_id) REFERENCES {$wpdb->base_prefix}fran_test_questions(id)
                ON  UPDATE CASCADE 
                ON DELETE RESTRICT
              ) $charset_collate;";

			dbDelta( $sql );


			//do response table

			$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}fran_test_responses` (
              id int NOT NULL AUTO_INCREMENT,
              survey_id int not null ,
              question_id int not null ,
              answer_id int not null,
              PRIMARY KEY  (id),
              KEY (survey_id),
              KEY  (question_id),
              UNIQUE (survey_id,question_id),
              CONSTRAINT  FOREIGN KEY fk_response_has_answer(answer_id) REFERENCES {$wpdb->base_prefix}fran_test_answers(id)
                ON  UPDATE CASCADE 
                ON DELETE RESTRICT,
              CONSTRAINT  FOREIGN KEY fk_response_has_question(question_id) REFERENCES {$wpdb->base_prefix}fran_test_questions(id)
                ON  UPDATE CASCADE 
                ON DELETE RESTRICT,
              CONSTRAINT  FOREIGN KEY fk_response_has_survey(survey_id) REFERENCES {$wpdb->base_prefix}fran_test_survey(id)
                ON  UPDATE CASCADE 
                ON DELETE RESTRICT  
              ) $charset_collate;";

			dbDelta( $sql );
			update_option( '_fran_test_db_version', Fran_Test_Activator::DB_VERSION );
		}


		$installed_questions_ver = floatval(get_option( "_fran_test_question_version" ));
		$questions = Fran_Test_Activator::load_questions_from_yaml();
		if ($questions['version'] > $installed_questions_ver) {
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
					$da_words = $q_node['question'];
					$da_limit = $q_node['limit'];

					$last_id = $wpdb->insert(
						$wpdb->base_prefix . 'fran_test_questions',
						array(
							'question' => $da_words,
							'answer_limit'      => $da_limit,
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
					foreach ( $answers as $an_answer ) {
						$last_id = $wpdb->insert(
							$wpdb->base_prefix . 'fran_test_answers',
							array(
								'question_id' => $new_question_id,
								'answer'      => $an_answer,
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
							throw new Exception( "Could not create new Answer" );
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
