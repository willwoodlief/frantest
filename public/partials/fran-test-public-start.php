<?php
 global $fran_test_custom_header;

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @since      1.0.0
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/public/partials
 */
  $start_text = get_option('fran_test_start_text');
?>

<div class="fran-test">
    <div class='fran-test-custom-header'> <?= $fran_test_custom_header ?></div>
    <div class="fran-test-html">
        <div class="fran-test-start">
            <div class='fran-test-custom-header fran-test-start-text'> <?= $start_text ?></div>
            <label for="fran-test-dob">Verjaardag</label><input type="date" name="fran-test-dob" id="fran-test-dob">
            <br>
            <label for="fran-test-code">Code</label><input type="text" name="fran-test-code" id="fran-test-code">
        </div>
    </div>
    <button id='fran-test-submit'> Submit </button>

</div>
