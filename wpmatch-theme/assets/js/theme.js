/**
 * WPMatch Dating Theme JavaScript
 * 
 * @package WPMatch_Theme
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Theme initialization
    const WPMatchTheme = {
        init: function() {
            this.bindEvents();
            this.initLazyLoading();
            this.initProfileCards();
            this.initMessaging();
            this.initSearchFilters();
            this.initRegistrationForm();
        },

        bindEvents: function() {
            $(document).ready(this.onReady.bind(this));
            $(window).on('load', this.onLoad.bind(this));
            $(window).on('resize', this.onResize.bind(this));
        },

        onReady: function() {
            // Add loading state removal
            $('body').removeClass('loading');
            
            // Initialize theme features
            this.initSmoothScrolling();
            this.initMobileMenu();
        },

        onLoad: function() {
            // Reveal animations
            this.initScrollAnimations();
        },

        onResize: function() {
            // Handle responsive adjustments
            this.handleResponsiveLayout();
        },

        // Smooth scrolling for anchor links
        initSmoothScrolling: function() {
            $('a[href^="#"]').on('click', function(e) {
                e.preventDefault();
                
                const target = $(this.getAttribute('href'));
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 800);
                }
            });
        },

        // Mobile menu functionality
        initMobileMenu: function() {
            const $menuToggle = $('.wp-block-navigation__responsive-container-open');
            const $mobileMenu = $('.wp-block-navigation__responsive-container');
            
            $menuToggle.on('click', function() {
                $mobileMenu.addClass('is-menu-open');
                $('body').addClass('menu-open');
            });

            $(document).on('click', '.wp-block-navigation__responsive-container-close', function() {
                $mobileMenu.removeClass('is-menu-open');
                $('body').removeClass('menu-open');
            });
        },

        // Lazy loading for images
        initLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                $('.lazy').each(function() {
                    imageObserver.observe(this);
                });
            }
        },

        // Profile card interactions
        initProfileCards: function() {
            $('.wpmatch-profile-card').on('click', function(e) {
                if (!$(e.target).closest('a, button').length) {
                    const profileUrl = $(this).data('profile-url');
                    if (profileUrl) {
                        window.location.href = profileUrl;
                    }
                }
            });

            // Like/Unlike functionality
            $('.profile-like-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $btn = $(this);
                const userId = $btn.data('user-id');
                const isLiked = $btn.hasClass('liked');

                WPMatchTheme.toggleLike(userId, !isLiked, $btn);
            });

            // View profile functionality
            $('.profile-view-btn').on('click', function(e) {
                e.stopPropagation();
                const userId = $(this).data('user-id');
                WPMatchTheme.trackProfileView(userId);
            });
        },

        // Messaging interface
        initMessaging: function() {
            if (!$('.wpmatch-messages').length) return;

            // Conversation selection
            $('.conversation-item').on('click', function() {
                $('.conversation-item').removeClass('active');
                $(this).addClass('active');
                
                const conversationId = $(this).data('conversation-id');
                WPMatchTheme.loadConversation(conversationId);
            });

            // Send message form
            $('#send-message-form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $textarea = $form.find('textarea[name="message"]');
                const message = $textarea.val().trim();
                
                if (message) {
                    const conversationId = $('.conversation-item.active').data('conversation-id');
                    WPMatchTheme.sendMessage(conversationId, message, $form);
                }
            });

            // Auto-resize textarea
            $('textarea[name="message"]').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Real-time message checking
            this.startMessagePolling();
        },

        // Search filters functionality
        initSearchFilters: function() {
            if (!$('.wpmatch-search-form').length) return;

            $('.wpmatch-search-form').on('submit', function(e) {
                e.preventDefault();
                WPMatchTheme.performSearch($(this));
            });

            // Filter change handlers
            $('.wpmatch-search-form select, .wpmatch-search-form input').on('change', function() {
                const $form = $(this).closest('form');
                clearTimeout(WPMatchTheme.searchTimeout);
                WPMatchTheme.searchTimeout = setTimeout(() => {
                    WPMatchTheme.performSearch($form);
                }, 500);
            });

            // Load more results
            $('.load-more-profiles').on('click', function(e) {
                e.preventDefault();
                WPMatchTheme.loadMoreProfiles($(this));
            });
        },

        // Registration form enhancements
        initRegistrationForm: function() {
            if (!$('#wpmatch-registration-form').length) return;

            // Form validation
            $('#wpmatch-registration-form').on('submit', function(e) {
                if (!WPMatchTheme.validateRegistrationForm($(this))) {
                    e.preventDefault();
                }
            });

            // Password strength indicator
            $('input[name="password"]').on('input', function() {
                WPMatchTheme.updatePasswordStrength($(this));
            });

            // Username availability check
            $('input[name="username"]').on('blur', function() {
                WPMatchTheme.checkUsernameAvailability($(this));
            });
        },

        // Scroll animations
        initScrollAnimations: function() {
            if ('IntersectionObserver' in window) {
                const animateObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-in');
                        }
                    });
                }, { threshold: 0.1 });

                $('.wp-block-group, .wp-block-columns, .wpmatch-profile-card').each(function() {
                    animateObserver.observe(this);
                });
            }
        },

        // Responsive layout handling
        handleResponsiveLayout: function() {
            const isMobile = window.innerWidth < 768;
            
            if (isMobile) {
                $('.wpmatch-messages').addClass('mobile-layout');
            } else {
                $('.wpmatch-messages').removeClass('mobile-layout');
            }
        },

        // AJAX Functions
        toggleLike: function(userId, isLike, $btn) {
            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_toggle_like',
                    user_id: userId,
                    like: isLike ? 1 : 0,
                    nonce: wpmatchTheme.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $btn.toggleClass('liked', isLike);
                        const icon = isLike ? '❤️' : '🤍';
                        $btn.find('.icon').text(icon);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        trackProfileView: function(userId) {
            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_track_view',
                    user_id: userId,
                    nonce: wpmatchTheme.nonce
                }
            });
        },

        loadConversation: function(conversationId) {
            const $messageThread = $('.thread-messages');
            
            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_load_conversation',
                    conversation_id: conversationId,
                    nonce: wpmatchTheme.nonce
                },
                beforeSend: function() {
                    $messageThread.html('<div class="wpmatch-loading">Loading messages...</div>');
                },
                success: function(response) {
                    if (response.success) {
                        $messageThread.html(response.data.messages);
                        WPMatchTheme.scrollToBottom($messageThread);
                    }
                }
            });
        },

        sendMessage: function(conversationId, message, $form) {
            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_send_message',
                    conversation_id: conversationId,
                    message: message,
                    nonce: wpmatchTheme.nonce
                },
                beforeSend: function() {
                    $form.find('button').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $form.find('textarea').val('');
                        WPMatchTheme.loadConversation(conversationId);
                    } else {
                        alert(response.data || wpmatchTheme.strings.error);
                    }
                },
                complete: function() {
                    $form.find('button').prop('disabled', false);
                }
            });
        },

        performSearch: function($form) {
            const formData = $form.serialize();
            const $results = $('.wpmatch-search-results');
            
            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: formData + '&action=wpmatch_search_profiles&nonce=' + wpmatchTheme.nonce,
                beforeSend: function() {
                    $results.html('<div class="wpmatch-loading">Searching profiles...</div>');
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                        WPMatchTheme.initProfileCards();
                    }
                }
            });
        },

        loadMoreProfiles: function($btn) {
            const page = parseInt($btn.data('page')) + 1;
            const $container = $('.wpmatch-profiles-grid');
            
            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_load_more_profiles',
                    page: page,
                    nonce: wpmatchTheme.nonce
                },
                beforeSend: function() {
                    $btn.text(wpmatchTheme.strings.loading);
                },
                success: function(response) {
                    if (response.success) {
                        $container.append(response.data.html);
                        $btn.data('page', page);
                        
                        if (!response.data.has_more) {
                            $btn.hide();
                        }
                    }
                },
                complete: function() {
                    $btn.text(wpmatchTheme.strings.loadMore);
                }
            });
        },

        // Form validation
        validateRegistrationForm: function($form) {
            let isValid = true;
            const $errors = $form.find('.error-message');
            $errors.remove();

            // Username validation
            const username = $form.find('input[name="username"]').val();
            if (username.length < 3) {
                this.showFieldError($form.find('input[name="username"]'), 'Username must be at least 3 characters long.');
                isValid = false;
            }

            // Email validation
            const email = $form.find('input[name="email"]').val();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                this.showFieldError($form.find('input[name="email"]'), 'Please enter a valid email address.');
                isValid = false;
            }

            // Password validation
            const password = $form.find('input[name="password"]').val();
            if (password.length < 8) {
                this.showFieldError($form.find('input[name="password"]'), 'Password must be at least 8 characters long.');
                isValid = false;
            }

            // Terms acceptance
            if (!$form.find('input[name="terms"]').is(':checked')) {
                this.showFieldError($form.find('input[name="terms"]'), 'You must accept the terms and conditions.');
                isValid = false;
            }

            return isValid;
        },

        showFieldError: function($field, message) {
            const $error = $('<div class="error-message" style="color: var(--wp--preset--color--error); font-size: var(--wp--preset--font-size--small); margin-top: var(--wp--preset--spacing--10);">' + message + '</div>');
            $field.closest('.form-group').append($error);
        },

        updatePasswordStrength: function($field) {
            const password = $field.val();
            let strength = 0;
            let text = '';
            let color = '';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch (strength) {
                case 0:
                case 1:
                    text = 'Weak';
                    color = 'var(--wp--preset--color--error)';
                    break;
                case 2:
                case 3:
                    text = 'Medium';
                    color = 'var(--wp--preset--color--warning)';
                    break;
                case 4:
                case 5:
                    text = 'Strong';
                    color = 'var(--wp--preset--color--success)';
                    break;
            }

            let $indicator = $field.siblings('.password-strength');
            if (!$indicator.length) {
                $indicator = $('<div class="password-strength" style="font-size: var(--wp--preset--font-size--small); margin-top: var(--wp--preset--spacing--10);"></div>');
                $field.after($indicator);
            }

            $indicator.text('Password strength: ' + text).css('color', color);
        },

        checkUsernameAvailability: function($field) {
            const username = $field.val();
            if (username.length < 3) return;

            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_check_username',
                    username: username,
                    nonce: wpmatchTheme.nonce
                },
                success: function(response) {
                    const $indicator = $field.siblings('.username-availability');
                    if ($indicator.length) {
                        $indicator.remove();
                    }

                    const isAvailable = response.success && response.data.available;
                    const text = isAvailable ? 'Username is available' : 'Username is not available';
                    const color = isAvailable ? 'var(--wp--preset--color--success)' : 'var(--wp--preset--color--error)';

                    $('<div class="username-availability" style="font-size: var(--wp--preset--font-size--small); margin-top: var(--wp--preset--spacing--10); color: ' + color + ';">' + text + '</div>').insertAfter($field);
                }
            });
        },

        // Message polling for real-time updates
        startMessagePolling: function() {
            if (!$('.wpmatch-messages').length) return;

            setInterval(() => {
                const conversationId = $('.conversation-item.active').data('conversation-id');
                if (conversationId) {
                    this.checkNewMessages(conversationId);
                }
            }, 10000); // Check every 10 seconds
        },

        checkNewMessages: function(conversationId) {
            const lastMessageId = $('.thread-messages .message').last().data('message-id');
            
            $.ajax({
                url: wpmatchTheme.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_check_new_messages',
                    conversation_id: conversationId,
                    last_message_id: lastMessageId,
                    nonce: wpmatchTheme.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_new) {
                        WPMatchTheme.loadConversation(conversationId);
                    }
                }
            });
        },

        // Utility functions
        scrollToBottom: function($element) {
            $element.animate({
                scrollTop: $element[0].scrollHeight
            }, 300);
        },

        showNotification: function(message, type = 'info') {
            const $notification = $('<div class="wpmatch-notification ' + type + '">' + message + '</div>');
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.addClass('show');
            }, 100);
            
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => $notification.remove(), 300);
            }, 3000);
        }
    };

    // Initialize theme
    WPMatchTheme.init();

})(jQuery);