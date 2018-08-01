<?php
    $head = plugin_dir_path(dirname(__FILE__));
   require_once  $head . 'partials/fran-test-before-submit.php';
   $public_test = new FranTestPublic();
   $json = $public_test->get_survey_questions();
   $question_json = json_encode($json);
?>


Shortcut Fran Test