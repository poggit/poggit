/*
 jquery.paginate

 Description: Add a pagination to everything.
 Version: Version 0.3.0
 Author: Kevin Eichhorn (https://github.com/neighbordog)
 */

(function($) {

    $.paginate = function(element, options) {

        /*
         #Defaults
         */
        var defaults = {
            perPage: 16,              //how many items per page
            autoScroll: false,           //boolean: scroll to top of the container if a user clicks on a pagination link
            scope: '',             //which elements to target
            paginatePosition: ['top', 'bottom'],     //defines where the pagination will be displayed
            containerTag: 'nav',
            paginationTag: 'ul',
            itemTag: 'li',
            linkTag: 'a',
            useHashLocation: true,           //Determines whether or not the plugin makes use of hash locations
            onPageClick: function() {
            }   //Triggered when a pagination link is clicked

        };

        var plugin = this;
        var plugin_index = $('.paginate').length;

        plugin.settings = {};

        var $element = $(element);

        var curPage, items, offset, maxPage;

        /*
         #Initliazes plugin
         */
        plugin.init = function() {
            plugin.settings = $.extend({}, defaults, options);

            curPage = 1;
            items = $element.children(plugin.settings.scope);
            maxPage = Math.ceil(items.length / plugin.settings.perPage); //determines how many pages exist

            var paginationHTML = generatePagination(); //generate HTML for pageination

            if($.inArray('top', plugin.settings.paginatePosition) > -1) {
                $element.before(paginationHTML);
            }

            if($.inArray('bottom', plugin.settings.paginatePosition) > -1) {
                $element.after(paginationHTML);
            }

            $element.addClass("paginate");
            $element.addClass("paginate-" + plugin_index);

            var hash = location.hash.match(/\#paginate\-(\d)/i);

            //Check if URL has matching location hash
            if(hash && plugin.settings.useHashLocation) {
                plugin.switchPage(hash[1]);
            } else {
                plugin.switchPage(1); //go to initial page
            }

        };

        /*
         #Switch to Page > 'page'
         */
        plugin.switchPage = function(page) {

            if(page == "next") {
                page = parseInt(curPage) + 1;
            }

            if(page == "prev") {
                page = parseInt(curPage) - 1;
            }

            //If page is out of range return false
            if(page < 1 || page > maxPage) {
                return false;
            }

            if(page > maxPage) {
                $('.paginate-pagination-' + plugin_index).find('.page-next').addClass("deactive");
                return false;
            } else {
                $('.paginate-pagination-' + plugin_index).find('.page-next').removeClass("deactive");
            }

            $('.paginate-pagination-' + plugin_index).find('.active').removeClass('active');
            $('.paginate-pagination-' + plugin_index).find('.page-' + page).addClass('active');

            offset = (page - 1) * plugin.settings.perPage;

            $(items).hide();

            //Display items of page
            for(i = 0; i < plugin.settings.perPage; i++) {
                if($(items[i + offset]).length)
                    $(items[i + offset]).fadeTo(100, 1);
            }

            //Deactive prev button
            if(page == 1) {
                $('.paginate-pagination-' + plugin_index).find('.page-prev').addClass("deactive");
            } else {
                $('.paginate-pagination-' + plugin_index).find('.page-prev').removeClass("deactive");
            }

            //Deactive next button
            if(page == maxPage) {
                $('.paginate-pagination-' + plugin_index).find('.page-next').addClass("deactive");
            } else {
                $('.paginate-pagination-' + plugin_index).find('.page-next').removeClass("deactive");
            }

            curPage = page;

            return curPage;

        };

        /*
         #Kills plugin
         */
        plugin.kill = function() {

            $(items).show();
            $('.paginate-pagination-' + plugin_index).remove();
            $element.removeClass('paginate');
            $element.removeData('paginate');

        };

        /*
         #Generates HTML for pagination (nav)
         */
        var generatePagination = function() {

            var paginationEl = '<' + plugin.settings.containerTag + ' class="paginate-pagination paginate-pagination-' + plugin_index + '" data-parent="' + plugin_index + '">';
            paginationEl += '<' + plugin.settings.paginationTag + '>';

            paginationEl += '<' + plugin.settings.itemTag + '>';
            paginationEl += '<' + plugin.settings.linkTag + ' href="#" data-page="prev" class="page page-prev">&laquo;</' + plugin.settings.linkTag + '>';
            paginationEl += '</' + plugin.settings.itemTag + '>';

            for(i = 1; i <= maxPage; i++) {
                paginationEl += '<' + plugin.settings.itemTag + '>';
                paginationEl += '<' + plugin.settings.linkTag + ' href="#paginate-' + i + '" data-page="' + i + '" class="page page-' + i + '">' + i + '</' + plugin.settings.linkTag + '>';
                paginationEl += '</' + plugin.settings.itemTag + '>';
            }

            paginationEl += '<' + plugin.settings.itemTag + '>';
            paginationEl += '<' + plugin.settings.linkTag + ' href="#" data-page="next" class="page page-next">&raquo;</' + plugin.settings.linkTag + '>';
            paginationEl += '</' + plugin.settings.itemTag + '>';

            paginationEl += '</' + plugin.settings.paginationTag + '>';
            paginationEl += '</' + plugin.settings.containerTag + '>';

            //Adds event listener for the buttons
            $(document).on('click', '.paginate-pagination-' + plugin_index + ' .page', function(e) {
                e.preventDefault();

                var page = $(this).data('page');
                var paginateParent = $(this).parents('.paginate-pagination').data('parent');

                //Call onPageClick callback function
                $('.paginate-' + paginateParent).data('paginate').settings.onPageClick();

                page = $('.paginate-' + paginateParent).data('paginate').switchPage(page);

                if(page) {
                    if(plugin.settings.useHashLocation)
                        location.hash = '#paginate-' + page; //set location hash

                    if(plugin.settings.autoScroll)
                        $('html, body').animate({scrollTop: $('.paginate-' + paginateParent).offset().top}, 'slow');

                }

            });

            return paginationEl;

        };

        plugin.init();
        window.onhashchange = function() {
            if(location.hash.length > 0) {
                plugin.switchPage(location.hash.match(/\#paginate\-(\d*)/i)[1]);
            } else {
                plugin.switchPage(1);
            }
        }
    };

    $.fn.paginate = function(options) {

        return this.each(function() {
            if(undefined === $(this).data('paginate')) {
                var plugin = new $.paginate(this, options);
                $(this).data('paginate', plugin);
            }
        });

    };

}(jQuery));
