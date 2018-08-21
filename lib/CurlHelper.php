<?php /** @noinspection PhpComposerExtensionStubsInspection */



class CurlHelperException extends \Exception {

	protected $data;

	public function __construct( $data, $message, $code = 0, \Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
		$this->message = $message;
		$this->data    = $data;

		//overwrites message using the older version of message set above
		$this->message = (string) $this;
	}

	/**
	 * Returns the error type of the exception that has been thrown.
	 */
	public function getData() {
		return $this->data;
	}

	public function __toString() {
		$data = print_r( $this->data, true );
		$code = $this->getCode();

		return "[$code] " . $this->message . "\n$data";
	}


}

class CurlHelper {
	protected $ch = null;
	protected $cookie_file = null;
	public $delete_cookie_file = true;
	protected $user_agent = null;
	public $b_debug = false;
	protected $last_url = null;

	public function get_last_url() { return $this->last_url;}

	/**
	 * CurlHelper constructor.
	 * @param  $b_delete_cookie_file bool
	 * @param  $use_cookie_file string|null
	 * @throws CurlHelperException
	 */
	public function __construct($b_delete_cookie_file = true,$use_cookie_file = null) {

		$this->ch                 = curl_init();
		$this->delete_cookie_file = $b_delete_cookie_file;

		if ( $use_cookie_file ) {
			$this->cookie_file = $use_cookie_file;
		} else {
			$this->cookie_file = CurlHelper::write_to_file( 'tmp/cookie', 'txt', '' );
		}

		curl_setopt( $this->ch, CURLOPT_COOKIEJAR, realpath( $this->cookie_file ) );
		curl_setopt( $this->ch, CURLOPT_COOKIEFILE, realpath( $this->cookie_file ) );

	}

	function __destruct() {
		curl_close( $this->ch );
		if ($this->cookie_file) {
			if ($this->delete_cookie_file) {
				unlink($this->cookie_file);
			}
		}

	}

	/**
	 * @param $root_name
	 * @param $extension
	 * @param $contents
	 *
	 * @return string
	 * @throws CurlHelperException
	 */
	public static function  write_to_file($root_name,$extension,$contents) {
		$uid = uniqid($root_name,true);
		$temp_name = realpath(dirname(__FILE__)) . '/'.$uid .'.'.$extension  ;

		if (!$contents) {
			$contents = '';
		}

		$b_what = file_put_contents($temp_name,$contents);
		if ($b_what === false) {
			throw new CurlHelperException(null,"could not create $temp_name file");
		}
		//$b_what = chmod( realpath($temp_name), 0777);
		shell_exec('chmod 666 ' . $temp_name);

		return $temp_name;
	}


	/**
	 * Returns the decoded json from the input
	 * if input is null then returns null
	 * if input is empty returns []
	 * else converts the string value of the input from a json string to a php structure
	 * @param mixed $what
	 * @param boolean $b_exception default true <p>
	 *  will throw an exception if is not proper json if true
	 *  will return the string if not proper json
	 * </p>
	 * @return array|mixed|null
	 * @throws CurlHelperException if json error
	 */
	public static function json_from_string($what,$b_exception=true) {
		if (is_null($what) ) { return null;}
		if (empty($what)) {return [];}
		$what = strval($what);
		if ( strcasecmp($what,'null') == 0) {return null;}
		$out = json_decode(strval($what), true);
		if (is_null($out)) {
			if ($b_exception) {
				$oops =  json_last_error_msg();
				$oops .= "\n Data: \n". $what;
				throw  new CurlHelperException(null,$oops);
			}else {
				return $what;
			}

		} else {
			return $out;
		}

	}

	/**
	 * @param $url string
	 * @param array $file_names
	 * @param array $fields
	 * @param string $format
	 * @throws CurlHelperException
	 * @return string
	 */
	function upload_file_and_data($url,array $file_names,array $fields,$format='text') {
// data fields for POST request

		$files = array();
		foreach ($file_names as $input_name => $file_path){
			$content = file_get_contents($file_path);
			$pretty_file_name = basename($file_path);
			$files[$input_name] = ['content'=>$content,'file_name'=>$pretty_file_name];
		}


		$boundary = uniqid();
		$delimiter = '-------------' . $boundary;

		$post_data = CurlHelper::build_data_files($boundary, $fields, $files);

		$headers = array(
			//"Authorization: Bearer $TOKEN",
			"Content-Type: multipart/form-data; boundary=" . $delimiter,
			"Content-Length: " . strlen($post_data)

		);

		$body =  $this->curl_helper($url,$http_code,$post_data,true,$format,false,false,$headers);

		if ($this->b_debug) {
			print "<h1>After Plugin Upload</h1>";
			print $body;
		}
		if ($http_code >= 400 ) {
			throw new CurlHelperException(null,$body);
		}
		return $body;
	}

	protected function build_data_files($boundary, $fields, $files){
		$data = '';
		$eol = "\r\n";

		$delimiter = '-------------' . $boundary;

		foreach ($fields as $name => $content) {
			$data .= "--" . $delimiter . $eol
			         . 'Content-Disposition: form-data; name="' . $name . "\"".$eol.$eol
			         . $content . $eol;
		}


		foreach ($files as $name => $details) {
			$content = $details['content'];
			$file_name = $details['file_name'];
			$data .= "--" . $delimiter . $eol
			         . 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file_name . '"' . $eol
			         //. 'Content-Type: image/png'.$eol
			         . 'Content-Transfer-Encoding: binary'.$eol
			;

			$data .= $eol;
			$data .= $content . $eol;
		}
		$data .= "--" . $delimiter . "--".$eol;


		return $data;
	}

	function get_cookie_file_path() { return $this->cookie_file;}

	function set_user_agent($agent) {
		$this->user_agent = $agent;
		curl_setopt ($this->ch, CURLOPT_USERAGENT, $this->user_agent);
	}
	/**
	 * @author Will Woodlief
	 * @license MIT
	 * @link https://gist.github.com/willwoodlief/1a008ab369ec48968d41d0cec1b9c4d6
	 * General Curl Helper. Its multipurpose. Used it in the transcription project and now improved it
	 * @example curl_helper('cnn.com',null,$code)
	 *          curl_helper('enri.ch',['var1'=>4],$code)
	 *
	 *   options reset for the curl handle after each call
	 *
	 * @param $url string the url

	 * @param &$http_code integer , will be set to the integer return code of the server. Its only an output variable
	 *
	 * @param $fields array|object|string|null <p>
	 *  the params to pass
	 *  May be an array, or object containing properties, or a string, or evaluates for false
	 *  </p>
	 * @param $b_post boolean , default true . POST is true, GET is false
	 * @param  $format string (json|xml|text) default json <p>
	 *      Tells how the response is formatted, text means no conversion
	 * </p>
	 * @param $b_header_only boolean, default false <p>
	 *  if true then no body is downloaded, and the return the headers
	 * </>
	 * @param $ssl_version boolean , default false <p>
	 *   if not false, then set CURLOPT_SSLVERSION to the value
	 * </p>
	 * @param $headers array , default empty <p>
	 *   adds to the headers of the request being sent
	 * </p>
	 * @param $custom_request false|string, default false <p>
	 * when set will set custom post instead of post
	 * </p>
	 *
	 * @return array|string|int|null depends on the format and option
	 *
	 * @throws CurlHelperException <p>
	 *   if curl cannot connect
	 *   if site gives response in the 500s (if $b_header_only is false)
	 *   if the format is json and the the conversion has errors and response is below 500
	 * if the format is xml and the conversion has errors and response is below 500
	 * </p>
	 */
	function curl_helper(
		 $url,  &$http_code,$fields = null, $b_post = true, $format = 'json',
		 $b_header_only = false, $ssl_version = false, $headers = [], $custom_request = false
	) {
		$b_verbose = $this->b_debug;
		if ( ! isset( $url ) ) {
			throw new CurlHelperException( $fields, "URL needs to be set" );
		}
		$url = strval( $url );


		try {
			curl_setopt_array( $this->ch, [
				CURLOPT_RETURNTRANSFER => true,
			] );

			//curl will not print verbose info to the html browser screen. So we have to capture it and replay it
			$verbose = null;
			if ( $b_verbose ) {
				$verbose = fopen( 'php://temp', 'w+' );
				curl_setopt( $this->ch, CURLOPT_STDERR, $verbose );
				curl_setopt( $this->ch, CURLOPT_VERBOSE, true );
			}

			if ( $b_header_only ) {
				curl_setopt( $this->ch, CURLOPT_HEADER, true );    // we want headers
				curl_setopt( $this->ch, CURLOPT_NOBODY, true );    // we don't need body

				$out_headers = [];
				// this function is called by curl for each header received
				curl_setopt( /**
				 * @param $curl resource
				 * @param $header string
				 *
				 * @return int
				 */
					$this->ch, CURLOPT_HEADERFUNCTION,
					function (
						/** @noinspection PhpUnusedParameterInspection */
						$curl, $header
					) use ( &$out_headers ) {
						$len    = strlen( $header );
						$header = explode( ':', $header, 2 );
						if ( count( $header ) < 2 ) // ignore invalid headers
						{
							return $len;
						}

						$name = strtolower( trim( $header[0] ) );
						if ( ! array_key_exists( $name, $out_headers ) ) {
							$out_headers[ $name ] = [ trim( $header[1] ) ];
						} else {
							$out_headers[ $name ][] = trim( $header[1] );
						}

						return $len;
					}
				);
			}

			if ( $headers ) {
				curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $headers );
			}

			//if testing on localhost and url is https, then this gets around it because some localhost do not have ssl certs

			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				if ( in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ) ) ) {
					curl_setopt( $this->ch, CURLOPT_SSL_VERIFYPEER, false );
				}
			}


			if ( $b_post ) {
				if ( $custom_request ) {
					curl_setopt( $this->ch, CURLOPT_CUSTOMREQUEST, $custom_request );
				} else {
					curl_setopt( $this->ch, CURLOPT_POST, count( $fields ) );
				}

				if ( $fields ) {
					if ( is_object( $fields ) || is_array( $fields ) ) {
						$b_do_build = true;
						if ( is_array( $fields ) ) {
							if ( array_key_exists( 'curl_helper_skip_encoding', $fields ) ) {
								if ( $fields['curl_helper_skip_encoding'] ) {
									$b_do_build = false;
								}
							}
						}
						if ( is_object( $fields ) ) {
							if ( property_exists( $fields, 'curl_helper_skip_encoding' ) ) {
								if ( $fields->curl_helper_skip_encoding ) {
									$b_do_build = false;
								}
							}
						}
						if ( $b_do_build ) {
							$build = http_build_query( $fields );
						} else {
							$build = $fields;
						}


						curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $build );
					} else {
						curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $fields );
					}

				}

			} else {
				if ( $custom_request ) {
					curl_setopt( $this->ch, CURLOPT_CUSTOMREQUEST, $custom_request );
				}
				if ( $fields ) {
					if ( is_object( $fields ) || is_array( $fields ) ) {
						$query = http_build_query( $fields );
					} else {
						$query = $fields;
					}

					$url = $url . '?' . $query;
				}
			}

			curl_setopt( $this->ch, CURLOPT_URL, $url );

			curl_setopt( $this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64; rv:21.0) Gecko/20100101 Firefox/21.0" );

			//curl_setopt( $this->ch, CURLOPT_COOKIEFILE, '' );

			curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );

			if ( $ssl_version ) {
				curl_setopt( $this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
			}


			$curl_output = curl_exec( $this->ch );




			$http_code = intval( curl_getinfo( $this->ch, CURLINFO_HTTP_CODE ) );

			$this->last_url = curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL);

			if ($b_verbose) {
				echo "<div style='color: blue;background-color: white'>Server returned code $http_code</div>\n";
			}

			if ( $b_verbose ) {
				rewind( $verbose );
				$verboseLog = stream_get_contents( $verbose );
				echo "Verbose information:\n<pre>", htmlspecialchars( $verboseLog ), "</pre>\n";
			}

			if ( curl_errno( $this->ch ) ) {
				throw new CurlHelperException( $fields, "could not open url: $url because of curl error: ", curl_error( $this->ch ) );
			}

			if ( $b_header_only ) {

				$out_headers['effective_url'] = curl_getinfo( $this->ch, CURLINFO_EFFECTIVE_URL );

				return $out_headers; //journey ends here with just the headers
			}

			if ( $http_code == 0 ) {
				throw new CurlHelperException( [], "Could not send data to $url", $http_code );
			}

			if ( ! is_string( $curl_output ) || ! strlen( $curl_output ) ) {
				$curl_output = ''; //no longer throwing exception here as sometimes need return code
			}

			//makes it easy to skip formatting
			if ( $format === true || ! $format ) {
				$format = 'none';
			}
			try {
				switch ( $format ) {
					case 'json':
						$data_out = CurlHelper::json_from_string( $curl_output );
						break;
					case 'xml':

						$data_out = json_decode( json_encode( (array) simplexml_load_string( $curl_output ) ), 1 );
						if ( $data_out === null ) {
							throw new \Exception( "failed to decode as xml: $curl_output" );
						}
						break;
					default:
						{
							$data_out = $curl_output;
						}
				}
			} catch ( \Exception $c ) {
				$data_out = $curl_output;
			}


			if ( $http_code >= 500 ) {
				throw new CurlHelperException( $data_out, 'Server had error', $http_code );
			}


			return $data_out;
		} finally {
			//reset curl options
			curl_reset($this->ch);
			//re add cookie jar
			curl_setopt($this->ch, CURLOPT_COOKIEJAR, realpath($this->cookie_file));
			curl_setopt($this->ch, CURLOPT_COOKIEFILE, realpath($this->cookie_file));
			if ($this->user_agent) {
				curl_setopt ($this->ch, CURLOPT_USERAGENT, $this->user_agent);
			}
		}
	}
}

//CURLOPT_CONNECTTIMEOUT 