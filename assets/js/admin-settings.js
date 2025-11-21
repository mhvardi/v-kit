jQuery(function ($) {
    'use strict';

    // Build recipient list from checkboxes + manual numbers
    function collectRecipients() {
        var selected = [];
        $('.vardi-recipient-checkbox:checked').each(function() {
            var num = $(this).data('number');
            if (num) { selected.push(num); }
        });
        var extra = $('#manual_extra_numbers').val() || '';
        return { list: selected, extra: extra };
    }

    // Select all toggles
    $('.vardi-select-all').on('change', function() {
        var group = $(this).data('group');
        $('.vardi-recipient-list[data-group="' + group + '"] .vardi-recipient-checkbox').prop('checked', $(this).is(':checked'));
    });

    // Handler for Manual SMS Sending Tab
    var manualSendBtn = $('#vardi_send_manual_sms_button');
    if (manualSendBtn.length) {
        manualSendBtn.on('click', function (e) {
            e.preventDefault();
            var btn = $(this);
            var spinner = btn.next('.spinner');
            var responseDiv = $('#vardi-manual-sms-response');
            btn.attr('disabled', true);
            spinner.addClass('is-active');
            responseDiv.html('').removeClass('notice-success notice-error notice is-dismissible');

            var recipientsData = collectRecipients();
            var data = {
                action: 'vardi_kit_send_manual_sms',
                nonce: $('#vardi_sms_nonce').val(),
                recipients: recipientsData.list,
                extra_numbers: recipientsData.extra,
                message: $('#manual_sms_message').val()
            };
            $.post(ajaxurl, data, function (response) {
                btn.attr('disabled', false);
                spinner.removeClass('is-active');
                if (response.success) {
                    responseDiv.html('<p>' + response.data + '</p>').addClass('notice notice-success is-dismissible');
                    $('#manual_sms_message').val('');
                } else {
                    responseDiv.html('<p>' + response.data + '</p>').addClass('notice notice-error is-dismissible');
                }
            });
        });
    }

    // --- **NEW**: Code for Automatic Pattern Tab (Admin/Customer tabs) ---
    function toggleStatusBody($toggle, animate) {
        var targetId = $toggle.data('target');
        var body = $('#' + targetId);
        var isChecked = $toggle.is(':checked');
        if (isChecked) {
            body.addClass('is-active');
            if (animate) {
                body.stop(true, true).slideDown(150);
            } else {
                body.show();
            }
            var activeMode = body.find('.vardi-mode-radio:checked');
            if (activeMode.length) {
                switchMode(activeMode, false);
            }
        } else {
            body.removeClass('is-active');
            if (animate) {
                body.stop(true, true).slideUp(150);
            } else {
                body.hide();
            }
        }
    }

    function switchMode($radio, animate) {
        var panelType = $radio.data('target');
        var body = $radio.closest('.vardi-status-body');
        var textPanel = body.find('.vardi-mode-panel-text');
        var patternPanel = body.find('.vardi-mode-panel-pattern');

        var toggleFn = animate ? 'slideUp' : 'hide';
        textPanel.removeClass('is-active').stop(true, true)[toggleFn](animate ? 150 : 0);
        patternPanel.removeClass('is-active').stop(true, true)[toggleFn](animate ? 150 : 0);

        if (panelType === 'text') {
            if (animate) {
                textPanel.addClass('is-active').stop(true, true).slideDown(180);
            } else {
                textPanel.addClass('is-active').show();
            }
        } else {
            if (animate) {
                patternPanel.addClass('is-active').stop(true, true).slideDown(180);
            } else {
                patternPanel.addClass('is-active').show();
            }
        }
    }

    $('.vardi-status-toggle').on('change', function() {
        toggleStatusBody($(this), true);
    });

    // Expand immediately when clicking the whole status head (no extra clicks)
    $('.vardi-status-head').on('click', function(e) {
        // If user clicks on the header container, force enabling + opening
        if ($(e.target).is('input')) { return; }
        var checkbox = $(this).find('.vardi-status-toggle');
        checkbox.prop('checked', true);
        toggleStatusBody(checkbox, true);
    });

    $('.vardi-status-body').on('change', '.vardi-mode-radio', function() {
        switchMode($(this), true);
    });

    $('.vardi-status-toggle').each(function() { toggleStatusBody($(this), false); });
    $('.vardi-mode-radio:checked').each(function() { switchMode($(this), false); });

    // Add token rows inside status cards
    $('.vardi-status-body').on('click', '.add-pattern-token-button', function() {
        var btn = $(this);
        var index = parseInt(btn.data('index'), 10);
        var wrapper = btn.closest('.vardi-token-wrapper');
        var nameBase = wrapper.data('name-base');
        var field = '<div class="vardi-token-row"><label>{' + index + '}</label><input type="text" class="regular-text" name="' + nameBase + '" placeholder="شورت‌کد یا مقدار برای {' + index + '}" /></div>';
        btn.before(field);
        btn.data('index', index + 1);
    });

    // --- End of new code ---


    // Main Settings Page: Random Login URL Generator (Existing code)
    var loginUrlInput = $('#vardi_kit_change_login_url');
    if (loginUrlInput.length) {
        var urlDisplay = $('#vardi_kit_final_login_url');
        var urlBase = $('#vardi_kit_login_url_base').data('home-url');

        $('#vardi_kit_generate_random_url').on('click', function() {
            var randomString = Math.random().toString(36).substring(2, 12);
            loginUrlInput.val(randomString);
            urlDisplay.text(urlBase + randomString);
        });

        loginUrlInput.on('keyup', function() {
            urlDisplay.text(urlBase + $(this).val());
        });
    }
});