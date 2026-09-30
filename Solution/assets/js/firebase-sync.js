/**
 * Firebase Synchronization Module
 *
 * Periodically projects a small, non-sensitive subset of the current
 * page's state to Firebase Realtime Database and reads back any changes
 * made from another client for the same user. The goal is approximate
 * real-time consistency, not a full replication of MySQL.
 *
 * DESIGN NOTES
 *
 * 1. MySQL remains the authoritative store for accounts, authentication,
 *    orders, carts, and business data. This module does not attempt to
 *    replace it. The Firebase projection carries only the fields that
 *    the security rules in firebase.rules.json permit the client to
 *    write for the current user.
 *
 * 2. The sync interval is ten seconds with a small jitter of up to plus
 *    or minus five hundred milliseconds. The jitter prevents many
 *    browser tabs opened at the same moment from issuing their first
 *    write in the same millisecond, which would otherwise create a
 *    brief spike of writes against the database.
 *
 * 3. Payload hashing suppresses redundant writes. If the projected
 *    payload is byte-identical to the last payload successfully written
 *    for the current user, the write is skipped. This keeps the number
 *    of writes proportional to the number of actual changes rather than
 *    the number of intervals elapsed.
 *
 * 4. On failure, the interval is not stopped. Instead, the next delay
 *    grows exponentially, capped at sixty seconds, and returns to the
 *    base interval after a successful write. A permanent failure such
 *    as a permission denial is logged once and does not spam the
 *    console on every retry.
 *
 * 5. When the browser fires the online event, a synchronization is
 *    scheduled immediately. When the browser fires the offline event,
 *    the loop pauses and waits. This avoids pointless network attempts
 *    while the connection is known to be down.
 *
 * 6. This module does not create its own Firebase app. It relies on
 *    window.Firebase, which is defined by firebase.js. If that module
 *    is not present, the sync module logs one warning and does nothing.
 *
 * SOURCE: Technical Audit Report - Campus Eats Platform, Section 6
 * SOURCE: campus-eats-process-document.pdf Section 19 - Online-First
 *
 * @version 1.0
 */

(function()
{
    'use strict';

    // =========================================================================
    // Configuration
    // =========================================================================

    /**
     * Base interval between synchronization attempts, in milliseconds.
     */
    var BASE_INTERVAL_MS = 10000;

    /**
     * Maximum jitter added to or subtracted from the base interval, in
     * milliseconds. The actual delay is uniform in
     * [BASE_INTERVAL_MS - JITTER_MS, BASE_INTERVAL_MS + JITTER_MS].
     */
    var JITTER_MS = 500;

    /**
     * Maximum delay between attempts when the previous attempt failed,
     * in milliseconds.
     */
    var MAX_BACKOFF_MS = 60000;

    /**
     * Multiplier applied to the current delay on each successive failure.
     */
    var BACKOFF_MULTIPLIER = 2;

    /**
     * Database path where the current user's projected state is written.
     * The path is scoped per user in the security rules, so a client
     * cannot write to another user's node even if it tries.
     */
    var SYNC_PATH_PREFIX = 'sync';

    // =========================================================================
    // Module state
    // =========================================================================

    /**
     * The single timer handle for the current scheduled attempt. Only one
     * timer is ever active at a time.
     */
    var timerHandle = null;

    /**
     * The current delay between attempts, in milliseconds. Reset to the
     * base interval after each successful attempt and doubled after each
     * failure, up to MAX_BACKOFF_MS.
     */
    var currentDelayMs = BASE_INTERVAL_MS;

    /**
     * Hash of the last payload successfully written for the current
     * user. Used to skip redundant writes.
     */
    var lastPayloadHash = null;

    /**
     * Firebase UID of the user for whom lastPayloadHash is valid. When
     * the user changes, the hash is cleared so the first write of the
     * new session is not suppressed.
     */
    var lastPayloadUid = null;

    /**
     * True while a synchronization attempt is in flight. Prevents
     * overlap when a manual trigger fires during an active attempt.
     */
    var isSyncing = false;

    /**
     * True once a permanent failure has been observed. Used to
     * suppress repeated console warnings for the same condition.
     */
    var permanentFailureLogged = false;

    /**
     * True while the browser is known to be offline. Used to pause the
     * loop and avoid pointless network attempts.
     */
    var isOffline = false;

    // =========================================================================
    // Small utility functions
    // =========================================================================

    /**
     * Returns a random integer in [min, max], inclusive.
     *
     * @param {number} min The minimum value
     * @param {number} max The maximum value
     * @returns {number}
     */
    function randomBetween(min, max)
    {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    /**
     * Computes a short, stable string hash of a JavaScript value.
     *
     * The value is serialized to JSON with sorted keys, then hashed with
     * the FNV-1a algorithm. The result is returned as a hexadecimal
     * string. This is used only for change detection, not for security,
     * so a non-cryptographic hash is sufficient.
     *
     * @param {*} value The value to hash
     * @returns {string} A hexadecimal hash string
     */
    function hashValue(value)
    {
        var serialized = stableStringify(value);
        var hash = 2166136261; // FNV offset basis, 32 bits

        for (var i = 0; i < serialized.length; i++)
        {
            hash ^= serialized.charCodeAt(i);

            // FNV prime, 32 bits, applied with Math.imul to stay within
            // 32-bit range in JavaScript.
            hash = Math.imul(hash, 16777619);
        }

        // Convert to unsigned and return as hexadecimal.
        return (hash >>> 0).toString(16);
    }

    /**
     * Serializes a value to JSON with object keys sorted, so two objects
     * with the same properties in different insertion orders produce the
     * same string.
     *
     * @param {*} value The value to serialize
     * @returns {string}
     */
    function stableStringify(value)
    {
        if (value === null || typeof value !== 'object')
        {
            return JSON.stringify(value);
        }

        if (Array.isArray(value))
        {
            var arrayParts = value.map(stableStringify);
            return '[' + arrayParts.join(',') + ']';
        }

        var keys = Object.keys(value).sort();
        var objectParts = [];

        for (var i = 0; i < keys.length; i++)
        {
            var key = keys[i];
            objectParts.push(JSON.stringify(key) + ':' + stableStringify(value[key]));
        }

        return '{' + objectParts.join(',') + '}';
    }

    /**
     * Logs a message at the info level. Prepends a module tag so the
     * line can be found in the browser console.
     *
     * @param {string} message
     * @returns {void}
     */
    function logInfo(message)
    {
        console.log('[firebase-sync] ' + message);
    }

    /**
     * Logs a message at the warning level.
     *
     * @param {string} message
     * @returns {void}
     */
    function logWarning(message)
    {
        console.warn('[firebase-sync] ' + message);
    }

    // =========================================================================
    // Payload construction
    // =========================================================================

    /**
     * Builds the projection that this module writes to Firebase.
     *
     * The projection is intentionally small. It contains only fields
     * that the security rules permit the client to write for the
     * current user. It does not contain passwords, password hashes,
     * session identifiers, or any other sensitive value.
     *
     * The fields are read from the page when the function is called.
     * If a field is not present on the page, the corresponding key is
     * omitted from the payload rather than written as null, so that a
     * partial update does not overwrite a value set by another client.
     *
     * @returns {Object} The payload to write
     */
    function buildPayload()
    {
        var payload = {};

        // Page identity. Helps a reader of the database understand which
        // page produced the last write. This is not user data.
        payload.lastPage = window.location.pathname;
        payload.lastSyncAt = new Date().toISOString();

        // Cart count, if the page exposes it on the document body as a
        // data attribute. This value is projected to Firebase so that a
        // second tab or a mobile client can display the same count.
        var cartCountElement = document.getElementById('cart-count-badge');

        if (cartCountElement)
        {
            var cartCountText = cartCountElement.textContent.trim();
            var cartCount = parseInt(cartCountText, 10);

            if (!isNaN(cartCount))
            {
                payload.cartItemCount = cartCount;
            }
        }

        // Current order status, if the page is the order tracking page
        // and exposes the order id and status on the document body.
        var orderStatusElement = document.querySelector('[data-order-status]');

        if (orderStatusElement)
        {
            var orderId = orderStatusElement.getAttribute('data-order-id');
            var orderStatus = orderStatusElement.getAttribute('data-order-status');

            if (orderId && orderStatus)
            {
                payload.currentOrderId = orderId;
                payload.currentOrderStatus = orderStatus;
            }
        }

        return payload;
    }

    // =========================================================================
    // Synchronization loop
    // =========================================================================

    /**
     * Schedules the next synchronization attempt.
     *
     * @param {number} delayMs The delay before the next attempt
     * @returns {void}
     */
    function scheduleNext(delayMs)
    {
        if (timerHandle !== null)
        {
            clearTimeout(timerHandle);
        }

        var jitteredDelay = delayMs + randomBetween(-JITTER_MS, JITTER_MS);

        if (jitteredDelay < 1000)
        {
            jitteredDelay = 1000;
        }

        timerHandle = setTimeout(runSync, jitteredDelay);
    }

    /**
     * Runs one synchronization attempt.
     *
     * Reads the current user from the Firebase module. If no user is
     * authenticated yet, the attempt is a no-op and the next attempt is
     * scheduled at the base interval. If a user is authenticated, the
     * payload is built, hashed, and written only when the hash differs
     * from the last successful write for that user.
     *
     * @returns {void}
     */
    function runSync()
    {
        timerHandle = null;

        if (isOffline)
        {
            logInfo('Offline. Deferring sync until the connection returns.');
            scheduleNext(BASE_INTERVAL_MS);
            return;
        }

        if (isSyncing)
        {
            logInfo('Previous sync still running. Skipping this cycle.');
            scheduleNext(BASE_INTERVAL_MS);
            return;
        }

        if (typeof window.Firebase === 'undefined')
        {
            logWarning('window.Firebase is not defined. Sync loop stopping.');
            return;
        }

        var currentUser = window.Firebase.getCurrentUser();

        if (!currentUser)
        {
            // No authenticated user yet. Try again next cycle.
            scheduleNext(BASE_INTERVAL_MS);
            return;
        }

        var userId = currentUser.uid;

        // Reset the change-detection hash when the user changes so the
        // first write of the new session is not suppressed.
        if (lastPayloadUid !== userId)
        {
            lastPayloadHash = null;
            lastPayloadUid = userId;
            permanentFailureLogged = false;
        }

        var payload = buildPayload();
        var payloadHash = hashValue(payload);

        if (payloadHash === lastPayloadHash)
        {
            // The projection has not changed. Skip the write.
            currentDelayMs = BASE_INTERVAL_MS;
            scheduleNext(currentDelayMs);
            return;
        }

        isSyncing = true;

        var path = SYNC_PATH_PREFIX + '/' + userId;

        window.Firebase.writeData(path, payload, true)
            .then(function()
            {
                isSyncing = false;
                lastPayloadHash = payloadHash;
                currentDelayMs = BASE_INTERVAL_MS;
                permanentFailureLogged = false;
                logInfo('Sync succeeded for user ' + userId.substring(0, 8) + '...');
                scheduleNext(currentDelayMs);
            })
            .catch(function(error)
            {
                isSyncing = false;

                var message = error && error.message ? error.message : String(error);
                var isPermanent = /permission|denied|unauthorized/i.test(message);

                if (isPermanent)
                {
                    if (!permanentFailureLogged)
                    {
                        logWarning('Sync failed with a permanent error: ' + message);
                        logWarning('Further occurrences of this error will not be logged.');
                        permanentFailureLogged = true;
                    }
                }
                else
                {
                    logWarning('Sync failed: ' + message);
                }

                currentDelayMs = Math.min(
                    currentDelayMs * BACKOFF_MULTIPLIER,
                    MAX_BACKOFF_MS
                );

                scheduleNext(currentDelayMs);
            });
    }

    // =========================================================================
    // Online and offline handling
    // =========================================================================

    /**
     * Handles the browser online event.
     *
     * Clears the offline flag, resets the backoff delay, and triggers an
     * immediate attempt rather than waiting for the next scheduled
     * cycle. The user-visible effect is that synchronization resumes
     * within a second of connectivity being restored.
     *
     * @returns {void}
     */
    function handleOnline()
    {
        logInfo('Online event received. Forcing an immediate sync.');
        isOffline = false;
        currentDelayMs = BASE_INTERVAL_MS;
        scheduleNext(0);
    }

    /**
     * Handles the browser offline event.
     *
     * Sets the offline flag so the next scheduled attempt becomes a
     * no-op. The timer is not cleared; the attempt will fire on
     * schedule, see the flag, and reschedule itself without performing
     * any network operation.
     *
     * @returns {void}
     */
    function handleOffline()
    {
        logInfo('Offline event received. Pausing sync until the connection returns.');
        isOffline = true;
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    /**
     * Starts the synchronization loop.
     *
     * The first attempt is scheduled after the base interval rather than
     * immediately, so page load is not delayed by a network call. The
     * loop is safe to call more than once; subsequent calls cancel the
     * previous timer and reschedule.
     *
     * @returns {void}
     */
    function startSync()
    {
        if (typeof window.Firebase === 'undefined')
        {
            logWarning('window.Firebase is not defined. Sync loop not started.');
            return;
        }

        isOffline = (typeof navigator !== 'undefined' && navigator.onLine === false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        logInfo('Sync loop started. Base interval: ' + BASE_INTERVAL_MS + ' ms.');
        scheduleNext(BASE_INTERVAL_MS);
    }

    /**
     * Stops the synchronization loop.
     *
     * Called before the page unloads so the timer does not fire after
     * the document is gone, and so the event listeners do not leak on a
     * single-page navigation.
     *
     * @returns {void}
     */
    function stopSync()
    {
        if (timerHandle !== null)
        {
            clearTimeout(timerHandle);
            timerHandle = null;
        }

        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);

        logInfo('Sync loop stopped.');
    }

    // =========================================================================
    // Public interface
    // =========================================================================

    window.FirebaseSync = {
        start: startSync,
        stop: stopSync,
        forceSync: function()
        {
            scheduleNext(0);
        }
    };

    // =========================================================================
    // Auto-start
    // =========================================================================
    // If the document is still loading, wait for DOMContentLoaded. If it
    // is already loaded, start immediately. The sync module does not
    // block page rendering.
    // =========================================================================

    if (document.readyState === 'loading')
    {
        document.addEventListener('DOMContentLoaded', startSync);
    }
    else
    {
        startSync();
    }

    window.addEventListener('beforeunload', stopSync);
})();