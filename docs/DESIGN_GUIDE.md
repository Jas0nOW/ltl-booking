# LazyBookings – Design Guide (v0.4.4)

This guide documents the **design tokens** stored in the `lazy_design` option and how they affect the **frontend booking widget**.

## Scope

- Tokens are applied as **CSS variables** scoped to `.ltlb-booking` (frontend wizard) and are also used in the admin **Design** page preview.
- Tokens are **not** applied globally to the WordPress theme.

## Token Storage

- Option name: `lazy_design`
- Type: associative array

## Tokens

### Colors

| Key | Meaning |
|-----|---------|
| `background` | Main background color for the booking widget |
| `text` | Primary text color |
| `primary` | Primary button background |
| `primary_hover` | Primary button hover background/border |
| `secondary` | Secondary (outline) button color |
| `secondary_hover` | Secondary button hover color |
| `accent` | Accent color (e.g., highlights, optional gradient end) |
| `border_color` | Borders for inputs/cards |
| `panel_background` | Inner panel / card background |
| `button_text` | Manual primary button text color (only used when `auto_button_text=0`) |

### Shape & Motion

| Key | Meaning |
|-----|---------|
| `border_width` | Border thickness in px |
| `border_radius` | Border radius in px |
| `transition_duration` | Transition duration in ms |
| `enable_animations` | 1/0 toggle for UI transitions |

### Shadows

| Key | Meaning |
|-----|---------|
| `box_shadow_blur` | Shadow blur in px |
| `box_shadow_spread` | Shadow spread in px |
| `shadow_container` | 1/0 shadow on main container |
| `shadow_button` | 1/0 shadow on buttons |
| `shadow_input` | 1/0 shadow on inputs |
| `shadow_card` | 1/0 shadow on cards/panels |

### Extras

| Key | Meaning |
|-----|---------|
| `use_gradient` | 1/0 uses `linear-gradient(primary, accent)` as background |
| `auto_button_text` | 1/0 automatically picks readable text color (black/white) |
| `custom_css` | Extra CSS appended for `.ltlb-booking` scope |

## Defaults

Defaults are created on plugin activation in `lazy_design` and can be adjusted any time via WP Admin → **LazyBookings → Design**.
