# Copy to Clipboard Utility with SWAL Notifications

This utility provides a consistent way to copy text to clipboard across the application using SweetAlert2 (SWAL) for user feedback.

## Features

- ✅ **Modern Clipboard API** with fallback for older browsers
- ✅ **SWAL Notifications** for success and error feedback
- ✅ **Multiple Usage Methods** - direct text, element ID, CSS selector
- ✅ **Customizable Options** - toast notifications, custom messages, timers
- ✅ **Auto-initialization** of copy buttons with data attributes
- ✅ **Responsive Design** with proper error handling

## Installation

The utility is already included in the dashboard footer:

```html
<script src="{{ asset('js/copy-to-clipboard.js') }}"></script>
```

## Usage Methods

### 1. Direct Text Copy

```javascript
// Copy specific text
copyToClipboard('Hello World!');

// With custom options
copyToClipboard('Hello World!', {
    successTitle: 'Success!',
    successText: 'Text copied successfully!',
    toast: true,
    timer: 3000
});
```

### 2. Copy from Element by ID

```javascript
// Copy from input/textarea value
copyElementToClipboard('licenseKey');

// Copy from any element's text content
copyElementToClipboard('myElementId');
```

### 3. Copy from Element by CSS Selector

```javascript
// Copy from element using CSS selector
copySelectorToClipboard('.license-key-input');
copySelectorToClipboard('#myElement');
```

### 4. HTML Data Attributes (Recommended)

#### Basic Usage

```html
<!-- Copy element by ID -->
<button data-copy="element" data-copy-element="licenseKey">
    <i class="fas fa-copy"></i> Copy
</button>

<!-- Copy specific text -->
<button data-copy="text" data-text="Hello World!">
    <i class="fas fa-copy"></i> Copy
</button>

<!-- Copy by CSS selector -->
<button data-copy="selector" data-copy-selector=".license-key">
    <i class="fas fa-copy"></i> Copy
</button>
```

#### Advanced Usage with Custom Options

```html
<!-- Toast notification with custom message -->
<button data-copy="element" 
        data-copy-element="licenseKey" 
        data-toast="true"
        data-success-text="License key copied to clipboard!"
        data-timer="3000">
    <i class="fas fa-copy"></i> Copy
</button>

<!-- Custom success/error messages -->
<button data-copy="text" 
        data-text="API Key: sk-1234567890"
        data-success-title="API Key Copied!"
        data-success-text="Your API key has been copied to clipboard"
        data-error-text="Failed to copy API key. Please try again."
        data-toast="true">
    <i class="fas fa-copy"></i> Copy API Key
</button>
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `successTitle` | string | 'Copied!' | Title for success notification |
| `successText` | string | 'Text copied to clipboard successfully!' | Message for success notification |
| `errorTitle` | string | 'Error' | Title for error notification |
| `errorText` | string | 'Failed to copy text. Please try again.' | Message for error notification |
| `showSuccessIcon` | boolean | true | Show success icon in notification |
| `showErrorIcon` | boolean | true | Show error icon in notification |
| `timer` | number | 2000 | Auto-close timer in milliseconds |
| `timerProgressBar` | boolean | true | Show progress bar in notification |
| `toast` | boolean | false | Use toast notification instead of modal |
| `position` | string | 'top-end' | Position for toast notifications |

## Data Attributes Reference

| Attribute | Description | Example |
|-----------|-------------|---------|
| `data-copy` | Type of copy operation | `"element"`, `"text"`, `"selector"` |
| `data-copy-element` | Element ID to copy from | `"licenseKey"` |
| `data-copy-selector` | CSS selector to copy from | `".license-key"` |
| `data-text` | Direct text to copy | `"Hello World!"` |
| `data-toast` | Use toast notification | `"true"` |
| `data-success-title` | Custom success title | `"Copied!"` |
| `data-success-text` | Custom success message | `"License key copied!"` |
| `data-error-title` | Custom error title | `"Error"` |
| `data-error-text` | Custom error message | `"Copy failed!"` |
| `data-timer` | Auto-close timer | `"3000"` |

## Real-World Examples

### License Key Copy Button

```html
<div class="input-group">
    <input type="text" class="form-control" value="PKG-CL-FREE-02" readonly id="licenseKey">
    <div class="input-group-append">
        <button class="btn btn-outline-secondary" type="button" 
                data-copy="element" 
                data-copy-element="licenseKey" 
                data-toast="true" 
                data-success-text="License key copied to clipboard!">
            <i class="fas fa-copy"></i>
        </button>
    </div>
</div>
```

### API Key Copy Button

```html
<div class="form-group">
    <label>API Key</label>
    <div class="input-group">
        <input type="password" class="form-control" value="sk-1234567890abcdef" readonly id="apiKey">
        <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" 
                    data-copy="element" 
                    data-copy-element="apiKey" 
                    data-toast="true" 
                    data-success-text="API key copied to clipboard!"
                    data-error-text="Failed to copy API key. Please try again.">
                <i class="fas fa-copy"></i>
            </button>
        </div>
    </div>
</div>
```

### Configuration Value Copy

```html
<div class="config-item">
    <span class="config-label">Database URL:</span>
    <span class="config-value" id="dbUrl">mysql://user:pass@localhost:3306/db</span>
    <button class="btn btn-sm btn-outline-primary" 
            data-copy="element" 
            data-copy-element="dbUrl" 
            data-toast="true"
            data-success-text="Database URL copied!">
        <i class="fas fa-copy"></i> Copy
    </button>
</div>
```

### Token Copy with Custom Styling

```html
<div class="token-display">
    <code id="accessToken">eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...</code>
    <button class="btn btn-sm btn-success" 
            data-copy="element" 
            data-copy-element="accessToken" 
            data-toast="true"
            data-success-title="Token Copied!"
            data-success-text="Access token has been copied to your clipboard"
            data-timer="4000">
        <i class="fas fa-copy"></i> Copy Token
    </button>
</div>
```

## Browser Compatibility

- **Modern Browsers**: Uses the Clipboard API
- **Older Browsers**: Falls back to `document.execCommand('copy')`
- **HTTPS Required**: Clipboard API requires secure context
- **Graceful Degradation**: Works in all supported browsers

## Error Handling

The utility handles various error scenarios:

- Element not found
- Empty text to copy
- Clipboard API not available
- User denied clipboard permission
- Network errors

All errors are displayed using SWAL notifications with appropriate error messages.

## Integration with Existing Code

The utility automatically initializes on page load and can be used alongside existing copy functionality. It's designed to be non-intrusive and won't interfere with other JavaScript code. 
