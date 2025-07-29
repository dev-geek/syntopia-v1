# Password Toggle Component

A beautiful, reusable password toggle component for the entire project.

## Features

- ✨ **Beautiful Design**: Modern, glassmorphism-style toggle button with smooth animations
- 🔄 **Auto-initialization**: Automatically adds toggle buttons to all password fields
- 📱 **Responsive**: Works perfectly on all device sizes
- ♿ **Accessible**: Full keyboard navigation and screen reader support
- 🎨 **Customizable**: Easy to style and customize
- 🔧 **Framework Agnostic**: Works with any CSS framework
- 🚀 **Performance**: Lightweight and efficient

## Quick Start

### 1. Include the CSS
Add this to your layout or page:

```html
<link rel="stylesheet" href="/css/password-toggle.css">
```

### 2. Include the JavaScript
Add this to your layout or page:

```html
<script src="/js/password-toggle.js"></script>
```

### 3. Use in HTML
Simply add a password input field:

```html
<input type="password" id="password" name="password" class="form-control">
```

The toggle button will be automatically added!

## Usage Options

### Option 1: Automatic (Recommended)
The script automatically finds all `input[type="password"]` fields and adds toggle buttons.

### Option 2: Manual
For dynamically added fields, you can manually add the toggle:

```javascript
const passwordField = document.getElementById('my-password');
PasswordToggle.addToField(passwordField);
```

### Option 3: Blade Component
Use the Blade component for consistent styling:

```blade
<x-password-toggle 
    id="password" 
    name="password" 
    placeholder="Enter your password"
    required
    class="custom-class"
/>
```

## Styling

### Custom Colors
You can customize the colors by overriding CSS variables:

```css
.password-toggle-btn {
    --toggle-bg: rgba(255, 255, 255, 0.9);
    --toggle-border: #e0e0e0;
    --toggle-color: #6c757d;
    --toggle-hover-bg: rgba(255, 255, 255, 1);
    --toggle-hover-color: #0d6efd;
    --toggle-hover-border: #0d6efd;
}
```

### Dark Theme
Add the `dark` class to the wrapper for dark theme support:

```html
<div class="password-field-wrapper dark">
    <input type="password" class="form-control">
</div>
```

## API

### PasswordToggle Class

#### Static Methods

- `PasswordToggle.addToField(passwordField)` - Add toggle to specific field
- `PasswordToggle.refresh()` - Refresh all password toggles

#### Instance Methods

- `new PasswordToggle()` - Initialize the component

## Browser Support

- ✅ Chrome 60+
- ✅ Firefox 55+
- ✅ Safari 12+
- ✅ Edge 79+

## Features

### Animations
- Smooth hover effects with scale transform
- Eye wink animation on hover
- Smooth icon transitions
- Loading spinner for async operations

### Accessibility
- Full keyboard navigation (Enter, Space)
- ARIA labels and titles
- Focus management
- Screen reader friendly

### Responsive Design
- Mobile-optimized button sizes
- Touch-friendly interactions
- Adaptive padding and spacing

## Examples

### Basic Usage
```html
<div class="form-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" class="form-control">
</div>
```

### With Validation
```html
<div class="form-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror">
    @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
```

### Custom Styling
```html
<div class="password-field-wrapper custom-theme">
    <input type="password" class="form-control">
</div>
```

## Troubleshooting

### Toggle button not appearing?
1. Make sure the CSS and JS files are loaded
2. Check that the input has `type="password"`
3. Verify there are no JavaScript errors in the console

### Styling issues?
1. Check if your CSS is overriding the password toggle styles
2. Ensure the wrapper has `position: relative`
3. Verify the z-index is appropriate

### Not working with dynamic content?
Use the manual method or call `PasswordToggle.refresh()` after adding new content.

## Contributing

To modify the password toggle:

1. Edit `/public/css/password-toggle.css` for styles
2. Edit `/public/js/password-toggle.js` for functionality
3. Test across different browsers and devices
4. Update this documentation

## License

This component is part of the Syntopia project. 
