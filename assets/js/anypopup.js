jQuery(document).ready(function ($) {
    var popups = anypopup_settings.popups;

    function showPopup(popup) {
        $('#anypopup-' + popup.id).fadeIn();
    }

    function hidePopup(popup) {
        $('#anypopup-' + popup.id).fadeOut();
        setPopupClosed(popup);
    }

    function setPopupClosed(popup) {
        var now = new Date().getTime();
        localStorage.setItem('anypopup_closed_' + popup.id, now);
    }

    function shouldShowPopup(popup) {
        var lastClosed = localStorage.getItem('anypopup_closed_' + popup.id);
        var now = new Date().getTime();

        // Check closed display delay
        if (lastClosed && now - lastClosed < popup.closed_display_delay * 60 * 60 * 1000) {
            return false;
        }

        switch (popup.display_frequency) {
            case 'every_time':
                return true;
            case 'once_per_session':
                if (sessionStorage.getItem('anypopup_shown_' + popup.id)) {
                    return false;
                }
                sessionStorage.setItem('anypopup_shown_' + popup.id, 'true');
                return true;
            case 'once_per_day':
                var lastShown = localStorage.getItem('anypopup_shown_' + popup.id);
                if (lastShown && now - lastShown < 24 * 60 * 60 * 1000) {
                    return false;
                }
                localStorage.setItem('anypopup_shown_' + popup.id, now);
                return true;
            default:
                return true;
        }
    }

    $.each(popups, function (index, popup) {
        if (shouldShowPopup(popup)) {
            setTimeout(function () {
                showPopup(popup);
            }, popup.delay_time * 1000);
        }

        $('#anypopup-' + popup.id + ' .anypopup-close').click(function () {
            hidePopup(popup);
        });
    });
});