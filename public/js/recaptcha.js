/**
 * Get the submit buttons from the given form
 */
function getSubmitButtons(form) {
    const buttons = form.querySelectorAll('button, input');
    const submitButtons = [];
    for (let i= 0; i < buttons.length; i++) {
        const button = buttons[i];
        if (button.getAttribute('type') == 'submit') {
            submitButtons.push(button);
        }
    }
    return submitButtons;
}
/**
 * Prevent the submit buttons from submitting a form
 * and invoke the challenge for the given widget id
 */
function bindChallengeToSubmitButtons(form, reCaptchaId) {
    getSubmitButtons(form).forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            grecaptcha.execute(reCaptchaId);
        });
    });
}
/**
 * Render a reCAPTCHA from the type
 */
function renderReCaptcha(widget) {
    const form = widget.closest('form');
    const widgetType = widget.getAttribute('data-type');
    const widgetParameters = {
        'sitekey': gg_recaptcha_site_key
    };
    if (widgetType == 'invisible') {
        widgetParameters['callback'] = function () {
            form.submit()
        };
        widgetParameters['size'] = "invisible";
    }
    const widgetId = grecaptcha.render(widget, widgetParameters);
    if (widgetType == 'invisible') {
        bindChallengeToSubmitButtons(form, widgetId)
    }
}
/**
 * The callback function executed
 * once all the Google dependencies have loaded
 */
function onGoogleReCaptchaLoad() {
    const widgets = document.querySelectorAll('[data-toggle="recaptcha"]');
    for (let i = 0; i < widgets.length; i++) {
        renderReCaptcha(widgets[i]);
    }
}