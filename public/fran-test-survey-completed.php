<?php

class FranSurveyCompleted
{

    var $survey_id = null;
    var $key = null;
    var $autonomie = null;
    var $competentie = null;
    var $sociale_verbondenheid = null;
    var $fysieke_vrijheid = null;
    var $emotioneel_welbevinden = null;
    var $energie = null;

    /**
     * FranSurvey constructor.
     * Creates a new survey and adds helper methods
     * @param $dob_ts integer -- timestamp
     * @param $anon_key string -- need this to access
     * @param $b_only_use_key bool -- use only key to get the survey
     * @throws
     */
    public function __construct($dob_ts,$anon_key,$b_only_use_key) {
        $this->open_survey($dob_ts,$anon_key,$b_only_use_key);
        $this->key = $anon_key;
    }

    /**
     * @param $dob_ts integer
     * @param $anon_key string
     * @param $b_only_use_key bool -- use only key to get the survey
     * @return bool|integer
     */
    public function open_survey($dob_ts,$anon_key,$b_only_use_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fran_test_survey';
        $dob = date("Y-m-d", $dob_ts);
        $key = sanitize_text_field($anon_key);
        if ($b_only_use_key) {
            $res = $wpdb->get_results(/** @lang text */
                "select id,autonomie,competentie,sociale_verbondenheid,fysieke_vrijheid,emotioneel_welbevinden,energie from $table_name where anon_key = '$key' ; ");
        } else {
            $res = $wpdb->get_results(/** @lang text */
                "select id,autonomie,competentie,sociale_verbondenheid,fysieke_vrijheid,emotioneel_welbevinden,energie from $table_name where dob = '$dob' and anon_key = '$key' ; ");
        }

        if (empty($res)) {
            return false;
        }
        $this->survey_id =  $res[0]->id;
        $this->autonomie = $res[0]->autonomie;
        $this->competentie = $res[0]->competentie;
        $this->sociale_verbondenheid = $res[0]->sociale_verbondenheid;
        $this->fysieke_vrijheid = $res[0]->fysieke_vrijheid;
        $this->emotioneel_welbevinden = $res[0]->emotioneel_welbevinden;
        $this->energie = $res[0]->energie;

    }
}