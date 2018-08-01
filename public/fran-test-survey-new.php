<?php
/** @noinspection PhpIncludeInspection */
require_once plugin_dir_path(__DIR__) . "lib/Base64UID.php";

use GpsLab\Component\Base64UID\Base64UID;

class FranSurvey
{

    var $survey_id = null;
    var $survey_code = null;
    var $number_questions = null;
    var $max_id_questions = null;
    var $loaded_questions = [];
    var $section_name = null;

    /**
     * FranSurvey constructor.
     * Creates a new survey and adds helper methods
     * @param $dob_ts integer -- timestamp
     * @param $anon_key string -- need this to access
     * @throws
     */
    public function __construct($dob_ts = null,$anon_key = null)
    {
        global $wpdb;
        $dob_ts = intval($dob_ts);
        $table_name = $wpdb->prefix . 'fran_test_questions';

        /** @noinspection SqlResolve */
        $res = $wpdb->get_results("select max(id) as max_id, count(id) as count_ids from $table_name");
        $max_id= $res[0]->max_id;
        $count_ids = $res[0]->count_ids;
        if (empty($max_id) || empty($count_ids)) {
            throw new Exception("Cannot find any questions in database");
        }
        $this->number_questions = $count_ids;
        $this->max_id_questions = $max_id;

        if ($anon_key) {
            $this->survey_id = $this->open_survey($anon_key);
        } else {
            $this->survey_id = $this->create_new_survey($dob_ts);
        }

    }

    /**
     * @param $anon_key string
     * @return bool|integer
     */
    public function open_survey($anon_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fran_test_survey';
        $key = sanitize_text_field($anon_key);
        $res = $wpdb->get_results(/** @lang text */
            "select id,anon_key from $table_name where  anon_key = '$key' ; ");
        if (empty($res)) {
            return false;
        }
        $this->survey_code = $anon_key;
        return $res[0]->id;

    }

    /**
     * @param string $section, name of section to load
     * @return array
     */
    public function load_questions_of_section($section ) {
        //get the next incomplete question, as well as the number of incomplete questions remaining
        global $wpdb;
        $section = sanitize_text_field($section);

        $table_name = $wpdb->prefix . 'fran_test_questions';

        /** @noinspection SqlResolve */
        $res = $wpdb->get_results(
            "select id,section,question,answers from $table_name where section = '$section' order by id  ; ");
        /**
         * @var array $res
         */
        $this->loaded_questions = $res;
        $this->section_name = $section;
        return $res;
    }

    /**
     * @param $question_obj
     * @return string - html of the question and answer
     */
    public function generate_question_html($question_obj) {
        $the_question = $question_obj->question;
        preg_match("/\s*(?P<number>^[\w]*,)\s*(?P<question>.*$)/", $the_question, $output_array);

        if (!empty($output_array)) {
            $bare_question = $output_array['question'];
            $question_number = rtrim($output_array['number'],',');
        } else {
            $bare_question = $the_question;
            $question_number = '';
        }
        $answer_string = $question_obj->answers;
        $the_question_id = $question_obj->id;
        //break apart answers by comma into array
        $answer_array_raw = explode(',',$answer_string);
        $answer_array = [];
        foreach ($answer_array_raw as $raw_answer) {
            //get the number associated with this, it will be (%d) at the end, strip it out and turn it into an integer
            preg_match("/(?P<question>.*)\((?P<value>\d)\)\s*$/", $raw_answer, $output_array);
            if (empty($output_array)) { continue;}
            if (0 ==$output_array['value']) {continue;}
            $node = ['text'=>$output_array['question'], 'value' => $output_array['value']];
            array_push($answer_array,$node);
        }
        $out =  "<div class='fran-test-question-block'><div class='fran-test-question'>".
            "<span class='fran-test-question-number'>{$question_number}</span>".
            "<span class='fran-test-actual-question'> {$bare_question}</span></div>" .
            "<fieldset><div class='fran-test-answer-line'>";

        foreach ($answer_array as $answer) {

            $answer_text = $answer['text'];
            $answer_value = $answer['value'];
            $answer_name = 'answer_for_question_'.$the_question_id;
            $answer_id = 'question_'.$the_question_id.'_value_'.$answer_value;
            $out.= "<div class='fran-test-radio-and-label'> <input type='radio' class='fran-test-radio' name='$answer_name' value='$answer_value' id='$answer_id' />" .
                "<label for='$answer_id' class='fran-test-radio-label'>$answer_text</label></div>";
        }
        $out .= "</div></fieldset></div>";
        return $out;
    }

    /**
     * @return array|false
     * @throws Exception
     */
    public function save_answers_from_post() {
        global $wpdb;
        try {
            $wpdb->query('START TRANSACTION');
            $ret = [];
            if (array_key_exists('answers', $_POST) && !empty($_POST['answers'])) {
                $answers = $_POST['answers'];
                $queue = [];
                foreach ($answers as $answer) {
                    $name = $answer['name'];
                    $value = $answer['value'];
                    preg_match("/[^\d]*(?<number>[\d]*$)/", $name, $output_array);
                    if ($output_array && !empty($output_array['number'])) {
                        $question_id = $output_array['number'];
                        $node = ['q' => $question_id, 'a' => $value];
                        array_push($queue, $node);
                    } else {
                        throw new Exception("Cannot get question number from " . $name);
                    }

                }
                foreach ($queue as $what) {

                    $answer_id = $this->save_answer($what['a'], $what['q']);
                    array_push($ret, $answer_id);
                }
            }
            $wpdb->query('COMMIT');
            if (empty($ret)) {
                return false;
            } else {
                return $ret;
            }
        } catch(Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }



    }
    /**
     * @param $numeric_answer integer
     * @param $question_id integer
     * @throws Exception
     * @return false|integer
     */
    public function save_answer($numeric_answer, $question_id)
    {
        //get the question section and short_code
        global $wpdb;

        $question_id = intval($question_id);
        $numeric_answer = floatval($numeric_answer);
        $raw_answer = $numeric_answer;
        $mentaal_stap = $fysiek_stap = $psych_stap = null;

        $table_name = $wpdb->prefix . 'fran_test_questions';

        /** @noinspection SqlResolve */
        $res = $wpdb->get_results("Select section,short_code from $table_name where id = $question_id");
        if (empty($res)) {
            throw new Exception("Cannot find the question id [$question_id];");
        }
        $section_name = trim($res[0]->section);
        $short_code = trim($res[0]->short_code);

        if ($section_name == 'vitacheck') {
            //fill in $mentaal_stap en $mentaal_stap
            switch ($short_code) {
                case "uw_gezondheid":
                    { //1
                        //1, Fysiek  =IF(C2=5,-8.37399,IF(C2=4,-5.56461,IF(C2=3,-3.02396,IF(C2=2,-1.31872,IF(C2=1,0,-1)))))
                        //1, Mentaal =IF(C2=5,-1.71175,IF(C2=4,-0.16891,IF(C2=3,0.03482,IF(C2=2,-0.06064,IF(C2=1,0,-1)))))
                        switch ($numeric_answer) {
                            case 5:
                                {
                                    $fysiek_stap = -8.37399;
                                    $mentaal_stap = -1.71175;
                                    break;
                                }
                            case 4:
                                {
                                    $fysiek_stap = -5.56461;
                                    $mentaal_stap = -0.16891;
                                    break;
                                }
                            case 3:
                                {
                                    $fysiek_stap = -3.02396;
                                    $mentaal_stap = 0.03482;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = -1.31872;
                                    $mentaal_stap = -0.06064;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }


                        }
                        break;
                    }

                case "matige_inspanning":
                    {  //2a
                        //2a,Fysiek, =IF(C3=3,0,IF(C3=2,-3.45555,IF(C3=1,-7.23216,-1)))
                        //2a Mentaal =IF(C3=3,0,IF(C3=2,1.8684,IF(C3=1,3.93115,-1)))
                        switch ($numeric_answer) {
                            case 3:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 2;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = -3.45555;
                                    $mentaal_stap = 1.8684;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = -7.23216;
                                    $mentaal_stap = 3.93115;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }

                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "trappen_oplopen":
                    {   //2b Fys =IF(C4=3,0,IF(C4=2,-2.73557,IF(C4=1,-6.24397,-1)))
                        // 2b men =IF(C4=3,0,IF(C4=2,1.43103,IF(C4=1,2.68282,-1)))
                        switch ($numeric_answer) {
                            case 3:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = -2.73557;
                                    $mentaal_stap = 1.43103;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = -6.24397;
                                    $mentaal_stap = 2.68282;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }

                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "minder_bereikt":
                    { //3a
                        switch ($numeric_answer) {
                            //3a Fys =IF(C5=2,0,IF(C5=1,-4.61617,-1))
                            //3a men = =IF(C5=2,0,IF(C5=1,1.4406,-1))
                            case 2:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = -4.61617;
                                    $mentaal_stap = 1.4406;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "beperkt_werk":
                    {
                        //3b fys =IF(C6=2,0,IF(C6=1,-5.51747,-1))
                        //3b me =IF(C6=2,0,IF(C6=1,1.66968,-1))
                        switch ($numeric_answer) {
                            case 2:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = -5.51747;
                                    $mentaal_stap = 1.66968;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;

                    }
                case "minder_behaald":
                    {
                        //4a fy =IF(C7=2,0,IF(C7=1,-5.51747,-1))
                        //4a me =IF(C7=2,0,IF(C7=1,1.66968,-1))
                        switch ($numeric_answer) {
                            case 2:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = -5.51747;
                                    $mentaal_stap = 1.66968;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "werk_zorgvuldig":
                    {
                        //4b fy =IF(C8=2,0,IF(C8=1,-2.32091,-1))
                        //4b me =IF(C8=2,0,IF(C8=1,-5.69921,-1))
                        switch ($numeric_answer) {
                            case 2:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = -2.32091;
                                    $mentaal_stap = -5.69921;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "afgelopen_weken":
                    {
                        //5 fy =IF(C9=5,-11.25544,IF(C9=4,-8.38063,IF(C9=3,-6.50522,IF(C9=2,-3.8013,IF(C9=1,0,-1)))))
                        //5 me =IF(C9=5,1.48619,IF(C9=4,1.76691,IF(C9=3,1.49384,IF(C9=2,0.90384,IF(C9=1,0,-1)))))
                        switch ($numeric_answer) {
                            case 5:
                                {
                                    $fysiek_stap = -11.25544;
                                    $mentaal_stap = 1.48619;
                                    break;
                                }
                            case 4:
                                {
                                    $fysiek_stap = -8.38063;
                                    $mentaal_stap = 1.76691;
                                    break;
                                }
                            case 3:
                                {
                                    $fysiek_stap = -6.50522;
                                    $mentaal_stap = 1.49384;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = -3.8013;
                                    $mentaal_stap = 0.90384;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "zenuwachtig":
                    {
                        //6a  non
                        break;
                    }
                case "kon_opvrolijken":
                    {
                        //6b non
                        break;
                    }
                case "zich_kalm_en_rustig":
                    {
                        // 6c fy =IF(C12=6,3.46638,IF(C12=5,2.90426,IF(C12=4,2.37241,IF(C12=3,1.36689,IF(C12=3,1.36689,IF(C12=2,0.66514,IF(C12=1,0,-1)))))))
                        // 6c me =IF(C12=6,-10.19085,IF(C12=5,-7.92717,IF(C12=4,-6.31121,IF(C12=3,-4.09842,IF(C12=2,-1.94949,IF(C12=1,0,-1))))))
                        switch ($numeric_answer) {
                            case 6:
                                {
                                    $fysiek_stap = 3.46638;
                                    $mentaal_stap = -10.19085;
                                    break;
                                }
                            case 5:
                                {
                                    $fysiek_stap = 2.90426;
                                    $mentaal_stap = -7.92717;
                                    break;
                                }
                            case 4:
                                {
                                    $fysiek_stap = 2.37241;
                                    $mentaal_stap = -6.31121;
                                    break;
                                }
                            case 3:
                                {
                                    $fysiek_stap = 1.36689;
                                    $mentaal_stap = -4.09842;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = 0.66514;
                                    $mentaal_stap = -1.94949;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "energiek_voelen":
                    {
                        //6d fy =IF(C13=6,-2.44706,IF(C13=5,-2.02168,IF(C13=4,-1.6185,IF(C13=3,-1.14387,IF(C13=2,-0.42251,IF(C13=1,0,-1))))))
                        //6d me = =IF(C13=6,-6.02409,IF(C13=5,-4.88962,IF(C13=4,-3.29805,IF(C13=3,-1.65178,IF(C13=2,-0.92057,IF(C13=1,0,-1))))))
                        switch ($numeric_answer) {
                            case 6:
                                {
                                    $fysiek_stap = -2.44706;
                                    $mentaal_stap = -6.02409;
                                    break;
                                }
                            case 5:
                                {
                                    $fysiek_stap = -2.02168;
                                    $mentaal_stap = -4.88962;
                                    break;
                                }
                            case 4:
                                {
                                    $fysiek_stap = -1.6185;
                                    $mentaal_stap = -3.29805;
                                    break;
                                }
                            case 3:
                                {
                                    $fysiek_stap = -1.14387;
                                    $mentaal_stap = -1.65178;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = -0.42251;
                                    $mentaal_stap = -0.92057;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "neerslachtig":
                    {
                        //6e fy =IF(C14=6,0,IF(C14=5,0.41188,IF(C14=4,1.28044,IF(C14=3,2.34247,IF(C14=2,3.41593,IF(C14=1,4.61446,-1))))))
                        //6e me = =IF(C14=6,0,IF(C14=5,-1.95934,IF(C14=4,-4.59055,IF(C14=3,-8.09914,IF(C14=2,-10.77911,IF(C14=1,-16.15395,-1))))))
                        switch ($numeric_answer) {
                            case 6:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 5:
                                {
                                    $fysiek_stap = 0.41188;
                                    $mentaal_stap = -1.95934;
                                    break;
                                }
                            case 4:
                                {
                                    $fysiek_stap = 1.28044;
                                    $mentaal_stap = -4.59055;
                                    break;
                                }
                            case 3:
                                {
                                    $fysiek_stap = 2.34247;
                                    $mentaal_stap = -8.09914;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = 3.41593;
                                    $mentaal_stap = -10.77911;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = 4.61446;
                                    $mentaal_stap = -16.15395;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }
                        }
                        break;
                    }
                case "gelukkig":
                    {
                        //6f non
                        break;
                    }
                case "uitgeblust":
                    {
                        //6g non
                        break;
                    }
                case "levenslustig":
                    {
                        //6h non
                        break;
                    }
                case "zich_moe":
                    {
                        //6i non
                        break;
                    }
                case "activiteiten":
                    {
                        //7 fys =IF(C19=5,0,IF(C19=4,0.11038,IF(C19=3,0.18043,IF(C19=2,-0.94342,IF(C19=1,-0.33682,-1)))))
                        //7 me =IF(C19=5,0,IF(C19=4,-3.13896,IF(C19=3,-5.63286,IF(C19=2,-8.26066,IF(C19=1,-6.29724,-1)))))
                        switch ($numeric_answer) {
                            case 5:
                                {
                                    $fysiek_stap = 0;
                                    $mentaal_stap = 0;
                                    break;
                                }
                            case 4:
                                {
                                    $fysiek_stap = 0.11038;
                                    $mentaal_stap = -3.13896;
                                    break;
                                }
                            case 3:
                                {
                                    $fysiek_stap = 0.18043;
                                    $mentaal_stap = -5.63286;
                                    break;
                                }
                            case 2:
                                {
                                    $fysiek_stap = -0.94342;
                                    $mentaal_stap = -8.26066;
                                    break;
                                }
                            case 1:
                                {
                                    $fysiek_stap = -0.33682;
                                    $mentaal_stap = -6.29724;
                                    break;
                                }
                            case 0:
                                {
                                    $fysiek_stap = -1;
                                    $mentaal_stap = -1;
                                    break;
                                }
                            default:
                                {
                                    throw new Exception("Could not figure out the numberic answer of [$short_code][$numeric_answer]");
                                }


                        }
                        break;
                    }
                default:
                    {
                        throw new Exception("Could not figure out the shortcode of [$short_code]");
                    }
            }
        } else if ($section_name == 'psychologische') {
            switch ($short_code) {
                case 'het_moet':
//2, De meeste dingen die ik doe voelen aan alsof ‘h...

                case 'uitgesloten':
//4, Ik voel me uitgesloten uit de groep waar ik bij...

                case 'ernstige_twijfels':
//6, Ik heb ernstige twijfels over de vraag of ik de...

                case 'gedwongen':
//8, Ik voel me gedwongen om veel dingen te doen waa...

                case 'afstandelijk':
//10, Ik voel dat mensen die belangrijk voor me zijn...

                case 'teleurgesteld':
//12, Ik voel me teleurgesteld in veel van mijn pres...

                case 'verplicht':
//14, Ik voel me verplicht om te veel dingen te doen

                case 'indruk_van_haat':
//16, Ik heb de indruk dat mensen waarmee ik tijd do...
//
                case 'onzeker':
//18, Ik voel me onzeker over mijn vaardigheden

                case 'verplichtingen':
//20, Mijn dagelijkse activiteiten voelen als een aa...

                case 'oppervlakkig':
//22, Ik voel dat de relaties die ik heb slechts opp...


                case 'een_mislukking':
//24, Ik voel me als een mislukking omwille van de f...
                    {
                        $psych_stap = 5 - $numeric_answer + 1;
                        break;
                    }
                default:
                    {
                        $psych_stap = $numeric_answer;
                        //half the codes were not covered and will end up in the else statement
                    }
            }
        } else {
            throw new Exception("Section name of [$section_name] not in logic");
        }


        //add new answer
        $table_name = $wpdb->prefix . 'fran_test_answers';
        $what = $wpdb->insert(
            $table_name,
            array(
                'survey_id' => $this->survey_id,
                'question_id' => $question_id,
                'raw_answer' => $raw_answer,
                'mentaal_stap' => $mentaal_stap,
                'fysiek_stap' => $fysiek_stap,
                'psych_stap' => $psych_stap,
            ),
            array(
                '%d','%d','%d', '%f','%f','%f'
            )
        );

        if ($what === false) {
            throw new Exception("Could not create new Survey");
        }
        $last_id = $wpdb->insert_id;
        return $last_id;


    }

    /**
     * @param $dob_ts
     * @return integer
     * @throws Exception
     */
    protected function create_new_survey($dob_ts)
    {
        global $wpdb;
        $anon_code = $this->get_unique_code();
        $this->survey_code = $anon_code;
        $table_name = $wpdb->prefix . 'fran_test_survey';
        $last_id = $wpdb->insert(
            $table_name,
            array(
                'dob' => date("Y-m-d", $dob_ts),
                'anon_key' => $anon_code,
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
        return $last_id;
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function get_unique_code()
    {
        global $wpdb;
        $charset = '23456789abcdefghijkmnpqrstuvwxyz';
        $counter = 100;
        while ($counter) {
            $uid = Base64UID::generate(6, $charset);
            /** @noinspection SqlResolve */
            $find_code = $wpdb->get_results(
                "select id  from {$wpdb->base_prefix}fran_test_survey WHERE anon_key = '%s'", $uid
            );
            if (empty($find_code)) {
                return $uid;
            }
            $counter--;
        }
        throw new Exception("Cound not generate a unique key after 100 tries");

    }

    /**
     * @throws Exception
     * @return void
     */
    public function calculate_and_close_survey() {
        /*
raw fys =
raw emotional = =SUM(C10,C11,C12,C14,C15) =  6a + 6b + 6c + 6e + 6f = 9[zenuwachtig] + 10[kon_opvrolijken] + 11[zich_kalm_en_rustig] + 13[neerslachtig] + 14[gelukkig]
raw energy = =(SUM(C16:C18,6-C13+6)-4)/20*100 = (6g + 6h +  6i + ([6] - 6d ) - 4) /20 * 100 = 15[uitgeblust] + 16[levenslustig] + 17[zich_moe] ( 6 - 12[energiek_voelen])



sub_fysieke_raw
sum all fys in survey + 56.57706

sub_emotioneel_raw
 zenuwachtig
 kon_opvrolijken
 zich_kalm_en_rustig
 neerslachtig
 gelukkig

sub_energie_raw
    uitgeblust
    levenslustig
    zich_moe
    6 - energiek_voelen

final fys  = ((round)raw fys/57.0) * 100
final emotional = raw emotional * 4
final energy = raw energy


        sub_autonomie
psych_step(1 + 7 + 13 + 19 + 2 + 8 + 14 + 20)/8
19	keuze
25	beslissingen
31	mijn_keuzes
37	interesseert
20	het_moet
26	gedwongen
32	verplicht
38	verplichtingen

        sub_binding
psych_step (3+9+15+21+4+10+16+22)/8
21	mensen_waar_ik_om
27	verbonden
33	nauw_verbonden
39	warm_gevoel
22	uitgesloten
28	afstandelijk
34	 indruk_van_haat
40	oppervlakkig

sub_competentie
 = psych_step (5,11,17,23,6,12,18,24)/8
23	goed_kan_doen
29	bekwaam
35	doelen_bereiken
41	met_succes
24	ernstige_twijfels
30	teleurgesteld
36	onzeker
42	een_mislukking
--------------------------------------
final values
Autonomie	= sub_autonomie*20
Competentie	=sub_competentie *20
Sociale verbondenheid	=sub_binding*20
Fysieke vrijheid	=final fys
Emotioneel welbevinden	=final_emotional
Energie	='VitaCheck 2018'!C25 = final_energy
         */

        global $wpdb;
        $questions_table_name = $wpdb->prefix . 'fran_test_questions';
        $answers_table_name = $wpdb->prefix . 'fran_test_answers';
        $survey_table = $wpdb->prefix . 'fran_test_survey';

        //sub_fysieke_raw
        //sum all fys in survey + 56.57706
        /** @noinspection SqlResolve */
        $res = $wpdb->get_results(
            "select sum(fysiek_stap) as sub_fysieke_raw from $answers_table_name where survey_id = '{$this->survey_id}'  ; ");

        if (empty($res)) {throw new Exception("summing up fysiek_stap is empty ");}
        $sub_fysieke_raw = $res[0]->sub_fysieke_raw;
        $sub_fysieke_raw = floatval($sub_fysieke_raw);
        $sub_fysieke_raw += 56.57706;


        /** @noinspection SqlResolve */
        $res = $wpdb->get_results(
            "select q.short_code , a.raw_answer from $questions_table_name q
        inner join $answers_table_name a on a.question_id = q.id
        and q.short_code in ('zenuwachtig','kon_opvrolijken','zich_kalm_en_rustig','neerslachtig','gelukkig')
        and a.survey_id = '{$this->survey_id}'  ; ");

        if (empty($res)) {throw new Exception("getting answer fields is empty ");}
        $sub_emotioneel_raw = 0;
        foreach ($res as $row) {
            $sub_emotioneel_raw += floatval($row->raw_answer);
        }


        /** @noinspection SqlResolve */
        $res = $wpdb->get_results(
            "select q.short_code , a.raw_answer from $questions_table_name q
        inner join $answers_table_name a on a.question_id = q.id
        and q.short_code in ('uitgeblust','levenslustig','zich_moe','energiek_voelen')
        and a.survey_id = '{$this->survey_id}'  ; ");

        if (empty($res)) {throw new Exception("getting answer fields is empty ");}
        $sub_energie_raw = 0;
        foreach ($res as $row) {
            if ($row->short_code != 'energiek_voelen') {
                $sub_energie_raw += floatval($row->raw_answer);
            } else {
                $sub_energie_raw += (6 -floatval($row->raw_answer));
            }
        }


        $rounded_sub_fys = round($sub_fysieke_raw);
        $sub_fysieke_final   = ($rounded_sub_fys/57.0) * 100;
        $sub_emotioneel_final = $sub_emotioneel_raw * 4;
        $sub_energie_final = $sub_energie_raw;


        /** @noinspection SqlResolve */
        $res = $wpdb->get_results(
            "select q.short_code , a.raw_answer from $questions_table_name q
        inner join $answers_table_name a on a.question_id = q.id
        and q.short_code in ('keuze','beslissingen','mijn_keuzes',
        'interesseert','het_moet','gedwongen','verplicht','verplichtingen')
        and a.survey_id = '{$this->survey_id}'  ; ");

        if (empty($res)) {throw new Exception("getting answer fields is empty ");}
        $sub_autonomie = 0;
        foreach ($res as $row) {
            $sub_autonomie += floatval($row->raw_answer);
        }
        $sub_autonomie /= 8.0;


        //        sub_binding

        /** @noinspection SqlResolve */
            $res = $wpdb->get_results(
                "select q.short_code , a.raw_answer from $questions_table_name q
        inner join $answers_table_name a on a.question_id = q.id
        and q.short_code in ('mensen_waar_ik_om','verbonden','nauw_verbonden',
        'warm_gevoel','uitgesloten','afstandelijk','indruk_van_haat','oppervlakkig')
        and a.survey_id = '{$this->survey_id}'  ; ");

        if (empty($res)) {throw new Exception("getting answer fields is empty ");}
        $sub_binding = 0;
        foreach ($res as $row) {
            $sub_binding += floatval($row->raw_answer);
        }
        $sub_binding /= 8.0;



        /** @noinspection SqlResolve */
        $res = $wpdb->get_results(
            "select q.short_code , a.raw_answer from $questions_table_name q
        inner join $answers_table_name a on a.question_id = q.id
        and q.short_code in ('goed_kan_doen','bekwaam','doelen_bereiken',
        'met_succes','ernstige_twijfels','teleurgesteld','onzeker','een_mislukking')
        and a.survey_id = '{$this->survey_id}'  ; ");

        if (empty($res)) {throw new Exception("getting answer fields is empty ");}
        $sub_competentie = 0;
        foreach ($res as $row) {
            $sub_competentie += floatval($row->raw_answer);
        }
        $sub_competentie /= 8.0;


        $autonomie = $sub_autonomie * 20;
        $competentie = $sub_competentie * 20;
        $sociale_verbondenheid = $sub_binding * 20;
        $fysieke_vrijheid = $sub_fysieke_final;
        $emotioneel_welbevinden = $sub_emotioneel_final;
        $energie = $sub_energie_final;

        /** @noinspection SqlResolve */
        $what = $wpdb->query("UPDATE $survey_table SET 
            sub_fysieke_raw = $sub_fysieke_raw,
            sub_emotioneel_raw = $sub_emotioneel_raw,
            sub_energie_raw = $sub_energie_raw,
            sub_fysieke_final = $sub_fysieke_final,
            sub_emotioneel_final = $sub_emotioneel_final,
            sub_energie_final = $sub_energie_final,
            sub_autonomie = $sub_autonomie,
            sub_binding = $sub_binding,
            sub_competentie = $sub_competentie,
            autonomie = $autonomie,
            competentie = $competentie,
            sociale_verbondenheid = $sociale_verbondenheid,
            fysieke_vrijheid = $fysieke_vrijheid,
            emotioneel_welbevinden = $emotioneel_welbevinden,
            energie = $energie,
            is_completed = 1
            WHERE id = {$this->survey_id}
        ");

        if (!$what) {
            throw new Exception("Could not update the survey table with final calcs");
        }
    }
}