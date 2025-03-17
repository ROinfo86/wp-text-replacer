(function($) {
    'use strict';

    // Utility for working with local storage
    const StorageManager = {
        prefix: 'wptr_',
        
        // Save data to local storage
        set: function(key, value) {
            try {
                localStorage.setItem(this.prefix + key, JSON.stringify(value));
            } catch (e) {
                console.error('Error saving to localStorage:', e);
            }
        },
        
        // Retrieve data from local storage
        get: function(key) {
            try {
                const item = localStorage.getItem(this.prefix + key);
                return item ? JSON.parse(item) : null;
            } catch (e) {
                console.error('Error reading from localStorage:', e);
                return null;
            }
        },
        
        // Clear specific data from local storage
        clear: function(key) {
            try {
                localStorage.removeItem(this.prefix + key);
            } catch (e) {
                console.error('Error removing from localStorage:', e);
            }
        }
    };

    // Escape special characters for safe use in regular expressions
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Sanitize input data
    function sanitizeInput(input) {
        if (typeof input !== 'string') return '';
        return input
            .replace(/</g, '&lt;')   // HTML escaping
            .replace(/>/g, '&gt;')
            .trim();
    }

    // Generate a unique hash for replacement rules
    function generateRulesHash(rules) {
        const rulesString = JSON.stringify(rules.map(rule => 
            `${sanitizeInput(rule.search)}:${sanitizeInput(rule.replace)}`
        ).sort());
        
        let hash = 0;
        for (let i = 0; i < rulesString.length; i++) {
            const char = rulesString.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        
        return Math.abs(hash).toString();
    }

    // Safe text replacement function
    function safeTextReplace(rules, mode = 'onload', iterations = 1) {
        if (!Array.isArray(rules) || rules.length === 0) {
            console.warn('Invalid replacement rules');
            return;
        }

        // Generate a unique hash for the current set of rules
        const rulesHash = generateRulesHash(rules);
        
        // Check if rules have already been applied
        const cachedReplacementState = StorageManager.get('replacement_state_' + rulesHash);
        if (cachedReplacementState) {
            console.log('Applying cached replacement state');
            return;
        }

        // Sanitize rules before application
        const sanitizedRules = rules.map(rule => ({
            search: sanitizeInput(rule.search),
            replace: sanitizeInput(rule.replace)
        })).filter(rule => rule.search && rule.replace);

        // List of attributes to replace
        const attributesToReplace = [
            'placeholder', 'title', 'alt', 'value', 
            'data-*', 'aria-*', 'class', 'id'
        ];

        // Safe search and replace in text nodes
        function traverseTextNodes(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                sanitizedRules.forEach(rule => {
                    node.textContent = node.textContent.replace(
                        new RegExp(escapeRegExp(rule.search), 'g'), 
                        rule.replace
                    );
                });
            } else if (node.childNodes && node.childNodes.length > 0) {
                node.childNodes.forEach(traverseTextNodes);
            }
        }

        // Replace in attributes
        function replaceAttributes(element) {
            sanitizedRules.forEach(rule => {
                attributesToReplace.forEach(attr => {
                    // Handle wildcards for data-* and aria-* attributes
                    if (attr.includes('*')) {
                        const attrPrefix = attr.replace('*', '');
                        Array.from(element.attributes).forEach(attribute => {
                            if (attribute.name.startsWith(attrPrefix)) {
                                const currentVal = attribute.value;
                                const newVal = currentVal.replace(
                                    new RegExp(escapeRegExp(rule.search), 'g'), 
                                    rule.replace
                                );
                                if (currentVal !== newVal) {
                                    element.setAttribute(attribute.name, newVal);
                                }
                            }
                        });
                    } else {
                        // Regular attributes
                        if (element.hasAttribute(attr)) {
                            const currentVal = element.getAttribute(attr);
                            const newVal = currentVal.replace(
                                new RegExp(escapeRegExp(rule.search), 'g'), 
                                rule.replace
                            );
                            if (currentVal !== newVal) {
                                element.setAttribute(attr, newVal);
                            }
                        }
                    }
                });
            });

            // Recursive traversal of child elements
            Array.from(element.children).forEach(replaceAttributes);
        }

        // Function to perform replacement with iteration support
        function performReplacement(currentIteration = 1) {
            // Replace in text nodes
            traverseTextNodes(document.body);

            // Replace in attributes
            replaceAttributes(document.body);

            // Save replacement state
            StorageManager.set('replacement_state_' + rulesHash, {
                timestamp: Date.now(),
                rules: sanitizedRules
            });

            // Check iterations for continuous mode
            if (mode === 'continuous' && currentIteration < iterations) {
                setTimeout(() => {
                    performReplacement(currentIteration + 1);
                }, 1000); // Repeat after 1 second
            }
        }

        // Safe execution of replacement
        try {
            performReplacement();
        } catch (error) {
            console.error('Error during text replacement:', error);
        }
    }

    // Initialization on page load
    $(document).ready(function() {
        const settings = window.WPTextReplacerSettings || {};
        
        // Additional settings check
        if (settings.rules && settings.rules.length > 0) {
            safeTextReplace(
                settings.rules, 
                settings.searchMode || 'onload', 
                settings.searchIterations || 1
            );
        }
    });

})(jQuery); 