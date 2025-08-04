/**
 * WPMatch Public JavaScript
 * 
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize WPMatch functionality
    const WPMatch = {
        
        init: function() {
            this.bindEvents();
            this.initProfileSearch();
            this.initMessaging();
            this.initPhotoUpload();
            this.initProfileEditor();
            this.initRegistrationForm();
            this.initInteractions();
        },

        bindEvents: function() {
            // Global event bindings
            $(document).on('click', '.btn-like', this.handleLike);
            $(document).on('click', '.btn-unlike', this.handleUnlike);
            $(document).on('click', '.btn-message', this.handleMessageClick);
            $(document).on('click', '.btn-view-profile', this.handleViewProfile);
            $(document).on('click', '.btn-block-user', this.handleBlockUser);
            $(document).on('click', '.btn-report-user', this.handleReportUser);
            $(document).on('submit', '.wpmatch-ajax-form', this.handleAjaxForm);
        },

        // Profile Search functionality
        initProfileSearch: function() {
            const $searchForm = $('.wpmatch-search-form');

            if ($searchForm.length) {
                $searchForm.on('submit', function(e) {
                    e.preventDefault();
                    WPMatch.performSearch();
                });

                // Auto-load initial results
                this.performSearch();

                // Bind profile card actions
                $(document).on('click', '.profile-card', this.handleProfileClick);
            }
        },

        performSearch: function() {
            const $form = $('.wpmatch-search-form');
            const $results = $('.wpmatch-search-results');
            const formData = $form.serializeArray();
            
            const searchArgs = {};
            formData.forEach(function(field) {
                searchArgs[field.name] = field.value;
            });

            $results.html('<div class="loading">' + wpMatch.strings.loading + '</div>');

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_search_profiles',
                    nonce: wpMatch.nonce,
                    search_args: searchArgs
                },
                success: function(response) {
                    if (response.success) {
                        WPMatch.displaySearchResults(response.data);
                    } else {
                        $results.html('<div class="error">' + (response.data || wpMatch.strings.error) + '</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="error">' + wpMatch.strings.error + '</div>');
                }
            });
        },

        displaySearchResults: function(data) {
            const $results = $('.wpmatch-search-results');
            let html = '';

            if (data.profiles && data.profiles.length > 0) {
                html += '<div class="profiles-grid">';
                data.profiles.forEach(function(profile) {
                    html += WPMatch.buildProfileCard(profile);
                });
                html += '</div>';

                // Pagination info
                if (data.total > data.found) {
                    html += '<div class="pagination-info">';
                    html += wpMatch.strings.showing.replace('%1$d', data.found).replace('%2$d', data.total);
                    html += '</div>';
                }
            } else {
                html = '<div class="no-results">' + wpMatch.strings.noResults + '</div>';
            }

            $results.html(html);
        },

        buildProfileCard: function(profile) {
            const photoUrl = profile.primary_photo ? profile.primary_photo.medium : wpMatch.defaultAvatar;
            const age = profile.age ? ', ' + profile.age : '';
            const location = profile.location || '';
            const distance = profile.distance ? Math.round(profile.distance) + ' km away' : '';

            return `
                <div class="profile-card" data-user-id="${profile.user_id}">
                    <div class="profile-photo">
                        <img src="${photoUrl}" alt="${profile.display_name}" loading="lazy">
                        ${profile.is_online ? '<span class="online-indicator"></span>' : ''}
                    </div>
                    <div class="profile-info">
                        <h3 class="profile-name">${profile.display_name}${age}</h3>
                        ${location ? '<p class="profile-location">' + location + '</p>' : ''}
                        ${distance ? '<p class="profile-distance">' + distance + '</p>' : ''}
                        <div class="profile-actions">
                            <button class="btn btn-like" data-user-id="${profile.user_id}" title="${wpMatch.strings.like}">
                                <i class="heart-icon"></i>
                            </button>
                            <button class="btn btn-message" data-user-id="${profile.user_id}" title="${wpMatch.strings.message}">
                                <i class="message-icon"></i>
                            </button>
                            <button class="btn btn-view-profile" data-user-id="${profile.user_id}" title="${wpMatch.strings.viewProfile}">
                                <i class="view-icon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

        // Messaging functionality
        initMessaging: function() {
            const $messagesContainer = $('.wpmatch-messages');
            
            if ($messagesContainer.length) {
                this.loadConversations();
                this.initMessageForm();
                
                // Auto-refresh messages
                setInterval(function() {
                    if ($('.active-conversation').length) {
                        WPMatch.refreshActiveConversation();
                    }
                }, 10000); // Refresh every 10 seconds
            }
        },

        loadConversations: function() {
            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_get_conversations',
                    nonce: wpMatch.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatch.displayConversations(response.data);
                    }
                }
            });
        },

        displayConversations: function(conversations) {
            const $list = $('.conversations-list .conversations');
            let html = '';

            if (conversations.length > 0) {
                conversations.forEach(function(conversation) {
                    const unreadClass = conversation.unread_count > 0 ? 'unread' : '';
                    const lastMessage = conversation.last_message_content || 'No messages yet';
                    const photoUrl = conversation.other_user.photo_url || wpMatch.defaultAvatar;

                    html += `
                        <div class="conversation-item ${unreadClass}" data-conversation-id="${conversation.id}">
                            <div class="conversation-avatar">
                                <img src="${photoUrl}" alt="${conversation.other_user.display_name}">
                            </div>
                            <div class="conversation-info">
                                <h4>${conversation.other_user.display_name}</h4>
                                <p class="last-message">${lastMessage}</p>
                                <span class="last-time">${WPMatch.timeAgo(conversation.last_message_time)}</span>
                            </div>
                            ${conversation.unread_count > 0 ? '<span class="unread-badge">' + conversation.unread_count + '</span>' : ''}
                        </div>
                    `;
                });
            } else {
                html = '<div class="no-conversations">No conversations yet</div>';
            }

            $list.html(html);

            // Bind conversation click events
            $('.conversation-item').on('click', function() {
                const conversationId = $(this).data('conversation-id');
                WPMatch.loadConversation(conversationId);
            });
        },

        loadConversation: function(conversationId) {
            $('.conversation-item').removeClass('active');
            $('.conversation-item[data-conversation-id="' + conversationId + '"]').addClass('active');

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_get_messages',
                    nonce: wpMatch.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        WPMatch.displayMessages(response.data, conversationId);
                        WPMatch.markMessagesRead(conversationId);
                    }
                }
            });
        },

        displayMessages: function(messages, conversationId) {
            const $thread = $('.message-thread');
            const $messages = $thread.find('.thread-messages');
            
            $thread.addClass('active-conversation').data('conversation-id', conversationId);
            
            let html = '';
            messages.forEach(function(message) {
                const isOwn = message.sender_id == wpMatch.userId;
                const messageClass = isOwn ? 'message-own' : 'message-other';
                
                html += `
                    <div class="message ${messageClass}" data-message-id="${message.id}">
                        <div class="message-content">${message.message_content}</div>
                        <div class="message-time">${WPMatch.timeAgo(message.created_at)}</div>
                    </div>
                `;
            });

            $messages.html(html);
            $messages.scrollTop($messages[0].scrollHeight);
        },

        initMessageForm: function() {
            $('#send-message-form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $textarea = $form.find('textarea[name="message"]');
                const message = $textarea.val().trim();
                const conversationId = $('.active-conversation').data('conversation-id');

                if (!message || !conversationId) {
                    return;
                }

                $.ajax({
                    url: wpMatch.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpmatch_send_message',
                        nonce: wpMatch.nonce,
                        receiver_id: WPMatch.getOtherUserId(conversationId),
                        message_content: message
                    },
                    success: function(response) {
                        if (response.success) {
                            $textarea.val('');
                            WPMatch.loadConversation(conversationId);
                            WPMatch.showNotification(wpMatch.strings.messageSent, 'success');
                        } else {
                            WPMatch.showNotification(response.data, 'error');
                        }
                    }
                });
            });
        },

        // Photo upload functionality
        initPhotoUpload: function() {
            const $uploadBtn = $('#upload-photo-btn');
            
            if ($uploadBtn.length) {
                $uploadBtn.on('click', function() {
                    $('<input type="file" accept="image/*">').on('change', function() {
                        WPMatch.uploadPhoto(this.files[0]);
                    }).click();
                });
            }
        },

        uploadPhoto: function(file) {
            if (!file) return;

            const formData = new FormData();
            formData.append('photo', file);
            formData.append('action', 'wpmatch_upload_photo');
            formData.append('nonce', wpMatch.nonce);

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        WPMatch.showNotification(response.data.message, 'success');
                        location.reload(); // Refresh to show new photo
                    } else {
                        WPMatch.showNotification(response.data, 'error');
                    }
                }
            });
        },

        // Profile editor functionality
        initProfileEditor: function() {
            $('#save-profile-btn').on('click', function() {
                WPMatch.saveProfile();
            });
        },

        saveProfile: function() {
            const profileData = {};
            $('.profile-info input, .profile-info textarea, .profile-info select').each(function() {
                profileData[$(this).attr('name')] = $(this).val();
            });

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_update_profile',
                    nonce: wpMatch.nonce,
                    profile_data: profileData
                },
                success: function(response) {
                    if (response.success) {
                        WPMatch.showNotification(wpMatch.strings.profileUpdated, 'success');
                    } else {
                        WPMatch.showNotification(response.data, 'error');
                    }
                }
            });
        },

        // Registration form functionality
        initRegistrationForm: function() {
            $('#wpmatch-registration-form').on('submit', function(e) {
                e.preventDefault();
                WPMatch.handleRegistration($(this));
            });
        },

        handleRegistration: function($form) {
            const formData = $form.serialize();

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: formData + '&action=wpmatch_register_user',
                success: function(response) {
                    if (response.success) {
                        WPMatch.showNotification(response.data.message, 'success');
                        // Redirect or reload
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        WPMatch.showNotification(response.data, 'error');
                    }
                }
            });
        },

        // Interaction functionality
        initInteractions: function() {
            $(document).on('click', '.btn-like', this.handleLike);
            $(document).on('click', '.btn-unlike', this.handleUnlike);
            $(document).on('click', '.btn-view-profile', this.handleViewProfile);
        },

        handleLike: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const targetUserId = $btn.data('user-id');

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_like_profile',
                    nonce: wpMatch.nonce,
                    target_user_id: targetUserId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.removeClass('btn-like').addClass('btn-unlike')
                            .find('i').removeClass('heart-icon').addClass('heart-filled-icon');
                        
                        if (response.data.is_match) {
                            WPMatch.showMatchNotification();
                        }
                    } else {
                        WPMatch.showNotification(response.data, 'error');
                    }
                }
            });
        },

        handleUnlike: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const targetUserId = $btn.data('user-id');

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_unlike_profile',
                    nonce: wpMatch.nonce,
                    target_user_id: targetUserId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.removeClass('btn-unlike').addClass('btn-like')
                            .find('i').removeClass('heart-filled-icon').addClass('heart-icon');
                    } else {
                        WPMatch.showNotification(response.data, 'error');
                    }
                }
            });
        },

        handleMessageClick: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetUserId = $(this).data('user-id');
            // Redirect to messages page or open modal
            window.location.href = wpMatch.messagesUrl + '?user=' + targetUserId;
        },

        handleViewProfile: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const targetUserId = $(this).data('user-id');
            
            // Record profile view
            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_view_profile',
                    nonce: wpMatch.nonce,
                    profile_user_id: targetUserId
                }
            });

            // Navigate to profile
            window.location.href = wpMatch.profileUrl + '/' + targetUserId;
        },

        handleProfileClick: function() {
            const userId = $(this).data('user-id');
            WPMatch.handleViewProfile.call($('<div>').data('user-id', userId));
        },

        // Utility functions
        timeAgo: function(dateString) {
            if (!dateString) return '';
            
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            const intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60
            };

            for (const [unit, secondsInUnit] of Object.entries(intervals)) {
                const interval = Math.floor(seconds / secondsInUnit);
                if (interval >= 1) {
                    return interval + ' ' + unit + (interval === 1 ? '' : 's') + ' ago';
                }
            }

            return 'Just now';
        },

        showNotification: function(message, type = 'info') {
            const $notification = $('<div>')
                .addClass('wpmatch-notification')
                .addClass('notification-' + type)
                .text(message);

            $('body').append($notification);

            setTimeout(function() {
                $notification.addClass('show');
            }, 100);

            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        showMatchNotification: function() {
            const $modal = $('<div class="match-modal-overlay">')
                .html(`
                    <div class="match-modal">
                        <div class="match-content">
                            <h2>🎉 It's a Match!</h2>
                            <p>You both liked each other. Start a conversation!</p>
                            <button class="btn btn-primary start-conversation">Send Message</button>
                            <button class="btn btn-secondary close-modal">Continue Browsing</button>
                        </div>
                    </div>
                `);

            $('body').append($modal);
            
            $modal.find('.close-modal').on('click', function() {
                $modal.remove();
            });

            $modal.find('.start-conversation').on('click', function() {
                // Navigate to messages
                window.location.href = wpMatch.messagesUrl;
            });
        },

        getOtherUserId: function(conversationId) {
            // This would need to be implemented based on conversation data
            return $('.active-conversation').data('other-user-id');
        },

        markMessagesRead: function(conversationId) {
            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_mark_read',
                    nonce: wpMatch.nonce,
                    conversation_id: conversationId
                }
            });
        },

        refreshActiveConversation: function() {
            const conversationId = $('.active-conversation').data('conversation-id');
            if (conversationId) {
                this.loadConversation(conversationId);
            }
        },

        handleAjaxForm: function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = $form.serialize();

            $.ajax({
                url: wpMatch.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        WPMatch.showNotification(response.data.message || 'Success!', 'success');
                    } else {
                        WPMatch.showNotification(response.data || 'Error occurred', 'error');
                    }
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WPMatch.init();
    });

    // Make WPMatch globally available
    window.WPMatch = WPMatch;

})(jQuery);