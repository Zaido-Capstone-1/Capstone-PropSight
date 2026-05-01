/**
 * PropSight Admin — Responsive Interactions Handler
 * Handles mobile sidebar, touch gestures, and responsive behaviors
 */

(function() {
  'use strict';

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    initSidebarToggle();
    initNavDropdowns();
    initTouchGestures();
    initTableResponsiveness();
    initModalResponsiveness();
    initDropdownMobile();
    initScrollToTop();
    initResponsiveCharts();
    initOrientationChange();
  }

  /**
   * Sidebar Toggle for Mobile
   */
  function initSidebarToggle() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarClose = document.querySelector('.sidebar-close');
    const overlay = document.querySelector('.overlay');

    if (!menuToggle || !sidebar) return;

    // Create overlay if it doesn't exist
    let overlayElement = overlay;
    if (!overlayElement) {
      overlayElement = document.createElement('div');
      overlayElement.className = 'overlay';
      document.body.appendChild(overlayElement);
    }

    // Toggle sidebar
    function toggleSidebar(show) {
      if (show) {
        sidebar.classList.add('open');
        overlayElement.classList.add('visible');
        document.body.style.overflow = 'hidden';
      } else {
        sidebar.classList.remove('open');
        overlayElement.classList.remove('visible');
        document.body.style.overflow = '';
      }
    }

    // Menu toggle click
    menuToggle?.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleSidebar(true);
    });

    // Sidebar close button
    sidebarClose?.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleSidebar(false);
    });

    // Overlay click
    overlayElement.addEventListener('click', () => {
      toggleSidebar(false);
    });

    // Close sidebar when clicking nav links on mobile
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 860) {
          toggleSidebar(false);
        }
      });
    });

    // Handle escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        toggleSidebar(false);
      }
    });

    // Close sidebar on window resize if screen becomes larger
    window.addEventListener('resize', () => {
      if (window.innerWidth > 860 && sidebar.classList.contains('open')) {
        toggleSidebar(false);
      }
    });
  }

  /**
   * Navigation Dropdown Toggle
   * Handles expanding/collapsing nav items with sub-menus (Properties, Bookings, Users, etc.)
   */
  function initNavDropdowns() {
    const navItems = document.querySelectorAll('.nav-item.has-sub');
    
    navItems.forEach(navItem => {
      navItem.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Toggle active/expanded state on the parent
        const wasActive = this.classList.contains('active');
        const wasExpanded = this.classList.contains('expanded');
        
        // Close all other dropdowns
        navItems.forEach(item => {
          if (item !== this) {
            item.classList.remove('active', 'expanded');
            const otherSubMenu = item.nextElementSibling;
            if (otherSubMenu && otherSubMenu.classList.contains('nav-sub')) {
              otherSubMenu.classList.remove('open');
            }
          }
        });
        
        // Toggle this dropdown
        if (wasActive && wasExpanded) {
          this.classList.remove('active', 'expanded');
        } else {
          this.classList.add('active', 'expanded');
        }
        
        // Toggle the submenu
        const subMenu = this.nextElementSibling;
        if (subMenu && subMenu.classList.contains('nav-sub')) {
          if (wasActive && wasExpanded) {
            subMenu.classList.remove('open');
          } else {
            subMenu.classList.add('open');
          }
        }
      });
    });
  }

  /**
   * Touch Gestures for Mobile Navigation
   */
  function initTouchGestures() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    let touchStartX = 0;
    let touchEndX = 0;

    // Swipe from left edge to open sidebar
    document.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    document.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
    }, { passive: true });

    function handleSwipe() {
      const swipeThreshold = 50;
      const edgeThreshold = 30;

      // Swipe right from left edge to open
      if (touchStartX < edgeThreshold && touchEndX - touchStartX > swipeThreshold) {
        if (!sidebar.classList.contains('open')) {
          sidebar.classList.add('open');
          const overlay = document.querySelector('.overlay');
          if (overlay) overlay.classList.add('visible');
        }
      }

      // Swipe left to close
      if (sidebar.classList.contains('open') && touchStartX - touchEndX > swipeThreshold) {
        sidebar.classList.remove('open');
        const overlay = document.querySelector('.overlay');
        if (overlay) overlay.classList.remove('visible');
      }
    }
  }

  /**
   * Make Tables Responsive (Card View on Mobile)
   */
  function initTableResponsiveness() {
    function convertTablesToCards() {
      if (window.innerWidth <= 480) {
        const tables = document.querySelectorAll('table.data-table');
        
        tables.forEach(table => {
          // Skip if already converted
          if (table.classList.contains('table-card-view')) return;

          // Get headers
          const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());

          // Add data-label to each cell
          const rows = table.querySelectorAll('tbody tr');
          rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
              if (headers[index]) {
                cell.setAttribute('data-label', headers[index]);
              }
            });
          });

          table.classList.add('table-card-view');
        });
      } else {
        // Remove card view on larger screens
        const tables = document.querySelectorAll('table.table-card-view');
        tables.forEach(table => {
          table.classList.remove('table-card-view');
          const cells = table.querySelectorAll('td[data-label]');
          cells.forEach(cell => cell.removeAttribute('data-label'));
        });
      }
    }

    convertTablesToCards();
    window.addEventListener('resize', debounce(convertTablesToCards, 250));
  }

  /**
   * Modal Responsiveness
   */
  function initModalResponsiveness() {
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
      // Prevent body scroll when modal is open on mobile
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.attributeName === 'style') {
            const display = window.getComputedStyle(modal).display;
            if (display === 'flex' && window.innerWidth <= 700) {
              document.body.style.overflow = 'hidden';
            } else {
              document.body.style.overflow = '';
            }
          }
        });
      });

      observer.observe(modal, { attributes: true });
    });
  }

  /**
   * Dropdown Mobile Behavior
   */
  function initDropdownMobile() {
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(dropdown => {
      const toggle = dropdown.querySelector('.dropdown-toggle');
      const menu = dropdown.querySelector('.dropdown-menu');

      if (!toggle || !menu) return;

      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target)) {
          menu.classList.remove('show');
        }
      });

      // Position dropdown properly on mobile
      toggle.addEventListener('click', () => {
        if (window.innerWidth <= 700) {
          const rect = toggle.getBoundingClientRect();
          const spaceBelow = window.innerHeight - rect.bottom;
          
          if (spaceBelow < 200) {
            menu.style.bottom = '100%';
            menu.style.top = 'auto';
            menu.style.marginBottom = '8px';
          } else {
            menu.style.top = '100%';
            menu.style.bottom = 'auto';
            menu.style.marginTop = '8px';
          }
        }
      });
    });
  }

  /**
   * Scroll to Top Button
   */
  function initScrollToTop() {
    const pageInner = document.querySelector('.page-inner');
    if (!pageInner) return;

    // Create scroll to top button
    const scrollBtn = document.createElement('button');
    scrollBtn.className = 'scroll-to-top';
    scrollBtn.innerHTML = '↑';
    scrollBtn.setAttribute('aria-label', 'Scroll to top');
    scrollBtn.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: var(--blue-600);
      color: white;
      border: none;
      font-size: 20px;
      cursor: pointer;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s, visibility 0.3s;
      z-index: 100;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;

    document.body.appendChild(scrollBtn);

    // Show/hide on scroll
    pageInner.addEventListener('scroll', () => {
      if (pageInner.scrollTop > 300) {
        scrollBtn.style.opacity = '1';
        scrollBtn.style.visibility = 'visible';
      } else {
        scrollBtn.style.opacity = '0';
        scrollBtn.style.visibility = 'hidden';
      }
    });

    // Scroll to top on click
    scrollBtn.addEventListener('click', () => {
      pageInner.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }

  /**
   * Responsive Chart Handling
   */
  function initResponsiveCharts() {
    // Resize charts on window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        // Trigger Chart.js resize if available
        if (window.Chart && window.Chart.instances) {
          Object.values(window.Chart.instances).forEach(chart => {
            if (chart && chart.resize) {
              chart.resize();
            }
          });
        }
      }, 250);
    });
  }

  /**
   * Handle Orientation Changes
   */
  function initOrientationChange() {
    window.addEventListener('orientationchange', () => {
      // Reload charts and responsive elements after orientation change
      setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
      }, 200);
    });
  }

  /**
   * Utility: Debounce Function
   */
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /**
   * Add smooth scrolling behavior for all internal links
   */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const href = this.getAttribute('href');
      if (href !== '#' && href !== '#!') {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      }
    });
  });

  /**
   * Detect iOS and add class for specific fixes
   */
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  if (isIOS) {
    document.documentElement.classList.add('ios-device');
  }

  /**
   * Handle safe area insets for notched devices
   */
  if (window.CSS && CSS.supports('padding-top: env(safe-area-inset-top)')) {
    document.documentElement.classList.add('has-safe-area');
  }

  // Log initialization
  console.log('PropSight responsive interactions initialized');
})();