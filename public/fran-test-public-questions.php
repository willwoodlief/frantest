<?php
/** @noinspection PhpIncludeInspection */

require_once plugin_dir_path( __DIR__). "vendor/autoload.php";
class SurveyNotFoundException extends Exception{}

use Hashids\Hashids;


class FranTestPublic {

	var $survey_id = null;
	var $anon_key = null;

	/**
	 * FranSurvey constructor.
	 * Creates a new survey and adds helper methods
	 *

	 * @param $survey_key string -- need this to access
	 *
	 * @throws
	 */
	public function __construct( $survey_key = null ) {

		if ($survey_key) {
			try {
				$this->survey_id = $this->open_survey($survey_key);
			} catch (SurveyNotFoundException $hmm) {
				$this->survey_id = $this->create_new_survey(null);
			}

		} else {
			$this->survey_id = $this->create_new_survey(null);
		}

	}

	/**
	 * @param integer $survey_id
	 * @param string $name
	 * @param string $email
	 * @param string $phone
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function update_words($survey_id,$name,$email,$phone) {
		global $wpdb;
		$survey_table_name = $wpdb->prefix . 'fran_test_survey';

		$update_args = [
			'survey_email' => $email,
			'full_name' => $name,
			'phone' => $phone,
			'is_completed' => 1,
			'completed_at' => date("Y-m-d H:m:s", time())
		];

		$b_check = $wpdb->update(
			$survey_table_name,
			$update_args,
			array( 'id' => $survey_id )
		);

		if ( $wpdb->last_error ) {
			throw new Exception( $wpdb->last_error );
		}

		return $b_check;
	}

	/**
	 * @param $survey_id
	 * @param $question_id
	 * @param $answer_id
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function update_answer($survey_id,$question_id,$answer_id) {
		global $wpdb;
		$response_table_name = $wpdb->prefix . 'fran_test_responses';

		/** @noinspection SqlResolve */
		$sql = "INSERT INTO $response_table_name (survey_id,question_id,answer_id) VALUES (%d,%d,%d) ON DUPLICATE KEY UPDATE answer_id = %s";
		$sql = $wpdb->prepare($sql,$survey_id,$question_id,$answer_id,$answer_id);
		$wpdb->query($sql);
		if ($wpdb->last_error) {
			throw new Exception($wpdb->last_error );
		}
		 $last_id = $wpdb->insert_id;
		if ($last_id === 0) {
			//get the real id
			$sql = $wpdb->prepare("select  q.id								      
										from $response_table_name q
										where survey_id = %d and question_id = %d and answer_id = %d",
				$survey_id,$question_id,$answer_id);
			$res = $wpdb->get_results($sql );
			if (empty($res)) {
				throw new Exception("Could not get response from database");
			}
			$last_id = $res[0]->id;
		}

		return $last_id;
	}


	/**
	 * @return array
	 * @throws Exception
	 */
	public function get_survey_questions() {
		global $wpdb;

		$response_table_name = $wpdb->prefix . 'fran_test_responses';
		$answer_table_name = $wpdb->prefix . 'fran_test_answers';
		$question_table_name = $wpdb->prefix . 'fran_test_questions';


		/** @noinspection SqlResolve */
		$res = $wpdb->get_results("
										        select
										       q.question,
										       q.id as question_id,
										       q.shortcode,
										       a.answer,
										       a.id as answer_id,
										       r.answer_id as response_answer
										from $question_table_name q
										
										left join $answer_table_name a on a.question_id = q.id
										left join $response_table_name r on r.question_id = q.id and r.survey_id = {$this->survey_id}  
										where q.is_obsolete = 0
										order by q.id,a.id;
        ");

		if ($wpdb->last_error) {
			throw new Exception($wpdb->last_error );
		}

		$ret = [];

		$last_question = null;
		$node = null;

		$options = get_option( 'fran_test_options' );
		$set_colors = $options['allowed_colors'];
		$default_colors = ['green','red','orange','purple','blue','pink','grey','brown'];
		if (!$set_colors) {
			$colors = $default_colors;
		} else {
			$color_count = sizeof($set_colors);
			$colors_more  = array_slice($default_colors,$color_count,sizeof($default_colors));
			$colors = array_merge($set_colors,$colors_more);
		}

		$color_index = 0;
		$next_color = $colors[$color_index];
		foreach ($res as $row) {
			if ($row->question !== $last_question) {
				if($node) {
					$ret[] = $node;
				}
				$node = ['question' => $row->question, 'answers'=> [],
				         'response' => $row->response_answer,'shortcode' => $row->shortcode,
							'survey_id' => $this->survey_id];
				$last_question = $row->question;
			}
			$node['answers'][]= ['words' =>$row->answer,'color'=>$next_color,
			                     'answer_id' => $row->answer_id,'question_id'=> $row->question_id,
			                     'shortcode' => $row->shortcode];
			$color_index++;
			if ($color_index === sizeof($colors)) {
				$color_index = 0;
			}
			$next_color = $colors[$color_index];
		}
		$ret[] = $node;
		return $ret;

	}

	/**
	 * @param string $survey_key
	 *
	 * @return integer
	 * @throws SurveyNotFoundException,
	 * @throws Exception
	 */
	protected function open_survey($survey_key) {
		global $wpdb;
		$survey_table_name = $wpdb->prefix . 'fran_test_survey';
		/** @noinspection SqlResolve */
		$res = $wpdb->get_results(

			$wpdb->prepare(
			"select id,anon_key,is_completed from $survey_table_name  s
			 where   s.anon_key = %s;",$survey_key)
		);

		if ($wpdb->last_error) {
			throw new Exception($wpdb->last_error );
		}
		if (empty($res)) {
			throw new SurveyNotFoundException("Cannot find survey by this key ". $survey_key);
		}
		if ($res[0]->is_completed) {
	//		throw new SurveyNotFoundException("Survey is completed");
		}
		$this->anon_key = $res[0]->anon_key;
		return intval($res[0]->id);
	}
	/**
	 * @param $anon_key
	 * @return integer
	 * @throws Exception
	 */
	protected function create_new_survey($anon_key = null)
	{
		global $wpdb;
		if (!$anon_key) {
			$anon_key = null;
		}


		$table_name = $wpdb->prefix . 'fran_test_survey';
		$last_id = $wpdb->insert(
			$table_name,
			array(
				'anon_key' => $anon_key,
				'created_at' => date("Y-m-d H:m:s", time())
			),
			array(
				'%s',
				'%s',
				'%s'
			)
		);

		if ($last_id === false) {
			throw new Exception("Could not create new Survey");
		}
		$last_id = $wpdb->insert_id;

		if (is_null($anon_key)) {
			$hashids = new Hashids('Salt and Pepper for Fran Test',6,'abcdefghijkmnpqrstuvwxyz');
			$anon_key = $hashids->encode($last_id);


			$b_check = $wpdb->update(
				$table_name,
				['anon_key' => $anon_key],
				array( 'id' => $last_id )
			);

			if ( $wpdb->last_error ) {
				throw new Exception( $wpdb->last_error );
			}

			if (!$b_check) {
				throw new Exception("Could not update a null anon key");
			}



		}

		$this->anon_key = $anon_key;
		return $last_id;
	}
}