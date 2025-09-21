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
        $countdown.find('.ylvp-countdown-message').text('Refresh to watch live');

        // Add refresh button
        if (!$container.find('.ylvp-refresh-btn').length) {
            var $refreshBtn = $('<button class="ylvp-refresh-btn ylvp-auto-refresh" title="Refresh to watch live">🔄 Watch Live</button>');
            $refreshBtn.on('click', function(e) {
                e.preventDefault();
                location.reload();
            });
            $countdown.append($refreshBtn);
        }

        // Auto-refresh after 10 seconds, then every 30 seconds
        setTimeout(function() {
            if ($container.is(':visible')) {
                location.reload();
            }
        }, 10000);

        // Continue checking every 30 seconds
        setInterval(function() {
            if ($container.is(':visible') && $container.find('.ylvp-countdown-complete').length > 0) {
                location.reload();
            }
        }, 30000);
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

})(jQuery);