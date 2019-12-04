if (!jq) {
	var jq = $;
}

String.prototype.nohtml = function () {
	return this + (this.indexOf('?') == -1 ? '?' : '&') + 'no_html=1';
};

var radiam = {};

jQuery(document).ready(function (jq) {
    var $ = jq;
    $(".show-all").click(function(event) {
        $(".extra-metadata-container").css("display", "block");
        $(".hide-all").css("display", "block");
        $(".hide").show();
        $(".show").hide();
        $(event.currentTarget).hide();
    });

    $(".hide-all").click(function(event) {
        $(".extra-metadata-container").hide();
        $(".show-all").show();
        $(".show").show();
        $(".hide").hide();
        $(event.currentTarget).hide();
    });

    $(".show").click(function(event) {
        $(event.currentTarget).parent().siblings(".extra-metadata-container").css("display", "block");
        $(event.currentTarget).siblings(".hide").css("display", "inline");
        $(event.currentTarget).hide();
    });

    $(".hide").click(function(event) {
        $(event.currentTarget).parent().siblings(".extra-metadata-container").hide();
        $(event.currentTarget).siblings(".show").css("display", "inline");
        $(event.currentTarget).hide();
    });

    $("#projects").change(function(event) {
        var newLocation = location.origin + location.pathname + "?project=" + event.currentTarget.value;
        window.location.href = newLocation;
    });
});
