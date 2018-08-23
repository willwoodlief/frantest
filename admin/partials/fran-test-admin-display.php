<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Fran_Test
 * @subpackage Fran_Test/admin/partials
 */
?>

<style>
    .slick-row {
        line-height: 16px;
    }

    .loading-indicator {
        display: inline-block;
        padding: 12px;
        background: white;
        -opacity: 0.5;
        color: black;
        font-weight: bold;
        z-index: 9999;
        border: 1px solid red;
        -moz-border-radius: 10px;
        -webkit-border-radius: 10px;
        -moz-box-shadow: 0 0 5px red;
        -webkit-box-shadow: 0px 0px 5px red;
        -text-shadow: 1px 1px 1px white;
    }

    .loading-indicator label {
        padding-left: 20px;
        background: url('http://6pac.github.io/SlickGrid/images/ajax-loader-small.gif') no-repeat center left;
    }
</style>

<div class="wrap">
    <h1>Franchise Test Options</h1>
    <div>
        <form method="post" action="options.php">
			<?php
			// This prints out all hidden setting fields
			settings_fields( 'fran-test-options-group' );
			do_settings_sections( 'fran-test-options' );
			submit_button();
			?>
        </form>
    </div>
</div>

<div class="fran-test-admin">
    <div class="fran-test-table">
        <div style="width:770px;height:700px;float:left;">
            <div class="grid-header" style="width:100%">
                <label>Survey Results Search</label>
                <span style="float:right;display:inline-block;">
                    Search Anonomous Key (partial or full) [press enter]: <input type="text" id="ecomhub-fi-search-table" value="">
                </span>
            </div>
            <div id="myGrid" style="width:100%;height:600px;"></div>
            <div id="pager" style="width:100%;height:20px;"></div>
        </div>


    </div>
    <div class="fran-test-detail">
        <div class="fran-test-stats">
            <span style="margin-bottom: 0.25em;font-weight: bold;font-size: larger"> Overall Stats for all Reports</span>
        </div>
        <div class="fran-test-details-here">

        </div>
    </div>
</div>



<script>

    var grid, s;

    jQuery(function () {
        var loader = new Slick.Data.RemoteModel();
        var dobFormatter = function (row, cell, value, columnDef, dataContext) {
            let d = new Date(dataContext.dob_ts * 1000);
            let s = '<span>' + d.toLocaleDateString()+  '</span>';
            return s;
        };

        var dateFormatter = function (row, cell, value, columnDef, dataContext) {
            let d = new Date(dataContext.created_at_ts * 1000);
            let s = '<span>' + d.toLocaleDateString()+  '</span>';
            return s;
        };
        var brandFormatter = function (row, cell, value, columnDef, dataContext) {
            return dataContext.brand.name;
        };

        function CheckmarkFormatter(row, cell, value, columnDef, dataContext) {
            if (value === '0') {

                return dataContext.number_completed;
            }
            return value === '1' ? "<img src='../wp-content/plugins/fran-test/admin/css/tick.png'>" : "";
        }

        var my_columns = [

            {id: "created_at_ts", name: "created at", field: "created_at_ts", formatter: dateFormatter, width: 90, sortable: true},
            {id: "anon_key", name: "Key", field: "anon_key", formatter: null, width: 80, sortable: true},
            {id: "first_name", name: "First", field: "first_name", formatter: null, width: 110, sortable: true},
            {id: "last_name", name: "Last", field: "last_name", formatter: null, width: 110, sortable: true},
            {id: "survey_email", name: "Email", field: "survey_email", formatter: null, width: 180, sortable: true},
            {id: "phone", name: "Phone", field: "phone", formatter: null, width: 100, sortable: true},
            {id: "is_completed", name: "Completed", field: "is_completed", formatter: CheckmarkFormatter, width: 100, sortable: true}




        ];
        var options = {
            rowHeight: 21,
            editable: false,
            enableAddRow: false,
            enableCellNavigation: false,
            enableColumnReorder: false
        };
        var loadingIndicator = null;
        //console.log("body js fired");
        grid = new Slick.Grid("#myGrid", loader.data, my_columns, options);



        grid.onViewportChanged.subscribe(function (e, args) {
            var vp = grid.getViewport();
            loader.ensureData(vp.top, vp.bottom);
        });
        grid.onSort.subscribe(function (e, args) {
            loader.setSort(args.sortCol.field, args.sortAsc ? 1 : -1);
            var vp = grid.getViewport();
            loader.ensureData(vp.top, vp.bottom);
        });
        loader.onDataLoading.subscribe(function () {
            if (!loadingIndicator) {
                loadingIndicator = jQuery("<span class='loading-indicator'><label>Buffering...</label></span>").appendTo(document.body);
                var $g = jQuery("#myGrid");
                loadingIndicator
                    .css("position", "absolute")
                    .css("top", $g.position().top + $g.height() / 2 - loadingIndicator.height() / 2)
                    .css("left", $g.position().left + $g.width() / 2 - loadingIndicator.width() / 2);
            }
            loadingIndicator.show();
        });
        loader.onDataLoaded.subscribe(function (e, args) {
            for (var i = args.from; i <= args.to; i++) {
                grid.invalidateRow(i);
            }
            grid.updateRowCount();
            grid.render();
            loadingIndicator.fadeOut();
        });
        jQuery("#ecomhub-fi-search-table").keyup(function (e) {
            if (e.which == 13) {
                loader.setSearch(jQuery(this).val());
                var vp = grid.getViewport();
                loader.ensureData(vp.top, vp.bottom);
            }
        });
        loader.setSearch(jQuery("#ecomhub-fi-search-table").val());
        loader.setSort("created_at_ts", -1);
        grid.setSortColumn("created_at_ts", false);
        // load the first page
        grid.onViewportChanged.notify();

        grid.onClick.subscribe(function (e, args) {
            grid.setSelectedRows([args.row]);
            var tim = grid.getDataItem(args.row);

            fran_test_talk_to_backend('detail', {id:tim.id}, function (data){
                jQuery('div.fran-test-details-here').html(data.html);
            });

        });

        grid.setSelectionModel(new Slick.RowSelectionModel({
            selectActiveRow: false
        }));
        // grid.invalidateAllRows();
        // grid.invalidate();
        // grid.render();
    })

</script>
