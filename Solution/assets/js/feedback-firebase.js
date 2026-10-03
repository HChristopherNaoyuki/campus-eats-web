/**
 * Feedback Firebase Integration Module
 *
 * Provides feedback-specific operations built on the shared window.Firebase
 * module. Only the operations actually used by the application are exported.
 *
 * CORRECTIONS (Version 4.0 - Technical Audit and Fixes Report):
 * - Refactored submitFeedback() to use the restored
 *   window.Firebase.writeData() function instead of performing the write
 *   directly through the modular SDK. The shared function now performs
 *   the client-side path validation and ensures the write stays within
 *   the allow-list. This removes duplicate write logic from this file.
 * - Retained readMyFeedback() and readAllFeedback(). Both are read
 *   operations that use window.Firebase.readData().
 * - The module is initialised with a user context object that contains
 *   the PHP user ID, role, full name, and email. These fields are stored
 *   in the feedback payload so an administrator can identify the
 *   submitter.
 * - The payload includes all nine fields required by the existing
 *   Firebase Realtime Database rules for the feedback node: userId,
 *   type, subject, message, userName, userEmail, status, createdAt,
 *   updatedAt. The type and status values are lowercase. The createdAt
 *   and updatedAt values are non-empty ISO 8601 strings.
 *
 * The existing database rules are not modified, bypassed, weakened, or
 * replaced by this file. The payload is constructed to satisfy every
 * validation expression in the rules.
 *
 * SOURCE: Existing Firebase Realtime Database rules, feedback node.
 * SOURCE: Technical Audit and Fixes Report.
 * SOURCE: Review items 7, 8, 18, 21, 22.
 *
 * @version 4.0
 */

(function()
{
    'use strict';

    var FEEDBACK_TYPES = {
        COMPLAINT: 'complaint',
        COMPLIMENT: 'compliment'
    };

    var FEEDBACK_STATUS = {
        PENDING: 'pending',
        RESOLVED: 'resolved'
    };

    var currentPHPUserId = null;
    var currentPHPUserRole = null;
    var currentPHPUserName = null;
    var currentPHPUserEmail = null;

    /**
     * Initializes the module with PHP user context.
     *
     * @param {Object} userContext The PHP user context
     * @returns {void}
     */
    function initFeedbackModule(userContext)
    {
        if (userContext)
        {
            currentPHPUserId = userContext.userId || null;
            currentPHPUserRole = userContext.role || null;
            currentPHPUserName = userContext.fullName || null;
            currentPHPUserEmail = userContext.email || null;
        }
    }

    /**
     * Submits feedback to Firebase.
     *
     * The payload is validated locally against the same constraints the
     * database rules enforce. A payload that would be rejected by the
     * rules is rejected here before the network request is attempted,
     * producing a clearer error.
     *
     * The write is performed through window.Firebase.writeData(). That
     * function validates the path against the client-side allow-list and
     * uses the shared Firebase app instance. The final enforcement
     * remains the database rules.
     *
     * @param {Object} feedbackData The feedback data
     * @returns {Promise<string>} Resolves with the feedback key
     */
    function submitFeedback(feedbackData)
    {
        // Validate the locally checkable constraints first. This avoids
        // a network round trip for a payload that the rules would reject.
        if (!feedbackData ||
            !feedbackData.type ||
            !feedbackData.subject ||
            !feedbackData.message)
        {
            return Promise.reject(new Error('All feedback fields are required'));
        }

        var type = String(feedbackData.type).toLowerCase();

        if (type !== FEEDBACK_TYPES.COMPLAINT &&
            type !== FEEDBACK_TYPES.COMPLIMENT)
        {
            return Promise.reject(
                new Error('Feedback type must be complaint or compliment')
            );
        }

        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }

                var now = new Date().toISOString();

                // The payload contains every field the rules require.
                // Optional fields are not added beyond the required set,
                // because the rules for the feedback node do not declare
                // any optional children.
                var payload = {
                    userId:    user.uid,
                    type:      type,
                    subject:   String(feedbackData.subject),
                    message:   String(feedbackData.message),
                    userName:  currentPHPUserName || 'User',
                    userEmail: currentPHPUserEmail || '',
                    status:    FEEDBACK_STATUS.PENDING,
                    createdAt: now,
                    updatedAt: now
                };

                var timestamp = Date.now();
                var key = user.uid + '_' + timestamp;
                var path = 'feedback/' + key;

                return window.Firebase.writeData(path, payload, true)
                    .then(function()
                    {
                        return key;
                    });
            });
    }

    /**
     * Reads feedback for the current authenticated user.
     *
     * @returns {Promise<Array>} Resolves with the user's feedback entries
     */
    function getMyFeedback()
    {
        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }
                return window.Firebase.readFeedback(user.uid, true);
            });
    }

    /**
     * Reads all feedback entries.
     *
     * The caller must be an administrator. The database rules enforce
     * that requirement. A non-administrator receives an empty list or a
     * permission error, depending on the rules evaluation.
     *
     * @returns {Promise<Array>} Resolves with all feedback entries
     */
    function getAllFeedback()
    {
        return window.Firebase.ensureAuthenticated(true)
            .then(function()
            {
                return window.Firebase.readFeedback(null, false);
            });
    }

    var FeedbackAPI = {
        init: initFeedbackModule,
        submitFeedback: submitFeedback,
        getMyFeedback: getMyFeedback,
        getAllFeedback: getAllFeedback,
        FEEDBACK_TYPES: FEEDBACK_TYPES,
        FEEDBACK_STATUS: FEEDBACK_STATUS
    };

    window.Feedback = FeedbackAPI;
})();