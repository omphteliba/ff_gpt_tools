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

    function checkIfLinkSelected() {
        // Get the value of the hidden input field.  We construct the ID dynamically
        // based on the linkId (which is 1 in your example). This makes the function reusable.
        const linkValue = document.getElementsByName('single_article')[0].value;

        // Check if the value is empty or not. A value of 0 means the "no link" option is selected
        // while an empty string "" means no selection has been made.  Any other value represents a selected article ID.
        return !!(linkValue && linkValue !== "");
    }

    function checkIfLinkListSelected() {
        // Get the value of the hidden input field.  We construct the ID dynamically
        // based on the linkId (which is 1 in your example). This makes the function reusable.
        const linkValue = document.getElementsByName('article_list')[0].value;

        // Check if the value is empty or not. A value of 0 means the "no link" option is selected
        // while an empty string "" means no selection has been made.  Any other value represents a selected article ID.
        return !!(linkValue && linkValue !== "");
    }

    function checkIfImageSelected() {
        // Get the value of the hidden input field.  We construct the ID dynamically
        // based on the linkId (which is 1 in your example). This makes the function reusable.
        const linkValue = document.getElementsByName('single_image')[0].value;

        // Check if the value is empty or not. A value of 0 means the "no link" option is selected
        // while an empty string "" means no selection has been made.  Any other value represents a selected article ID.
        return !!(linkValue && linkValue !== "");
    }

    function checkIfImageListSelected() {
        // Get the value of the hidden input field.  We construct the ID dynamically
        // based on the linkId (which is 1 in your example). This makes the function reusable.
        const linkValue = document.getElementsByName('image_list')[0].value;

        // Check if the value is empty or not. A value of 0 means the "no link" option is selected
        // while an empty string "" means no selection has been made.  Any other value represents a selected article ID.
        return !!(linkValue && linkValue !== "");
    }

    function getFuncValue() {
        let selectedValue = null;
        const radioButtons = document.querySelectorAll('input[name="func"]');

        radioButtons.forEach(radioButton => {
            if (radioButton.checked) {
                selectedValue = radioButton.value;
                // Break the loop once you find the checked radio button
                return; // Or use a traditional for loop and break there
            }
        });

        return selectedValue;
    }

    const form_meta_generate = document.getElementById('gpt_tools_meta_generate');
    if (form_meta_generate) {
        form_meta_generate.addEventListener('submit', function (event) {
            const funcValue = getFuncValue();

            if (funcValue === null) {
                alert("Please select an option.");
                event.preventDefault();
                return;
            }

            let errorMessage = ""; // Store the error message

            if (funcValue === "2" && !checkIfLinkSelected()) {
                errorMessage = "Please select an article.";
            } else if (funcValue === "3" && !checkIfLinkListSelected()) {
                errorMessage = "Please add articles to the article list.";
            }

            if (errorMessage) {
                // Display the error message to the user
                alert(errorMessage); // Or use a more visually appealing method
                event.preventDefault();
            }
        });
    }

    const form_image_description = document.getElementById('gpt_tools_image_description');
    if (form_image_description) {
        form_image_description.addEventListener('submit', function (event) {
            const funcValue = getFuncValue();

            if (funcValue === null) {
                alert("Please select an option.");
                event.preventDefault();
                return;
            }

            let errorMessage = ""; // Store the error message

            if (funcValue === "2" && !checkIfImageSelected()) {
                errorMessage = "Please select an image.";
            } else if (funcValue === "3" && !checkIfImageListSelected()) {
                errorMessage = "Please add images to the image list.";
            }

            if (errorMessage) {
                // Display the error message to the user
                alert(errorMessage); // Or use a more visually appealing method
                event.preventDefault();
            }
        });
    }

});
