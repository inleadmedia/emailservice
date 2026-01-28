/**
 * @file
 * Email service behaviors.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Email service form behavior.
   */
  Drupal.behaviors.emailserviceForm = {
    attach: function (context, settings) {
      // Process email subscription form only once.
      once('emailservice-form', '.emailservice-subscriber-form', context).forEach(function (form) {
        var email_address = form.querySelector('[name="email_address"]');
        var subscribe_button = form.querySelector('[name="subscribe"]');
        var mailinglist_id = form.querySelector('[name="mailinglist_id"]');
        var throbber = form.querySelector('.loader');
        var checkboxes = form.querySelectorAll('.form-checkbox');
        var update_button = form.querySelector('[name="update"]');
        var preferences_wrapper = form.querySelector('#preferences_wrapper');
        var feedback_holder = form.querySelector('#preferences-error');

        if (!subscribe_button && !update_button) {
          return;
        }

        // Disabling subscription button by default.
        if (subscribe_button) {
          subscribe_button.setAttribute('disabled', true);
        }
        if (update_button) {
          update_button.setAttribute('disabled', true);
        }

        // Check if there are already checked checkboxes.
        var checkboxArray = Array.prototype.slice.call(checkboxes);
        var default_checked = checkboxArray.find(function (element) {
          return element.checked;
        });

        if (default_checked && update_button) {
          // If there are checked items, then we activate button.
          update_button.removeAttribute('disabled');
        }

        // Trigger form validation on blur event.
        if (email_address) {
          email_address.addEventListener('blur', function (event) {
            var email = event.target.value;
            var mailinglist = mailinglist_id ? mailinglist_id.value : '';

            // Check email string validity.
            if (isValidEmailAddress(email)) {
              // Set the loader to indicate some progress.
              var loader = document.createElement('img');
              loader.src = drupalSettings.path.baseUrl + 'modules/custom/emailservice/assets/throbber_12.gif';
              loader.setAttribute('style', 'width: 16px; height: 16px;');
              if (throbber) {
                throbber.appendChild(loader);
              }

              // Prepare request.
              var HTTP = new XMLHttpRequest();
              var url = drupalSettings.path.baseUrl + 'check-subscriber';
              var params = 'email=' + encodeURIComponent(email) + '&mailinglist=' + encodeURIComponent(mailinglist);
              HTTP.open('POST', url, true);
              HTTP.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
              HTTP.send(params);

              // Processing data received as response.
              HTTP.onreadystatechange = function () {
                if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                  var response = JSON.parse(this.response);

                  // Remove loader when request is finished.
                  if (loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                  }

                  // Process 'status' values.
                  switch (response.status) {
                    case 'existing':
                      if (subscribe_button) {
                        subscribe_button.setAttribute('disabled', 'disabled');
                      }
                      if (update_button) {
                        update_button.setAttribute('disabled', 'disabled');
                      }
                      email_address.classList.add('is-invalid');

                      var error = document.createElement('div');
                      error.classList.add('invalid-feedback');
                      error.innerText = response.message;

                      if (email_address.nextSibling) {
                        email_address.parentNode.replaceChild(error, email_address.nextSibling);
                      } else {
                        email_address.parentNode.appendChild(error);
                      }
                      break;

                    case 'not-existing':
                      email_address.classList.remove('is-invalid');
                      email_address.classList.add('is-valid');

                      var success = document.createElement('div');
                      success.classList.add('valid-feedback');

                      if (email_address.nextSibling) {
                        email_address.parentNode.replaceChild(success, email_address.nextSibling);
                      } else {
                        email_address.parentNode.appendChild(success);
                      }
                      break;

                    case 'not-valid':
                      if (subscribe_button) {
                        subscribe_button.setAttribute('disabled', 'disabled');
                      }
                      if (update_button) {
                        update_button.setAttribute('disabled', 'disabled');
                      }
                      email_address.classList.add('is-invalid');

                      var errorMsg = document.createElement('div');
                      errorMsg.classList.add('invalid-feedback');
                      errorMsg.innerText = response.message;

                      if (email_address.nextSibling) {
                        email_address.parentNode.replaceChild(errorMsg, email_address.nextSibling);
                      } else {
                        email_address.parentNode.appendChild(errorMsg);
                      }
                      break;
                  }
                }
              };
            } else {
              // Clear all additional theming.
              if (subscribe_button) {
                subscribe_button.setAttribute('disabled', true);
              }
              email_address.classList.add('is-invalid');
            }
          });
        }

        // Listen for events on preferences wrapper.
        if (preferences_wrapper) {
          preferences_wrapper.addEventListener('change', function () {
            // Return element in case there at least one item checked.
            var someChecked = checkboxArray.find(function (element) {
              return element.checked;
            });

            // Activating or deactivating buttons.
            if (someChecked) {
              if (subscribe_button) {
                subscribe_button.removeAttribute('disabled');
              }
              if (update_button) {
                update_button.removeAttribute('disabled');
              }
              if (feedback_holder) {
                feedback_holder.style.display = 'none';
              }
            } else {
              if (subscribe_button) {
                subscribe_button.setAttribute('disabled', 'disabled');
              }
              if (update_button) {
                update_button.setAttribute('disabled', 'disabled');
              }
              if (feedback_holder) {
                feedback_holder.textContent = Drupal.t('You have to pick at least one interest in order to subscribe.');
                feedback_holder.style.display = 'block';
              }
            }
          });
        }
      });

      /**
       * Validate email address.
       */
      function isValidEmailAddress(emailAddress) {
        var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
        return pattern.test(emailAddress);
      }
    }
  };

})(Drupal, once);
