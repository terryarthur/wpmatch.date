/**
 * WPMatch Admin JavaScript
 * 
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const WPMatchAdmin = {
        init: function() {
            this.bindEvents();
            this.initDashboard();
            this.initUserManagement();
            this.initPhotoModeration();
            this.initDemoContent();
            this.initSettings();
            this.initUpgradeModals();
        },

        bindEvents: function() {
            $(document).ready(this.onReady.bind(this));
        },

        onReady: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Auto-refresh dashboard stats
            if ($('.wpmatch-dashboard').length) {
                this.startStatsRefresh();
            }
        },

        // Dashboard functionality
        initDashboard: function() {
            // Demo content generation
            $(document).on('click', '#generate-demo-content, .generate-demo-content, #generate-demo-users', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.prop('disabled', true).text(wpmatchAdmin.strings.processing);
                
                $.ajax({
                    url: wpmatchAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpmatch_generate_demo_content',
                        nonce: wpmatchAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchAdmin.showNotification(response.data, 'success');
                            // Refresh the page to show new data
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            WPMatchAdmin.showNotification(response.data, 'error');
                        }
                    },
                    error: function() {
                        WPMatchAdmin.showNotification(wpmatchAdmin.strings.error, 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Quick stats refresh
            $('.refresh-stats').on('click', function(e) {
                e.preventDefault();
                WPMatchAdmin.refreshDashboardStats();
            });

            // Chart initialization
            this.initCharts();
        },

        initDemoContent: function() {
            // Already handled in initDashboard - keeping for consistency
        },

        initUserManagement: function() {
            // Bulk actions
            $('.action').on('click', function() {
                const action = $('#bulk-action-selector-top').val();
                const selectedUsers = $('input[name="users[]"]:checked').map(function() {
                    return this.value;
                }).get();

                if (action === '-1' || selectedUsers.length === 0) {
                    return;
                }

                if (action === 'delete' && !confirm(wpmatchAdmin.strings.confirmDelete)) {
                    return;
                }

                WPMatchAdmin.performBulkAction(action, selectedUsers);
            });

            // Individual user actions
            $(document).on('click', '.suspend-user', function() {
                const userId = $(this).data('user-id');
                WPMatchAdmin.suspendUser(userId);
            });

            $(document).on('click', '.view-profile', function() {
                const userId = $(this).data('user-id');
                WPMatchAdmin.viewUserProfile(userId);
            });

            // User search
            $('#search-submit').on('click', function(e) {
                e.preventDefault();
                WPMatchAdmin.searchUsers();
            });

            $('#user-search-input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    WPMatchAdmin.searchUsers();
                }
            });
        },

        initPhotoModeration: function() {
            // Photo approval/rejection
            $(document).on('click', '.approve-photo', function() {
                const photoId = $(this).data('photo-id');
                WPMatchAdmin.moderatePhoto(photoId, 'approve');
            });

            $(document).on('click', '.reject-photo', function() {
                const photoId = $(this).data('photo-id');
                const reason = prompt('Reason for rejection (optional):');
                WPMatchAdmin.moderatePhoto(photoId, 'reject', reason);
            });

            // Bulk photo actions
            $('#bulk-approve-photos').on('click', function() {
                const selectedPhotos = $('.photo-checkbox:checked').map(function() {
                    return $(this).data('photo-id');
                }).get();

                if (selectedPhotos.length === 0) {
                    alert('Please select photos to approve.');
                    return;
                }

                WPMatchAdmin.bulkModeratePhotos(selectedPhotos, 'approve');
            });

            $('#bulk-reject-photos').on('click', function() {
                const selectedPhotos = $('.photo-checkbox:checked').map(function() {
                    return $(this).data('photo-id');
                }).get();

                if (selectedPhotos.length === 0) {
                    alert('Please select photos to reject.');
                    return;
                }

                const reason = prompt('Reason for bulk rejection (optional):');
                WPMatchAdmin.bulkModeratePhotos(selectedPhotos, 'reject', reason);
            });
        },

        initSettings: function() {
            // Settings form handling
            $('.wpmatch-settings-form').on('submit', function(e) {
                e.preventDefault();
                WPMatchAdmin.saveSettings($(this));
            });

            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                const tab = $(this).attr('href').split('tab=')[1];
                window.location.href = $(this).attr('href');
            });

            // Settings validation
            $('input[name*="min_age"], input[name*="max_age"]').on('change', function() {
                WPMatchAdmin.validateAgeSettings();
            });
        },

        initUpgradeModals: function() {
            $('#upgrade-to-premium, #upgrade-premium, .upgrade-btn').on('click', function(e) {
                e.preventDefault();
                WPMatchAdmin.showUpgradeModal();
            });
        },

        initCharts: function() {
            // Initialize dashboard charts if Chart.js is available
            if (typeof Chart !== 'undefined' && $('#stats-chart').length) {
                WPMatchAdmin.createStatsChart();
            }
        },

        initTooltips: function() {
            // Initialize tooltips
            $('[data-tooltip]').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });
        },

        // User Management Functions
        performBulkAction: function(action, userIds) {
            $.ajax({
                url: wpmatchAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_bulk_user_action',
                    nonce: wpmatchAdmin.nonce,
                    bulk_action: action,
                    user_ids: userIds
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotification(response.data, 'success');
                        location.reload();
                    } else {
                        WPMatchAdmin.showNotification(response.data, 'error');
                    }
                }
            });
        },

        suspendUser: function(userId) {
            if (!confirm('Are you sure you want to suspend this user?')) {
                return;
            }

            $.ajax({
                url: wpmatchAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_suspend_user',
                    nonce: wpmatchAdmin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotification(response.data, 'success');
                        location.reload();
                    } else {
                        WPMatchAdmin.showNotification(response.data, 'error');
                    }
                }
            });
        },

        viewUserProfile: function(userId) {
            // Open user profile in modal or new tab
            const profileUrl = '/wp-admin/user-edit.php?user_id=' + userId;
            window.open(profileUrl, '_blank');
        },

        searchUsers: function() {
            const searchTerm = $('#user-search-input').val();
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('s', searchTerm);
            window.location.href = currentUrl.toString();
        },

        // Photo Moderation Functions
        moderatePhoto: function(photoId, action, reason = '') {
            const $photoItem = $('.photo-item[data-photo-id="' + photoId + '"]');
            
            $.ajax({
                url: wpmatchAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_' + action + '_photo',
                    nonce: wpmatchAdmin.nonce,
                    photo_id: photoId,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        $photoItem.fadeOut(300, function() {
                            $(this).remove();
                        });
                        WPMatchAdmin.showNotification(response.data, 'success');
                        WPMatchAdmin.updatePendingPhotoCount();
                    } else {
                        WPMatchAdmin.showNotification(response.data, 'error');
                    }
                }
            });
        },

        bulkModeratePhotos: function(photoIds, action, reason = '') {
            $.ajax({
                url: wpmatchAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_bulk_moderate_photos',
                    nonce: wpmatchAdmin.nonce,
                    photo_ids: photoIds,
                    moderation_action: action,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        photoIds.forEach(function(photoId) {
                            $('.photo-item[data-photo-id="' + photoId + '"]').fadeOut();
                        });
                        WPMatchAdmin.showNotification(response.data, 'success');
                        WPMatchAdmin.updatePendingPhotoCount();
                    } else {
                        WPMatchAdmin.showNotification(response.data, 'error');
                    }
                }
            });
        },

        updatePendingPhotoCount: function() {
            const remaining = $('.photo-item:visible').length;
            $('.pending-photo-count').text(remaining);
            
            if (remaining === 0) {
                $('.photos-grid').html('<div class="no-photos">No photos pending approval.</div>');
            }
        },

        // Settings Functions
        saveSettings: function($form) {
            const formData = $form.serialize();
            const $submitBtn = $form.find('input[type="submit"]');
            const originalText = $submitBtn.val();
            
            $submitBtn.prop('disabled', true).val(wpmatchAdmin.strings.processing);

            $.ajax({
                url: wpmatchAdmin.ajaxUrl,
                type: 'POST',
                data: formData + '&action=wpmatch_save_settings',
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotification(response.data, 'success');
                    } else {
                        WPMatchAdmin.showNotification(response.data, 'error');
                    }
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).val(originalText);
                }
            });
        },

        validateAgeSettings: function() {
            const minAge = parseInt($('input[name*="min_age"]').val());
            const maxAge = parseInt($('input[name*="max_age"]').val());

            if (minAge && maxAge && minAge >= maxAge) {
                alert('Maximum age must be greater than minimum age.');
                $('input[name*="max_age"]').val(minAge + 1);
            }
        },

        // Dashboard Functions
        refreshDashboardStats: function() {
            $.ajax({
                url: wpmatchAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_get_dashboard_stats',
                    nonce: wpmatchAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.updateDashboardStats(response.data);
                    }
                }
            });
        },

        updateDashboardStats: function(stats) {
            $('.stat-number').each(function() {
                const $stat = $(this);
                const statType = $stat.closest('.wpmatch-stat-card').find('h3').text().toLowerCase();
                
                // Update stats based on type
                if (stats[statType]) {
                    $stat.text(stats[statType]);
                }
            });
        },

        startStatsRefresh: function() {
            // Auto-refresh stats every 5 minutes
            setInterval(function() {
                WPMatchAdmin.refreshDashboardStats();
            }, 300000);
        },

        createStatsChart: function() {
            const ctx = document.getElementById('stats-chart').getContext('2d');
            
            // Sample chart - replace with actual data
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'New Members',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#e91e63',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Member Growth'
                        }
                    }
                }
            });
        },

        // Upgrade Modal
        showUpgradeModal: function() {
            const modalHtml = `
                <div class="wpmatch-modal-overlay">
                    <div class="wpmatch-modal">
                        <div class="modal-header">
                            <h2>Upgrade to WPMatch Premium</h2>
                            <button class="modal-close">&times;</button>
                        </div>
                        <div class="modal-content">
                            <div class="upgrade-features">
                                <h3>Premium Features Include:</h3>
                                <ul>
                                    <li>✓ Advanced Matching Algorithm</li>
                                    <li>✓ Video Chat Integration</li>
                                    <li>✓ Unlimited Photo Uploads</li>
                                    <li>✓ Advanced Search Filters</li>
                                    <li>✓ Mobile App Support</li>
                                    <li>✓ Priority Support</li>
                                    <li>✓ Custom Branding</li>
                                    <li>✓ Analytics & Reports</li>
                                </ul>
                            </div>
                            <div class="upgrade-pricing">
                                <div class="price-box">
                                    <h3>Annual Plan</h3>
                                    <div class="price">$99/year</div>
                                    <p>Save 30% compared to monthly</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="button button-primary upgrade-now">Upgrade Now</button>
                            <button class="button button-secondary modal-close">Maybe Later</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Modal event handlers
            $('.modal-close').on('click', function() {
                $('.wpmatch-modal-overlay').remove();
            });

            $('.upgrade-now').on('click', function() {
                window.open('https://wpmatch.com/upgrade', '_blank');
            });
        },

        // Utility Functions
        showNotification: function(message, type = 'info') {
            const $notification = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible')
                .html('<p>' + message + '</p>');

            $('.wrap h1').after($notification);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut();
            }, 5000);
        },

        formatNumber: function(number) {
            return new Intl.NumberFormat().format(number);
        },

        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },

        formatTime: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WPMatchAdmin.init();
    });

    // Make WPMatchAdmin globally available
    window.WPMatchAdmin = WPMatchAdmin;

})(jQuery);