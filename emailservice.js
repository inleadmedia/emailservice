/**
 * @file
 */

// Defining base elements variables.
var email_address = document.getElementsByName('email_address');
var subscribe_button = document.getElementsByName('subscribe');
var mailinglist_id = document.getElementsByName('mailinglist_id');
var throbber = document.getElementsByClassName('loader');

if (subscribe_button.length) {
  // Disabling subscription button by default.
  subscribe_button[0].setAttribute('disabled', true);

  // Trigger form validation on blur event.
  email_address[0].addEventListener('blur', function (event) {
    // Define email variable.
    var email = event.target.value;
    var mailinglist = mailinglist_id[0].value;

    // Check email string validity.
    if (isValidEmailAddress(email)) {

      // Set the loader to indicate some progress.
      var loader = document.createElement('img');
      loader.src = "modules/custom/emailservice/assets/throbber_12.gif";
      loader.setAttribute('style', 'width: 16px; height: 16px;')
      throbber[0].appendChild(loader);

      // Prepare request.
      const HTTP = new XMLHttpRequest();
      const url = '/check-subscriber';
      var params = ['email=' + email + '&mailinglist=' + mailinglist];
      HTTP.open('POST', url, true);
      HTTP.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      HTTP.send(params.toString());

      // Processing data received as response.
      HTTP.onreadystatechange = function () { // Call a function when the state changes.
        if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
          // Getting values from JSON response.
          var response = JSON.parse(this.response);

          // Remove loader when request is finished.
          loader.remove();

          // Process 'status' values.
          switch (response.status) {
            case 'existing':
              subscribe_button[0].setAttribute('disabled', true);
              email_address[0].classList.add('is-invalid');

              var error = document.createElement('div');
              error.classList.add('invalid-feedback');
              error.innerText = response.message;

              email_address[0].parentNode.replaceChild(error, email_address[0].nextSibling);

              break;

            case 'not-existing':
              email_address[0].classList.remove('is-invalid');
              email_address[0].classList.add('is-valid');

              var success = document.createElement('div');
              success.classList.add('valid-feedback');

              email_address[0].parentNode.replaceChild(success, email_address[0].nextSibling);

              subscribe_button[0].removeAttribute('disabled');
              break;

            case 'not-valid':
              subscribe_button[0].setAttribute('disabled', true);
              email_address[0].classList.add('is-invalid');

              var error = document.createElement('div');
              error.classList.add('invalid-feedback');
              error.innerText = response.message;

              email_address[0].parentNode.replaceChild(error, email_address[0].nextSibling);
              break;
          }
        }
      }
    }
    else {
      // Clear all additional theming.
      subscribe_button[0].setAttribute('disabled', true);
      email_address[0].classList.add('is-invalid');
    }
  });
}

// Validate email address.
function isValidEmailAddress(emailAddress) {
  var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
  return pattern.test(emailAddress);
}
