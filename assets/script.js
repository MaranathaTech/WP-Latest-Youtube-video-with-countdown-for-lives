/**
 * YouTube Latest Video Player JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initializeVideoPlayers();
        setupRefreshButton();
        initializeCountdowns();
        initializeStatusMonitoring();
    });

    /**
     * Initialize all video players on the page
     */
    function initializeVideoPlayers() {
        $('.ylvp-container').each(function() {
            var $container = $(this);
            var $iframe = $container.find('iframe');

            if ($iframe.length) {
                // Add loading state
                $container.addClass('ylvp-loading-state');

                // Handle iframe load
                $iframe.on('load', function() {
                    $container.removeClass('ylvp-loading-state');
                    $container.addClass('ylvp-loaded');
                });

                // Handle iframe errors
                $iframe.on('error', function() {
                    $container.removeClass('ylvp-loading-state');
                    $container.addClass('ylvp-error-state');
                    showError($container, 'Failed to load video player.');
                });
            }
        });
    }

    /**
     * Setup refresh functionality if needed
     */
    function setupRefreshButton() {
        // Add refresh button for admin or when needed
        if (typeof ylvp_ajax !== 'undefined') {
            $('.ylvp-container').each(function() {
                var $container = $(this);
                addRefreshButton($container);
            });
        }
    }

    /**
     * Add refresh button to video container
     */
    function addRefreshButton($container) {
        var $refreshBtn = $('<button class="ylvp-refresh-btn" title="Refresh Video">⟳</button>');

        $refreshBtn.on('click', function(e) {
            e.preventDefault();
            refreshVideo($container);
        });

        $container.find('.ylvp-video-info').append($refreshBtn);
    }

    /**
     * Refresh video content
     */
    function refreshVideo($container) {
        if (typeof ylvp_ajax === 'undefined') {
            return;
        }

        $container.addClass('ylvp-refreshing');

        $.ajax({
            url: ylvp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ylvp_refresh_video',
                nonce: ylvp_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    var $newContainer = $(response.data.html);
                    $container.replaceWith($newContainer);
                    initializeVideoPlayers();
                } else {
                    showError($container, 'Failed to refresh video.');
                }
            },
            error: function() {
                showError($container, 'Network error occurred.');
            },
            complete: function() {
                $container.removeClass('ylvp-refreshing');
            }
        });
    }

    /**
     * Show error message
     */
    function showError($container, message) {
        var $error = $('<div class="ylvp-error">' + message + '</div>');
        $container.find('.ylvp-video-wrapper').replaceWith($error);
    }

    /**
     * Handle responsive video sizing
     */
    function handleResponsiveVideo() {
        $('.ylvp-container').each(function() {
            var $container = $(this);
            var $iframe = $container.find('iframe');

            if ($iframe.length) {
                var containerWidth = $container.width();
                var aspectRatio = 16 / 9;
                var newHeight = containerWidth / aspectRatio;

                $iframe.css({
                    width: containerWidth + 'px',
                    height: newHeight + 'px'
                });
            }
        });
    }

    // Handle window resize for responsive videos
    $(window).on('resize', debounce(handleResponsiveVideo, 250));

    /**
     * Debounce function to limit resize events
     */
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var later = function() {
                clearTimeout(timeout);
                func();
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * YouTube iframe API integration (optional enhancement)
     */
    window.onYouTubeIframeAPIReady = function() {
        $('.ylvp-container iframe').each(function() {
            var $iframe = $(this);
            var videoId = extractVideoId($iframe.attr('src'));

            if (videoId) {
                setupYouTubePlayer($iframe, videoId);
            }
        });
    };

    /**
     * Extract video ID from YouTube URL
     */
    function extractVideoId(url) {
        if (!url) return null;

        var match = url.match(/embed\/([a-zA-Z0-9_-]+)/);
        return match ? match[1] : null;
    }

    /**
     * Setup YouTube player with API
     */
    function setupYouTubePlayer($iframe, videoId) {
        // This would be used for advanced player controls
        // Currently just a placeholder for future enhancements
        console.log('YouTube player ready for video:', videoId);
    }

    /**
     * Initialize countdown timers
     */
    function initializeCountdowns() {
        $('.ylvp-countdown').each(function() {
            var $countdown = $(this);
            var targetTime = $countdown.data('target');

            if (targetTime) {
                startCountdown($countdown, targetTime);
            }
        });
    }

    /**
     * Start countdown timer
     */
    function startCountdown($countdown, targetTime) {
        var targetDate = new Date(targetTime).getTime();

        // Update immediately
        updateCountdownDisplay($countdown, targetDate);

        // Update every second
        var interval = setInterval(function() {
            var now = new Date().getTime();
            var distance = targetDate - now;

            if (distance < 0) {
                clearInterval(interval);
                onCountdownComplete($countdown);
                return;
            }

            updateCountdownDisplay($countdown, targetDate);
        }, 1000);

        // Store interval for cleanup
        $countdown.data('countdown-interval', interval);
    }

    /**
     * Update countdown display
     */
    function updateCountdownDisplay($countdown, targetDate) {
        var now = new Date().getTime();
        var distance = targetDate - now;

        if (distance < 0) {
            return;
        }

        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

        $countdown.find('.ylvp-days').text(String(days).padStart(2, '0'));
        $countdown.find('.ylvp-hours').text(String(hours).padStart(2, '0'));
        $countdown.find('.ylvp-minutes').text(String(minutes).padStart(2, '0'));
        $countdown.find('.ylvp-seconds').text(String(seconds).padStart(2, '0'));
    }

    /**
     * Handle countdown completion
     */
    function onCountdownComplete($countdown) {
        var $container = $countdown.closest('.ylvp-container');

        $countdown.find('.ylvp-countdown-display').html('<div class="ylvp-countdown-complete">Stream is starting!</div>');
        $countdown.find('.ylvp-countdown-message').text('Checking for live stream...');

        // Check video status immediately
        checkVideoStatus($container);

        // Continue checking every 60 seconds (reduced from 15 to save API quota)
        var checkInterval = setInterval(function() {
            if ($container.is(':visible') && $container.hasClass('ylvp-upcoming')) {
                checkVideoStatus($container);
            } else {
                // Stop checking if video is now live
                clearInterval(checkInterval);
            }
        }, 60000);
    }

    /**
     * Check video status via AJAX and update display
     */
    function checkVideoStatus($container) {
        if (typeof ylvp_ajax === 'undefined') {
            console.log('AJAX not configured, falling back to page reload');
            location.reload();
            return;
        }

        $.ajax({
            url: ylvp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ylvp_check_video_status',
                nonce: ylvp_ajax.nonce
            },
            success: function(response) {
                if (response.success && (response.data.status === 'live' || response.data.status === 'completed')) {
                    // Video is now live or ready! Replace the countdown with the video player

                    // Set data attributes before replacing
                    if (response.data.is_live) {
                        $container.attr('data-is-live', 'true');
                        $container.attr('data-video-id', response.data.video_id);
                    } else {
                        $container.attr('data-video-id', response.data.video_id);
                    }

                    replaceCountdownWithVideo($container, response.data.html);
                } else if (response.success && response.data.status === 'upcoming') {
                    // Still upcoming, update message
                    $container.find('.ylvp-countdown-message').text('Stream starting soon... checking again');
                } else {
                    console.log('Error checking video status:', response);
                }
            },
            error: function(xhr, status, error) {
                console.log('Network error checking video status:', error);
                // On error, fall back to page reload after a delay
                setTimeout(function() {
                    location.reload();
                }, 5000);
            }
        });
    }

    /**
     * Replace countdown with video player
     */
    function replaceCountdownWithVideo($container, videoHtml) {
        // Remove the upcoming class
        $container.removeClass('ylvp-upcoming');

        // Replace the countdown wrapper with the video player
        $container.find('.ylvp-countdown-wrapper').fadeOut(400, function() {
            $(this).remove();
            $container.html(videoHtml);

            // Initialize the new video player
            initializeVideoPlayers();

            // Start monitoring if this is a live stream
            if ($container.attr('data-is-live') === 'true') {
                startLiveStreamMonitoring($container);
            }

            // Fade in the video
            $container.find('iframe').hide().fadeIn(600);
        });
    }

    /**
     * Auto-refresh functionality (optional)
     */
    function setupAutoRefresh() {
        if (typeof ylvp_settings !== 'undefined' && ylvp_settings.auto_refresh) {
            var refreshInterval = parseInt(ylvp_settings.refresh_interval) * 1000 || 300000; // 5 minutes default

            setInterval(function() {
                $('.ylvp-container').each(function() {
                    refreshVideo($(this));
                });
            }, refreshInterval);
        }
    }

    // Initialize auto-refresh if enabled
    setupAutoRefresh();

    /**
     * Accessibility enhancements
     */
    function enhanceAccessibility() {
        $('.ylvp-container iframe').each(function() {
            var $iframe = $(this);
            var title = $iframe.closest('.ylvp-container').find('.ylvp-video-title').text();

            if (title && !$iframe.attr('title')) {
                $iframe.attr('title', title);
            }

            // Add keyboard navigation support
            $iframe.attr('tabindex', '0');
        });
    }

    // Enhance accessibility
    enhanceAccessibility();

    /**
     * Initialize status monitoring for live/completed videos
     */
    function initializeStatusMonitoring() {
        // Monitor live streams to detect when they end
        $('.ylvp-container[data-is-live="true"]').each(function() {
            var $container = $(this);
            startLiveStreamMonitoring($container);
        });

        // Monitor completed videos to check for upcoming sermons
        $('.ylvp-container').not('[data-is-live="true"]').not('.ylvp-upcoming').each(function() {
            var $container = $(this);
            startCompletedVideoMonitoring($container);
        });
    }

    /**
     * Monitor a live stream to detect when it ends
     */
    function startLiveStreamMonitoring($container) {
        var videoId = $container.data('video-id');

        // Check every 10 minutes while live (reduced from 2 to save API quota)
        var monitorInterval = setInterval(function() {
            if (!$container.is(':visible')) {
                clearInterval(monitorInterval);
                return;
            }

            checkVideoStatusUpdate($container, videoId, function(response) {
                if (response.data.status === 'completed') {
                    // Stream ended, update to replay
                    console.log('Live stream ended, showing replay');
                    updateVideoDisplay($container, response.data);
                    clearInterval(monitorInterval);

                    // Start monitoring for next upcoming sermon
                    startCompletedVideoMonitoring($container);
                } else if (response.data.status === 'upcoming' && response.data.video_changed) {
                    // New upcoming sermon scheduled
                    console.log('New upcoming sermon detected');
                    updateVideoDisplay($container, response.data);
                    clearInterval(monitorInterval);
                }
            });
        }, 600000); // Check every 10 minutes
    }

    /**
     * Monitor a completed video to check for new upcoming sermons
     */
    function startCompletedVideoMonitoring($container) {
        // Check every 30 minutes for new upcoming sermons (reduced from 5 to save API quota)
        var monitorInterval = setInterval(function() {
            if (!$container.is(':visible')) {
                clearInterval(monitorInterval);
                return;
            }

            var currentVideoId = $container.data('video-id') || '';

            checkVideoStatusUpdate($container, currentVideoId, function(response) {
                if (response.data.status === 'upcoming' && response.data.video_changed) {
                    // New upcoming sermon scheduled
                    console.log('New upcoming sermon detected while showing replay');
                    updateVideoDisplay($container, response.data);
                    clearInterval(monitorInterval);
                }
            });
        }, 1800000); // Check every 30 minutes
    }

    /**
     * Check video status update via AJAX
     */
    function checkVideoStatusUpdate($container, currentVideoId, callback) {
        if (typeof ylvp_ajax === 'undefined') {
            return;
        }

        $.ajax({
            url: ylvp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ylvp_check_video_status',
                nonce: ylvp_ajax.nonce,
                current_video_id: currentVideoId
            },
            success: function(response) {
                if (response.success && callback) {
                    callback(response);
                }
            },
            error: function(xhr, status, error) {
                console.log('Error checking video status:', error);
            }
        });
    }

    /**
     * Update video display based on new status
     */
    function updateVideoDisplay($container, data) {
        if (data.status === 'upcoming') {
            // Switch to countdown mode
            $container.addClass('ylvp-upcoming');
            $container.removeAttr('data-is-live');
            $container.removeAttr('data-video-id');

            $container.fadeOut(300, function() {
                $container.html(data.html);
                $container.fadeIn(300, function() {
                    // Reinitialize countdowns
                    initializeCountdowns();
                });
            });
        } else if (data.status === 'completed' || data.status === 'live') {
            // Update to video player (replay or live)
            $container.removeClass('ylvp-upcoming');

            if (data.is_live) {
                $container.attr('data-is-live', 'true');
                $container.attr('data-video-id', data.video_id);
            } else {
                $container.removeAttr('data-is-live');
                $container.attr('data-video-id', data.video_id);
            }

            $container.fadeOut(300, function() {
                $container.html(data.html);
                $container.fadeIn(300, function() {
                    // Reinitialize video players
                    initializeVideoPlayers();
                });
            });
        }
    }

})(jQuery);