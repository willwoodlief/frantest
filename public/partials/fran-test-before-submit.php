<?php
$error = false;
$head = plugin_dir_path(dirname(__FILE__));
require_once  $head . 'partials/fran-test-before-submit.php';

   try {

       //see if this is from a param call
       if ($_GET && isset($_GET['survey-step']) && $_GET['survey-step']) {
           $override_key = trim($_GET['survey-step']);
       } else {
           $override_key = null;
       }

	   if ( isset( $_COOKIE['fran_test'] ) && ! empty( $_COOKIE['fran_test'] ) ) {
		   $survey_key = $_COOKIE['fran_test'];
	   } else {
		   $survey_key = null;
	   }

	   $public_test = new FranTestPublic( $survey_key );
	   // if cookie has a quiz set then pull in the quiz in progress instead of making a new one

	   $json = $public_test->get_survey_questions();

	   $start_step = 0;
	   //find any last completed step
	   for ( $i = 0; $i < sizeof( $json ); $i ++ ) {
		   if ( ! $json[ $i ]['response'] ) {
			   break; //break on first empty response
		   }
	   }

	   $start_step = $i;

	   //build a reference to the steps
       $step_ref = [];
       $step_array = [];
        for ( $i = 0; $i < sizeof( $json ); $i ++ ) {
            $node =  $json[ $i ];
            $step_ref[$node['shortcode']] = $node;
	        $step_ref[$node['shortcode']]['index'] = $i;
	        $step_array[] = $node['shortcode'];
        }

        $percentage = (100.0/sizeof( $json ));

        if ($override_key) {
            //redo start step if it fits
            if (array_key_exists($override_key,$step_ref)) {
                //its a valid step
	            $start_step = $step_ref[$override_key]['index'];
            }
        }

	   $question_json = json_encode( $json );
        $step_ref_json = json_encode( $step_ref );
	   $step_array_json = json_encode( $step_array );
	   $options = get_option( 'fran_test_options' );
	   if (isset($options['redirect_url']) && (!empty($options['redirect_url']))) {
		   $redirect_url = $options['redirect_url'];
       } else {
	       $redirect_url = null;
       }

	   if (isset($options['text_color']) && (!empty($options['text_color']))) {
		   $text_color = $options['text_color'];
	   } else {
		   $text_color = 'black';
	   }



   } catch (Exception $e) {
	   $error = $e->getMessage() ." [ {$e->getFile()} {$e->getLine()} ]";
       return;
   }
?>

<?php if ($error) {  ?>
    <div style="font-size: large; font-weight: bold; border: red solid 1px ; padding: 1em; width: 100%; text-align: center">
        <?= $error ?>
    </div>
<?php } ?>
<link rel="stylesheet" property="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.css">
<link rel="stylesheet" property="stylesheet" href="<?= plugins_url()?>/fran-test/public/css/barfiller_style.css">
<script type='text/javascript' src='<?= plugins_url()?>/fran-test/public/js/jquery.barfiller.js'></script>
<script>
    var questions = <?= $question_json ?>;
    var step_reference = <?= $step_ref_json ?>;
    var step_array  = <?= $step_array_json ?>;
    var start_step = <?= $start_step ?>;
    var origonal_title = null;
    var survey_id = <?= $public_test->survey_id ?>;
    var redirect_url = "<?= $redirect_url ?>";
    var text_color = "<?= $text_color ?>";
    var base_percentage = <?= $percentage ?>;


    Cookies.set('fran_test', '<?= $public_test->anon_key ?>', { expires: 1, path: '' });


    jQuery(function($) {

        function updateText(event){
            var input=$(this);
            setTimeout(function(){
                var val=input.val();
                if(val!=="")
                    input.parent().addClass("floating-placeholder-float");
                else
                    input.parent().removeClass("floating-placeholder-float");
            },1)
        }
        $(".floating-placeholder input").keydown(updateText);
        $(".floating-placeholder input").change(updateText);


        ElementQueries.init(); //start the media query listeners

        $('#bar1').barfiller({duration:1000});
        if (start_step > 0) {
            for(var j=0; j < start_step; j++) {
        //        jQuery('#bar1').barfiller('refill');
            }
        }

        origonal_title = document.title;
        // Revert to a previously saved state
        setTimeout(function() {
            window.addEventListener('popstate', function(event) {
               // console.log('popstate fired! ' + event.state);
                var the_state = event.state;
                if (event.state === null) {
                    the_state = null;
                }
                set_question(the_state,false);
            });
        },500);

        set_question(null,true);

        $("#fran-test-ask-words").click(function() {
            var first_name = jQuery( "input[name='fran-test-ask-first-name']" ).val();
            var last_name = jQuery( "input[name='fran-test-ask-last-name']" ).val();
            var email = jQuery( "input[name='fran-test-ask-email']" ).val();
            var phone = jQuery( "input[name='fran-test-ask-phone']" ).val();

            if ((!first_name.trim()) || (!email.trim() ) ) {
                // shake
                $('div.fran-test-ask-words').addClass('shake').one('webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend',
                    function(){
                        $(this).removeClass('shake');
                    }
                );

                $('span.fran-test-need-input').show();
                return;
            } else {
                $('span.fran-test-need-input').hide();
            }

            var pack = {
                survey_id: survey_id,
                first_name: first_name,
                last_name: last_name,
                email: email,
                phone: phone
            };

            function on_success(data) {
                finish_quiz();
            }

            fran_test_talk_to_frontend('survey_words', pack ,on_success);

        });



    });

    function set_final_box() {
        var answers = jQuery('div.fran-test-answers');
        answers.html('');
        jQuery('div.fran-test-ask-words').show();
    }

    function set_question(question_nick,b_add_history) {

        if (!question_nick) {
            question_nick = step_array[start_step];
        } else {
           start_step = step_reference[question_nick].index;
        }
        var node = step_reference[question_nick];



        var percentage = base_percentage * (start_step );
        if (start_step === step_array.length -1) {
            percentage = 99;
        }
        jQuery('#bar1').barfiller('advance', percentage);
        jQuery('div.frant-test-progress-detail').text("Question " + (start_step + 1) + " Out Of " + step_array.length + " " );



        jQuery('div.fran-test-question').text(node.question);
        if ( (!question_nick) || (start_step === (step_array.length -1)) ) {
            set_final_box();

        } else {
            jQuery('div.fran-test-ask-words').hide();

            start_step ++;
            var next_state = step_array[start_step];


            do_answers(node.answers,next_state);
        }




        if (b_add_history) {
            var title = origonal_title + ': ' + node.question;
            history.pushState(question_nick,title , 'survey-' + question_nick);

            try {

                document.getElementsByTagName('title')[0].innerHTML = title.replace('<','&lt;').replace('>','&gt;').replace(' & ',' &amp; ');
            }
            catch ( Exception ) { }
            document.title = title;
        }


    }

    function do_answers(answers,next_state) {
        var par = jQuery('div.fran-test-answers');
        par.html('');

        for(var i = 0; i < answers.length; i++) {
            var ans = answers[i];
            var da_ans_div =  jQuery("<div></div>").
                                css({  backgroundColor: ans.color,color: text_color });
            da_ans_div.addClass('fran-test-base');
            if (ans.response) {
                da_ans_div.addClass('fran-test-highlight');
            }
            da_ans_div.text(ans.words);

            if (answers.length <= 2) {
                da_ans_div.addClass('fran-test-binary');

            } else if (answers.length <= 6) {
                da_ans_div.addClass('fran-test-few');
            }
            else {
                da_ans_div.addClass('fran-test-multiple');
            }
            da_ans_div.addClass('fran_answer_itself');
            da_ans_div.data('state',next_state);
            da_ans_div.data('this_state',ans.shortcode);
            da_ans_div.data('answer_id',ans.answer_id);
            da_ans_div.data('answer_index',i);
            da_ans_div.data('question_id',ans.question_id);

            par.append(da_ans_div);
        }




        jQuery("div.fran_answer_itself").click(function() {
            var next_state = jQuery(this).data('state');
            var this_state = jQuery(this).data('this_state');
            var answer_id = jQuery(this).data('answer_id');
            var question_id = jQuery(this).data('question_id');
            var answer_index = jQuery(this).data('answer_index');
            var node = step_reference[this_state];
            var pack = {
                survey_id: survey_id,
                question_id: question_id,
                answer_id: answer_id
            };

            function on_success(data) {

                for(var i = 0; i < node.answers.length; i++) {
                    node.answers[i].response_id = null;
                }
                var answer = node.answers[answer_index];
                answer.response_id = data.response_id;
            }

            fran_test_talk_to_frontend('survey_answer', pack ,on_success);
            set_question(next_state,true);
        });
    }

    function finish_quiz() {
        jQuery('div.fran-test-ask-words').hide();
        if (redirect_url) {
            location.href = redirect_url;
        }

    }

</script>

<script type='text/javascript' src='<?= plugins_url()?>/fran-test/public/js/ResizeSensor.js'></script>
<script type='text/javascript' src='<?= plugins_url()?>/fran-test/public/js/ElementQueries.js'></script>






<div class="fran-test-public-wrapper"  style="width: 100%; margin: auto">
    <div class="fran-test-header"></div>
    <div class="fran-test-progress">
        <div class="frant-test-progress-detail"></div>
        <div id="bar1" class="barfiller">
            <span class="tip"></span>
            <span class="fill" data-percentage="0"></span>
        </div>
    </div>
    <div class="fran-test-quiz-holder" style="">
        <div class="fran-test-question"></div>
        <div class="fran-test-answers"></div>
        <div class="fran-test-ask-words animated">

                <input type="text" name="fran-test-ask-first-name" title="Your Name"  autocomplete="given-name" placeholder="First Name">
                <br>



                <input type="text" name="fran-test-ask-last-name" title="Your Name"  autocomplete="family-name" placeholder="Last Name">
            <br>



                <input type="email" name="fran-test-ask-email" title="Your Email"   autocomplete="email" placeholder="Email">

            <br>


                <input type="tel" name="fran-test-ask-phone" title="Contact Phone"  autocomplete="tel" placeholder="Phone">

            <br>

            <button type="button" id="fran-test-ask-words" class="big-button2"><span>Thank You!</span></button> <br>
            <span class="fran-test-need-input" style="display: none; color:red">Need At Least Email and First Name</span>
        </div>
    </div>
    <div class="log">

    </div>
</div>



