$(function() {
    var scopes = $("input:checkbox.authScope");
    $("#checkAll").change(function() {
        scopes.prop("checked", $(this).prop("checked"));
    });
    scopes.each(function() {
        var $this = $(this);
        if(hasScopes.indexOf($this.attr("data-scope")) !== -1) {
            $this.prop("checked", true);
        }
    });
    $("#submitScopes").click(function() {
        var url = "https://github.com/login/oauth/authorize?client_id=" + getClientId() + "&state=" + getAntiForge() + "&scope=";
        url += encodeURIComponent(scopes.filter(":checked").map(function() {
            return this.getAttribute("data-scope");
        }).get().join());
        window.location = url;
    });
});
