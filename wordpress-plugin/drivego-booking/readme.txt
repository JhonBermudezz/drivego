=== DriveGo Booking ===
Booking calendar for DriveGo London: lessons, refresher and road test packages, with Square payments and a dashboard.

== Install ==
1. Plugins > Add New > Upload Plugin > choose drivego-booking.zip > Install > Activate.
2. Bookings > Settings: set hours, prices, the email that receives bookings and, when ready, the Square connection.
3. Show the calendar:
   - Any page: add the shortcode [drivego_booking service="lessons"], [drivego_booking service="refresher"] or [drivego_booking service="roadtest"].
   - Any button or link: add the attribute data-book="lessons" (or refresher, roadtest) to open the calendar in a pop-up.

== Square ==
Square Developer Dashboard > Applications > your app > Credentials. Copy the Access token and the Location ID into Bookings > Settings.
Use Sandbox first. Switch to Production when you are ready for real payments.
Optional: add a webhook for payment.updated pointing to the URL shown on the settings page, and paste its Signature key. Bookings are then marked Paid automatically.

== Notes ==
Emails use wp_mail(). Install an SMTP plugin so messages are delivered reliably.

== Home pick up price ==
In Bookings > Settings each option has "Extra for home pick up". It is added to the price when the student chooses "Pick me up at home" (and always for road tests), and it is included in the Square charge. Leave 0 when pick up is included. The price is always calculated on the server.

== Contact form emails ==
Messages from the Elementor contact forms (subject starts with "New message from") are turned into a styled HTML card automatically, with Reply-To set to the sender's email.

The confirmation email that the form sends to the customer (subject starting "We received your message") is styled the same way, with a WhatsApp button.

== Spam protection ==
The Elementor contact forms get three server-side checks: a hidden trap field, a minimum time on the page, and Google reCAPTCHA v3 when the site key and secret key are filled in under Bookings > Settings. Without the keys the first two still work.
