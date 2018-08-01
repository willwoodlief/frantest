<?php
/**
 * @var {FranSurvey} $survey_obj
 */
global $survey_obj;

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/public/partials
 */
switch ($survey_obj->section_name) {
    case 'vitacheck': {
        $text = get_option('fran_test_vitacheck_text');
        $state = 'vitacheck';
        break;
    }
    case 'psychologische': {
        $text = get_option('fran_test_psychologische_text');
        $state = 'psychologische';
        break;
    }
    default:{
        $text = 'unknown section [plugin error]';
        break;
    }
}

?>

<div class="fran-test-questions">
    <h2> Survey </h2>
    <div class="fran-test-customized-header">
        <?= $text ?>
    </div>
    <input type="hidden" id="fran-test-survey-code" class="fran-test-code" value="<?= $survey_obj->survey_code ?>">
    <input type="hidden" id="fran-test-state-holder" class="fran-test-state-info" value="<?= $state ?>">
    <div class="fran-test-questions-list">
    <?php foreach ($survey_obj->loaded_questions as $question) {?>
        <?php print $survey_obj->generate_question_html($question); ?>
    <?php } ?>
    </div>

</div>
