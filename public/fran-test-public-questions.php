<?php
/** @noinspection PhpIncludeInspection */

require_once plugin_dir_path( __DIR__). "vendor/autoload.php";
require_once plugin_dir_path( __DIR__). "lib/CurlHelper.php";
require_once plugin_dir_path( __DIR__). "admin/fran-test-survey-backend.php";

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
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $email
	 * @param string $phone
	 * @param $hubspot_response
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function update_words($survey_id,$first_name, $last_name,$email,$phone,&$hubspot_response) {
		global $wpdb;
		$survey_table_name = $wpdb->prefix . 'fran_test_survey';

		$update_args = [
			'survey_email' => $email,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'phone' => $phone,
			'is_completed' => 1,
			'completed_at_ts' => time()
		];

		$b_check = $wpdb->update(
			$survey_table_name,
			$update_args,
			array( 'id' => $survey_id )
		);

		if ( $wpdb->last_error ) {
			throw new Exception( $wpdb->last_error );
		}

		$hubspot_response = FranTestPublic::connect_to_hubspot($survey_id,$email,$first_name,$last_name,$phone);

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
			/** @noinspection SqlResolve */
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
//		if ($res[0]->is_completed) {
	//		throw new SurveyNotFoundException("Survey is completed");
//		}
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
				'created_at_ts' => time()
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

	/**
	 * @param integer $survey_id
	 * @param string $email
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $phone
	 *
	 * @return null|\SevenShores\Hubspot\Http\Response
	 * @throws
	 */
	protected static function connect_to_hubspot($survey_id,$email,$first_name,$last_name,$phone) {

		$options = get_option( 'fran_test_options' );
		$api_key = $options['hubspot_api'];
		if (!$api_key) {
			return null;
		}

		if (empty($email)) {
			return null;
		}

		//Check if contact exists

		$hubspot = new SevenShores\Hubspot\Factory([
			'key'      => $api_key,
			'oauth'    => false, // default
			'base_url' => 'https://api.hubapi.com' // default
		]);

		$contact = null;
		try {
			$contact = $hubspot->contacts()->getByEmail($email);
		}
		/** @noinspection PhpRedundantCatchClauseInspection */
		catch (SevenShores\Hubspot\Exceptions\BadRequest $e) {
			$msg = $e->getMessage();
			if (strpos($msg, 'contact does not exist') !== false) {
				$contact = null;
			} else {
				throw $e;
			}
		}

		$survey_summary_html = self::get_form_info($survey_id,true);
		$survey_summary_text = self::get_form_info($survey_id,false);

		$updates = [
			['property' => 'lifecyclestage', 'value' => 'subscriber']
		];

	//	$updates[] = ['property' => 'quiz_results', 'value' => "test test !"];

		if ($contact) {
			if ((!isset($contact->properties->firstname)) && (!empty(trim($first_name)))) {
				$updates[] = ['property' => 'firstname', 'value' => trim($first_name)];
			}

			if ((!isset($contact->properties->lastname)) && (!empty(trim($last_name)))) {
				$updates[] = ['property' => 'lastname', 'value' => trim($last_name)];
			}

			if ((!isset($contact->properties->phone) ) && (!empty(trim($phone)))) {
				$updates[] = ['property' => 'phone', 'value' => trim($phone)];
			}
		} else {
			if ((!isset($contact->properties->firstname)) && (!empty(trim($first_name)))) {
				$updates[] = ['property' => 'firstname', 'value' => trim($first_name)];
			}

			if ((!isset($contact->properties->lastname)) && (!empty(trim($last_name)))) {
				$updates[] = ['property' => 'lastname', 'value' => trim($last_name)];
			}

			if ((!isset($contact->properties->phone) ) && (!empty(trim($phone)))) {
				$updates[] = ['property' => 'phone', 'value' => trim($phone)];
			}
		}



		$client = new SevenShores\Hubspot\Http\Client(['key' => $api_key]);
		$contacts = new SevenShores\Hubspot\Resources\Contacts($client);

		$result = $contacts->createOrUpdate($email, $updates);
		$vid = $result->data->vid; //5201
		$engagements = new \SevenShores\Hubspot\Resources\Engagements($client);
		//make note
		$response = $engagements->create([
			"active" => true,
			"ownerId" => 1,
			"type" => "NOTE"
		], [
			"contactIds" => [$vid],
			"companyIds" => [],
			"dealIds" => [],
			"ownerIds" => [],
		], [
			'body' => $survey_summary_html, //only br, anchor tags, and header tags are supported
		]);

		$options = get_option( 'fran_test_options' );
		$emails = $options['email_alerts'];
		$headers = array('Content-Type: text/html; charset=UTF-8');
		if( $emails && sizeof($emails) > 0) {

			$b_what = wp_mail( $emails, "Survey Finished by $first_name $last_name" , $survey_summary_html, $headers );
			if (!$b_what) {
				$email_string = implode(", ",$emails);
				error_log("Cannot email to $email_string . The survey made by $first_name $last_name $email    !");
			}

		}
		return $response;



	}



	/**
	 *
	 * @param $survey_id integer
	 * @param bool $b_html, default false <p>
	 *    if true, then newlines are converted to br tags
	 * </p>
	 *
	 * @return string <p>
	 *   format of string:
	 *    first line is Survey completed at [time_date]
	 *    second line is first_name,last_name
	 *    next line is survey_email
	 *    next line is phone
	 *    next line is seconds to complete
	 *    next line is id,anon_key
	 *    then the answers and questions one pair per line
	 *    fourth line: Questions:
     *      for each question: the question => the answer
	 * </p>
	 * @throws Exception
	 */
	protected static function get_form_info($survey_id,$b_html = false) {
		//returns a string of the form info

		/**
		 * @var $info array
		 * *   array [
		 *          survey=>[id,anon_key,first_name,last_name,survey_email,phone,
		 *                      is_completed,completed_at_ts,created_at_ts],
		 *          answers => array of [question,question_id,answer,answer_id,response_id,shortcode]
		 *  ]
		 */
		$info =  FranSurveyBackend::get_details_of_one(intval($survey_id));
		$first_name = $info['survey']->first_name;
		$last_name = $info['survey']->last_name;
		$survey_email = $info['survey']->survey_email;
		$phone = $info['survey']->phone;
		$id = $info['survey']->id;
		$anon_key = $info['survey']->anon_key;
		$completed_at = $info['survey']->completed_at_ts;
		$created_at = $info['survey']->created_at_ts;

		$lines = [];

		$timezone = new DateTimeZone('America/New_York');
		$format = "l F j Y, g:i a";
		if (empty($completed_at)) {
			$dt = new DateTime("@$created_at",$timezone);
			$dt->setTimezone($timezone);
			$when = $dt->format($format);
			if ($b_html) {
				$lines[] = "<h1>Survey Started</h1> <h3>$when</h3> <h4>But Not Completed</h4>";
			} else {
				$lines[] = "Survey Started at $when, but not completed";
			}

		} else {
			$dt = new DateTime("@$completed_at",$timezone);
			$dt->setTimezone($timezone);
			$when = $dt->format($format);
			if ($b_html) {
				$lines[] = "<h1>Survey completed</h1> <h3>$when</h3>"; //todo look at timezones
			} else {
				$lines[] = "Survey completed at $when";
			}

		}



		$seconds_to_complete = null;
		if ($completed_at && $created_at) {
			$seconds_to_complete = intval($completed_at) - intval($created_at);
		}

		if ($b_html) {
			$lines[] = '<h2>Information</h2>';
		} else {
			$lines[] = 'Information';
		}

		$lines[] = "$first_name $last_name ";
		$lines[] = "$survey_email";
		$lines[] = "$phone";
		if ($seconds_to_complete) {
			$lines[]  = "Seconds to Complete: $seconds_to_complete";
		}
		$lines[] = "Internal Survey Tracking: $id  $anon_key";

		$lines[] = ''; //line break
		if ($b_html) {
			$lines[] = '<h2>Questions</h2>';
		} else {
			$lines[] = 'Questions';
		}

		foreach ($info['answers'] as $question_info) {
			$question = $question_info['question'];
			$answer =  $question_info['answer'];
			$lines[] = "$question => $answer";
		}



		if ($b_html) {
			return implode('<br/>',$lines);
		} else {
			return implode("\n",$lines);
		}

	}

	//example of submitting a form, not used here, or even all the way implemented,
	// just in case its needed later
	/**
	 * @throws CurlHelperException
	 */
	protected function send_form() {

		$email = $firstname = $lastname = $phonenumber = '';
		//Process a new form submission in HubSpot in order to create a new Contact.

		$hubspotutk      = $_COOKIE['hubspotutk']; //grab the cookie from the visitors browser.
		$ip_addr         = $_SERVER['REMOTE_ADDR']; //IP address too.
		$hs_context      = array(
			'hutk' => $hubspotutk,
			'ipAddress' => $ip_addr,
			'pageUrl' => 'http://www.example.com/form-page',
			'pageName' => 'Example Title'
		);
		$hs_context_json = json_encode($hs_context);

		$fields = [
			'firstname' =>$firstname,
			'lastname'=>$lastname,
			'email' =>$email,
			'phone'=>$phonenumber,
			'hs_context'=> $hs_context_json
		];


//replace the values in this URL with your portal ID and your form GUID
		$endpoint = 'https://forms.hubspot.com/uploads/form/v2/{portalId}/{formGuid}';
		//send as form url encoded
		$ch = new CurlHelper();
		$ch->upload_file_and_data($endpoint,[],$fields);

	}
}