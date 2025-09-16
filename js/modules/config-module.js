/**
 * Configuration Module
 * Contains all configuration data for the user page
 */

// Dynamic category options based on printing shop services
export const categoryOptions = {
    't-shirt-print': {
        label: 'Process Type',
        options: [
            { value: 'silkscreen', text: 'Silkscreen Process' },
            { value: 'dtf', text: 'DTF Process' },
            { value: 'vinyl', text: 'Vinyl' }
        ]
    },
    'tag-print': {
        label: 'Tag Type',
        options: [
            { value: 'hangtag', text: 'Hangtag' },
            { value: 'etikta', text: 'Etikta (Sublimate)' }
        ]
    },
    'sticker-print': {
        label: 'Sticker Type',
        options: [
            { value: 'die-cut', text: 'Die Cut' },
            { value: 'kiss-cut', text: 'Kiss Cut' },
            { value: 'decals', text: 'Decals' },
            { value: 'product-label', text: 'Product Label' }
        ]
    },
    'card-print': {
        label: 'Card Type',
        options: [
            { value: 'thank-you', text: 'Thank You Card' },
            { value: 'calling', text: 'Calling Card' },
            { value: 'business', text: 'Business Card' },
            { value: 'invitation', text: 'Invitation Card' }
        ],
        sizes: [
            { value: '2x3.5', text: '2" x 3.5" (Standard Business Card)' },
            { value: '3.5x2', text: '3.5" x 2" (Standard Business Card)' },
            { value: '4x6', text: '4" x 6" (Postcard Size)' },
            { value: '5x7', text: '5" x 7" (Greeting Card)' },
            { value: '4.25x5.5', text: '4.25" x 5.5" (A2 Card)' },
            { value: 'custom', text: 'Custom Size' }
        ]
    },
    'document-print': {
        label: 'Document Size',
        options: [
            { value: 'short', text: 'Short (8.5" x 11")' },
            { value: 'long', text: 'Long (8.5" x 14")' },
            { value: 'a4', text: 'A4 (8.27" x 11.69")' }
        ]
    },
    'photo-print': {
        label: 'Photo Size',
        options: [
            { value: 'a4', text: 'A4 (8.27" x 11.69")' },
            { value: '8r', text: '8R (8" x 10")' },
            { value: '6r', text: '6R (6" x 8")' },
            { value: '5r', text: '5R (5" x 7")' },
            { value: '3r', text: '3R (3" x 5")' },
            { value: 'wallet', text: 'Wallet Size (2" x 3.5")' }
        ]
    },
    'photo-copy': {
        label: 'Paper Size',
        options: [
            { value: 'long', text: 'Long' },
            { value: 'short', text: 'Short' }
        ]
    },
    'lamination': {
        label: 'Lamination Size',
        options: [
            { value: 'a4', text: 'A4' },
            { value: '8r', text: '8R' },
            { value: '6r', text: '6R' },
            { value: '5r', text: '5R' },
            { value: '3r', text: '3R' },
            { value: 'wallet', text: 'Wallet Size' },
            { value: 'id', text: 'ID' }
        ]
    },
    'typing-job': {
        label: 'Document Type',
        options: [
            { value: 'document', text: 'Document' },
            { value: 'resume', text: 'Resume' }
        ]
    }
};

// API endpoints
export const API_ENDPOINTS = {
    CSRF_TOKEN: 'api/csrf_token.php',
    REQUESTS: 'api/requests.php',
    USER_ACCOUNT: 'api/user_account.php',
    AI_IMAGE_GENERATOR: 'api/ai_image_generator.php'
};

// File upload constraints
export const FILE_CONSTRAINTS = {
    ALLOWED_TYPES: ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],
    MAX_SIZE: 10 * 1024 * 1024, // 10MB
    PROMPT_MAX_LENGTH: 500
};

// Animation settings
export const ANIMATION_SETTINGS = {
    FORM_TRANSITION_DURATION: 300,
    MODAL_AUTO_CLOSE_DELAY: 3000,
    INITIALIZATION_DELAY: 100
};
