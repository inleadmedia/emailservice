/**
 * @file
 */

// Defining base elements variables.
var email_address = document.getElementsByName('email_address');
var subscribe_button = document.getElementsByName('subscribe');
var mailinglist_id = document.getElementsByName('mailinglist_id');
var throbber = document.getElementsByClassName('loader');
var checkboxes = document.getElementsByClassName('form-checkbox');
var update_button = document.getElementsByName('update');

// Define prototype of find() function for IE.
if (!Array.prototype.find) {
  Object.defineProperty(Array.prototype, 'find', {
    value: function(predicate) {
      // 1. Let O be ? ToObject(this value).
      if (this == null) {
        throw new TypeError('"this" is null or not defined');
      }

      var o = Object(this);

      // 2. Let len be ? ToLength(? Get(O, "length")).
      var len = o.length >>> 0;

      // 3. If IsCallable(predicate) is false, throw a TypeError exception.
      if (typeof predicate !== 'function') {
        throw new TypeError('predicate must be a function');
      }

      // 4. If thisArg was supplied, let T be thisArg; else let T be undefined.
      var thisArg = arguments[1];

      // 5. Let k be 0.
      var k = 0;

      // 6. Repeat, while k < len
      while (k < len) {
        // a. Let Pk be ! ToString(k).
        // b. Let kValue be ? Get(O, Pk).
        // c. Let testResult be ToBoolean(? Call(predicate, T, « kValue, k, O »)).
        // d. If testResult is true, return kValue.
        var kValue = o[k];
        if (predicate.call(thisArg, kValue, k, o)) {
          return kValue;
        }
        // e. Increase k by 1.
        k++;
      }

      // 7. Return undefined.
      return undefined;
    }
  });
}

if (subscribe_button.length !== 0 || update_button.length !== 0) {
  // Disabling subscription button by default.
  if (subscribe_button.length !== 0) {
    subscribe_button[0].setAttribute('disabled', true);
  }
  if (update_button.length !== 0) {
    update_button[0].setAttribute('disabled', true);
  }

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
          loader.outerHTML = '';

          // Process 'status' values.
          switch (response.status) {
            case 'existing':
              if (subscribe_button.length) {
                subscribe_button[0].setAttribute('disabled', 'disabled');
              }

              if (update_button.length) {
                update_button[0].setAttribute('disabled', 'disabled');
              }
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

              break;

            case 'not-valid':
              if (subscribe_button.length) {
                subscribe_button[0].setAttribute('disabled', 'disabled');
              }

              if (update_button.length) {
                update_button[0].setAttribute('disabled', 'disabled');
              }
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

  /* Disallow form submit if no preferences are selected. */
  // Get preferences wrapper.
  var preferences_wrapper = document.getElementById('preferences_wrapper');
  var feedback_holder = document.getElementById('preferences-error');
  // Get states of checkboxes in true or false.
  checkboxes = Array.prototype.map.call(checkboxes, function (checkbox) {
    return checkbox;
  });

  // Check if there are already checked checkboxes.
  var default_checked = checkboxes.find(function (element) {
    return element.checked;
  });

  if (default_checked) {
    // If there are checked items, then we activating button.
    update_button[0].removeAttribute('disabled');
  }

  // Listen for events on preferences wrapper.
  preferences_wrapper.addEventListener('change', function () {
    // Return element in case there at least one item checked.
    var someChecked = checkboxes.find(function (element) {
      return element.checked;
    });

    // Activating or deactivating buttons.
    if (someChecked) {
      if (subscribe_button.length) {
        subscribe_button[0].removeAttribute('disabled');
      }

      if (update_button.length) {
        update_button[0].removeAttribute('disabled');
      }
      feedback_holder.style = 'display: none;';
    }
    else {
      if (subscribe_button.length) {
        subscribe_button[0].setAttribute('disabled', 'disabled');
      }

      if (update_button.length) {
        update_button[0].setAttribute('disabled', 'disabled');
      }

      feedback_holder.textContent = 'You have to pick at least one interest in order to subscribe.';
      feedback_holder.style = 'display: block !important;';
    }
  });
}

// Validate email address.
function isValidEmailAddress(emailAddress) {
  var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
  return pattern.test(emailAddress);
}
