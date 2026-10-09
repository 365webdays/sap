# Testing Checklist — St. Anthony Adoration App

**Website:** https://stanthonyadoration.com

This checklist walks you through every part of the app. Work through each
section, tick the box when something works, and write down anything that
doesn't. You don't need any technical knowledge — if you can browse a website
and fill in a form, you can do this.

---

## Your Test Account

| Role   | Email                | Sign-in page                                    | Notes                        |
|--------|----------------------|-------------------------------------------------|------------------------------|
| Admin  | bonetp168@gmail.com  | https://stanthonyadoration.com/admin/login      | Password provided separately |

The admin sign-in page is not linked from the home page — type the address
above directly.

For testing the adorer side of the app, you'll create your own adorer account
in Section 2 below. Use your own email address so you can receive the welcome
email and test the notification features.

> **This is the live system.** Any adorer you register is a real record. Use
> your own name and email so the admin can find and deactivate it afterwards.
> You will also see five placeholder adorers named "Test User One" through
> "Test User Five" in the admin lists — ignore them.

---

## 1. Home Page

- [ ] The website loads when you type in the address
- [ ] The parish name and logo/branding are visible
- [ ] There are buttons or links for "Login" and "Register"
- [ ] Clicking "Login" takes you to the login page
- [ ] Clicking "Register" takes you to the registration page
- [ ] Everything fits on the screen — no side-to-side scrolling needed on a phone

### "Add to Home Screen" Prompt
- [ ] On an Android phone, you see a button or prompt to "Add to Home Screen"
- [ ] On an iPhone, you see instructions explaining how to add it using the Share button
- [ ] If you dismiss the prompt, it doesn't keep popping up

---

## 2. Registering a New Adorer

### Filling Out the Form
- [ ] The registration form asks for: Full Name, Email, Mobile Number, Password, Assigned Day, Assigned Time, and a privacy consent checkbox
- [ ] You cannot submit the form without checking the privacy consent box
- [ ] You cannot submit the form with empty fields
- [ ] If you type an invalid email (like "abc"), it tells you to fix it
- [ ] If you type a very short password, it tells you it's too short

### Successful Registration
- [ ] When you fill in everything correctly and submit, your account is created
- [ ] You receive a welcome email at the address you registered with (check your spam/junk folder too)
- [ ] After registering, you are taken straight to your dashboard (already logged in)

### Duplicate Email
- [ ] If you try to register again with an email that's already been used, it tells you "That email address is already registered"
- [ ] No second account is created

---

## 3. Adorer Login

### Logging In
- [ ] The login page loads
- [ ] Logging in with the correct email and password takes you to your dashboard
- [ ] Logging in with the wrong password shows "Incorrect email or password" and tells you how many tries you have left
- [ ] After 5 wrong attempts, it says "Too many login attempts" and makes you wait 15 minutes before trying again

### Staying Logged In / Logging Out
- [ ] After logging in, refreshing the page keeps you logged in
- [ ] The logout button works — it takes you back to the login page
- [ ] After logging out, going to the dashboard page sends you back to login
- [ ] Pages that require login always send you to login if you're not logged in

---

## 4. Adorer Dashboard

### What You See
- [ ] Your name is displayed at the top
- [ ] Your assigned adoration day and time are shown
- [ ] Your last check-in date/time is shown (or a message saying "No check-ins yet")
- [ ] Your recent attendance history is listed
- [ ] There's a button to quickly check in

### Getting Around
- [ ] You can move between all your pages (Dashboard, Attendance, Preferences, Check-in)
- [ ] The navigation works on a phone (bottom bar or menu icon)
- [ ] The navigation works on a computer too

---

## 5. Checking In

### Manual Check-In
- [ ] Clicking the check-in button on the dashboard records your check-in
- [ ] A confirmation message appears
- [ ] The check-in shows up in your attendance history
- [ ] If you try to check in again within the same hour, it stops you (to prevent duplicates)

### QR Code Check-In
- [ ] The QR code is visible in the admin area (see Section 15)
- [ ] Scanning the QR code with your phone camera opens the check-in page
- [ ] If you're already logged in, the check-in is recorded automatically
- [ ] If you're NOT logged in, it asks you to log in first, then takes you back to check-in
- [ ] A confirmation message appears after checking in
- [ ] The check-in shows up in your history as a "QR" check-in

---

## 6. Notification Preferences

- [ ] The preferences page shows three on/off switches: Hour Reminders, Chapel Announcements, Attendance Notifications
- [ ] The switches show your current settings when the page loads
- [ ] Changing a switch and saving shows a confirmation
- [ ] Reloading the page shows your saved settings (the change stuck)
- [ ] You can change your preferences at any time

---

## 7. Admin Login

- [ ] The admin login page is separate from the adorer login page
- [ ] Logging in with `bonetp168@gmail.com` and the password takes you to the admin dashboard
- [ ] Logging in with the wrong password shows an error with remaining attempts
- [ ] After 5 wrong attempts, you're locked out for 15 minutes (separate from the adorer login)
- [ ] An adorer account cannot access the admin area
- [ ] An admin account cannot access the adorer pages
- [ ] Refreshing the admin dashboard keeps you logged in
- [ ] Logging out returns you to the admin login page
- [ ] Going to the admin area without logging in sends you to the admin login page

---

## 8. Admin Dashboard — Overview

### Summary Numbers
- [ ] "Total Registered Adorers" shows the correct count
- [ ] "Active Adorers" shows the correct count
- [ ] "Today's Attendance" shows the correct count
- [ ] "This Week's Attendance" shows the correct count
- [ ] "This Month's Attendance" shows the correct count

### Charts
- [ ] The attendance trend chart loads
- [ ] You can switch between daily, weekly, and monthly views
- [ ] The chart updates when you switch views
- [ ] The "peak attendance periods" chart loads and is readable

---

## 9. Managing Adorers

### The Adorer List
- [ ] A list of all adorers loads
- [ ] You can search for an adorer by name or email
- [ ] You can filter by active or inactive status
- [ ] You can filter by assigned day
- [ ] You can filter by assigned time
- [ ] If there are many adorers, you can go to the next/previous page

### Viewing an Adorer
- [ ] Clicking an adorer opens their profile
- [ ] Their personal details are shown (name, email, mobile)
- [ ] Their assigned schedule is shown
- [ ] Their full attendance history is listed

### Editing an Adorer
- [ ] You can change an adorer's assigned day or time
- [ ] You can turn an adorer's account on or off (active/inactive)
- [ ] Saving changes shows a confirmation
- [ ] The changes are still there after reloading the page

### Turning Accounts Off / On
- [ ] Turning off an adorer prevents them from logging in
- [ ] Turning them back on lets them log in again
- [ ] Turned-off adorers appear when you filter for "inactive"

---

## 10. Attendance Records

### Viewing Records
- [ ] A table of all check-ins loads (date, time, adorer name, method)
- [ ] You can filter by date range
- [ ] You can filter by adorer name
- [ ] You can filter by day of the week
- [ ] You can filter by time slot
- [ ] You can filter by check-in method (manual or QR)
- [ ] Using multiple filters at the same time works

### Exporting
- [ ] You can download the attendance records as a spreadsheet (CSV)
- [ ] The downloaded file only contains the records matching your filters (not everything)
- [ ] The columns in the file are: Timestamp, Date, Time, Name, Email, Method, Scheduled Hour

---

## 11. Missed Attendance

### The Report
- [ ] The report shows adorers who missed their scheduled hour
- [ ] Each row shows: the missed date, the adorer's name, and their scheduled time
- [ ] You can filter by date range
- [ ] The information is accurate — cross-check a couple of rows against what actually happened

### Marking as Followed Up
- [ ] You can mark a missed record as "followed up"
- [ ] Followed-up records look different (a badge or checkmark)
- [ ] The followed-up status stays after you reload the page

### Exporting
- [ ] You can download the missed attendance report as a spreadsheet
- [ ] The file matches what you see on screen

---

## 12. Coverage / Schedule Gaps

### Viewing All Time Slots
- [ ] All time slots (day + time) are listed
- [ ] Each slot shows which adorers are assigned to it
- [ ] Slots with no assigned adorer are clearly marked (so you can see where coverage is missing)
- [ ] The layout is easy to read on both phone and computer

### Filling a Gap
The coverage view is read-only. To fill a gap, change the adorer's schedule
from their profile (Section 9 → Editing an Adorer).
- [ ] Pick an empty slot in the coverage view and note the day and time
- [ ] Open an adorer's profile and change their assigned day/time to that slot
- [ ] Go back to the coverage view — the adorer now appears in that slot and the old slot updates
- [ ] The adorer's profile shows the new schedule

---

## 13. Sending Bulk Emails

### Composing an Email
- [ ] The email compose form loads (Subject and Message fields)
- [ ] You can choose who to send to: All Adorers, Active Adorers, Inactive Adorers, or Adorers Who Missed Their Hour
- [ ] After choosing a group, it shows how many people will receive the email
- [ ] You cannot send with an empty subject or message

### Sending
- [ ] Sending to a small group (1-2 people) — the emails actually arrive (check inbox and spam)
- [ ] A record of the sent email appears in Email History
- [ ] If you try to send to a group with 0 people, it warns you and doesn't send

### Email History
- [ ] The history page lists all previously sent emails
- [ ] Each entry shows the subject, which group it went to, when, and who sent it
- [ ] Each entry shows how many emails were sent (e.g. "2 / 2 sent") and flags any failures

---

## 14. Exporting Reports

- [ ] You can download the full adorer list as a spreadsheet
- [ ] You can download attendance records as a spreadsheet (Section 10)
- [ ] You can download the missed attendance report as a spreadsheet (Section 11)
- [ ] All downloaded files open correctly in Excel or Google Sheets

---

## 15. QR Code

### Viewing
- [ ] The QR code is visible in the admin area
- [ ] The QR code can be scanned with a phone camera and opens the check-in page

### Downloading
- [ ] You can download the QR code as an image file
- [ ] The downloaded image is clear and high quality (good enough to print)

---

## 16. Automated Reminder Emails

> These are automatic emails sent by the system on a schedule. They only run
> once the two scheduled tasks (cron jobs) have been set up on the server —
> check with the developer before testing this section.

### Pre-Adoration Reminder
- [ ] An adorer who has "Hour Reminders" turned ON and has a scheduled hour coming up receives a reminder email
- [ ] An adorer who has "Hour Reminders" turned OFF does NOT receive a reminder
- [ ] The same person doesn't get duplicate reminders for the same hour

### Missed Attendance Notification
- [ ] An adorer who missed their hour and has "Attendance Notifications" turned ON receives a notification email
- [ ] An adorer who has "Attendance Notifications" turned OFF does NOT receive one

---

## 17. Security

### Login Protection
- [ ] Pages that need a login always send you to the login page if you're not logged in
- [ ] An adorer cannot access the admin area
- [ ] An admin cannot access adorer pages
- [ ] A turned-off adorer account cannot log in

### Secure Connection
- [ ] The website address shows a lock icon in the browser (secure connection)
- [ ] No warning messages appear in the browser

### Too Many Login Attempts
- [ ] After 5 wrong adorer logins, you're locked out for 15 minutes
- [ ] After 5 wrong admin logins, you're locked out for 15 minutes (counted separately)
- [ ] A successful login resets the counter

### Form Validation
- [ ] The registration form rejects invalid emails, short passwords, and missing fields
- [ ] Typing special characters (like apostrophes or quotes) in form fields doesn't cause errors

---

## 18. Phone, Tablet, and Computer

### On a Phone (small screen)
- [ ] Everything fits on the screen — no side-to-side scrolling
- [ ] All buttons are big enough to tap easily
- [ ] Forms are easy to fill in
- [ ] Text is readable without zooming in

### On a Tablet
- [ ] The layout uses the wider screen well
- [ ] It looks correct both upright (portrait) and sideways (landscape)

### On a Computer
- [ ] The admin dashboard uses the full screen width well
- [ ] Tables are wide and easy to read
- [ ] Content isn't stretched too thin across a very wide screen

---

## 19. Different Devices and Browsers

### Android Phone
- [ ] Chrome — everything works (register, login, check-in, admin)
- [ ] Samsung Internet — everything works

### iPhone
- [ ] Safari — everything works
- [ ] Chrome — everything works

### Computer
- [ ] Mac — Chrome and Safari
- [ ] Windows — Chrome and Edge

---

## 20. Edge Cases (Unusual Situations)

- [ ] Checking in twice in the same hour — the second one is blocked
- [ ] Registering with an email that's already used — it's rejected with a clear message
- [ ] Sending a bulk email to a group with nobody in it — it warns you and doesn't send
- [ ] Scanning the QR code when not logged in — it asks you to log in, then takes you to check-in
- [ ] An adorer with no assigned schedule viewing their dashboard — it handles it gracefully (no error or blank page)
- [ ] When something is loading, you see a loading indicator (spinner)
- [ ] When something goes wrong, you see a friendly error message (not a page of code)
- [ ] Empty lists/tables show a helpful message like "No records found" instead of a blank screen

---

## 21. App Installation (PWA)

- [ ] On Android, you can add the app to your home screen
- [ ] On iPhone, you can add it using Share → Add to Home Screen
- [ ] Once installed, it opens full-screen (no browser address bar)
- [ ] The app icon appears on your home screen

---

## Sign-Off

| Area                              | Pass? | Notes |
|-----------------------------------|-------|-------|
| Home page                         |       |       |
| Registration                      |       |       |
| Adorer login                      |       |       |
| Adorer dashboard                  |       |       |
| Check-in (manual + QR)            |       |       |
| Notification preferences          |       |       |
| Admin login                       |       |       |
| Admin dashboard & charts          |       |       |
| Managing adorers                  |       |       |
| Attendance records                |       |       |
| Missed attendance                 |       |       |
| Coverage / schedule gaps          |       |       |
| Bulk email                        |       |       |
| Exporting reports                 |       |       |
| QR code                           |       |       |
| Automated reminder emails         |       |       |
| Security                          |       |       |
| Phone / tablet / computer layout  |       |       |
| Different devices & browsers      |       |       |
| Edge cases                        |       |       |
| App installation                  |       |       |

**Tester name:** _______________________________

**Date:** _______________________________

**Overall result:** ☐ Pass ☐ Pass with notes ☐ Needs work
