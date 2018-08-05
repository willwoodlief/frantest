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

        //wp_enqueue_script($this->plugin_name, plugin_dir_url(__DIR__) . 'lib/Chart.min.js', array('jquery'), $this->version, false);
	    wp_enqueue_script($this->plugin_name. 'b', plugin_dir_url(__FILE__) . 'js/js.cookie.js', array(), $this->version, false);
        wp_enqueue_script($this->plugin_name. 'a', plugin_dir_url(__FILE__) . 'js/fran-test-public-test.js', array('jquery'), $this->version, false);
        $title_nonce = wp_create_nonce('fran_test_chart');
        wp_localize_script($this->plugin_name. 'a', 'fran_test_frontend_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'action' => 'fran_test_submit_chart_step',
            'nonce' => $title_nonce,
        ));

    }


    public function send_survey_ajax_handler() {

	    require_once plugin_dir_path(dirname(__FILE__)) . 'public/fran-test-public-questions.php';

	    check_ajax_referer( 'fran_test_chart' );

	    if (array_key_exists( 'method',$_POST) && $_POST['method'] == 'survey_answer') {

		    try {
			    $survey_id = sanitize_text_field($_POST['survey_id']);
			    $question_id = sanitize_text_field($_POST['question_id']);
			    $answer_id = sanitize_text_field($_POST['answer_id']);
			    $response_id = FranTestPublic::update_answer($survey_id,$question_id,$answer_id);

			    wp_send_json(['is_valid' => true, 'data' => $response_id, 'action' => 'updated_survey_answer']);
			    die();
		    } catch (Exception $e) {
			    wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), 'action' => 'stats' ]);
			    die();
		    }
	    }
	    elseif  (array_key_exists( 'method',$_POST) && $_POST['method'] == 'survey_words') {
		    try {
			    $survey_id = sanitize_text_field($_POST['survey_id']);
			    if (array_key_exists('name',$_POST)) {
				    $name = sanitize_text_field($_POST['name']);
			    } else {
				    $name = null;
			    }

			    if (array_key_exists('email',$_POST)) {
				    $email = sanitize_text_field($_POST['email']);
			    } else {
					$email = null;
			    }

			    if (array_key_exists('phone',$_POST)) {
				    $phone = sanitize_text_field($_POST['phone']);
			    } else {
					$phone = null;
			    }


			    $response_id = FranTestPublic::update_words($survey_id,$name,$email,$phone);

			    wp_send_json(['is_valid' => true, 'update_count' => $response_id, 'action' => 'updated_survey_words']);
			    die();
		    } catch (Exception $e) {
			    wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), 'action' => 'stats' ]);
			    die();
		    }
	    }

	    else {
		    //unrecognized
		    wp_send_json(['is_valid' => false, 'message' => "unknown action"]);
		    die();
	    }
    }

    //JSON


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
