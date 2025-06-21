/**
 * Bridge MLS Listings Plugin JavaScript
 * Enhanced for mobile and user experience
 */

jQuery(document).ready(function($) {
    
    // Detect if mobile device
    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Initialize Select2 with mobile optimizations
    function initializeSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            setTimeout(initializeSelect2, 100);
            return;
        }
        
        // Mobile-optimized Select2 configuration
        $('#mls-cities').select2({
            placeholder: 'Tap to search cities...',
            allowClear: true,
            width: '100%',
            closeOnSelect: false,
            scrollAfterSelect: false,
            minimumResultsForSearch: 0,
            dropdownAutoWidth: true,
            // Mobile-specific options
            minimumInputLength: isMobile ? 0 : 0,
            maximumSelectionLength: 20,
            language: {
                searching: function() {
                    return 'Searching cities...';
                },
                noResults: function() {
                    return 'No cities found';
                },
                maximumSelected: function(e) {
                    return 'Maximum ' + e.maximum + ' cities allowed';
                }
            }
        });
        
        // Add select all/none buttons for better UX
        $('#mls-cities').on('select2:open', function() {
            if (!$('.select2-results').find('.select-buttons').length) {
                var buttons = $('<div class="select-buttons">' +
                    '<button type="button" class="select-all">Select All</button>' +
                    '<button type="button" class="select-none">Clear All</button>' +
                    '</div>');
                $('.select2-results').prepend(buttons);
                
                $('.select-all').on('click', function(e) {
                    e.stopPropagation();
                    var allValues = [];
                    $('#mls-cities option').each(function() {
                        allValues.push($(this).val());
                    });
                    $('#mls-cities').val(allValues).trigger('change');
                });
                
                $('.select-none').on('click', function(e) {
                    e.stopPropagation();
                    $('#mls-cities').val(null).trigger('change');
                });
            }
        });
    }
    
    // Initialize Select2
    initializeSelect2();
    
    // Smooth scroll to results after search
    $('.bridge-mls-search-form').on('submit', function(e) {
        var currentUrl = window.location.href.split('?')[0];
        $(this).attr('action', currentUrl);
        
        // Show loading indicator
        var $submitBtn = $(this).find('.btn-search');
        var originalText = $submitBtn.text();
        $submitBtn.html('<span class="loading-spinner"></span> Searching...').prop('disabled', true);
    });
    
    // Lazy load images for better performance
    if ('IntersectionObserver' in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });
        
        // Observe all property images
        $('.property-image img[data-src]').each(function() {
            imageObserver.observe(this);
        });
    }
    
    // Handle View Details with loading animation
    $('.bridge-mls-listings').on('click', '.btn-view-details', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var listingKey = $button.data('listing-key');
        var originalText = $button.html();
        
        // Show loading state with spinner
        $button.html('<span class="loading-spinner"></span> Loading...').prop('disabled', true);
        
        // Make AJAX request
        $.ajax({
            url: bridge_mls_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bridge_mls_property_details',
                listing_key: listingKey,
                nonce: bridge_mls_ajax.nonce
            },
            success: function(response) {
                // Create modal with fade-in effect
                var modal = $('<div class="mls-modal-overlay">' +
                    '<div class="mls-modal">' +
                        '<button class="mls-modal-close" aria-label="Close">&times;</button>' +
                        '<div class="mls-modal-content">' + response + '</div>' +
                    '</div>' +
                '</div>');
                
                $('body').append(modal).addClass('modal-open');
                
                // Animate modal entrance
                setTimeout(function() {
                    modal.addClass('show');
                }, 10);
                
                // Initialize swipe gestures for mobile image gallery
                if (isMobile) {
                    initializeImageSwipe(modal.find('.property-images'));
                }
                
                // Restore button
                $button.html(originalText).prop('disabled', false);
            },
            error: function() {
                // User-friendly error message
                showNotification('Unable to load property details. Please try again.', 'error');
                $button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Enhanced modal close functionality
    $('body').on('click', '.mls-modal-close, .mls-modal-overlay', function(e) {
        if (e.target === this) {
            var $modal = $(this).closest('.mls-modal-overlay');
            $modal.removeClass('show');
            $('body').removeClass('modal-open');
            setTimeout(function() {
                $modal.remove();
            }, 300);
        }
    });
    
    // Keyboard navigation
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.mls-modal-overlay.show').find('.mls-modal-close').click();
        }
    });
    
    // Touch-friendly swipe to close modal on mobile
    if (isMobile) {
        var touchStartY = 0;
        
        $('body').on('touchstart', '.mls-modal', function(e) {
            touchStartY = e.originalEvent.touches[0].clientY;
        });
        
        $('body').on('touchmove', '.mls-modal', function(e) {
            var touchEndY = e.originalEvent.touches[0].clientY;
            var diff = touchStartY - touchEndY;
            
            // Swipe down to close
            if (diff < -100 && $(e.target).closest('.property-images').length === 0) {
                $(this).closest('.mls-modal-overlay').find('.mls-modal-close').click();
            }
        });
    }
    
    // Initialize touch-friendly image swipe gallery
    function initializeImageSwipe($gallery) {
        if (!$gallery.length) return;
        
        var startX = 0;
        var scrollLeft = 0;
        
        $gallery.on('touchstart', function(e) {
            startX = e.originalEvent.touches[0].pageX - this.offsetLeft;
            scrollLeft = this.scrollLeft;
        });
        
        $gallery.on('touchmove', function(e) {
            e.preventDefault();
            var x = e.originalEvent.touches[0].pageX - this.offsetLeft;
            var walk = (x - startX) * 2;
            this.scrollLeft = scrollLeft - walk;
        });
    }
    
    // Show notification function
    function showNotification(message, type) {
        var notification = $('<div class="mls-notification ' + type + '">' +
            '<p>' + message + '</p>' +
            '</div>');
        
        $('body').append(notification);
        
        setTimeout(function() {
            notification.addClass('show');
        }, 10);
        
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    // Add "Back to Top" button for mobile
    if (isMobile) {
        var $backToTop = $('<button class="back-to-top" aria-label="Back to top">↑</button>');
        $('body').append($backToTop);
        
        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 300) {
                $backToTop.addClass('show');
            } else {
                $backToTop.removeClass('show');
            }
        });
        
        $backToTop.on('click', function() {
            $('html, body').animate({ scrollTop: 0 }, 300);
        });
    }
    
    // Price formatting for better readability
    $('.property-price').each(function() {
        var price = $(this).text().replace(/[^0-9]/g, '');
        if (price) {
            $(this).text('$' + parseInt(price).toLocaleString());
        }
    });
    
    // Auto-save search preferences to sessionStorage
    $('.bridge-mls-search-form input, .bridge-mls-search-form select').on('change', function() {
        var formData = $('.bridge-mls-search-form').serializeArray();
        sessionStorage.setItem('mls_search_preferences', JSON.stringify(formData));
    });
    
    // Restore search preferences on page load
    var savedPreferences = sessionStorage.getItem('mls_search_preferences');
    if (savedPreferences && !window.location.search) {
        try {
            var preferences = JSON.parse(savedPreferences);
            // Optionally restore preferences
        } catch(e) {
            console.log('Could not restore search preferences');
        }
    }
});

// Add dynamic styles
(function() {
    var style = document.createElement('style');
    style.textContent = `
        /* Modal Styles */
        .mls-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            padding: 20px;
            overflow-y: auto;
        }
        
        .mls-modal-overlay.show {
            opacity: 1;
        }
        
        body.modal-open {
            overflow: hidden;
        }
        
        .mls-modal {
            background: #fff;
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: auto;
            position: relative;
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .mls-modal-overlay.show .mls-modal {
            transform: scale(1) translateY(0);
        }
        
        .mls-modal-close {
            position: sticky;
            top: 10px;
            right: 10px;
            float: right;
            background: rgba(255,255,255,0.9);
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .mls-modal-close:hover {
            background-color: #f0f0f0;
            color: #333;
            transform: rotate(90deg);
        }
        
        .mls-modal-content {
            padding: 30px;
        }
        
        /* Select buttons in dropdown */
        .select-buttons {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 10px;
            background: #f8f8f8;
        }
        
        .select-buttons button {
            flex: 1;
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .select-buttons button:hover {
            background: #007cba;
            color: #fff;
            border-color: #007cba;
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Back to top button */
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: #007cba;
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background: #005a87;
            transform: translateY(-3px);
        }
        
        /* Notification styles */
        .mls-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: #fff;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 10000;
            max-width: 300px;
        }
        
        .mls-notification.show {
            transform: translateX(0);
        }
        
        .mls-notification.error {
            background: #dc3545;
        }
        
        .mls-notification p {
            margin: 0;
            font-size: 15px;
        }
        
        /* Mobile specific modal styles */
        @media (max-width: 768px) {
            .mls-modal-overlay {
                padding: 0;
                align-items: flex-end;
            }
            
            .mls-modal {
                max-height: 95vh;
                border-radius: 20px 20px 0 0;
                margin: 0;
            }
            
            .mls-modal-content {
                padding: 20px;
            }
            
            .mls-notification {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }
        
        /* Image loaded animation */
        .property-image img {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .property-image img.loaded {
            opacity: 1;
        }
    `;
    document.head.appendChild(style);
})();