/**
 * Magal Creator Multi-Vendor Marketplace
 * Main UI Interactions & Mobile Drawer
 * Location: /assets/js/main.js
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Menu Toggle
    const navToggle = document.querySelector('.nav-toggle-btn');
    const navLinks = document.querySelector('.nav-links');

    if (navToggle && navLinks) {
        navToggle.addEventListener('click', () => {
            navLinks.classList.toggle('show');
            const icon = navToggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-xmark');
            }
        });
    }

    // 2. How It Works Tab Switcher (Customer vs Provider)
    const stepTabs = document.querySelectorAll('.step-tab-btn');
    const stepGrids = document.querySelectorAll('.step-grid-content');

    stepTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetId = tab.getAttribute('data-target');
            stepTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            stepGrids.forEach(grid => {
                if (grid.id === targetId) {
                    grid.style.display = 'grid';
                } else {
                    grid.style.display = 'none';
                }
            });
        });
    });

    // 3. Confirm Delete Prompts
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const message = el.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 4. Auto-dismiss alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 6000);
    });
});

