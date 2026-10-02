/**
 * Firebase Authentication Bridge
 *
 * Obtains a Firebase ID token for the currently signed-in application
 * user and stores it in the session through a server endpoint so that
 * server-side writes can supply the token to the Firebase REST API.
 *
 * The Firebase Realtime Database rules require auth != null for the
 * users, orders, feedback, and coupons nodes. When the PHP application
 * performs a server-side write on behalf of the current user, it must
 * supply that user's Firebase ID token. This bridge is the mechanism
 * by which the token is transferred from the browser, where the
 * Firebase Authentication SDK runs, to the PHP session.
 *
 * The bridge does not modify, bypass, weaken, or replace the database
 * rules. It supplies the token that the rules require.
 *
 * SOURCE: Existing Firebase Realtime Database rules.
 * SOURCE: Campus Eats Technical Audit Report, Section 4.
 *
 * @version 1.0
 */

(function()
{
    'use strict';

    /**
     * Stores the Firebase ID token in the server session.
     *
     * The token is posted to a server endpoint that places it in the
     * session. The token is short-lived. The bridge refreshes the token
     * periodically so the session copy remains valid.
     *
     * @param {string} idToken The Firebase ID token
     * @returns {Promise<void>}
     */
    function storeTokenInSession(idToken)
    {
        var baseUrl = (typeof window.BASE_URL !== 'undefined' && window.BASE_URL)
            ? window.BASE_URL
            : '';

        var url = baseUrl + '/api/store_firebase_token.php';

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ idToken: idToken })
        })
        .then(function(response)
        {
            if (!response.ok)
            {
                throw new Error('Failed to store Firebase token: ' + response.status);
            }
            return response.json();
        })
        .then(function(data)
        {
            if (!data.success)
            {
                throw new Error(data.message || 'Token store rejected.');
            }
        });
    }

    /**
     * Obtains the current ID token and stores it in the session.
     *
     * The function is called after the Firebase Authentication SDK has
     * signed the user in. It retrieves the token through the SDK's
     * getIdToken method, which refreshes the token when necessary.
     *
     * @returns {Promise<void>}
     */
    function syncToken()
    {
        if (typeof window.Firebase === 'undefined')
        {
            return Promise.resolve();
        }

        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    return null;
                }

                return user.getIdToken(true);
            })
            .then(function(idToken)
            {
                if (!idToken)
                {
                    return;
                }

                return storeTokenInSession(idToken);
            })
            .catch(function(error)
            {
                console.warn('Firebase auth bridge: ' + error.message);
            });
    }

    /**
     * Schedules periodic token refresh.
     *
     * Firebase ID tokens expire after one hour. The bridge refreshes
     * the session copy every fifty minutes so the server always holds
     * a token that is valid for at least a few minutes.
     */
    function scheduleRefresh()
    {
        setInterval(function()
        {
            syncToken();
        }, 50 * 60 * 1000);
    }

    document.addEventListener('DOMContentLoaded', function()
    {
        syncToken().then(scheduleRefresh);
    });

    document.addEventListener('firebase-auth-changed', function(event)
    {
        if (event.detail && event.detail.authenticated)
        {
            syncToken();
        }
    });

    window.FirebaseAuthBridge = {
        syncToken: syncToken,
        storeTokenInSession: storeTokenInSession
    };
})();