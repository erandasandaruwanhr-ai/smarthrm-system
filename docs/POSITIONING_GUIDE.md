# SmartHRM Module Positioning Guide

## Overview
This guide ensures all modules maintain consistent positioning and layout that matches the dashboard.

## ✅ CORRECT Structure
All modules **MUST** follow this exact structure:

```html
<!-- Sidebar -->
<?php include '../../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <!-- navbar content -->
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- Your module content here -->
    </div>
</div>
```

## ❌ INCORRECT Structures
**NEVER** use these old patterns:

```html
<!-- ❌ OLD CONTAINER-FLUID PATTERN -->
<div class="container-fluid">
    <div class="row">
        <?php include '../../includes/sidebar.php'; ?>
        <main class="col-md-10 ms-sm-auto main-content">
```

```html
<!-- ❌ OLD CSS LINK PATTERN -->
<link href="../../assets/css/style.css" rel="stylesheet">
```

## Required CSS Variables
```css
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --sidebar-width: 280px;
}

.main-content {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
}

.dashboard-content {
    padding: 2rem;
}
```

## Key Rules

### 1. **Sidebar Positioning**
- ✅ `position: fixed` with `margin-left: var(--sidebar-width)` on main content
- ❌ Never use Bootstrap grid (`col-md-10 ms-sm-auto`) for sidebar layout

### 2. **Content Structure**
- ✅ Always wrap content in `dashboard-content` class with `padding: 2rem`
- ✅ Use the standardized top navbar structure
- ❌ Never use `container-fluid py-4` wrapper

### 3. **CSS Organization**
- ✅ Include all dashboard CSS inline in `<style>` tags
- ✅ Copy exact CSS from `MODULE_TEMPLATE.php`
- ❌ Never link to external `../../assets/css/style.css`

### 4. **Mobile Responsiveness**
- ✅ Include sidebar toggle JavaScript
- ✅ Use exact media query: `@media (max-width: 768px)`
- ✅ Transform sidebar off-screen: `transform: translateX(-100%)`

## Template Usage

### For New Modules:
1. Copy `/docs/MODULE_TEMPLATE.php`
2. Replace `MODULE_NAME` and `MODULE_TITLE`
3. Add your PHP logic after `$user = getCurrentUser();`
4. Replace content placeholder with your module content
5. **NEVER modify the sidebar, navbar, or main structure**

### For Existing Modules:
1. Check if module follows the correct structure
2. If not, use the template to fix it
3. Preserve existing content but update structure
4. Test that sidebar appears and positioning is correct

## Validation Checklist

Before deploying any module, verify:

- [ ] Sidebar appears and is properly positioned
- [ ] Content has consistent padding (2rem)
- [ ] Top navbar matches dashboard style
- [ ] Mobile responsive (sidebar toggles)
- [ ] CSS uses dashboard variables
- [ ] No external style.css links
- [ ] Main content starts at 280px from left edge
- [ ] Structure matches dashboard exactly

## Common Issues & Solutions

### Issue: Sidebar Missing
**Cause:** Wrong include or structure
**Solution:** Use `<?php include '../../includes/sidebar.php'; ?>` before main-content

### Issue: Content Too Close to Sidebar
**Cause:** Missing or incorrect margin-left
**Solution:** Ensure `.main-content { margin-left: var(--sidebar-width); }`

### Issue: Different Padding/Spacing
**Cause:** Not using `dashboard-content` wrapper
**Solution:** Wrap all content in `<div class="dashboard-content">`

### Issue: Responsive Behavior Broken
**Cause:** Missing JavaScript or incorrect CSS
**Solution:** Copy exact mobile CSS and sidebar toggle script from template

## Development Workflow

1. **Always** start with the template for new modules
2. **Never** modify the core structure (sidebar, main-content, top-navbar)
3. **Test** on both desktop and mobile after changes
4. **Validate** against the checklist above
5. **Document** any custom CSS in the module comments

## Emergency Fix Script

If you need to quickly fix positioning issues across modules:
```bash
# Use the fix_all_modules.php script
php fix_all_modules.php
```

---

**Remember:** Consistency is key. All modules should look and feel exactly like the dashboard in terms of layout and positioning.