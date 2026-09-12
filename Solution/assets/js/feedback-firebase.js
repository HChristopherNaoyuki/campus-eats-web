/**
 * Feedback Firebase Integration Module
 *
 * Provides feedback-specific operations that build on the shared
 * window.Firebase module.
 *
 * CORRECTIONS (Version 2.0):
 * - Uses window.Firebase.ensureAuthenticated with the corrected signature
 * - Respects the corrected default handling for boolean flags
 * - Added submitFeedback and readMyFeedback and readAllFeedback
 *
 * SOURCE: Issue report - items 18, 21, 22
 *
 * @version 2.0
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
     * @param {Object} userContext
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
     * @param {Object} feedbackData
     * @returns {Promise<string>} The feedback ID
     */
    function submitFeedback(feedbackData)
    {
        if (!feedbackData || !feedbackData.type ||
            !feedbackData.subject || !feedbackData.message)
        {
            return Promise.reject(new Error('All feedback fields are required'));
        }

        return window.Firebase.ensureAuthenticated(true)
            .then(function(user)
            {
                if (!user)
                {
                    throw new Error('Could not authenticate with Firebase');
                }

                var payload = {
                    userId: user.uid,
                    type: feedbackData.type,
                    subject: feedbackData.subject,
                    message: feedbackData.message,
                    userName: currentPHPUserName || 'User',
                    userEmail: currentPHPUserEmail || '',
                    phpUserId: currentPHPUserId || '',
                    phpUserRole: currentPHPUserRole || '',
                    status: FEEDBACK_STATUS.PENDING,
                    createdAt: new Date().toISOString(),
                    updatedAt: new Date().toISOString()
                };

                var path = 'feedback';
                var timestamp = Date.now();
                var key = user.uid + '_' + timestamp;

                return window.Firebase.writeData(path, payload, key, true)
                    .then(function()
                    {
                        return key;
                    });
            });
    }

    /**
     * Reads feedback for the current authenticated user.
     *
     * @returns {Promise<Array>}
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
     * Reads all feedback entries. Caller must have read permission
     * according to the Firebase security rules.
     *
     * @returns {Promise<Array>}
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