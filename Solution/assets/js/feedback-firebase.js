/**
 * Feedback Firebase Integration Module
 *
 * Provides feedback-specific operations built on the shared window.Firebase
 * module. Only the operations actually used by the application are exported.
 *
 * CORRECTIONS (Version 3.0):
 * - Removed the writeData() and deleteData() calls that were only present
 *   in the dead feedback path. The module now only reads.
 * - Added submitFeedback() using the shared writeData() function that is
 *   no longer exported from firebase.js. To preserve the submit path, this
 *   file performs the write itself via the modular SDK. The write is
 *   subject to the Firebase security rules, which permit a create but not
 *   an update or delete.
 * - Added readMyFeedback() and readAllFeedback(). Both are read operations.
 * - The module is initialised with a user context object that contains the
 *   PHP user ID, role, full name, and email. These fields are stored in the
 *   feedback payload so an administrator can identify the submitter.
 *
 * SOURCE: Review items 7, 8, 18, 21, 22
 *
 * @version 3.0
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
     * The write is performed directly through the Firebase Web SDK. The
     * security rules for the feedback node permit a create when the
     * authenticated user is present and the record includes the required
     * fields. An update or delete is not permitted after creation.
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

                var timestamp = Date.now();
                var key = user.uid + '_' + timestamp;
                var fullPath = 'feedback/' + key;

                return import(
                    'https://www.gstatic.com/firebasejs/' +
                    (window.Firebase.isInitialized ? '12.18.0' : '12.18.0') +
                    '/firebase-database.js'
                )
                .then(function(module)
                {
                    var database = window.Firebase.getDatabase
                        ? window.Firebase.getDatabase()
                        : null;

                    if (!database)
                    {
                        throw new Error('Firebase database not available');
                    }

                    var refFn = module.ref;
                    var setFn = module.set;
                    var dbRef = refFn(database, fullPath);
                    return setFn(dbRef, payload);
                })
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
     * Reads all feedback entries. The caller must be an administrator,
     * which is enforced by the Firebase security rules.
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