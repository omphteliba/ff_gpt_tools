document.addEventListener("DOMContentLoaded", function () {
    var selectAllRadio = document.getElementById("rex-form-select_all");
    var selectEmptyRadio = document.getElementById("rex-form-select_empty");
    var selectOneRadio = document.getElementById("rex-form-pages_select_one");
    var selectListRadio = document.getElementById("rex-form-pages_select_list");
    // var selectCatRadio = document.getElementById("rex-form-pages_select_cat");

    var linkElement = document.querySelector("input[id='REX_LINK_1_NAME']");
    var mediaElement = document.querySelector("input[id='REX_MEDIA_1']");
    var linkListElement = document.querySelector("select[id='REX_LINKLIST_SELECT_2']");
    var mediaListElement = document.querySelector("select[id='REX_MEDIALIST_SELECT_2']");

    var articleWidget = [
        linkElement ? linkElement.closest('.rex-form-container') : null,
        mediaElement ? mediaElement.closest('.rex-form-container') : null
    ].filter(Boolean);

    var articleListWidget = [
        linkListElement ? linkListElement.closest('.rex-form-container') : null,
        mediaListElement ? mediaListElement.closest('.rex-form-container') : null
    ].filter(Boolean);

    // Function to handle the visibility of widgets based on the selected radio button
    function handleWidgetVisibility() {
        if (selectAllRadio.checked || selectEmptyRadio.checked) {
            articleWidget.forEach(function (widget) {
                widget.style.display = "none";
            });
            articleListWidget.forEach(function (widget) {
                widget.style.display = "none";
            });
        } else if (selectOneRadio.checked) {
            articleWidget.forEach(function (widget) {
                widget.style.display = "table";
            });
            articleListWidget.forEach(function (widget) {
                widget.style.display = "none";
            });
        } else if (selectListRadio.checked) {
            articleWidget.forEach(function (widget) {
                widget.style.display = "none";
            });
            articleListWidget.forEach(function (widget) {
                widget.style.display = "table";
            });
        } else {
            // If none of the above conditions are true, hide the media_list widget by default
            articleListWidget.forEach(function (widget) {
                widget.style.display = "none";
            });
        }
    }

    // Initial visibility based on the default selected radio button
    handleWidgetVisibility();

    // Event listener for radio button change
    [selectAllRadio, selectEmptyRadio, selectOneRadio, selectListRadio].forEach(function (radio) {
        radio.addEventListener("change", handleWidgetVisibility);
    });
});
