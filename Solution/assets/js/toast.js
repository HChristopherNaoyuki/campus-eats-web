/**
 * Toast Notification Module
 *
 * Presents a non-blocking message to the user. The toast appears at
 * the bottom of the screen, remains visible for a short time, and
 * fades out. The module registers a global handler so that any
 * uncaught JavaScript error also produces a toast. The handler does
 * not replace the console error. It only surfaces the error to the
 * user.
 *
 * SOURCE: REPORT.txt, Robust Error Handling.
 *
 * @version 1.0
 */

(function()
{
    'use strict';

    var container = null;
    var defaultDuration = 4000;

    function ensureContainer()
    {
        if (container !== null)
        {
            return container;
        }

        container = document.createElement('div');
        container.className = 'toast-container';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);

        return container;
    }

    function show(message, type, duration)
    {
        var box = ensureContainer();

        var toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = String(message);

        if (type === 'success')
        {
            toast.classList.add('toast-success');
        }
        else if (type === 'error')
        {
            toast.classList.add('toast-error');
        }
        else if (type === 'warning')
        {
            toast.classList.add('toast-warning');
        }

        box.appendChild(toast);

        var timeout = typeof duration === 'number' ? duration : defaultDuration;

        setTimeout(function()
        {
            toast.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';

            setTimeout(function()
            {
                if (toast.parentNode)
                {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }, timeout);
    }

    window.showToast = show;

    // Global error handler. When a JavaScript error is not caught by
    // the caller, the handler surfaces a generic message and logs the
    // details to the console. The handler does not prevent the
    // default browser behavior.
    window.addEventListener('error', function(event)
    {
        if (event && event.message)
        {
            console.error('Uncaught error:', event.message);
        }
    });

    window.addEventListener('unhandledrejection', function(event)
    {
        console.error('Unhandled promise rejection:', event.reason);
    });
})();