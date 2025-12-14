# LazyBookings - Screenshots Documentation

This document lists the required screenshots for WordPress.org submission and their descriptions.

## Required Screenshots (6 total)

### Screenshot 1: Appointments Dashboard
**Filename:** `screenshot-1.png` (1200x900px recommended)
**Description:** "Appointments Dashboard - KPIs and quick actions for daily operations"

**What to capture:**
- Main dashboard view in Appointments mode
- KPI cards showing "Appointments This Week" with week-over-week comparison
- "Pending Appointments" count
- "Total Customers" count
- Recently Viewed sidebar widget
- Latest Appointments table
- Quick action buttons (New Appointment, View Calendar)

---

### Screenshot 2: Calendar View
**Filename:** `screenshot-2.png` (1200x900px recommended)
**Description:** "Calendar View - Drag & drop appointments with status colors"

**What to capture:**
- FullCalendar with sample appointments
- Different status colors (Confirmed green, Pending yellow, Cancelled red)
- Collapsible legend (expanded state showing color swatches)
- Calendar details panel on the right
- Month/Week/Day view switcher

---

### Screenshot 3: Service/Room Type Wizard
**Filename:** `screenshot-3.png` (1200x900px recommended)
**Description:** "Service/Room Type Wizard - Multi-step form for easy setup"

**What to capture:**
- Multi-step wizard interface (Step 2 or 3 of 3)
- Progress indicator at top
- Form fields with validation
- Back/Next buttons
- Wizard sidebar navigation showing completed/current/upcoming steps

---

### Screenshot 4: Customers/Guests Management
**Filename:** `screenshot-4.png` (1200x900px recommended)
**Description:** "Customers/Guests Management - Pagination, bulk actions, and CSV export"

**What to capture:**
- Customers table with sample data
- Pagination controls (20/50/100 items per page dropdown)
- "Export CSV" button
- Row actions (View, Edit, Delete)
- Search functionality (if visible)

---

### Screenshot 5: Settings Page
**Filename:** `screenshot-5.png` (1200x900px recommended)
**Description:** "Settings Page - Configure booking rules, email, and design"

**What to capture:**
- Settings page with multiple tabs/sections visible
- Email notification settings
- Booking rules configuration
- Design customization options (color picker, logo upload)
- Save button with success indicator

---

### Screenshot 6: Hotel Dashboard
**Filename:** `screenshot-6.png` (1200x900px recommended)
**Description:** "Hotel Dashboard - Check-ins, check-outs, and occupancy overview"

**What to capture:**
- Hotel mode dashboard (different from Appointments)
- KPI cards: "Check-ins Today", "Check-outs Today", "Occupied Rooms"
- Latest Bookings table
- Quick action buttons (New Booking, View Calendar)
- Mode indicator showing "Hotel" mode active

---

## Optional Additional Screenshots

### Screenshot 7: Bulk Actions
**Description:** "Bulk Actions - Select multiple items and perform batch operations"

### Screenshot 8: Mobile Responsive
**Description:** "Mobile-friendly admin interface"

### Screenshot 9: Column Toggles
**Description:** "Customize table columns with visibility toggles"

---

## Banner Images (Required for WordPress.org)

### Header Banner
**Filename:** `banner-1544x500.jpg`
**Dimensions:** 1544 x 500 pixels
**Description:** High-res banner for plugin directory header

**Content Suggestions:**
- Plugin logo/name: "LazyBookings"
- Tagline: "Appointments & Hotel Booking Made Simple"
- Visual: Calendar icon + Hotel icon
- Premium/Modern aesthetic

---

### Low-res Header Banner
**Filename:** `banner-772x250.jpg`
**Dimensions:** 772 x 250 pixels
**Description:** Standard banner for plugin directory (scaled version of high-res)

---

## Plugin Icon (Required for WordPress.org)

### Icon 256x256
**Filename:** `icon-256x256.png`
**Dimensions:** 256 x 256 pixels
**Description:** Square plugin icon with transparent background

**Design Suggestions:**
- Calendar icon with booking elements
- Or: "LB" monogram in modern style
- Use brand colors from Design Guide
- Ensure clarity at small sizes (128px, 64px)

---

### Icon 128x128
**Filename:** `icon-128x128.png`
**Dimensions:** 128 x 128 pixels
**Description:** Scaled version of 256x256 icon

---

## How to Take Screenshots

### Preparation:
1. Activate plugin in clean WordPress installation
2. Add sample data:
   - 5-10 appointments with different statuses
   - 3-5 services/room types
   - 10-15 customers/guests
   - 2-3 resources/rooms
3. Set mode to Appointments for screenshots 1-5
4. Switch to Hotel mode for screenshot 6

### Best Practices:
- Use 1920x1080 resolution for capturing (crop to 1200x900)
- Hide personal/sensitive test data
- Show plugin in best light (no errors, clean UI)
- Ensure good contrast and readability
- Use realistic but dummy data
- Capture on high-DPI display for crisp images

### Tools:
- Windows: Snipping Tool, ShareX, Greenshot
- Mac: Cmd+Shift+4 (built-in screenshot)
- Browser: DevTools Device Toolbar (for responsive shots)

---

## Screenshot Checklist

Before submission:
- [ ] All 6 required screenshots captured
- [ ] Images are 1200x900px (or close to 4:3 ratio)
- [ ] No personal/sensitive data visible
- [ ] UI looks clean and professional
- [ ] Different features highlighted in each screenshot
- [ ] Banner images created (1544x500 and 772x250)
- [ ] Plugin icons created (256x256 and 128x128)
- [ ] All images optimized (compressed without quality loss)
- [ ] File names match WordPress.org convention

---

## Image Optimization

Before uploading, optimize all images:
- Use TinyPNG or ImageOptim
- Target: <200KB per screenshot
- Format: PNG for screenshots, JPG for banners
- Maintain visual quality while reducing file size

---

## WordPress.org Assets Folder Structure

```
assets/
├── banner-1544x500.jpg      # High-res header banner
├── banner-772x250.jpg        # Standard header banner
├── icon-256x256.png          # Large plugin icon
├── icon-128x128.png          # Small plugin icon
├── screenshot-1.png          # Appointments Dashboard
├── screenshot-2.png          # Calendar View
├── screenshot-3.png          # Wizard
├── screenshot-4.png          # Customers Management
├── screenshot-5.png          # Settings Page
└── screenshot-6.png          # Hotel Dashboard
```

**Note:** The `assets/` folder should be in the SVN repository root, NOT inside the plugin ZIP file.

---

## Next Steps After Screenshots

1. Create assets folder in SVN repository
2. Upload optimized images to assets folder
3. Update readme.txt with final screenshot descriptions
4. Test screenshots display correctly on WordPress.org preview
5. Submit plugin for review

---

*Last Updated: December 2024*
*Version: 1.0.0*
