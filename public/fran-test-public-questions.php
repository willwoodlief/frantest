<?php
/** @noinspection PhpIncludeInspection */

require_once plugin_dir_path( __DIR__). "vendor/autoload.php";

use Hashids\Hashids;

class FranTestPublic {

	var $survey_id = null;


	/**
	 * FranSurvey constructor.
	 * Creates a new survey and adds helper methods
	 *

	 * @param $anon_key string -- need this to access
	 *
	 * @throws
	 */
	public function __construct( $anon_key = null ) {
		$this->survey_id = $this->create_new_survey($anon_key);
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function get_survey_questions() {
		global $wpdb;

		$answer_table_name = $wpdb->prefix . 'fran_test_answers';
		$question_table_name = $wpdb->prefix . 'fran_test_questions';


		/** @noinspection SqlResolve */
		$res = $wpdb->get_results("
										        select
										       q.question,
										       a.answer
										from $question_table_name q
										
										inner join $answer_table_name a on a.question_id = q.id
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
				$node = ['question' => $row->question, 'answers'=> []];
				$last_question = $row->question;
			}
			$node['answers'][]= ['words' =>$row->answer,'color'=>$next_color];
			$color_index++;
			if ($color_index === sizeof($colors)) {
				$color_index = 0;
			}
			$next_color = $colors[$color_index];
		}

		return $ret;

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
		return $last_id;
	}
}