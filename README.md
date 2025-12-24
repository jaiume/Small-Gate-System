# EntryZen System

## Style Guide

The EntryZen system uses a centralized CSS approach with all styles defined in `styles.css`. This document provides guidance on using these styles consistently across the application.

### General Structure

The `styles.css` file is organized into the following sections:

1. **General Styles** - Basic styling for body, headings, and content containers
2. **Tables** - Styles for tables, including user tree tables and add user tables
3. **Forms** - Input fields, checkboxes, and form layouts
4. **Buttons** - Various button styles including action buttons and front page buttons
5. **Button Assignments** - Styles for button assignment checkboxes
6. **Links** - Styling for hyperlinks
7. **Header** - Navigation and logo styling
8. **Admin Login** - Styles specific to the login page
9. **Front Page Styles** - Styling for the main index page
10. **Status Info** - Information display boxes
11. **Code Output** - Styles for code display (used in blast.php)
12. **Responsive Styles** - Media queries for mobile devices

### Usage Guidelines

#### Page Structure

All pages should include the stylesheet:

```html
<link rel="stylesheet" type="text/css" href="/styles.css">
```

For responsive design, include the viewport meta tag:

```html
<meta name="viewport" content="width=device-width, initial-scale=0.75" />
```

#### Common Components

1. **Tables**
   - Use the `.user-tree-table` class for user management tables
   - Use `.add-user-table` for add user forms

2. **Buttons**
   - Standard buttons: `<button>` or `<input type="submit">`
   - Link-style buttons: `<a class="button-style">...</a>`
   - Front page buttons: `<a class="button-style-front">...</a>`
   - Action buttons: `<button class="action-btn">...</button>`
   - Delete buttons: `<button class="action-btn">Delete</button>`

3. **Forms**
   - Wrap form sections in `.form-container`
   - Group related inputs with `.form-group`
   - Use `.checkbox-group` for horizontal checkbox layouts

4. **Button Assignments**
   - Use `.button-checkbox-container` to wrap button assignment checkboxes
   - Each checkbox should be in a `.button-checkbox` label

### Extending Styles

When adding new styles:

1. Add them to the appropriate section in `styles.css`
2. Use existing color variables and spacing patterns
3. Follow the established naming conventions
4. Test on both desktop and mobile views

### Color Palette

- Primary buttons: `#ff6666` (hover: `#ff0f0f`)
- Action buttons: `#dc3545` (hover: `#c82333`)
- Links: `#007bff` (hover: `#0056b3`)
- Table headers: `#f2f2f2`
- Borders: `#dddddd` or `#eee`

### Responsive Design

The stylesheet includes media queries for screens smaller than 768px wide. Key responsive adjustments include:

- Larger font sizes for better readability on mobile
- Full-width inputs and buttons
- Adjusted spacing and layout for smaller screens
- Vertical layout for button checkboxes on mobile 