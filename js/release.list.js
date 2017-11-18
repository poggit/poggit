$(function() {
    var sortMethods = [
        {category: "state-change-date", direction: "desc"}
    ];

    function filterReleaseResults() {
        var selectedCat = $('#category-list').val();
        var selectedCatName = $('#category-list option:selected').text();
        var selectedAPI = $('#api-list').val();
        var selectedAPIIndex = $('#api-list').prop('selectedIndex');
        if(selectedCat > 0) {
            $('#category-list').attr('style', 'background-color: #FF3333');
        }
        else {
            $('#category-list').attr('style', 'background-color: #FFFFFF');
        }
        if(selectedAPIIndex > 0) {
            $('#api-list').attr('style', 'background-color: #FF3333');
        }
        else {
            $('#api-list').attr('style', 'background-color: #FFFFFF');
        }
        $('.plugin-entry').each(function(idx, el) {
            var cats = $(el).children('#plugin-categories');
            var catArray = cats.attr("value").split(',');
            var apis = $(el).children('#plugin-apis');
            var apiJSON = apis.attr("value");
            var json = JSON.stringify(eval('(' + apiJSON + ')'));
            var apiArray = $.parseJSON(json);
            var compatibleAPI = false;
            for(var i = 0; i < apiArray.length; i++) {
                var sinceok = compareApis(apiArray[i][0], selectedAPI);
                var tillok = compareApis(apiArray[i][1], selectedAPI);
                if(sinceok <= 0 && tillok >= 0) {
                    compatibleAPI = true;
                    break;
                }
            }
            if((!catArray.includes(selectedCat) && Number(selectedCat) !== 0) || (selectedAPIIndex > 0 && !compatibleAPI)) {
                $(el).attr("hidden", true);
            } else {
                $(el).attr("hidden", false);
            }
        });
        var visiblePlugins = $('#main-release-list .plugin-entry:visible').length;
        // if(visiblePlugins === 0) {
            //alert("No Plugins Found Matching " + selectedAPI + " in " + selectedCatName);
        // }
        if($('#main-release-list .plugin-entry:hidden').length === 0 && visiblePlugins > 12) {
            if(getParameterByName("usePages", sessionData.opts.usePages !== false ? "on" : "off") === "on") {
                $('#main-release-list').paginate({
                    perPage: 12
                });
            }
        } else {
            if(!$.isEmptyObject($('#main-release-list').data('paginate'))) $('#main-release-list').data('paginate').kill();
        }
    }

    function sortReleases() {
        $("#main-release-list").find("> div").sortElements(function(a, b) {
            for(var i in sortMethods) {
                var method = sortMethods[i];
                var da = a.getAttribute("data-" + method.category);
                var db = b.getAttribute("data-" + method.category);
                var signum;
                if(method.category === "name"){
                    signum = da.toLowerCase() > db.toLowerCase() ? 1 : -1;
                }else if(method.category === "mean-review"){
                    signum = (da === "NAN" ? 2.5 : Number(da)) > (db === "NAN" ? 2.5 : Number(db)) ? 1 : -1;
                }else{
                    signum = Number(da) > Number(db) ? 1 : -1;
                }
                console.log(da, db, signum);
                if(da !== db) {
                    return method.direction === "asc" ? signum : -signum;
                }
            }
            return 1;
        });
    }

    function replicateSortRow() {
        var row = $(".release-sort-row-template").clone();
        row.removeClass("release-sort-row-template");
        row.find(".release-sort-row-close").click(function() {
            row.remove();
            updateSortRowClose();
        });
        row.appendTo($("#release-sort-list"));

        updateSortRowClose();
    }

    function updateSortRowClose() {
        $(".release-sort-row-close").css("display", $(".release-sort-row").length > 2 ? "inline" : "none");
    }

    $(".release-filter-select").change(filterReleaseResults);

    if($('#main-release-list').find('> div').length > 0) {
        filterReleaseResults();
    }

    var sortDialog = $("#release-sort-dialog");
    sortDialog.dialog({
        position: modalPosition,
        autoOpen: false,
        modal: true,
        width: 'auto',
        height: 'auto',
        buttons: {
            Sort: function() {
                sortMethods = [];
                $(".release-sort-row:not(.release-sort-row-template)").each(function() {
                    sortMethods.push({
                        category: $(this).find(".release-sort-category").val(),
                        direction: $(this).find(".release-sort-direction").val()
                    });
                });
                sortReleases();
                sortDialog.dialog("close");
            }
        },
        open: function(event, ui) {
            $('.ui-widget-overlay').bind('click', function() {
                $("#release-sort-dialog").dialog('close');
            });
        }
    });

    replicateSortRow();

    $("#release-sort-row-add").click(replicateSortRow);

    $("#release-sort-button").click(function() {
        sortDialog.dialog("open");
    });

    function doPluginSearch() {
        var searchText = encodeURIComponent($("#pluginSearch").val());
        var searchMode = $("#pluginSearchField").val();
        window.location = getRelativeRootPath() + searchMode + searchText;
    }

    var pluginSearch = $("#pluginSearch");
    pluginSearch.on("keyup", function(e) {
        if(e.keyCode === 13) {
            doPluginSearch();
        }
    });

    if(!window.matchMedia('(max-width: 900px)').matches) {
        pluginSearch.focus();
    }

    $("#searchButton").on("click", function(e) {
        doPluginSearch();
    });
    $("#searchAuthorsButton").on("click", function() {
        window.location = getRelativeRootPath() + "plugins/by/" + $("#searchAuthorsQuery").val();
    });
});
