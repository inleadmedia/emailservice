# Emailservice
Signup/Update subscription form is accessible on "/sm" url, with municipality param.
> Example: "http://example.com/sm?municipality=skanbib"

### Features
* Only users with role "Administrator" can add "Subscription" nodes, but each "Local admin" user can edit nodes which were assigned to them.
* If the current user is not logged in and not on "**/sm**" page, it always will be redirected to user login page.

### Installation
1. Enable "**Email Subscription Manager**" module on **/admin/modules** page.
2. Configure connection to PeytzMail service by filling connection credentials on **/admin/config/emailservice/emailserviceconfig** page.

### Usage
1. Add library users. The field "Alias" **MUST** be filled with corresponding library code (ex: "skanbib") and role "Local admin".
2. Add node of type "Subscription" and assign corresponding user as node author.
