/**
 * Theme System - Modern Admin Panel Theme Management
 * Handles theme switching, persistence, and dynamic updates
 */

class ThemeSystem {
    constructor() {
        this.currentTheme = 'default';
        this.themes = {
            'default': 'Modern Mavi',
            'blue': 'Mavi',
            'green': 'Yeşil',
            'purple': 'Mor',
            'orange': 'Turuncu',
            'red': 'Kırmızı',
            'pink': 'Pembe',
            'teal': 'Turkuaz',
            'indigo': 'Çivit',
            'amber': 'Kehribar',
            'cyan': 'Cyan'
        };
        
        this.init();
    }
    
    init() {
        // Load saved theme from localStorage
        const savedTheme = localStorage.getItem('admin_theme');
        if (savedTheme && this.themes[savedTheme]) {
            this.currentTheme = savedTheme;
        }
        
        // Apply current theme
        this.applyTheme(this.currentTheme);
        
        // Listen for theme change events
        document.addEventListener('themeChanged', (e) => {
            this.applyTheme(e.detail.theme);
        });
        
        // Initialize theme switcher if exists
        this.initializeThemeSwitcher();
    }
    
    applyTheme(themeName) {
        if (!this.themes[themeName]) {
            console.warn(`Theme "${themeName}" not found`);
            return;
        }
        
        // Set theme attribute on document
        document.documentElement.setAttribute('data-theme', themeName);
        
        // Update current theme
        this.currentTheme = themeName;
        
        // Save to localStorage
        localStorage.setItem('admin_theme', themeName);
        
        // Update theme switcher UI
        this.updateThemeSwitcherUI(themeName);
        
        // Dispatch theme changed event
        document.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: themeName }
        }));
        
        // Save theme to database via AJAX
        this.saveThemeToDatabase(themeName);
    }
    
    switchTheme(themeName) {
        this.applyTheme(themeName);
    }
    
    getCurrentTheme() {
        return this.currentTheme;
    }
    
    getAvailableThemes() {
        return this.themes;
    }
    
    initializeThemeSwitcher() {
        // Find theme switcher elements
        const themeSwitchers = document.querySelectorAll('[data-theme-switcher]');
        
        themeSwitchers.forEach(switcher => {
            switcher.addEventListener('change', (e) => {
                const selectedTheme = e.target.value;
                this.switchTheme(selectedTheme);
            });
        });
        
        // Initialize theme cards if they exist
        const themeCards = document.querySelectorAll('.theme-option-card');
        themeCards.forEach(card => {
            card.addEventListener('click', (e) => {
                const themeName = card.dataset.theme;
                if (themeName) {
                    this.switchTheme(themeName);
                    
                    // Update selected state
                    document.querySelectorAll('.theme-option-card').forEach(c => {
                        c.classList.remove('selected');
                    });
                    card.classList.add('selected');
                }
            });
        });
    }
    
    updateThemeSwitcherUI(themeName) {
        // Update select elements
        const themeSelects = document.querySelectorAll('[data-theme-switcher]');
        themeSelects.forEach(select => {
            select.value = themeName;
        });
        
        // Update theme cards
        document.querySelectorAll('.theme-option-card').forEach(card => {
            card.classList.remove('selected');
            if (card.dataset.theme === themeName) {
                card.classList.add('selected');
            }
        });
    }
    
    saveThemeToDatabase(themeName) {
        // Send AJAX request to save theme preference
        fetch('ajax/save_theme.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                theme: themeName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Theme saved to database');
            } else {
                console.error('Failed to save theme:', data.error);
            }
        })
        .catch(error => {
            console.error('Error saving theme:', error);
        });
    }
    
    // Utility methods
    getThemeColor(themeName, colorType) {
        const themeColors = {
            'default': {
                primary: '#3b82f6',
                secondary: '#64748b',
                accent: '#2563eb'
            },
            'blue': {
                primary: '#3b82f6',
                secondary: '#1d4ed8',
                accent: '#1e40af'
            },
            'green': {
                primary: '#10b981',
                secondary: '#059669',
                accent: '#047857'
            },
            'purple': {
                primary: '#8b5cf6',
                secondary: '#7c3aed',
                accent: '#6d28d9'
            },
            'orange': {
                primary: '#f59e0b',
                secondary: '#d97706',
                accent: '#b45309'
            },
            'red': {
                primary: '#ef4444',
                secondary: '#dc2626',
                accent: '#b91c1c'
            },
            'pink': {
                primary: '#ec4899',
                secondary: '#db2777',
                accent: '#be185d'
            },
            'teal': {
                primary: '#14b8a6',
                secondary: '#0d9488',
                accent: '#0f766e'
            },
            'indigo': {
                primary: '#6366f1',
                secondary: '#4f46e5',
                accent: '#4338ca'
            },
            'amber': {
                primary: '#f59e0b',
                secondary: '#d97706',
                accent: '#b45309'
            },
            'cyan': {
                primary: '#06b6d4',
                secondary: '#0891b2',
                accent: '#0e7490'
            }
        };
        
        return themeColors[themeName]?.[colorType] || themeColors['default'][colorType];
    }
    
    // Animation methods
    animateThemeChange() {
        // Add transition class to body
        document.body.classList.add('theme-transitioning');
        
        // Remove class after transition
        setTimeout(() => {
            document.body.classList.remove('theme-transitioning');
        }, 300);
    }
    
    // Notification methods
    showThemeNotification(themeName) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Tema Değiştirildi',
                text: `${this.themes[themeName]} teması uygulandı!`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    }
}

// Initialize theme system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.themeSystem = new ThemeSystem();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeSystem;
}
