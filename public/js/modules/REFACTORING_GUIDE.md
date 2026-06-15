# drive-premium.js Refactoring Guide

## Overview
The original `drive-premium.js` file (2,900 lines) has been refactored into smaller, manageable modules organized by functionality. This guide shows how to split the monolithic file into smaller files.

## Module Structure

```
public/js/
├── drive-premium.js (main entry point - kept for now)
└── modules/
    ├── utils.js              # Utility functions (escapeHtml, formatBytes, etc.)
    ├── state.js              # State management & initialization
    ├── router.js             # SPA routing & navigation
    ├── renderer.js           # View rendering & card generation
    ├── events.js             # Event binding & interaction handlers
    ├── context-menu.js       # Context menu functionality
    ├── drawer.js             # Details drawer rendering
    ├── api.js                # API execution functions
    ├── modals.js             # Modal management
    ├── editor.js             # OnlyOffice preview/editor
    ├── upload.js             # Drag-and-drop uploading
    ├── shell.js              # Shell element creation & global behaviors
    └── main.js               # Application initialization
```

## Refactoring Strategy

### Step 1: Extract Utility Functions ✓
**File:** `modules/utils.js`

**Functions to extract:**
- `escapeHtml()` - HTML escape helper
- `formatBytes()` - File size formatting
- `formatDate()` - Date/time formatting
- `showToast()` - Toast notifications
- `renderCardTagsInline()` - Inline tag rendering
- `renderDrawerTags()` - Drawer tag rendering
- `renderModalActiveTags()` - Modal tag rendering
- `getFileTypeInfo()` - File type detection
- `selectItem()` - Item selection helper

### Step 2: Extract State Management
**File:** `modules/state.js`

**Exports:**
```javascript
export const createAppState = () => { ... }
export const getCSRFToken = () => { ... }
export const getDOMElements = () => { ... }
```

### Step 3: Extract Router Functions
**File:** `modules/router.js`

**Functions:**
- `parseUrlToState(urlStr)` - Parse URL to app state
- `navigateSPA(tab, folderId, query, pushHistory)` - Navigate to view
- `loadCurrentView(showLoading)` - Fetch and load view data
- `fetchStorageUsage()` - Update storage info
- `updateActiveSidebarClass()` - Update sidebar active state

### Step 4: Extract Rendering Functions
**File:** `modules/renderer.js`

**Functions:**
- `renderSPAView()` - Main view renderer
- `renderBreadcrumbs()` - Breadcrumb navigation
- `renderCards()` - File/folder card grid/list
- `generateItemCardHtml()` - Individual card HTML
- `renderFilePreview()` - File preview area
- `getMockPreviewHtml()` - Mock preview for documents
- `renderDetailsDrawer()` - Right sidebar drawer

### Step 5: Extract Event Handlers
**File:** `modules/events.js`

**Functions:**
- `bindSidebarNavigation()` - Sidebar click handlers
- `bindNewViewEvents()` - View event handlers
- `bindGlobalDocumentClicks()` - Global document handlers

### Step 6: Extract Context Menu
**File:** `modules/context-menu.js`

**Functions:**
- `showContextMenu(x, y, event)` - Display context menu
- `closeContextMenu()` - Close context menu

### Step 7: Extract Drawer Functions
**File:** `modules/drawer.js`

**Functions:**
- `renderDetailsDrawer()` - Render side drawer

### Step 8: Extract API Functions
**File:** `modules/api.js`

**Functions:**
- `executeStarToggle(id, type)` - Star/unstar item
- `executeFolderCreate(name)` - Create folder
- `executeFileUpload(file, currentFolderId)` - Upload file
- `executeFolderUpload(files, currentFolderId)` - Upload folder
- `executeRename(id, newName, isFolder)` - Rename item
- `executeShare(id, isFolder, email, permission)` - Add collaborator
- `executeDownload(id)` - Download item
- `executeDelete(id, isFolder)` - Move to trash
- `executeRestore(id, isFolder)` - Restore from trash
- `executeForceDelete(id, isFolder)` - Permanently delete
- `executeMove(fromId, toFolderId, isFolder)` - Move item
- `executeTagsSave(itemId, tags)` - Save tags

### Step 9: Extract Modal Functions
**File:** `modules/modals.js`

**Functions:**
- `openRenameModal()` - Open rename modal
- `openModal(modal)` - Generic modal opener
- `closeModal(modal)` - Generic modal closer
- `openShareModal()` - Open share modal
- `openMoveModal()` - Open move modal
- `openTagsModal()` - Open tags modal
- `renderMoveFolderList()` - Render move modal folder list
- `executeMove()` - Execute move operation

### Step 10: Extract Editor Functions
**File:** `modules/editor.js`

**Functions:**
- `launchEditor(fileId)` - Launch OnlyOffice editor
- `launchPreview(fileId)` - Launch file preview

### Step 11: Extract Upload Functions
**File:** `modules/upload.js`

**Functions:**
- `setupDragAndDrop()` - Setup drag-drop handlers

### Step 12: Extract Shell Functions
**File:** `modules/shell.js`

**Functions:**
- `ensureAppShellElements()` - Create modal shells
- `updateStorageWidget()` - Update storage display

## Migration Path

### Phase 1: Extract & Test Individual Modules
1. Extract utility functions (complete ✓)
2. Extract state management
3. Extract router functions
4. Test each module independently

### Phase 2: Update Main File
1. Import modules at top of `drive-premium.js`
2. Replace inline functions with module imports
3. Test functionality

### Phase 3: Optional: Full ES6 Module Conversion
If you want to go all-in with ES6 modules:
1. Add `type="module"` to script tag in HTML
2. Update all functions to be pure exports
3. Remove IIFE wrapper

## Example Module Usage

```javascript
// modules/api.js
export async function executeStarToggle(id, type, csrfToken, state) {
    try {
        const response = await fetch(`/drive/files/${id}/star`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Star toggle error:', error);
        throw error;
    }
}

// In main drive-premium.js
import { executeStarToggle } from './modules/api.js';

// Usage
executeStarToggle(itemId, itemType, csrfToken, state).then(data => {
    showToast(data.message);
    loadCurrentView(false);
});
```

## Shared State & Dependencies

Most functions depend on shared state/globals:
- `state` - Global state object
- `csrfToken` - CSRF token from meta tag
- DOM elements - Various cached DOM references

**Solution:** Pass these as parameters to module functions or use a state management pattern.

## Implementation Steps

1. **Keep original file as backup**
   ```
   drive-premium.js (original)
   drive-premium.backup.js (backup)
   ```

2. **Load modules in order**
   ```html
   <script type="module" src="public/js/modules/utils.js"></script>
   <script type="module" src="public/js/modules/state.js"></script>
   <script type="module" src="public/js/modules/router.js"></script>
   <!-- etc -->
   ```

3. **Update references**
   - Replace inline function calls with module imports
   - Update scope/parameter passing

4. **Test thoroughly**
   - Each feature (navigation, uploads, modals, etc.)
   - Cross-browser compatibility

## Quick Win: Immediate Improvements

Even without full refactoring, you can:

1. Extract `utils.js` (already done ✓)
2. Extract `shell.js` (modal creation)
3. Extract `api.js` (API calls)

These three modules are most self-contained and least dependent on global state.

## Notes

- The current structure uses a single `DOMContentLoaded` wrapper
- All functions share the same scope (state object, DOM refs)
- No build step required - files can be concatenated at deployment
- Consider using a bundler (Webpack, Vite) for production optimization

## Next Steps

1. Choose a module to extract first (suggested: `shell.js` or `api.js`)
2. Extract the functions into a new module file
3. Import and test the module
4. Repeat for other modules
5. Consider adding a module loader or bundler setup

---

**Status:** Initial module structure created
- [x] utils.js - Utility functions
- [x] state.js - State initialization
- [ ] router.js - Navigation
- [ ] renderer.js - View rendering
- [ ] events.js - Event handling
- [ ] context-menu.js - Context menu
- [ ] drawer.js - Sidebar drawer
- [ ] api.js - API calls
- [ ] modals.js - Modal management
- [ ] editor.js - OnlyOffice editor
- [ ] upload.js - File uploads
- [ ] shell.js - Shell creation
- [ ] main.js - Initialization
