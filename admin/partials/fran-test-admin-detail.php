<?php


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
global $fran_test_details_object;

$survey_obj = $fran_test_details_object['survey'];
$questions = $fran_test_details_object['answers'];
?>


<div class="fran-test-chart">


    <h3>Questions</h3>
    <table class="fran-test-answers">
        <tbody>
    <?php
    foreach ($questions as $qob) {
        $question = $qob['question'];
        $question_number = $qob['question_id'];
        $promt = $qob['promt'];
        $answer = strval($qob['answer']);
        if ($answer == $promt) {
            $promt = '';
        }
    ?>
        <tr>
            <td>
                <span class="fran-question-number"> <?=$question_number ?></span>
                <span class="fran-question-text"> <?= $question ?></span>
            </td>
            <td>
                <span class="fran-answer" ><?=$answer ?></span>

            </td>
        </tr>

    <?php } ?>
    </tbody>
    </table>
</div>
