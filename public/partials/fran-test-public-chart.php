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
global $survey_obj;
$text = get_option('fran_test_finished_chart');
?>


<div class="fran-test-chart">
    <h2> Code <?= $survey_obj->key ?> </h2>
    <div>
        <?= $text ?>
    </div>

    <div class="chartjs-wrapper">
        <canvas id="chartjs-3" class="chartjs" width="undefined" height="undefined"></canvas>
        <script>
            new Chart(document.getElementById("chartjs-3"),
                {
                    type: "radar",
                    data:
                        {
                            labels: ["Autonomie", "Competentie", "Sociale Verbondenheid", "Fysieke Vrijheid", "Emotioneel Welbevinden", "Energie" ],
                            datasets: [
                                {
                                    label: "<?= $survey_obj->key ?>",
                                    data: [
                                        <?= $survey_obj->autonomie ?>,
                                        <?= $survey_obj->competentie ?>,
                                        <?= $survey_obj->sociale_verbondenheid ?>,
                                        <?= $survey_obj->fysieke_vrijheid ?>,
                                        <?= $survey_obj->emotioneel_welbevinden ?>,
                                        <?= $survey_obj->energie ?>
                                    ],
                                    fill: true,
                                    backgroundColor: "rgba(54, 162, 235, 0.2)",
                                    borderColor: "rgb(54, 162, 235)",
                                    pointBackgroundColor: "rgb(54, 162, 235)",
                                    pointBorderColor: "#fff",
                                    pointHoverBackgroundColor: "#fff",
                                    pointHoverBorderColor: "rgb(54, 162, 235)"
                                }

                            ]
                        },
                    options:
                        {
                            elements:
                                {
                                    line:
                                        {
                                            "tension": 0,
                                            "borderWidth": 3
                                        }
                                },
                            scale: {
                                ticks: {
                                    min: 0
                                }
                            }
                        }
                });
        </script>
    </div>
</div>
