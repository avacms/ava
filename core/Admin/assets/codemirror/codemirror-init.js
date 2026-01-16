/**
 * CodeMirror 6 Editor Setup for Ava CMS
 * 
 * This module initializes CodeMirror 6 editors with markdown/YAML support.
 * CodeMirror 6 is MIT licensed - see LICENSE file in this directory.
 * 
 * For self-hosting: Run `npm run build-codemirror` to bundle dependencies,
 * or use the ESM imports from esm.sh CDN (current approach).
 */

// CDN base for CodeMirror ESM modules (esm.sh bundles for browser)
const CDN = 'https://esm.sh';

// Module cache
const modules = {};

// Load a module from CDN
async function load(pkg) {
    if (modules[pkg]) return modules[pkg];
    modules[pkg] = await import(`${CDN}/${pkg}`);
    return modules[pkg];
}

// Create editor configuration
async function createEditorConfig(options = {}) {
    const [
        { EditorView, keymap, highlightSpecialChars, drawSelection, highlightActiveLine, dropCursor, rectangularSelection, crosshairCursor, lineNumbers, highlightActiveLineGutter },
        { EditorState },
        { defaultHighlightStyle, syntaxHighlighting, indentOnInput, bracketMatching, foldGutter, foldKeymap },
        { defaultKeymap, history, historyKeymap, indentWithTab },
        { searchKeymap, highlightSelectionMatches },
        { autocompletion, completionKeymap, closeBrackets, closeBracketsKeymap },
        { markdown, markdownLanguage },
        { yaml },
        { oneDark },
    ] = await Promise.all([
        load('@codemirror/view'),
        load('@codemirror/state'),
        load('@codemirror/language'),
        load('@codemirror/commands'),
        load('@codemirror/search'),
        load('@codemirror/autocomplete'),
        load('@codemirror/lang-markdown'),
        load('@codemirror/lang-yaml'),
        load('@codemirror/theme-one-dark'),
    ]);

    // Light theme (matches Ava admin light mode)
    const lightTheme = EditorView.theme({
        '&': {
            backgroundColor: '#ffffff',
            color: '#0e1c31',
        },
        '.cm-content': {
            caretColor: '#3d52b8',
            fontFamily: 'var(--font-mono, monospace)',
            fontSize: '13px',
            lineHeight: '1.6',
        },
        '.cm-cursor': {
            borderLeftColor: '#3d52b8',
        },
        '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection': {
            backgroundColor: 'rgba(61, 82, 184, 0.2)',
        },
        '.cm-activeLine': {
            backgroundColor: 'rgba(0, 0, 0, 0.03)',
        },
        '.cm-gutters': {
            backgroundColor: '#fafafa',
            color: '#64748b',
            border: 'none',
            borderRight: '1px solid #e5e7eb',
        },
        '.cm-activeLineGutter': {
            backgroundColor: 'rgba(0, 0, 0, 0.05)',
        },
    }, { dark: false });

    // Dark theme (extends oneDark to match Ava admin)
    const darkTheme = EditorView.theme({
        '&': {
            backgroundColor: '#141417',
        },
        '.cm-content': {
            caretColor: '#818cf8',
            fontFamily: 'var(--font-mono, monospace)',
            fontSize: '13px',
            lineHeight: '1.6',
        },
        '.cm-cursor': {
            borderLeftColor: '#818cf8',
        },
        '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection': {
            backgroundColor: 'rgba(129, 140, 248, 0.25)',
        },
        '.cm-activeLine': {
            backgroundColor: 'rgba(255, 255, 255, 0.03)',
        },
        '.cm-gutters': {
            backgroundColor: '#0f0f11',
            color: '#71717a',
            border: 'none',
            borderRight: '1px solid #2e2e33',
        },
        '.cm-activeLineGutter': {
            backgroundColor: 'rgba(255, 255, 255, 0.05)',
        },
    }, { dark: true });

    // Detect theme
    const isDark = () => {
        const html = document.documentElement;
        if (html.dataset.theme === 'light') return false;
        if (html.dataset.theme === 'dark') return true;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    };

    // Base extensions
    const baseExtensions = [
        lineNumbers(),
        highlightActiveLineGutter(),
        highlightSpecialChars(),
        history(),
        foldGutter(),
        drawSelection(),
        dropCursor(),
        EditorState.allowMultipleSelections.of(true),
        indentOnInput(),
        syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
        bracketMatching(),
        closeBrackets(),
        autocompletion(),
        rectangularSelection(),
        crosshairCursor(),
        highlightActiveLine(),
        highlightSelectionMatches(),
        keymap.of([
            ...closeBracketsKeymap,
            ...defaultKeymap,
            ...searchKeymap,
            ...historyKeymap,
            ...foldKeymap,
            ...completionKeymap,
            indentWithTab,
        ]),
        EditorView.lineWrapping,
    ];

    // Theme extensions
    const themeExtensions = isDark() 
        ? [oneDark, darkTheme]
        : [lightTheme];

    // Language extension
    let langExtension;
    if (options.language === 'yaml') {
        langExtension = yaml();
    } else if (options.language === 'markdown-frontmatter' || options.language === 'markdown') {
        // Markdown with YAML frontmatter support
        langExtension = markdown({ base: markdownLanguage });
    } else {
        langExtension = markdown({ base: markdownLanguage });
    }

    // Custom keymap for save
    const customKeymap = [];
    if (options.onSave) {
        customKeymap.push({
            key: 'Mod-s',
            run: () => {
                options.onSave();
                return true;
            }
        });
    }

    // Update listener
    const updateListener = [];
    if (options.onChange) {
        updateListener.push(EditorView.updateListener.of(update => {
            if (update.docChanged) {
                options.onChange(update.state.doc.toString());
            }
        }));
    }

    return {
        extensions: [
            ...baseExtensions,
            ...themeExtensions,
            langExtension,
            keymap.of(customKeymap),
            ...updateListener,
        ],
        EditorView,
        EditorState,
    };
}

/**
 * Initialize a CodeMirror editor
 * @param {HTMLElement} container - Element to attach editor to
 * @param {Object} options - Configuration options
 * @param {string} options.content - Initial content (alias: value)
 * @param {string} options.language - 'markdown', 'yaml', or 'yaml-frontmatter'
 * @param {Function} options.onChange - Called on content change
 * @param {Function} options.onSave - Called on Ctrl+S
 * @returns {Promise<EditorView>} The editor instance
 */
async function createEditor(container, options = {}) {
    const { extensions, EditorView, EditorState } = await createEditorConfig(options);
    
    // Support both 'content' and 'value' for initial content
    const initialContent = options.content ?? options.value ?? '';
    
    const state = EditorState.create({
        doc: initialContent,
        extensions,
    });

    const view = new EditorView({
        state,
        parent: container,
    });

    // Store reference for theme switching and API access
    container._cmView = view;
    container._cmOptions = options;

    return view;
}

/**
 * Get the current content of an editor
 * @param {EditorView} view - The editor instance
 * @returns {string} The editor content
 */
function getValue(view) {
    if (view && view.state) {
        return view.state.doc.toString();
    }
    return '';
}

/**
 * Set the content of an editor
 * @param {EditorView} view - The editor instance
 * @param {string} content - The new content
 */
function setValue(view, content) {
    if (view && view.dispatch) {
        view.dispatch({
            changes: {
                from: 0,
                to: view.state.doc.length,
                insert: content
            }
        });
    }
}

/**
 * Insert text at the current cursor position
 * @param {EditorView} view - The editor instance  
 * @param {string} text - Text to insert
 */
function insertText(view, text) {
    if (view && view.dispatch) {
        const selection = view.state.selection.main;
        view.dispatch({
            changes: {
                from: selection.from,
                to: selection.to,
                insert: text
            },
            selection: { anchor: selection.from + text.length }
        });
        view.focus();
    }
}

/**
 * Get the currently selected text
 * @param {EditorView} view - The editor instance
 * @returns {string} The selected text
 */
function getSelection(view) {
    if (view && view.state) {
        const selection = view.state.selection.main;
        return view.state.sliceDoc(selection.from, selection.to);
    }
    return '';
}

/**
 * Update editor theme (call when system theme changes)
 */
async function updateEditorTheme(container) {
    const view = container._cmView;
    const options = container._cmOptions;
    if (!view || !options) return;

    const content = view.state.doc.toString();
    view.destroy();
    await createEditor(container, { ...options, value: content });
}

/**
 * Line wrap modes:
 * - 'full': Full width wrapping (default)
 * - 'narrow': Narrow centered column (~70ch)
 * - 'none': No wrapping (horizontal scroll)
 */
const WRAP_MODES = ['full', 'narrow', 'none'];

/**
 * Set the line wrap mode for an editor container
 * @param {HTMLElement} container - The editor container element
 * @param {string} mode - 'full', 'narrow', or 'none'
 */
function setLineWrap(container, mode) {
    if (!WRAP_MODES.includes(mode)) mode = 'full';
    
    // Remove all wrap classes
    container.classList.remove('cm-wrap-full', 'cm-wrap-narrow', 'cm-wrap-none');
    // Add the new one
    container.classList.add(`cm-wrap-${mode}`);
    
    // Store preference
    container.dataset.wrapMode = mode;
    try {
        localStorage.setItem('ava-editor-wrap-mode', mode);
    } catch (e) {}
}

/**
 * Cycle to the next line wrap mode
 * @param {HTMLElement} container - The editor container element
 * @returns {string} The new mode
 */
function cycleLineWrap(container) {
    const current = container.dataset.wrapMode || 'full';
    const currentIndex = WRAP_MODES.indexOf(current);
    const nextIndex = (currentIndex + 1) % WRAP_MODES.length;
    const nextMode = WRAP_MODES[nextIndex];
    setLineWrap(container, nextMode);
    return nextMode;
}

/**
 * Get the saved line wrap preference
 * @returns {string} The saved mode or 'full'
 */
function getSavedWrapMode() {
    try {
        return localStorage.getItem('ava-editor-wrap-mode') || 'full';
    } catch (e) {
        return 'full';
    }
}

// Export for global use
window.AvaCodeMirror = {
    createEditor,
    updateEditorTheme,
    getValue,
    setValue,
    insertText,
    getSelection,
    setLineWrap,
    cycleLineWrap,
    getSavedWrapMode,
    WRAP_MODES,
};

// Auto-initialize editors with data-codemirror attribute
// Note: Manual initialization is preferred - this is for fallback/convenience
document.addEventListener('DOMContentLoaded', async () => {
    const editors = document.querySelectorAll('[data-codemirror]');
    for (const el of editors) {
        // Skip if already initialized
        if (el._cmView) continue;
        
        // Find the hidden textarea that holds the value
        const parent = el.closest('.editor-wrapper') || el.closest('.ce-editor-wrapper') || el.parentElement;
        const textarea = parent?.querySelector('textarea.editor-hidden-input');
        
        if (textarea) {
            const language = el.dataset.codemirror || 'markdown';
            const form = textarea.closest('form');
            
            await createEditor(el, {
                content: textarea.value,
                language,
                onChange: (value) => {
                    textarea.value = value;
                },
                onSave: form ? () => form.submit() : undefined,
            });
            
            // Hide original textarea
            textarea.style.display = 'none';
        }
    }
});

// Listen for theme changes
const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        if (mutation.attributeName === 'data-theme') {
            document.querySelectorAll('[data-codemirror]').forEach(el => {
                if (el._cmView) {
                    updateEditorTheme(el);
                }
            });
        }
    }
});
observer.observe(document.documentElement, { attributes: true });
