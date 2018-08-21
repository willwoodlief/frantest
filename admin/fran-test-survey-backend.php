<?php
class FranSurveyBackend
{
    /**
     * gets min, max,avg of the important fields see sql statement for the names
     * @return object
     * @throws
     */
    public static function get_stats_array() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fran_test_survey';
        /** @noinspection SqlResolve */
        $res = $wpdb->get_results(
            " 
            select count(id) number_completed,
			min(UNIX_TIMESTAMP(created_at)) as min_created_at_ts, max(UNIX_TIMESTAMP(created_at)) as max_created_at_ts
            from $table_name where is_completed = 1;
            ");

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error );
        }
        return $res[0];

    }

	/**
	 * @return array
	 * @throws Exception
	 */
    public static function do_query_from_post() {
        if (array_key_exists( 'start_index',$_POST) ) {
            $start_index = intval($_POST['start_index']);
        } else {
            $start_index = null;
        }

        if (array_key_exists( 'limit',$_POST) ) {
            $limit = intval($_POST['limit']);
        } else {
            $limit = null;
        }

        if (array_key_exists( 'sort_by',$_POST) ) {
            $sort_by = $_POST['sort_by'];
        } else {
            $sort_by = null;
        }

        if (array_key_exists( 'sort_direction',$_POST) ) {
            $sort_direction = intval($_POST['sort_direction']);
        } else {
            $sort_direction = null;
        }

        if (array_key_exists( 'search_column',$_POST) ) {
            $search_column = $_POST['search_column'];
        } else {
            $search_column = null;
        }

        if (array_key_exists( 'search_value',$_POST) ) {
            $search_value = $_POST['search_value'];
        } else {
            $search_value = null;
        }

        return FranSurveyBackend::get_search_results_array($start_index,$limit,$sort_by,
            $sort_direction,$search_column,$search_value);
    }

    /**
     * @param $start_index
     * @param $limit
     * @param $sort_by
     * @param $sort_direction
     * @param $search_column
     * @param $search_value
     * @return array
     * @throws Exception
     */
    public static function get_search_results_array($start_index,$limit,$sort_by,
                                                    $sort_direction, $search_column,$search_value) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'fran_test_survey';
        $responses_table = $wpdb->prefix . 'fran_test_responses';

        $where_clause = '';
        $search_value = trim($search_value);
        if (!empty($search_column) && !empty($search_value)) {
            $escaped_value = sanitize_text_field($search_value);
            switch ($search_column) {
                case 'anon_key':
                    $where_clause .= " AND ($search_column LIKE '%$escaped_value%' ) ";
            }
        }

        $sort_by_clause = " order by id asc";
        $sort_by = trim($sort_by);
        $sort_direction = intval($sort_direction);
        if ($sort_by === 'is_completed') {
        	$sort_by = 'number_completed';
        }
        if ($sort_by) {
            switch ($sort_by) {

	            case 'number_completed':
                case 'created_at':
	            case 'created_at_ts':
	            case 'survey_email':
                case 'anon_key': {
                     if ($sort_direction > 0) {
                         $sort_by_clause = " ORDER BY $sort_by ASC ";
                     } else {
                         $sort_by_clause = " ORDER BY $sort_by DESC ";
                     }
                     break;
                }
                default:
            }
        }


        $start_index = intval($start_index);
        $limit = intval($limit);
         if ($start_index > 0 && $limit > 0) {
             $offset_clause = "LIMIT $limit OFFSET $start_index";
         } elseif ($limit > 0) {
             $offset_clause = "LIMIT $limit";
         } elseif ($start_index > 0) {
             $offset_clause = "OFFSET $start_index";
         } else {
             $offset_clause = '';
         }


        //add in meta section of start and limit

        $res = $wpdb->get_results( /** @lang text */
                            "
                select id,anon_key, first_name,last_name,phone,survey_email,is_completed,
                  UNIX_TIMESTAMP(created_at) as created_at_ts,
                  (answer_count) as number_completed
                from $table_name s  
                INNER JOIN (
                 select count(*) as answer_count,survey_id from $responses_table a  group by survey_id 
                ) as completed ON completed.survey_id = s.id
                $where_clause $sort_by_clause  $offset_clause;"
        );

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error );
        }

         $meta = [
             'start_index'=>$start_index,
             'limit'=>$limit,
             'sort_by'=>$sort_by,
             'sort_direction'=>$sort_direction,
             'search_column'=>$search_column,
             'search_value'=>$search_value
         ];
        return ['meta'=>$meta,'results'=>$res];

    }

    /**
     * @param $survey_id
     * @return array|false <p>
     *   array [
     *          survey=>[id,anon_key,created_at,first_name,last_name,survey_email,phone,
     *                      completed_at,is_completed,created_at_ts,completed_at_ts],
     *          answers => array of [question,question_id,answer,answer_id,response_id,shortcode]
     *  ]
     * </p>
     * @throws Exception
     */
    public static function get_details_of_one($survey_id) {
        global $wpdb;
        $survey_table_name = $wpdb->prefix . 'fran_test_survey';
        $response_table_name = $wpdb->prefix . 'fran_test_responses';
        $answer_table_name = $wpdb->prefix . 'fran_test_answers';
        $question_table_name = $wpdb->prefix . 'fran_test_questions';
        $survey_id = intval($survey_id);

        /** @noinspection SqlResolve */
        $survey_res = $wpdb->get_results("
        select id,anon_key,created_at,completed_at,is_completed,first_name,last_name,survey_email,phone,
        		UNIX_TIMESTAMP(created_at) as created_at_ts,
        		UNIX_TIMESTAMP(completed_at) as completed_at_ts
        from $survey_table_name where id = $survey_id;
        ");

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error );
        }

        if (empty($survey_res)) {return false;}


        /** @noinspection SqlResolve */
        $answers_res = $wpdb->get_results("select
						r.id as response_id,
						q.id as question_id,
						q.question,
						q.shortcode,
						a.id as answer_id,
						a.answer
						from $survey_table_name s
						inner join $response_table_name r on r.survey_id = s.id
						inner join $question_table_name q on q.id = r.question_id
						inner join $answer_table_name a on a.id = r.answer_id
						WHERE s.id = $survey_id
						 order by a.id;
        ");

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error );
        }

        $answers_array = [];
        foreach ($answers_res as $answer) {
            $node = ['question'=> $answer->question,'question_id'=> $answer->question_id,
                     'answer'=> $answer->answer,"answer_id"=>$answer->answer_id,
                     "response_id"=>$answer->response_id,
	                "shortcode" => $answer->shortcode
	            ];

            array_push($answers_array,$node);
        }


        return ['survey' => $survey_res[0], 'answers' => $answers_array];
    }




}