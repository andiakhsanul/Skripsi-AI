# Azure Meridian Design System

### 1. Overview & Creative North Star
**Creative North Star: The Academic Vanguard**
Azure Meridian is a design system that balances institutional gravity with modern accessibility. It moves away from the "static portal" aesthetic toward a dynamic, editorial experience. By utilizing high-contrast photography, bold typographic scales, and immersive glassmorphism, the system creates a sense of prestigious transparency. The layout breaks traditional rigid grids by using a "split-screen narrative" where content and imagery coexist in a high-contrast, functional harmony.

### 2. Colors
Azure Meridian uses a deep "Vivid Blue" as its anchor, supported by "Academic Gold" accents to denote value and high-priority interactions.
- **The "No-Line" Rule:** Visual boundaries are created via shifts between `surface` (#ffffff) and `surface_container_low` (#f8fafc). Designers must avoid using 1px solid borders for sectioning; instead, use background tonal shifts or large-scale shadows to define regions.
- **Surface Hierarchy:** Depth is achieved by nesting `surface_container` (used for tab backgrounds and inputs) inside the primary `surface`.
- **The "Glass & Gradient" Rule:** Floating elements, especially over imagery, must use a `20% opacity` white fill with a `backdrop-blur` of at least 12px.
- **Signature Textures:** Hero sections utilize a dual-layer gradient: a primary color multiply layer combined with a bottom-to-top alpha gradient to ensure legibility of overlaying white text.

### 3. Typography
The system relies exclusively on **Lexend**, a typeface designed for reading proficiency, which reinforces the educational focus of the brand.
- **Display & Headlines:** Use 'ExtraBold' weights with tight tracking (-0.02em) for hero sections. The `1.875rem` (Title) and `1.5rem` (Header) sizes create a commanding presence.
- **Body & Labels:** Body text utilizes `0.875rem` (14px) for optimal information density.
- **Micro-Copy:** A specific `10px` uppercase tracking-widest style is reserved for legal footers and secondary branding tags to provide a premium, "label-like" feel.
- **Rhythm:** The scale jumps from `1.125rem` (subheadings) to `1.875rem` (page titles), creating a sharp, intentional hierarchy that guides the eye toward primary actions.

### 4. Elevation & Depth
Elevation in Azure Meridian is expressed through soft, ambient light rather than physical height.
- **Ambient Shadows:** 
    - *Level 1 (Inputs/Tabs):* `0 1px 2px 0 rgba(0, 0, 0, 0.05)` (shadow-sm).
    - *Level 2 (Buttons/Cards):* `0 10px 15px -3px rgba(0, 0, 0, 0.1)` (shadow-lg).
    - *Level 3 (Main Containers):* `0 25px 50px -12px rgba(0, 0, 0, 0.25)` (shadow-2xl).
- **The Layering Principle:** Construct layouts by stacking `surface_container_low` elements onto the main `background`. Use the Gold accent (#eab308) as a "border-top" highlight to signify active state or portal focus without enclosing the entire element.
- **Ghost Border Fallback:** Where contrast is critical (e.g., input fields), use `outline_variant` at 50% opacity.

### 5. Components
- **Buttons:** Primary buttons are high-saturation (`primary`) with significant padding (`py-3.5`). They feature a subtle `shadow-primary/20` to appear "glowing" rather than "heavy."
- **Tabs:** Use a "Pill in a Box" style—a `surface_container` background with a `surface` white active state and `shadow-sm`.
- **Inputs:** Large, clear hit areas with leading icons. Backgrounds should be `surface` (white) to pop against container backgrounds.
- **Glass Chips:** Small informational buttons used over imagery should have `border-white/30` and a `backdrop-blur-md` effect.
- **Status Indicators:** Use the "Accent Gold" for top-border decorative elements to tie the interface to the institutional brand.

### 6. Do's and Don'ts
**Do:**
- Use wide gutters and generous padding (spacing level 2 or 3) to maintain an editorial feel.
- Use high-quality campus photography with brand-colored overlays.
- Align icons with the baseline of the text for a "label" feel.

**Don't:**
- Do not use black text; use `on_surface` (#0f172a) for better readability.
- Do not use rounded corners larger than `0.75rem` except for full-pill buttons; the system aims for "Modern Professional," not "Playful."
- Avoid using the accent gold for large background areas; it is strictly a "highlight" color.