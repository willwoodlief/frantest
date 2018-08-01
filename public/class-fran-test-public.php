<?php


/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/public
 * @author     Your Name <email@example.com>
 */
class Fran_Test_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string $plugin_name The name of the plugin.
     * @param      string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {


        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/fran-test-public.css', array(), $this->version, 'all');

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__DIR__) . 'lib/Chart.min.js', array('jquery'), $this->version, false);
        wp_enqueue_script($this->plugin_name. 'a', plugin_dir_url(__FILE__) . 'js/fran-test-public.js', array('jquery'), $this->version, false);
        $title_nonce = wp_create_nonce('fran_test_chart');
        wp_localize_script('fran-test', 'fran_test_chart_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'action' => 'fran_test_submit_chart_step',
            'nonce' => $title_nonce,
        ));

    }

    //JSON
    function send_survey_ajax_handler()
    {
        global $survey_obj;
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/fran-test-survey-new.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/fran-test-survey-completed.php';

        check_ajax_referer('fran_test_chart');
        $dob = null;
        if (array_key_exists('dob_ts',$_POST) && !empty($_POST['dob_ts'])) {
            $dob = intval($_POST['dob_ts']);
        }
        $code = null;
        if (array_key_exists( 'code',$_POST) && !empty($_POST['code'])) {
            $code = sanitize_text_field($_POST['code']);
        }

        if (array_key_exists( 'state',$_POST) && $_POST['state'] == 'start') {

            if ($code && $dob) {
                //try to find a completed survey
                $survey_obj = new FranSurveyCompleted($dob, $code);
                ob_start();
                require_once plugin_dir_path(dirname(__FILE__)) . 'public/partials/fran-test-public-chart.php';
                $html = ob_get_contents();
                ob_end_clean();
                wp_send_json(['is_valid' => true, 'html' => $html, 'message' => 'finished survey page', "state" => "finished_survey_page"]);
                die();
            } else if ($code) {
                $text = get_option('fran_test_not_found_text');
                wp_send_json(['is_valid' => false, 'html' => $text, "state" => "missing_dob"]);
                die();
            } elseif(!$dob) {
                wp_send_json(['is_valid' => false, 'message' => "start without dob"]);
            } else {
                try {
                    $survey_obj = new FranSurvey($dob);
                    $survey_obj->load_questions_of_section('vitacheck');
                } catch (Exception $e) {
                    wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), ]);
                    die();
                }
                ob_start();
                require_once plugin_dir_path(dirname(__FILE__)) . 'public/partials/fran-test-public-questions.php';
                $html = ob_get_contents();
                ob_end_clean();
                wp_send_json(['is_valid' => true, 'html' => $html, 'message' => 'starting survey vitacheck', "state" => "survey_page"]);
                die();
            }


        } elseif (array_key_exists( 'state',$_POST) && $_POST['state'] == 'vitacheck') {

            try {
                $survey_obj = new FranSurvey(null, $code);
                $answers =$survey_obj->save_answers_from_post();
                $survey_obj->load_questions_of_section('psychologische');
            } catch (Exception $e) {
                wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), ]);
                die();
            }
            ob_start();
            require_once plugin_dir_path(dirname(__FILE__)) . 'public/partials/fran-test-public-questions.php';
            $html = ob_get_contents();
            ob_end_clean();
            wp_send_json(['is_valid' => true, 'html' => $html, 'message' => 'starting survey psychologische', "state" => "survey_page",'processed_answers'=>$answers]);
            die();
        } elseif (array_key_exists( 'state',$_POST) && $_POST['state'] == 'psychologische') {

            try {
                $survey_obj = new FranSurvey(null, $code);
                $answers = $survey_obj->save_answers_from_post();
                $survey_obj->calculate_and_close_survey();
            } catch (Exception $e) {
                wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), ]);
                die();
            }
            $survey_obj = new FranSurveyCompleted(null, $code,true);
            ob_start();
            require_once plugin_dir_path(dirname(__FILE__)) . 'public/partials/fran-test-public-chart.php';
            $html = ob_get_contents();
            ob_end_clean();
            wp_send_json(['is_valid' => true, 'html' => $html, 'message' => 'finished survey page', "state" => "finished_survey_page",'processed_answers'=>$answers]);
            die();
        }

        wp_send_json(['is_valid' => true, 'message' => "hi", 'test' => $_POST['test']]);
    }

    public function shortcut_code()
    {
        add_shortcode($this->plugin_name, array($this, 'manage_shortcut'));
    }

    /**
     * @param array $attributes - [$tag] attributes
     * @param null $content - post content
     * @param string $tag
     * @return string - the html to replace the shortcode
     */
    public
    function manage_shortcut($attributes = [], $content = null, $tag = '')
    {
        global $fran_test_custom_header;
// normalize attribute keys, lowercase
        $atts = array_change_key_case((array)$attributes, CASE_LOWER);

        // override default attributes with user attributes
        $our_atts = shortcode_atts([
            'border' => 1,
            'results' => 0,
        ], $atts, $tag);

        // start output
        $o = '';

        $fran_test_custom_header = '';
        // enclosing tags
        if (!is_null($content)) {

            // run shortcode parser recursively
            $expanded__other_shortcodes = do_shortcode($content);
            // secure output by executing the_content filter hook on $content, allows site wide auto formatting too
            $fran_test_custom_header .= apply_filters('the_content', $expanded__other_shortcodes);

        }

	                    require_once plugin_dir_path(dirname(__FILE__)) . 'public/fran-test-public-questions.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/partials/fran-test-before-submit.php';


        // return output
        return $o;
    }

}
