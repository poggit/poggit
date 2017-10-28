$(function() {
    $(".settings-cb").click(function() {
        var cb = this;
        this.disabled = true;
        ajax("opt.toggle", {
            data: {
                name: this.getAttribute("data-name"),
                value: this.checked ? "true" : "false"
            },
            success: function() {
                cb.disabled = false;
            }
        });
    });
});
