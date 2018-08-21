<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/admin
 * @author     Your Name <email@example.com>
 */
class Fran_Test_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;


	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

        $b_check = strpos($_SERVER['QUERY_STRING'], 'fran-test');
        if ($b_check !== false) {



            wp_enqueue_style( $this->plugin_name.'slick', plugin_dir_url( __DIR__ ) . 'lib/SlickGrid/slick.grid.css', array(), $this->version, 'all' );
         //   wp_enqueue_style( $this->plugin_name.'slickuismooth', plugin_dir_url( __DIR__ ) . 'lib/SlickGrid/css/smoothness/jquery-ui-1.11.3.custom.css', array(), $this->version, 'all' );
            wp_enqueue_style( $this->plugin_name.'slickexamps', plugin_dir_url( __DIR__ ) . 'lib/SlickGrid/css/working.css', array(), $this->version, 'all' );
            wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/fran-test-admin.css', array(), $this->version, 'all' );
        }


	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {


        $b_check = strpos($_SERVER['QUERY_STRING'], 'fran-test');


        if ($b_check !== false) {


          //  wp_enqueue_script($this->plugin_name.'slickcorejqui', plugin_dir_url(__DIR__) . 'lib/SlickGrid/lib/jquery-ui-1.11.3.js', array('jquery'), $this->version, false);

            wp_enqueue_script($this->plugin_name.'slickcoredrag', plugin_dir_url(__DIR__) . 'lib/SlickGrid/lib/jquery.event.drag-2.3.0.js', array('jquery'), $this->version, false);
            wp_enqueue_script($this->plugin_name.'slickcorejsonp', plugin_dir_url(__DIR__) . 'lib/SlickGrid/lib/jquery.jsonp-2.4.min.js', array('jquery'), $this->version, false);
            wp_enqueue_script($this->plugin_name.'slickcore', plugin_dir_url(__DIR__) . 'lib/SlickGrid/slick.core.js', array('jquery'), $this->version, false);
            wp_enqueue_script( $this->plugin_name.'a', plugin_dir_url( __FILE__ ) . 'js/fran-test-admin.js', array( 'jquery' ), $this->version, false );
            wp_enqueue_script($this->plugin_name.'slickgrid', plugin_dir_url(__DIR__) . 'lib/SlickGrid/slick.grid.js', array('jquery'), $this->version, false);
            wp_enqueue_script($this->plugin_name.'slicksel', plugin_dir_url(__DIR__) . 'lib/SlickGrid/plugins/slick.rowselectionmodel.js', array('jquery'), $this->version, false);



            wp_enqueue_script($this->plugin_name, plugin_dir_url(__DIR__) . 'lib/Chart.min.js', array('jquery'), $this->version, false);

            $title_nonce = wp_create_nonce('fran_test_admin');
            wp_localize_script('fran-test', 'fran_test_backend_ajax_obj', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'action' => 'fran_test_admin',
                'nonce' => $title_nonce,
            ));
        }

	}

    public function my_admin_menu() {
	    add_options_page( 'Survey Results', 'Franchise Test', 'manage_options',
		    'fran-test', array( $this, 'create_admin_interface') );//

     //   add_menu_page( 'Survey Results', 'Franchise Test', 'manage_options', 'fran-test/fran-test-admin-page.php', array( $this, 'create_admin_interface' ), 'dashicons-chart-line', null  );
    }

    /**
     * Callback function for the admin settings page.
     *
     * @since    1.0.0
     */
    public function create_admin_interface(){

	    $this->options = get_option( 'fran_test_options' );
        /** @noinspection PhpIncludeInspection */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/fran-test-admin-display.php';

    }



    public function add_settings() {



	    register_setting(
		    'fran-test-options-group', // Option group
		    'fran_test_options', // Option name
		    array( $this, 'sanitize' ) // Sanitize
	    );

	    add_settings_section(
		    'fran_setting_section_id', // ID
		    'Click Funnel Integration Settings', // Title
		    array( $this, 'print_section_info' ), // Callback
		    'fran-test-options' // Page
	    );


	    add_settings_field(
		    'hubspot_api', // ID
		    'Hub Spot API Key', // Title
		    array( $this, 'hubspot_api_callback' ), // Callback
		    'fran-test-options', // Page
		    'fran_setting_section_id' // Section
	    );

	    add_settings_field(
		    'allowed_colors', // ID
		    'List of Colors', // Title
		    array( $this, 'allowed_colors_callback' ), // Callback
		    'fran-test-options', // Page
		    'fran_setting_section_id' // Section
	    );

	    add_settings_field(
		    'text_color', // ID
		    'Text Color', // Title
		    array( $this, 'text_color_callback' ), // Callback
		    'fran-test-options', // Page
		    'fran_setting_section_id' // Section
	    );


	    add_settings_field(
		    'redirect_url', // ID
		    'Redirect', // Title
		    array( $this, 'redirect_url_callback' ), // Callback
		    'fran-test-options', // Page
		    'fran_setting_section_id' // Section
	    );


	    add_settings_field(
		    'email_alerts', // ID
		    'Email Notices ', // Title
		    array( $this, 'email_alerts_callback' ), // Callback
		    'fran-test-options', // Page
		    'fran_setting_section_id' // Section
	    );



    }


	public function email_alerts_callback() {

    	if (array_key_exists('email_alerts',$this->options)) {
		    $array =  $this->options['email_alerts'] ;
	    } else {
		    $array =  [] ;
	    }

		if (!is_array($array)) {
			$array = [$array];
		}
		$lines_as_one_string = implode("\n",$array);

		printf(
			'
					<div style="display: inline-block">
 						<textarea  id="email_alerts" name="fran_test_options[email_alerts]" rows="4" cols="55" >%s</textarea>
 						<br>
 						<span style="font-size: smaller">One address per line. Each will be sent   the completed surveys</span>
                    </div>',
			$lines_as_one_string
		);

	}

	public function allowed_colors_callback() {

		$array =  $this->options['allowed_colors'] ;
		if (!is_array($array)) {
			$array = [$array];
		}
		$lines_as_one_string = implode(" , ",$array);

		$color_show = "<div>";
		foreach ($array as $a_color) {
			$color_show .= "<div style='display:inline-block; background-color: $a_color; width: 1em; height: 1em;margin-left: 0.25em'> </div>";
		}

		printf(
			'
					<div style="display: inline-block">
 						<input type="text" value="%s" id="allowed_colors" name="fran_test_options[allowed_colors]" size="60" >
 						<br>
 						<span style="font-size: smaller">Colors </span>
                    </div>
                    %s',
			$lines_as_one_string,$color_show
		);
	}




	public function hubspot_api_callback() {

		if (array_key_exists('hubspot_api',$this->options)) {
			$api_key =  $this->options['hubspot_api'] ;
		} else {
			$api_key =  '' ;
		}


		printf(
			'
					<div style="display: inline-block">
 						<input type="text" value="%s" id="hubspot_api" name="fran_test_options[hubspot_api]" size="60" >
 						<br>
 						<span style="font-size: smaller"> The HubSpot API Key found in Hubspot\'s your-integrations/api-key </span>
                    </div>',
			$api_key
		);
	}

	public function text_color_callback() {

		if (array_key_exists('text_color',$this->options)) {
			$redir =  $this->options['text_color'] ;
		} else {
			$redir =  '' ;
		}


		printf(
			'
					<div style="display: inline-block">
 						<input type="text" value="%s" id="text_color" name="fran_test_options[text_color]" size="60" >
 						<br>
 						<span style="font-size: smaller"> Text Color </span>
                    </div>',
			$redir
		);
	}



	public function redirect_url_callback() {

    	if (array_key_exists('redirect_url',$this->options)) {
		    $redir =  $this->options['redirect_url'] ;
	    } else {
		    $redir =  '' ;
	    }


		printf(
			'
					<div style="display: inline-block">
 						<input type="url" value="%s" id="redirect_url" name="fran_test_options[redirect_url]" size="60" >
 						<br>
 						<span style="font-size: smaller"> Where do you want the user to go after the survey ? </span>
                    </div>',
			$redir
		);

	}




	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 * @return array
	 */
	public function sanitize( $input )
	{

		$new_input = array();



		if( isset( $input['allowed_colors'] ) ) {
			$one_string = $input['allowed_colors'];
			$allowed_array_raw = preg_split('/\s+|,/', $one_string);
			if ($allowed_array_raw === false) {$allowed_array_raw = [];}

			$allowed_array = [];
			foreach ($allowed_array_raw as $allowed_raw) {
				$allowed_raw = trim($allowed_raw,"\"', \t\n\r\0\x0B");
				
				$allowed = sanitize_text_field($allowed_raw);
				if (!empty($allowed)) {
					array_push($allowed_array,$allowed);
				}
			}

			$new_input['allowed_colors'] = $allowed_array;
		}


		if( isset( $input['is_listening'] ) ) {
			$new_input['is_listening'] = sanitize_text_field( $input['is_listening'] );
		} else {
			$new_input['is_listening'] = '0' ;
		}


		if( isset( $input['redirect_url'] ) ) {
			$new_input['redirect_url'] = sanitize_text_field( $input['redirect_url'] );
		} else {
			$new_input['redirect_url'] = '' ;
		}


		if( isset( $input['text_color'] ) ) {
			$new_input['text_color'] = sanitize_text_field( $input['text_color'] );
		} else {
			$new_input['text_color'] = '' ;
		}

		if( isset( $input['hubspot_api'] ) ) {
			$new_input['hubspot_api'] = sanitize_text_field( $input['hubspot_api'] );
		} else {
			$new_input['hubspot_api'] = '' ;
		}


		if( isset( $input['email_alerts'] ) ) {
			$one_string = $input['email_alerts'];
			$allowed_array_raw = preg_split('/\r\n|\r|\n/', $one_string);
			if ($allowed_array_raw === false) {
				if (trim($one_string)) {
					$allowed_array_raw = [$one_string];
				} else {
					$allowed_array_raw = [];
				}

			}

			$allowed_array = [];
			foreach ($allowed_array_raw as $allowed_raw) {
				$allowed_raw = trim($allowed_raw,"\"', \t\n\r\0\x0B");
				if( preg_match("/^\/.+\/[a-z]*$/i",$allowed_raw)) {
					$allowed = $allowed_raw;
				} else {
					if (strpos($allowed_raw, '<') !== false) {
						$allowed = preg_replace('/.*<(.*)>.*/',"$1",$allowed_raw);
					} else {
						$allowed = $allowed_raw;
					}
				}


				$allowed = sanitize_text_field($allowed);
				if (!empty($allowed)) {
					array_push($allowed_array,$allowed);
				}
			}

			$new_input['email_alerts'] = $allowed_array;
		}




		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info()
	{
		print 'Enter your settings below:';
	}

    public function query_survey_ajax_handler() {
        /** @noinspection PhpIncludeInspection */
        global $fran_test_list_survey_obj;
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/fran-test-survey-backend.php';
        check_ajax_referer('fran_test_admin');

        if (array_key_exists( 'method',$_POST) && $_POST['method'] == 'stats') {
            try {
                $stats = FranSurveyBackend::get_stats_array();
                wp_send_json(['is_valid' => true, 'data' => $stats, 'action' => 'stats']);
                die();
            } catch (Exception $e) {
                wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), 'action' => 'stats' ]);
                die();
            }

        } elseif (array_key_exists( 'method',$_POST) && $_POST['method'] == 'list') {

            try {

                $fran_test_list_survey_obj = FranSurveyBackend::do_query_from_post();
                wp_send_json(['is_valid' => true, 'data' => $fran_test_list_survey_obj, 'action' => 'list']);
                die();
            } catch (Exception $e) {
                wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), 'action' => 'list' ]);
                die();
            }

        } elseif (array_key_exists( 'method',$_POST) && $_POST['method'] == 'detail') {
                global $fran_test_details_object;
            try {
                $fran_test_details_object = FranSurveyBackend::get_details_of_one(intval($_POST['id']));
                ob_start();
                require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/fran-test-admin-detail.php';
                $html = ob_get_contents();
                ob_end_clean();
                $fran_test_details_object['html'] = $html;
                wp_send_json(['is_valid' => true, 'data' => $fran_test_details_object, 'action' => 'detail']);
                die();
            } catch (Exception $e) {
                wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), 'action' => 'detail' ]);
                die();
            }

        } else {
            //unrecognized
            wp_send_json(['is_valid' => false, 'message' => "unknown action"]);
            die();
        }
    }



}
