/**
 * Alabama Navigation System JavaScript
 * Handles sidebar, global search, badges, and favorites
 */

(function() {
    'use strict';
    
    // State
    let favorites = [];
    let searchPages = [];
    let selectedSearchIndex = 0;
    
    // DOM Elements
    const sidebar = document.getElementById('alabama-sidebar');
    const sidebarToggle = document.getElementById('alabama-sidebar-toggle');
    const sidebarCollapse = document.getElementById('alabama-sidebar-collapse');
    const sidebarOverlay = document.getElementById('alabama-sidebar-overlay');
    const searchTrigger = document.getElementById('alabama-search-trigger');
    const searchModal = document.getElementById('alabama-search-modal');
    const searchInput = document.getElementById('alabama-search-input');
    const searchClose = document.getElementById('alabama-search-close');
    const searchResults = document.getElementById('alabama-search-results');
    const searchEmpty = document.getElementById('alabama-search-empty');
    
    // ============================================
    // 1. SIDEBAR FUNCTIONALITY
    // ============================================
    
    /**
     * Initialize sidebar
     */
    function initSidebar() {
        if (!sidebar) return;
        
        // Restore sidebar state from localStorage
        const sidebarState = localStorage.getItem('alabama_sidebar_state');
        if (sidebarState === 'mini') {
            sidebar.classList.add('mini');
        }
        
        // Restore expanded categories from localStorage
        const expandedCategories = JSON.parse(localStorage.getItem('alabama_expanded_categories') || '[]');
        expandedCategories.forEach(categoryId => {
            const category = document.querySelector(`[data-category="${categoryId}"]`);
            if (category) {
                category.classList.add('expanded');
            }
        });
        
        // Toggle sidebar on mobile
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }
        
        // Collapse sidebar to mini mode
        if (sidebarCollapse) {
            sidebarCollapse.addEventListener('click', () => {
                sidebar.classList.toggle('mini');
                const state = sidebar.classList.contains('mini') ? 'mini' : 'full';
                localStorage.setItem('alabama_sidebar_state', state);
            });
        }
        
        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
        }
        
        // Category toggle
        const categoryToggles = document.querySelectorAll('.alabama-category-toggle');
        categoryToggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const categoryId = toggle.dataset.category;
                const category = toggle.closest('.alabama-sidebar-category');
                
                category.classList.toggle('expanded');
                
                // Save expanded state
                updateExpandedCategories();
            });
        });
    }
    
    /**
     * Update expanded categories in localStorage
     */
    function updateExpandedCategories() {
        const expanded = [];
        document.querySelectorAll('.alabama-sidebar-category.expanded').forEach(cat => {
            const toggle = cat.querySelector('.alabama-category-toggle');
            if (toggle) {
                expanded.push(toggle.dataset.category);
            }
        });
        localStorage.setItem('alabama_expanded_categories', JSON.stringify(expanded));
    }
    
    // ============================================
    // 2. GLOBAL SEARCH
    // ============================================
    
    /**
     * Initialize global search
     */
    function initGlobalSearch() {
        if (!searchModal) return;
        
        // Get searchable pages from window object (set by PHP)
        searchPages = window.ALABAMA_SEARCH_PAGES || [];
        
        // Open search with Ctrl+K or Cmd+K
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openSearch();
            }
            
            // Close search with Escape
            if (e.key === 'Escape' && searchModal.style.display !== 'none') {
                closeSearch();
            }
        });
        
        // Open search with trigger button
        if (searchTrigger) {
            searchTrigger.addEventListener('click', openSearch);
        }
        
        // Close search
        if (searchClose) {
            searchClose.addEventListener('click', closeSearch);
        }
        
        // Close search when clicking outside
        if (searchModal) {
            searchModal.addEventListener('click', (e) => {
                if (e.target === searchModal) {
                    closeSearch();
                }
            });
        }
        
        // Search input
        if (searchInput) {
            searchInput.addEventListener('input', handleSearch);
            searchInput.addEventListener('keydown', handleSearchNavigation);
        }
    }
    
    /**
     * Open search modal
     */
    function openSearch() {
        if (!searchModal || !searchInput) return;
        
        searchModal.style.display = 'flex';
        searchInput.value = '';
        searchInput.focus();
        
        // Show all pages initially
        renderSearchResults(searchPages);
    }
    
    /**
     * Close search modal
     */
    function closeSearch() {
        if (!searchModal) return;
        
        searchModal.style.display = 'none';
        selectedSearchIndex = 0;
    }
    
    /**
     * Handle search input
     */
    function handleSearch(e) {
        const query = e.target.value.toLowerCase().trim();
        
        if (!query) {
            renderSearchResults(searchPages);
            return;
        }
        
        // Filter pages
        const filtered = searchPages.filter(page => {
            return page.label.toLowerCase().includes(query) ||
                   page.category.toLowerCase().includes(query) ||
                   page.url.toLowerCase().includes(query);
        });
        
        renderSearchResults(filtered);
    }
    
    /**
     * Handle keyboard navigation in search
     */
    function handleSearchNavigation(e) {
        const items = document.querySelectorAll('.alabama-search-result-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedSearchIndex = Math.min(selectedSearchIndex + 1, items.length - 1);
            updateSearchSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedSearchIndex = Math.max(selectedSearchIndex - 1, 0);
            updateSearchSelection(items);
        } else if (e.key === 'Enter' && items.length > 0) {
            e.preventDefault();
            const selected = items[selectedSearchIndex];
            if (selected) {
                window.location.href = selected.dataset.url;
            }
        }
    }
    
    /**
     * Update search selection
     */
    function updateSearchSelection(items) {
        items.forEach((item, index) => {
            if (index === selectedSearchIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                item.classList.remove('selected');
            }
        });
    }
    
    /**
     * Render search results
     */
    function renderSearchResults(pages) {
        if (!searchResults || !searchEmpty) return;
        
        selectedSearchIndex = 0;
        
        if (pages.length === 0) {
            searchResults.innerHTML = '';
            searchEmpty.style.display = 'flex';
            return;
        }
        
        searchEmpty.style.display = 'none';
        
        const html = pages.map((page, index) => `
            <a href="${escapeHtml(page.url)}" 
               class="alabama-search-result-item ${index === 0 ? 'selected' : ''}"
               data-url="${escapeHtml(page.url)}">
                <div class="alabama-search-result-icon">
                    <i class="fas ${escapeHtml(page.icon)}"></i>
                </div>
                <div class="alabama-search-result-content">
                    <div class="alabama-search-result-label">${escapeHtml(page.label)}</div>
                    <div class="alabama-search-result-category">${escapeHtml(page.category)}</div>
                </div>
            </a>
        `).join('');
        
        searchResults.innerHTML = html;
        
        // Add click handlers
        const items = searchResults.querySelectorAll('.alabama-search-result-item');
        items.forEach((item, index) => {
            item.addEventListener('mouseenter', () => {
                selectedSearchIndex = index;
                updateSearchSelection(items);
            });
        });
    }
    
    // ============================================
    // 3. BADGES
    // ============================================
    
    /**
     * Initialize badges
     */
    function initBadges() {
        // Load badges immediately
        loadBadges();
        
        // Update badges every 30 seconds
        setInterval(loadBadges, 30000);
    }
    
    /**
     * Load badges from API
     */
    function loadBadges() {
        fetch('api/menu_badges.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.badges) {
                    updateBadges(data.badges);
                }
            })
            .catch(err => {
                console.warn('Failed to load badges:', err);
            });
    }
    
    /**
     * Update badge counts in the UI
     */
    function updateBadges(badges) {
        Object.keys(badges).forEach(badgeType => {
            const count = badges[badgeType];
            const elements = document.querySelectorAll(`[data-badge-type="${badgeType}"]`);
            
            elements.forEach(el => {
                if (count > 0) {
                    el.textContent = count > 99 ? '99+' : count;
                    el.style.display = 'inline-flex';
                } else {
                    el.textContent = '';
                    el.style.display = 'none';
                }
            });
        });
    }
    
    // ============================================
    // 4. FAVORITES
    // ============================================
    
    /**
     * Initialize favorites
     */
    function initFavorites() {
        // Load favorites
        loadFavorites();
        
        // Add click handlers to favorite buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.alabama-favorite-btn')) {
                const btn = e.target.closest('.alabama-favorite-btn');
                const link = btn.closest('.alabama-menu-item');
                
                if (link) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleFavorite(link, btn);
                }
            }
        });
    }
    
    /**
     * Load favorites from API
     */
    function loadFavorites() {
        fetch('api/favorites.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.favorites) {
                    favorites = data.favorites;
                    updateFavoritesUI();
                }
            })
            .catch(err => {
                console.warn('Failed to load favorites:', err);
            });
    }
    
    /**
     * Toggle favorite
     */
    function toggleFavorite(link, btn) {
        const url = link.dataset.url;
        const label = link.dataset.label;
        const icon = link.dataset.icon;
        const isFavorited = btn.dataset.favorited === 'true';
        
        const action = isFavorited ? 'remove' : 'add';
        const csrfToken = window.AL_BAMA_CSRF_TOKEN || '';
        
        const payload = {
            action: action,
            page_url: url,
            page_label: label,
            page_icon: icon,
            _csrf_token: csrfToken
        };
        
        fetch('api/favorites.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update UI
                btn.dataset.favorited = isFavorited ? 'false' : 'true';
                
                // Reload favorites
                loadFavorites();
            }
        })
        .catch(err => {
            console.error('Failed to toggle favorite:', err);
        });
    }
    
    /**
     * Update favorites UI
     */
    function updateFavoritesUI() {
        // Update all favorite buttons
        const allLinks = document.querySelectorAll('.alabama-menu-item');
        allLinks.forEach(link => {
            const btn = link.querySelector('.alabama-favorite-btn');
            if (!btn) return;
            
            const url = link.dataset.url;
            const isFavorited = favorites.some(fav => fav.page_url === url);
            
            btn.dataset.favorited = isFavorited ? 'true' : 'false';
        });
    }
    
    // ============================================
    // 5. UTILITY FUNCTIONS
    // ============================================
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ============================================
    // 6. INITIALIZATION
    // ============================================
    
    /**
     * Initialize all navigation features
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        initSidebar();
        initGlobalSearch();
        initBadges();
        initFavorites();
    }
    
    // Start initialization
    init();
    
})();
