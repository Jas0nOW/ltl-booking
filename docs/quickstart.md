# Quickstart Guide

**Scope:** Initial installation, configuration, and first booking.  
**Non-Scope:** Advanced API usage, custom development, or server-side optimization.

## Who should read this?
- New users setting up LazyBookings for the first time.
- Developers setting up a local development environment.

---

## 1. Installation

### Standard WordPress Installation
1. Upload the `ltl-bookings` folder to your `/wp-content/plugins/` directory.
2. Go to **Plugins** in your WordPress admin and click **Activate** on "LazyBookings".
3. You will see a new **LazyBookings** menu item in the sidebar.

### Development Setup
If you are working on the plugin's assets:
1. Clone the repository into your plugins folder.
2. Run `npm install` to install development dependencies.
3. Run `npm run build` to compile the CSS tokens and base styles.

---

## 2. Initial Configuration

1. Navigate to **LazyBookings > Settings**.
2. Configure your **Business Hours** and **Currency**.
3. (Optional) Set up your **AI Provider** if you plan to use the Room Assistant.

---

## 3. Create Your First Service

1. Go to **LazyBookings > Services**.
2. Click **Add New Service**.
3. Fill in the name, duration, and price.
4. Assign at least one **Staff Member** to the service.

---

## 4. Test the Booking Wizard

1. Create a new WordPress page.
2. Add the shortcode `[ltlb_booking_wizard]`.
3. Publish the page and view it in the frontend.
4. Complete a test booking to ensure everything is working correctly.

---

## Next Steps
- [Configure Automations](explanation/automations.md)
- [Customize Design](explanation/design-guide.md)
- [Check System Health](how-to/testing.md)
