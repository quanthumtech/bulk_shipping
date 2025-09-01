import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import LinkTool from '@editorjs/link';
import Quote from '@editorjs/quote';
import RawTool from '@editorjs/raw';
import SimpleImage from '@editorjs/simple-image';
import Checklist from '@editorjs/checklist';
import Embed from '@editorjs/embed';
import Sortable from 'sortablejs';

// Custom debounce function
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Function to initialize Editor.js
function initializeEditor(pageContent) {
    setTimeout(() => {
        const editorContainer = document.getElementById('editorjs');
        if (!editorContainer) {
            console.log('Editor.js container not found, skipping initialization');
            return;
        }

        if (window.editorInstance && typeof window.editorInstance.destroy === 'function') {
            try {
                window.editorInstance.destroy();
            } catch (e) {
                console.warn('Failed to destroy Editor.js instance:', e);
            }
        }
        window.editorInstance = null;

        let data = { blocks: [] };
        if (pageContent) {
            try {
                data = typeof pageContent === 'string' ? JSON.parse(pageContent) : pageContent;
            } catch (e) {
                console.warn('Invalid content for Editor.js:', pageContent, e);
                data = { blocks: [] };
            }
        }

        window.editorInstance = new EditorJS({
            holder: 'editorjs',
            tools: {
                header: { class: Header, inlineToolbar: ['link'] },
                list: { class: List, inlineToolbar: true },
                linkTool: LinkTool,
                quote: Quote,
                raw: RawTool,
                simpleImage: SimpleImage,
                checklist: Checklist,
                embed: Embed,
            },
            data,
            onReady: () => {
                console.log('Editor.js initialized successfully with content:', data);
            },
            onChange: debounce(async (api, event) => {
                try {
                    console.log('Editor.js onChange triggered:', { event });
                    const outputData = await window.editorInstance.save();
                    console.log('Editor.js content changed, dispatching saveContent:', outputData);
                    if (typeof Livewire === 'undefined') {
                        console.error('Livewire is not defined');
                        return;
                    }
                    Livewire.dispatch('saveContent', { content: outputData });
                    console.log('saveContent event dispatched with content:', outputData);
                } catch (e) {
                    console.error('Error in Editor.js onChange:', e);
                }
            }, 1000),
        });
    }, 100);
}

// Initialize Sortable and Editor.js
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing Sortable');
    
    const sortablePages = document.getElementById('sortable-pages');
    if (sortablePages) {
        Sortable.create(sortablePages, {
            animation: 150,
            onEnd: (evt) => {
                const pageIds = Array.from(sortablePages.querySelectorAll('li')).map((li) => li.getAttribute('data-id'));
                console.log('Dispatching reorderPages with pageIds:', pageIds);
                Livewire.dispatch('reorderPages', { pageIds });
            },
        });
    }

    console.log('Skipping Editor.js initialization on DOM load');
});

document.addEventListener('livewire:init', () => {
    console.log('Livewire initialized, registering init-editor listener');
    Livewire.on('init-editor', ({ content }) => {
        console.log('Received init-editor event with content:', content);
        initializeEditor(content);
    });
});

// Expose initializeEditor globally
window.initializeEditor = initializeEditor;

// Debug Livewire availability
if (typeof Livewire === 'undefined') {
    console.error('Livewire is not defined on page load');
} else {
    console.log('Livewire is available');
}