document.addEventListener("DOMContentLoaded", function() {
    var selectAllRadio = document.getElementById("rex-form-select_all");
    var selectEmptyRadio = document.getElementById("rex-form-select_empty");
    var selectOneRadio = document.getElementById("rex-form-pages_select_one");
    var selectListRadio = document.getElementById("rex-form-pages_select_list");
    // var selectCatRadio = document.getElementById("rex-form-pages_select_cat");

    var articleWidget = document.querySelector("input[id='REX_LINK_1_NAME']").closest('.rex-form-container');
    var articleListWidget = document.querySelector("select[id='REX_LINKLIST_SELECT_2']").closest('.rex-form-container');

    // Function to handle the visibility of widgets based on the selected radio button
    function handleWidgetVisibility() {
        if (selectAllRadio.checked || selectEmptyRadio.checked) {
            articleWidget.style.display = "none";
            articleListWidget.style.display = "none";
        } else if (selectOneRadio.checked) {
            articleWidget.style.display = "table";
            articleListWidget.style.display = "none";
        } else if (selectListRadio.checked) {
            articleWidget.style.display = "none";
            articleListWidget.style.display = "table";
        }
    }

    // Initial visibility based on the default selected radio button
    handleWidgetVisibility();

    // Event listener for radio button change
    [selectAllRadio, selectEmptyRadio, selectOneRadio, selectListRadio].forEach(function(radio) {
        radio.addEventListener("change", handleWidgetVisibility);
    });
});
