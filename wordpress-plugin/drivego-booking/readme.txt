=== Drive Go Booking ===
Booking calendar for Drive Go London: lessons, refresher and road test packages, with Square payments and a dashboard.

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
