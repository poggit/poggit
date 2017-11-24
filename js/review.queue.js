$(function() {
    var reviewReleases = $("#review-releases");
    if(reviewReleases.find('> div').length > 16) {
        if(getParameterByName("usePages", sessionData.opts.usePages !== false ? "on" : "off") === "on") {
            reviewReleases.paginate({
                perPage: 16,
                scope: $('div') // targets all div elements
            });
        }
    }
});
